<?php
include("../includes/header.php");
include("../includes/project_process.php");
include("../db_connect.php");
include("../includes/function.php");
$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    echo "Invalid project ID.";
    exit;
}

// Fetch columns from admit_card_records table
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM admit_card_records");
// $candidate_sessting_details = $conn->query("SELECT * FROM candidate_login_settings WHERE project_id = $project_id");
// $candidate_sessting_details = $candidate_sessting_details->fetch_assoc();


if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}
$candidate_sessting_details = $conn->query("SELECT * FROM candidate_login_settings WHERE project_id = $project_id");
$candidate_sessting_details = $candidate_sessting_details->get_result();
   while ($row = $candidate_sessting_details->fetch_assoc()) {
       $candidate_sessting_details[] = $row;
   }

// $first_row_column = $candidate_sessting_details[0]['column_name'];
// $first_row_label = $candidate_sessting_details[0]['label_name'];
// $second_row_column = $candidate_sessting_details[1]['column_name'];
// $second_row_label = $candidate_sessting_details[1]['label_name'];





if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['candidate_login'])) {

    $project_id = intval($_POST['project_id']);
    $login_fields = $_POST['login_fields'] ?? [];
    $created_by = $_SESSION['user_id'] ?? null;


    $errors = [];

    if ($project_id <= 0) {
        $errors[] = 'Invalid project ID.';
    }

    // if (empty($login_fields['registration_number']) || empty($login_fields['dob'])) {
    //     $errors[] = 'Both Registration Number and Date of Birth fields are required.';
    // }

    if (empty($errors)) {
        $existingColumns = [];
        $result = $conn->prepare("SELECT column_name FROM candidate_login_settings WHERE project_id = ?");
        $result->bind_param("i", $project_id);
        $result->execute();
        $res = $result->get_result();
        while ($row = $res->fetch_assoc()) {
            $existingColumns[] = $row['column_name'];
        }
        $result->close();

        // Step 2: Delete columns not in submitted fields
        $submittedColumns = array_values($login_fields); // extract values only
        foreach ($existingColumns as $existing) {
            if (!in_array($existing, $submittedColumns)) {
                $deleteStmt = $conn->prepare("DELETE FROM candidate_login_settings WHERE project_id = ? AND column_name = ?");
                $deleteStmt->bind_param("is", $project_id, $existing);
                $deleteStmt->execute();
                $deleteStmt->close();
                echo "Deleted: $existing<br>";
            }
        }


        // Step 3: Insert/update current fields
        foreach ($login_fields as $key => $value) {
            // if (empty($value)) {
            //     $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' field cannot be empty.';
            //     continue;
            // }
            $column_name = $value['column'];
            $label_name = $value['label'];

            // Check if column exists
            $checkStmt = $conn->prepare("SELECT id FROM candidate_login_settings WHERE project_id = ? AND column_name = ?");
            $checkStmt->bind_param("is", $project_id, $column_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // Update
                $updateStmt = $conn->prepare("UPDATE candidate_login_settings SET label_name = ? WHERE project_id = ? AND column_name = ?");
                $updateStmt->bind_param("sis", $label_name, $project_id, $column_name);
                $updateStmt->execute();
                $updateStmt->close();
                echo "Updated: $column_name<br>";

                $_SESSION['success_message'] = " Candidate Login Settings updated successfully!";
                // After inserting into projects
                $action = "Updated candidate login settings for " . $label_name;
                logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
            } else {
                // Insert
                $insertStmt = $conn->prepare("INSERT INTO candidate_login_settings (project_id, column_name, label_name) VALUES (?, ?, ?)");
                $insertStmt->bind_param("iss", $project_id, $column_name, $label_name);
                $insertStmt->execute();
                $insertStmt->close();
                echo "Inserted: $column_name<br>";
                $_SESSION['success_message'] = " Candidate Login Settings created successfully!";
                // After inserting into projects
                $action = "Created candidate login settings for " . $label_name;
                logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, $action);
            }

            $checkStmt->close();
        }


        if (empty($errors)) {
            $_SESSION['success_message'] = 'Candidate login settings saved successfully!';
            header("Location: candidate_login_settings.php?project_id=" . $project_id);
            exit;
        }
    }
}
$candidate_sessting_details = $conn->query("SELECT * FROM candidate_login_settings WHERE project_id = $project_id");
//$candidate_sessting_details = $candidate_sessting_details->fetch_assoc();
$candidate_sessting_details = $candidate_sessting_details->fetch_all(MYSQLI_ASSOC);



