<?php
session_start();
include("../db_connect.php");
include("../includes/function.php");
$project_id = intval($_POST['project_id']);
if($project_id <= 0){
    echo "Invalid project ID.";
    exit;
}

$view_fields = $_POST['view_fields'] ?? [];
$created_by = $_SESSION['user_id'] ?? null;
$errors = [];

// First, get all existing records for this project
$existingStmt = $conn->prepare("SELECT column_name FROM candidate_view_details WHERE project_id = ?");
$existingStmt->bind_param("i", $project_id);
$existingStmt->execute();
$existingResult = $existingStmt->get_result();
$existingColumns = [];
while ($row = $existingResult->fetch_assoc()) {
    $existingColumns[] = $row['column_name'];
}
$existingStmt->close();

// Get submitted columns
$submittedColumns = [];
foreach($view_fields as $field) {
    if (!empty($field['column'])) {
        $submittedColumns[] = $field['column'];
    }
}

// Delete records that are not in submitted data
foreach($existingColumns as $existingColumn) {
    if (!in_array($existingColumn, $submittedColumns)) {
        $deleteStmt = $conn->prepare("DELETE FROM candidate_view_details WHERE project_id = ? AND column_name = ?");
        $deleteStmt->bind_param("is", $project_id, $existingColumn);
        $deleteStmt->execute();
        $deleteStmt->close();
        echo "Deleted: $existingColumn<br>";
    }
}

// Insert/Update submitted fields
foreach($view_fields as $key => $value){
    if (empty($value['column']) || empty($value['label'])) {
        continue; // Skip empty fields
    }
    
    $label_name = $value['label'];
    $column_name = $value['column'];
    
    $checkStmt = $conn->prepare("SELECT id FROM candidate_view_details WHERE project_id = ? AND column_name = ?");
    $checkStmt->bind_param("is", $project_id, $column_name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // Update
        $updateStmt = $conn->prepare("UPDATE candidate_view_details SET label_name = ? WHERE project_id = ? AND column_name = ?");
        $updateStmt->bind_param("sis", $label_name, $project_id, $column_name);
        $updateStmt->execute();
        $updateStmt->close();
        echo "Updated: $column_name<br>";

        $_SESSION['success_message'] = "Candidate View Settings updated successfully!";
        $action = "Updated candidate view settings for " . $label_name;
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
    } else {
        // Insert
        $insertStmt = $conn->prepare("INSERT INTO candidate_view_details (project_id, column_name, label_name, created_by) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("issi", $project_id, $column_name, $label_name, $created_by);
        $insertStmt->execute();
        $insertStmt->close();
        echo "Inserted: $column_name<br>";
        $_SESSION['success_message'] = "Candidate View Settings created successfully!";
        $action = "Created candidate view settings for " . $label_name;
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
    }

    $checkStmt->close();
}

if(empty($errors)){
    $_SESSION['success_message'] = "Candidate View Settings saved successfully!";
    header("Location: candidate_login_settings.php?project_id=" . $project_id);
    exit;
}
?>