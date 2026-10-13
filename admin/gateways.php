<?php
require_once __DIR__ . '/auth.php';
$adminUser = requireAdmin();

$success = '';
$error = '';

// Handle Gateway Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_gateway') {
    $gwId = trim($_POST['gateway_id'] ?? '');
    $gateways = getPaymentGateways(false);

    if (!isset($gateways[$gwId])) {
        $error = 'Invalid payment gateway ID.';
    } else {
        $data = [
            'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
            'name' => trim($_POST['name'] ?? $gateways[$gwId]['name']),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'account_title' => trim($_POST['account_title'] ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'iban' => trim($_POST['iban'] ?? ''),
            'wallet_address' => trim($_POST['wallet_address'] ?? ''),
            'network' => trim($_POST['network'] ?? ''),
            'instructions' => trim($_POST['instructions'] ?? '')
        ];

        if (updatePaymentGateway($gwId, $data)) {
            $success = "Successfully updated details for <strong>" . htmlspecialchars($data['name']) . "</strong>! Changes are live on the deposit screen.";
        } else {
            $error = "Failed to update payment gateway in the database.";
        }
    }
}

// Reload gateways
$gateways = getPaymentGateways(false);

$pageTitle = 'Manage Payment Gateways';
require_once __DIR__ . '/header.php';
?>

<div style="margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 14px;">
  <div>
    <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Dynamic Payment Details</h1>
    <p style="color: var(--text-secondary); font-size: 0.88rem;">
      Admin controls: Enable/Disable payment methods and update live receiving details, account numbers, IBANs, and wallet addresses.
    </p>
  </div>
</div>

