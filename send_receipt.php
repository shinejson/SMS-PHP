<?php
require 'vendor/autoload.php';
require_once 'functions/sendEmail.php';
require_once 'functions/send.php';
require_once 'functions/generateReceiptPDF.php';
require_once 'config.php';
require_once 'session.php';

// --- Fetch School Settings ---
function getSchoolSettings($conn) {
    $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
}
$school = getSchoolSettings($conn);

if (!isset($_POST['payment_id']) || !isset($_POST['method'])) {
    $_SESSION['error'] = "Invalid request: Missing payment ID or method.";
    header("Location: payments.php");
    exit();
}

$payment_id = intval($_POST['payment_id']);
$method = $_POST['method'];

// === Fetch Payment Details ===
$sql = "SELECT p.*, 
               s.first_name, s.last_name, s.student_id as student_code,
               s.parent_contact, s.email as parent_email, 
               u.full_name as collector_name,
               t.term_name, s.class_id
        FROM payments p
        JOIN students s ON p.student_id = s.id
        LEFT JOIN users u ON p.collected_by_user_id = u.id
        LEFT JOIN terms t ON p.term_id = t.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "No payment found.";
    header("Location: payments.php");
    exit();
}
$payment = $result->fetch_assoc();
$stmt->close();

// === Billing amount for this class, term & payment_type ===
$billing_amount = 0;
$billing_sql = "SELECT amount FROM billing WHERE class_id=? AND term_id=? AND payment_type=? LIMIT 1";
$stmt = $conn->prepare($billing_sql);
$stmt->bind_param("iis", $payment['class_id'], $payment['term_id'], $payment['payment_type']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $billing_amount = floatval($row['amount']);
}
$stmt->close();

// === Total paid so far (same student, term, payment_type) ===
$total_paid = 0;
$total_sql = "SELECT SUM(amount) as total_paid FROM payments WHERE student_id=? AND term_id=? AND payment_type=?";
$stmt = $conn->prepare($total_sql);
$stmt->bind_param("iis", $payment['student_id'], $payment['term_id'], $payment['payment_type']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $total_paid = floatval($row['total_paid']);
}
$stmt->close();

// === Compute balance & status ===
$balance = $billing_amount - $total_paid;
if ($billing_amount > 0) {
    if ($total_paid == $billing_amount) {
        $status = "Full Payment";
    } elseif ($total_paid < $billing_amount) {
        $status = "Part Payment";
    } elseif ($total_paid > $billing_amount) {
        $status = "Overpayment";
    } else {
        $status = "Unknown";
    }
} else {
    $status = "Unknown";
}

// === Extract fields ===
$student_name   = $payment['first_name'] . ' ' . $payment['last_name'];
$receipt_no     = $payment['receipt_no'];
$payment_date   = date('F j, Y g:i A', strtotime($payment['payment_date']));
$term           = $payment['term_name'] ?? 'N/A';
$parent_email   = $payment['parent_email'];
$parent_contact = $payment['parent_contact'];
$collector_name = $payment['collector_name'] ?? 'N/A';

// === PDF Receipt Generation ===
$receiptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'receipts';
if (!is_dir($receiptsDir)) mkdir($receiptsDir, 0755, true);

$pdfFileName = $receipt_no . '.pdf';
$pdfFullPath = $receiptsDir . DIRECTORY_SEPARATOR . $pdfFileName;

$pdfGenerated = false;
if (function_exists('generateReceiptPDF')) {
    try {
        generateReceiptPDF([
            'school_name'   => $school['school_name'] ?? 'School',
            'logo'          => $school['logo'] ?? (__DIR__ . '/img/logo.png'),
            'student_name'  => $student_name,
            'student_code'  => $payment['student_code'],
            'reference'     => $receipt_no,
            'payment_type'  => $payment['payment_type'],
            'amount_paid'   => $payment['amount'],
            'billing_amount'=> $billing_amount,
            'total_paid'    => $total_paid,
            'balance'       => $balance,
            'status'        => $status,
            'term'          => $term,
            'description'   => $payment['description'] ?? '',
            'date'          => $payment_date,
            'collector_name'=> $collector_name,
            'payment_method'=> $payment['payment_method'],
            'footer'        => "Thank you for trusting " . ($school['school_name'] ?? 'Our School') . ".\nThis receipt is system-generated."
        ], $pdfFullPath);
        $pdfGenerated = true;
    } catch (Exception $e) {
        error_log("PDF error: " . $e->getMessage());
    }
}

// === Email / SMS sending ===
// Your existing conditional block for SMS method

