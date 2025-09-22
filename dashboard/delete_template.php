<?php
 session_start();
 include("../db_connect.php");
 include("../includes/function.php");
//  if (empty($_SESSION["user_name"])) {
//     header("Location: index.php");
//     exit();
// }

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['project_id'])) {
    $projectId = intval($_GET['project_id']);
        $backupTimestamp = date('Y-m-d H:i:s');
   
    $backupUser = $_SESSION['user_name'] ?? 'unknown';
    $backupIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // First, get all template_ids for this project
//     $getTemplates = $conn->prepare("SELECT id FROM project_templates WHERE project_id = ?");
//     $getTemplates->bind_param("i", $projectId);
//     $getTemplates->execute();
//     $result = $getTemplates->get_result();

//     while ($row = $result->fetch_assoc()) {
//         $templateId = $row['id'];

//         // Delete all field mappings for each template
//         $deleteMappings = $conn->prepare("DELETE FROM field_mappings WHERE template_id = ?");
//         $deleteMappings->bind_param("i", $templateId);
//         $deleteMappings->execute();
//         $deleteMappings->close();
//     }
//     $getTemplates->close();

//     // Delete templates
//     $stmt = $conn->prepare("DELETE FROM project_templates WHERE project_id = ?");
//     $stmt->bind_param("i", $projectId);

//     if ($stmt->execute()) {
//         // Delete project entry
//         $stmt2 = $conn->prepare("DELETE FROM projects WHERE id = ?");
//         $stmt2->bind_param("i", $projectId);
//         if ($stmt2->execute()) {
//             $_SESSION['DELETE_SUCCESS'] = "Project and associated templates deleted successfully!";
//             header("Location: dashboard.php");
//             exit;
//         } else {
//             echo "Error deleting project: " . $conn->error;
//         }
//         $stmt2->close();
//     } else {
//         echo "Error deleting template: " . $conn->error;
//     }

//     $stmt->close();
// } else {
//     echo "Invalid request. No project ID provided.";
// }
// $deleteMappings = $conn->prepare("
//         DELETE FROM field_mappings 
//         WHERE template_id IN (
//             SELECT id FROM project_templates WHERE project_id = ?
//         )
//     ");
//     $deleteMappings->bind_param("i", $projectId);
//     $deleteMappings->execute();
//     $deleteMappings->close();

//     // Delete all templates for the project
//     $deleteTemplates = $conn->prepare("DELETE FROM project_templates WHERE project_id = ?");
//     $deleteTemplates->bind_param("i", $projectId);
//     $deleteTemplates->execute();
//     $deleteTemplates->close();

//     $deleteadmitcard = $conn->prepare("DELETE FROM admit_card_records WHERE project_id = ?");

//     $deleteadmitcard->bind_param("i", $projectId);
//     $deleteadmitcard->execute();
//     $deleteadmitcard->close();

//     // Delete the project
//     $deleteProject = $conn->prepare("DELETE FROM projects WHERE id = ?");
//     $deleteProject->bind_param("i", $projectId);

//     if ($deleteProject->execute()) {
//         $_SESSION['DELETE_SUCCESS'] = "Project and associated data deleted successfully!";
//         header("Location: dashboard.php");
//         exit;
//     } else {
//         echo "Error deleting project: " . $conn->error;
//     }

//     $deleteProject->close();
// } else {
//     echo "Invalid request. No project ID provided.";
// }
   $fetchQuery = $conn->prepare("SELECT * FROM admit_card_records WHERE project_id = ?");
    $fetchQuery->bind_param("i", $projectId);
    $fetchQuery->execute();
    $result = $fetchQuery->get_result();
   
   
       
    while ($row = $result->fetch_assoc()) {
        $insertQuery = $conn->prepare("
            INSERT INTO admit_card_records_backup (
                id, project_id, first_name, middle_name, last_name, father_name,
                email, mobileNumber, sex, dob, community, address, district,
                state, postoffice, landmark, pincode, exam_center, exam_date,
                exam_time, roll_number, registration_number, photo_path, signature_path,
                other_info, created_at, role, is_admit_card_live, photo,
                backup_timestamp, backup_user, backup_ip
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
$insertQuery->bind_param(
    "iissssssssssssssssssssssssssssss",  // 33 characters: 2 i + 31 s
    $row['id'],
    $row['project_id'],
    $row['first_name'],
    $row['middle_name'],
    $row['last_name'],
    $row['father_name'],
    $row['email'],
    $row['mobileNumber'],
    $row['sex'],
    $row['dob'],
    $row['community'],
    $row['address'],
    $row['district'],
    $row['state'],
    $row['postoffice'],
    $row['landmark'],
    $row['pincode'],
    $row['exam_center'],
    $row['exam_date'],
    $row['exam_time'],
    $row['roll_number'],
    $row['registration_number'],
    $row['photo_path'],
    $row['signature_path'],
    $row['other_info'],
    $row['created_at'],
    $row['role'],
    $row['is_admit_card_live'], // even though it's int, treat it as string to simplify
    $row['photo'],
    $backupTimestamp,
    $backupUser,
    $backupIP
);

        $insertQuery->execute();
        $insertQuery->close();
    }
    $fetchQuery->close();
  

    // Step 2: Delete from field_mappings based on templates
    $deleteMappings = $conn->prepare("
        DELETE FROM field_mappings 
        WHERE template_id IN (
            SELECT id FROM project_templates WHERE project_id = ?
        )
    ");
    $deleteMappings->bind_param("i", $projectId);
    $deleteMappings->execute();
    $deleteMappings->close();

      $fetchTemplates = $conn->prepare("SELECT * FROM project_templates WHERE project_id = ?");
    $fetchTemplates->bind_param("i", $projectId);
    $fetchTemplates->execute(); 

    $template_result = $fetchTemplates->get_result();
    while ($template = $template_result->fetch_assoc()) {
        $insert_template = $conn->prepare("INSERT INTO project_templates_backup (
            id, project_id, template_name, template_image_path, columns_name, 
            page_order, template_width, template_height, deleted_by, deleted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $insert_template->bind_param("iisssissss", 
            $template["id"],
            $template["project_id"],
            $template["template_name"],
            $template["template_image_path"],
            $template["columns_name"],
            $template["page_order"],
            $template["template_width"],
            $template["template_height"],
            $backupUser,
            $backupTimestamp
        );

        $insert_template->execute();
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $projectId,  $template["id"], "Project Deleted :" .  $template["template_name"]);
        $insert_template->close();   
    }

    $fetchTemplates->close();
    // Step 3: Delete project templates
    $deleteTemplates = $conn->prepare("DELETE FROM project_templates WHERE project_id = ?");
    $deleteTemplates->bind_param("i", $projectId);
    $deleteTemplates->execute();
    $deleteTemplates->close();

    // Step 4: Delete admit card records
    $deleteAdmitCard = $conn->prepare("DELETE FROM admit_card_records WHERE project_id = ?");
    $deleteAdmitCard->bind_param("i", $projectId);
    $deleteAdmitCard->execute();
    $deleteAdmitCard->close();
$deleted_by = $_SESSION['user_name'];


$stmt = $conn->prepare("UPDATE projects SET is_delete = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?");
$stmt->bind_param("si", $deleted_by, $projectId);
$stmt->execute();
   $stmt->close();


    // $softDelete = $conn->prepare("UPDATE projects SET is_delete = 1 WHERE id = ?");
    //     $softDelete->bind_param("i", $projectId);
    //     $softDelete->execute();
    //     $softDelete->close();

    // ❌ Step 5: Do NOT delete the project itself
    // Commented out the project deletion
    // $deleteProject = $conn->prepare("DELETE FROM projects WHERE id = ?");
    // $deleteProject->bind_param("i", $projectId);
    // if ($deleteProject->execute()) {

    $_SESSION['DELETE_SUCCESS'] = "Admit card data backed up and related records deleted. Project retained.";
  
    header("Location: dashboard.php");
    exit;

    // } else {
    //     echo "Error deleting project: " . $conn->error;
    //     $deleteProject->close();
    // }

} else {
    echo "Invalid request. No project ID provided.";
}

?>