<?php if ($success): ?>
  <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo $success; ?>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.9rem;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">

  <!-- 1. Bank Transfer Settings Card -->
  <?php $bank = $gateways['bank'] ?? []; ?>
  <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
    <form method="POST" action="gateways.php">
      <input type="hidden" name="action" value="update_gateway">
      <input type="hidden" name="gateway_id" value="bank">

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: #10b981;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="21" width="18" height="2" rx="1"/><path d="M4 10h16v11H4z"/><path d="M12 3L2 8h20L12 3z"/><path d="M8 14v4"/><path d="M12 14v4"/><path d="M16 14v4"/></svg>
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">Bank Transfer</h3>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Raast / IBFT / Pakistani Banks</span>
          </div>
        </div>

        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
          <input type="checkbox" name="is_enabled" value="1" <?php echo (!empty($bank['is_enabled'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-lime); cursor: pointer;">
          Active
        </label>
      </div>

      <div class="form-group">
        <label class="form-label">Display Method Name</label>
        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($bank['name'] ?? 'Bank Transfer'); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Bank Name</label>
        <input type="text" name="bank_name" class="form-input" placeholder="e.g. Meezan Bank Limited" value="<?php echo htmlspecialchars($bank['bank_name'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Account Title</label>
        <input type="text" name="account_title" class="form-input" placeholder="e.g. EarnNow Technologies PVT LTD" value="<?php echo htmlspecialchars($bank['account_title'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Account Number</label>
        <input type="text" name="account_number" class="form-input" placeholder="e.g. 01020304050607" value="<?php echo htmlspecialchars($bank['account_number'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">IBAN (International / Raast)</label>
        <input type="text" name="iban" class="form-input" placeholder="e.g. PK64MEZN0001020304050607" value="<?php echo htmlspecialchars($bank['iban'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Payment Instructions</label>
        <textarea name="instructions" class="form-input" rows="2" style="resize: vertical; font-size: 0.82rem;"><?php echo htmlspecialchars($bank['instructions'] ?? ''); ?></textarea>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 10px; font-size: 0.88rem; margin-top: 6px;">
        Save Bank Details
      </button>
    </form>
  </div>

  <!-- 2. EasyPaisa Settings Card -->
  <?php $ep = $gateways['easypaisa'] ?? []; ?>
  <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
    <form method="POST" action="gateways.php">
      <input type="hidden" name="action" value="update_gateway">
      <input type="hidden" name="gateway_id" value="easypaisa">

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(147, 215, 38, 0.15); display: flex; align-items: center; justify-content: center; color: var(--accent-lime);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">EasyPaisa</h3>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Telenor Microfinance</span>
          </div>
        </div>

        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
          <input type="checkbox" name="is_enabled" value="1" <?php echo (!empty($ep['is_enabled'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-lime); cursor: pointer;">
          Active
        </label>
      </div>

      <div class="form-group">
        <label class="form-label">Display Method Name</label>
        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($ep['name'] ?? 'Easypaisa'); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Account Title</label>
        <input type="text" name="account_title" class="form-input" placeholder="e.g. EarnNow Official Deposits" value="<?php echo htmlspecialchars($ep['account_title'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">EasyPaisa Mobile Number</label>
        <input type="text" name="account_number" class="form-input" placeholder="e.g. 0345-1234567" value="<?php echo htmlspecialchars($ep['account_number'] ?? ''); ?>" required>
      </div>

      <div class="form-group" style="margin-top: 40px;">
        <label class="form-label">Payment Instructions</label>
        <textarea name="instructions" class="form-input" rows="3" style="resize: vertical; font-size: 0.82rem;"><?php echo htmlspecialchars($ep['instructions'] ?? ''); ?></textarea>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 10px; font-size: 0.88rem; margin-top: 6px;">
        Save EasyPaisa Details
      </button>
    </form>
  </div>

  <!-- 3. JazzCash Settings Card -->
  <?php $jc = $gateways['jazzcash'] ?? []; ?>
  <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
    <form method="POST" action="gateways.php">
      <input type="hidden" name="action" value="update_gateway">
      <input type="hidden" name="gateway_id" value="jazzcash">

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">JazzCash</h3>
            <span style="font-size: 0.72rem; color: var(--text-muted);">Mobilink Microfinance</span>
          </div>
        </div>

        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
          <input type="checkbox" name="is_enabled" value="1" <?php echo (!empty($jc['is_enabled'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-lime); cursor: pointer;">
          Active
        </label>
      </div>

      <div class="form-group">
        <label class="form-label">Display Method Name</label>
        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($jc['name'] ?? 'JazzCash'); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Account Title</label>
        <input type="text" name="account_title" class="form-input" placeholder="e.g. EarnNow Finance" value="<?php echo htmlspecialchars($jc['account_title'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">JazzCash Mobile Number</label>
        <input type="text" name="account_number" class="form-input" placeholder="e.g. 0300-9876543" value="<?php echo htmlspecialchars($jc['account_number'] ?? ''); ?>" required>
      </div>

      <div class="form-group" style="margin-top: 40px;">
        <label class="form-label">Payment Instructions</label>
        <textarea name="instructions" class="form-input" rows="3" style="resize: vertical; font-size: 0.82rem;"><?php echo htmlspecialchars($jc['instructions'] ?? ''); ?></textarea>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 10px; font-size: 0.88rem; margin-top: 6px;">
        Save JazzCash Details
      </button>
    </form>
  </div>

  <!-- 4. USDT TRC-20 Settings Card -->
  <?php $trc = $gateways['trc20'] ?? []; ?>
  <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
    <form method="POST" action="gateways.php">
      <input type="hidden" name="action" value="update_gateway">
      <input type="hidden" name="gateway_id" value="trc20">

      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-glass); padding-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(56, 189, 248, 0.15); display: flex; align-items: center; justify-content: center; color: #38bdf8;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v12M8 10h8M8 14h8"/></svg>
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0;">USDT (TRC-20)</h3>
            <span style="font-size: 0.72rem; color: #38bdf8;">Tron Blockchain</span>
          </div>
        </div>

        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
          <input type="checkbox" name="is_enabled" value="1" <?php echo (!empty($trc['is_enabled'])) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--accent-lime); cursor: pointer;">
          Active
        </label>
      </div>

      <div class="form-group">
        <label class="form-label">Display Method Name</label>
        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($trc['name'] ?? 'USDT (TRC20)'); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Network Name</label>
        <input type="text" name="network" class="form-input" placeholder="e.g. TRC-20 (Tron Network)" value="<?php echo htmlspecialchars($trc['network'] ?? 'TRC-20 (Tron Network)'); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">TRC-20 Receiving Wallet Address</label>
        <input type="text" name="wallet_address" class="form-input" placeholder="e.g. TXm1P2k7gK9Q5vR4zB8aC3dE6fH7jL9mNw" value="<?php echo htmlspecialchars($trc['wallet_address'] ?? ''); ?>" required style="font-family: monospace; color: var(--accent-lime);">
      </div>

      <div class="form-group" style="margin-top: 40px;">
        <label class="form-label">Payment Instructions</label>
        <textarea name="instructions" class="form-input" rows="3" style="resize: vertical; font-size: 0.82rem;"><?php echo htmlspecialchars($trc['instructions'] ?? ''); ?></textarea>
      </div>

      <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 10px; font-size: 0.88rem; margin-top: 6px;">
        Save TRC-20 Details
      </button>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
