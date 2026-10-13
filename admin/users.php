<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();

$success = '';
$error = '';

// Handle Status & Package Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = trim($_POST['action']);

    if ($action === 'approve_package') {
        $res = updateUserPackageStatus($userId, 'active');
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    } elseif ($action === 'reject_package') {
        $res = updateUserPackageStatus($userId, 'rejected');
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    } elseif ($action === 'expire_package') {
        $res = updateUserPackageStatus($userId, 'expired');
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    } elseif ($action === 'fast_forward_10d') {
        $res = fastForwardUserPackageDays($userId, 10);
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    } elseif ($action === 'change_package') {
        $newPlanId = (int)($_POST['new_plan_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? 'active');
        $res = updateUserPackageStatus($userId, $newStatus, '', $newPlanId);
        if ($res['success']) {
            $success = $res['message'];
        } else {
            $error = $res['message'];
        }
    }
}

// Filter and Search
$statusFilter = trim($_GET['status'] ?? '');
$validStatuses = ['pending', 'active', 'expired', 'rejected'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

$search = trim($_GET['search'] ?? '');
$users = getAllUsersWithPackages(150, $statusFilter, $search);
$counts = getUserPackageCounts();
$availablePlans = getInvestmentPlans(false);

$pageTitle = 'User Accounts & Package Approval';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">User Accounts &amp; Package Approval</h1>
    <p style="color: var(--text-secondary); font-size: 0.88rem;">
      When new users register, their accounts are created. Review and approve their investment packages below to activate their daily earnings and withdrawals.
    </p>
  </div>
  <div style="display: flex; gap: 10px;">
    <a href="plans.php" class="btn btn-secondary" style="font-size: 0.85rem; padding: 8px 16px;">
      Manage Package Catalog &rarr;
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

<!-- Summary Metric Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
  <div class="card" style="margin-bottom: 0; padding: 16px; background: var(--bg-card); border-radius: var(--radius-md);">
    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Total Users</div>
    <div style="font-size: 1.8rem; font-weight: 900; color: var(--text-main); margin-top: 4px;">
      <?php echo $counts['all']; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Registered investor accounts</div>
  </div>

  <div class="card" style="margin-bottom: 0; padding: 16px; background: rgba(245, 158, 11, 0.08); border-color: rgba(245, 158, 11, 0.4); border-radius: var(--radius-md);">
    <div style="font-size: 0.75rem; color: #f59e0b; text-transform: uppercase; font-weight: 700; display: flex; align-items: center; gap: 6px;">
      <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span>
      Pending Approvals
    </div>
    <div style="font-size: 1.8rem; font-weight: 900; color: #f59e0b; margin-top: 4px;">
      <?php echo $counts['pending']; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Waiting for admin activation</div>
  </div>

  <div class="card" style="margin-bottom: 0; padding: 16px; background: rgba(147, 215, 38, 0.08); border-color: rgba(147, 215, 38, 0.4); border-radius: var(--radius-md);">
    <div style="font-size: 0.75rem; color: var(--accent-lime); text-transform: uppercase; font-weight: 700; display: flex; align-items: center; gap: 6px;">
      <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-lime); display: inline-block;"></span>
      Active Packages
    </div>
    <div style="font-size: 1.8rem; font-weight: 900; color: var(--accent-lime); margin-top: 4px;">
      <?php echo $counts['active']; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Actively generating daily profit</div>
  </div>

  <div class="card" style="margin-bottom: 0; padding: 16px; background: rgba(148, 163, 184, 0.08); border-color: rgba(148, 163, 184, 0.3); border-radius: var(--radius-md);">
    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; display: flex; align-items: center; gap: 6px;">
      <span style="width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; display: inline-block;"></span>
      Expired Packages
    </div>
    <div style="font-size: 1.8rem; font-weight: 900; color: #94a3b8; margin-top: 4px;">
      <?php echo $counts['expired']; ?>
    </div>
    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px;">Full actual capital withdrawn</div>
  </div>
</div>

<!-- Filter Tabs & Search Bar -->
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="users.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="btn <?php echo ($statusFilter === '') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px;">
      All (<?php echo $counts['all']; ?>)
    </a>
    <a href="users.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn <?php echo ($statusFilter === 'pending') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(245, 158, 11, 0.4);">
      <span style="color: #f59e0b;">●</span> Pending (<?php echo $counts['pending']; ?>)
    </a>
    <a href="users.php?status=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn <?php echo ($statusFilter === 'active') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(147, 215, 38, 0.4);">
      <span style="color: var(--accent-lime);">●</span> Active (<?php echo $counts['active']; ?>)
    </a>
    <a href="users.php?status=expired<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn <?php echo ($statusFilter === 'expired') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(148, 163, 184, 0.4);">
      <span style="color: #94a3b8;">●</span> Expired (<?php echo $counts['expired']; ?>)
    </a>
    <a href="users.php?status=rejected<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn <?php echo ($statusFilter === 'rejected') ? 'btn-pwa' : 'btn-secondary'; ?>" style="font-size: 0.82rem; padding: 7px 14px; border-color: rgba(239, 68, 68, 0.4);">
      <span style="color: #ef4444;">●</span> Rejected (<?php echo $counts['rejected']; ?>)
    </a>
  </div>

  <form method="GET" action="users.php" style="display: flex; gap: 8px;">
    <?php if (!empty($statusFilter)): ?>
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
    <?php endif; ?>
    <input type="text" name="search" class="form-input" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 7px 12px; font-size: 0.82rem; min-width: 240px;">
    <button type="submit" class="btn btn-secondary" style="font-size: 0.82rem; padding: 7px 14px;">Search</button>
    <?php if (!empty($search)): ?>
      <a href="users.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>" class="btn btn-secondary" style="font-size: 0.82rem; padding: 7px 12px;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Users Table Card -->
<div class="card" style="padding: 0; overflow: hidden;">
  <?php if (empty($users)): ?>
    <div style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
      <p style="font-size: 0.95rem;">No user accounts found matching your criteria.</p>
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.84rem;">
        <thead>
          <tr style="background: var(--bg-subtle); border-bottom: 1px solid var(--border-glass); color: var(--text-secondary); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">
            <th style="padding: 12px 14px;">User</th>
            <th style="padding: 12px 14px;">Contact</th>
            <th style="padding: 12px 14px;">Selected Package</th>
            <th style="padding: 12px 14px;">Capital Amount</th>
            <th style="padding: 12px 14px;">Earned Profit / Threshold</th>
            <th style="padding: 12px 14px;">Wallet Balance</th>
            <th style="padding: 12px 14px;">Package Status</th>
            <th style="padding: 12px 14px; text-align: right;">Admin Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): 
            $status = $u['package_status'] ?? 'pending';
            $badgeColor = '#f59e0b';
            $badgeBg = 'rgba(245, 158, 11, 0.12)';
            $statusLabel = 'Pending Approval';

            if ($status === 'active') {
                $badgeColor = 'var(--accent-lime)';
                $badgeBg = 'var(--accent-lime-soft)';
                $statusLabel = 'Active';
            } elseif ($status === 'expired') {
                $badgeColor = '#94a3b8';
                $badgeBg = 'rgba(148, 163, 184, 0.15)';
                $statusLabel = 'Expired (Capital Withdrawn)';
            } elseif ($status === 'rejected') {
                $badgeColor = '#ef4444';
                $badgeBg = 'rgba(239, 68, 68, 0.12)';
                $statusLabel = 'Rejected';
            }

            $uStats = getUserProfitStats($u);
            $uMinW = $uStats['min_withdrawal'];
          ?>
            <tr style="border-bottom: 1px solid var(--border-glass);">
              <td style="padding: 12px 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--bg-subtle); border: 1px solid var(--border-glass); display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--accent-lime); font-size: 0.85rem; flex-shrink: 0;">
                    <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                  </div>
                  <div>
                    <strong style="color: var(--text-main); font-size: 0.9rem; display: block;">
                      <?php echo htmlspecialchars($u['name']); ?>
                    </strong>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                      ID #<?php echo $u['id']; ?> • Joined <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                    </span>
                  </div>
                </div>
              </td>

              <td style="padding: 12px 14px;">
                <div style="color: var(--text-secondary); font-size: 0.8rem;"><?php echo htmlspecialchars($u['email']); ?></div>
                <div style="color: var(--accent-lime); font-size: 0.76rem; font-weight: 600;"><?php echo htmlspecialchars($u['phone']); ?></div>
              </td>

              <td style="padding: 12px 14px;">
                <strong style="color: var(--text-main);"><?php echo htmlspecialchars($u['plan_name']); ?></strong>
              </td>

              <td style="padding: 12px 14px;">
                <strong style="color: var(--text-main); font-size: 0.92rem;">
                  $<?php echo number_format($u['plan_amount'], 2); ?>
                </strong>
                <span style="font-size: 0.72rem; color: var(--text-muted);">USD</span>
              </td>

              <td style="padding: 12px 14px;">
                <div style="font-weight: 800; color: <?php echo $uStats['can_withdraw'] ? 'var(--accent-lime)' : '#f59e0b'; ?>; font-size: 0.92rem;">
                  +$<?php echo number_format($uStats['available_profit'], 2); ?>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                  <?php if ($status === 'active'): ?>
                    <?php if ($uStats['can_withdraw']): ?>
                      <span style="color: var(--accent-lime); font-weight: 700;">✓ Ready ($<?php echo number_format($uMinW, 0); ?> min)</span>
                    <?php else: ?>
                      <span style="color: #f59e0b;">🔒 $<?php echo number_format($uStats['remaining_to_unlock'], 2); ?> left for $<?php echo number_format($uMinW, 0); ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span><?php echo ucfirst($status); ?></span>
                  <?php endif; ?>
                </div>
              </td>

              <td style="padding: 12px 14px; font-weight: 700; color: #a5b4fc;">
                $<?php echo number_format($u['balance'], 2); ?>
              </td>

              <td style="padding: 12px 14px;">
                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 9px; border-radius: 20px; background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeColor; ?>;">
                  <span style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $badgeColor; ?>; display: inline-block;"></span>
                  <?php echo $statusLabel; ?>
                </span>
                <?php if (!empty($u['package_approved_at'])): ?>
                  <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;">
                    Approved: <?php echo date('M d, H:i', strtotime($u['package_approved_at'])); ?>
                  </div>
                <?php endif; ?>
              </td>

              <td style="padding: 12px 14px; text-align: right;">
                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                  <?php if ($status === 'pending'): ?>
                    <form method="POST" action="users.php" style="display: inline;">
                      <input type="hidden" name="action" value="approve_package">
                      <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                      <button type="submit" class="btn btn-pwa" style="font-size: 0.76rem; padding: 5px 11px;">
                        ✓ Approve Package
                      </button>
                    </form>
                    <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Reject this package request for <?php echo htmlspecialchars($u['name']); ?>?');">
                      <input type="hidden" name="action" value="reject_package">
                      <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                      <button type="submit" class="btn btn-secondary" style="font-size: 0.76rem; padding: 5px 10px; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);">
                        ✕ Reject
                      </button>
                    </form>
                  <?php elseif ($status === 'active'): ?>
                    <form method="POST" action="users.php" style="display: inline;" title="Simulate +10 days of earnings for instant threshold testing">
                      <input type="hidden" name="action" value="fast_forward_10d">
                      <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                      <button type="submit" class="btn" style="font-size: 0.74rem; padding: 5px 9px; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.35);">
                        ⚡ +10 Days
                      </button>
                    </form>
                    <form method="POST" action="users.php" style="display: inline;" onsubmit="return confirm('Expire package for <?php echo htmlspecialchars($u['name']); ?>? User account will remain active.');">
                      <input type="hidden" name="action" value="expire_package">
                      <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                      <button type="submit" class="btn btn-secondary" style="font-size: 0.74rem; padding: 5px 10px; color: #94a3b8;">
                        Expire Package
                      </button>
                    </form>
                  <?php elseif ($status === 'expired' || $status === 'rejected'): ?>
                    <form method="POST" action="users.php" style="display: inline;">
                      <input type="hidden" name="action" value="approve_package">
                      <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                      <button type="submit" class="btn btn-pwa" style="font-size: 0.74rem; padding: 5px 10px;">
                        Reactivate Package
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Change Plan Dropdown Modal Trigger / Form -->
                  <details style="position: relative; display: inline-block;">
                    <summary class="btn btn-secondary" style="font-size: 0.74rem; padding: 5px 8px; cursor: pointer; list-style: none;">
                      ⚙ Plan
                    </summary>
                    <div style="position: absolute; right: 0; top: 100%; z-index: 100; min-width: 220px; background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-md); box-shadow: var(--shadow-glass); padding: 12px; margin-top: 4px; text-align: left;">
                      <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Change User Package</div>
                      <form method="POST" action="users.php">
                        <input type="hidden" name="action" value="change_package">
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <div style="margin-bottom: 8px;">
                          <label style="font-size: 0.7rem; color: var(--text-secondary); display: block; margin-bottom: 3px;">Select Package</label>
                          <select name="new_plan_id" class="form-input" style="font-size: 0.75rem; padding: 5px 8px;">
                            <?php foreach ($availablePlans as $pId => $p): ?>
                              <option value="<?php echo $pId; ?>" <?php echo ($u['plan_id'] == $pId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> ($<?php echo number_format($p['amount'], 0); ?>)
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div style="margin-bottom: 8px;">
                          <label style="font-size: 0.7rem; color: var(--text-secondary); display: block; margin-bottom: 3px;">Set Status</label>
                          <select name="new_status" class="form-input" style="font-size: 0.75rem; padding: 5px 8px;">
                            <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="expired" <?php echo ($status === 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="rejected" <?php echo ($status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                          </select>
                        </div>
                        <button type="submit" class="btn btn-pwa" style="width: 100%; font-size: 0.75rem; padding: 5px 8px;">
                          Update Package
                        </button>
                      </form>
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
