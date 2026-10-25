<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

check_auth();
require_permission('pos');

$customers = $conn->query("SELECT id, name, name_ur, phone, current_balance, credit_limit FROM customers WHERE status = 'active' ORDER BY name ASC");
$accounts = $conn->query("SELECT id, name, name_ur, account_type FROM financial_accounts ORDER BY id ASC");

$page_title = __('pos');
include __DIR__ . '/includes/header.php';
?>

<div class="pos-container">
    <div class="row g-3 h-100">
        <!-- Left Column: Search & Current Cart Items -->
        <div class="col-12 col-lg-8 d-flex flex-column">
            <!-- Search & Barcode input -->
            <div class="card card-custom mb-3 p-3 position-relative">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white"><i class="bi bi-upc-scan fs-5"></i></span>
                    <input type="text" id="productSearch" class="form-control form-control-lg" placeholder="Scan barcode or type hardware item (e.g. Saria, Cement, Elbow, Screws)..." autofocus autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch" title="Clear Search"><i class="bi bi-x-lg"></i></button>
                    <button class="btn btn-outline-primary px-3 d-flex align-items-center gap-1" type="button" id="btnOpenScanner" title="Scan Barcode using Camera">
                        <i class="bi bi-camera-fill fs-5"></i>
                        <span class="d-none d-sm-inline fw-semibold">Camera</span>
                    </button>
                </div>
                <!-- Dropdown Search Results -->
                <div id="searchResults" class="search-results-floating d-none"></div>
            </div>

            <!-- Cart Table Card -->
            <div class="card card-custom flex-grow-1 d-flex flex-column mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-cart3 me-1"></i> Current Invoice Items</span>
                    <button class="btn btn-sm btn-outline-danger" id="btnClearCart"><i class="bi bi-trash me-1"></i> Clear Cart</button>
                </div>
                <div class="table-responsive pos-cart-table-wrapper flex-grow-1 p-0">
                    <table class="table table-hover align-middle mb-0" id="cartTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 35%;">Item Name</th>
                                <th style="width: 20%;">Price (PKR)</th>
                                <th style="width: 25%;">Qty / Units</th>
                                <th style="width: 15%;">Total</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyCartRow">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                                    <?= __('empty_cart') ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer Khata, Payment & Checkout -->
        <div class="col-12 col-lg-4">
            <div class="card card-custom h-100 d-flex flex-column p-3" id="paymentCard">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person-check-fill me-1 text-primary"></i> Customer & Payment</h6>
                
                <!-- Customer Selection -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">Select Customer (کھاتہ دار)</label>
                    <select id="customerId" class="form-select">
                        <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" data-balance="<?= $c['current_balance'] ?>" data-limit="<?= $c['credit_limit'] ?>">
                                <?= clean_input($c['name']) ?> (<?= clean_input($c['phone']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="small mt-1 text-muted d-flex justify-content-between">
                        <span>Current Khata Due:</span>
                        <span id="customerBalanceBadge" class="fw-bold text-danger">PKR 0.00</span>
                    </div>
                </div>

                <!-- Price Level selection -->
                <div class="mb-3">
                    <label class="form-label small fw-bold">Price Tier</label>
                    <select id="priceTier" class="form-select form-select-sm">
                        <option value="retail">Retail Rate (پرچون)</option>
                        <option value="wholesale">Wholesale Rate (تھوک)</option>
                        <option value="contractor">Contractor Rate (ٹھیکیدار)</option>
                    </select>
                </div>

                <!-- Financial Calculations -->
                <div class="bg-light p-3 rounded mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-bold fs-6" id="lblSubtotal">PKR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Discount (PKR):</span>
                        <input type="number" id="txtDiscount" class="form-control form-control-sm text-end" style="width: 110px;" value="0" min="0">
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-5">Grand Total:</span>
                        <span class="fw-bold fs-4 text-primary" id="lblGrandTotal">PKR 0.00</span>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select id="paymentMethod" class="form-select form-select-sm">
                                <option value="cash">Cash (نقد)</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Deposit Account</label>
                            <select id="accountId" class="form-select form-select-sm">
                                <?php while ($a = $accounts->fetch_assoc()): ?>
                                    <option value="<?= $a['id'] ?>"><?= clean_input($a['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Received Amount (PKR)</label>
                    <div class="input-group">
                        <input type="number" id="txtPaidAmount" class="form-control form-control-lg fw-bold text-success" value="0" min="0">
                        <button class="btn btn-outline-secondary" type="button" id="btnExactPaid">Full</button>
                    </div>
                </div>

                <div class="d-flex justify-content-between small text-muted mb-3 px-1">
                    <span>Due on Khata: <strong id="lblDueAmount" class="text-danger">PKR 0.00</strong></span>
                    <span>Change: <strong id="lblChangeAmount" class="text-success">PKR 0.00</strong></span>
                </div>

                <!-- Checkout Button -->
                <div class="mt-auto">
                    <button class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow" id="btnCheckout">
                        <i class="bi bi-printer-fill me-1"></i> Complete & Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

// Customer Balance Update
function updateCustomerInfo() {
    const opt = $('#customerId option:selected');
    const bal = parseFloat(opt.data('balance') || 0);
    $('#customerBalanceBadge').text('PKR ' + bal.toFixed(2));
}
$('#customerId').on('change', updateCustomerInfo);
updateCustomerInfo();

// Search Product AJAX with debounce & direct click handling
let searchTimeout = null;
let currentProducts = [];

$('#productSearch').on('input', function() {
    clearTimeout(searchTimeout);
    const term = $(this).val().trim();
    if (term.length < 1) {
        $('#searchResults').addClass('d-none').html('');
        currentProducts = [];
        return;
    }

    searchTimeout = setTimeout(function() {
        $.ajax({
            url: '<?= BASE_URL ?>/ajax/products.php?action=search&term=' + encodeURIComponent(term),
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.success && res.data.length > 0) {
                    currentProducts = res.data;
                    let html = '';
                    res.data.forEach((p, idx) => {
                        const stockQty = parseFloat(p.stock_qty || 0);
                        const price = parseFloat(p.retail_price || 0);
                        const brandName = p.brand_name ? ` | ${p.brand_name}` : '';
                        const catName = p.category_name ? ` (${p.category_name})` : '';
                        html += `
                            <div class="search-result-item" data-index="${idx}">
                                <div>
                                    <div class="fw-bold">${p.name}</div>
                                    <small class="text-muted font-monospace">${p.code}${brandName}${catName} | Stock: ${stockQty} ${p.unit_name}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary fs-6">PKR ${price.toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    });
                    $('#searchResults').html(html).removeClass('d-none');
                } else {
                    currentProducts = [];
                    $('#searchResults').html('<div class="p-3 text-muted text-center"><i class="bi bi-info-circle me-1"></i> No matching products found</div>').removeClass('d-none');
                }
            },
            error: function(err) {
                console.error("Search AJAX Error:", err);
            }
        });
    }, 250);
});

// Event delegation for clicking search result items (avoids string escaping errors with quotes/inch marks)
$(document).on('click', '.search-result-item', function() {
    const idx = $(this).data('index');
    if (idx !== undefined && currentProducts[idx]) {
        addToCart(currentProducts[idx]);
    }
});

// Enter key in search input selects the first product if exact or single match
$('#productSearch').on('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (currentProducts.length > 0) {
            addToCart(currentProducts[0]);
        }
    }
});

// Hide dropdown when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#productSearch, #searchResults').length) {
        $('#searchResults').addClass('d-none');
    }
});

$('#clearSearch').click(function() {
    $('#productSearch').val('').focus();
    $('#searchResults').addClass('d-none').html('');
    currentProducts = [];
});

// Add to Cart
function addToCart(product) {
    const existing = cart.find(i => i.product_id === parseInt(product.id));
    const tier = $('#priceTier').val();
    let unitPrice = parseFloat(product.retail_price);
    if (tier === 'wholesale') unitPrice = parseFloat(product.wholesale_price || product.retail_price);
    if (tier === 'contractor') unitPrice = parseFloat(product.contractor_price || product.retail_price);

    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({
            product_id: parseInt(product.id),
            name: product.name,
            code: product.code,
            price: unitPrice,
            qty: 1,
            unit_name: product.unit_name,
            max_stock: parseFloat(product.stock_qty)
        });
    }

    $('#productSearch').val('');
    $('#searchResults').addClass('d-none');
    renderCart();
}

function renderCart() {
    if (cart.length === 0) {
        $('#cartBody').html(`
            <tr id="emptyCartRow">
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                    <?= __('empty_cart') ?>
                </td>
            </tr>
        `);
        recalculateTotals();
        return;
    }

    let rows = '';
    cart.forEach((item, idx) => {
        const lineTotal = item.qty * item.price;
        rows += `
            <tr>
                <td>
                    <div class="fw-semibold">${item.name}</div>
                    <small class="text-muted font-monospace">${item.code}</small>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm text-end" value="${item.price}" onchange="updatePrice(${idx}, this.value)">
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" min="0.01" max="${item.max_stock}" class="form-control text-center" value="${item.qty}" onchange="updateQty(${idx}, this.value)">
                        <span class="input-group-text">${item.unit_name}</span>
                    </div>
                </td>
                <td class="fw-bold text-end">PKR ${lineTotal.toFixed(2)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(${idx})"><i class="bi bi-x-circle fs-5"></i></button>
                </td>
            </tr>
        `;
    });

    $('#cartBody').html(rows);
    recalculateTotals();
}

function updateQty(idx, val) {
    cart[idx].qty = Math.max(0.01, parseFloat(val) || 1);
    renderCart();
}

function updatePrice(idx, val) {
    cart[idx].price = Math.max(0, parseFloat(val) || 0);
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx, 1);
    renderCart();
}

$('#btnClearCart').click(function() {
    cart = [];
    renderCart();
});

function recalculateTotals() {
    let subtotal = 0;
    cart.forEach(i => subtotal += (i.qty * i.price));

    const discount = parseFloat($('#txtDiscount').val()) || 0;
    const grandTotal = Math.max(0, subtotal - discount);

    $('#lblSubtotal').text('PKR ' + subtotal.toFixed(2));
    $('#lblGrandTotal').text('PKR ' + grandTotal.toFixed(2));
    $('#mobileGrandTotal').text('PKR ' + grandTotal.toFixed(2));

    const paid = parseFloat($('#txtPaidAmount').val()) || 0;
    const due = Math.max(0, grandTotal - paid);
    const change = Math.max(0, paid - grandTotal);

    $('#lblDueAmount').text('PKR ' + due.toFixed(2));
    $('#lblChangeAmount').text('PKR ' + change.toFixed(2));
}

$('#txtDiscount, #txtPaidAmount').on('input', recalculateTotals);

$('#btnExactPaid').click(function() {
    let subtotal = 0;
    cart.forEach(i => subtotal += (i.qty * i.price));
    const discount = parseFloat($('#txtDiscount').val()) || 0;
    const grandTotal = Math.max(0, subtotal - discount);
    $('#txtPaidAmount').val(grandTotal.toFixed(2));
    recalculateTotals();
});

// Checkout Submission
$('#btnCheckout').click(function() {
    if (cart.length === 0) {
        alert('Please add items to cart first.');
        return;
    }

    const payload = {
        action: 'create_sale',
        customer_id: $('#customerId').val(),
        discount_amount: parseFloat($('#txtDiscount').val()) || 0,
        paid_amount: parseFloat($('#txtPaidAmount').val()) || 0,
        payment_method: $('#paymentMethod').val(),
        account_id: $('#accountId').val(),
        items: cart
    };

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

    $.ajax({
        url: '<?= BASE_URL ?>/ajax/sales.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(res) {
            if (res.success) {
                window.location.href = '<?= BASE_URL ?>/modules/sales/view.php?id=' + res.data.invoice_id + '&print=true';
            } else {
                alert('Sale Error: ' + res.message);
                $('#btnCheckout').prop('disabled', false).html('<i class="bi bi-printer-fill me-1"></i> Complete & Print');
            }
        },
        error: function(err) {
            alert('Server error processing sale.');
            $('#btnCheckout').prop('disabled', false).html('<i class="bi bi-printer-fill me-1"></i> Complete & Print');
        }
    });
});
</script>

<!-- Camera Barcode Scanner Modal -->
<div class="modal fade" id="modalBarcodeScanner" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-camera-fill me-1 text-primary"></i> Scan Barcode with Camera</h5>
                <button type="button" class="btn-close" id="btnCloseScanner"></button>
            </div>
            <div class="modal-body text-center p-3">
                <div id="barcodeReader" style="width: 100%; min-height: 280px; background: #000; border-radius: 8px; overflow: hidden;"></div>
                <div class="small text-muted mt-2">
                    <i class="bi bi-info-circle me-1"></i> Point your phone or laptop camera at the product barcode
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span id="scanStatus" class="small text-muted">Ready to scan...</span>
                <button type="button" class="btn btn-secondary btn-sm" id="btnStopScanner">Cancel & Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;

// Open Camera Scanner Modal
$('#btnOpenScanner').click(function() {
    const modalEl = document.getElementById('modalBarcodeScanner');
    const modalInstance = new bootstrap.Modal(modalEl);
    modalInstance.show();

    $('#scanStatus').text('Starting camera...').removeClass('text-danger text-success').addClass('text-muted');

    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("barcodeReader");
    }

    const config = { 
        fps: 15, 
        qrbox: { width: 280, height: 180 },
        aspectRatio: 1.333
    };

    html5QrCode.start(
        { facingMode: "environment" }, 
        config, 
        onBarcodeSuccess, 
        onBarcodeFailure
    ).catch(err => {
        console.error("Camera access error:", err);
        $('#scanStatus').text('Camera permission denied or camera not found.').addClass('text-danger');
    });
});

function stopScanner() {
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop().then(() => {
            bootstrap.Modal.getInstance(document.getElementById('modalBarcodeScanner')).hide();
        }).catch(err => {
            console.error("Stop error:", err);
            bootstrap.Modal.getInstance(document.getElementById('modalBarcodeScanner')).hide();
        });
    } else {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalBarcodeScanner'));
        if (modal) modal.hide();
    }
}

$('#btnCloseScanner, #btnStopScanner').click(stopScanner);

function onBarcodeSuccess(decodedText, decodedResult) {
    $('#scanStatus').text('Scanned: ' + decodedText).addClass('text-success');
    
    // Beep sound feedback
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = 850;
        osc.start();
        setTimeout(() => { osc.stop(); }, 120);
    } catch(e) {}

    // Lookup barcode and add directly to cart
    $.get('<?= BASE_URL ?>/ajax/products.php?action=search&term=' + encodeURIComponent(decodedText), function(res) {
        if (res.success && res.data.length > 0) {
            addToCart(res.data[0]);
            stopScanner();
        } else {
            $('#scanStatus').text('Barcode ' + decodedText + ' not found in products.').addClass('text-danger');
        }
    }, 'json');
}

function onBarcodeFailure(error) {
    // Continuous scan attempt frame - silent
}
</script>

<!-- Mobile Sticky POS Bottom Bar -->
<div class="d-lg-none fixed-bottom bg-white border-top p-2 px-3 shadow-lg d-flex justify-content-between align-items-center" id="mobilePosBar" style="z-index: 1025;">
    <div>
        <small class="text-muted d-block" style="font-size: 0.725rem;">Total Payable (کل بل)</small>
        <span class="fw-bold fs-6 text-primary" id="mobileGrandTotal">PKR 0.00</span>
    </div>
    <div>
        <a href="#paymentCard" class="btn btn-primary btn-sm px-3 fw-bold d-flex align-items-center gap-1">
            <span><?= __('checkout') ?: 'Checkout' ?></span>
            <i class="bi bi-arrow-down-circle"></i>
        </a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
