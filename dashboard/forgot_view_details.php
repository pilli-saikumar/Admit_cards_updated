<?php
session_start();
include("../db_connect.php");
include("../includes/function.php");
$project_id = intval($_POST['project_id'] ?? 0);
$errors = [];
if($project_id <= 0){
    $errors[] = "Invalid project ID.";
}

$view_fields = $_POST['view_fields'] ?? [];
$created_by = $_SESSION['user_id'] ?? null;
$updated_by = $_SESSION['user_id'] ?? null;
$updated_at = date('Y-m-d H:i:s');
$created_at = date('Y-m-d H:i:s');


if(empty($errors)){

  //  First, get all existing records for this project
    $existingStmt = $conn->prepare("SELECT column_name FROM forgot_view_details WHERE project_id = ?");
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
    if(!empty($existingColumns) && !empty($submittedColumns)){
      
            $deleteStmt = $conn->prepare("DELETE FROM forgot_view_details WHERE project_id = ?");
            $deleteStmt->bind_param("i", $project_id);
            $deleteStmt->execute();
            $deleteStmt->close();
       
    }
//    exit;
    
    // Insert/Update submitted fields   

    foreach($view_fields as $key => $value){
        if (empty($value['column']) || empty($value['label'])) {
            continue; // Skip empty fields
        }
        $label_name = $value['label'];
        $column_name = $value['column'];
        $view_status = $value['status'];
       

        $checkStmt = $conn->prepare("SELECT id FROM forgot_view_details WHERE project_id = ? AND column_name = ?");
        $checkStmt->bind_param("is", $project_id, $column_name);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        

        if ($checkResult->num_rows > 0) {
            // Update
            $updateStmt = $conn->prepare("UPDATE forgot_view_details SET label_name = ? WHERE project_id = ? AND column_name = ? AND view_status = ? AND updated_by = ? AND updated_at = ?");
            $updateStmt->bind_param("sissis", $label_name, $project_id, $column_name,$view_status,$updated_by,$updated_at);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert
            $insertStmt = $conn->prepare("INSERT INTO forgot_view_details (project_id, column_name, label_name, view_status, created_by, created_at, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issisiis", $project_id, $column_name, $label_name, $view_status, $created_by, $created_at, $updated_by, $updated_at);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
  
    if(empty($errors)){
        $_SESSION['success_message'] = "Forgot View Settings saved successfully!";
        header("Location: forgot_settings.php?project_id=" . $project_id);
        exit;
    }
}


