<?php
// include files
sett
include("../includes/header.php");
include("../db_connect.php");
include("../includes/function.php");

// Load PhpSpreadsheet
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$headers = [];
$all_rows = [];
$excel_relative_path = '';
$project_id = $_POST['project_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $column_based = $_POST['column_based'] ?? 'all';

    if (empty($project_id)) {
        $_SESSION['flash_error'] = "Project ID is required.";
        header("Location: upload_excel.php");
        exit;
    }

    // File validation
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "❌ Failed to upload file.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    $file_ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['xls', 'xlsx'])) {
        $_SESSION['flash_error'] = "❌ Please upload a valid Excel file (.xls or .xlsx).";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Read Excel file
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (!empty($rows)) {
        $headers = array_values($rows[1]); // first row = headers
        unset($rows[1]); // remove header row
        foreach ($rows as $row) {
            $all_rows[] = array_values($row);
        }
    }


    if (empty($headers) || empty($all_rows)) {
        $_SESSION['flash_error'] = "Excel file appears empty or improperly formatted.";
        header("Location: upload_excel.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Fetch DB columns
    $db_columns_result = $conn->query("SHOW COLUMNS FROM admit_card_records");
    $db_columns = [];
    while ($col = $db_columns_result->fetch_assoc()) {
        $db_columns[] = $col['Field'];
    }

    // Save file
    $slug_res = $conn->query("SELECT name FROM projects WHERE id = $project_id");
    $slug_row = $slug_res->fetch_assoc();
    $project_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $slug_row['name']));

    $upload_dir = dirname(__DIR__) . "/$project_slug/excel/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $excel_filename = 'data_' . time() . '.' . $file_ext;
    $excel_full_path = $upload_dir . $excel_filename;
    $excel_relative_path = "$project_slug/excel/$excel_filename";

    if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $excel_full_path)) {
        $_SESSION['flash_error'] = "❌ Failed to store Excel file.";
        header("Location: upload_excel.php?project_id=$project_id");
        exit;
    }

    // Log
    $action = "Uploaded Excel File: " . $project_slug;
    logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);

    $_SESSION['uploaded_excel_path'] = $excel_relative_path;
}
?>
<div class="ml-64 p-5">
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
                    <input type="hidden" name="excel_headers[<?= $i ?>]" value="<?= htmlspecialchars($header) ?>">
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p>
            Mapped columns: <span id="mappedCount">0</span> / <?= count($headers) ?>
        </p>

        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <input type="hidden" name="excel_path" value="<?= $excel_relative_path ?>">
        <br>
        <button type="submit" class="btn btn-success">✅ Continue to Import</button>
    </form>

    <script>
      const mappingSelects = document.querySelectorAll('select[name^="mapping"]');
      const mappedCountEl = document.getElementById('mappedCount');

      function updateMappedCount() {
        const filled = [...mappingSelects].filter(s => s.value.trim() !== '').length;
        mappedCountEl.textContent = filled;
      }

      mappingSelects.forEach(s => {
        s.addEventListener('change', updateMappedCount);
      });

      updateMappedCount();
    </script>
    <?php endif; ?>
</div>
