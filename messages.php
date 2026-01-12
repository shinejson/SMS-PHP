<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// Fetch data from database
$academic_years = [];
$terms = [];
$events = [];
$recipient_groups = [];
$message_templates = [];

// Fetch academic years
$year_result = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC");
while ($row = $year_result->fetch_assoc()) {
    $academic_years[] = $row;
}

// Fetch terms
$term_result = $conn->query("SELECT id, term_name FROM terms ORDER BY term_order");
while ($row = $term_result->fetch_assoc()) {
    $terms[] = $row;
}

// Fetch upcoming events (next 30 days)
$upcoming_events_sql = "SELECT id, event_title, event_date, event_type 
                        FROM events 
                        WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        ORDER BY event_date, start_time";
$event_result = $conn->query($upcoming_events_sql);
while ($row = $event_result->fetch_assoc()) {
    $events[] = $row;
}

// Recipient groups
$recipient_groups = [
    'all_teachers' => 'All Teachers',
    'all_parents' => 'All Parents',
    'specific_class' => 'Specific Class',
    'specific_teacher' => 'Specific Teacher',
    'specific_parent' => 'Specific Parent'
];

// Message templates
$message_templates = [
    'event_reminder' => 'Event Reminder',
    'meeting_invitation' => 'Meeting Invitation',
    'exam_schedule' => 'Exam Schedule',
    'fee_reminder' => 'Fee Reminder',
    'general_announcement' => 'General Announcement'
];

// Get current academic year and term for default filter values
$current_academic_year = $conn->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();
$current_term = $conn->query("SELECT id FROM terms WHERE id = 1 LIMIT 1")->fetch_assoc();

// Fetch classes for specific class selection
$classes = [];
$class_result = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch teachers for specific teacher selection
$teachers = [];
$teacher_result = $conn->query("SELECT id, first_name, last_name FROM teachers WHERE status = 'Active' ORDER BY first_name, last_name");
while ($row = $teacher_result->fetch_assoc()) {
    $teachers[] = $row;
}

