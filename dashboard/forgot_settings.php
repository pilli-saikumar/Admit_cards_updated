<?php
include("../includes/header.php");
include("../includes/project_process.php");
include("../db_connect.php");
include("../includes/function.php");
$project_id = $_GET['project_id'] ?? 0;
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM admit_card_records");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['candidate_forgot'])) {

    $project_id = intval($_POST['project_id']);
    $login_fields = $_POST['login_fields'];
    $errors = [];
    if ($project_id <= 0) {
        $errors[] = 'Invalid project ID.';
    }
    //    if(empty($login_fields['mobile_number']['column']) || empty($login_fields['mobile_number']['label'])){
    //     $errors[] = 'Mobile Number field is required.';
    //    }
    //    if(empty($login_fields['dob']['column']) || empty($login_fields['dob']['label'])){
    //     $errors[] = 'DOB field is required.';
    //    }

    if (empty($errors)) {

        // First, delete any existing forgot_settings for this project_id to avoid duplicates
        $deleteStmt = $conn->prepare("DELETE FROM forgot_settings WHERE project_id = ?");
        $deleteStmt->bind_param("i", $project_id);
        $deleteStmt->execute();
        $deleteStmt->close();

        foreach ($login_fields as $key => $value) {
            $column_name = $value['column'];
            $label_name = $value['label'];
            $created_by = $_SESSION['user_id'] ?? null;
            $updated_by = $_SESSION['user_id'] ?? null;
            $updated_at = date('Y-m-d H:i:s');

            $insertStmt = $conn->prepare("INSERT INTO forgot_settings (project_id, column_name, label_name, created_by, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issiis", $project_id, $column_name, $label_name, $created_by, $updated_by, $updated_at);
            $insertStmt->execute();
            $insertStmt->close();

            // After inserting into projects
            $action = "Created forgot settings for " . $label_name;
            logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
        }

        $_SESSION['success_message'] = "Forgot Settings created successfully!";

        // Redirect to prevent form resubmission on refresh
        header("Location: forgot_settings.php?project_id=" . $project_id);
        exit();
    }
}

// Handle delete request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_field_id'])) {
    $field_id = intval($_POST['delete_field_id']);
    $project_id = intval($_POST['project_id']);
    
    
    $deleteStmt = $conn->prepare("DELETE FROM forgot_view_details WHERE id = ? AND project_id = ?");
    $deleteStmt->bind_param("ii", $field_id, $project_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    $_SESSION['success_message'] = "Field deleted successfully!";
    header("Location: forgot_settings.php?project_id=" . $project_id);
    exit();
}

