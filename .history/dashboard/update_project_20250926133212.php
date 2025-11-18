<?php 
session_start();
include("../db_connect.php");



if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $project_id = $_POST['project_id'];
    $project_name = $_POST['project_name'];
    $project_description = $_POST['description'];
    $project_header = $_POST['header'];
  $project_live_date = $_POST['project_live_date'] ?? null;
    $project_end_date = $_POST['project_end_date'] ?? null;
    $sub_header = $_POST['sub_header'] ?? null;
    $forgot_label_name = $_POST['forgot_label_name'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'];  // capture IP
    $edited_user_id = $_SESSION['user_id'] ?? null; // assuming login session stores this

    // $get_project = $conn->prepare("SELECT  name,logo_path FROM projects where id = ? ");
    // $get_project->bind_param("i", $project_id);
    // $get_project->execute();
    // $get_project->bind_result($projectNameRaw, $existingLogoPath);
    // $get_project->fetch();
    // $get_project->close();
    $get_project = $conn->prepare("SELECT name, logo_path, header, description, project_live_date, project_end_date, sub_header, forgot_label_name FROM projects WHERE id = ?");
    $get_project->bind_param("i", $project_id);
    $get_project->execute();
    $get_project->bind_result($projectNameRaw, $existingLogoPath, $old_header, $old_description, $old_live_date, $old_end_date, $old_sub_header, $old_forgot_label_name);
    $get_project->fetch();
    $get_project->close();


$projectNameSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $projectNameRaw));
$logoPath = $existingLogoPath;

  if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logoFileName = 'logo_' . time() . '.' . $ext;
        
     //   $uploadDir = __DIR__ . "/$projectNameSlug/logos/";
$uploadDir = dirname(__DIR__) . "/$projectNameSlug/logos/";


        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $errors[] = 'Failed to create logo directory.';
            }
        }

        if (empty($errors)) {
            $fullLogoPath = $uploadDir . $logoFileName;
            $relativeLogoPath = "$projectNameSlug/logos/" . $logoFileName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $fullLogoPath)) {
                $logoPath = $relativeLogoPath;
                 // Delete old logo file if it exists
            if (!empty($existingLogoPath)) {
                $oldLogoFullPath = dirname(__DIR__) . '/' . $existingLogoPath;
                if (file_exists($oldLogoFullPath)) {
                    unlink($oldLogoFullPath);
                }
            }
                        
            } else {
                $errors[] = 'Failed to upload new logo.';
            }
        }
    }
   

    
  
    // Now update DB if no errors
    if (empty($errors)) {
        $backup = $conn->prepare("
        INSERT INTO project_edited 
        (project_id, name, header, description, logo_path, project_live_date, project_end_date, sub_header, forgot_label_name, updated_by, edited_user_id, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $updated_by = $edited_user_id; // or whoever is updating
    $backup->bind_param(
        "issssssssiss",
        $project_id, $projectNameRaw, $old_header, $old_description, $existingLogoPath,
        $old_live_date, $old_end_date, $old_sub_header, $old_forgot_label_name,
        $updated_by, $edited_user_id, $ip_address
    );
    $backup->execute();
    $backup->close();
        $stmt = $conn->prepare("UPDATE projects SET header = ?, description = ?, logo_path = ? ,project_live_date = ? ,project_end_date = ?,sub_header = ? ,forgot_label_name = ?  WHERE id = ? ");
        $stmt->bind_param("sssssssi", $project_header, $project_description, $logoPath,$project_live_date,$project_end_date, $sub_header,$forgot_label_name, $project_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Project updated successfully!";
            header("Location: edit_project.php?project_id=".$project_id);
            exit;
        } else {
            $errors[] = "Failed to update project: " . $stmt->error;
        }
        $stmt->close();
    }
}