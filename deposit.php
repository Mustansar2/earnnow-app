<?php
define('EARNNOW_APP', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methodKey = $_POST['payment_method'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $trxId = trim($_POST['trx_id'] ?? '');

    if (!isset($DEPOSIT_METHODS[$methodKey])) {
        $error = 'Please select a valid deposit payment method.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid deposit amount greater than 0.';
    } elseif (empty($trxId)) {
        $error = 'Please enter the Transaction ID / Reference Number from your payment.';
    } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a clear screenshot of your payment receipt.';
    } else {
        $file = $_FILES['screenshot'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedExtensions)) {
            $error = 'Invalid screenshot format. Only JPG, PNG, and WebP images are accepted.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Screenshot file size exceeds the 5MB limit.';
        } else {
            $newFileName = 'dep_' . $currentUser['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            $destination = __DIR__ . '/uploads/deposits/' . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $db = getDB();
                $methodName = $DEPOSIT_METHODS[$methodKey]['name'];
                $relativeScreenshotPath = 'uploads/deposits/' . $newFileName;

                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, category, amount, method, trx_id, screenshot, status, note)
                    VALUES (?, 'deposit', 'deposit', ?, ?, ?, ?, 'Pending', 'Awaiting administrative verification')
                ");

                $stmt->execute([
                    $currentUser['id'],
                    $amount,
                    $methodName,
                    $trxId,
                    $relativeScreenshotPath
                ]);

                $success = 'Deposit request of $' . number_format($amount, 2) . ' submitted successfully! Your account balance will be updated upon verification.';
            } else {
                $error = 'Failed to upload screenshot. Please verify file permissions and try again.';
            }
        }
    }
}

