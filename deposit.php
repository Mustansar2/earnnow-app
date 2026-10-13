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

$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Real-time TXID check AJAX endpoint
if (isset($_GET['action']) && $_GET['action'] === 'validate_trx') {
    header('Content-Type: application/json');
    $trxToCheck = trim($_GET['trx_id'] ?? '');
    
    if (empty($trxToCheck)) {
        echo json_encode(['valid' => false, 'message' => 'Please enter a Transaction ID.']);
        exit;
    }
    if (strlen($trxToCheck) < 6) {
        echo json_encode(['valid' => false, 'message' => 'Transaction ID must be at least 6 characters.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9\-_#]{6,100}$/', $trxToCheck)) {
        echo json_encode(['valid' => false, 'message' => 'Invalid characters. Use letters, numbers, dashes.']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, status, created_at FROM transactions WHERE LOWER(TRIM(trx_id)) = LOWER(?) LIMIT 1");
    $stmt->execute([$trxToCheck]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode([
            'valid' => false,
            'duplicate' => true,
            'message' => 'Duplicate detected: Already submitted on ' . date('M d, Y', strtotime($existing['created_at'])) . ' (' . $existing['status'] . ').'
        ]);
        exit;
    }

    echo json_encode(['valid' => true, 'message' => 'Transaction ID verified unique & available.']);
    exit;
}

$activeGateways = getPaymentGateways(true);
if (empty($activeGateways)) {
    $activeGateways = $DEPOSIT_METHODS;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methodKey = $_POST['payment_method'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $trxId = trim($_POST['trx_id'] ?? '');

    if (!isset($activeGateways[$methodKey])) {
        $error = 'Please select a valid, currently active deposit payment method.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid deposit amount greater than 0.';
    } elseif (empty($trxId)) {
        $error = 'Please enter the Transaction ID / Reference Number from your payment.';
    } elseif (strlen($trxId) < 6) {
        $error = 'Transaction ID / Reference must be at least 6 characters long.';
    } elseif (!preg_match('/^[A-Za-z0-9\-_#]{6,100}$/', $trxId)) {
        $error = 'Transaction ID contains invalid characters. Only letters, numbers, and dashes are allowed.';
    } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a clear screenshot of your payment receipt.';
    } else {
        $checkStmt = $db->prepare("SELECT id, status, created_at FROM transactions WHERE LOWER(TRIM(trx_id)) = LOWER(?) LIMIT 1");
        $checkStmt->execute([$trxId]);
        $existingTx = $checkStmt->fetch();

        if ($existingTx) {
            $error = 'Security Alert: This Transaction ID (' . htmlspecialchars($trxId) . ') was already submitted on ' . date('M d, Y', strtotime($existingTx['created_at'])) . ' with status "' . htmlspecialchars($existingTx['status']) . '". Duplicate submissions are rejected.';
        } else {
            $file = $_FILES['screenshot'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExtensions)) {
                $error = 'Invalid screenshot format. Only JPG, PNG, and WebP images are accepted.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Screenshot file size exceeds the 5MB limit.';
            } else {
                $uploadDir = __DIR__ . '/uploads/deposits/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'dep_' . $currentUser['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                $destination = $uploadDir . $newFileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $methodName = $activeGateways[$methodKey]['name'];
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

                    header('Location: index.php?deposit=success');
                    exit;
                } else {
                    $error = 'Failed to upload screenshot. Please verify file permissions and try again.';
                }
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
            <?php 
              $defaultKey = isset($activeGateways['bank']) ? 'bank' : array_key_first($activeGateways);
              $selectedMethod = $_POST['payment_method'] ?? $defaultKey;
              foreach ($activeGateways as $key => $gw): 
            ?>
              <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($selectedMethod === $key) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($gw['name']); ?> <?php echo ($key === 'trc20') ? '(Crypto)' : '(Pakistan)'; ?>
              </option>
            <?php endforeach; ?>
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

        <!-- Transaction ID / Reference with Live Duplicate & Format Verification -->
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin-bottom: 0;">Transaction ID / Reference Number</label>
            <span id="trxCheckBadge" style="font-size: 0.72rem; font-weight: 700; display: none;"></span>
          </div>
          <div style="position: relative;">
            <input type="text" name="trx_id" id="trxIdInput" class="form-input" placeholder="e.g. Bank Ref / TID / TRC20 TXID" value="<?php echo htmlspecialchars($_POST['trx_id'] ?? ''); ?>" required autocomplete="off" oninput="handleTrxInput(this.value)" onblur="validateTrxLive(this.value)">
            <span id="trxCheckSpinner" style="display: none; position: absolute; right: 12px; top: 12px; width: 14px; height: 14px; border: 2px solid var(--accent-lime); border-top-color: transparent; border-radius: 50%; animation: spin 0.6s linear infinite;"></span>
          </div>
          <div id="trxFeedbackMsg" style="font-size: 0.78rem; margin-top: 5px; display: none; line-height: 1.4;"></div>
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

        <button type="submit" id="submitDepositBtn" class="btn btn-pwa" style="width: 100%; padding: 12px 20px; font-size: 0.95rem;">
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
  const depositMethods = <?php echo json_encode($activeGateways); ?>;
  const selectElem = document.getElementById('paymentMethodSelect');
  const detailsBox = document.getElementById('paymentDetailsBox');

  function renderPaymentDetails(methodKey) {
    const data = depositMethods[methodKey];
    if (!data) return;

    if (methodKey === 'bank') {
      detailsBox.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <span style="font-size: 0.88rem; font-weight: 700; color: #10b981; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="21" width="18" height="2" rx="1"/><path d="M4 10h16v11H4z"/><path d="M12 3L2 8h20L12 3z"/><path d="M8 14v4"/><path d="M12 14v4"/><path d="M16 14v4"/></svg>
            ${data.name}
          </span>
          <span style="font-size: 0.72rem; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Raast / IBFT Ready</span>
        </div>

        <div style="background: var(--bg-input); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-glass); margin-bottom: 10px; display: flex; flex-direction: column; gap: 10px;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
              <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Bank Name</div>
              <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-top: 2px;">${data.bank_name}</div>
            </div>
            <div>
              <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Account Title</div>
              <div style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin-top: 2px;">${data.account_title}</div>
            </div>
          </div>

          <div style="border-top: 1px solid var(--border-glass); padding-top: 10px;">
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Account Number</div>
            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
              <code style="font-family: monospace; font-size: 0.95rem; font-weight: 700; color: var(--accent-lime); background: var(--bg-subtle); padding: 7px 12px; border-radius: 6px; flex: 1; border: 1px solid var(--border-glass); letter-spacing: 0.04em;">${data.account_number}</code>
              <button type="button" class="btn btn-secondary copy-btn" onclick="copyAddress('${data.account_number}', this)" style="padding: 6px 12px; font-size: 0.78rem; flex-shrink: 0; min-width: 65px;">Copy Acc</button>
            </div>
          </div>

          <div style="border-top: 1px solid var(--border-glass); padding-top: 10px;">
            <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">IBAN (International / Raast)</div>
            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
              <code style="font-family: monospace; font-size: 0.88rem; font-weight: 700; color: #38bdf8; background: var(--bg-subtle); padding: 7px 12px; border-radius: 6px; flex: 1; word-break: break-all; border: 1px solid var(--border-glass); letter-spacing: 0.03em;">${data.iban}</code>
              <button type="button" class="btn btn-secondary copy-btn" onclick="copyAddress('${data.iban}', this)" style="padding: 6px 12px; font-size: 0.78rem; flex-shrink: 0; min-width: 65px;">Copy IBAN</button>
            </div>
          </div>
        </div>

        <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;">${data.instructions}</div>
      `;
    } else if (methodKey === 'trc20') {
      detailsBox.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: #38bdf8; text-transform: uppercase;">Method: ${data.name}</span>
          <span style="font-size: 0.75rem; background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 2px 8px; border-radius: 6px; font-weight: 700;">Network: TRC-20</span>
        </div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;">Tron TRC-20 Receiving Address:</div>
        <div style="display: flex; gap: 8px; align-items: center; background: var(--bg-input); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-glass);">
          <code id="trcAddress" style="font-family: monospace; color: var(--accent-lime); word-break: break-all; font-size: 0.9rem; flex: 1;">${data.wallet_address}</code>
          <button type="button" class="btn btn-secondary copy-btn" onclick="copyAddress('${data.wallet_address}', this)" style="padding: 6px 12px; font-size: 0.78rem; flex-shrink: 0; min-width: 65px;">Copy</button>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 10px;">${data.instructions}</div>
      `;
    } else {
      detailsBox.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 0.85rem; font-weight: 700; color: var(--accent-lime); text-transform: uppercase;">Method: ${data.name}</span>
          <span style="font-size: 0.75rem; background: var(--accent-lime-soft); color: var(--accent-lime); padding: 2px 8px; border-radius: 6px; font-weight: 700;">Instant Mobile Verification</span>
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
              <button type="button" class="btn btn-secondary copy-btn" onclick="copyAddress('${data.account_number}', this)" style="padding: 2px 8px; font-size: 0.7rem; min-width: 50px;">Copy</button>
            </div>
          </div>
        </div>
        <div style="font-size: 0.78rem; color: var(--text-muted);">${data.instructions}</div>
      `;
    }
  }

  function copyAddress(text, btnElement) {
    const handleSuccess = () => {
      if (btnElement) {
        const originalText = btnElement.innerText;
        btnElement.innerText = 'Copied! ✓';
        btnElement.style.borderColor = 'var(--accent-lime)';
        btnElement.style.color = 'var(--accent-lime)';
        setTimeout(() => {
          btnElement.innerText = originalText;
          btnElement.style.borderColor = '';
          btnElement.style.color = '';
        }, 1600);
      }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(handleSuccess).catch(() => {
        fallbackCopy(text);
        handleSuccess();
      });
    } else {
      fallbackCopy(text);
      handleSuccess();
    }
  }

  function fallbackCopy(text) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
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

  // Live Duplicate TXID Validator
  let trxDebounceTimer = null;
  const trxInput = document.getElementById('trxIdInput');
  const trxBadge = document.getElementById('trxCheckBadge');
  const trxFeedback = document.getElementById('trxFeedbackMsg');
  const trxSpinner = document.getElementById('trxCheckSpinner');
  const submitBtn = document.getElementById('submitDepositBtn');

  function handleTrxInput(val) {
    clearTimeout(trxDebounceTimer);
    const trimmed = (val || '').trim();
    if (trimmed.length < 6) {
      trxBadge.style.display = 'none';
      trxFeedback.style.display = 'none';
      trxSpinner.style.display = 'none';
      trxInput.style.borderColor = '';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
      return;
    }
    trxSpinner.style.display = 'block';
    trxDebounceTimer = setTimeout(() => {
      validateTrxLive(trimmed);
    }, 450);
  }

  function validateTrxLive(val) {
    const trimmed = (val || '').trim();
    if (trimmed.length < 6) return;

    trxSpinner.style.display = 'block';

    fetch('deposit.php?action=validate_trx&trx_id=' + encodeURIComponent(trimmed))
      .then(res => res.json())
      .then(data => {
        trxSpinner.style.display = 'none';
        trxBadge.style.display = 'inline-block';
        trxFeedback.style.display = 'block';

        if (!data.valid) {
          // Duplicate or Invalid!
          trxBadge.innerHTML = '<span style="color: #ef4444; background: rgba(239, 68, 68, 0.12); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(239, 68, 68, 0.3);">Duplicate / Invalid</span>';
          trxFeedback.style.color = '#ef4444';
          trxFeedback.innerHTML = '<strong>❌ ' + data.message + '</strong>';
          trxInput.style.borderColor = '#ef4444';
          submitBtn.disabled = true;
          submitBtn.style.opacity = '0.5';
          submitBtn.style.cursor = 'not-allowed';
        } else {
          // Unique & Verified!
          trxBadge.innerHTML = '<span style="color: var(--accent-lime); background: var(--accent-lime-soft); padding: 2px 8px; border-radius: 4px; border: 1px solid var(--accent-lime);">Verified Unique ✓</span>';
          trxFeedback.style.color = 'var(--accent-lime)';
          trxFeedback.innerHTML = '✔ ' + data.message;
          trxInput.style.borderColor = 'var(--accent-lime)';
          submitBtn.disabled = false;
          submitBtn.style.opacity = '1';
          submitBtn.style.cursor = 'pointer';
        }
      })
      .catch(() => {
        trxSpinner.style.display = 'none';
      });
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
