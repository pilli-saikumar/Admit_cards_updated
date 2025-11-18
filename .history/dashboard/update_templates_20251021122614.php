<?php
session_start();

include("../db_connect.php");

$project_id = $_POST['project_id'] ?? 0;
$column_names = $_POST['column_name'] ?? [];
$new_column_names = $_POST['new_column_name'] ?? [];

echo "<pre/>"; print_r($column_names);
die;

// Get project slug from DB
$slug_res = $conn->query("SELECT name FROM projects WHERE id = $project_id");
$slug_row = $slug_res->fetch_assoc();
$project_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $slug_row['name']));

// Set folder path
$relative_template_dir = "$project_slug/templates/"; // for DB
$absolute_template_dir = dirname(__DIR__) . "/" . $relative_template_dir; // full path for move/upload

// Ensure directory exists
if (!is_dir($absolute_template_dir)) {
    mkdir($absolute_template_dir, 0755, true);
}

$successes = [];
$errors = [];

// --- 1. Process Existing Templates (Column Name Updates and Image Updates) ---
foreach ($column_names as $template_id => $column) {
    $column = trim($column);

    // Get existing template data for comparison
    $stmt_get_existing = $conn->prepare("SELECT columns_name, template_image_path FROM project_templates WHERE id = ? AND project_id = ?");
    $stmt_get_existing->bind_param("ii", $template_id, $project_id);
    $stmt_get_existing->execute();
    $result_get_existing = $stmt_get_existing->get_result();
    $existing_template_data = $result_get_existing->fetch_assoc();
    $stmt_get_existing->close();

    // Update column name if it has changed
    if ($existing_template_data && $existing_template_data['columns_name'] !== $column) {
        $stmt_update_column_name = $conn->prepare("UPDATE project_templates SET columns_name = ? WHERE id = ? AND project_id = ?");
        $stmt_update_column_name->bind_param("sii", $column, $template_id, $project_id);
        if ($stmt_update_column_name->execute()) {
            $successes[] = "✅ Updated column name for template ID $template_id to '$column'";
        } else {
            $errors[] = "❌ Failed to update column name for template ID $template_id.";
        }
        $stmt_update_column_name->close();
    }

    // Handle template image updates for existing templates
    if (isset($_FILES['template_image']['tmp_name'][$template_id]) && !empty($_FILES['template_image']['tmp_name'][$template_id][0])) {
        $uploaded_files = $_FILES['template_image']['tmp_name'][$template_id];
        $uploaded_names = $_FILES['template_image']['name'][$template_id];

        // Fetch existing templates for this column to manage updates/deletions
        $stmt_fetch_current_column_templates = $conn->prepare("SELECT id, template_image_path,template_name,page_order,template_width,template_height,columns_name FROM project_templates WHERE project_id = ? AND columns_name = ? ORDER BY page_order ASC");
        $stmt_fetch_current_column_templates->bind_param("is", $project_id, $column);
        $stmt_fetch_current_column_templates->execute();
        $result_fetch_current_column_templates = $stmt_fetch_current_column_templates->get_result();
        $existing_templates_for_column = [];
        while ($row = $result_fetch_current_column_templates->fetch_assoc()) {
            $existing_templates_for_column[] = $row;
        }
        $stmt_fetch_current_column_templates->close();


        foreach ($uploaded_files as $i => $tmpName) {
            if (!is_uploaded_file($tmpName)) continue;

            $originalName = $uploaded_names[$i];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $errors[] = "❌ Invalid file type for '$originalName'.";
                continue;
            }

            $newFilename = uniqid() . '_' . basename($originalName);
            $relative_path = $relative_template_dir . $newFilename;
            $absolute_path = $absolute_template_dir . $newFilename;

            if (!move_uploaded_file($tmpName, $absolute_path)) {
                $errors[] = "❌ Failed to upload '$originalName'.";
                continue;
            }

            list($width, $height) = getimagesize($absolute_path);
            $page_order = $i + 1;
            date_default_timezone_set('Asia/Kolkata');
            $backup_at = date('Y-m-d H:i:s');
            $updated_by = $_SESSION['user_id'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            if (!empty($existing_templates_for_column[$i])) {
                
                $old_template_data = $existing_templates_for_column[$i];
                $id_to_update = $old_template_data['id'];
                $insert_old_template = $conn->prepare("INSERT INTO project_templates_edited (old_template_id,project_id, template_name, template_image_path,columns_name, page_order, template_width, template_height,backup_at,updated_by,ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_old_template->bind_param("iisssiissss",$id_to_update, $project_id, $old_template_data['template_name'], $old_template_data['template_image_path'], $old_template_data['columns_name'], $old_template_data['page_order'], $old_template_data['template_width'], $old_template_data['template_height'],$backup_at,$updated_by,$ip_address);
                $insert_old_template->execute();
                $insert_old_template->close();

                // Delete old file if it exists
                if (!empty($old_template_data['template_image_path']) && file_exists(dirname(__DIR__) . "/" . $old_template_data['template_image_path'])) {
                    unlink(dirname(__DIR__) . "/" . $old_template_data['template_image_path']);
                }

                $stmt_update_template = $conn->prepare("
                    UPDATE project_templates
                    SET template_name = ?, template_image_path = ?, template_width = ?, template_height = ?, page_order = ?
                    WHERE id = ? AND project_id = ?
                ");
                $stmt_update_template->bind_param("ssiiiii", $originalName, $relative_path, $width, $height, $page_order, $id_to_update, $project_id);
                if ($stmt_update_template->execute()) {
                    $successes[] = "✅ Updated template for '$column' (ID: $id_to_update).";
                } else {
                    $errors[] = "❌ Failed to update template for '$column' (ID: $id_to_update).";
                }
                $stmt_update_template->close();
            } else {
                // This means new images are uploaded for an existing column beyond the existing count
                $stmt_insert_new_template = $conn->prepare("
                    INSERT INTO project_templates
                    (project_id, columns_name, template_name, template_image_path, page_order, template_width, template_height)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert_new_template->bind_param("isssiii", $project_id, $column, $originalName, $relative_path, $page_order, $width, $height);
                if ($stmt_insert_new_template->execute()) {
                    $successes[] = "✅ Added new template image for '$column'.";
                } else {
                    $errors[] = "❌ Failed to add new template image for '$column'.";
                }
                $stmt_insert_new_template->close();
            }
        }

        // Delete excess templates for this column if fewer files were uploaded than existing entries
        $uploaded_count = count($uploaded_files);
        $existing_count = count($existing_templates_for_column);

        // if ($uploaded_count < $existing_count) {
        //     for ($j = $uploaded_count; $j < $existing_count; $j++) {
        //         $toDelete = $existing_templates_for_column[$j];
        //         if (!empty($toDelete['template_image_path']) && file_exists(dirname(__DIR__) . "/" . $toDelete['template_image_path'])) {
        //             unlink(dirname(__DIR__) . "/" . $toDelete['template_image_path']);
        //         }
        //         $stmt_delete = $conn->prepare("DELETE FROM project_templates WHERE id = ? AND project_id = ?");
        //         $stmt_delete->bind_param("ii", $toDelete['id'], $project_id);
        //         if ($stmt_delete->execute()) {
        //             $successes[] = "🗑️ Removed unused template ID {$toDelete['id']} for '$column'.";
        //         } else {
        //             $errors[] = "❌ Failed to remove unused template ID {$toDelete['id']} for '$column'.";
        //         }
        //         $stmt_delete->close();
        //     }
        // }
                    if ($uploaded_count < $existing_count) {
                for ($j = $uploaded_count; $j < $existing_count; $j++) {
                    $toDelete = $existing_templates_for_column[$j];

                    // Delete the related file if it exists
                    if (!empty($toDelete['template_image_path']) && file_exists(dirname(__DIR__) . "/" . $toDelete['template_image_path'])) {
                        unlink(dirname(__DIR__) . "/" . $toDelete['template_image_path']);
                    }

                    // First delete from field_mappings (child table)
                    $stmt_delete_mapping = $conn->prepare("DELETE FROM field_mappings WHERE template_id = ?");
                    $stmt_delete_mapping->bind_param("i", $toDelete['id']);
                    if ($stmt_delete_mapping->execute()) {
                        $successes[] = "✅ Deleted field mappings for template ID {$toDelete['id']}.";
                    } else {
                        $errors[] = "❌ Failed to delete field mappings for template ID {$toDelete['id']}.";
                    }
                    $stmt_delete_mapping->close();

                    // Now delete from project_templates (parent table)
                    $stmt_delete = $conn->prepare("DELETE FROM project_templates WHERE id = ? AND project_id = ?");
                    $stmt_delete->bind_param("ii", $toDelete['id'], $project_id);
                    if ($stmt_delete->execute()) {
                        $successes[] = "🗑️ Removed unused template ID {$toDelete['id']} for '$column'.";
                    } else {
                        $errors[] = "❌ Failed to remove unused template ID {$toDelete['id']} for '$column'.";
                    }
                    $stmt_delete->close();
                }
            }
    }
}

// --- 2. Process New Column Names (with or without new images) ---
if (!empty($new_column_names)) {
    foreach ($new_column_names as $index => $new_column_name) {
        $new_column_name = trim($new_column_name);

        $new_template_files_exist = isset($_FILES['new_template_images']['tmp_name'][$index]) && !empty($_FILES['new_template_images']['tmp_name'][$index][0]);

        if ($new_template_files_exist) {
            $uploaded_files = $_FILES['new_template_images']['tmp_name'][$index];
            $uploaded_names = $_FILES['new_template_images']['name'][$index];

            foreach ($uploaded_files as $i => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;

                $originalName = $uploaded_names[$i];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $errors[] = "❌ Invalid file type for new template '$originalName'.";
                    continue;
                }

                $newFilename = uniqid() . '_' . basename($originalName);
                $relative_path = $relative_template_dir . $newFilename;
                $absolute_path = $absolute_template_dir . $newFilename;

                if (!move_uploaded_file($tmpName, $absolute_path)) {
                    $errors[] = "❌ Failed to upload new template '$originalName'.";
                    continue;
                }

                list($width, $height) = getimagesize($absolute_path);
                $page_order = $i + 1;

                $stmt_insert_new = $conn->prepare("
                    INSERT INTO project_templates
                    (project_id, columns_name, template_name, template_image_path, page_order, template_width, template_height)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_insert_new->bind_param("isssiii", $project_id, $new_column_name, $originalName, $relative_path, $page_order, $width, $height);
                if ($stmt_insert_new->execute()) {
                    $successes[] = "✅ Inserted new template with image for '$new_column_name'.";
                } else {
                    $errors[] = "❌ Failed to insert new template with image for '$new_column_name'.";
                }
                $stmt_insert_new->close();
            }
        } else {
            // Insert new column name without an associated image
            $stmt_insert_new_column_only = $conn->prepare("
                INSERT INTO project_templates
                (project_id, columns_name, page_order)
                VALUES (?, ?, ?)
            ");
            // Assuming page_order can be 0 or a default for columns without images.
            // You might want to define a specific logic for page_order for such entries.
            $default_page_order = 0; // Or whatever makes sense for your application
            $stmt_insert_new_column_only->bind_param("isi", $project_id, $new_column_name, $default_page_order);
            if ($stmt_insert_new_column_only->execute()) {
                $successes[] = "✅ Inserted new column name '$new_column_name' (no image).";
            } else {
                $errors[] = "❌ Failed to insert new column name '$new_column_name'.";
            }
            $stmt_insert_new_column_only->close();
        }
    }
}

// ✅ Store success & error messages in session
$_SESSION['template_success'] = $successes;
$_SESSION['template_error'] = $errors;

// 🔁 Redirect back
header("Location: edit_project.php?project_id=$project_id");
exit;
?>