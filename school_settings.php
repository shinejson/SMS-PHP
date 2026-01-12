<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// === Fetch Current Settings ===
$sql = "SELECT * FROM school_settings ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);
$settings = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;

// === Fetch Academic Years ===
$academic_years = [];
$years_result = $conn->query("SELECT * FROM academic_years ORDER BY created_at DESC");
if ($years_result && $years_result->num_rows > 0) {
    while ($row = $years_result->fetch_assoc()) {
        $academic_years[] = $row;
    }
}

// === Handle School Settings Form Submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_name'])) {
    $school_name = $_POST['school_name'];
    $school_short_name = $_POST['school_short_name'] ?? '';
    $address     = $_POST['address'];
    $phone       = $_POST['phone'];
    $email       = $_POST['email'];
    $headmaster_name = $_POST['headmaster_name'] ?? '';
    $motto = $_POST['motto'] ?? '';
    $app_password = $_POST['app_password'] ?? '';

    // --- Handle Logo Upload ---
    $logoPath = $settings['logo'] ?? null;
    if (!empty($_FILES['logo']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileName = time() . "_" . basename($_FILES['logo']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            $logoPath = $targetFile;
        }
    }

    // --- Handle Headmaster Signature Upload ---
    $signaturePath = $settings['headmaster_signature'] ?? null;
    if (!empty($_FILES['headmaster_signature']['name'])) {
        $targetDir = "uploads/signatures/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileName = "signature_" . time() . "_" . basename($_FILES['headmaster_signature']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['headmaster_signature']['tmp_name'], $targetFile)) {
            $signaturePath = $targetFile;
        }
    }

  // --- Handle Favicon Upload ---
$faviconPath = $settings['favicon'] ?? null;
if (!empty($_FILES['favicon']['name'])) {
    $targetDir = "uploads/favicons/";
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            error_log("Failed to create directory: " . $targetDir);
            $_SESSION['error'] = "Failed to create upload directory.";
        }
    }
    
    $fileName = "favicon_" . time() . "_" . basename($_FILES['favicon']['name']);
    $targetFile = $targetDir . $fileName;
    
    // Check for upload errors
    if ($_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $_FILES['favicon']['error']);
        $_SESSION['error'] = "File upload failed with error code: " . $_FILES['favicon']['error'];
    } else {
        // Validate favicon file type
        $allowedTypes = ['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'];
        $fileType = mime_content_type($_FILES['favicon']['tmp_name']);
        
        // Check file extension
        $fileExtension = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['ico', 'png', 'svg', 'jpg', 'jpeg'];
        
        error_log("Favicon upload attempt:");
        error_log(" - Original name: " . $_FILES['favicon']['name']);
        error_log(" - MIME type: " . $fileType);
        error_log(" - File extension: " . $fileExtension);
        error_log(" - Target file: " . $targetFile);
        
        if (in_array($fileType, $allowedTypes) || in_array($fileExtension, $allowedExtensions)) {
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $targetFile)) {
                $faviconPath = $targetFile;
                error_log("Favicon successfully uploaded to: " . $faviconPath);
            } else {
                error_log("Move uploaded file failed");
                $_SESSION['error'] = "Failed to save favicon file. Please check directory permissions.";
            }
        } else {
            error_log("Invalid file type: " . $fileType . " or extension: " . $fileExtension);
            $_SESSION['error'] = "Invalid file type. Please use ICO, PNG, SVG, or JPEG format.";
        }
    }
}

    // --- Encrypt App Password only if provided ---
    $encryptedPassword = $settings['app_password'] ?? null;
    if (!empty($app_password)) {
        $encryptedPassword = encryptPassword($app_password);
    }

    if ($settings) {
        // Update existing row
        $stmt = $conn->prepare("UPDATE school_settings 
            SET school_name=?, school_short_name=?, address=?, phone=?, email=?, headmaster_name=?, motto=?, logo=?, headmaster_signature=?, favicon=?, app_password=? WHERE id=?");
        $stmt->bind_param("sssssssssssi", $school_name, $school_short_name, $address, $phone, $email, $headmaster_name, $motto, $logoPath, $signaturePath, $faviconPath, $encryptedPassword, $settings['id']);
        $stmt->execute();
        $_SESSION['message'] = "School settings updated successfully!";
    } else {
        // Insert new row
        $stmt = $conn->prepare("INSERT INTO school_settings (school_name, school_short_name, address, phone, email, headmaster_name, motto, logo, headmaster_signature, favicon, app_password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssss", $school_name, $school_short_name, $address, $phone, $email, $headmaster_name, $motto, $logoPath, $signaturePath, $faviconPath, $encryptedPassword);
        $stmt->execute();
        $_SESSION['message'] = "School settings saved successfully!";
    }

    // Clear all school-related caches
if (isset($_SESSION['school_favicon'])) {
    unset($_SESSION['school_favicon']);
}
if (isset($_SESSION['school_settings'])) {
    unset($_SESSION['school_settings']);
}
if (isset($_SESSION['school_sidebar_info'])) {
    unset($_SESSION['school_sidebar_info']);
}

    header("Location: school_settings.php");
    exit();
}