?>

<div class="ml-64 p-1">
    <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
    <div class="container w-4/5 mx-auto">
        <?php if (isset($errors) && !empty($errors)): ?>
            <div id="file-error" class="ml-64 p-5" style="color:red">
                <?php foreach ($errors as $error): ?>
                    ❌ <?= htmlspecialchars($error) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div id="file-success" class="ml-64 p-5" style="color:green">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <p class="text-center mb-2 font-bold"><?= ProjectName($project_id) ?></p>
        <div class="grid grid-cols-6 md:grid-cols-2 gap-2">

            <div class="">
                <div class="card shadow p-3  text-sm       ">

                    <h2 class="text-center mb-4 font-bold">Candidate Login Settings</h2>
                    <form action="" method="post" class="text-sm">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">

                        <div class="mb-3">
                            <label for="registration_number" class="form-label">Login With</label>
                            <select name="login_fields[registration_number][column]"
                                id="registration_number"
                                class="form-select text-sm"

                                onchange="updateLoginLabel('registration_number')">



                                <option value="registration_number" selected>Registration Number (Default)</option>

                                <?php foreach ($columns as $col): ?>
                                    <?php if ($col !== 'registration_number' && $col !== 'dob'): ?>
                                        <option value="<?= htmlspecialchars($col) ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom Label Input -->
                        <div class="mb-3">
                            <label for="registration_number_label" class="form-label text-sm">Label Name</label>
                            <input type="text"
                                class="form-control text-sm"
                                name="login_fields[registration_number][label]"
                                id="registration_number_label"
                                value="Registration Number">
                        </div>

                        <!-- DOB Field -->
                        <div class="mb-3">
                            <label for="dob" class="form-label text-sm">DOB Field</label>
                            <select name="login_fields[dob][column]"
                                id="dob"
                                class="form-select text-sm"
                                onchange="updateLoginLabel('dob')">
                                <option value="dob" selected>Date of Birth (Default)</option>
                                <?php foreach ($columns as $col): ?>
                                    <?php if ($col !== 'registration_number' && $col !== 'dob'): ?>
                                        <option value="<?= htmlspecialchars($col) ?>">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Custom Label Input for DOB -->
                        <div class="mb-3">
                            <label for="dob_label" class="form-label text-sm">Label Name</label>
                            <input type="text"
                                class="form-control text-sm"
                                name="login_fields[dob][label]"
                                id="dob_label"
                                value="Date of Birth">
                        </div>

                        <!-- Static Captcha Field -->
                        <div class="mb-3">
                            <label class="form-label text-sm">Captcha</label>
                            <input type="text" class="form-control text-sm" value="Captcha (Default)" disabled>
                        </div>
                        <div class="alert alert-warning text-sm">
                            <small><strong>Note:</strong> Candidate login settings are used to determine which fields are displayed when candidates log in page.</small>
                        </div>

                        <!-- Submit -->
                        <div class="mt-5">
                            <button type="submit" name="candidate_login" class="btn btn-success  ">Save Settings</button>
                        </div>
                    </form>

                </div>
            </div>
            <div class="">
                <div class="card shadow p-3 text-sm">
                    <h2 class="align-items-center mb-4 font-bold text-center">Candidate View Details</h2>

                    <?php $candidate_view_details = $conn->query("SELECT * FROM candidate_view_details WHERE project_id = $project_id ORDER BY id ASC");
                    $candidate_view_details = $candidate_view_details->fetch_all(MYSQLI_ASSOC);

                    // Default fields if no data exists
                    $default_fields = [
                        ['label' => 'Name', 'column' => 'first_name'],
                        ['label' => 'Registration Number', 'column' => 'registration_number'],
                        ['label' => 'Date of Birth', 'column' => 'dob'],
                        ['label' => 'Father Name', 'column' => 'father_name'],
                        ['label' => 'Gender', 'column' => 'sex']
                    ];

                    // Use saved data if exists, otherwise use defaults
                    $fields_to_show = !empty($candidate_view_details) ? $candidate_view_details : $default_fields;
                    ?>

                    <form action="candidate_view_details.php" method="post" class="text-sm" id="viewDetailsForm">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">

                        <div id="viewFieldsContainer">
                            <?php foreach ($fields_to_show as $index => $field): ?>
                                <div class="view-field-row mb-3" data-field-index="<?= $index ?>">
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="flex-grow-1 me-2">
                                            <label class="form-label text-sm">Field Label</label>
                                            <input type="text" name="view_fields[<?= $index ?>][label]" class="form-control text-sm"
                                                value="<?= htmlspecialchars($field['label_name'] ?? $field['label']) ?>" required>
                                        </div>
                                        <div class="flex-grow-1 me-2">
                                            <label class="form-label text-sm">Database Column</label>
                                            <select name="view_fields[<?= $index ?>][column]" class="form-select text-sm" required>
                                                <option value="">Select Column</option>
                                                <?php foreach ($columns as $col): ?>
                                                    <option value="<?= htmlspecialchars($col) ?>"
                                                        <?= ($col === ($field['column_name'] ?? $field['column'])) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" class="" onclick="removeViewField(<?= $index ?>)">
                                                <i class="fa-solid fa-trash text-red-500"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="">
                            <button type="button" class="btn btn-primary btn-sm mb-1" onclick="addViewField()">+ Add Field</button>
                        </div>

                        <div class="alert alert-warning text-sm">
                            <small><strong>Note:</strong> These fields will be displayed when candidates view their details after login.</small>
                        </div>

                        <div class="">
                            <button type="submit" name="save_view_details" class="btn btn-success w-100">Save View Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>



    </div>

