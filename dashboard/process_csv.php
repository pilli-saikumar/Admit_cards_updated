<?php 
 include("../includes/header.php");
 include("../db_connect.php");
 include("../includes/function.php");


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $project_id = $_POST['project_id'];

        $column_based = $_POST['column_based'] ?? 'all';
  
    if (empty($project_id)) {
        $_SESSION['flash_error'] = "Project ID is required.";
         header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }

    if (!empty($column_based)) {
        $update_query = "UPDATE projects SET column_based = '$column_based' WHERE id = $project_id";
        if (!$conn->query($update_query)) {
            $_SESSION['flash_error'] = "❌ Error updating project columns: " . $conn->error;
             header("Location: upload_csv.php?project_id=" . urlencode($project_id));
            exit;
        } else {
            $_SESSION['flash_success'] = "✅ Column setting updated successfully.";
        }
    } else {
        $_SESSION['flash_error'] = "Column basis selection is required.";
        header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "❌ Failed to upload CSV file.";
    header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }

  $allowed_mime_types = [
    'text/plain',
    'text/csv',
    'application/csv',
    'application/vnd.ms-excel',
    'application/vnd.msexcel',
    'text/comma-separated-values',
    'application/octet-stream' // some servers send this
];
    $file_mime = mime_content_type($_FILES['csv_file']['tmp_name']);
    $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_mime, $allowed_mime_types) || $file_ext !== 'csv') {
        $_SESSION['flash_error'] = "❌ Please upload a valid .csv file only.";
        header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }

    if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "❌ Failed to upload CSV file.";
        header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }

    // Read CSV
    $csv = fopen($_FILES['csv_file']['tmp_name'], 'r');
  
    $firstLine = fgets($csv);
     rewind($csv);

        $delimiter = (strpos($firstLine, ";") !== false) ? ";" : ",";

        $headers = fgetcsv($csv, 0, $delimiter);
        $all_rows = [];
        while (($row = fgetcsv($csv, 0, $delimiter)) !== false) {
            $all_rows[] = $row;
        }
    fclose($csv);

    if (empty($headers) || empty($all_rows)) {
        $_SESSION['flash_error'] = "CSV file appears empty or improperly formatted.";
        header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }
   
     $db_columns_result = $conn->query("SHOW COLUMNS FROM admit_card_records");
    $db_columns = [];
    if ($db_columns_result->num_rows > 0) {
        while ($col = $db_columns_result->fetch_assoc()) {
            $db_columns[] = $col['Field'];
        }
    } else {
        $_SESSION['flash_error'] = "Could not fetch database columns.";
         header("Location: upload_csv.php?project_id=" . urlencode($project_id));
        exit;
    }
    
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $project_id = $_POST['project_id'];

                // Get project slug
                $slug_res = $conn->query("SELECT name FROM projects WHERE id = $project_id");
                $slug_row = $slug_res->fetch_assoc();
                $project_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $slug_row['name']));

                // Set up folder
                $upload_dir = dirname(__DIR__) . "/$project_slug/csv/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Save file
                $csv_filename = 'data_' . time() . '.csv';
                $csv_full_path = $upload_dir . $csv_filename;
                $csv_relative_path = "$project_slug/csv/$csv_filename";

                if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $csv_full_path)) {
                    $_SESSION['flash_error'] = "❌ Failed to store CSV.";
                    header("Location: upload_csv.php?project_id=$project_id");
                    exit;
                }
 
                            // After inserting into projects
            $action = "Uploaded CSV File: " . $project_slug;
            logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
                            // Optionally store the path in session or DB for later use
                            $_SESSION['uploaded_csv_path'] = $csv_relative_path;
                        }
            }else {
                
            }

?>
<div class="ml-64  p-5">
    <a href="javascript:history.back()" class="back-button">← Back</a>
<?php if (!empty($headers) && !empty($all_rows)): ?>
<form method="post" action="import.php">
    <h3> Map CSV Headers to DB Columns</h3>
    <table border="1" cellpadding="6">
        <tr>
            <th>CSV Header</th>
            <th>Map to DB Column</th>
        </tr>
        <?php foreach ($headers as $i => $header):
   
            
            
            ?>
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

   

    <!-- <input type="hidden" name="csv_data" value='<?= htmlspecialchars(json_encode($all_rows)) ?>'> -->
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <input type="hidden" name="csv_path" value="<?= $csv_relative_path ?>">
    <br>
    <button type="submit" class="btn btn-success">✅ Continue to Import</button>
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

  // Initialize on page load
  updateMappedCount();
</script>
</form>
<?php endif; ?>
</div>
<script>
  setTimeout(function() {
    var errBox = document.getElementById('file-success');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds
</script>