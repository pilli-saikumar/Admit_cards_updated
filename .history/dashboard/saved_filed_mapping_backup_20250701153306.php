<?php 

 include("../db_connect.php");


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
//    // header("Location: view_admit_cards.php?project_id=$project_id&message=Field mappings saved successfully");
//    exit();
// } else {
//     echo "Invalid request method.";
// }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {


  
   // Debugging: Print the POST data to check if it's being received correctly
       
// Debugging: Check the POST data
    $project_id = intval($_POST['project_id']);
    $template_id = intval($_POST['template_id']);

    $column_names = $_POST['column_name'];
    $x_positions = $_POST['x_position'];
    $y_positions = $_POST['y_position'];
    $widths = $_POST['width'];
    $heights = $_POST['height'];
    $cell_types = $_POST['cell_type'];
    $font_sizes = $_POST['font_size'];
    $font_types = $_POST['font_type'];
    $font_colors = $_POST['font_color'];
    $font_styles = $_POST['font_style'];
    $field_ids = $_POST['field_id'] ?? []; // existing record IDs
 
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

            $field_id = $field_ids[$template_id][$index] ?? null;

            if (!empty($field_id) && is_numeric($field_id)) {
                echo "Updating field ID: $field_id for template ID: $template_id<br>";
                die; // Debugging: Stop here to check the update logic
                // Update existing record
                $stmt = $conn->prepare("UPDATE field_mappings 
                    SET column_name=?, x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? 
                    WHERE id=? AND template_id=?");
                $stmt->bind_param("siiissssssii", $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $field_id, $template_id);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO field_mappings 
                    (project_id, template_id, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisiiissssss", $project_id, $template_id, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
            }

                if (!$stmt->execute()) {
                echo "❌ Error inserting/updating field: $column_name for template ID $template_id<br>";
                echo "Error: " . $stmt->error;
                $stmt->close();
                exit; // stop and do not redirect
            }

            $stmt->close();
        }
    }
     exit;
    $_SESSION['success_message'] = "✅ Field mappings saved successfully!";
    header("Location: dashboard.php");
    exit;

} else {
    echo "❌ Invalid request method.";
}


