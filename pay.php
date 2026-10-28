<?php
/**
 * UniStay - STUDENT DEMO PAYMENT PAGE
 * This is a simulation for an academic project. It never charges real money.
 * Requires the existing db.php, student_fees and fee_payments tables.
 */
session_start();
require_once '../db.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'student') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userName = (string) ($_SESSION['user_name'] ?? 'Student');
$feeId = filter_input(INPUT_GET, 'fee_id', FILTER_VALIDATE_INT);
if (!$feeId || $feeId < 1) {
    header('Location: fees.php');
    exit;
}

if (empty($_SESSION['unistay_payment_csrf'])) {
    $_SESSION['unistay_payment_csrf'] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetchStudentFee($conn, $feeId, $userId, $lock = false) {
    $sql = 'SELECT id, fee_title, total_amount, paid_amount, due_date, status '
         . 'FROM student_fees WHERE id = ? AND user_id = ?' . ($lock ? ' FOR UPDATE' : '');
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { throw new RuntimeException('Unable to read fee details.'); }
    mysqli_stmt_bind_param($stmt, 'ii', $feeId, $userId);
    if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('Unable to load fee record.'); }
    $result = mysqli_stmt_get_result($stmt);
    $fee = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);
    return $fee;
}

$error = '';
$methods = ['UPI', 'Card', 'Net Banking'];
$selectedMethod = 'UPI';
$enteredAmount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedMethod = (string)($_POST['payment_method'] ?? 'UPI');
    $enteredAmount = trim((string)($_POST['amount'] ?? ''));
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['unistay_payment_csrf'], $token)) {
        $error = 'Your session has changed. Refresh this page and try again.';
    } elseif (!in_array($selectedMethod, $methods, true)) {
        $error = 'Please select a valid demo payment method.';
    } elseif (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $enteredAmount)) {
        $error = 'Enter a valid amount (up to two decimal places).';
    } else {
        $amount = round((float)$enteredAmount, 2);
        if ($amount <= 0) {
            $error = 'Payment amount must be greater than zero.';
        } else {
            try {
                mysqli_begin_transaction($conn);
                // Lock this fee record so two submissions cannot overpay it.
                $feeLocked = fetchStudentFee($conn, $feeId, $userId, true);
                if (!$feeLocked) {
                    throw new RuntimeException('Fee record was not found.');
                }
                $total = round((float)$feeLocked['total_amount'], 2);
                $paid = round((float)$feeLocked['paid_amount'], 2);
                $remaining = round(max(0, $total - $paid), 2);
                if ($remaining <= 0) {
                    throw new RuntimeException('This fee has already been paid.');
                }
                if ($amount > $remaining) {
                    throw new RuntimeException('Amount exceeds the remaining balance of ₹' . number_format($remaining, 2) . '.');
                }

                $newPaid = round($paid + $amount, 2);
                $newStatus = ($newPaid >= $total) ? 'Paid' : 'Partial';
                $update = mysqli_prepare($conn,
                    'UPDATE student_fees SET paid_amount = ?, status = ? WHERE id = ? AND user_id = ?');
                if (!$update) { throw new RuntimeException('Unable to update fee record.'); }
                mysqli_stmt_bind_param($update, 'dsii', $newPaid, $newStatus, $feeId, $userId);
                if (!mysqli_stmt_execute($update) || mysqli_stmt_affected_rows($update) !== 1) {
                    throw new RuntimeException('Unable to save updated fee balance.');
                }
                mysqli_stmt_close($update);

                // Store one payment-history entry for this simulated payment.
                $paymentDate = date('Y-m-d');
                $remarks = 'DEMO PAYMENT ONLY - simulated ' . $selectedMethod . ' payment; no real money transferred';
                $insert = mysqli_prepare($conn,
                    'INSERT INTO fee_payments (fee_id, amount, payment_date, payment_method, remarks) '
                    . 'VALUES (?, ?, ?, ?, ?)');
                if (!$insert) { throw new RuntimeException('Unable to create payment record.'); }
                mysqli_stmt_bind_param($insert, 'idsss', $feeId, $amount, $paymentDate, $selectedMethod, $remarks);
                if (!mysqli_stmt_execute($insert)) {
                    throw new RuntimeException('Unable to save payment history. Check fee_payments table fields.');
                }
                mysqli_stmt_close($insert);
                mysqli_commit($conn);

                // Rotate CSRF token to prevent accidental duplicate submission.
                $_SESSION['unistay_payment_csrf'] = bin2hex(random_bytes(32));
                $_SESSION['unistay_demo_payment_success'] =
                    'Demo payment of ₹' . number_format($amount, 2)
                    . ' recorded successfully via ' . $selectedMethod . '. No real money was transferred.';
                header('Location: fees.php');
                exit;
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                // Display a safe error message; do not expose SQL internals to students.
                error_log('UniStay demo payment error: ' . $e->getMessage());
                $error = ($e instanceof RuntimeException) ? $e->getMessage()
                    : 'Payment could not be recorded. Please check your database setup.';
            }
        }
    }
}

