<?php 
 include("../includes/header.php");
include("../db_connect.php");


$projectId = intval($_GET['project_id']);

// Fetch project data
$projectStmt = $conn->prepare("SELECT name, description,header, column_based,logo_path,project_live_date,project_end_date,sub_header,forgot_label_name FROM projects WHERE id = ?");
$projectStmt->bind_param("i", $projectId);
$projectStmt->execute();
$projectResult = $projectStmt->get_result();
$project = $projectResult->fetch_assoc();
//Fetch distinct columns from admit_card_records for dropdown
$columnsResult = $conn->query("SELECT * FROM admit_card_records WHERE project_id = $projectId LIMIT 1");
$all_column = [];
if ($columnsResult && $row = $columnsResult->fetch_assoc()) {
    $all_column = array_keys($row);
}
$stmt = $conn->prepare("SELECT column_based FROM projects WHERE id = ?");
$stmt->bind_param("i", $projectId);
$stmt->execute();
$stmt->bind_result($column_based);
$stmt->fetch();
$stmt->close();

$columns = [];
 if(!empty($all_column)){
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
    $stmt2->bind_param("i", $projectId);
    $stmt2->execute();
    $result = $stmt2->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = htmlspecialchars($row[$column_based]);
        }
    }
}
}

?>

<div class="ml-64 p-5">

    <a href="javascript:history.back()" class="back-button ">← Back</a>
    <h2 class="text-1xl   text-center">✏️ Edit Project for : <span class="font-bold"> <?= $project['name'] ?> <span> </h2>

   <?php 
if (!empty($_SESSION['template_success'])) {
    echo "<div id='file-error' style='color: green; padding: 10px;'>";
    foreach ($_SESSION['template_success'] as $msg) {
        echo "$msg<br>";
    }
    echo "</div>";
      unset($_SESSION['template_success']); 
  
}

if (!empty($_SESSION['template_error'])) {
    echo "<div id='file-error' style='color: red; padding: 10px;'>";
    foreach ($_SESSION['template_error'] as $msg) {
        echo "$msg<br>";
    }
    echo "</div>";
      unset($_SESSION['template_error']); 
 
} ?>
    <!-- Edit Project Info -->
     
    <form action="update_project.php" method="post"  enctype="multipart/form-data" class=" bg-white p-6 rounded-lg shadow-md  border-b text-sm w-1/2 mx-auto">
        <input type="hidden" name="project_id" value="<?= $projectId ?>">

        <label class="block font-semibold">Project Name:</label>
        <input type="text" name="project_name" value="<?= htmlspecialchars($project['name']) ?>" required class="w-full mb-2 border rounded px-2 py-1" readonly>
           <label class="block font-semibold">Header:</label>
        <input type="text" name="header" value="<?=nl2br(htmlspecialchars($project_header)) htmlspecialchars($project['header']) ?>" required class="w-full mb-2 border rounded px-2 py-1">
            <label class="block font-semibold">Sub Header:</label>
        <input type="text" name="sub_header" value="<?= htmlspecialchars($project['sub_header']) ?>" class="w-full mb-2 border rounded px-2 py-1"> 
        <label class="block font-semibold">Description:</label>
        <textarea name="description" class="w-full mb-2 border rounded px-2 py-1"><?= htmlspecialchars($project['description']) ?></textarea>
           <label class="block font-semibold">Forgot Label Name:</label>
        <input type="text" name="forgot_label_name" value="<?= htmlspecialchars($project['forgot_label_name']) ?>" class="w-full mb-2 border rounded px-2 py-1"> 
            <div class="mb-2">
            <label class="block font-semibold mb-1">Logo Image:</label>
            <div class="flex items-center space-x-10">
                <!-- File input on the left -->
                <input type="file" name="logo" class="border rounded px-2 py-1" accept="image/*">

                <!-- Preview image on the right -->
                <?php
                 $path_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
                    $base_url = "$path_url/Admit_Cards";
                   // $base_url = "http://localhost/Admit_Cards";
                    $image_path = $base_url . '/' . htmlspecialchars($project['logo_path']);
                   
                ?>
                <img src="<?= $image_path ?>"  alt="Logo" width="60" height="60" class="border rounded shadow-sm object-cover">
            </div>
        </div>
        <label class="project_live_date block font-semibold">Project Live Date:</label>
        <input type="date" name="project_live_date" value="<?= htmlspecialchars($project['project_live_date']) ?>" class="w-full mb-2 border rounded px-2 py-1" required>
        <label class="project_end_date block font-semibold">Project End Date:</label>
        <input type="date" name="project_end_date" value="<?= htmlspecialchars($project['project_end_date']) ?>" class="w-full mb-2 border rounded px-2 py-1" required>
       