// Handle remove all forgot settings request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_forgot_settings'])) {
    $project_id = intval($_POST['project_id']);
    
    // Delete from forgot_settings table
    $deleteStmt = $conn->prepare("DELETE FROM forgot_settings WHERE project_id = ?");
    $deleteStmt->bind_param("i", $project_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Also delete from forgot_view_details table
    $deleteStmt = $conn->prepare("DELETE FROM forgot_view_details WHERE project_id = ?");
    $deleteStmt->bind_param("i", $project_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    $_SESSION['success_message'] = "All forgot settings removed successfully!";
    header("Location: forgot_settings.php?project_id=" . $project_id);
    exit();
}

?>
<div class="ml-64  ">
    <div class="container w-4/5 mx-auto">
        <?php if (isset($errors) && !empty($errors)): ?>
            <div id="file-error" class="ml-64 p-5" style="color:red">
                <?php foreach ($errors as $error): ?>
                    ❌ <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="file-error" class="ml-64 " style="color:green"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
       
        <?php endif; ?>
        <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
       
        <?php if (isset($_SESSION['flash_error'])) {
            echo "<div id='file-success' class='bg-danger-100 text-green-800 px-4 py-2 rounded mb-4'>{$_SESSION['flash_error']}</div>";
            unset($_SESSION['flash_error']); // Clear it after showing once
        } ?>
        <!-- <h4 class="text-2xl font-bold text-center">Forgot Settings</h4> -->
        <div class="grid grid-cols-6 md:grid-cols-2 gap-2">

            <div class="">
                <div class="card shadow p-3  text-sm       ">

                    <h2 class="text-center mb-2 font-bold">Candidate Forgot Settings </h2>
                    
                    <form action="" method="post" class="bg-white p-6  ">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <div class="mb-3">
                            <label for="mobile_number" class="form-label">Login With</label>
                            <select name="login_fields[mobileNumber][column]"
                                id="mobile_number"
                                class="form-select text-sm">



                                <option value="mobileNumber" selected>Mobile Number (Default)</option>

                                <?php foreach ($columns as $col): ?>
                                    <?php if ($col !== 'mobileNumber' && $col !== 'dob'): ?>
                                        <option value="<?= htmlspecialchars($col) ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom Label Input -->
                        <div class="mb-3">
                            <label for="mobileNumber" class="form-label text-sm">Label Name</label>
                            <input type="text"
                                class="form-control text-sm"
                                name="login_fields[mobileNumber][label]"
                                id="mobileNumber"
                                value="Mobile Number">
                        </div>

                        <!-- DOB Field -->
                        <div class="mb-3">
                            <label for="dob" class="form-label text-sm">DOB Field</label>
                            <select name="login_fields[dob][column]"
                                id="dob"
                                class="form-select text-sm">

                                <option value="dob" selected>Date of Birth (Default)</option>
                                <?php foreach ($columns as $col): ?>
                                    <?php if ($col !== 'mobileNumber' && $col !== 'dob'): ?>
                                        <option value="<?= htmlspecialchars($col) ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom Label Input for DOB -->
                        <div class="mb-3">
                            <label for="dob" class="form-label text-sm">Label Name</label>
                            <input type="text"
                                class="form-control text-sm"
                                name="login_fields[dob][label]"
                                id="dob"
                                value="Date of Birth">
                        </div>

                        <!-- Static Captcha Field -->
                        <div class="mb-3">
                            <label class="form-label text-sm">Captcha</label>
                            <input type="text" class="form-control text-sm" value="Captcha (Default)" disabled>
                        </div>
                        <div class="alert alert-warning text-sm">
                            <small><strong>Note:</strong> Candidate forgot settings are used to determine which fields are displayed when candidates forgot Registration Number page</small>
                        </div>

                        <!-- Submit -->
                        <div class="mt-2">
                            <button type="submit" name="candidate_forgot" class="btn btn-success  ">Save Settings</button>
                            <form action="" method="post">
                                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                <button type="submit" name="remove_forgot_settings" class="btn btn-link btn-sm  font-weight-bold text-danger" onclick="return confirm('Are you sure you want to remove this settings?')">Remove Settings</button>
                            </form>
                        </div>
                    </form>
                    
                </div>
            </div>
            <div class="">
                <div class="card shadow p-3 text-sm">
                    <h2 class="align-items-center mb-2 font-bold text-center">Forgot View Details</h2>
                    <?php $forgot_view_details = $conn->query("SELECT * FROM forgot_view_details WHERE project_id = $project_id ORDER BY id ASC");
                    $forgot_view_details = $forgot_view_details->fetch_all(MYSQLI_ASSOC);

                    // Default fields if no data exists
                    $default_fields = [
                        ['label' => 'Name', 'column' => 'first_name'],
                        ['label' => 'Registration Number', 'column' => 'registration_number'],
                        ['label' => 'Date of Birth', 'column' => 'dob'],
                        // ['label' => 'ROLL NO', 'column' => 'roll_number'],
                        // ['label' => 'Mobile Number', 'column' => 'mobileNumber']
                    ];

                    // Use saved data if exists, otherwise use defaults
                    $fields_to_show = !empty($forgot_view_details) ? $forgot_view_details : $default_fields;
                   
                   ?>
        
                    <form action="forgot_view_details.php" method="post" class="text-sm" id="viewDetailsForm">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                      
                        <div id="viewFieldsContainer">
                            <?php foreach ($fields_to_show as $index => $field): ?>
                                <div class="view-field-row mb-3" data-field-index="<?= $index ?>">
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="flex-grow-1 me-2">
                                            <label class="form-label text-sm">Field Label</label>
                                            <input type="text" name="view_fields[<?= $index ?>][label]" class="form-control text-sm"
                                                value="<?= htmlspecialchars($field['label_name'] ?? $field['label']) ?>" required>
                                        </div>
                                        <div class="flex-grow-1 me-2">
                                            <label class="form-label text-sm">Database Column</label>
                                            <select name="view_fields[<?= $index ?>][column]" class="form-select text-sm" required>
                                                <option value="">Select Column</option>
                                                <?php foreach ($columns as $col): ?>
                                                    <option value="<?= htmlspecialchars($col) ?>"
                                                        <?= ($col === ($field['column_name'] ?? $field['column'])) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mt-2">
                                         
                                            <input type="hidden" name="view_fields[<?= $index ?>][status]" value="0">

                                            <input type="checkbox" name="view_fields[<?= $index ?>][status]" value="1" <?= ($field['view_status'] ?? 1) == 1 ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- <div class="">
                            <button type="button" class="btn btn-primary btn-sm mb-1" onclick="addViewField()">+ Add Field</button>
                        </div> -->

                        <div class="alert alert-warning text-sm">
                            <small><strong>Note:</strong> These fields will be displayed when candidates forget their registration number.</small>
                        </div>

                        <div class="">
                            <button type="submit" name="save_forgot_settings" class="btn btn-success w-100">Save Display Settings</button>
                        </div>
                    </form>
                    
                </div>
                <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_login_content'])) { ?>
                    <?php 
                    $status = $_POST['status'] ?? 0;
                    $project_id = $_POST['project_id'];
                    $candidate_login_content = $_POST['page_content'];
                    $created_by = $_SESSION['user_role'] ?? 'admin';
                    $updated_by = $_SESSION['user_role'] ?? 'admin';
                    $updated_at = date('Y-m-d H:i:s');
                    $created_at = date('Y-m-d H:i:s');
                    $existingContent = [];
                    $stmt = $conn->prepare("SELECT * FROM candidate_login_content WHERE project_id = ?");
                    $stmt->bind_param("i", $project_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $existingContent[] = $row;
                    }
                    if(!empty($existingContent)){
                        $deleteStmt = $conn->prepare("DELETE FROM candidate_login_content WHERE project_id = ?");
                        $deleteStmt->bind_param("i", $project_id);
                        $deleteStmt->execute();
                        $deleteStmt->close();



                    }

                    $insertcontent = $conn->prepare("INSERT INTO candidate_login_content (project_id, page_content, status, created_by, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insertcontent->bind_param("issiiis", $project_id, $candidate_login_content, $status, $created_by, $updated_by, $updated_at, $created_at);
                    $insertcontent->execute();
                   
                    $_SESSION['success_message'] = "Login Content saved successfully!";
                    header("Location: forgot_settings.php?project_id=" . $project_id);
                    exit();
                    ?>
                <?php } ?>
                <?php $candidate_login_content = $conn->query("SELECT * FROM candidate_login_content WHERE project_id = $project_id ");
                 $candidate_login_content = $candidate_login_content->fetch_assoc();
                 $content = $candidate_login_content['page_content'] ?? '';
                

                   
                    ?>
                <div class="card shadow p-3 text-sm mt-3">
                    <h2 class="text-center mb-2 font-bold">Display Content for below Login Form</h2>
                    <div id="viewFieldsContainer">
                        <form action="" method="post">
                            <input type="checkbox" name="status" value="1" <?= ($candidate_login_content['status'] ?? 0) == 1 ? 'checked' : '' ?>> Show Content in Login Page
                            <input type="hidden" name="project_id" value="<?= $project_id ?>">
                            <textarea name="page_content" id="candidate_login_content" cols="30" rows="10" class="form-control" required><?= $content ?? '' ?></textarea>
                      
                        <div class="">
                            <button type="submit" name="save_login_content" class="btn btn-success w-100">Save Content</button>
                        </div>
                        </form>
                        <div id="candidate_login_content_preview" class="mt-3">
                            <?= $content ?? '' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        setTimeout(function() {
            var errBox = document.getElementById('file-error');
            if (errBox) {
                errBox.style.display = 'none';
            }
        }, 5000); // 5000ms = 5 seconds
    </script>