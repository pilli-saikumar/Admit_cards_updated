<?php 
 include("../db_connect.php");

if($_SERVER['REQUEST_METHOD'] === 'POST') {
   

    $project_id = intval($_POST['project_id']);
    $field_ids = $_POST['field_id'];
    $template_ids = $_POST['template_id'];
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

    // Prepare queries
    $updateStmt = $conn->prepare("UPDATE field_mappings 
        SET column_name=?, x_position=?, y_position=?, width=?, height=?, cell_type=?, font_size=?, font_type=?, font_color=?, font_style=? 
        WHERE id=? AND template_id=?");

    $insertStmt = $conn->prepare("INSERT INTO field_mappings 
        (project_id, template_id, column_name, x_position, y_position, width, height, cell_type, font_size, font_type, font_color, font_style) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($field_ids as $index => $field_id) {
        $template_id = intval($template_ids[$index]);

        if (strpos($field_id, 'new_') === false) {
            // Existing field - update
            $column_name = $column_names[$template_id][$index] ?? '';
            $x = $x_positions[$template_id][$index] ?? 0;
            $y = $y_positions[$template_id][$index] ?? 0;
            $w = $widths[$template_id][$index] ?? 0;
            $h = $heights[$template_id][$index] ?? 0;
            $cell = $cell_types[$template_id][$index] ?? 'Cell';
            $font_size = $font_sizes[$template_id][$index] ?? 12;
            $font_type = $font_types[$template_id][$index] ?? 'Arial';
            $font_color = $font_colors[$template_id][$index] ?? '#000000';
            $font_style = $font_styles[$template_id][$index] ?? 'normal';

            $updateStmt->bind_param("siiissssssii", $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style, $field_id, $template_id);
            if (!$updateStmt->execute()) {
                die("Update Error: " . $updateStmt->error);
            }
        } else {
            // New field - insert
            $column_name = $column_names[$field_id] ?? '';
            $x = $x_positions[$field_id] ?? 0;
            $y = $y_positions[$field_id] ?? 0;
            $w = $widths[$field_id] ?? 0;
            $h = $heights[$field_id] ?? 0;
            $cell = $cell_types[$field_id] ?? 'Cell';
            $font_size = $font_sizes[$field_id] ?? 12;
            $font_type = $font_types[$field_id] ?? 'Arial';
            $font_color = $font_colors[$field_id] ?? '#000000';
            $font_style = $font_styles[$field_id] ?? 'normal';

            $insertStmt->bind_param("iissiiisssss", $project_id, $template_id, $column_name, $x, $y, $w, $h, $cell, $font_size, $font_type, $font_color, $font_style);
            if (!$insertStmt->execute()) {
                die("Insert Error: " . $insertStmt->error);
            }
        }
    }

    // Close statements
    $updateStmt->close();
    $insertStmt->close();

    // Redirect or respond
    header("Location: dashboard.php");
    exit();
} else {
    echo "Invalid request.";
}
?>