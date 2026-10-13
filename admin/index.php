<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();

$success = '';
$error = '';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $txId = (int)($_POST['tx_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $adminNote = trim($_POST['admin_note'] ?? '');

    $res = updateTransactionStatus($txId, $newStatus, $adminNote);
    if ($res['success']) {
        $success = $res['message'];
    } else {
        $error = $res['message'];
    }
}

// Filter
$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Failed'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

$transactions = getAllTransactions(100, $statusFilter);

// Count summary
$db = getDB();
$counts = [
    'All' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'Pending' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'Pending'")->fetchColumn(),
    'Under Review' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'Under Review'")->fetchColumn(),
    'Approved' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'Approved'")->fetchColumn(),
    'Rejected' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'Rejected'")->fetchColumn(),
    'Failed' => $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'Failed'")->fetchColumn(),
];

$pageTitle = 'Payment Verification & Submissions';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Payment Submissions & Verification</h1>
    <p style="color: var(--text-secondary); font-size: 0.88rem;">
      Manage all deposit & withdrawal requests across 5 distinct stages: Pending, Under Review, Approved, Rejected, and Failed.
    </p>
  </div>
  <div>
    <a href="gateways.php" class="btn btn-secondary" style="font-size: 0.85rem; padding: 8px 16px;">
      Edit Gateway Details &rarr;
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($success); ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>
<?php 
  $pendingPkgCount = (int)getPendingPackageCount(); 
  if ($pendingPkgCount > 0): 
?>
  <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: var(--radius-md); padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 10px; color: #f59e0b; font-size: 0.9rem;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
      <span>
        <strong style="color: var(--text-main);"><?php echo $pendingPkgCount; ?> User Package(s) Awaiting Admin Approval!</strong> Review user requests to activate their packages and daily earnings.
      </span>
    </div>
    <a href="users.php?status=pending" class="btn btn-pwa" style="font-size: 0.82rem; padding: 6px 14px;">
      Review &amp; Approve Users &rarr;
    </a>
  </div>
<?php endif; ?>

<!-- 5 Status Filter Tabs -->
<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
  <a href="index.php" class="btn <?php echo ($statusFilter === '') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px;">
    All (<?php echo $counts['All']; ?>)
  </a>
  <a href="index.php?status=Pending" class="btn <?php echo ($statusFilter === 'Pending') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(245, 158, 11, 0.4);">
    <span style="color: #f59e0b;">●</span> Pending (<?php echo $counts['Pending']; ?>)
  </a>
  <a href="index.php?status=Under+Review" class="btn <?php echo ($statusFilter === 'Under Review') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(14, 165, 233, 0.4);">
    <span style="color: #0ea5e9;">●</span> Under Review (<?php echo $counts['Under Review']; ?>)
  </a>
  <a href="index.php?status=Approved" class="btn <?php echo ($statusFilter === 'Approved') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(147, 215, 38, 0.4);">
    <span style="color: var(--accent-lime);">●</span> Approved (<?php echo $counts['Approved']; ?>)
  </a>
  <a href="index.php?status=Rejected" class="btn <?php echo ($statusFilter === 'Rejected') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(239, 68, 68, 0.4);">
    <span style="color: #ef4444;">●</span> Rejected (<?php echo $counts['Rejected']; ?>)
  </a>
  <a href="index.php?status=Failed" class="btn <?php echo ($statusFilter === 'Failed') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(148, 163, 184, 0.4);">
    <span style="color: #94a3b8;">●</span> Failed (<?php echo $counts['Failed']; ?>)
  </a>
</div>

<!-- Transactions Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
  <?php if (empty($transactions)): ?>
    <div style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
      <p style="font-size: 0.95rem;">No transactions found for the selected status.</p>
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
        <thead>
          <tr style="border-bottom: 1px solid var(--border-glass); background: var(--bg-subtle); color: var(--text-muted); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em;">
            <th style="padding: 12px 14px;">ID</th>
            <th style="padding: 12px 14px;">User</th>
            <th style="padding: 12px 14px;">Type &amp; Method</th>
            <th style="padding: 12px 14px;">Amount</th>
            <th style="padding: 12px 14px;">TXID / Reference</th>
            <th style="padding: 12px 14px;">Proof</th>
            <th style="padding: 12px 14px;">Current Status</th>
            <th style="padding: 12px 14px;">Date</th>
            <th style="padding: 12px 14px; text-align: right;">Update Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): 
            $isDeposit = ($tx['type'] === 'deposit');
            $badgeClass = 'badge-warning';
            if ($tx['status'] === 'Under Review') $badgeClass = 'badge-info';
            if ($tx['status'] === 'Approved') $badgeClass = 'badge-success';
            if ($tx['status'] === 'Rejected') $badgeClass = 'badge-danger';
            if ($tx['status'] === 'Failed') $badgeClass = 'badge-secondary';
          ?>
            <tr style="border-bottom: 1px solid var(--border-glass);">
              <td style="padding: 12px 14px; font-weight: 700; color: var(--text-muted);">
                #<?php echo $tx['id']; ?>
              </td>

              <td style="padding: 12px 14px;">
                <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($tx['user_name']); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($tx['user_email']); ?></div>
                <div style="font-size: 0.72rem; color: var(--accent-lime);"><?php echo htmlspecialchars($tx['user_phone']); ?></div>
              </td>

              <td style="padding: 12px 14px;">
                <div style="font-weight: 700; color: <?php echo $isDeposit ? 'var(--accent-lime)' : '#38bdf8'; ?>; text-transform: uppercase; font-size: 0.78rem;">
                  <?php echo htmlspecialchars($tx['type']); ?>
                </div>
                <div style="color: var(--text-secondary); font-size: 0.8rem;">
                  <?php echo htmlspecialchars($tx['method']); ?>
                </div>
              </td>

              <td style="padding: 12px 14px; font-weight: 800; font-size: 0.95rem; color: var(--text-main);">
                $<?php echo number_format($tx['amount'], 2); ?>
              </td>

              <td style="padding: 12px 14px;">
                <?php if (!empty($tx['trx_id'])): ?>
                  <code style="font-family: monospace; font-size: 0.8rem; background: var(--bg-input); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-glass); color: var(--accent-lime);">
                    <?php echo htmlspecialchars($tx['trx_id']); ?>
                  </code>
                <?php elseif (!empty($tx['account_or_wallet'])): ?>
                  <div style="font-family: monospace; font-size: 0.8rem; background: var(--bg-input); padding: 5px 9px; border-radius: 6px; border: 1px solid var(--border-glass); color: #38bdf8; word-break: break-word; max-width: 260px;" title="<?php echo htmlspecialchars($tx['account_or_wallet']); ?>">
                    <?php echo htmlspecialchars($tx['account_or_wallet']); ?>
                  </div>
                <?php else: ?>
                  <span style="color: var(--text-muted);">&mdash;</span>
                <?php endif; ?>
              </td>

              <td style="padding: 12px 14px;">
                <?php if (!empty($tx['screenshot'])): ?>
                  <a href="../<?php echo htmlspecialchars($tx['screenshot']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.78rem; color: #38bdf8; text-decoration: underline;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                    View Receipt
                  </a>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-size: 0.78rem;">No file</span>
                <?php endif; ?>
              </td>

              <td style="padding: 12px 14px;">
                <span class="pwa-status-badge <?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($tx['status']); ?>
                </span>
                <?php if (!empty($tx['note'])): ?>
                  <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($tx['note']); ?>">
                    <?php echo htmlspecialchars($tx['note']); ?>
                  </div>
                <?php endif; ?>
              </td>

              <td style="padding: 12px 14px; color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;">
                <?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?>
              </td>

              <td style="padding: 12px 14px; text-align: right;">
                <!-- Fast Status Changer Form -->
                <form method="POST" action="index.php" style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="tx_id" value="<?php echo $tx['id']; ?>">

                  <select name="status" class="form-input" style="padding: 5px 8px; font-size: 0.78rem; width: auto; font-weight: 700;">
                    <option value="Pending" <?php echo ($tx['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Under Review" <?php echo ($tx['status'] === 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
                    <option value="Approved" <?php echo ($tx['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo ($tx['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Failed" <?php echo ($tx['status'] === 'Failed') ? 'selected' : ''; ?>>Failed</option>
                  </select>

                  <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.75rem;">
                    Update
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
