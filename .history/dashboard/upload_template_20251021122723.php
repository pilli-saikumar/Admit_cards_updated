<?php 
include("../includes/header.php");
include("../includes/project_process.php");
include("../db_connect.php");
include("../includes/function.php");
$project_id = $_GET['project_id'] ?? 0;
if (empty($project_id)) {
    header("Location: dashboard.php?error=" . urlencode("Project ID is required."));
    exit;
}
$stmt = $conn->prepare("SELECT column_based FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$stmt->bind_result($column_based);
$stmt->fetch();
$stmt->close();

// $get_template = $conn->query("SELECT group_concat(distinct columns_name) as   columns_name separator '|||' FROM project_templates WHERE project_id = $project_id");
// $get_template = $get_template->fetch_assoc();
// $get_template = explode('|||', $get_template['columns_name']);
$get_template = $conn->query("SELECT GROUP_CONCAT(DISTINCT columns_name ORDER BY columns_name ASC SEPARATOR '|||') AS columns_name FROM project_templates WHERE project_id = $project_id");

$get_template = $get_template->fetch_assoc();
$templateNames = explode('|||', $get_template['columns_name']);
if (!empty($templateNames[0])) { // Check if there are any templates
    $templateNames = implode(' | ', array_map('htmlspecialchars', $templateNames)); // side-by-side with separator
} else {
    $templateNames = 'No Template';
}


// Step 2: Validate column exists in admitcards table
// $column_check = $conn->query("SHOW COLUMNS FROM admit_card_records");
// $valid_columns = [];
// while ($col = $column_check->fetch_assoc()) {
//     $valid_columns[] = $col['Field'];
// }

// if (!in_array($column_based, $valid_columns)) {
//     die("Mapped column not found in admitcards.");
// }

// // Step 3: Get distinct values for that mapped column
// $sql = "SELECT DISTINCT `$column_based` FROM admit_card_records WHERE project_id = ?";
// $stmt2 = $conn->prepare($sql);
// $stmt2->bind_param("i", $project_id);
// $stmt2->execute();
// $result = $stmt2->get_result();



// $columns = [];

// if ($result) {
//     while ($row = $result->fetch_assoc()) {
//         $columns[] = htmlspecialchars($row[$column_based]);
//     }
// }
// If column_based is 'all', don't try to fetch column values
$columns = [];
if ($column_based !== 'all') {
    // Step 2: Validate column exists in admitcards table
    $column_check = $conn->query("SHOW COLUMNS FROM admit_card_records");
    $valid_columns = [];
    while ($col = $column_check->fetch_assoc()) {
        $valid_columns[] = $col['Field'];
    }

    if (!in_array($column_based, $valid_columns)) {
        die("Mapped column not found in admitcards.");
    }

    // Step 3: Get distinct values for that mapped column
    $sql = "SELECT DISTINCT `$column_based` FROM admit_card_records WHERE project_id = ?";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("i", $project_id);
    $stmt2->execute();
    $result = $stmt2->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = htmlspecialchars($row[$column_based]);
        }
    }
}
if (isset($_POST['add_project'])) {
  
    

  $project_id = $_GET['project_id'];
  $column_names = $_POST['column_name'];



//  / $columns = $_POST['column_name'] ?? [];
//$template_groups = $_FILES['template_images'] ?? [];
 

    $errors = [];

    // Validate project ID
    if (empty($project_id)) {
        $errors[] = "Project ID is required.";
        echo "<div id= 'file-error' class='ml-64  p-5'  style='color:red'>❌ Project ID is required.</div>";
        exit;
    }

    // Validate column names
    if (empty($column_names) || !is_array($column_names)) {
        $errors[] = "At least one column must be selected.";
        echo "<div id= 'file-error' class='ml-64  p-5'  style='color:red'>❌ At least one column must be selected.</div>";
        exit;
    }

    // Validate file uploads
    if (empty($_FILES['template_images']['name'][0])) {
        $errors[] = "At least one template image must be uploaded.";
        echo "<div id= 'file-error' class='ml-64  p-5'  style='color:red'>❌ At least one template image must be uploaded.</div>";
        exit;
    }

    // Create upload directory
$slug_res = $conn->query("SELECT name,slug FROM projects WHERE id = $project_id");
$slug_row = $slug_res->fetch_assoc();
$project_slug = $slug_row['slug'];

// Build folder path like /{slug}/templates/
$template_dir = dirname(__DIR__) . "/$project_slug/templates/";

// Create folder if it doesn't exist
if (!is_dir($template_dir)) {
    mkdir($template_dir, 0755, true);
}
//  $template_dir = 'uploads/templates/';

//     // Create upload folder if it doesn't exist
//     if (!file_exists($template_dir)) {
//         mkdir($template_dir, 0777, true);
//     }
    function reformatFilesArray($file_post) {
    $reformatted = [];

    foreach ($file_post['name'] as $index => $names) {
        foreach ($names as $i => $name) {
            $reformatted[$index]['name'][$i] = $name;
            $reformatted[$index]['type'][$i] = $file_post['type'][$index][$i];
            $reformatted[$index]['tmp_name'][$i] = $file_post['tmp_name'][$index][$i];
            $reformatted[$index]['error'][$i] = $file_post['error'][$index][$i];
            $reformatted[$index]['size'][$i] = $file_post['size'][$index][$i];
        }
    }

    return $reformatted;
}

    // 1. Insert the project
    // $query = "INSERT INTO projects (name, description) VALUES ('$project_name', '$description')";
    // if ($conn->query($query) === TRUE) {
    //     $project_id = $conn->insert_id;
    // } else {
    //     $errors[] = "Error inserting project: " . $conn->error;
    //     die("Error inserting project: " . $conn->error);
    // }

    // 2. Handle multiple template uploads
    // foreach ($_FILES['template_images']['tmp_name'] as $index => $tmpName) {
    //     $originalName = $_FILES['template_images']['name'][$index];
    //     $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)); // check file extension

    //     // ✅ Accept only jpg or jpeg
    //     if (!in_array($fileType, ['jpg', 'jpeg'])) {
    //          $errors[] = 'Invalid file type: ' . $originalName . ' (only JPG or JPEG allowed)';
    //         echo "<div id= 'file-error' class='ml-64  p-5'  style='color:red'>❌ Invalid file type: $originalName (only JPG or JPEG allowed)</div>";
    //         continue; // Skip this file
    //     }

    //     $filename = uniqid() . '_' . basename($originalName);
    //     $destination = $template_dir . $filename;

    //     if (move_uploaded_file($tmpName, $destination)) {
    //         $template_name = $conn->real_escape_string($originalName);
    //         $template_path = $conn->real_escape_string($destination);
    //         $page_order = $index + 1;

    //         $tpl_query = "INSERT INTO project_templates (project_id, template_name, template_image_path, page_order)
    //                       VALUES ('$project_id', '$template_name', '$template_path', '$page_order')";
    //         if (!$conn->query($tpl_query)) {
    //             $errors[] = "Error inserting template: " . $conn->error;
    //             echo "Error inserting template: " . $conn->error;
    //             exit;
    //         }
    //     } else {
    //         $errors[] = "Error uploading file: $originalName<br>";
    //         echo "Error uploading file: $originalName<br>";
    //         exit;
    //     }
    // }
   $template_groups = reformatFilesArray($_FILES['template_images']);

   
foreach ($_POST['column_name'] as $index => $column_name) {
    $column = $conn->real_escape_string($column_name);
    $files = $template_groups[$index] ?? [];

    if (empty($files['tmp_name'])) continue;

    foreach ($files['tmp_name'] as $i => $tmpName) {
        $originalName = $files['name'][$i];
        $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($fileType, ['jpg', 'jpeg'])) {
            $errors[] = "Invalid file type for column $column: $originalName";
            echo "<div id ='file-error' class='ml-64  p-5' style='color:red'>❌ Invalid file type for column $column: $originalName</div>";
            continue;
        }

        // $filename = uniqid() . '_' . basename($originalName);
        // $destination = $template_dir . $filename;
        $slugified_column = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $column));
