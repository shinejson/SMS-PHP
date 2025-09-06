<?php
// print_receipt.php
require_once 'config.php';
require_once 'session.php';
include 'school_info.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payments.php");
    exit();
}

$payment_id = intval($_GET['id']);

// Fetch payment details
$sql = "SELECT p.*, 
               s.first_name, s.last_name, s.student_id as student_code, 
               s.parent_name, s.parent_contact, s.email as parent_email, s.class_id,
               u.full_name as collector_name,
               t.term_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        LEFT JOIN users u ON p.collected_by_user_id = u.id
        LEFT JOIN terms t ON p.term_id = t.id
        WHERE p.id = $payment_id";

$result = $conn->query($sql);
$payment = $result->fetch_assoc();

if (!$payment) {
    $_SESSION['error'] = "Payment record not found.";
    header("Location: payments.php");
    exit();
}

// --- Billing & Total Paid Calculation ---
$billing_amount = 0;
$total_paid = 0;

// Get billing amount for this student’s class, term & payment type
$billing_sql = "SELECT amount FROM billing 
                WHERE class_id = {$payment['class_id']} 
                AND term_id = {$payment['term_id']} 
                AND payment_type = '{$payment['payment_type']}' 
                LIMIT 1";
$billing_res = $conn->query($billing_sql);
if ($billing_res && $row = $billing_res->fetch_assoc()) {
    $billing_amount = (float)$row['amount'];
}

// Get total paid by student for this payment type & term
$paid_sql = "SELECT SUM(amount) as total_paid 
             FROM payments 
             WHERE student_id = {$payment['student_id']} 
             AND term_id = {$payment['term_id']} 
             AND payment_type = '{$payment['payment_type']}'";
$paid_res = $conn->query($paid_sql);
if ($paid_res && $row = $paid_res->fetch_assoc()) {
    $total_paid = (float)$row['total_paid'];
}