// === Handle Academic Year Form Submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['year_name'])) {
    $year_name = $_POST['year_name'];
    $is_current = isset($_POST['is_current']) ? 1 : 0;
    
    // If setting as current year, unset any previous current year
    if ($is_current) {
        $conn->query("UPDATE academic_years SET is_current = 0");
    }
    
    // Insert new academic year
    $stmt = $conn->prepare("INSERT INTO academic_years (year_name, is_current) VALUES (?, ?)");
    $stmt->bind_param("si", $year_name, $is_current);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Academic year added successfully!";
    } else {
        $_SESSION['error'] = "Error adding academic year: " . $conn->error;
    }
    
    header("Location: school_settings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Settings</title>
    <link rel="stylesheet" type="text/css" href="css/dashboard.css">
    <link rel="stylesheet" href="css/dropdown.css">
    <link rel="stylesheet" type="text/css" href="css/dark-mode.css">
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="css/school-setting.css">
</head>
<body>
   <?php include 'sidebar.php'; ?>
    <main class="main-content">
      <?php include 'topnav.php'; ?>
      <div class="content-wrapper">
    <div class="settings-container">
      
        <h2>School Settings</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
       <form method="POST" enctype="multipart/form-data">
    <label for="school_name">School Name:</label>
    <input type="text" name="school_name" id="school_name" required
        value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>">

    <label for="school_short_name">School Short Name/Abbreviation:</label>
    <input type="text" name="school_short_name" id="school_short_name"
        value="<?= htmlspecialchars($settings['school_short_name'] ?? '') ?>"
        placeholder="e.g., GEBS, GPS, etc.">
    <small style="color:#fff;">Short name or abbreviation for reports and certificates</small>

    <label for="address">Address:</label>
    <textarea name="address" id="address" required><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>

    <label for="phone">Phone:</label>
    <input type="text" name="phone" id="phone" required
        value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">

    <label for="email">School Email:</label>
    <input type="email" name="email" id="email" required
        value="<?= htmlspecialchars($settings['email'] ?? '') ?>">

    <!-- New Headmaster Name Field -->
    <label for="headmaster_name">Headmaster Name:</label>
    <input type="text" name="headmaster_name" id="headmaster_name"
        value="<?= htmlspecialchars($settings['headmaster_name'] ?? '') ?>"
        placeholder="Enter headmaster's full name">

    <label for="app_password">Gmail App Password:</label>
    <input type="password" name="app_password" id="app_password" placeholder="Enter new password if changing">
    <small style="color:#777;">Leave blank to keep existing password.</small>

    <label for="logo">School Logo:</label>
    <input type="file" name="logo" id="logo" accept="image/*">
    <?php if (!empty($settings['logo'])): ?>
        <div>
            <img src="<?= $settings['logo'] ?>" alt="School Logo" style="max-width: 200px; margin-top: 10px;">
        </div>
    <?php endif; ?>

    <!-- New Headmaster Signature Field -->
    <label for="headmaster_signature">Headmaster Signature (PNG with transparent background):</label>
    <input type="file" name="headmaster_signature" id="headmaster_signature" accept="image/png,image/jpeg,image/gif">
    <small style="color:#777;">
        Recommended: PNG image with transparent background for best results on reports.
        Max size: 2MB. Optimal dimensions: 200x80px.
    </small>
    <?php if (!empty($settings['headmaster_signature'])): ?>
        <div style="margin-top: 10px;">
            <p>Current Signature:</p>
            <img src="<?= $settings['headmaster_signature'] ?>" alt="Headmaster Signature" 
                 style="max-width: 200px; max-height: 100px; background-color: #f8f9fa; padding: 10px; border: 1px solid #ddd;">
            <br>
            <small>Preview with light background to show transparency</small>
        </div>
    <?php endif; ?>

    <!-- New School Motto Field -->
    <label for="motto">School Motto:</label>
    <textarea name="motto" id="motto" placeholder="Enter school motto"><?= htmlspecialchars($settings['motto'] ?? '') ?></textarea>
    <small style="color:#777;">School motto that will appear on reports and certificates</small>

    <!-- Favicon Upload Field -->
<label for="favicon">Browser Icon (Favicon):</label>
<input type="file" name="favicon" id="favicon" accept="image/x-icon,image/png,image/svg+xml">
<small style="color:#777;">
    Recommended: PNG or ICO format, 32x32 or 64x64 pixels. 
    This will appear in browser tabs and bookmarks.
</small>
<?php if (!empty($settings['favicon'])): ?>
    <div style="margin-top: 10px;">
        <p>Current Favicon:</p>
        <img src="<?= $settings['favicon'] ?>" alt="Favicon Preview" 
             style="width: 32px; height: 32px; background-color: #f8f9fa; padding: 5px; border: 1px solid #ddd;">
        <br>
        <small>Preview (32x32 pixels)</small>
    </div>
<?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn-primary">Save Settings</button>
        <button type="button" class="btn-primary" onclick="openAcademicYearModal()">Enter Academic Year</button>
        <button type="button" class="btn-primary" onclick="openRemarksModal()">Manage Grading System</button>
    </div>
</form>
        
        <!-- The rest of your modal and table code remains the same -->
        <!-- Remarks/Grading System Modal -->
        <div id="remarksModal" class="modal">
            <div class="modal-content" style="max-width: 700px;">
                <span class="close" onclick="closeRemarksModal()">&times;</span>
                <h2>Manage Grading System</h2>
                
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Configure the grading system with minimum and maximum marks, grades, and remarks.
                </div>
                
                <input type="hidden" id="remarks_csrf_token" value="<?php 
                    $csrf_token = $_SESSION['csrf_token'] ?? '';
                    if (empty($csrf_token)) {
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $csrf_token = $_SESSION['csrf_token'];
                    }
                    echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); 
                ?>">
                
                <div id="remarksContainer">
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading grading system...
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeRemarksModal()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="addNewRemarkRow()">
                        <i class="fas fa-plus"></i> Add New Grade
                    </button>
                    <button type="button" class="btn-primary" onclick="saveRemarks()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Academic Years Table -->
        <div class="academic-years-table">
            <h3>Academic Years</h3>
            <?php if (!empty($academic_years)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Year Name</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($academic_years as $year): ?>
                            <tr>
                                <td><?= htmlspecialchars($year['year_name']) ?></td>
                                <td>
                                    <?php if ($year['is_current']): ?>
                                        <span class="current-year">Current Year</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M j, Y', strtotime($year['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No academic years found.</p>
            <?php endif; ?>
        </div>
    </div>
    </div>
</main>

<!-- Academic Year Modal -->
<div id="academicYearModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAcademicYearModal()">&times;</span>
        <h2>Add Academic Year</h2>
        <form method="POST" id="academicYearForm">
            <input type="hidden" name="year_name" value="">
            
            <div class="form-group">
                <label for="year_name_input">Year Name:</label>
                <input type="text" id="year_name_input" required 
                       placeholder="e.g., 2023-2024" 
                       oninput="updateYearName(this.value)">
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_current" name="is_current" value="1">
                    <label for="is_current">Set as current academic year</label>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAcademicYearModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Academic Year</button>
            </div>
        </form>
    </div>
</div>

<!-- The rest of your JavaScript code remains the same -->
<script src="js/school_settings.js"></script>

    <script src="js/darkmode.js"></script>
    <script src="js/dropdown.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>