// Fetch students/parents for specific parent selection
$students = [];
$student_result = $conn->query("SELECT id, first_name, last_name, student_id FROM students WHERE status = 'Active' ORDER BY first_name, last_name");
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_type = $_POST['recipient_type'] ?? '';
    $message_type = $_POST['message_type'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message_content = $_POST['message_content'] ?? '';
    $event_id = $_POST['event_id'] ?? '';
    $specific_class = $_POST['specific_class'] ?? '';
    $specific_teacher = $_POST['specific_teacher'] ?? '';
    $specific_parent = $_POST['specific_parent'] ?? '';
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    $send_sms = isset($_POST['send_sms']) ? 1 : 0;
    
    // Validate required fields
    if (empty($recipient_type) || empty($message_type) || empty($subject) || empty($message_content)) {
        $error = "Please fill all required fields";
    } else {
        // Save message to database
        try {
            // Prepare the SQL statement
            $stmt = $conn->prepare("
                INSERT INTO messages (recipient_type, message_type, subject, content, event_id, 
                                     specific_class_id, specific_teacher_id, specific_student_id, 
                                     send_email, send_sms, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            
            // Set values for parameters
            $specific_class_id = !empty($specific_class) ? $specific_class : null;
            $specific_teacher_id = !empty($specific_teacher) ? $specific_teacher : null;
            $specific_student_id = !empty($specific_parent) ? $specific_parent : null;
            $event_id = !empty($event_id) ? $event_id : null;
            $created_by = $_SESSION['user_id'] ?? 1; // Assuming you have user authentication
            
            // CORRECTED: The number of parameters (11) must match the number of placeholders (11)
            // Format string: s=string, i=integer, b=blob, d=double
            $stmt->bind_param("ssssiiiiiii", 
                $recipient_type, 
                $message_type, 
                $subject, 
                $message_content, 
                $event_id,
                $specific_class_id, 
                $specific_teacher_id, 
                $specific_student_id,
                $send_email, 
                $send_sms, 
                $created_by
            );
            
            if ($stmt->execute()) {
                $message_id = $conn->insert_id;
                $success = "Message scheduled successfully!";
                
                // If immediate sending is requested, process the message
                if (isset($_POST['send_now'])) {
                    // This would typically be handled by a background process or cron job
                    // For now, we'll just update the status
                    $conn->query("UPDATE messages SET status = 'Processing' WHERE id = $message_id");
                    $success .= " Message is being processed.";
                }
            } else {
                $error = "Error saving message: " . $stmt->error;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message System - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <link rel="stylesheet" type="text/css" href="css/message.css">
    <link rel="stylesheet" type="text/css" href="css/db.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'topnav.php'; ?>
        
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-envelope"></i> Message System</h1>
                <nav class="breadcrumb">
                    <a href="index.php">Home</a> > <a href="#">Communication</a> > Message System
                </nav>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-paper-plane"></i> Compose Message</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="messageForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="recipient_type">Recipient Type *</label>
                                <select id="recipient_type" name="recipient_type" required onchange="toggleSpecificFields()">
                                    <option value="">Select Recipient Type</option>
                                    <?php foreach ($recipient_groups as $key => $value): ?>
                                        <option value="<?= $key ?>"><?= htmlspecialchars($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message_type">Message Type *</label>
                                <select id="message_type" name="message_type" required onchange="toggleEventField()">
                                    <option value="">Select Message Type</option>
                                    <?php foreach ($message_templates as $key => $value): ?>
                                        <option value="<?= $key ?>"><?= htmlspecialchars($value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Specific recipient fields (conditionally shown) -->
                        <div id="specific_class_field" class="form-group conditional-field">
                            <label for="specific_class">Select Class</label>
                            <select id="specific_class" name="specific_class">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="specific_teacher_field" class="form-group conditional-field">
                            <label for="specific_teacher">Select Teacher</label>
                            <select id="specific_teacher" name="specific_teacher">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="specific_parent_field" class="form-group conditional-field">
                            <label for="specific_parent">Select Parent/Student</label>
                            <select id="specific_parent" name="specific_parent">
                                <option value="">Select Parent/Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_id'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="event_field" class="form-group conditional-field">
                            <label for="event_id">Select Event (Optional)</label>
                            <select id="event_id" name="event_id" onchange="loadEventDetails()">
                                <option value="">Select Event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= $event['id'] ?>" data-date="<?= $event['event_date'] ?>" data-type="<?= $event['event_type'] ?>">
                                        <?= htmlspecialchars($event['event_title'] . ' (' . date('M j, Y', strtotime($event['event_date'])) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="event_preview" class="event-preview" style="display: none;">
                                <h4>Event Details</h4>
                                <div class="event-detail">
                                    <i class="fas fa-calendar"></i>
                                    <span id="event_date_preview"></span>
                                </div>
                                <div class="event-detail">
                                    <i class="fas fa-tag"></i>
                                    <span id="event_type_preview"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" required placeholder="Enter message subject">
                        </div>
                        
                        <div class="form-group">
                            <label for="message_content">Message Content *</label>
                            <textarea id="message_content" name="message_content" required placeholder="Type your message here..." onkeyup="updateCharacterCount()"></textarea>
                            <div class="character-count">
                                <span id="char_count">0</span> characters
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Delivery Method</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                                    <label for="send_email">Email</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="send_sms" name="send_sms" value="1">
                                    <label for="send_sms">SMS</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="send_now" value="1" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send Now
                            </button>
                            <button type="submit" name="save_draft" value="1" class="btn btn-secondary">
                                <i class="fas fa-save"></i> Save as Draft
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('messageForm').reset();">
                                <i class="fas fa-times"></i> Clear Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Messages</h3>
                </div>
                <div class="card-body">
                    <p>Message history and status tracking will be displayed here.</p>
                    <!-- In a complete implementation, this would show a table of recent messages -->
                </div>
            </div>
        </div>
    </div>
    <script src="js/darkmode.js"></script>
    <script src="js/dashboard.js"></script>
    <script>
        function toggleSpecificFields() {
            const recipientType = document.getElementById('recipient_type').value;
            
            // Hide all specific fields first
            document.getElementById('specific_class_field').style.display = 'none';
            document.getElementById('specific_teacher_field').style.display = 'none';
            document.getElementById('specific_parent_field').style.display = 'none';
            
            // Show the appropriate field based on selection
            if (recipientType === 'specific_class') {
                document.getElementById('specific_class_field').style.display = 'block';
            } else if (recipientType === 'specific_teacher') {
                document.getElementById('specific_teacher_field').style.display = 'block';
            } else if (recipientType === 'specific_parent') {
                document.getElementById('specific_parent_field').style.display = 'block';
            }
        }
        
        function toggleEventField() {
            const messageType = document.getElementById('message_type').value;
            const eventField = document.getElementById('event_field');
            
            if (messageType === 'event_reminder' || messageType === 'meeting_invitation') {
                eventField.style.display = 'block';
            } else {
                eventField.style.display = 'none';
                document.getElementById('event_id').value = '';
                document.getElementById('event_preview').style.display = 'none';
            }
        }
        
        function loadEventDetails() {
            const eventSelect = document.getElementById('event_id');
            const selectedOption = eventSelect.options[eventSelect.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('event_date_preview').textContent = 
                    'Date: ' + new Date(selectedOption.getAttribute('data-date')).toLocaleDateString();
                document.getElementById('event_type_preview').textContent = 
                    'Type: ' + selectedOption.getAttribute('data-type');
                document.getElementById('event_preview').style.display = 'block';
                
                // Auto-fill subject if empty
                if (!document.getElementById('subject').value) {
                    document.getElementById('subject').value = 
                        'Reminder: ' + selectedOption.text.split(' (')[0];
                }
            } else {
                document.getElementById('event_preview').style.display = 'none';
            }
        }
        
        function updateCharacterCount() {
            const content = document.getElementById('message_content').value;
            document.getElementById('char_count').textContent = content.length;
            
            // Warn if message is getting long for SMS
            if (content.length > 140) {
                document.getElementById('char_count').style.color = '#e74c3c';
            } else {
                document.getElementById('char_count').style.color = '#6c757d';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleSpecificFields();
            toggleEventField();
            updateCharacterCount();
        });
    </script>
</body>
</html>