$pageTitle = 'Deposit Funds';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-2col-grid">
  <!-- Left Side: Header, Instructions & Payment Account Info -->
  <div>
    <div style="margin-bottom: 18px;">
      <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">Deposit Funds</h1>
      <p style="color: var(--text-secondary); font-size: 0.9rem;">Select your payment exchange, send funds, and upload payment receipt.</p>
    </div>

    <!-- Dynamic Payment Details Box -->
    <div id="paymentDetailsBox" style="background: var(--bg-card); border: 1px solid var(--border-glass); border-radius: var(--radius-lg); padding: 18px; margin-bottom: 16px; box-shadow: var(--shadow-glass);">
      <!-- Filled by JavaScript -->
    </div>

    <div class="card" style="font-size: 0.82rem; color: var(--text-secondary); line-height: 1.6;">
      <strong style="color: var(--text-main); display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent-lime)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Important Instructions
      </strong>
      • Minimum deposit amount is $10.00 USD.<br>
      • Ensure your Transaction Reference Number matches the payment proof.<br>
      • Deposits are verified by administrators and credited to your wallet balance.
    </div>
  </div>

  <!-- Right Side: Submission Form & Status Alerts -->
  <div>
    <?php if ($success): ?>
      <div style="background: var(--accent-lime-soft); border: 1px solid var(--accent-lime); color: var(--accent-lime); padding: 16px 20px; border-radius: var(--radius-lg); margin-bottom: 18px;">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Deposit Request Submitted
        </div>
        <p style="font-size: 0.92rem; color: var(--text-secondary);"><?php echo htmlspecialchars($success); ?></p>
        <div style="margin-top: 12px;">
          <a href="index.php" class="btn btn-secondary" style="font-size: 0.85rem; padding: 7px 14px;">Return to Dashboard</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #ef4444; padding: 12px 16px; border-radius: var(--radius-lg); margin-bottom: 18px; font-size: 0.92rem;">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" action="deposit.php" enctype="multipart/form-data">
        
        <!-- Payment Method Dropdown -->
        <div class="form-group">
          <label class="form-label">Select Payment Method / Exchange</label>
          <select name="payment_method" id="paymentMethodSelect" class="form-input" style="font-weight: 600;" required>
            <option value="easypaisa" <?php echo (($_POST['payment_method'] ?? '') === 'easypaisa') ? 'selected' : ''; ?>>Easypaisa (Pakistan)</option>
            <option value="jazzcash" <?php echo (($_POST['payment_method'] ?? '') === 'jazzcash') ? 'selected' : ''; ?>>JazzCash (Pakistan)</option>
            <option value="trc20" <?php echo (($_POST['payment_method'] ?? 'trc20') === 'trc20') ? 'selected' : ''; ?>>USDT (TRC20 - Crypto)</option>
          </select>
        </div>

        <!-- Deposit Amount Input -->
        <div class="form-group">
          <label class="form-label">Deposit Amount (USD)</label>
          <div style="position: relative;">
            <input type="number" step="0.01" min="10" name="amount" class="form-input" placeholder="e.g. 100.00" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required style="padding-left: 36px;">
            <span style="position: absolute; left: 14px; top: 13px; color: var(--accent-lime); font-weight: 800;">$</span>
          </div>
          <small style="color: var(--text-muted); font-size: 0.78rem;">Minimum deposit: $10 USD</small>
        </div>

        <!-- Transaction ID / Reference -->
        <div class="form-group">
          <label class="form-label">Transaction ID / Reference Number</label>
          <input type="text" name="trx_id" class="form-input" placeholder="e.g. TRX9823487123 or Bank TID" value="<?php echo htmlspecialchars($_POST['trx_id'] ?? ''); ?>" required>
        </div>

        <!-- Payment Proof Screenshot Upload -->
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label">Upload Payment Proof (Screenshot)</label>
          <div style="border: 2px dashed var(--border-glass); border-radius: var(--radius-md); padding: 14px; background: var(--bg-subtle);">
            <input type="file" name="screenshot" id="screenshotInput" accept="image/jpeg,image/png,image/webp" required class="form-input" style="padding: 8px; cursor: pointer;" onchange="handleFileSelected(this)">
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
              Supports JPG, PNG, WebP (Max 5MB)
            </div>
            <div id="selectedFileName" style="display: none; margin-top: 6px; font-size: 0.82rem; color: var(--accent-lime); font-weight: 700;"></div>
          </div>
        </div>

        <button type="submit" class="btn btn-pwa" style="width: 100%; padding: 12px 20px; font-size: 0.95rem;">
          Submit Deposit Proof
        </button>

        <div style="text-align: center; margin-top: 14px;">
          <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.85rem;">&larr; Back to Dashboard</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const depositMethods = <?php echo json_encode($DEPOSIT_METHODS); ?>;
  const selectElem = document.getElementById('paymentMethodSelect');
  const detailsBox = document.getElementById('paymentDetailsBox');

  function renderPaymentDetails(methodKey) {
    const data = depositMethods[methodKey];
    if (!data) return;

    if (methodKey === 'trc20') {
      detailsBox.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">Method: ${data.name}</span>
          <span style="font-size: 0.75rem; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Network: TRC-20</span>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Tron TRC-20 Receiving Address:</div>
        <div style="display: flex; gap: 8px; align-items: center; background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-glass);">
          <code id="trcAddress" style="font-family: monospace; color: var(--accent-lime); word-break: break-all; font-size: 0.9rem; flex: 1;">${data.wallet_address}</code>
          <button type="button" class="btn btn-secondary" onclick="copyAddress('${data.wallet_address}')" style="padding: 4px 10px; font-size: 0.78rem; flex-shrink: 0;">Copy</button>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 10px;">${data.instructions}</div>
      `;
    } else {
      detailsBox.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: var(--accent-lime); text-transform: uppercase;">Method: ${data.name}</span>
          <span style="font-size: 0.75rem; background: var(--accent-lime-soft); color: var(--accent-lime); padding: 2px 8px; border-radius: 6px; font-weight: 700;">Instant Verification</span>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: var(--bg-input); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--border-glass); margin-bottom: 8px;">
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Account Title:</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);">${data.account_title}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Mobile Number:</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--accent-lime); display: flex; align-items: center; gap: 6px;">
              <span>${data.account_number}</span>
              <button type="button" class="btn btn-secondary" onclick="copyAddress('${data.account_number}')" style="padding: 2px 8px; font-size: 0.7rem;">Copy</button>
            </div>
          </div>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted);">${data.instructions}</div>
      `;
    }
  }

  function copyAddress(text) {
    navigator.clipboard.writeText(text).then(() => {
      alert('Copied to clipboard: ' + text);
    }).catch(() => {
      prompt('Copy manually:', text);
    });
  }

  function handleFileSelected(input) {
    if (input.files && input.files[0]) {
      const nameBox = document.getElementById('selectedFileName');
      nameBox.textContent = 'Selected: ' + input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
      nameBox.style.display = 'block';
    }
  }

  selectElem.addEventListener('change', function() {
    renderPaymentDetails(this.value);
  });

  renderPaymentDetails(selectElem.value);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
