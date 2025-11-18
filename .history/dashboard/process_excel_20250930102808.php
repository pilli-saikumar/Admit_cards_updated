<?php
include("../includes/header.php");
include("../db_connect.php");
include("../includes/function.php");

// Require PhpSpreadsheet (install via: composer require phpoffice/phpspreadsheet)
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    $_SESSION['flash_error'] = "PhpSpreadsheet not found. Please run: composer require phpoffice/phpspreadsheet";
    header("Location: upload_excel.php?project_id=" . urlencode($_POST['project_id'] ?? ''));
    exit;
}
require $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $project_id = $_POST['project_id'] ?? '';
    $column_based = $_POST['column_based'] ?? 'all';

    if (empty($project_id)) {
        $_SESSION['flash_error'] = "Project ID is required.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Update column_based on project
    if (!empty($column_based)) {
        $update_query = "UPDATE projects SET column_based = '" . $conn->real_escape_string($column_based) . "' WHERE id = " . intval($project_id);
        if (!$conn->query($update_query)) {
            $_SESSION['flash_error'] = "❌ Error updating project columns: " . $conn->error;
            header("Location: upload_excel.php?project_id=" . urlencode($project_id));
            exit;
        } else {
            $_SESSION['flash_success'] = "✅ Column setting updated successfully.";
        }
    } else {
        $_SESSION['flash_error'] = "Column basis selection is required.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "❌ Failed to upload Excel file.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    $allowed_ext = ['xlsx', 'xls'];
    $file_ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_ext, true)) {
        $_SESSION['flash_error'] = "❌ Please upload a valid .xlsx or .xls file only.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Load spreadsheet
    try {
        $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "❌ Unable to read Excel: " . $e->getMessage();
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Take first worksheet
    /** @var Worksheet $sheet */
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Extract headers (row 1)
    $headers = [];
    if ($highestRow >= 1) {
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $val = $sheet->getCellByColumnAndRow($col, 1)->getCalculatedValue();
            $headers[] = is_null($val) ? '' : trim((string)$val);
        }
    }

    // Extract data rows (from row 2)
    $all_rows = [];
    for ($row = 2; $row <= $highestRow; $row++) {
        $rowData = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $val = $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
            $rowData[] = is_null($val) ? '' : (is_scalar($val) ? (string)$val : json_encode($val));
        }
        // skip completely empty rows
        if (count(array_filter($rowData, fn($v) => $v !== '' && $v !== null)) > 0) {
            $all_rows[] = $rowData;
        }
    }

    if (empty(array_filter($headers)) || empty($all_rows)) {
        $_SESSION['flash_error'] = "Excel file appears empty or improperly formatted.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Prepare project slug and storage dir (reuse csv dir to stay compatible with import.php)
    $slug_res = $conn->query("SELECT name FROM projects WHERE id = " . intval($project_id));
    $slug_row = $slug_res ? $slug_res->fetch_assoc() : null;
    $project_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $slug_row['name'] ?? ("project_" . $project_id)));

    $upload_dir = dirname(__DIR__) . "/$project_slug/csv/"; // keep same as CSV flow
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Save a CSV converted from Excel so existing import.php can consume it
    $csv_filename = 'data_' . time() . '.csv';
    $csv_full_path = $upload_dir . $csv_filename;
    $csv_relative_path = "$project_slug/csv/$csv_filename";

    $out = fopen($csv_full_path, 'w');
    if ($out === false) {
        $_SESSION['flash_error'] = "❌ Failed to create CSV from Excel.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }
    // write headers and rows
    fputcsv($out, $headers, ',');
    foreach ($all_rows as $row) {
        fputcsv($out, $row, ',');
    }
    fclose($out);

    // Log and stash path in session (same keys as CSV flow where possible)
    $action = "Uploaded Excel File (converted to CSV): " . $project_slug;
    if (isset($_SESSION['user_name'])) {
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'] ?? '', $project_id, 0, $action);
    }
    $_SESSION['uploaded_csv_path'] = $csv_relative_path;

    // Fetch DB columns for mapping
    $db_columns_result = $conn->query("SHOW COLUMNS FROM admit_card_records");
    $db_columns = [];
    if ($db_columns_result && $db_columns_result->num_rows > 0) {
        while ($col = $db_columns_result->fetch_assoc()) {
            $db_columns[] = $col['Field'];
        }
    } else {
        $_SESSION['flash_error'] = "Could not fetch database columns.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }
} else {
    $_SESSION['flash_error'] = "Invalid request.";
    header("Location: upload_excel.php?project_id=" . urlencode($_POST['project_id'] ?? ''));
    exit;
}
?>
<div class="ml-64  p-5">
    <a href="javascript:history.back()" class="back-button">← Back</a>
<?php if (!empty($headers) && !empty($all_rows)): ?>
<form method="post" action="import.php">
    <h3> Map Excel Headers to DB Columns</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>Excel Header</th>
            <th>Map to DB Column</th>
        </tr>
        <?php foreach ($headers as $i => $header): ?>
            <tr>
                <td><?= htmlspecialchars($header) ?></td>
                <td>
                    <select name="mapping[<?= $i ?>]">
                        <option value="">-- Ignore --</option>
                        <?php foreach ($db_columns as $col): ?>
                            <option value="<?= $col ?>" <?= strtolower(trim($header)) === strtolower(trim($col)) ? 'selected' : '' ?>>
                                <?= $col ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="csv_headers[<?= $i ?>]" value="<?= htmlspecialchars($header) ?>">
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p>
      Mapped columns: <span id="mappedCount">0</span> /
      <?= count($headers) ?>
    </p>

    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
    <input type="hidden" name="csv_path" value="<?= htmlspecialchars($csv_relative_path) ?>">
    <br>
    <button type="submit" class="btn btn-success">✅ Continue to Import</button>
    <script>
      const mappingSelects = document.querySelectorAll('select[name^="mapping"]');
      const mappedCountEl = document.getElementById('mappedCount');
      function updateMappedCount() {
        const filled = [...mappingSelects].filter(s => s.value.trim() !== '').length;
        mappedCountEl.textContent = filled;
      }
      mappingSelects.forEach(s => s.addEventListener('change', updateMappedCount));
      updateMappedCount();
    </script>
</form>
<?php endif; ?>
</div>
<script>
  setTimeout(function() {
    var errBox = document.getElementById('file-success');
    if (errBox) { errBox.style.display = 'none'; }
  }, 5000);
</script>