<!--        
        <label class="block font-semibold">Column Based:</label>

        <select name="column_based" required class="w-full mb-4 border rounded px-2 py-1">
            <option value="" disabled>Select Column</option>
            <option value="all" <?= $project['column_based'] === 'all' ? 'selected' : '' ?>>All Columns</option>
            <?php foreach ($columns as $col): ?>
                <option value="<?= htmlspecialchars($col) ?>" <?= $project['column_based'] === $col ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col) ?>
                </option>
            <?php endforeach; ?>
        </select> -->

        <button type="submit" class="bg-blue-600 text-white px-2 py-2 btn btn-sm rounded">💾 Save Changes</button>
    </form>

    <hr class="my-8">
 <?php ?>
   
     <?php if (!empty($all_column)): ?>
         <!-- <p class="text-center"> CSV Re-upload </p> -->
            <h3 class="text-xl font-bold mb-2 text-center">📄 CSV Re-upload </h3>
            <!-- <form action="process_csv.php" method="POST" enctype="multipart/form-data" class = "bg-white p-6 rounded-lg shadow-md  border-b text-sm w-1/2 mx-auto"> -->
         <form action="process_excel.php" method="POST" enctype="multipart/form-data" class = "bg-white p-6 rounded-lg shadow-md  border-b text-sm w-1/2 mx-auto">
            <label class="block font-semibold mb-1">Column Based:</label>

        <select name="column_based" required class="w-full mb-2 border rounded px-2 py-1"  disabled>
            <option value="">Select Column</option>
            <option value="all" <?= $project['column_based'] === 'all' ? 'selected' : '' ?>>All Columns</option>
            <?php foreach ($all_column as $col): ?>
                <option value="<?= htmlspecialchars($col) ?>" <?= $project['column_based'] === $col ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col) ?>
                </option>
            <?php endforeach; ?>
            </select>
    <label class="block font-semibold mb-2">📄 Re-upload Excel </label>
  
        <input type="hidden" name="project_id" value="<?= $projectId ?>">
        <input type="hidden" name="column_based" value="<?= htmlspecialchars($project['column_based']) ?>">
        <input type="file" name="csv_file" accept=".xlsx,.xls" required class="mb-2"><br>
        <button type="submit" class="bg-blue-600 text-white px-2 py-2 btn btn-sm rounded">Submit</button>
    </form>
    
<?php endif; ?>
    <hr class="my-8">

    <!-- Template Management -->

    <h3 class="text-xl font-bold mb-2 text-center">🖼️ Update Templates</h3>
    <form action="update_templates.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md  border-b text-sm w-1/2 mx-auto">
        <input type="hidden" name="project_id" value="<?= $projectId ?>">
        
        <?php $groupedTemplates = [];
$templateStmt = $conn->prepare("SELECT * FROM project_templates WHERE project_id = ? ORDER BY columns_name ASC, page_order ASC");
$templateStmt->bind_param("i", $projectId);
$templateStmt->execute();
$templates = $templateStmt->get_result();

while ($t = $templates->fetch_assoc()) {
    $groupedTemplates[$t['columns_name']][] = $t;
}

?>
<?php if(!empty($groupedTemplates)) { ?>
<?php foreach ($groupedTemplates as $column => $templateGroup): 
    $firstTemplate = $templateGroup[0]; // Use first template ID as identifier
?>
    <div class="border p-4 mb-4 rounded">
        <p><strong>Column:</strong> <?= htmlspecialchars($column) ?></p>
        
        <div class="flex gap-4 flex-wrap mb-2">
            <?php foreach ($templateGroup as $t): ?>
                <div class="text-center">
                    <p>Page <?= $t['page_order'] ?></p>
                     <?php $template_path = $t['template_image_path']; ?>
                     <img src="../<?= $template_path ?>" height="50" width="50" class="rounded shadow">

                  
                </div>
            <?php endforeach; ?>
        </div>

        <label>Change Column Name:</label>
        <select name="column_name[<?= $firstTemplate['id'] ?>]" class="w-full mb-2">
            <option value="">Select</option>
            <option value="all" <?= $column === 'all' ? 'selected' : '' ?>>All</option>
            <?php foreach ($columns as $col): ?>
                <option value="<?= htmlspecialchars($col) ?>" <?= $column === $col ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Replace Images (optional):</label>
        
        <input type="file" name="template_image[<?= $firstTemplate['id'] ?>][]" multiple accept="image/*">
   
    </div>
   
<?php endforeach; ?>
 <?php if($column != 'all') { ?>
<div id="templateSettingsContainer"></div>
<button type="button" id="addMoreButton" class="btn btn-sm btn-secondary mb-2">➕ Add More</button>
<?php }?>

        <button type="submit" class=" btn btn-purple btn-sm text-white  mb-2 rounded">📌 Save Template Changes</button>
    </form>
</div>
<?php }?>
<script>
document.querySelector('input[name="logo"]').addEventListener('change', function (event) {
    const preview = document.querySelector('img[alt="Logo"]');
    const file = event.target.files[0];
    if (file) {
        preview.src = URL.createObjectURL(file);
    }
});
 setTimeout(function() {
    var errBox = document.getElementById('file-error');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000);

let rowIndex = 0;

$('#addMoreButton').on('click', function () {
    const newRow = `
        <div class="templateSettings row mb-3 border rounded p-3 bg-light">
            <div class="col-12 mb-2">
                <label class="form-label">Select Column:</label>
                <select name="new_column_name[]" class="form-select columnDropdown" required>
                    <option value="">Select Column</option>
                    <?php foreach ($columns as $column): ?>
                        <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars($column) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 mb-2">
                <label class="form-label">Upload Template Image(s):</label>
                <input type="file" name="new_template_images[${rowIndex}][]" multiple class="form-control" required>
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-danger removeButton">❌ Remove</button>
            </div>
        </div>
    `;
    $('#templateSettingsContainer').append(newRow);
    rowIndex++;
});


// Remove row
$(document).on('click', '.removeButton', function () {
    $(this).closest('.templateSettings').remove();
});
</script>

