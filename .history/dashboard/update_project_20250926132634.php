<?php 

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

    $get_project = $conn->prepare("SELECT  name,logo_path FROM projects where id = ? ");
    $get_project->bind_param("i", $project_id);
    $get_project->execute();
    $get_project->bind_result($projectNameRaw, $existingLogoPath);
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
    $project_show = $conn->prepare
    $
  
    // Now update DB if no errors
    if (empty($errors)) {
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