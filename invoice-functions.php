<?php
/**
 * Invoice Management Functions
 * Reusable functions for invoice operations
 */

require_once 'config.php';

/**
 * Generate a unique invoice number
 * Format: INV-YYYY-XXXX (where XXXX is sequential number)
 */
function generateInvoiceNumber() {
    global $conn;
    
    $year = date('Y');
    $prefix = 'INV-' . $year . '-';
    
    // Get the last invoice number for this year
    $sql = "SELECT invoice_number FROM invoices 
            WHERE invoice_number LIKE ? 
            AND status != 'deleted'
            ORDER BY id DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_invoice = $result->fetch_assoc();
        $last_number = intval(substr($last_invoice['invoice_number'], -4));
        $new_number = str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_number = '0001';
    }
    
    $stmt->close();
    
    return $prefix . $new_number;
}

/**
 * Create a new invoice
 */
function createInvoice($invoice_data) {
    global $conn;
    
    $required_fields = ['student_id', 'academic_year_id', 'term_id', 'invoice_type', 'amount', 'due_date'];
    
    // Validate required fields
    foreach ($required_fields as $field) {
        if (!isset($invoice_data[$field]) || empty($invoice_data[$field])) {
            return ['success' => false, 'message' => "Missing required field: $field"];
        }
    }
    
    // Validate amount
    if ($invoice_data['amount'] <= 0) {
        return ['success' => false, 'message' => 'Amount must be greater than zero'];
    }
    
    // Validate due date
    if (strtotime($invoice_data['due_date']) < strtotime(date('Y-m-d'))) {
        return ['success' => false, 'message' => 'Due date cannot be in the past'];
    }
    
    // Generate invoice number
    $invoice_number = generateInvoiceNumber();
    
    // Prepare SQL
    $sql = "INSERT INTO invoices (invoice_number, student_id, academic_year_id, term_id, 
            invoice_type, amount, due_date, description, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiisisss", 
        $invoice_number,
        $invoice_data['student_id'],
        $invoice_data['academic_year_id'],
        $invoice_data['term_id'],
        $invoice_data['invoice_type'],
        $invoice_data['amount'],
        $invoice_data['due_date'],
        $invoice_data['description'] ?? '',
        $invoice_data['created_by']
    );
    
    if ($stmt->execute()) {
        $invoice_id = $stmt->insert_id;
        $stmt->close();
        
        // Log the action
        logAction($invoice_data['created_by'], 'CREATE_INVOICE', "Created invoice: $invoice_number");
        
        return [
            'success' => true, 
            'message' => 'Invoice created successfully',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number
        ];
    } else {
        $error = $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => "Error creating invoice: $error"];
    }
}

/**
 * Update invoice status
 */
function updateInvoiceStatus($invoice_id, $status, $user_id) {
    global $conn;
    
    $allowed_statuses = ['paid', 'unpaid', 'overdue', 'cancelled'];
    
    if (!in_array($status, $allowed_statuses)) {
        return ['success' => false, 'message' => 'Invalid status'];
    }
    
    // Get current invoice details
    $invoice = getInvoiceById($invoice_id);
    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }
    
    $payment_date = ($status === 'paid') ? date('Y-m-d') : null;
    
    $sql = "UPDATE invoices SET status = ?, payment_date = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $payment_date, $invoice_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Log the action
        logAction($user_id, 'UPDATE_INVOICE_STATUS', 
            "Updated invoice {$invoice['invoice_number']} to $status");
        
        return ['success' => true, 'message' => 'Invoice status updated successfully'];
    } else {
        $error = $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => "Error updating invoice status: $error"];
    }
}

/**
 * Get invoice by ID with full details
 */
function getInvoiceById($invoice_id) {
    global $conn;
    
    $sql = "SELECT i.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.student_id as student_number,
                   s.email, s.address,
                   s.parent_name, s.parent_contact,
                   ay.year_name, t.term_name,
                   CONCAT(u.username, ' ', u.full_name) as created_by_name,
                   DATEDIFF(i.due_date, CURDATE()) as days_until_due
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
            LEFT JOIN terms t ON i.term_id = t.id
            LEFT JOIN users u ON i.created_by = u.id
            WHERE i.id = ? AND i.status != 'deleted'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    return $invoice;
}

/**
 * Get invoices with filtering and pagination
 */
