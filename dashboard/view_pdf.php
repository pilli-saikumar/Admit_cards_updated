<?php
ob_start(); // Start output buffering

// Include database connection
include("../db_connect.php");

// Require the FPDF library file.
// Note: The FPDF class is typically named FPDF (all caps).
// If you are using the namespaced version from 'setasign/fpdf' Composer package,
// you would usually do:
// require_once __DIR__ . '/../vendor/autoload.php';
// use setasign\Fpdf\Fpdf;
// But given your current include path, we assume the direct FPDF class.
require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';


// --- PDF Generation Logic ---

// Get project ID from GET parameters, default to 0 if not set
$project_id = $_GET['project_id'] ?? 0;

// Validate project_id: ensure it's a number to prevent SQL injection and errors
if (!is_numeric($project_id)) {
    die("Error: Invalid project ID provided.");
}
$project_id = (int)$project_id; // Cast to integer for safety

// Fetch the template_id associated with this project.
// We are selecting from `project_fields` here, assuming a field implies a template.
// A more direct approach might be to link `projects` to `project_templates`.
$templateIdQuery = $conn->prepare("SELECT template_id FROM project_fields WHERE project_id = ? LIMIT 1");
if (!$templateIdQuery) {
    error_log("Prepare failed for template ID query: " . $conn->error);
    die("Database error. Please try again.");
}
$templateIdQuery->bind_param("i", $project_id);
$templateIdQuery->execute();
$templateIdResult = $templateIdQuery->get_result();
$templateRow = $templateIdResult->fetch_assoc();
$template_id = $templateRow['template_id'] ?? 0;
$templateIdQuery->close();

// Validate template_id
if (!is_numeric($template_id)) {
    die("Error: Invalid template ID found for this project.");
}
$template_id = (int)$template_id; // Cast to integer

// Fetch the image path for the template
$templateImageQuery = $conn->prepare("SELECT template_image_path FROM project_templates WHERE id = ? LIMIT 1");
if (!$templateImageQuery) {
    error_log("Prepare failed for template image query: " . $conn->error);
    die("Database error. Please try again.");
}
$templateImageQuery->bind_param("i", $template_id);
$templateImageQuery->execute();
$templateImageResult = $templateImageQuery->get_result();
$templateImageRow = $templateImageResult->fetch_assoc();
$templateImageFilename = $templateImageRow['template_image_path'] ?? ''; // Get just the filename
$templateImageFullPath = ''; // Initialize full path

// if ($templateImageFilename) {
//     // Construct the absolute path to the template image file
//     // Adjust this path if your 'uploads/templates' directory is in a different location relative to this script
//     $templateImageFullPath = __DIR__ . '/../uploads/templates/' . $templateImageFilename;

//     // Check if the image file actually exists and is readable
//     if (!file_exists($templateImageFullPath) || !is_readable($templateImageFullPath)) {
//         error_log("PDF Generation Error: Template image file not found or not readable at: " . $templateImageFullPath);
//         $templateImageFullPath = ''; // Clear path to prevent FPDF error if file is missing
//     }
// } else {
//     error_log("PDF Generation Warning: No template image filename found for template ID: " . $template_id);
// }


// Fetch all defined fields for the project and template (their positions, fonts, colors)
$fieldQuery = $conn->prepare("SELECT field_name, x_axis, y_axis, font_size, font_color FROM project_fields WHERE project_id = ? AND template_id = ?");
if (!$fieldQuery) {
    error_log("Prepare failed for field query: " . $conn->error);
    die("Database error. Please try again.");
}
$fieldQuery->bind_param("ii", $project_id, $template_id);
$fieldQuery->execute();
$fieldResult = $fieldQuery->get_result();
$fields = [];
while ($row = $fieldResult->fetch_assoc()) {
    $fields[] = $row; // Store each field's properties
}
$fieldQuery->close();

// Fetch the actual record data for the project (assuming JSON data)
$recordQuery = $conn->prepare("SELECT data FROM project_records WHERE project_id = ? LIMIT 1");
if (!$recordQuery) {
    error_log("Prepare failed for record query: " . $conn->error);
    die("Database error. Please try again.");
}
$recordQuery->bind_param("i", $project_id);
$recordQuery->execute();
$recordResult = $recordQuery->get_result();
$recordRow = $recordResult->fetch_assoc();
// Decode the JSON string into a PHP associative array, or an empty array if no data
$data = $recordRow ? json_decode($recordRow['data'], true) : [];
$recordQuery->close();


// Initialize FPDF: Portrait ('P'), Millimeters ('mm'), A4 size
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage(); // Add the first page

// Add the template background image to the PDF
if ($templateImageFilename) {
    // Image(file, x, y, width, height) - A4 size is 210x297mm
    $pdf->Image($templateImageFilename, 0, 0, 210, 297);
} else {
    // Display an error message on the PDF if the template image was not found
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 0, 0); // Red color
    $pdf->Text(10, 10, 'Error: Template background image could not be loaded!');
    $pdf->SetTextColor(0, 0, 0); // Reset color to black for subsequent text
}


// Loop through each field definition and print the corresponding data
foreach ($fields as $field) {
    $fieldName = $field['field_name'];
    // Get the value from the $data array using the fieldName as the key.
    // Use the null coalescing operator (??) to set an empty string if the key doesn't exist.
    $value = $data[$fieldName] ?? '';
 
    // Only print the field if its value is not empty
    if ($value !== '') {
        // Set font: Family (Arial), Style (Normal), Size
        // Cast font_size to float as FPDF expects a number
        $pdf->SetFont('Arial', '', (float)$field['font_size']);

        // Convert hex color from database to RGB array
        $rgb = hexToRGB($field['font_color']);
        
        // Set text color. If hexToRGB returned null (invalid color), default to black.
        if ($rgb && count($rgb) === 3) {
            $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        } else {
            // Fallback to black if color conversion fails
            $pdf->SetTextColor(0, 0, 0);
            error_log("PDF Generation Warning: Invalid font color hex: " . $field['font_color'] . " for field: " . $fieldName);
        }
        
        // Ensure x_axis and y_axis are floats as FPDF expects numbers for coordinates
        $x_axis = (float)$field['x_axis'];
        $y_axis = (float)$field['y_axis'];

        // Add the text to the PDF at the specified coordinates
        $pdf->Text($x_axis, $y_axis, $value);
    }
}

// Output the PDF to the browser and terminate the script
$pdf->Output();
exit;


// --- Helper Function ---

/**
 * Converts a hexadecimal color string to an RGB array.
 * @param string $hexColor The hex color string (e.g., "#RRGGBB" or "RRGGBB" or "#RGB" or "RGB").
 * @return array|null An array [R, G, B] or null if the hex format is invalid.
 */
function hexToRGB($hexColor) {
    $hex = str_replace("#", "", $hexColor); // Remove '#' if present
    
    // Handle 3-character hex (e.g., "FFF" -> "FFFFFF")
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0], 2));
        $g = hexdec(str_repeat($hex[1], 2));
        $b = hexdec(str_repeat($hex[2], 2));
    } 
    // Handle 6-character hex (e.g., "RRGGBB")
    elseif (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    } 
    // Invalid hex format
    else {
        return null; // Return null if the format is not recognized
    }
    return [$r, $g, $b];
}