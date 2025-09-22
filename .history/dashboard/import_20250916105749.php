<?php 
session_start();
ob_start(); 
set_time_limit(0); // Allow script to run longer if needed

 include("../db_connect.php");


$mapping = $_POST['mapping'] ?? [];
$headers = $_POST['csv_headers'] ?? [];
$all_rows = json_decode($_POST['csv_data'] ?? '[]', true);
$project_id = $_POST['project_id'] ?? 0;
$csv_path = $_POST['csv_path'] ?? '';


// if (empty($mapping) || empty($headers) || empty($all_rows)) {
//     $_SESSION['flash_error'] = "Invalid data provided for import.";
//     header("Location: upload_csv.php");
//     exit;
// }
if (empty($mapping) || empty($headers) || empty($csv_path)) {
    $_SESSION['flash_error'] = "Invalid data provided for import.";
    header("Location: upload_csv.php?project_id=" . urlencode($project_id));
    exit;
}


// ✅ Read the CSV file again from saved location
$all_rows = [];
$full_path = dirname(__DIR__) . "/" . $csv_path;

if (!file_exists($full_path)) {
    $_SESSION['flash_error'] = "CSV file not found on server.";
    header("Location: upload_csv.php?project_id=" . urlencode($project_id));
    exit;
}

$csv = fopen($full_path, "r");
$firstLine = fgets($csv);
rewind($csv);

$delimiter = (strpos($firstLine, ";") !== false) ? ";" : ",";
$csv_headers = fgetcsv($csv, 0, $delimiter); // skip headers
while (($row = fgetcsv($csv, 0, $delimiter)) !== false) {
    if (count(array_filter($row)) > 0) {
        $all_rows[] = $row;
    }
}
fclose($csv);


if (empty($all_rows)) {
    $_SESSION['flash_error'] = "CSV file appears empty.";
    header("Location: upload_csv.php?project_id=" . urlencode($project_id));
    exit;
}
$totalRows = count($all_rows);
$importedCount = 0;
$skippedCount = 0;
$updatedCount = 0;

$date_fields = ['dob', 'exam_date', 'trail_date', 'dv_date']; // Update as per your columns

foreach ($all_rows as $row) {
    $data = [];

    // Build $data array from Excel columns
    foreach ($mapping as $csv_idx => $db_col) {
        if ($db_col !== '') {
            $data[$db_col] = $row[$csv_idx] ?? null;
        }
    }

    $data['project_id'] = $project_id;

    // Convert date fields
    foreach ($data as $col => $val) {
        if (in_array($col, $date_fields) && !empty($val)) {
            $converted = null;
            $formats = ['d-m-Y', 'd.m.Y', 'd/m/Y', 'Y-m-d', 'Y.m.d', 'Y/m/d', 'd-m-Y H:i:s', 'd.m.Y H:i:s', 'd/m/Y H:i:s'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $val);
                if ($date && $date->format($format) === $val) {
                    $converted = $date->format('Y-m-d');
                    break;
                }
            }
            $data[$col] = $converted ?? null;
        }
    }

    // Generate row_hash (based on all fields except row_hash itself)
    $hash_input = '';
    foreach ($data as $key => $val) {
        $hash_input .= trim($val) . '|';
    }
    $row_hash = sha1($hash_input);
  
    $data['row_hash'] = $row_hash;

//    // Step 1: Check if record exists for this registration_number + project_id
//     $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
//     $check_stmt->bind_param('si', $data['registration_number'], $data['project_id']);
//     $check_stmt->execute();
//     $check_stmt->store_result();
//     $check_stmt->bind_result($existing_id, $existing_hash);
    
// //     $check_stmt = $conn->prepare("SELECT id FROM admit_card_records WHERE row_hash = ?");
// // $check_stmt->bind_param('s', $row_hash);
// // $check_stmt->execute();
// // $check_stmt->store_result();

//     if ($check_stmt->num_rows > 0) {
//         echo "Record exists for registration_number: {$data['registration_number']}, checking for updates...\n"."<br>";

//         $check_stmt->fetch();
//         if ($existing_hash !== $row_hash) {
//             // Data changed → UPDATE record
//             $update_cols = [];
//             $update_values = [];