function getInvoices($filters = [], $page = 1, $limit = 20) {
    global $conn;
    
    $where_conditions = ["i.status != 'deleted'"];
    $params = [];
    $param_types = '';
    
    // Apply filters
    if (!empty($filters['status'])) {
        $where_conditions[] = "i.status = ?";
        $params[] = $filters['status'];
        $param_types .= 's';
    }
    
    if (!empty($filters['type'])) {
        $where_conditions[] = "i.invoice_type = ?";
        $params[] = $filters['type'];
        $param_types .= 's';
    }
    
    if (!empty($filters['search'])) {
        $where_conditions[] = "(i.invoice_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ssss';
    }
    
    if (!empty($filters['student_id'])) {
        $where_conditions[] = "i.student_id = ?";
        $params[] = $filters['student_id'];
        $param_types .= 'i';
    }
    
    if (!empty($filters['academic_year_id'])) {
        $where_conditions[] = "i.academic_year_id = ?";
        $params[] = $filters['academic_year_id'];
        $param_types .= 'i';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM invoices i JOIN students s ON i.student_id = s.id $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
    $total_rows = $total_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Calculate pagination
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_rows / $limit);
    
    // Get invoices
    $sql = "SELECT i.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.student_id as student_number,
                   ay.year_name, t.term_name,
                   DATEDIFF(i.due_date, CURDATE()) as days_until_due
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
            LEFT JOIN terms t ON i.term_id = t.id
            $where_clause
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return [
        'invoices' => $invoices,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * Get invoice statistics
 */
function getInvoiceStatistics() {
    global $conn;
    
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as total_unpaid,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue,
                SUM(CASE WHEN status = 'cancelled' THEN amount ELSE 0 END) as total_cancelled,
                AVG(amount) as avg_invoice_amount,
                COUNT(CASE WHEN DATEDIFF(due_date, CURDATE()) < 0 AND status = 'unpaid' THEN 1 END) as overdue_count,
                SUM(amount) as total_amount,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN status = 'unpaid' THEN 1 END) as unpaid_count
            FROM invoices 
            WHERE status != 'deleted'";
    
    $result = $conn->query($sql);
    return $result->fetch_assoc();
}

/**
 * Get overdue invoices
 */
function getOverdueInvoices() {
    global $conn;
    
    $sql = "SELECT i.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.student_id as student_number,
                   DATEDIFF(CURDATE(), i.due_date) as days_overdue
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            WHERE i.status = 'unpaid' 
            AND i.due_date < CURDATE()
            AND i.status != 'deleted'
            ORDER BY i.due_date ASC";
    
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get student's invoice history
 */
function getStudentInvoices($student_id) {
    global $conn;
    
    $sql = "SELECT i.*, ay.year_name, t.term_name
            FROM invoices i
            LEFT JOIN academic_years ay ON i.academic_year_id = ay.id
            LEFT JOIN terms t ON i.term_id = t.id
            WHERE i.student_id = ? AND i.status != 'deleted'
            ORDER BY i.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoices = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $invoices;
}

/**
 * Calculate invoice summary for student
 */
function getStudentInvoiceSummary($student_id) {
    global $conn;
    
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as total_unpaid,
                SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue,
                SUM(amount) as total_billed
            FROM invoices 
            WHERE student_id = ? AND status != 'deleted'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
    
    return $summary;
}

/**
 * Send invoice reminder (placeholder function - integrate with email system)
 */
function sendInvoiceReminder($invoice_id) {
    $invoice = getInvoiceById($invoice_id);
    
    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }
    
    // Here you would integrate with your email system
    // This is a placeholder implementation
    
    $subject = "Payment Reminder: Invoice {$invoice['invoice_number']}";
    $message = "Dear {$invoice['student_name']},\n\n" .
               "This is a reminder that invoice {$invoice['invoice_number']} for ₵" . 
               number_format($invoice['amount'], 2) . " is due on " . 
               date('F j, Y', strtotime($invoice['due_date'])) . ".\n\n" .
               "Please make payment at your earliest convenience.\n\n" .
               "Thank you.";
    
    // Log the reminder action
    logAction($_SESSION['user_id'] ?? 0, 'SEND_REMINDER', 
        "Reminder sent for invoice: {$invoice['invoice_number']}");
    
    return [
        'success' => true, 
        'message' => 'Reminder prepared for sending',
        'subject' => $subject,
        'message_body' => $message
    ];
}

/**
 * Validate invoice data before processing
 */
function validateInvoiceData($data) {
    $errors = [];
    
    // Required fields validation
    $required = ['student_id', 'academic_year_id', 'term_id', 'invoice_type', 'amount', 'due_date'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '$field' is required";
        }
    }
    
    // Numeric validation
    if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
        $errors[] = 'Amount must be a positive number';
    }
    
    // Date validation
    if (isset($data['due_date']) && !strtotime($data['due_date'])) {
        $errors[] = 'Invalid due date format';
    }
    
    // Date not in past
    if (isset($data['due_date']) && strtotime($data['due_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Due date cannot be in the past';
    }
    
    return $errors;
}

/**
 * Log action to activity log
 */
function logAction($user_id, $action, $details) {
    global $conn;
    
    $sql = "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $action, $details, 
        $_SERVER['REMOTE_ADDR'] ?? 'unknown', 
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $stmt->execute();
    $stmt->close();
}

/**
 * Get school information (you should create a school_settings table)
 */
function getSchoolInfo() {
    return [
        'name' => 'Bright Future Academy',
        'address' => '123 Education Street, Accra, Ghana',
        'phone' => '+233 24 123 4567',
        'email' => 'info@brightfutureacademy.edu.gh',
        'website' => 'www.brightfutureacademy.edu.gh',
        'logo' => 'img/school-logo.png'
    ];
}
?>