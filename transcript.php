<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
// Temporary debugging - remove after testing (FIXED VERSION)
if (isset($_GET['debug_tables']) && $_GET['debug_tables'] == '1' && isset($_GET['student_id'])) {
    // Allow GET for debugging
    require_once 'get_student_transcript.php';
    debugCustomTableStructure($_GET['student_id']);
    exit;
}


// For admin-only pages
requirePermission('admin');
// Fetch data from database
$students = [];
$classes = [];
$academic_years = [];
$school_settings = [];

// Fetch students with class information for filtering
$student_query = "
    SELECT 
        s.id, 
        s.first_name, 
        s.last_name, 
        s.student_id, 
        s.class_id,
        s.dob,
        s.parent_contact,
        s.email,
        s.address,
        c.class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.status = 'Active' 
    ORDER BY c.class_name, s.first_name, s.last_name
";

$student_result = $conn->query($student_query);
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}

// Fetch classes
$class_result = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch academic years
$year_result = $conn->query("SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC");
while ($row = $year_result->fetch_assoc()) {
    $academic_years[] = $row;
}

// Fetch school settings
$settings_result = $conn->query("SELECT * FROM school_settings");
while ($row = $settings_result->fetch_assoc()) {
    $school_settings[$row['school_name']] = $row['address'];
}

// Set default values with fallbacks
$school_name = $school_settings['school_name'] ?? 'GLOBAL EVANGELICAL BASIC SCHOOL-TETTEKOPE';
$school_address = $school_settings['school_address'] ?? 'P.O. BOX 182, KETA';
$school_phone = $school_settings['school_phone'] ?? '024409832';
$school_email = $school_settings['school_email'] ?? 'info@gebsco.edu.gh';
$school_logo = $school_settings['school_logo'] ?? '';

// Get current academic year
$current_year = null;
$current_year_result = $conn->query("SELECT id, year_name FROM academic_years WHERE is_current = 1 LIMIT 1");
if ($current_year_result && $current_year_result->num_rows > 0) {
    $current_year = $current_year_result->fetch_assoc();
} else {
    // Fallback to the most recent year if no current year is set
    $fallback_year_result = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC LIMIT 1");
    if ($fallback_year_result && $fallback_year_result->num_rows > 0) {
        $current_year = $fallback_year_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Transcript - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="css/transcript.css">
</head>

<body>
    <div class="container">
        <div class="input-form">
            <h3 class="form-title">Generate Student Transcript</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="class_id">Class Filter (optional)</label>
                    <select id="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="student_id">Student *</label>
                    <select id="student_id" required>
                        <option value="">Search and select student...</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" 
                                data-class-id="<?php echo $student['class_id']; ?>"
                                data-class-name="<?php echo htmlspecialchars($student['class_name'] ?? ''); ?>"
                                data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                data-dob="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>"
                                data-phone="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                                data-email="<?php echo htmlspecialchars($student['email'] ?? ''); ?>"
                                data-address="<?php echo htmlspecialchars($student['address'] ?? ''); ?>">
                            <?php 
                            echo htmlspecialchars(
                                $student['first_name'] . ' ' . $student['last_name'] . 
                                ' (' . $student['student_id'] . ')' . 
                                ' - ' . ($student['class_name'] ?? 'No Class')
                            ); 
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="graduation_year">Graduation Year</label>
                    <select id="graduation_year">
                        <option value="">Select Graduation Year</option>
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo $year['id']; ?>" 
                                <?php echo (isset($current_year) && $current_year['id'] == $year['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-generate" onclick="generateTranscript()">
                    <i class="fas fa-cog"></i> Generate Transcript
                </button>
            </div>
        </div>
        
        <div class="loading" id="loadingIndicator">
            <i class="fas fa-spinner fa-spin"></i> Generating transcript, please wait...
        </div>
        
        <div id="transcriptContent">
            <div class="transcript-header">
                <div class="school-info">
                    <?php if (!empty($school_logo)): ?>
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" class="school-logo">
                    <?php endif; ?>
                    <div class="school-details">
                        <h2><?php echo htmlspecialchars($school_name); ?></h2>
                        <p><?php echo htmlspecialchars($school_address); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($school_phone); ?> | Email: <?php echo htmlspecialchars($school_email); ?></p>
                    </div>
                </div>
                <div class="transcript-title">OFFICIAL HIGH SCHOOL TRANSCRIPT</div>
            </div>
            
            <div class="student-info-grid">
                <div class="info-section">
                    <h3>Student Information</h3>
                    <div class="info-row">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value" id="student-full-name">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value" id="student-address">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number:</span>
                        <span class="info-value" id="student-phone">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value" id="student-email">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of birth:</span>
                        <span class="info-value" id="student-dob">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Parent/Guardian:</span>
                        <span class="info-value" id="parent-guardian">_________________________</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3>School Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($school_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value"><?php echo htmlspecialchars($school_address); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($school_phone); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($school_email); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Graduation Date:</span>
                        <span class="info-value" id="graduation-date">_________________________</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span class="info-value" id="school-contact">Registrar</span>
                    </div>
                </div>
            </div>
            
            <div class="academic-record">
                <div class="record-title">ACADEMIC RECORD</div>
                <div id="grades-content">
                    <p style="text-align: center; padding: 40px; color: #666; font-style: italic;">
                        Please generate a transcript to view academic records
                    </p>
                </div>
            </div>
            
            <div class="signature-section">
 
    <?php
    // Fetch school settings including headmaster signature and name
    $settings_result = $conn->query("SELECT headmaster_name, headmaster_signature FROM school_settings ORDER BY id DESC LIMIT 1");
    $school_settings = $settings_result->fetch_assoc();
    
    if (!empty($school_settings['headmaster_signature'])) {
        echo '<img src="' . htmlspecialchars($school_settings['headmaster_signature']) . '" alt="Headmaster Signature" style="height: 60px; max-width: 200px;">';
    }
    
    if (!empty($school_settings['headmaster_name'])) {
        echo '<p style="margin-top: 5px; font-weight: bold;">' . htmlspecialchars($school_settings['headmaster_name']) . '</p>';
    }
    ?>
    <p><strong>Headmaster</strong></p>
    <p>Date: <?php echo date('F j, Y'); ?></p>
</div>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Transcript
            </button>
            <button class="btn btn-download" onclick="downloadPDF()">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/transcript.js"></script>
</body>
</html>