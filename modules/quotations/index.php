<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('quotations');

$page_title = __('quotations');

$quotations = $conn->query("SELECT q.*, c.name as customer_name, c.name_ur as customer_name_ur 
                            FROM quotations q 
                            JOIN customers c ON q.customer_id = c.id 
                            ORDER BY q.id DESC LIMIT 100");

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= __('quotations') ?> (نرخ نامہ)</h4>
        <small class="text-muted">Generate project estimates for builders & contractors without altering inventory</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/quotations/new.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> <?= __('new_quotation') ?>
    </a>
</div>

<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-hover table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Date</th>
                    <th>Customer / Contractor</th>
                    <th>Total Estimate (PKR)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($quotations->num_rows > 0): ?>
                    <?php while ($q = $quotations->fetch_assoc()): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary"><?= clean_input($q['quotation_no']) ?></td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($q['date'])) ?></td>
                            <td class="fw-semibold"><?= clean_input($q['customer_name']) ?></td>
                            <td class="fw-bold text-dark"><?= format_currency($q['grand_total']) ?></td>
                            <td>
                                <?php if ($q['status'] === 'converted'): ?>
                                    <span class="badge bg-success">Converted to Sale</span>
                                <?php elseif ($q['status'] === 'approved'): ?>
                                    <span class="badge bg-primary">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/modules/quotations/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-light border" title="View & Print">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted"><?= __('no_records') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