</div>
</div>
<!-- JavaScript for live label update -->
<script>
    // function updateLoginLabel() {
    //     const loginDropdown = document.getElementById("registration_number");
    //     const dobDropdown = document.getElementById("dob");

    //     const loginText = loginDropdown.options[loginDropdown.selectedIndex].text;
    //     const dobText = dobDropdown.options[dobDropdown.selectedIndex].text;

    //     document.getElementById("loginLabel").innerText = loginText;
    //     document.getElementById("loginInput")?.setAttribute("placeholder", "Enter " + loginText);

    //     document.getElementById("loginLabels").innerText = dobText;
    // }
    function updateLoginLabel(type) {
        let select = document.getElementById(type);
        let input = document.getElementById(type + "_label");
        input.value = select.options[select.selectedIndex].text;
    }

    let fieldIndex = <?= count($fields_to_show) ?>; // Start from current field count

    function addViewField() {
        const container = document.getElementById('viewFieldsContainer');
        const newFieldHtml = `
        <div class="view-field-row mb-3" data-field-index="${fieldIndex}">
            <div class="d-flex align-items-center mb-1">
                <div class="flex-grow-1 me-2">
                    <label class="form-label text-sm">Field Label</label>
                    <input type="text" name="view_fields[${fieldIndex}][label]" class="form-control text-sm" placeholder="Field Label" required>
                </div>
                <div class="flex-grow-1 me-2">
                    <label class="form-label text-sm">Database Column</label>   
                    <select name="view_fields[${fieldIndex}][column]" class="form-select text-sm" required>
                        <option value="">Select Column</option>
                        <?php foreach ($columns as $col): ?>
                            <option value="<?= htmlspecialchars($col) ?>">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $col))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mt-2">
                    <button type="button" class="" onclick="removeViewField(${fieldIndex})">
                        <i class="fa-solid fa-trash text-red-500"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

        container.insertAdjacentHTML('beforeend', newFieldHtml);
        fieldIndex++;
    }

    function removeViewField(index) {
        const fieldRow = document.querySelector(`[data-field-index="${index}"]`);
        if (fieldRow) {
            fieldRow.remove();
            // Reindex remaining fields to maintain sequential numbering
            reindexFields();
        }
    }

    function reindexFields() {
        const fieldRows = document.querySelectorAll('.view-field-row');
        fieldRows.forEach((row, newIndex) => {
            row.setAttribute('data-field-index', newIndex);

            // Update input names
            const labelInput = row.querySelector('input[name*="[label]"]');
            const columnSelect = row.querySelector('select[name*="[column]"]');
            const deleteBtn = row.querySelector('button[onclick*="removeViewField"]');

            if (labelInput) labelInput.name = `view_fields[${newIndex}][label]`;
            if (columnSelect) columnSelect.name = `view_fields[${newIndex}][column]`;
            if (deleteBtn) deleteBtn.setAttribute('onclick', `removeViewField(${newIndex})`);
        });

        // Update fieldIndex for next addition
        fieldIndex = fieldRows.length;
    }
</script>
</body>
<script>
    setTimeout(function() {
        var errBox = document.getElementById('file-error');
        if (errBox) {
            errBox.style.display = 'none';
        }
    }, 5000); // 5000ms = 5 seconds
</script>

</html>