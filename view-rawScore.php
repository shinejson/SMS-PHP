<?php
// ---------- UPDATED RAW SCORES FETCH HELPERS (put near top of marks.php) ----------

// Optional filters
$filters = [];
$types   = '';
$params  = [];

if (!empty($_GET['class_id']))   { $filters[] = 's.class_id = ?'; $types .= 'i'; $params[] = (int)$_GET['class_id']; }
if (!empty($_GET['term_id']))    { $filters[] = 'm.term_id = ?'; $types .= 'i'; $params[] = (int)$_GET['term_id']; }
if (!empty($_GET['subject_id'])) { $filters[] = 'm.subject_id = ?'; $types .= 'i'; $params[] = (int)$_GET['subject_id']; }

$whereSql = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

/**
 * Run a marks query for a whitelisted table and return rows.
 * $table must be one of: midterm_marks, class_score_marks, exam_score_marks
 */
function fetchMarksForModal(mysqli $conn, string $table, string $whereSql, string $types, array $params): array {
    $allowed = ['midterm_marks', 'class_score_marks', 'exam_score_marks'];
    if (!in_array($table, $allowed, true)) return [];

    // First, let's check the table structure
    $checkSql = "SHOW COLUMNS FROM `$table`";
    $checkResult = $conn->query($checkSql);
    $columns = [];
    
    if ($checkResult) {
        while ($column = $checkResult->fetch_assoc()) {
            $columns[] = $column['Field'];
        }
        error_log("Table $table columns: " . implode(', ', $columns));
    }
    
    // Build query based on actual table structure
    $sql = "
        SELECT 
            m.id,
            m.student_id,
            m.subject_id,
            m.term_id,
            m.academic_year_id,
            COALESCE(sub.subject_name, 'Unknown') as subject_name,
            COALESCE(t.term_name, 'Unknown') as term,
            COALESCE(s.first_name, '') as first_name,
            COALESCE(s.last_name, '') as last_name,
            COALESCE(c.class_name, 'Unknown') as class_name,
            COALESCE(ay.year_name, 'N/A') AS year_name,
            COALESCE(m.total_marks, 0) AS total_marks
        FROM `$table` m
        LEFT JOIN students s ON s.id = m.student_id
        LEFT JOIN classes c ON c.id = s.class_id
        LEFT JOIN subjects sub ON sub.id = m.subject_id
        LEFT JOIN terms t ON t.id = m.term_id
        LEFT JOIN academic_years ay ON ay.id = m.academic_year_id
        $whereSql
        ORDER BY s.last_name, s.first_name, sub.subject_name
    ";

    error_log("Final SQL: " . $sql);
    
    $rows = [];
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('fetchMarksForModal prepare error: ' . $conn->error);
        return $rows;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log('fetchMarksForModal execute error: ' . $stmt->error);
        $stmt->close();
        return $rows;
    }

    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'               => (int)($r['id'] ?? 0),
                'student_id'       => (int)($r['student_id'] ?? 0),
                'subject_id'       => (int)($r['subject_id'] ?? 0),
                'term_id'          => (int)($r['term_id'] ?? 0),
                'academic_year_id' => (int)($r['academic_year_id'] ?? 0),
                'first_name'       => (string)($r['first_name'] ?? ''),
                'last_name'        => (string)($r['last_name'] ?? ''),
                'class_name'       => (string)($r['class_name'] ?? 'N/A'),
                'subject_name'     => (string)($r['subject_name'] ?? 'N/A'),
                'term'             => (string)($r['term'] ?? 'N/A'),
                'year_name'        => (string)($r['year_name'] ?? 'N/A'),
                'total_marks'      => is_numeric($r['total_marks']) ? (float)$r['total_marks'] : 0,
            ];
        }
        $res->free();
    }
    $stmt->close();

    return $rows;
}

// Fetch for the three tabs
$midtermMarks = fetchMarksForModal($conn, 'midterm_marks', $whereSql, $types, $params);
$classMarks   = fetchMarksForModal($conn, 'class_score_marks', $whereSql, $types, $params);
$examMarks    = fetchMarksForModal($conn, 'exam_score_marks', $whereSql, $types, $params);

// Ensure arrays are always arrays
$midtermMarks = is_array($midtermMarks) ? $midtermMarks : [];
$classMarks   = is_array($classMarks)   ? $classMarks   : [];
$examMarks    = is_array($examMarks)    ? $examMarks    : [];
// ---------- END RAW SCORES FETCH HELPERS ----------
?>


