<?php 
  session_start();
 include("../db_connect.php");
 include("../includes/function.php");

 


// if ($_SERVER['REQUEST_METHOD'] === 'POST') {

//     $project_id = intval($_POST['project_id']); // Ensure project_id is passed in the form
//     $template_ids = $_POST['template_id'];
//     $column_names = $_POST['column_name'];
//     $x_positions = $_POST['x_position'];
//     $y_positions = $_POST['y_position'];
//     $widths = $_POST['width'];
//     $heights = $_POST['height'];
//     $cell_types = $_POST['cell_type'];
//     $font_sizes = $_POST['font_size'];
//     $font_types = $_POST['font_type'];
//     $font_colors = $_POST['font_color'];
//     $font_styles = $_POST['font_style'];

//     $stmt = $conn->prepare("INSERT INTO field_mappings (project_id, template_id, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

//     foreach ($template_ids as $template_id) {
//         // Skip empty template IDs
//         if (empty($template_id)) {
//             continue;
//         }

//         foreach ($column_names[$template_id] as $index => $column_name) {
//             $x_position = $x_positions[$template_id][$index];
//             $y_position = $y_positions[$template_id][$index];
//             $width = $widths[$template_id][$index];
//             $height = $heights[$template_id][$index];
//             $cell_type = $cell_types[$template_id][$index];
//             $font_size = $font_sizes[$template_id][$index];
//             $font_type = $font_types[$template_id][$index];
//             $font_color = $font_colors[$template_id][$index];
//             $font_style = $font_styles[$template_id][$index];

//             // Log the data for debugging
//             error_log("Data: project_id=$project_id, template_id=$template_id, column_name=$column_name, x_position=$x_position, y_position=$y_position, width=$width, height=$height, cell_type=$cell_type, font_size=$font_size, font_type=$font_type, font_color=$font_color, font_style=$font_style");

//             $stmt->bind_param("iissiiisssss", $project_id, $template_id, $column_name, $x_position, $y_position, $width, $height, $cell_type, $font_size, $font_type, $font_color, $font_style);

//             if (!$stmt->execute()) {
//                 die("Error inserting field mapping: " . $stmt->error);
//             }
//         }
//     }

//     $stmt->close();
//     header("Location: dashboard.php");
//    // header("Location: view_Admit_Cards.php?project_id=$project_id&message=Field mappings saved successfully");
//    exit();
// } else {
//     echo "Invalid request method.";

// }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 


    $project_id = intval($_POST['project_id']);
    $template_id = intval($_POST['template_id']);
  $column_filter = $_POST['column_filter'] ?? '';
 
    $column_names = $_POST['column_name'];
    $x_positions = $_POST['x_position'];
    $y_positions = $_POST['y_position'];
    $widths = $_POST['width'];
    $heights = $_POST['height'];
    $cell_types = $_POST['cell_type'];
    $line_breaks = $_POST['line_break'];
    $font_sizes = $_POST['font_size'];
    $font_types = $_POST['font_type'];
    $font_colors = $_POST['font_color'];
    $font_styles = $_POST['font_style'];
    $field_ids = $_POST['field_id'] ?? []; // existing record IDs
    $barcode_based = $_POST['barcode_based'] ?? ''; 

  echo "<pre>";
  

    if (!empty($_POST['deleted_fields'])) {
  
    foreach ($_POST['deleted_fields'] as $fieldId) {
        $fieldId = intval($fieldId);
        // Use your correct table name here
        $stmt = $conn->prepare("DELETE FROM field_mappings WHERE id = ?");
        $stmt->execute([$fieldId]);
    }
}