$cleaned_original = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $originalName);
$page_order = $i + 1;
$filename = "{$slugified_column}_{$page_order}_" . uniqid() . "_" . $cleaned_original;

$destination = $template_dir . $filename;
$relative_path = "$project_slug/templates/$filename";

        if (move_uploaded_file($tmpName, $destination)) {
             list($img_width, $img_height) = getimagesize($destination); 
            $template_name = $conn->real_escape_string($originalName);
            $template_path = $conn->real_escape_string($relative_path);
            $page_order = $i + 1;

            // $query = "INSERT INTO project_templates 
            //           (project_id, columns_name, template_name, template_image_path, page_order)
            //           VALUES ('$project_id', '$column', '$template_name', '$template_path', '$page_order')";
            $query = "INSERT INTO project_templates 
              (project_id, columns_name, template_name, template_image_path, page_order, template_width, template_height)
              VALUES ('$project_id', '$column', '$template_name', '$template_path', '$page_order', '$img_width', '$img_height')";
             $result = $conn->query($query);
                // if ($conn->query($query)) {
                //     $template_id = $conn->insert_id; // ✅ get the newly inserted ID
                //     echo "<div class='ml-64 p-5 text-green-600'>✅ Template uploaded. ID: $template_id</div>";

                //     // Optional: log action
                //     logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, "Uploaded new template: $column");
                //  }       
                //     if (!$conn->query($query)) {
                //         echo "<div  id ='file-error' class='ml-64  p-5' style='color:red'>❌ DB Error for $originalName: {$conn->error}</div>";
                //     }
                // } else {
                //     echo "<div  id ='file-error' class='ml-64  p-5' style='color:red'>❌ Failed to upload $originalName for column $column</div>";
                // }
                if ($result) {
                        $template_id = $conn->insert_id; // ✅ get the newly inserted ID
                        echo "<div class='ml-64 p-5 text-green-600'>✅ Template uploaded. ID: $template_id</div>";

                        // Optional: log action
                        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, "Uploaded new template: $column");
                    } else {
                        echo "<div id='file-error' class='ml-64 p-5' style='color:red'>❌ DB Error for $originalName: {$conn->error}</div>";
                    }if ($result) {
                        $template_id = $conn->insert_id; // ✅ get the newly inserted ID
                        echo "<div class='ml-64 p-5 text-green-600'>✅ Template uploaded. ID: $template_id</div>";

                        // Optional: log action
                        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, "Uploaded new template: $column");
                    } else {
                        echo "<div id='file-error' class='ml-64 p-5' style='color:red'>❌ DB Error for $originalName: {$conn->error}</div>";
                    }
                }
    }
}

    if (empty($errors)) {
    $_SESSION['success_message'] = "Project  Template Uploaded  Successfully!";

    // header("Location: dashboard.php");
    header("Location: generate_admit_card.php?project_id=$project_id");
    exit;
}
}