// Status calculation
$status = "Unknown";
if ($total_paid < $billing_amount) {
    $status = "Part Payment";
} elseif ($total_paid == $billing_amount) {
    $status = "Full Payment";
} elseif ($total_paid > $billing_amount) {
    $status = "Overpayment";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - <?= htmlspecialchars($payment['receipt_no']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .receipt-container {
            max-width: 800px;
            margin: auto;
            background: #fff;
            border: 1px solid #ddd;
            padding: 25px;
            border-radius: 8px;
        }
        .school-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .school-header img {
            max-height: 80px;
        }
        .school-header h2 {
            margin: 10px 0 5px;
            font-size: 22px;
            color: #2c3e50;
        }
        .receipt-title {
            text-align: center;
            font-size: 20px;
            margin: 15px 0;
            font-weight: bold;
            color: #34495e;
        }
        .details-section {
            margin-bottom: 15px;
        }
        .details-section p {
            margin: 5px 0;
            font-size: 14px;
        }
        .summary-box {
            border: 1px solid #ccc;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            background: #f6faff;
        }
        .summary-box p {
            margin: 6px 0;
            font-size: 15px;
        }
        .status {
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            color: #fff;
        }
        .status.fullpayment { background: #27ae60; }
        .status.partpayment { background: #e67e22; }
        .status.overpayment { background: #c0392b; }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 13px;
            color: #777;
        }
        @media print {
            body {
                background: #fff;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
            }
            .no-print {
                display: none;
            }
        }


        /* ===== Receipt Action Buttons ===== */
.no-print button,
.no-print a,
.no-print form button {
    display: inline-block;
    margin: 5px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    text-decoration: none;
}

/* Primary buttons (Print, Send Email, Send SMS) */
.no-print .btn-primary {
    background: #3498db;
    color: #fff;
    border: 1px solid #2980b9;
}

.no-print .btn-primary:hover {
    background: #2980b9;
}

/* Cancel / Back button */
.no-print .btn-cancel {
    background: #e74c3c;
    color: #fff;
    border: 1px solid #c0392b;
}

.no-print .btn-cancel:hover {
    background: #c0392b;
}

/* Icon spacing */
.no-print button i,
.no-print a i {
    margin-right: 6px;
}

/* ===== Signature Section ===== */
.signature-section {
    margin-top: 50px;
    display: flex;
    justify-content: flex-end;
}

.signature-box {
    text-align: center;
    width: 250px;
    font-size: 14px;
    color: #333;
}

/* ===== Flash Messages ===== */
.alert {
    padding: 12px 18px;
    margin: 15px 0;
    border-radius: 5px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}


    </style>
</head>
<body>
<div class="receipt-container">
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
    </div>
<?php endif; ?>
            
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>


    <div class="school-header">
        <?php 
       $school = getSchoolSettings($conn);

        echo "<h1>{$school['school_name']}</h1>";
        echo "<img src='{$school['logo']}' alt='School Logo'>";
        echo "<p>{$school['address']} | {$school['phone']}</p>";
        echo "<p><em>Official Payment Receipt</em></p>"
        ?>
    </div>


    <div class="receipt-title">
        Receipt #<?= htmlspecialchars($payment['receipt_no']); ?>
    </div>

    <div class="details-section">
        <p><strong>Student Name:</strong> <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
        <p><strong>Student ID:</strong> <?= htmlspecialchars($payment['student_code']); ?></p>
        <p><strong>Parent/Guardian:</strong> <?= htmlspecialchars($payment['parent_name'] ?? 'N/A'); ?></p>
        <p><strong>Parent Contact:</strong> <?= htmlspecialchars($payment['parent_contact'] ?? 'N/A'); ?></p>
    </div>

    <hr>

    <div class="details-section">
        <p><strong>Payment Date:</strong> <?= date('F j, Y g:i A', strtotime($payment['payment_date'])); ?></p>
        <p><strong>Term:</strong> <?= htmlspecialchars($payment['term_name'] ?? 'N/A'); ?></p>
        <p><strong>Payment Type:</strong> <?= htmlspecialchars($payment['payment_type']); ?></p>
        <p><strong>Payment Method:</strong> <?= htmlspecialchars($payment['payment_method']); ?></p>
        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($payment['description'] ?? 'N/A')); ?></p>
        <p><strong>Collected By:</strong> <?= htmlspecialchars($payment['collector_name'] ?? 'N/A'); ?></p>
    </div>

    <div class="summary-box">
        <p><strong>Billing Amount:</strong> GHC<?= number_format($billing_amount, 2); ?></p>
        <p><strong>Total Paid by Student:</strong> GHC<?= number_format($total_paid, 2); ?></p>
        <p><strong>Status:</strong> <span class="status <?= strtolower(str_replace(' ', '', $status)); ?>"><?= $status; ?></span></p>
    </div>

    <div class="footer">
        <p>Thank you for your payment!</p>
        <p>Printed on <?= date("F j, Y, g:i A"); ?></p>

    </div>
    <div class="signature-section">
    <div class="signature-box">
        <p>______________________________</p>
        <p><strong>Authorized Signature</strong></p>
    </div>
</div>


    <div class="no-print" style="text-align:center; margin-top:15px;">
        <button onclick="window.print()" class="btn-primary">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <a href="payments.php" class="btn-cancel">Back to Payments</a>
         <form action="send_receipt.php" method="POST" style="display: inline-block;">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                        <input type="hidden" name="method" value="sms">
                        <button type="submit" class="btn-primary" title="Send SMS">
                            <i class="fas fa-sms"></i> Send SMS
                        </button>
        </form>
                    <form action="send_receipt.php" method="POST" style="display: inline-block;">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                        <input type="hidden" name="method" value="email">
                        <button type="submit" class="btn-primary" title="Send Email">
                            <i class="fas fa-envelope"></i> Send Email
                        </button>
             </form>
    </div>
</div>
<script>
 document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".alert");
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.classList.add("hide");
            });
        }, 5000); // 5 seconds
    }
});
</script>
</body>
</html>
