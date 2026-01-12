<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Get export parameters with proper validation
$format = strtolower($_GET['format'] ?? 'csv'); // Default to CSV if not specified
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$status = $_GET['status'] ?? 'all';
$invoice_type = $_GET['invoice_type'] ?? 'all';

// Validate format
$allowed_formats = ['csv', 'excel', 'pdf'];
if (!in_array($format, $allowed_formats)) {
    $format = 'csv'; // Default to CSV if invalid format
}

// Build query with filters
$sql = "SELECT i.*, 
               s.student_id as student_number,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               ay.year_name,
               t.term_name
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
        LEFT JOIN terms t ON i.term_id = t.id
        WHERE i.status != 'deleted'";

$params = [];
$types = "";

// Add date filter
if ($start_date && $end_date) {
    $sql .= " AND i.created_at BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date . ' 23:59:59';
    $types .= "ss";
} elseif ($start_date) {
    $sql .= " AND i.created_at >= ?";
    $params[] = $start_date;
    $types .= "s";
} elseif ($end_date) {
    $sql .= " AND i.created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
    $types .= "s";
}

// Add status filter
if ($status !== 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Add invoice type filter
if ($invoice_type !== 'all') {
    $sql .= " AND i.invoice_type = ?";
    $params[] = $invoice_type;
    $types .= "s";
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$invoices = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// Set headers based on format
switch ($format) {
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoices_' . date('Y-m-d') . '.csv');
        break;
        
    case 'excel':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename=invoices_' . date('Y-m-d') . '.xls');
        break;
        
    case 'pdf':
        // You'll need to implement PDF generation here
        // For now, we'll default to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoices_' . date('Y-m-d') . '.csv');
        break;
}

// Export data based on format
if ($format === 'csv' || $format === 'excel') {
    exportToCSVOrExcel($invoices, $format);
} elseif ($format === 'pdf') {
    exportToPDF($invoices);
}

function exportToCSVOrExcel($invoices, $format) {
    // Output CSV/Excel content
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 to help with Excel compatibility
    fputs($output, "\xEF\xBB\xBF");
    
    // Headers
    $headers = [
        'Invoice Number',
        'Student Name',
        'Student ID',
        'Invoice Type',
        'Amount (₵)',
        'Status',
        'Due Date',
        'Created Date',
        'Academic Year',
        'Term'
    ];
    
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($invoices as $invoice) {
        $row = [
            $invoice['invoice_number'],
            $invoice['student_name'],
            $invoice['student_number'],
            ucfirst($invoice['invoice_type']),
            number_format($invoice['amount'], 2),
            ucfirst($invoice['status']),
            $invoice['due_date'],
            $invoice['created_at'],
            $invoice['year_name'] ?? 'N/A',
            $invoice['term_name'] ?? 'N/A'
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportToPDF($invoices) {
    // For PDF export, you'll need to implement TCPDF or similar
    // For now, we'll provide a basic implementation
    
    require_once 'tcpdf/tcpdf.php';
    
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('School Management System');
    $pdf->SetAuthor('School Management System');
    $pdf->SetTitle('Invoices Export');
    $pdf->SetSubject('Invoices Report');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    
    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Invoices Report', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $headers = ['Invoice #', 'Student', 'Type', 'Amount', 'Status', 'Due Date'];
    $widths = [30, 50, 25, 25, 25, 35];
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 7, $headers[$i], 1, 0, 'C');
    }
    $pdf->Ln();
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    foreach ($invoices as $invoice) {
        $pdf->Cell($widths[0], 6, $invoice['invoice_number'], 'LR', 0, 'L');
        $pdf->Cell($widths[1], 6, substr($invoice['student_name'], 0, 25), 'LR', 0, 'L');
        $pdf->Cell($widths[2], 6, ucfirst($invoice['invoice_type']), 'LR', 0, 'C');
        $pdf->Cell($widths[3], 6, '₵' . number_format($invoice['amount'], 2), 'LR', 0, 'R');
        $pdf->Cell($widths[4], 6, ucfirst($invoice['status']), 'LR', 0, 'C');
        $pdf->Cell($widths[5], 6, date('M d, Y', strtotime($invoice['due_date'])), 'LR', 0, 'C');
        $pdf->Ln();
    }
    
    // Close table
    $pdf->Cell(array_sum($widths), 0, '', 'T');
    
    $pdf->Output('invoices_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Log the export action
function logAction($user_id, $action, $details) {
    global $conn;
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    $stmt->execute();
    $stmt->close();
}

// Log the export
logAction($_SESSION['user_id'], 'EXPORT_INVOICES', 'Exported invoices in ' . strtoupper($format) . ' format');
?>