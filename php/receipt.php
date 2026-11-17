<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$paymentId = (int)($_GET['id'] ?? 0);
if (!$paymentId) {
    die("Invalid receipt ID.");
}

$stmt = $pdo->prepare("
    SELECT p.*, 
           s.full_name AS student_name, s.student_reg_no, s.phone AS student_phone, 
           s.national_id, s.address, s.course_category, s.course_fee,
           u.full_name AS recorder_name
    FROM payments p
    JOIN students s ON p.student_id = s.student_id
    LEFT JOIN users u ON p.recorded_by = u.user_id
    WHERE p.payment_id = ?
");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Payment record not found.");
}

// Calculate total paid up to and including this receipt
$stmtTotal = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE student_id = ? AND payment_id <= ?");
$stmtTotal->execute([$payment['student_id'], $paymentId]);
$paidSoFar = (float)$stmtTotal->fetchColumn();

$courseFee = (float)$payment['course_fee'];
$balanceRemaining = $courseFee - $paidSoFar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt - <?= htmlspecialchars($payment['receipt_no']) ?> | Anza Auto Driving School Mombasa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem 0;
            color: #212529;
        }
        .receipt-container {
            max-width: 780px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2.5rem;
            position: relative;
            border: 1px solid #e2e8f0;
        }
        .receipt-header {
            border-bottom: 2px solid #0d3b66;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .school-logo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #0d3b66;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 5rem;
            color: rgba(13, 59, 102, 0.05);
            font-weight: 900;
            text-transform: uppercase;
            pointer-events: none;
            user-select: none;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="no-print d-flex justify-content-between align-items-center mb-3" style="max-width: 780px; margin: 0 auto;">
        <a href="payments.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Payments</a>
        <button onclick="window.print()" class="btn btn-primary px-4 shadow-sm"><i class="fa-solid fa-print me-2"></i> Print Official Receipt</button>
    </div>

    <div class="receipt-container">
        <div class="watermark">ANZA AUTO</div>

        <!-- Header -->
        <div class="receipt-header text-center">
            <div class="school-logo">
                <i class="fa-solid fa-car-side"></i>
            </div>
            <h3 class="fw-bold text-dark mb-1">ANZA AUTO DRIVING SCHOOL</h3>
            <h5 class="fw-semibold text-primary mb-1">MOMBASA BRANCH</h5>
            <p class="text-muted small mb-0">
                Moi Avenue, P.O. Box 90123 - 80100 Mombasa, Kenya<br>
                Tel: +254 712 345 678 / +254 722 334 455 &bull; Email: mombasa@anzaautodrivingschool.co.ke<br>
                <em>NTSA Accredited Driving School &bull; License Ref: NTSA/DS/MBA/042</em>
            </p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="badge bg-primary px-3 py-2 fs-6">OFFICIAL TUITION RECEIPT</span>
            </div>
            <div class="text-end">
                <div class="fw-bold text-dark fs-5 font-monospace"><?= htmlspecialchars($payment['receipt_no']) ?></div>
                <small class="text-muted">Date: <?= format_date($payment['payment_date'], 'd-M-Y') ?></small>
            </div>
        </div>

        <!-- Student & Payment Details -->
        <div class="bg-light p-3 rounded-3 mb-4 border">
            <div class="row g-2">
                <div class="col-sm-6">
                    <span class="text-muted small">Student Full Name:</span>
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($payment['student_name']) ?></div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">Student Registration No:</span>
                    <div class="fw-bold font-monospace text-primary"><?= htmlspecialchars($payment['student_reg_no']) ?></div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">National ID / Passport:</span>
                    <div class="fw-semibold"><?= htmlspecialchars($payment['national_id'] ?? 'N/A') ?></div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">Phone Contact:</span>
                    <div class="fw-semibold"><?= htmlspecialchars($payment['student_phone']) ?></div>
                </div>
                <div class="col-12 mt-2">
                    <span class="text-muted small">Enrolled Training Course:</span>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($payment['course_category']) ?></div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown Table -->
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th>Item Description</th>
                    <th>Payment Method</th>
                    <th>Reference / Code</th>
                    <th class="text-end">Amount (KES)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Tuition Fee Payment</strong>
                        <div class="small text-muted"><?= htmlspecialchars($payment['notes'] ?? 'Tuition fee installment') ?></div>
                        <?php if ($payment['payment_method'] === 'M-Pesa'): ?>
                        <div class="small text-success mt-1"><i class="fa-solid fa-mobile-screen-button me-1"></i> Lipa na M-Pesa &bull; Prompt Phone: <strong>0715718022</strong></div>
                        <?php endif; ?>
                        <?php if ($payment['payment_method'] === 'Bank Transfer' || $payment['payment_method'] === 'Credit / Debit Card' || stripos($payment['notes'] ?? '', 'Card') !== false): ?>
                        <div class="small text-primary mt-1 p-2 bg-light rounded border border-primary border-opacity-25">
                            <i class="fa-solid fa-credit-card me-1"></i> <strong>Bank Transfer / Electronic Clearance</strong>
                            <div>Ref: <strong class="font-monospace text-dark"><?= htmlspecialchars($payment['reference_no'] ?? '-') ?></strong></div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= ($payment['payment_method'] === 'M-Pesa') ? 'bg-success' : (($payment['payment_method'] === 'Cash') ? 'bg-secondary' : 'bg-primary') ?>">
                            <?= htmlspecialchars($payment['payment_method']) ?>
                        </span>
                    </td>
                    <td class="font-monospace fw-bold"><?= htmlspecialchars($payment['reference_no'] ?? '-') ?></td>
                    <td class="text-end fw-bold fs-5 text-success"><?= number_format($payment['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Fee Ledger Summary -->
        <div class="row justify-content-end mb-4">
            <div class="col-md-6">
                <ul class="list-group list-group-flush border rounded-3 p-2 bg-light">
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-0">
                        <span class="text-muted">Total Course Fee:</span>
                        <span class="fw-semibold"><?= format_currency($courseFee) ?></span>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-0">
                        <span class="text-muted">Total Cumulative Paid:</span>
                        <span class="fw-bold text-success"><?= format_currency($paidSoFar) ?></span>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between py-1 border-top">
                        <span class="fw-bold">Outstanding Balance:</span>
                        <span class="fw-bold fs-6 <?= ($balanceRemaining > 0) ? 'text-danger' : 'text-success' ?>">
                            <?= format_currency($balanceRemaining) ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Signatures and Stamp -->
        <div class="row pt-4 mt-4 border-top">
            <div class="col-6">
                <small class="text-muted d-block mb-4">Received & Issued by:</small>
                <div class="fw-bold"><?= htmlspecialchars($payment['recorder_name'] ?? 'Authorized Officer') ?></div>
                <small class="text-muted">Accounts Desk &bull; Anza Auto Mombasa</small>
            </div>
            <div class="col-6 text-end">
                <div class="border border-2 border-secondary border-opacity-25 rounded-3 d-inline-block p-3 text-center" style="min-width: 180px;">
                    <i class="fa-solid fa-stamp text-muted opacity-25 fs-1 d-block mb-1"></i>
                    <small class="text-muted fw-bold text-uppercase" style="letter-spacing: 1px;">Official Branch Stamp</small>
                </div>
            </div>
        </div>

        <div class="text-center text-muted small mt-4 pt-3 border-top">
            <em>Thank you for training with Anza Auto Driving School Mombasa. Safe driving begins with professional training!</em>
        </div>
    </div>
</div>

</body>
</html>
