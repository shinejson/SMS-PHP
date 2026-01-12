<?php
// assignment_details.php - View details of a specific assignment
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';
require_once 'access_control.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access to teacher and admin only
if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
    header('Location: access_denied.php');
    exit();
}
checkAccess(['teacher', 'admin']);

$teacher_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');

// Get assignment ID
$assignment_id = intval($_GET['id'] ?? 0);
if ($assignment_id <= 0) {
    $_SESSION['error'] = 'Invalid assignment ID.';
    header('Location: assignments.php');
    exit();
}

// Helper functions (for verification)
function getTeacherIdFromUserId($conn, $user_id) {
    if (empty($user_id)) return null;
    $sql = "SELECT id FROM teachers WHERE user_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['id'];
        $stmt->close();
        return $teacher_id;
    }
    $stmt->close();
    return null;
}

function verifyAssignmentOwnership($conn, $assignment_id, $teacher_user_id) {
    $teacher_id = getTeacherIdFromUserId($conn, $teacher_user_id);
    if (!$teacher_id) return false;
    $sql = "SELECT id FROM assignments WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $assignment_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasAccess = $result->num_rows > 0;
    $stmt->close();
    return $hasAccess;
}

// Verify access to this assignment
if (!$is_admin && !verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)) {
    $_SESSION['error'] = "You don't have permission to view this assignment.";
    header('Location: assignments.php');
    exit();
}

// Fetch assignment details
$sql = "SELECT a.*, c.class_name, t.first_name as teacher_name 
        FROM assignments a 
        LEFT JOIN classes c ON a.class_id = c.id 
        LEFT JOIN teachers t ON a.teacher_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if (!$assignment) {
    $_SESSION['error'] = 'Assignment not found.';
    header('Location: assignments.php');
    exit();
}

// Fetch submission count
$sql = "SELECT COUNT(*) as submission_count FROM assignment_submissions WHERE assignment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$submission_count = $result->fetch_assoc()['submission_count'];
$stmt->close();

// Flash messages
function showFlashMessage() {
    if (!empty($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($_SESSION['error']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error']);
    }
    if (!empty($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Details - <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
      <link rel="stylesheet" href="css/assignment_detais.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <h1><i class="fas fa-book"></i> Assignment Details</h1>
            </div>
            <nav class="header-nav">
                <a href="assignments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Assignments</a>
            </nav>
        </header>

        <main class="dashboard-main">
            <?php showFlashMessage(); ?>

            <div class="card">
                <div class="card-header">
                    <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
                    <span class="status-badge status-<?php echo $assignment['status']; ?>">
                        <?php echo ucfirst($assignment['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="assignment-meta">
                        <div class="meta-item">
                            <label>Class:</label>
                            <span><?php echo htmlspecialchars($assignment['class_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Subject:</label>
                            <span><?php echo htmlspecialchars($assignment['subject']); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Teacher:</label>
                            <span><?php echo htmlspecialchars($assignment['teacher_name'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Type:</label>
                            <span><?php echo ucfirst($assignment['assignment_type']); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Max Marks:</label>
                            <span><?php echo $assignment['max_marks']; ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Assignment Date:</label>
                            <span><?php echo date('M j, Y', strtotime($assignment['assignment_date'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <label>Due Date:</label>
                            <span class="due-date <?php 
                                $due = $assignment['due_date'];
                                $today = date('Y-m-d');
                                if ($due < $today) echo 'overdue';
                                elseif ($due == $today) echo 'today';
                                else echo 'future';
                            ?>">
                                <?php echo date('M j, Y', strtotime($due)); ?>
                            </span>
                        </div>
                        <div class="meta-item full-width">
                            <label>Submissions:</label>
                            <span><?php echo $submission_count; ?> submissions received</span>
                            <?php if ($submission_count > 0): ?>
                                <a href="assignment_submissions.php?id=<?php echo $assignment_id; ?>" class="btn btn-primary btn-small">View Submissions</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($assignment['description'])): ?>
                        <div class="section">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($assignment['instructions'])): ?>
                        <div class="section">
                            <h3>Instructions</h3>
                            <p><?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($assignment['attachment_path']): ?>
                        <div class="section">
                            <h3>Attachment</h3>
                            <a href="<?php echo htmlspecialchars($assignment['attachment_path']); ?>" class="btn btn-secondary" target="_blank">
                                <i class="fas fa-download"></i> Download Attachment
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_admin || verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)): ?>
                        <div class="section actions">
                            <a href="javascript:void(0)" onclick="window.location.href='assignments.php'" class="btn btn-secondary">Back</a>
                            <a href="#" onclick="editAssignment(<?php echo $assignment_id; ?>)" class="btn btn-primary">Edit</a>
                            <?php if ($submission_count == 0): ?>
                                <button onclick="deleteAssignment(<?php echo $assignment_id; ?>)" class="btn btn-danger">Delete</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/assignments.js"></script>
    <script>
        // Expose functions for inline onclick
        if (typeof editAssignment === 'undefined') {
            function editAssignment(id) {
                window.location.href = 'assignments.php'; // Fallback to main page
                alert('Edit functionality requires returning to assignments list.');
            }
        }
        if (typeof deleteAssignment === 'undefined') {
            function deleteAssignment(id) {
                if (confirm('Delete this assignment?')) {
                    window.location.href = `assignments.php?delete_id=${id}`;
                }
            }
        }
    </script>
</body>
</html>