// Your SMS sending code with additional checks
if ($method === 'sms') {
    if ($parent_contact) {
        // Validate phone number format
        $cleanPhone = preg_replace('/[^0-9+]/', '', $parent_contact);
        
        // Ensure phone number starts with country code for Ghana
        if (!preg_match('/^\+233/', $cleanPhone) && !preg_match('/^233/', $cleanPhone)) {
            // If starts with 0, replace with +233
            if (preg_match('/^0/', $cleanPhone)) {
                $cleanPhone = '+233' . substr($cleanPhone, 1);
            } else {
                $cleanPhone = '+233' . $cleanPhone;
            }
        } else if (preg_match('/^233/', $cleanPhone) && !preg_match('/^\+233/', $cleanPhone)) {
            $cleanPhone = '+' . $cleanPhone;
        }
        
        error_log("Original phone: $parent_contact, Cleaned phone: $cleanPhone");

        // School info
        $school = getSchoolSettings($conn);
        
        // Infobip config
        $apiKey      = "10177e9933e9629004913f2cdc1bded2-4681828d-696c-4d25-ab58-5c9530fdced5";
        $senderId    = $school['school_name'] ?? "GEBSCO";
        $apiEndpoint = "https://9kemld.api.infobip.com";
        
        // Build SMS content (shortened to avoid length issues)
        $receiptMessage = 
            "Receipt: {$school['school_name']}\n" .
            "Student: {$student_name}\n" .
            "Type: {$payment['payment_type']}\n" .
            "Paid: GHC " . number_format($payment['amount'], 2) . "\n" .
            "Balance: GHC " . number_format($balance, 2) . "\n" .
            "Ref: {$receipt_no}\n" .
            "Date: {$payment_date}";
        
        // Check message length (SMS limit is usually 160 characters for single SMS)
        error_log("SMS length: " . strlen($receiptMessage) . " characters");
        
        // Send SMS with cleaned phone number
        $smsResult = sendSmsReceipt($cleanPhone, $receiptMessage, $apiKey, $senderId, $apiEndpoint);
        
        if ($smsResult['status'] === 'success') {
            $_SESSION['message'] = $smsResult['message'];
            // Log the full response for debugging
            error_log("SMS Success Response: " . json_encode($smsResult));
        } else {
            $_SESSION['error'] = $smsResult['message'];
            error_log("SMS Error: " . $smsResult['message']);
        }
    } else {
        $_SESSION['error'] = "No parent contact available for SMS.";
        error_log("SMS Error: No parent contact found");
    }
}
 elseif ($method === 'email') {
    if ($parent_email) {
        $attachmentPath = $pdfGenerated ? $pdfFullPath : null;

        if (function_exists('sendEmail')) {
            $logoUrl = $school['logo'] ?? "./img/logo.png"; // dynamic school logo
            $schoolName = $school['school_name'] ?? 'School';
            $schoolPhone = $school['phone'] ?? '';
            $schoolEmail = $school['email'] ?? '';

            $htmlBody = "
            <div style='font-family:Arial, sans-serif; font-size:14px; color:#333;'>
                <div style='text-align:center; margin-bottom:20px;'>
                    <img src='{$logoUrl}' alt='School Logo' style='max-width:120px; margin-bottom:10px;'>
                    <h2 style='color:#2c3e50; margin:0;'>{$schoolName}</h2>
                    <p style='margin:0; font-size:13px; color:#555;'>Payment Receipt</p>
                </div>

                <p>Dear Parent/Guardian,</p>
                <p>Below are the details of your ward's payment:</p>
                
                <table style='border-collapse:collapse; width:100%; margin:15px 0;'>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Receipt No</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$receipt_no}</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Student</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$student_name}</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Payment Date</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$payment_date}</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Term</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$term}</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Payment Type</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$payment['payment_type']}</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Amount Paid</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>GHC " . number_format($payment['amount'], 2) . "</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Total Paid</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>GHC " . number_format($total_paid, 2) . "</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Billing Amount</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>GHC " . number_format($billing_amount, 2) . "</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Balance</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>GHC " . number_format($balance, 2) . "</td></tr>
                    <tr><td style='padding:8px; border:1px solid #ccc;'><strong>Status</strong></td>
                        <td style='padding:8px; border:1px solid #ccc;'>{$status}</td></tr>
                </table>
                
                <p><strong>Collected By:</strong> {$collector_name}</p>
                <p><strong>Payment Method:</strong> {$payment['payment_method']}</p>
                <p><strong>Description:</strong> " . ($payment['description'] ?: 'N/A') . "</p>
                
                <hr style='margin:20px 0; border:none; border-top:1px solid #ddd;'>
                <footer style='text-align:center; font-size:12px; color:#777;'>
                    <p>{$schoolName}</p>
                    <p>Tel: {$schoolPhone} | Email: {$schoolEmail}</p>
                    <p>&copy; " . date('Y') . " {$schoolName}. All rights reserved.</p>
                </footer>
            </div>";

            $email_sent = sendEmail(
                $parent_email,
                "Payment Receipt - {$receipt_no}",  // Subject
                $htmlBody,                         // HTML Body
                $attachmentPath,                   // PDF
                true                               // Flag for HTML
            );

            if ($email_sent) {
                $_SESSION['message'] = "Email receipt sent successfully to " . $parent_email;
            } else {
                $_SESSION['error'] = "Failed to send email receipt.";
            }
        }
    } else {
        $_SESSION['error'] = "No parent email available.";
    }
}

header("Location: print_receipt.php?id=" . $payment_id);
exit;
?>