?>
<style>
    .form-wrapper {
        max-width: 800px;
        margin: 10px auto;
        padding: 25px;
        border: 2px solid #ccc;
        border-radius: 10px;
        background-color: #f9f9f9;
    }
    .templateSettings {
        border: 1px dashed #ccc;
        padding: 15px;
        border-radius: 8px;
        background: #fff;
        margin-bottom: 20px;
    }
</style>
<div class="ml-64 ">
      <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
    <!-- <h2 class="text-2xl font-bold mb-4">Upload Tempalte  </h2>
    <form action="" method="post" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md">
    <div class="mb-4">
        

        <label for="template_images" class="block mt-4 text-gray-700 text-sm font-bold mb-2">Upload Templates </label>
        <input type="file" name="template_images[]" multiple class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*" required>

        <button type="submit" name="add_project" class="mt-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Create Project</button>
    </div> 
</form> -->
<?php   if (empty($templateNames)): ?>
        <div class="text-center text-sm">
            <span class="font-italic"> Template Not Uploaded </span>
        </div>
    <?php else: ?>
            <div class="text-center text-sm">
                <span class="font-italic"> Template Uploaded For Column : <?php echo strtoupper($templateNames); ?></span>
            </div>
            <?php endif; ?>
   <div class="form-wrapper">
    
    <h2 class="text-center mb-1">Admit Card Template Settings</h2>

    <form method="POST" action="" enctype="multipart/form-data" class="p-3    text-sm w-1/1 mx-auto">
        <div id="templateSettingsContainer">
            <div class="templateSettings row align-items-end">
                <div class=" row">
                 <?php if ($column_based !== 'all'): ?>
                <div class="col-12 mb-6">
                   
                     
                    <label class="form-label">Select Column value for dropdown:</label>
                    <select name="column_name[]" class="form-select columnDropdown" required>
                        <option value="" class="text-gray-500">Select Column</option>
                      
                        <?php foreach ($columns as $column): ?>
                            <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars($column) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else :?>
                    <div class="col-12 mb-6  ">
                   
                     
                    <label class="form-label">Template Selected For All coulmns :</label>
                    <select name="column_name[]" class="form-select columnDropdown" required>
                        <option value="all" selected>All</option>
                            
                    </select>
                </div>
                <?php endif; ?>
       <?php $index = 0; ?>
                <div class="col-12 mb-6">
                    <label class="form-label">Upload Template:</label>
                    <input type="file" name="template_images[<?= $index ?>][]" multiple class="form-control" >
                </div>
                <!-- <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger removeButton d-none">❌ Remove</button>
                </div> -->
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
              <?php if ($column_based !== 'all'): ?>
            <button type="button" id="addMoreButton" class="btn btn-secondary">➕ Add More</button>
            <?php  endif ?>
            <button type="submit" name="add_project" class="btn btn-success">💾 Save Settings</button>
        </div>
    </form>
