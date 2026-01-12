<?php
require_once 'config.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$student_id = $_POST['student_id'] ?? '';
$class_id = $_POST['class_id'] ?? '';
$term_id = $_POST['term_id'] ?? '';
$academic_year_id = $_POST['academic_year_id'] ?? '';

if (empty($student_id) || empty($term_id) || empty($academic_year_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Get student details
    $stmt = $conn->prepare("
        SELECT s.first_name, s.last_name, c.class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    // Get term details
    $stmt = $conn->prepare("SELECT term_name FROM terms WHERE id = ?");
    $stmt->bind_param("i", $term_id);
    $stmt->execute();
    $term = $stmt->get_result()->fetch_assoc();
    
    // Get academic year details
    $stmt = $conn->prepare("SELECT year_name FROM academic_years WHERE id = ?");
    $stmt->bind_param("i", $academic_year_id);
    $stmt->execute();
    $academic_year = $stmt->get_result()->fetch_assoc();
    
    // Get fee items for the selected class and academic year
    $fee_items = [];
    
    // If class_id is provided, use it, otherwise use student's class
    $target_class_id = !empty($class_id) ? $class_id : $student['class_id'];
    
    $stmt = $conn->prepare("
        SELECT b.id, b.payment_type, b.amount, b.description 
        FROM billing b 
        WHERE b.class_id = ? AND b.term_id = ? AND b.academic_year_id = ?
        ORDER BY b.payment_type
    ");
    $stmt->bind_param("iii", $target_class_id, $term_id, $academic_year_id);
    $stmt->execute();
    $billing_result = $stmt->get_result();
    
    while ($row = $billing_result->fetch_assoc()) {
        $fee_item = [
            'id' => $row['id'],
            'payment_type' => $row['payment_type'],
            'name' => $row['payment_type'],
            'amount' => $row['amount'],
            'description' => $row['description'],
            'sub_fees' => []
        ];
        
        // If this is a tuition payment, get the sub-fees
        if ($row['payment_type'] === 'Tuition') {
            $sub_stmt = $conn->prepare("
                SELECT sub_fee_name, sub_fee_amount 
                FROM tuition_details 
                WHERE billing_id = ?
                ORDER BY id
            ");
            $sub_stmt->bind_param("i", $row['id']);
            $sub_stmt->execute();
            $sub_result = $sub_stmt->get_result();
            
            while ($sub_row = $sub_result->fetch_assoc()) {
                $fee_item['sub_fees'][] = [
                    'name' => $sub_row['sub_fee_name'],
                    'amount' => $sub_row['sub_fee_amount']
                ];
            }
            
            $sub_stmt->close();
        }
        
        $fee_items[] = $fee_item;
    }
    
    // If no billing records found, use default fees with sub-fees for tuition
    if (empty($fee_items)) {
        $fee_items = [
            [
                'payment_type' => 'Tuition',
                'name' => 'Tuition fee Per Term',
                'amount' => 280.00,
                'description' => '',
                'sub_fees' => [
                    ['name' => 'Library Fee', 'amount' => 30.00],
                    ['name' => 'Science Laboratory', 'amount' => 40.00],
                    ['name' => 'Computer Lab', 'amount' => 25.00],
                    ['name' => 'Sports Facility', 'amount' => 15.00],
                    ['name' => 'Development Levy', 'amount' => 50.00],
                    ['name' => 'Textbook Rental', 'amount' => 35.00],
                    ['name' => 'Examination Materials', 'amount' => 25.00],
                    ['name' => 'Student ID Card', 'amount' => 10.00],
                    ['name' => 'Cultural Activities', 'amount' => 20.00],
                    ['name' => 'Maintenance Fee', 'amount' => 30.00]
                ]
            ],
            [
                'payment_type' => 'PTA',
                'name' => 'P.T.A. Fee Per Term',
                'amount' => 10.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Examination',
                'name' => 'Examination Fee Per Term',
                'amount' => 30.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Report Card',
                'name' => 'Report Card fee Per term',
                'amount' => 5.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Extra Classes',
                'name' => 'Extra Classes Fee Per Term',
                'amount' => 40.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'First Aid',
                'name' => 'First Aid Per Term',
                'amount' => 10.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'GNAPS',
                'name' => 'GNAPS Per Term',
                'amount' => 10.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Repairs',
                'name' => 'Repairs/Replacement Fee Per term',
                'amount' => 30.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Utilities',
                'name' => 'Utilities Per Term',
                'amount' => 30.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Sports',
                'name' => 'Sports & Culture Per Term',
                'amount' => 15.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Computer',
                'name' => 'Computer User Fee Per Term',
                'amount' => 10.00,
                'description' => '',
                'sub_fees' => []
            ],
            [
                'payment_type' => 'Sanitation',
                'name' => 'Sanitation Per Term',
                'amount' => 10.00,
                'description' => '',
                'sub_fees' => []
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
        'class_name' => $student['class_name'],
        'term_name' => $term['term_name'],
        'academic_year' => $academic_year['year_name'],
        'fee_items' => $fee_items
    ]);
    
} catch (Exception $e) {
    error_log("Error getting student bill: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>