try {
    $fee = fetchStudentFee($conn, $feeId, $userId);
} catch (Throwable $e) {
    error_log('UniStay fee fetch error: ' . $e->getMessage());
    $fee = null;
    $error = 'Unable to load fee details. Please try again.';
}
if (!$fee) {
    http_response_code(404);
    $error = $error ?: 'This fee record is not available for your account.';
}
$total = $fee ? round((float)$fee['total_amount'], 2) : 0;
$paid = $fee ? round((float)$fee['paid_amount'], 2) : 0;
$remaining = round(max(0, $total - $paid), 2);
if ($enteredAmount === '' && $remaining > 0) {
    $enteredAmount = number_format($remaining, 2, '.', '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Payment - UniStay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{--navy:#1F2747;--teal:#0E6077;--gold:#E8B23D;--bg:#EFF4F7;--muted:#718092;--border:#DFE7EC;--green:#16805b}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:'Segoe UI',Arial,sans-serif;color:#1E2639}
        .dashboard{display:flex;min-height:100vh}.main{flex:1;min-width:0;padding:32px 36px 55px}
        .wrap{max-width:920px;margin:auto}.back{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--teal);font-weight:700;margin-bottom:20px}
        .heading{background:linear-gradient(120deg,#1F2747,#0E6077,#25899A);color:white;padding:32px;border-radius:18px;margin-bottom:22px}
        .heading h1{margin:8px 0;font-size:30px}.heading p{margin:0;opacity:.85;line-height:1.6}
        .tag{display:inline-block;background:var(--gold);color:var(--navy);border-radius:20px;padding:7px 12px;font-size:12px;font-weight:800}
        .grid{display:grid;grid-template-columns:1fr 1.1fr;gap:20px}.panel{background:#fff;border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 8px 24px rgba(31,39,71,.06)}
        .panel h2{font-size:19px;margin:0 0 20px}.line{display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid #edf1f3;padding:13px 0;font-size:14px}.line span{color:var(--muted)}
        .line strong{text-align:right}.line.remaining{font-size:19px;border-bottom:0}.line.remaining strong{color:var(--teal)}
        label.field{display:block;margin:18px 0 8px;font-weight:700;font-size:13px}.amount{width:100%;padding:13px;border:1px solid #cddbe1;border-radius:10px;font-size:18px}
        .method{display:flex;align-items:center;gap:12px;border:1px solid #dfe7ec;border-radius:12px;padding:14px;margin:10px 0;cursor:pointer}
        .method:has(input:checked){border-color:var(--teal);background:#f0fafb}.method input{accent-color:var(--teal)}.method i{color:var(--teal);font-size:19px;width:23px}
        .submit{width:100%;background:var(--teal);border:0;color:white;border-radius:11px;padding:15px;font-weight:800;font-size:15px;cursor:pointer;margin-top:15px}
        .submit:hover{background:#09485a}.submit:disabled{background:#aebcc4;cursor:not-allowed}
        .note{background:#fff7df;color:#7b5a0b;padding:13px 15px;border-radius:10px;font-size:12px;line-height:1.6;margin-top:18px}
        .error{padding:13px 16px;background:#fbe8ea;color:#9a2537;border-radius:10px;margin-bottom:18px}.success{color:var(--green);font-weight:700}
        @media(max-width:950px){.grid{grid-template-columns:1fr}.main{padding:20px}}@media(max-width:700px){.dashboard{display:block}}

        /* Match the existing UniStay student sidebar */
        .sidebar{width:230px;min-width:230px;min-height:100vh;position:sticky;top:0;align-self:flex-start;padding:30px 18px;background:#fff;border-right:1px solid var(--border);z-index:50}
        .logo{position:relative;display:flex;align-items:center;gap:12px;margin-bottom:50px;padding-left:8px}
        .logo::after{content:"Live  Learn  Belong";position:absolute;left:61px;top:31px;color:#7c8796;font-size:10px}
        .logo-box{width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:11px;color:white;background:linear-gradient(135deg,#0c556c,#168295);font-size:21px;font-weight:800}
        .logo h2{color:#162238;font-size:24px;font-weight:800;margin:0}
        .menu a{display:flex;align-items:center;gap:13px;padding:13px 14px;margin-bottom:8px;border-radius:10px;color:#45576e;text-decoration:none;font-size:14px;font-weight:500}
        .menu a i{width:22px;text-align:center;color:#395875}
        .menu a:hover{background:#f0f7f9;color:var(--teal)}
        .menu a.active{background:#ddf0f3;color:var(--teal);font-weight:700}
        @media(max-width:850px){.sidebar{width:205px;min-width:205px}}
        @media(max-width:700px){.sidebar{display:none}}
    </style>
</head>
<body>
<div class="dashboard">
    <?php include 'sidebar.php'; ?>
    <main class="main"><div class="wrap">
        <a class="back" href="fees.php"><i class="fa-solid fa-arrow-left"></i> Back to My Fees</a>
        <header class="heading">
            <span class="tag"><i class="fa-solid fa-flask"></i> DEMO PAYMENT</span>
            <h1>Pay Your Hostel Fee</h1>
            <p>Choose a payment method and simulate an installment for your UniStay project.</p>
        </header>
        <?php if ($error): ?><div class="error" role="alert"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($fee): ?>
        <div class="grid">
            <section class="panel">
                <h2><i class="fa-solid fa-file-invoice"></i> Fee Summary</h2>
                <div class="line"><span>Student</span><strong><?php echo h($userName); ?></strong></div>
                <div class="line"><span>Fee</span><strong><?php echo h($fee['fee_title']); ?></strong></div>
                <div class="line"><span>Total</span><strong>₹<?php echo number_format($total,2); ?></strong></div>
                <div class="line"><span>Paid</span><strong>₹<?php echo number_format($paid,2); ?></strong></div>
                <div class="line remaining"><span>Remaining</span><strong>₹<?php echo number_format($remaining,2); ?></strong></div>
                <div class="note"><strong>Academic simulation only.</strong> No bank, UPI, or card transaction takes place. A successful demo will update the local database fee balance and payment history.</div>
            </section>
            <section class="panel">
                <h2><i class="fa-solid fa-credit-card"></i> Payment Details</h2>
                <?php if ($remaining > 0): ?>
                <form method="post" action="pay.php?fee_id=<?php echo (int)$feeId; ?>" onsubmit="return confirm('Record this simulated payment in UniStay? No real money will be transferred.');">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['unistay_payment_csrf']); ?>">
                    <label class="field" for="amount">Amount to pay (₹)</label>
                    <input class="amount" type="number" name="amount" id="amount" step="0.01" min="0.01" max="<?php echo h(number_format($remaining,2,'.','')); ?>" value="<?php echo h($enteredAmount); ?>" required>
                    <label class="field">Choose demo method</label>
                    <label class="method"><input type="radio" name="payment_method" value="UPI" <?php echo $selectedMethod==='UPI'?'checked':''; ?>><i class="fa-solid fa-mobile-screen"></i><strong>UPI</strong></label>
                    <label class="method"><input type="radio" name="payment_method" value="Card" <?php echo $selectedMethod==='Card'?'checked':''; ?>><i class="fa-regular fa-credit-card"></i><strong>Debit / Credit Card</strong></label>
                    <label class="method"><input type="radio" name="payment_method" value="Net Banking" <?php echo $selectedMethod==='Net Banking'?'checked':''; ?>><i class="fa-solid fa-building-columns"></i><strong>Net Banking</strong></label>
                    <button class="submit" type="submit"><i class="fa-solid fa-circle-check"></i> Confirm Demo Payment</button>
                </form>
                <?php else: ?>
                    <p class="success"><i class="fa-solid fa-circle-check"></i> This fee is fully paid.</p>
                    <a class="back" href="fees.php">View payment history</a>
                <?php endif; ?>
            </section>
        </div>
        <?php endif; ?>
    </div></main>
</div>
</body>
</html>
