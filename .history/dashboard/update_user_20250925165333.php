<?php
session_start();
include("../db_connect.php");

$id = intval($_POST['id']);

$project_id = $_POST['project_id'];

$columnsResult = $conn->query("SHOW COLUMNS FROM admit_card_records");
$columns = [];
while ($col = $columnsResult->fetch_assoc()) {
    $columns[] = $col['Field'];
}

// Remove 'id' because backup table has its own AUTO_INCREMENT id
$columns = array_filter($columns, fn($c) => $c !== 'id');

// Build comma-separated list of columns for INSERT and SELECT
$insertColumns = 'admitcard_record_id,' . implode(',', $columns);
$selectColumns = $id . ',' . implode(',', $columns); // original id goes to admitcard_record_id

// Prepare SQL
$backupSql = "INSERT INTO admitcard_edit_data ($insertColumns) SELECT $selectColumns FROM admit_card_records WHERE id = ?";
$backupStmt = $conn->prepare($backupSql);
$backupStmt->bind_param("i", $id);
$backupStmt->execute();
$backupStmt->close();
// Fetch column names
$uploadBase = "../uploads"; // Folder to store photos/signatures
$projectSlug = ""; // optional: set based on project if needed
$columnsResult = $conn->query("SHOW COLUMNS FROM admit_card_records");

// Define columns to skip
$exclude = ['id', 'photo_path', 'signature_path', 'created_at', 'role', 'photo'];

// Build dynamic SET clause
$fields = [];
$types = '';
$values = [];

while ($col = $columnsResult->fetch_assoc()) {
    $field = $col['Field'];
    if (!in_array($field, $exclude) && isset($_POST[$field])) {
        $fields[] = "$field = ?";
        $types .= 's'; // assuming all are strings (you can improve this per type)
        $values[] = $_POST[$field];
    }
}


$project_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id")->fetch_assoc()['slug'];

 
    // $upload_dir = dirname(__DIR__) . "/$project_slug/" . ($_FILES['photo_path']['name'] === 'photo_path' ? 'photos' : 'signatures') . "/";
 
    $upload_dir = dirname(__DIR__) . "/$project_slug/";

// Handle photo upload
// if (isset($_FILES['photo_path']) && $_FILES['photo_path']['error'] === UPLOAD_ERR_OK) {
//     $ext = pathinfo($_FILES['photo_path']['name'], PATHINFO_EXTENSION);
//     $photoName = 'photo_' . time() . '.' . $ext;
//     $photoPath = "$uploadBase/photos/" . $photoName;

//     if (!is_dir(dirname($photoPath))) {
//         mkdir(dirname($photoPath), 0755, true);
//     }

//     if (move_uploaded_file($_FILES['photo_path']['tmp_name'], $photoPath)) {
//         $relativePhotoPath = ltrim($photoPath, '../'); // store relative path
//         $fields[] = "photo_path = ?";
//         $types .= 's';
//         $values[] = $relativePhotoPath;
//     }
// }
if(isset($_FILES['photo_path']) && $_FILES['photo_path']['error'] === UPLOAD_ERR_OK){
    $ext = pathinfo($_FILES['photo_path']['name'], PATHINFO_EXTENSION);
    $photoName = 'photo_' . time() . '.' . $ext;
   
    $photoPath = "$upload_dir/photos/" . $photoName;
    $folder = 'photos';
    $dbPath = "$project_slug/$folder/$photoName";
  
    if (!is_dir(dirname($photoPath))) {
        mkdir(dirname($photoPath), 0755, true);
    }

    if (move_uploaded_file($_FILES['photo_path']['tmp_name'], $photoPath)) {

        $relativePhotoPath = ltrim($dbPath); // store relative path
        
        $fields[] = "photo_path = ?";
        $types .= 's';
        $values[] = $relativePhotoPath;
    }
}
// Handle signature upload
if(isset($_FILES['signature_path']) && $_FILES['signature_path']['error'] === UPLOAD_ERR_OK){
    $ext = pathinfo($_FILES['signature_path']['name'], PATHINFO_EXTENSION);
    $signName = 'sign_' . time() . '.' . $ext;
    
    $signPath = "$upload_dir/signatures/" . $signName;
    $folder = 'signatures';
    $dbPath = "$project_slug/$folder/$signName";
    if (!is_dir(dirname($signPath))) {
        mkdir(dirname($signPath), 0755, true);
    }

    if (move_uploaded_file($_FILES['signature_path']['tmp_name'], $signPath)) {
        $relativeSignPath = ltrim($dbPath); // store relative path
        $fields[] = "signature_path = ?";
        $types .= 's';
        $values[] = $relativeSignPath;
    }
}
// if (isset($_FILES['signature_path']) && $_FILES['signature_path']['error'] === UPLOAD_ERR_OK) {
//     $ext = pathinfo($_FILES['signature_path']['name'], PATHINFO_EXTENSION);
//     $signName = 'sign_' . time() . '.' . $ext;
//     $signPath = "$uploadBase/signs/" . $signName;

//     if (!is_dir(dirname($signPath))) {
//         mkdir(dirname($signPath), 0755, true);
//     }

//     if (move_uploaded_file($_FILES['signature_path']['tmp_name'], $signPath)) {
//         $relativeSignPath = ltrim($signPath, '../'); // store relative path
//         $fields[] = "signature_path = ?";
//         $types .= 's';
//         $values[] = $relativeSignPath;
//     }
// }

// Append ID for WHERE clause
$types .= 'i';
$values[] = $id;

// Final SQL
$sql = "UPDATE admit_card_records SET " . implode(", ", $fields) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);

// $sql_insert = "INSERT INTO admitcard_edit_data (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
// $stmt_insert = $conn->prepare($sql_insert);
// $stmt_insert->bind_param($types, ...$values);
// $stmt_insert->execute();
// $stmt_insert->close();

if ($stmt->execute()) {
    $_SESSION['flash_success'] = "✅ Record updated successfully.";
} else {
    $_SESSION['flash_error'] = "❌ Update failed: " . $stmt->error;
}

header("Location: users.php?project_id=$project_id"); // update with your redirect
exit;
