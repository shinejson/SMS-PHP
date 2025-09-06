<?php
require_once 'config.php';
require_once 'session.php';

// Fetch all billing records with prepared statements
$bills = [];
$sql = "SELECT b.*, t.term_name, c.class_name
        FROM billing b
        LEFT JOIN terms t ON b.term_id = t.id
        LEFT JOIN classes c ON b.class_id = c.id
        ORDER BY b.due_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Decode fee breakdown if exists
        if (!empty($row['fee_breakdown'])) {
            $row['fee_breakdown'] = json_decode($row['fee_breakdown'], true);
        }
        $bills[] = $row;
    }
}

// Fetch dropdown data in a single query
$dropdownData = [];
$sql = "(SELECT 'term' AS type, id, term_name AS name FROM terms)
        UNION
        (SELECT 'class' AS type, id, class_name AS name FROM classes ORDER BY name)
        UNION
        (SELECT 'year' AS type, academic_year AS id, academic_year AS name FROM billing GROUP BY academic_year ORDER BY name DESC)";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dropdownData[$row['type']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Records - GEBSCO</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/db.css">
    <link rel="stylesheet" href="css/viewbills.css">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'topnav.php'; ?>
        
        <main>
            <div class="page-header">
                <h1>Billing Management</h1>
                     <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
                <nav class="breadcrumb">
                    <a href="index.php">Home</a> > <a href="#">Finance</a> > Billing Records
                </nav>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Billing Records</h3>
                    <div class="header-actions">
                        <button id="addBillBtn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Bill
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                    <div class="table-card">
                        <table id="billingTable" class="display responsive nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Payment Type</th>
                                    <th class="dt-right">Amount</th>
                                    <th>Term</th>
                                    <th>Academic Year</th>
                                    <th>Due Date</th>
                                    <th>Class</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><?= htmlspecialchars($bill['payment_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="dt-right"><?= number_format($bill['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($bill['term_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($bill['academic_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= date('M j, Y', strtotime($bill['due_date'])) ?></td>
                                    <td><?= htmlspecialchars($bill['class_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($bill['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <button class="btn-icon edit-payment" 
                                                data-id="<?= $bill['id'] ?>"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-payment" 
                                                data-id="<?= $bill['id'] ?>"
                                                title="Delete">
                                            <i class="fas fa-trash-alt"></i>
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
        </main>
    </div>

    <!-- Single Dynamic Modal for Add/Edit -->
    <div id="billingModal" class="modal" role="dialog" aria-labelledby="modalTitle" hidden>
        <div class="modal-content">
           <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Billing Record</h2>
            <form id="billingForm" method="POST">
                <input type="hidden" name="billing_id" id="billingId">

                <div id="billingMessage" class="form-message"></div>

        <div class="form-group">
            <label for="payment_type">Payment Type</label>
            <select id="payment_type" name="payment_type" required>
                <option value="">Select Payment Type</option>
                <option value="Tuition">Tuition</option>
                <option value="PTA">PTA</option>
                <option value="Extra Class">Extra Class</option>
                <option value="Other">Other</option>
            </select>
        </div>
              <div id="tuitionSection" class="hidden">
                <div id="tuitionFields" class="form-group">
                    <div class="tuition-header">
                        <label>Tuition Sub-Fees</label>
                        <button type="button" class="add-sub-fee btn-primary">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div id="tuitionSubFields">
                        <!-- Dynamic content -->
                    </div>
                    <div class="tuition-total">
                        <strong>Total:</strong> <span id="tuitionTotalAmount">0.00</span>
                        </div>
                    </div>
                </div>

                <div id="simpleFieldGroup" class="form-group hidden">
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" name="amount" step="0.01" placeholder="e.g. 500.00">
                </div>

                <div class="form-group">
                    <label for="term_id">Term</label>
                    <select id="term_id" name="term_id" required>
                        <option value="">Select Term</option>
                        <?php foreach ($dropdownData['term'] ?? [] as $term): ?>
                            <option value="<?= $term['id'] ?>">
                                <?= htmlspecialchars($term['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

               <div class="form-group">
    <label for="academic_year">Academic Year*</label>
    <select id="academic_year" name="academic_year" required>
        <?php
        // Fetch academic years from DB
        $sql = "SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Mark current academic year as selected
                $selected = ($row['is_current'] == 1) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($row['id']) . '" ' . $selected . '>' 
                    . htmlspecialchars($row['year_name']) . '</option>';
            }
        } else {
            echo '<option value="">No academic years available</option>';
        }
        ?>
    </select>
</div>


                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" required>
                </div>

                <div class="form-group">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($dropdownData['class'] ?? [] as $class): ?>
                            <option value="<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Optional description"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit submit-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" role="dialog" aria-labelledby="deleteModalTitle">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h2 class="text-danger">Confirm Deletion</h2>
            <p>Are you sure you want to delete this billing record? This action cannot be undone.</p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="id" id="deleteId">
                <input type="hidden" name="delete_billing" value="1">
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-submit btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="js/bills.js"></script>
<script src="js/darkmode.js"></script>
<script src="js/dashboard.js"></script>
    <script>
    // Pass minimal data to frontend
    window.dropdownData = <?= json_encode($dropdownData) ?>;
    </script>
</body>
</html>



