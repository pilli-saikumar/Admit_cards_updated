<?php
{{ ... }}
    // Take first worksheet (active)
    /** @var Worksheet $sheet */
    $sheet = $spreadsheet->getActiveSheet();
    if (!$sheet) {
        $_SESSION['flash_error'] = "❌ No active worksheet found in the Excel file.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Read entire sheet to array for robust parsing
    // toArray(null, calculateFormulas=true, formatData=true, returnCellRef=false)
    $rows = $sheet->toArray(null, true, true, false);

    if (empty($rows)) {
        $_SESSION['flash_error'] = "Excel sheet is empty.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Extract headers from the first row
    $rawHeaders = isset($rows[0]) ? $rows[0] : [];
    $headers = array_map('trim', array_filter($rawHeaders, function($v) {
        return $v !== null && $v !== '';
    }));

    // Extract data rows starting from row index 1
    $all_rows = array_filter(array_slice($rows, 1), function($r) {
        return count(array_filter($r, function($v) {
            return $v !== null && $v !== '';
        })) > 0;
    });

    if (empty($headers) || empty($all_rows)) {
        $_SESSION['flash_error'] = "Excel file appears empty or improperly formatted.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }
{{ ... }}
?>
