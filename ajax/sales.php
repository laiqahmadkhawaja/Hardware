<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();
require_permission('sales');

$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$action = clean_input($_POST['action'] ?? ($json_data['action'] ?? ($_GET['action'] ?? '')));

if ($action === 'create_sale') {
    $data = $json_data ?: $_POST;

    $customer_id = (int)($data['customer_id'] ?? 1);
    $items = $data['items'] ?? [];
    $discount_amount = (float)($data['discount_amount'] ?? 0);
    $paid_amount = (float)($data['paid_amount'] ?? 0);
    $payment_method = clean_input($data['payment_method'] ?? 'cash');
    $account_id = (int)($data['account_id'] ?? 1);
    $notes = clean_input($data['notes'] ?? '');

    if (empty($items)) {
        json_response(false, 'Cart is empty. Please add items to checkout.');
    }

    $conn->begin_transaction();
    try {
        $subtotal = 0.00;
        $validated_items = [];

        // Verify items & stock availability server-side
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (float)$item['qty'];
            $price = (float)$item['price'];

            if ($qty <= 0) continue;

            $p_stmt = $conn->prepare("SELECT p.id, p.name, p.cost_price, p.base_unit_id, IFNULL(sb.quantity, 0) as current_stock 
                                      FROM products p 
                                      LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.warehouse_id = 1
                                      WHERE p.id = ? FOR UPDATE");
            $p_stmt->bind_param("i", $pid);
            $p_stmt->execute();
            $p_data = $p_stmt->get_result()->fetch_assoc();

            if (!$p_data) {
                throw new Exception("Product ID #$pid not found.");
            }

            // Verify stock
            if ($p_data['current_stock'] < $qty) {
                throw new Exception("Insufficient stock for " . $p_data['name'] . ". Available: " . $p_data['current_stock']);
            }

            $line_total = $qty * $price;
            $subtotal += $line_total;

            $validated_items[] = [
                'product_id' => $pid,
                'unit_id' => $p_data['base_unit_id'],
                'qty' => $qty,
                'price' => $price,
                'cost_price' => (float)$p_data['cost_price'],
                'line_total' => $line_total,
                'current_stock' => (float)$p_data['current_stock']
            ];
        }

        if (empty($validated_items)) {
            throw new Exception("No valid items in sale.");
        }

        $grand_total = max(0, $subtotal - $discount_amount);
        $due_amount = max(0, $grand_total - $paid_amount);

        $payment_status = 'paid';
        if ($paid_amount <= 0 && $grand_total > 0) {
            $payment_status = 'due';
        } elseif ($paid_amount < $grand_total) {
            $payment_status = 'partial';
        }

        // Generate Invoice #
        $invoice_no = generate_reference_no($conn, 'sales', 'INV', 'invoice_no');
        $user_id = $_SESSION['user_id'];
        $today = date('Y-m-d');
        $now_time = date('H:i:s');

        // Insert Sale
        $sale_stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, warehouse_id, user_id, date, time, subtotal, discount_amount, grand_total, paid_amount, due_amount, payment_status, notes) 
                                    VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $sale_stmt->bind_param("siissdddddss", $invoice_no, $customer_id, $user_id, $today, $now_time, $subtotal, $discount_amount, $grand_total, $paid_amount, $due_amount, $payment_status, $notes);
        $sale_stmt->execute();
        $sale_id = $conn->insert_id;

        // Insert Sale Items & Deduct Stock
        foreach ($validated_items as $v) {
            $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, unit_id, quantity, base_quantity, unit_price, cost_price, total) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $item_stmt->bind_param("iiiddddd", $sale_id, $v['product_id'], $v['unit_id'], $v['qty'], $v['qty'], $v['price'], $v['cost_price'], $v['line_total']);
            $item_stmt->execute();

            // Deduct stock
            $new_stock = $v['current_stock'] - $v['qty'];
            $stock_stmt = $conn->prepare("UPDATE stock_balances SET quantity = ? WHERE product_id = ? AND warehouse_id = 1");
            $stock_stmt->bind_param("di", $new_stock, $v['product_id']);
            $stock_stmt->execute();

            // Record Movement
            $sm_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, warehouse_id, transaction_type, reference_no, qty_in, qty_out, balance_after, unit_cost, notes, user_id) 
                                       VALUES (?, 1, 'sale', ?, 0, ?, ?, ?, 'POS Sale Invoice', ?)");
            $sm_stmt->bind_param("isdddi", $v['product_id'], $invoice_no, $v['qty'], $new_stock, $v['cost_price'], $user_id);
            $sm_stmt->execute();
        }

        // Process Customer Khata & Balances
        $cust_stmt = $conn->prepare("SELECT current_balance, credit_limit, name FROM customers WHERE id = ? FOR UPDATE");
        $cust_stmt->bind_param("i", $customer_id);
        $cust_stmt->execute();
        $cust = $cust_stmt->get_result()->fetch_assoc();

        if ($cust) {
            $running = (float)$cust['current_balance'];
            
            // 1. Debit customer for grand total
            $running += $grand_total;
            $ld_stmt = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                                       VALUES (?, ?, 'sale', ?, 'Sales Invoice', ?, 0, ?)");
            $ld_stmt->bind_param("issdd", $customer_id, $today, $invoice_no, $grand_total, $running);
            $ld_stmt->execute();

            // 2. If paid > 0, Credit customer & record payment
            if ($paid_amount > 0) {
                $running -= $paid_amount;
                $lc_stmt = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, transaction_type, reference_no, description, debit, credit, running_balance) 
                                           VALUES (?, ?, 'payment', ?, 'Receipt Against Invoice', 0, ?, ?)");
                $lc_stmt->bind_param("issdd", $customer_id, $today, $invoice_no, $paid_amount, $running);
                $lc_stmt->execute();

                // Payment record
                $pay_stmt = $conn->prepare("INSERT INTO sale_payments (sale_id, customer_id, account_id, payment_method, amount, date, reference_no) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                $pay_stmt->bind_param("iiisdss", $sale_id, $customer_id, $account_id, $payment_method, $paid_amount, $today, $invoice_no);
                $pay_stmt->execute();

                // Increment Financial Account Balance
                $acc_up = $conn->prepare("UPDATE financial_accounts SET balance = balance + ? WHERE id = ?");
                $acc_up->bind_param("di", $paid_amount, $account_id);
                $acc_up->execute();
            }

            // Update customer balance
            $c_up = $conn->prepare("UPDATE customers SET current_balance = ? WHERE id = ?");
            $c_up->bind_param("di", $running, $customer_id);
            $c_up->execute();
        }

        $conn->commit();
        log_audit($conn, 'CREATE_SALE', $invoice_no, "Sale invoice generated: Grand Total $grand_total, Paid $paid_amount");

        json_response(true, 'Sale invoice created successfully!', [
            'invoice_id' => $sale_id,
            'invoice_no' => $invoice_no
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        json_response(false, $e->getMessage());
    }
}

json_response(false, 'Invalid action');