// $insertBackup = $conn->prepare("INSERT INTO field_mappings_backup (project_id, template_id, column_based, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
// $insertBackup->bind_param("iissiiissssss", $project_id, $template_id, $column_filter, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
// $insertBackup->execute();
// $insertBackup->close();

    // foreach ($column_names as $template_id => $columns) {
  
    //     foreach ($columns as $index => $column_name) {
            
   
    //         $x = $x_positions[$template_id][$index];
    //         $y = $y_positions[$template_id][$index];
    //         $w = $widths[$template_id][$index];
    //         $h = $heights[$template_id][$index];
    //         $cell = $cell_types[$template_id][$index];
    //         $font_size = $font_sizes[$template_id][$index];
    //         $font_type = $font_types[$template_id][$index];
    //         $font_color = $font_colors[$template_id][$index];
    //         $font_style = $font_styles[$template_id][$index];

    //         $field_id = $field_ids[$template_id][$index] ?? null;

    //         if (!empty($field_id) && is_numeric($field_id)) {
      
    //             // Update existing record
    //             $stmt = $conn->prepare("UPDATE field_mappings 
    //                 SET column_name=?, x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? 
    //                 WHERE id=? AND template_id=?");
    //             $stmt->bind_param("siiissssssii", $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $field_id, $template_id);
    //         } else {
    //             // Insert new record
    //             $stmt = $conn->prepare("INSERT INTO field_mappings 
    //                 (project_id, template_id, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) 
    //                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    //             $stmt->bind_param("iisiiissssss", $project_id, $template_id, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
    //        }

    //         if (!$stmt->execute()) {
    //         echo "❌ Error inserting/updating field: $column_name for template ID $template_id<br>";
    //         echo "Error: " . $stmt->error;
    //         $stmt->close();
    //         exit; // stop and do not redirect
    //     }
         
    //     $stmt->close();
    //     }
      
    // }
    //  foreach ($column_names as $template_id => $columns) {
    //     foreach ($columns as $index => $column_name) {
    //         $x = $x_positions[$template_id][$index];
    //         $y = $y_positions[$template_id][$index];
    //         $w = $widths[$template_id][$index];
    //         $h = $heights[$template_id][$index];
    //         $cell = $cell_types[$template_id][$index];
    //         $font_size = $font_sizes[$template_id][$index];
    //         $font_type = $font_types[$template_id][$index];
    //         $font_color = $font_colors[$template_id][$index];
    //         $font_style = $font_styles[$template_id][$index];
    //         $field_id = $field_ids[$template_id][$index] ?? null;
        
          
    //         // Check if this field already exists
    //         /* $check = $conn->prepare("SELECT id FROM field_mappings WHERE project_id=? AND template_id=? " );
    //         $check->bind_param("ii", $project_id, $template_id); */
    //         $check = $conn->prepare("SELECT id FROM field_mappings 
    //             WHERE project_id = ? AND template_id = ? AND column_name = ? AND x_position = ? AND y_position = ?");
    //         $check->bind_param("iisii", $project_id, $template_id, $column_name, $x, $y);
    //         $check->execute();
    //         $check->store_result();
          
 
          
    //         if ($check->num_rows > 0) {
                
    //             // Update existing record
    //             $check->bind_result($existing_id);
    //             $check->fetch();
    //             $stmt = $conn->prepare("UPDATE field_mappings 
    //                 SET x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? 
    //                 WHERE id=?");
    //             $stmt->bind_param("iiissssssi", $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $existing_id);
    //         } else {

             
    //             // Insert new record
    //             $stmt = $conn->prepare("INSERT INTO field_mappings 
    //                 (project_id, template_id,column_based, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) 
    //                 VALUES (?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    //             $stmt->bind_param("iissiiissssss", $project_id, $template_id,$column_filter, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
              
    //         }
   
    //         $check->close();

    //         if (!$stmt->execute()) {
    //             echo "❌ Error saving field: $column_name (Template ID: $template_id)<br>Error: " . $stmt->error;
    //             $stmt->close();
    //             exit;
    //         }

    //         $stmt->close();
    //     }
    // }
    foreach ($column_names as $template_id => $columns) {
    foreach ($columns as $index => $column_name) {
        $x = $x_positions[$template_id][$index];
        $y = $y_positions[$template_id][$index];
        $w = $widths[$template_id][$index];
        $h = $heights[$template_id][$index];
        $cell = $cell_types[$template_id][$index];
        $font_size = $font_sizes[$template_id][$index];
        $font_type = $font_types[$template_id][$index];
        $font_color = $font_colors[$template_id][$index];
        $font_style = $font_styles[$template_id][$index];
        $line_break = $line_breaks[$template_id][$index];
        $field_id = $field_ids[$template_id][$index] ?? null;
           
       
        // $check = $conn->prepare("SELECT id FROM field_mappings 
        //     WHERE project_id = ? AND template_id = ? AND column_name = ? AND x_position = ? AND y_position = ?");
        // $check->bind_param("iisii", $project_id, $template_id, $column_name, $x, $y);
        // $check->execute();
        // $check->store_result();
     
     
        // if ($check->num_rows > 0) {
          
        //     $check->bind_result($existing_id);
        //     $check->fetch();
        //     $stmt = $conn->prepare("UPDATE field_mappings 
        //         SET width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? 
        //         WHERE id=?");
        //     // Corrected line 218:
        //     $stmt->bind_param("iisssssi", $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $existing_id);
        // } else {
           
        //     $stmt = $conn->prepare("INSERT INTO field_mappings 
        //         (project_id, template_id, column_based, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) 
        //         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        //     // Ensure $column_filter is defined somewhere before this loop.
        //     $stmt->bind_param("iissiiissssss", $project_id, $template_id, $column_filter, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
        // }

        // $check->close();

 
        if ($field_id && is_numeric($field_id)) {
            
            $updatedAt = date('Y-m-d H:i:s');
            $edited_user_id = $_SESSION['user_id'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            
            // 1. Get columns from main table
            $columnsResult = $conn->query("SHOW COLUMNS FROM field_mappings");
            $filedcolumns = [];
            while ($col = $columnsResult->fetch_assoc()) {
                if ($col['Field'] !== 'id') { // skip primary key
                    $filedcolumns[] = $col['Field'];
                }
            }

           
            
            // 2. Add backup tracking columns
          
            $filedcolumns[] = 'edited_user_id';
            $filedcolumns[] = 'ip_address';
            $filedcolumns[] = 'Filed_id';
            
            // 3. Build column list and placeholders
            $filedcolumns_list = implode(", ", $filedcolumns);
            $placeholders = implode(", ", array_fill(0, count($filedcolumns), '?'));
            
            // 4. Fetch data from main table
            $dataSql = "SELECT * FROM field_mappings WHERE id = ?";
            $dataStmt = $conn->prepare($dataSql);
            $dataStmt->bind_param("i", $field_id);
            $dataStmt->execute();
            $data = $dataStmt->get_result()->fetch_assoc();
            $dataStmt->close();
            
            if (!$data) {
                die("Record not found.");
            }
            
            // 5. Build values array dynamically
            $filedvalues = [];
            foreach ($filedcolumns as $col) {
                if ($col === 'edited_user_id') {
                    $filedvalues[] = $edited_user_id;
                } elseif ($col === 'ip_address') {
                    $filedvalues[] = $ip_address;
                } elseif ($col === 'Filed_id') {
                    $filedvalues[] = $field_id;
                } else {
                    $filedvalues[] = $data[$col] ?? null;
                }
            }
            
            // 6. Prepare insert
            $types = str_repeat('s', count($filedcolumns)); // all strings; you can adjust if needed
            $backupSql = "INSERT INTO field_mapping_edited ($filedcolumns_list) VALUES ($placeholders)";
            $backupStmt = $conn->prepare($backupSql);
            $backupStmt->bind_param($types, ...$filedvalues);
            $backupStmt->execute();
            $backupStmt->close();
            

            // $backupfileds = $conn->prepare("SELECT * FROM field_mappings WHERE id = ?");
            // $backupfileds->bind_param("i", $field_id);
            // $backupfileds->execute();
            // $backupfileds_result = $backupfileds->get_result();
            // $backupfileds_row = $backupfileds_result->fetch_assoc();

           

        //     echo $updatedAt;
        //     echo "update";
       
        //   //   If field ID exists, update directly
        //                 $stmt = $conn->prepare("UPDATE field_mappings 
        //             SET column_name=?, x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? ,line_break = ?,updated_at =? ,updated_by = ?
        //             WHERE id=?");
        //     $stmt->bind_param("siiiisssssiiss", $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $line_break, $field_id,$updatedAt,$_SESSION['user_name']);
        //     $action = "Updated value for: " . $column_name;

        // logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, $action);
          
    $stmt = $conn->prepare("UPDATE field_mappings 
        SET column_name=?, x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? ,line_break = ?, updated_at = ?, updated_by = ?
        WHERE id=?");

   $stmt->bind_param(
    "siiiisssssssis",  // ✅ Now has 14 characters
    $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type,
    $font_color, $font_style, $line_break, $updatedAt, $_SESSION['user_name'], $field_id
   );



    $action = "Updated value for: " . $column_name;
    logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, $action);

    
      } else {
     
                $createdAt = date('Y-m-d H:i:s');
            
                            $stmt = $conn->prepare("INSERT INTO field_mappings 
                    (project_id, template_id, column_based, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style,line_break,created_at,created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)");
                $stmt->bind_param("iissiiissssssiss", $project_id, $template_id, $column_filter, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $line_break,$createdAt,$_SESSION['user_name']);
                $action = "Inserted value for: " . $column_name;
        $field_id_value =  $field_id;
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, $template_id, $action);


        
        }

        if (!$stmt->execute()) {
            echo "❌ Error saving field: $column_name (Template ID: $template_id)<br>Error: " . $stmt->error;
            $stmt->close();
            exit;
        }

        $stmt->close();
    }
}

   $_SESSION['success_message'] = "✅ Field mappings saved successfully!";
// header("Location: generate_admit_card.php?project_id=$project_id");
header("Location: generate_admit_card.php?project_id=$project_id&filter_column=" . urlencode($column_filter));

exit;

} else {
    echo "❌ Invalid request method.";
}


