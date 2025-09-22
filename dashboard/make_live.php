<?php
session_start();
include("../db_connect.php");
include("../includes/function.php");

//$project_id = intval($_GET['project_id']);
$project_id = $_POST['project_id'] ?? null;
$columns_name = $_POST['columns_name'] ?? null;
$column_based = $_POST['column_based'] ?? null;
$live_date = $_POST['live_date'] ?? null;
$unlive_date = $_POST['unlive_date'] ?? null;
$live_time = $_POST['live_time'] ?? null;
$unlive_time = $_POST['unlive_time'] ?? null;

$new_status = isset($_POST['is_admit_card_live']) ? intval($_POST['is_admit_card_live']) : null;

    //  if (!empty($live_date) && !empty($unlive_date) && $live_date > $unlive_date) {
    //     $errors[] = 'Project live date cannot be later than project end date.';
    //     $_SESSION['error_message'] = 'Project live date cannot be later than project end date.';
    //     header("Location: check_admitcard.php?project_id=" . $project_id);
    // }

if ($project_id > 0 && !empty($column_based) && !empty($columns_name)) {

    // // Optional: restrict to allowed column names
    // $allowed_columns = ['community', 'category', 'gender']; // Add your list
    // if (!in_array($column_based, $allowed_columns)) {
    //     die("Invalid column name.");
    // }
    

   // Update only the target row
    if($columns_name == 'all' && $column_based == 'all') {
        
    $stmt = $conn->prepare("
        UPDATE admit_card_records 
        SET is_admit_card_live = ? ,live_date = ?, unlive_date = ?,live_time = ?,unlive_time = ?
        WHERE project_id = ? 
    ");
    $stmt->bind_param("issssi", $new_status, $live_date, $unlive_date,$live_time,$unlive_time,$project_id);
    }else{
      $stmt = $conn->prepare("
        UPDATE admit_card_records 
        SET is_admit_card_live = ? ,live_date = ?, unlive_date = ?  ,live_time = ?,unlive_time = ?
        WHERE project_id = ? AND `$column_based` = ?
    ");
    $stmt->bind_param("issssis", $new_status,$live_date,$unlive_date,$live_time,$unlive_time, $project_id, $columns_name);

    }



    if ($stmt->execute()) {
        $_SESSION['success_message'] = $new_status ? "Admit Card Live Successfully" : "Admit Card Unlive Successfully";
         if($new_status === 0){
            $action = "Admit Card Unlive Successfully For :" .$columns_name;
         }else{
              $action = "Admit Card Live Successfully :" .$columns_name;
         }
         logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id,0, $action);
       header("Location: check_admitcard.php?project_id=" . $project_id);
        exit();
    } else {
        echo "Failed to update.";
    }

} else {
    echo "Missing or invalid input.";
}