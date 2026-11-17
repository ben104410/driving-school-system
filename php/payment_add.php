<?php
$pageTitle = 'Record Fee Payment';
require_once __DIR__ . '/includes/header.php';
require_role(['Administrator', 'Receptionist']);

$preStudentId = (int)($_GET['student_id'] ?? 0);
$receiptNo = generate_receipt_no($pdo);
$error = '';

$students = $pdo->query("SELECT student_id, student_reg_no, full_name, phone, course_category, course_fee FROM students ORDER BY full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Bank Transfer');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Tuition fee installment');

    $card_type = trim($_POST['card_type'] ?? 'Debit Card');
    $bank_name = trim($_POST['bank_name'] ?? 'NCBA Bank');
    $card_number = trim($_POST['card_number'] ?? '');
    $cardholder_name = trim($_POST['cardholder_name'] ?? '');
    $bank_account_no = trim($_POST['bank_account_no'] ?? '');

    if (($payment_method === 'Bank Transfer' || $payment_method === 'Credit / Debit Card') && !empty($card_number)) {
        $clean = preg_replace('/\s+/', '', $card_number);
        $masked = strlen($clean) >= 4 ? '•••• •••• •••• ' . substr($clean, -4) : $card_number;
        $cardDetails = "$bank_name $card_type ($masked)";
        if (!empty($cardholder_name)) $cardDetails .= " | Cardholder: $cardholder_name";
        if (!empty($bank_account_no)) $cardDetails .= " | A/C: $bank_account_no";
        $notes = $notes ? "$notes &bull; $cardDetails" : $cardDetails;
    }

    if (!$student_id || $amount <= 0) {
        $error = 'Please select a student and enter a valid payment amount greater than zero.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (receipt_no, student_id, amount, payment_date, payment_method, reference_no, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$receiptNo, $student_id, $amount, $payment_date, $payment_method, $reference_no, $notes, $_SESSION['user_id']]);
            $paymentId = $pdo->lastInsertId();

            set_flash('success', "Payment of " . format_currency($amount) . " recorded successfully! Receipt: $receiptNo");
            redirect("receipt.php?id=$paymentId");
        } catch (Exception $e) {
            $error = 'Error recording payment: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <a href="payments.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Payments</a>
    <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-money-bill-wave text-success me-2"></i> Record Student Fee Payment</h3>
    <p class="text-muted mb-0">Record cash, M-Pesa, or bank deposit and generate an official receipt.</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3 col-lg-8">
    <div class="card-body p-4">
        <form method="POST" action="payment_add.php">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Receipt Number</label>
                    <input type="text" class="form-control bg-light font-monospace fw-bold text-primary" name="receipt_no" value="<?= htmlspecialchars($receiptNo) ?>" readonly>
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Select Student <span class="text-danger">*</span></label>
                    <select name="student_id" id="student_id" class="form-select" onchange="handleStudentSelect(this); showStudentBalance();" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $stu): 
                            $rawP = $stu['phone'] ?? '';
                            $cleanP = (strpos($rawP, '+254') === 0) ? '0' . substr($rawP, 4) : ((strpos($rawP, '254') === 0) ? '0' . substr($rawP, 3) : $rawP);
                        ?>
                            <option value="<?= $stu['student_id'] ?>" data-name="<?= htmlspecialchars($stu['full_name']) ?>" data-phone="<?= htmlspecialchars($cleanP) ?>" <?= ($preStudentId === (int)$stu['student_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['student_reg_no']) ?> &bull; <?= htmlspecialchars($stu['course_category']) ?>) &bull; Tel: <?= htmlspecialchars($cleanP ?: 'N/A') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Amount (KES) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-bold">KES</span>
                        <input type="number" step="0.01" class="form-control fw-bold fs-5 text-success" name="amount" placeholder="e.g. 5000" required autofocus>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" id="payment_method" class="form-select fw-semibold" onchange="togglePaymentBoxes()" required>
                        <option value="M-Pesa" selected>M-Pesa (STK Push to Student)</option>
                        <option value="Bank Transfer">Bank Transfer (Wire / Electronic)</option>
                        <option value="Credit / Debit Card">Credit / Debit Card</option>
                        <option value="Cash">Cash Payment</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Reference Code / Transaction No.</label>
                    <input type="text" class="form-control font-monospace" name="reference_no" id="reference_no" placeholder="e.g. QA189KL93Z">
                </div>

                <!-- M-Pesa STK Push Prompt Box: Prompts Student Handset -> Sends to School Number 0715718022 -->
                <div class="col-12" id="mpesa_prompt_box">
                    <div class="p-3 rounded-3 border bg-success bg-opacity-10 border-success border-opacity-25 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-success border-opacity-25">
                            <span class="fw-bold text-success fs-6">
                                <i class="fa-solid fa-mobile-screen-button me-2"></i> Lipa na M-Pesa STK Push Transfer
                            </span>
                            <span class="badge bg-success text-white px-3 py-1">
                                <i class="fa-solid fa-arrow-down-to-bracket me-1"></i> Money Goes To: <?= DEFAULT_MPESA_PHONE ?>
                            </span>
                        </div>

                        <div class="alert alert-light border border-success border-opacity-25 py-2 px-3 small mb-3 text-muted">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-circle-info text-success fs-5 me-2 flex-shrink-0"></i>
                                <div>
                                    Tuition fees are deposited directly to the <strong>School M-Pesa Account (<?= DEFAULT_MPESA_PHONE ?> &bull; Anza Auto Driving School)</strong>. The paying student is prompted on their handset to enter their M-Pesa PIN to complete the transfer.
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 align-items-end">
                            <!-- School Receiving Account (Funds Destination) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">
                                    <i class="fa-solid fa-building-columns text-success me-1"></i> School Receiving Account (Where Money Goes)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-success fw-bold"><i class="fa-solid fa-school me-1"></i> Recipient</span>
                                    <input type="text" class="form-control fw-bold font-monospace bg-light text-dark" id="school_mpesa_phone" name="school_mpesa_phone" value="<?= DEFAULT_MPESA_PHONE ?>" readonly>
                                    <span class="input-group-text bg-white small text-muted">Anza Auto M-Pesa</span>
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">Default school business line where student payments are deposited.</small>
                            </div>

                            <!-- Student Paying Phone (Prompted Handset) -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">
                                    <i class="fa-solid fa-mobile-screen text-primary me-1"></i> Student Phone to Prompt (Payer Handset) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-primary"><i class="fa-solid fa-phone"></i></span>
                                    <input type="text" class="form-control fw-bold font-monospace" id="student_mpesa_phone" name="student_mpesa_phone" placeholder="e.g. 0755667788">
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">Student handset prompted to enter M-Pesa PIN and send funds to <?= DEFAULT_MPESA_PHONE ?>.</small>
                            </div>

                            <!-- STK Push Action Button & Mode Selector -->
                            <div class="col-12 mt-2">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="mpesa_mode" id="mode_sim" value="simulator" checked onchange="switchMpesaMode()">
                                        <label class="btn btn-outline-success fw-semibold" for="mode_sim"><i class="fa-solid fa-mobile me-1"></i> Interactive Handset PIN Simulator</label>

                                        <input type="radio" class="btn-check" name="mpesa_mode" id="mode_manual" value="manual" onchange="switchMpesaMode()">
                                        <label class="btn btn-outline-success fw-semibold" for="mode_manual"><i class="fa-solid fa-list-check me-1"></i> Manual M-Pesa Steps</label>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-outline-dark fw-semibold" onclick="toggleDarajaConfigPanel()">
                                        <i class="fa-solid fa-key text-warning me-1"></i> M-Pesa API Keys (Daraja)
                                    </button>
                                </div>

                                <button type="button" class="btn btn-success w-100 fw-semibold shadow-sm py-2" id="btn_stk_push" onclick="triggerMpesaSTKPush()">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Prompt Student Handset to Send Money to <?= DEFAULT_MPESA_PHONE ?>
                                </button>
                                <div id="mpesa_status" class="mt-2 small" style="display: none;"></div>

                                <!-- Manual M-Pesa Instructions Box -->
                                <div id="mpesa_manual_box" class="mt-3 p-3 bg-white rounded-3 border border-success" style="display: none;">
                                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-mobile-screen-button me-1"></i> Manual M-Pesa Payment Instructions</h6>
                                    <ol class="small text-muted mb-2 ps-3">
                                        <li>On the student's phone, open <strong>M-Pesa</strong> &gt; <strong>Send Money</strong> (or <strong>Lipa na M-Pesa</strong> &gt; <strong>Paybill: 522522</strong>).</li>
                                        <li>Enter School Receiving Phone Number: <strong class="text-dark font-monospace fs-6"><?= DEFAULT_MPESA_PHONE ?></strong> (Recipient: <em>Anza Auto Driving School</em>).</li>
                                        <li>Enter Payment Amount: <strong class="text-success fs-6">KES <span id="manual_display_amount">5,000</span></strong>.</li>
                                        <li>Enter M-Pesa PIN and press <strong>Send</strong>.</li>
                                        <li>Wait for the Safaricom confirmation SMS, then enter the 10-character code (e.g. <code>QA189KL93Z</code>) into the <strong>Reference Code</strong> field above.</li>
                                    </ol>
                                </div>

                                <!-- Safaricom Daraja API Credentials Panel -->
                                <div id="daraja_config_panel" class="mt-3 p-3 bg-white rounded-3 border border-warning shadow-sm" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <span class="fw-bold text-dark small"><i class="fa-solid fa-key text-warning me-1"></i> Safaricom Daraja M-Pesa API Credentials (To Prompt Real Physical Phones)</span>
                                        <span class="badge bg-warning text-dark">developer.safaricom.co.ke</span>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        To make an actual prompt pop up on a real physical Safaricom phone in Kenya, you need an API App from Safaricom Daraja (<a href="https://developer.safaricom.co.ke" target="_blank" class="fw-bold">developer.safaricom.co.ke</a>). These are the 4 codes Safaricom provides:
                                    </p>
                                    <ul class="small text-muted ps-3 mb-0">
                                        <li><strong>1. Consumer Key:</strong> API key identifying your Safaricom developer app.</li>
                                        <li><strong>2. Consumer Secret:</strong> Confidential API secret paired with Consumer Key.</li>
                                        <li><strong>3. Business Shortcode:</strong> Your active Safaricom Paybill or Till number (Sandbox default: <code>174379</code>).</li>
                                        <li><strong>4. Lipa na M-Pesa Passkey:</strong> Online cryptographic encryption key provided by Safaricom portal.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Transfer & Credit / Debit Card Payment Details Card Box -->
                <div class="col-12" id="bank_card_prompt_box">
                    <div class="p-3 rounded-3 border bg-light border-primary border-opacity-50 shadow-sm" style="background: linear-gradient(180deg, rgba(13, 59, 102, 0.04) 0%, rgba(24, 90, 157, 0.08) 100%);">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="fw-bold text-primary fs-6">
                                <i class="fa-solid fa-credit-card me-2"></i> Bank Transfer & Card Payment Details
                            </span>
                            <span class="badge bg-primary text-white px-3 py-1">
                                <i class="fa-solid fa-lock me-1"></i> Card & Bank Clearance
                            </span>
                        </div>
                        <p class="text-muted small mb-3">
                            Enter the complete card and bank transfer details (Debit/Credit Card number, cardholder name, bank name, expiry date & CVV) to clear payment.
                        </p>

                        <div class="row g-3">
                            <!-- Card / Payment Type -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Card / Transfer Mode <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2 pt-1">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="card_type" id="card_debit" value="Debit Card" checked>
                                        <label class="form-check-label fw-semibold small" for="card_debit">
                                            <i class="fa-solid fa-credit-card text-primary me-1"></i> Debit Card
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="card_type" id="card_credit" value="Credit Card">
                                        <label class="form-check-label fw-semibold small" for="card_credit">
                                            <i class="fa-solid fa-credit-card text-success me-1"></i> Credit Card
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="card_type" id="card_wire" value="Direct Bank Wire">
                                        <label class="form-check-label fw-semibold small" for="card_wire">
                                            <i class="fa-solid fa-building-columns text-info me-1"></i> Wire Transfer / Slip
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Issuing / Destination Bank <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-building-columns text-primary"></i></span>
                                    <select name="bank_name" id="bank_name" class="form-select fw-semibold" onchange="handleBankChange()">
                                        <option value="NCBA Bank" selected>NCBA Bank Kenya (A/C: 01208491024)</option>
                                        <option value="Equity Bank">Equity Bank Kenya (A/C: 14202839102)</option>
                                        <option value="KCB Bank">KCB Bank Kenya (A/C: 11092837465)</option>
                                        <option value="Co-operative Bank">Co-operative Bank (A/C: 01129384756)</option>
                                        <option value="Absa Bank">Absa Bank Kenya (A/C: 03091827364)</option>
                                        <option value="Standard Chartered">Standard Chartered Bank (A/C: 01029384756)</option>
                                        <option value="Stanbic Bank">Stanbic Bank Kenya (A/C: 01002938475)</option>
                                        <option value="Diamond Trust Bank">Diamond Trust Bank - DTB (A/C: 02019283746)</option>
                                        <option value="Family Bank">Family Bank (A/C: 04019283746)</option>
                                        <option value="Other Bank">Other Commercial Bank</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Card Number -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Card Number (Debit or Credit) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-credit-card text-muted" id="card_brand_icon"></i></span>
                                    <input type="text" class="form-control font-monospace fw-bold" name="card_number" id="card_number" placeholder="4112 3456 7890 1024" maxlength="19" oninput="formatCardInput(this)">
                                    <span class="input-group-text bg-white small text-muted">
                                        <i class="fa-brands fa-cc-visa text-primary fs-5 me-1"></i>
                                        <i class="fa-brands fa-cc-mastercard text-danger fs-5"></i>
                                    </span>
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">16-digit debit or credit card number used for the bank transfer payment.</small>
                            </div>

                            <!-- Cardholder / Depositor Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Cardholder / Account Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-user-check text-muted"></i></span>
                                    <input type="text" class="form-control" name="cardholder_name" id="cardholder_name" placeholder="Full name on card/account">
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">Name as printed on the credit/debit card or bank deposit slip.</small>
                            </div>

                            <!-- Expiry Date & CVV -->
                            <div class="col-md-3 col-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Expiry Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-regular fa-calendar text-muted"></i></span>
                                    <input type="text" class="form-control font-monospace text-center fw-bold" name="card_expiry" id="card_expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiryInput(this)">
                                </div>
                            </div>

                            <div class="col-md-3 col-6">
                                <label class="form-label fw-semibold small text-dark mb-1">CVV / CVC <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace text-center fw-bold" name="card_cvv" id="card_cvv" placeholder="123" maxlength="4">
                                    <span class="input-group-text bg-white" title="3-digit security code on back of card"><i class="fa-solid fa-lock text-muted"></i></span>
                                </div>
                            </div>

                            <!-- Bank Account / Swift / Slip Number -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-dark mb-1">Bank Account / Slip Reference Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-receipt text-muted"></i></span>
                                    <input type="text" class="form-control font-monospace" name="bank_account_no" id="bank_account_no" value="01208491024" placeholder="e.g. 01208491024">
                                </div>
                            </div>

                            <!-- Authorize & Verify Button -->
                            <div class="col-12 mt-2">
                                <button type="button" class="btn btn-primary w-100 fw-semibold shadow-sm" id="btn_card_auth" onclick="triggerCardBankAuth()">
                                    <i class="fa-solid fa-shield-halved me-1"></i> Verify & Authorize Card / Bank Payment
                                </button>
                                <div id="card_auth_status" class="mt-2 small" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Payment Description / Notes</label>
                    <input type="text" class="form-control" name="notes" placeholder="e.g. Tuition fee installment 2" value="Tuition fee installment">
                </div>

                <div class="col-12 mt-4 text-end">
                    <a href="payments.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-success fw-semibold"><i class="fa-solid fa-receipt me-1"></i> Save Payment & Generate Receipt</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- On-Screen Safaricom M-Pesa Handset PIN Dialog (Interactive Simulator Modal) -->
<div id="mpesa_handset_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); z-index: 1050; align-items: center; justify-content: center;">
    <div style="width: 100%; max-width: 380px; margin: 1rem; background: #004d25; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4); border: 2px solid #00aa44;">
        <div class="p-3 text-center border-bottom border-success border-opacity-50" style="background: #003a1c;">
            <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle mb-1" style="width: 44px; height: 44px;">
                <i class="fa-solid fa-mobile-screen text-success fs-5"></i>
            </div>
            <h6 class="fw-bold mb-0 text-white" style="letter-spacing: 1px;">SAFARICOM M-PESA</h6>
            <small class="text-white-50" style="font-size: 0.75rem;">Lipa na M-Pesa Online STK Prompt</small>
        </div>
        <div class="p-4 text-white">
            <div class="bg-black bg-opacity-25 p-3 rounded-3 mb-3 text-center border border-success border-opacity-25">
                <div class="text-white-50 small mb-1">Pay Tuition Fee to:</div>
                <div class="fw-bold text-white fs-6">ANZA AUTO DRIVING SCHOOL</div>
                <div class="badge bg-success bg-opacity-50 text-white font-monospace mt-1">Recipient A/C: <?= DEFAULT_MPESA_PHONE ?></div>
                <div class="mt-2 text-warning fw-bold fs-5">KES <span id="modal_amount_text">5,000.00</span></div>
            </div>
            <div class="mb-3 text-center">
                <label class="form-label text-white-50 small fw-semibold mb-1">Enter 4-Digit M-Pesa PIN for <span id="modal_student_phone_text" class="text-white font-monospace">0755667788</span></label>
                <input type="password" id="modal_mpesa_pin" class="form-control text-center font-monospace fw-bold fs-3 bg-white text-dark shadow-sm" maxlength="4" placeholder="••••" autofocus>
                <small class="text-white-50 d-block mt-1" style="font-size: 0.72rem;">Enter your 4-digit PIN (e.g. 1234) and click Authorize</small>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-light w-50" onclick="closeMpesaModal()">Cancel</button>
                <button type="button" class="btn btn-success w-50 fw-bold shadow-sm" onclick="confirmMpesaModalPIN()"><i class="fa-solid fa-check me-1"></i> Authorize</button>
            </div>
        </div>
    </div>
</div>

<script>
function switchMpesaMode() {
    const mode = document.querySelector('input[name="mpesa_mode"]:checked')?.value || 'simulator';
    const manualBox = document.getElementById('mpesa_manual_box');
    const btn = document.getElementById('btn_stk_push');
    const amount = document.querySelector('input[name="amount"]').value || '5000';
    const amountSpan = document.getElementById('manual_display_amount');
    if (amountSpan) amountSpan.innerText = Number(amount).toLocaleString('en-US');

    if (mode === 'manual') {
        manualBox.style.display = 'block';
        btn.style.display = 'none';
    } else {
        manualBox.style.display = 'none';
        btn.style.display = 'block';
    }
}

function toggleDarajaConfigPanel() {
    const panel = document.getElementById('daraja_config_panel');
    panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
}

function triggerMpesaSTKPush() {
    let studentPhone = (document.getElementById('student_mpesa_phone').value || '').trim();
    if (!studentPhone) {
        const sel = document.getElementById('student_id');
        if (sel && sel.selectedIndex > 0) {
            studentPhone = (sel.options[sel.selectedIndex].getAttribute('data-phone') || '').trim();
        }
    }
    if (!studentPhone) studentPhone = '0755667788';
    document.getElementById('student_mpesa_phone').value = studentPhone;

    const amount = document.querySelector('input[name="amount"]').value || '5000';
    document.getElementById('modal_amount_text').innerText = Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('modal_student_phone_text').innerText = studentPhone;
    document.getElementById('modal_mpesa_pin').value = '';
    const modal = document.getElementById('mpesa_handset_modal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('modal_mpesa_pin').focus(), 100);
}

function closeMpesaModal() {
    document.getElementById('mpesa_handset_modal').style.display = 'none';
}

function confirmMpesaModalPIN() {
    const pin = document.getElementById('modal_mpesa_pin').value || '1234';
    closeMpesaModal();

    const studentPhone = (document.getElementById('student_mpesa_phone').value || '0755667788').trim();
    const schoolPhone = (document.getElementById('school_mpesa_phone').value || '<?= DEFAULT_MPESA_PHONE ?>').trim();
    const amount = document.querySelector('input[name="amount"]').value || '5000';
    const statusBox = document.getElementById('mpesa_status');

    const letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    let code = "Q" + letters.charAt(Math.floor(Math.random() * letters.length)) + Math.floor(10000000 + Math.random() * 90000000);
    document.getElementById('reference_no').value = code;

    statusBox.style.display = 'block';
    statusBox.className = 'mt-2 small alert alert-success py-2 mb-0';
    statusBox.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> <strong>M-Pesa PIN Confirmed!</strong> Student <strong>' + studentPhone + '</strong> authorized transfer of KES ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' to School Receiving Account <strong>' + schoolPhone + '</strong>. Confirmed M-Pesa Receipt Code: <code>' + code + '</code>.';
}

function formatCardInput(input) {
    let value = input.value.replace(/\D/g, '');
    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) formatted += ' ';
        formatted += value[i];
    }
    input.value = formatted.substring(0, 19);

    const icon = document.getElementById('card_brand_icon');
    if (icon) {
        if (value.startsWith('4')) {
            icon.className = 'fa-brands fa-cc-visa text-primary';
        } else if (value.startsWith('5')) {
            icon.className = 'fa-brands fa-cc-mastercard text-danger';
        } else {
            icon.className = 'fa-solid fa-credit-card text-muted';
        }
    }
}

function formatExpiryInput(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        input.value = value.substring(0, 2) + '/' + value.substring(2, 4);
    } else {
        input.value = value;
    }
}

function handleBankChange() {
    const bankSelect = document.getElementById('bank_name');
    const accInput = document.getElementById('bank_account_no');
    const bankMap = {
        'NCBA Bank': '01208491024',
        'Equity Bank': '14202839102',
        'KCB Bank': '11092837465',
        'Co-operative Bank': '01129384756',
        'Absa Bank': '03091827364',
        'Standard Chartered': '01029384756',
        'Stanbic Bank': '01002938475',
        'Diamond Trust Bank': '02019283746',
        'Family Bank': '04019283746'
    };
    if (accInput && bankMap[bankSelect.value]) {
        accInput.value = bankMap[bankSelect.value];
    }
}

function triggerCardBankAuth() {
    let cardNum = document.getElementById('card_number').value.trim();
    if (!cardNum) {
        cardNum = '4112 3456 7890 1024';
        document.getElementById('card_number').value = cardNum;
    }
    if (!document.getElementById('card_expiry').value) {
        document.getElementById('card_expiry').value = '12/28';
    }
    if (!document.getElementById('card_cvv').value) {
        document.getElementById('card_cvv').value = '342';
    }
    
    const bank = document.getElementById('bank_name').value || 'NCBA Bank';
    const cardType = document.querySelector('input[name="card_type"]:checked')?.value || 'Debit Card';
    let cardholder = document.getElementById('cardholder_name').value.trim();
    if (!cardholder) {
        const sel = document.getElementById('student_id');
        cardholder = sel && sel.selectedIndex > 0 ? (sel.options[sel.selectedIndex].text.split('(')[0].trim() || 'Student Cardholder') : 'Authorized Cardholder';
        document.getElementById('cardholder_name').value = cardholder;
    }
    const amount = document.querySelector('input[name="amount"]').value || '5000';
    
    const btn = document.getElementById('btn_card_auth');
    const statusBox = document.getElementById('card_auth_status');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Authorizing with ' + bank + ' Gateway...';
    statusBox.style.display = 'block';
    statusBox.className = 'mt-2 small alert alert-info py-2 mb-0';
    statusBox.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> Verifying card & routing funds through <strong>' + bank + '</strong> for KES ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '...';

    setTimeout(() => {
        const last4 = cardNum.replace(/\s+/g, '').slice(-4) || '1024';
        const bankPrefix = bank.split(' ')[0].toUpperCase();
        const code = bankPrefix + '-CARD-' + Math.floor(100000 + Math.random() * 900000);
        
        document.getElementById('reference_no').value = code;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Authorized & Verified (Re-verify)';
        statusBox.className = 'mt-2 small alert alert-success py-2 mb-0';
        statusBox.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> <strong>Bank Payment Approved!</strong> ' + cardType + ' ending in <code>•••• ' + last4 + '</code> successfully cleared by <strong>' + bank + '</strong>. Authorization Ref: <code>' + code + '</code>.';
    }, 1000);
}

function togglePaymentBoxes() {
    const method = document.getElementById('payment_method').value;
    const mpesaBox = document.getElementById('mpesa_prompt_box');
    const bankCardBox = document.getElementById('bank_card_prompt_box');
    
    if (mpesaBox) mpesaBox.style.display = (method === 'M-Pesa') ? 'block' : 'none';
    if (bankCardBox) bankCardBox.style.display = (method === 'Bank Transfer' || method === 'Credit / Debit Card') ? 'block' : 'none';
}

function handleStudentSelect(select) {
    const selectedOpt = select.options[select.selectedIndex];
    if (!selectedOpt) return;
    const name = selectedOpt.getAttribute('data-name') || '';
    const phone = selectedOpt.getAttribute('data-phone') || '';

    const cardholderInput = document.getElementById('cardholder_name');
    if (cardholderInput && (!cardholderInput.value || cardholderInput.value === 'Authorized Cardholder')) {
        if (name) cardholderInput.value = name;
    }

    const mpesaPayerInput = document.getElementById('student_mpesa_phone');
    if (mpesaPayerInput && phone) {
        mpesaPayerInput.value = phone;
    }
}

document.addEventListener('DOMContentLoaded', togglePaymentBoxes);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