<!-- View Raw Scores Modal -->
<div id="viewScoresModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>View Raw Marks</h2>

        <!-- Tab navigation -->
        <ul class="tab-nav">
            <li class="active" data-tab="midterm-tab">Midterm</li>
            <li data-tab="class-tab">Class</li>
            <li data-tab="exam-tab">Exam</li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content">

         <!-- Midterm -->
<div id="midterm-tab" class="tab-pane active">
    <table class="display">
        <thead>
            <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Academic Year</th>
                <th>Term</th>
                <th>Total Marks</th>
                <th>Control</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($midtermMarks as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?= htmlspecialchars($row['subject_name']); ?></td>
                    <td><?= htmlspecialchars($row['class_name']); ?></td>
                    <td><?= htmlspecialchars($row['year_name'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['term']); ?></td>
                    <td><?= $row['total_marks']; ?></td>
                    <td>
                        <button class="btn-icon view-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="View Details"> 
                            <i class="fas fa-eye"></i>
                        </button>
                     <button class="btn-icon edit-mark"
                            data-id="<?= (int)$row['id']; ?>"
                            data-student-id="<?= (int)$row['student_id']; ?>"
                            data-subject-id="<?= (int)$row['subject_id']; ?>"
                            data-term-id="<?= (int)$row['term_id']; ?>"
                            data-year-id="<?= (int)($row['academic_year_id'] ?? 0); ?>"
                            title="Edit Mark">
                        <i class="fas fa-edit"></i>
                    </button>
                        <button class="btn-icon delete-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="Delete Mark">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Class Score -->
<div id="class-tab" class="tab-pane">
    <table class="display">
        <thead>
            <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Academic Year</th>
                <th>Term</th>
                <th>Total Marks</th>
                <th>Control</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($classMarks as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?= htmlspecialchars($row['subject_name']); ?></td>
                    <td><?= htmlspecialchars($row['class_name']); ?></td>
                    <td><?= htmlspecialchars($row['year_name'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['term']); ?></td>
                    <td><?= $row['total_marks']; ?></td>
                    <td>
                        <button class="btn-icon view-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon edit-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="Edit Mark">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="Delete Mark">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Exam Score -->
<div id="exam-tab" class="tab-pane">
    <table class="display">
        <thead>
            <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Academic Year</th>
                <th>Term</th>
                <th>Total Marks</th>
                <th>Control</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($examMarks as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?= htmlspecialchars($row['subject_name']); ?></td>
                    <td><?= htmlspecialchars($row['class_name']); ?></td>
                    <td><?= htmlspecialchars($row['year_name'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['term']); ?></td>
                    <td><?= $row['total_marks']; ?></td>
                    <td>
                        <button class="btn-icon view-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon edit-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="Edit Mark">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete-mark" 
                                data-id="<?= $row['id']; ?>"
                                data-student-id="<?= $row['student_id']; ?>"
                                data-subject-id="<?= $row['subject_id']; ?>"
                                data-term-id="<?= $row['term_id']; ?>"
                                data-year-id="<?= $row['academic_year_id'] ?? ''; ?>"
                                title="Delete Mark">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        </div>
    </div>
</div>

<div id="editMarkModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Student Mark</h2>
        <form id="editMarkForm" method="POST" action="marks_control.php">
            <input type="hidden" name="action" value="edit_mark"> <!-- Change this line -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="mark_id" id="edit_mark_id">
            <input type="hidden" name="mark_type" id="edit_mark_type">
            <!-- Rest of the form remains the same -->
            
            <div class="form-group">
                <label for="edit_student_name">Student</label>
                <input type="text" id="edit_student_name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label for="edit_class_name">Class</label>
                <input type="text" id="edit_class_name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label for="edit_subject_name">Subject</label>
                <input type="text" id="edit_subject_name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label for="edit_term_name">Term</label>
                <input type="text" id="edit_term_name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label for="edit_year_name">Academic Year</label>
                <input type="text" id="edit_year_name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <h4>Individual Marks</h4>
                <div id="individual-marks-container">
                    <!-- Individual marks will be added here dynamically -->
                </div>
                <button type="button" id="add-mark-btn" class="btn-secondary">
                    <i class="fas fa-plus"></i> Add Another Mark
                </button>
            </div>
            
            <div class="form-group">
                <label for="edit_total_marks">Total Marks</label>
                <input type="number" name="total_marks" id="edit_total_marks" min="0" max="100" step="0.01" readonly>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-submit">Update Mark</button>
            </div>
        </form>
    </div>
</div>