<?php
// assignment_submissions.php - View and grade submissions for an assignment
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

// Helper functions
function getTeacherIdFromUserId($conn, $user_id) {
    if (empty($user_id)) return null;
    $sql = "SELECT id FROM teachers WHERE user_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
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

// Verify access
if (!$is_admin && !verifyAssignmentOwnership($conn, $assignment_id, $teacher_id)) {
    $_SESSION['error'] = "You don't have permission to view submissions for this assignment.";
    header('Location: assignments.php');
    exit();
}

// Fetch assignment details
$sql = "SELECT a.title, a.max_marks, c.class_name FROM assignments a LEFT JOIN classes c ON a.class_id = c.id WHERE a.id = ?";
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

// Handle grading (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $grade = floatval($_POST['grade']);
    $comments = trim($_POST['comments'] ?? '');

    $sql = "UPDATE assignment_submissions SET grade = ?, comments = ?, status = 'graded' WHERE id = ? AND assignment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("disi", $grade, $comments, $submission_id, $assignment_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Grade submitted successfully!';
    } else {
        $_SESSION['error'] = 'Error submitting grade.';
    }
    $stmt->close();
    header('Location: assignment_submissions.php?id=' . $assignment_id);
    exit();
}

// Fetch submissions (updated to use student_id, no users JOIN)
$sql = "SELECT s.*, st.first_name, st.last_name, st.student_id,
               CONCAT(t.first_name, ' ', t.last_name) as grader_name  -- From teachers for graded_by
        FROM assignment_submissions s 
        JOIN students st ON s.student_id = st.id 
        LEFT JOIN teachers t ON s.teacher_id = t.id  -- For grader name
        WHERE s.assignment_id = ? 
        ORDER BY s.submitted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt->close();

// Flash messages
function showFlashMessage() {
    if (!empty($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($_SESSION['error']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['error']);
    }
    if (!empty($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($_SESSION['success']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
    <title>Submissions - <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/assignment_details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <h1><i class="fas fa-list"></i> Submissions for: <?php echo htmlspecialchars($assignment['title']); ?></h1>
            </div>
            <nav class="header-nav">
                <a href="assignment_details.php?id=<?php echo $assignment_id; ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View Details</a>
                <a href="assignments.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Assignments</a>
            </nav>
        </header>

        <main class="dashboard-main">
            <?php showFlashMessage(); ?>

            <div class="card">
                <div class="card-header">
                    <h2>Submissions (<?php echo count($submissions); ?> total)</h2>
                    <div class="meta">
                        <span>Class: <?php echo htmlspecialchars($assignment['class_name']); ?></span>
                        <span>Max Marks: <?php echo $assignment['max_marks']; ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($submissions)): ?>
                        <p class="text-center">No submissions yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Submitted At</th>
                                        <th>Status</th>
                                        <th>Attachment</th>
                                        <th>Grade</th>
                                        <th>Comments</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $sub): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></td>
                                            <td><?php echo date('M j, Y H:i', strtotime($sub['submitted_at'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $sub['status']; ?>">
                                                    <?php echo ucfirst($sub['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($sub['attachment_path']): ?>
                                                    <a href="<?php echo htmlspecialchars($sub['attachment_path']); ?>" target="_blank" class="btn btn-small btn-secondary">
                                                        <i class="fas fa-download"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    No file
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $sub['grade'] ? $sub['grade'] . '/' . $assignment['max_marks'] : 'Not graded'; ?></td>
                                            <td><?php echo htmlspecialchars($sub['comments'] ?? ''); ?></td>
                                            <td>
                                                <?php if ($sub['status'] === 'submitted'): ?>
                                                    <button class="btn btn-small btn-primary" onclick="openGradeModal(<?php echo $sub['id']; ?>, <?php echo $assignment['max_marks']; ?>)">
                                                        <i class="fas fa-edit"></i> Grade
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grade Modal -->
            <div id="gradeModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Grade Submission</h3>
                        <span class="close" onclick="closeGradeModal()">&times;</span>
                    </div>
                    <form method="POST" id="gradeForm">
                        <input type="hidden" name="submission_id" id="submission_id">
                        <input type="hidden" name="grade_submission" value="1">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="grade">Grade <span id="maxMarksDisplay"></span></label>
                                <input type="number" id="grade" name="grade" min="0" step="0.5" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="comments">Comments</label>
                                <textarea id="comments" name="comments" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeGradeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Submit Grade</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentSubmissionId = 0;
        let maxMarks = 0;

        function openGradeModal(subId, maxM) {
            currentSubmissionId = subId;
            maxMarks = maxM;
            document.getElementById('submission_id').value = subId;
            document.getElementById('maxMarksDisplay').textContent = ` (Max: ${maxM})`;
            document.getElementById('grade').max = maxM;
            document.getElementById('gradeModal').style.display = 'block';
        }

        function closeGradeModal() {
            document.getElementById('gradeModal').style.display = 'none';
            document.getElementById('gradeForm').reset();
        }

        // Form submit with assignment_id
        document.getElementById('gradeForm').addEventListener('submit', function(e) {
            const formData = new FormData(this);
            formData.append('assignment_id', <?php echo $assignment_id; ?>);
            fetch('assignment_submissions.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                location.reload();
            });
            e.preventDefault();
        });

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('gradeModal');
            if (event.target === modal) closeGradeModal();
        }
    </script>
</body>
</html>