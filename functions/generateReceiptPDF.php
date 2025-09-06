<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // adjust path if needed
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Generates a PDF receipt for a payment.
 *
 * @param array $data An associative array with:
 * 'school_name','logo','student_name','student_code','reference','payment_type',
 * 'amount_paid','billing_amount','total_paid','balance','status','term',
 * 'description','date','collector_name','payment_method','footer'
 * @param string $outputPath
 * @return string
 */
function generateReceiptPDF($data, $outputPath) {
    global $conn;

    // --- Fetch school info if not already passed ---
    if (!isset($data['school_name']) || !isset($data['logo'])) {
        $sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $data['school_name'] = $data['school_name'] ?? $row['school_name'];
            $data['logo']        = $data['logo'] ?? $row['logo'];
            $data['address']     = $data['address'] ?? $row['address'];
            $data['phone']       = $data['phone'] ?? $row['phone'];
            $data['email']       = $data['email'] ?? $row['email'];
        }
    }

    $pdf = new Fpdi();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // Document properties
    $pdf->SetCreator($data['school_name'] ?? 'School');
    $pdf->SetAuthor($data['school_name'] ?? 'School');
    $pdf->SetTitle('Payment Receipt - ' . $data['reference']);
    $pdf->SetSubject('Payment Receipt');

    // --- Header ---
    $logoPath = $data['logo'] ?? './img/logo.png';
    if ($logoPath && file_exists($logoPath)) {
        $pdf->Image($logoPath, 10, 10, 25);
    }
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(40, 10);
    $pdf->Cell(0, 8, strtoupper($data['school_name'] ?? 'School Name'), 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetXY(40, 20);
    $pdf->Cell(0, 8, $data['address'] ?? '', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Tel: ' . ($data['phone'] ?? '') . ' | Email: ' . ($data['email'] ?? ''), 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Payment Receipt', 0, 1, 'C');

    $pdf->Ln(8);

    // --- Receipt Details Table ---
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetFillColor(240, 240, 240);

    $pdf->Cell(50, 8, 'Receipt No:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['reference'], 1, 1, 'L');

    $pdf->Cell(50, 8, 'Student:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['student_name'] . ' (' . ($data['student_code'] ?? '') . ')', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Payment Date:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['date'], 1, 1, 'L');

    $pdf->Cell(50, 8, 'Term:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['term'] ?? 'N/A', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Payment Type:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['payment_type'] ?? 'N/A', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Amount Paid:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, 'GHC ' . number_format($data['amount_paid'], 2), 1, 1, 'L');

    $pdf->Cell(50, 8, 'Total Paid:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, 'GHC ' . number_format($data['total_paid'], 2), 1, 1, 'L');

    $pdf->Cell(50, 8, 'Billing Amount:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, 'GHC ' . number_format($data['billing_amount'], 2), 1, 1, 'L');

    $pdf->Cell(50, 8, 'Balance:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, 'GHC ' . number_format($data['balance'], 2), 1, 1, 'L');

    $pdf->Cell(50, 8, 'Status:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['status'] ?? 'N/A', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Payment Method:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['payment_method'] ?? 'N/A', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Collected By:', 1, 0, 'L', 1);
    $pdf->Cell(0, 8, $data['collector_name'] ?? 'N/A', 1, 1, 'L');

    $pdf->Cell(50, 8, 'Description:', 1, 0, 'L', 1);
    $pdf->MultiCell(0, 8, $data['description'] ?? 'N/A', 1, 'L', 0, 1);

    $pdf->Ln(15);

    // --- Signature ---
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, '______________________________', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Authorized Signature', 0, 1, 'R');

    // --- Footer ---
    $pdf->SetY(-28);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 5, $data['school_name'] ?? 'School', 0, 1, 'C');
    $pdf->Cell(0, 5, $data['footer'] ?? 'This receipt is system-generated.', 0, 1, 'C');
    $pdf->Cell(0, 5, '© ' . date('Y') . ' ' . ($data['school_name'] ?? 'School'), 0, 1, 'C');

    // Output
    $pdf->Output($outputPath, 'F');
    return $outputPath;
}
