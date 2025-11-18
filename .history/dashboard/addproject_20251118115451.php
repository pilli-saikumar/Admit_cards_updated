<?php 
include("../includes/header.php");
include("../db_connect.php");
include("../includes/function.php");

$errors = [];
$successMessage = ''; 

if (isset($_POST['add_project'])) {
    $projectNameRaw = trim($_POST['project_name'] ?? ''); // Use null coalescing for safety
    $projectHeader = trim($_POST['project_header'] ?? '');
    $description = trim($_POST['description'] ?? ''); 
     $created_by = $_SESSION['user_id'] ?? 'admin'; 
   //   $created_by = $_SESSION['user_name'];
    $projectLiveDate = $_POST['project_live_date'] ?? null; 
    $projectEndDate = $_POST['project_end_date'] ?? null; 
    $sub_header = $_POST['sub_header'] ?? null; 
    $forgot_label_name = $_POST['forgot_label_name'] ?? null; 
    // --- Validation Checks ---
    if (empty($projectNameRaw)) {
        $errors[] = 'Project name is required.';
    }
    if (empty($description)) {
        $errors[] = 'Description is required.';
    }
     if (empty($projectHeader)) {
        $errors[] = 'Header is required.';
    }
    if(!empty($projectLiveDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $projectLiveDate)) {
        $errors[] = 'Invalid project live date format. Use YYYY-MM-DD.';
    }
    if(!empty($projectEndDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $projectEndDate)) {
        $errors[] = 'Invalid project end date format. Use YYYY-MM-DD.';
    }
    if (!empty($projectLiveDate) && !empty($projectEndDate) && $projectLiveDate > $projectEndDate) {
        $errors[] = 'Project live date cannot be later than project end date.';
    }
    if(empty($projectLiveDate) || empty($projectEndDate)) {

        $error[] = 'Project live date and project end date are required.';
        // If either date is empty, set them to NULL for DB insert
        
    }

    // Check for unique project name ONLY if project name is not empty
    if (empty($errors)) { // Only proceed with DB check if basic validations pass
        $checkStmt = $conn->prepare("SELECT id FROM projects WHERE name = ? AND is_delete = 0");
        $checkStmt->bind_param("s", $projectNameRaw);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $errors[] = 'Project name already exists. Please enter another name.';
        }
        $checkStmt->close(); // Close the statement regardless of the outcome
    }

    // Handle logo upload only if no errors yet
    $logoPath = '';
    if (empty($errors) && isset($_FILES['project_log']) && $_FILES['project_log']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['project_log']['name'], PATHINFO_EXTENSION);
        $logoFileName = 'logo_' . time() . '.' . $ext;

        // Generate slug for folder BEFORE creating the base path
        $projectNameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $projectNameRaw)); // Folder-safe
        $basePath = __DIR__ . "/../$projectNameSlug";
        $uploadDir = $basePath . "/logos/";

        // Ensure directories exist before moving file
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $errors[] = 'Failed to create logo upload directory.';
            }
        }

        if (empty($errors)) { // Only try to move if directories are fine
            $fullLogoPath = $uploadDir . $logoFileName;
            $relativeLogoPath = "$projectNameSlug/logos/" . $logoFileName;

            if (move_uploaded_file($_FILES['project_log']['tmp_name'], $fullLogoPath)) {
                $logoPath = $relativeLogoPath; // Save relative path in DB
            } else {
                $errors[] = 'Failed to upload logo.';
            }
        }
    } elseif (empty($errors) && isset($_FILES['project_log']) && $_FILES['project_log']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other file upload errors if a file was attempted to be uploaded
        $errors[] = 'An error occurred during logo upload: ' . $_FILES['project_log']['error'];
    }

    // --- Final Check: If no errors, proceed with project creation ---
    if (empty($errors)) {
        // Generate slug here, after all validations
        $projectNameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $projectNameRaw)); // Folder-safe

        // Create base folder (if it wasn't created by logo upload already)
        $basePath = __DIR__ . "/../$projectNameSlug";
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true)) {
                $errors[] = 'Failed to create project directory.';
            }
        }

        if (empty($errors)) { // Check errors again after directory creation
            // Copy dashboard and index files
            if (!copy(__DIR__ . '/../Candidate/dashboard.php', "$basePath/dashboard.php")) {
                $errors[] = 'Failed to copy dashboard file.';
            }
            if (!copy(__DIR__ . '/../Candidate/index.php', "$basePath/index.php")) {
                $errors[] = 'Failed to copy index file.';
            }
            if (!copy(__DIR__ . '/../Candidate/forgot_registration_number.php', "$basePath/forgot_registration_number.php")) {
                $errors[] = 'Failed to copy forgot_registration_number.php file.';
            }
            if (!copy(__DIR__ . '/../Candidate/ge.php', "$basePath/forgot_registration_number.php")) {
                $errors[] = 'Failed to copy forgot_registration_number.php file.';
            }
        }
        }

        if (empty($errors)) { // Proceed to DB insert only if all previous steps succeeded
            $stmt = $conn->prepare("INSERT INTO projects (name,created_by, description, header, logo_path,project_live_date,project_end_date,slug,sub_header,forgot_label_name) VALUES (?,?, ?, ?, ?,?,?,?,?,?)");
            $stmt->bind_param("ssssssssss", $projectNameRaw,$created_by, $description, $projectHeader, $logoPath, $projectLiveDate, $projectEndDate, $projectNameSlug,$sub_header,$forgot_label_name);

            if ($stmt->execute()) {
                $project_id = $stmt->insert_id;
                $_SESSION['success_message'] = " Project created successfully!";
                // After inserting into projects
                    $action = "Created project: " . $projectNameRaw;
                    logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);

                                    header("Location: upload_csv.php?project_id=" . $project_id);
                exit; // ALWAYS exit after a header redirect
            } else {
                $errors[] = "Error inserting project: " . $stmt->error;
            }
            $stmt->close(); // Close the insert statement
        }
    }
}
?>