//             foreach ($data as $col => $val) {
//                 if (!in_array($col, ['registration_number', 'project_id'])) {
//                     $update_cols[] = "$col = ?";
//                     $update_values[] = $val;
//                 }
//             }

//             $update_values[] = $existing_id;
//             $update_sql = "UPDATE admit_card_records SET " . implode(', ', $update_cols) . " WHERE id = ?";
//             $update_stmt = $conn->prepare($update_sql);

//             $types = str_repeat('s', count($update_values) - 1) . 'i';
//             $update_stmt->bind_param($types, ...$update_values);
//             $update_stmt->execute();

//             if ($update_stmt->affected_rows >= 0) {
//                 $updatedCount++;
//             }

//             $update_stmt->close();
//         } else {
//             // No change → skip
//             $skippedCount++;
//         }
//     } else {
//         echo "New record for registration_number: {$data['registration_number']}, inserting...\n"."<br>";
//         // Record not found → INSERT
//         $columns = implode(',', array_keys($data));
//         $placeholders = implode(',', array_fill(0, count($data), '?'));
//         $types = str_repeat('s', count($data));

//         $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
//         $insert_stmt = $conn->prepare($insert_sql);
//         if ($insert_stmt === false) {
//             die("Prepare failed: " . $conn->error);
//         }

//         $insert_stmt->bind_param($types, ...array_values($data));
//         $insert_stmt->execute();

//         if ($insert_stmt->affected_rows > 0) {
//             $importedCount++;
//         }

//         if ($insert_stmt->error) {
//             error_log("Error executing statement: " . $insert_stmt->error);
//             $_SESSION['flash_error'] = "❌ Failed to import some rows.";
//             header("Location: upload_csv.php");
//             exit;
//         }

//         $insert_stmt->close();
//     }

//     $check_stmt->close();
// }
$reg = $data['registration_number'];
    $pid = $data['project_id'];

    // 1. Check for existing record by reg no + project_id
    // $check_stmt = $conn->prepare("SELECT id, row_hash FROM admit_card_records WHERE registration_number = ? AND project_id = ?");
    // $check_stmt->bind_param('si', $reg, $pid);
    // $check_stmt->execute();
    // $check_stmt->store_result();
    // $check_stmt->bind_result($existing_id, $existing_hash);
$check_stmt = $conn->prepare("SELECT id FROM admit_card_records WHERE row_hash = ?");
$check_stmt->bind_param('s', $row_hash);
$check_stmt->execute();
$check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
         $check_stmt->bind_result($existing_id, $existing_hash);
        $check_stmt->fetch();
        if ($existing_hash === $row_hash) {
            // ✅ Same data, skip
            $skippedCount++;
        } else {
            // 🔄 Different data, update
            $updateCols = array_keys($data);
            $updateSet = implode(', ', array_map(fn($col) => "$col = ?", $updateCols));
            $types = str_repeat('s', count($data));

            $update_sql = "UPDATE admit_card_records SET $updateSet WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $types .= 'i';
            $params = [...array_values($data), $existing_id];
            $update_stmt->bind_param($types, ...$params);
            $update_stmt->execute();

            if ($update_stmt->affected_rows > 0) {
                $updatedCount++;
            }

            $update_stmt->close();
        }
    } else {
        // ➕ New record → insert
        $insert_data = $data;
        if (isset($insert_data['id'])) {
            unset($insert_data['id']);
        }
        $columns = implode(',', array_keys($insert_data));
        $placeholders = implode(',', array_fill(0, count($insert_data), '?'));
        $types = str_repeat('s', count($insert_data));

        $insert_sql = "INSERT INTO admit_card_records ($columns) VALUES ($placeholders)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param($types, ...array_values($insert_data));
        $insert_stmt->execute();

        if ($insert_stmt->affected_rows > 0) {
            $importedCount++;
        }

        $insert_stmt->close();
    }

    $check_stmt->close();
}

// ✅ Success Summary
$_SESSION['success_message'] = "✅ Imported: $importedCount, Updated: $updatedCount, Skipped: $skippedCount of $totalRows rows.";
// header("Location: dashboard.php");
header("Location: upload_photos.php?project_id=$project_id");
exit;