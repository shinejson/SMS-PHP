<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get filter parameters
    $startDate = $_GET['start_date'] ?? date('Y-m-d');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $academicYearId = $_GET['academic_year_id'] ?? '';
    $termId = $_GET['term_id'] ?? '';
    $classId = $_GET['class_id'] ?? '';
    $paymentMethod = $_GET['payment_method'] ?? '';
    
    // Check if export is requested
    $export = $_GET['export'] ?? '';
    
    try {
        // Build the query with filters
        $sql = "SELECT 
                    p.id,
                    p.receipt_no,
                    p.amount,
                    p.payment_method,
                    p.payment_type,
                    DATE(p.payment_date) as payment_date,
                    TIME(p.payment_date) as payment_time,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    c.class_name,
                    u.full_name as collected_by
                FROM payments p
                JOIN students s ON p.student_id = s.id
                JOIN classes c ON s.class_id = c.id
                LEFT JOIN users u ON p.collected_by_user_id = u.id
                WHERE DATE(p.payment_date) BETWEEN ? AND ?";
        
        $params = [$startDate, $endDate];
        $paramTypes = 'ss';
        
        // Add optional filters
        if (!empty($academicYearId)) {
            $sql .= " AND p.academic_year_id = ?";
            $params[] = $academicYearId;
            $paramTypes .= 'i';
        }
        
        if (!empty($termId)) {
            $sql .= " AND p.term_id = ?";
            $params[] = $termId;
            $paramTypes .= 'i';
        }
        
        if (!empty($classId)) {
            $sql .= " AND s.class_id = ?";
            $params[] = $classId;
            $paramTypes .= 'i';
        }
        
        if (!empty($paymentMethod)) {
            $sql .= " AND p.payment_method = ?";
            $params[] = $paymentMethod;
            $paramTypes .= 's';
        }
        
        $sql .= " ORDER BY p.payment_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($paramTypes, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate totals
        $totalAmount = 0;
        $paymentMethods = [];
        $classSummary = [];
        
        foreach ($transactions as $transaction) {
            $totalAmount += (float)$transaction['amount'];
            
            // Payment method breakdown
            $method = $transaction['payment_method'];
            if (!isset($paymentMethods[$method])) {
                $paymentMethods[$method] = 0;
            }
            $paymentMethods[$method] += (float)$transaction['amount'];
            
            // Class summary
            $className = $transaction['class_name'];
            if (!isset($classSummary[$className])) {
                $classSummary[$className] = 0;
            }
            $classSummary[$className] += (float)$transaction['amount'];
        }
        
        // Handle export request
        if ($export) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="daily_report_' . date('Y-m-d') . '.' . $export . '"');
            
            $output = fopen('php://output', 'w');
            
            // CSV header
            fputcsv($output, ['Date', 'Receipt No', 'Student', 'Class', 'Amount', 'Method', 'Type', 'Collected By']);
            
            // CSV data
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['payment_date'],
                    $transaction['receipt_no'],
                    $transaction['student_name'],
                    $transaction['class_name'],
                    $transaction['amount'],
                    $transaction['payment_method'],
                    $transaction['payment_type'],
                    $transaction['collected_by']
                ]);
            }
            
            fclose($output);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_amount' => $totalAmount,
            'transaction_count' => count($transactions),
            'payment_methods' => $paymentMethods,
            'class_summary' => $classSummary,
            'transactions' => $transactions
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving report: ' . $e->getMessage()
        ]);
    }
}
?>