<div class="ml-64  p-3">
    <a href="javascript:history.back()" class="back-button btn btn-sm">← Back</a>
     <?php
// Display errors if any
if (!empty($errors)) {
    echo "<div id='file-error' class='ml-64 p-2' style='color:red'>";
    foreach ($errors as $error) {
        echo "❌ " . htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
}

// Display success message (if redirected from upload_csv.php, this won't show)
if (isset($_SESSION['success_message'])) {
    echo "<div id='file-error' class='ml-64 p-5' style='color:green'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
    unset($_SESSION['success_message']);
}
?>
    <h2 class="text-2xl font-bold mb-1 text-center">Create New Project</h2>
    <form action="" method="post" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md  border-b text-sm w-1/2 mx-auto">
    <div class="mb-2">
        <label for="project_name" class="block text-gray-700 text-sm font-bold mb-2">Project Name</label>
        <input type="text" name="project_name" id="project_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" >           
         <label for="project_name" class="block mt-2 text-gray-700 text-sm font-bold mb-2">Main Header</label>
        <!-- <input type="text" name="project_header" id="project_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" >             -->
       <textarea name="project_header" id="project_header" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="type your header even in multiple lines"></textarea>
       
        <label for="sub_header" class="block mt-2 text-gray-700 text-sm font-bold mb-2"> Sub Header</label>
        <input type="text" name="sub_header" id="sub_header" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" >            
        <label for="description" class="block mt-2 text-gray-700 text-sm font-bold mb-2">Description</label>
        <textarea name="description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
        <label for="forgot_text" class="block mt-2 text-gray-700 text-sm font-bold mb-2">Forgot Label Text</label>
        <input type="text" name="forgot_text" id="forgot_label_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" >
         <label for="template_images" class="block mt-2 text-gray-700 text-sm font-bold mb-2">Upload Logo </label>
        <input type="file" name="project_log"  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*" >  
         <label for="project_live_date" class="block mt-2 text-gray-700 text-sm font-bold mb-2">URL Live Date </label>
        <input type="date" name="project_live_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required min="<?= date('Y-m-d') ?>">
        <label for="project_end_date" class="block mt-2 text-gray-700 text-sm font-bold mb-2">URL End Date </label>
        <input type="date" name="project_end_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
        

        <button type="submit" name="add_project" class="mt-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Create Project</button>
    </div>
</form>
   
</div>
<script>
  setTimeout(function() {
    var errBox = document.getElementById('file-error');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds
</script>