</div>
    
</div>

<script>
$(document).ready(function () {
    function checkAllSelected() {
        let allSelected = false;
        $('.columnDropdown').each(function () {
            if ($(this).val() === 'all') {
                allSelected = true;
            }
        });
        $('#addMoreButton').prop('disabled', allSelected);
    }
let rowIndex = 1;

    $('#addMoreButton').on('click', function () {

    const newRow = `
        <div class="templateSettings row mb-3 align-items-end">
            <div class="col-12 mb-3">
                <label class="form-label">Select Column value for dropdown:</label>
                <select name="column_name[]" class="form-select columnDropdown" required>
                    <option value="">Select Column</option>
                    <?php foreach ($columns as $column): ?>
                        <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars($column) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Upload Template:</label>
                <input type="file" name="template_images[${rowIndex}][]" multiple class="form-control" required>
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-danger removeButton">❌ Remove</button>
            </div>
        </div>
    `;
    $('#templateSettingsContainer').append(newRow);
    rowIndex++; // ✅ Increment for next row
});

    // Remove row
    $(document).on('click', '.removeButton', function () {
        $(this).closest('.templateSettings').remove();
        checkAllSelected();
    });

    // Handle column change
    $(document).on('change', '.columnDropdown', function () {
        checkAllSelected();
    });

    // Show remove button only if >1 row
    $(document).on('DOMNodeInserted DOMNodeRemoved', '#templateSettingsContainer', function () {
        const rows = $('.templateSettings');
        $('.removeButton').toggleClass('d-none', rows.length <= 1);
    });

    // Hide error box after 5 seconds
    setTimeout(function () {
        var errBox = document.getElementById('file-error');
        if (errBox) {
            errBox.style.display = 'none';
        }
    }, 5000);
}); 
</script>
</body>
</html>