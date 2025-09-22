<?php 

// include("../includes/header.php");
include("../includes/candidate_header.php");
include("../db_connect.php");
$registration_number = $_SESSION["registration_number"];



//$project_id = intval($_GET['project_id']);

// Fetch admit card records for the project
// $recordsQuery = $conn->prepare("
//     SELECT acr.id, acr.first_name, acr.project_id, acr.registration_number,
//            p.name AS project_name, p.column_based
//     FROM admit_card_records acr
//     JOIN projects p ON acr.project_id = p.id
//     WHERE acr.registration_number = ? AND acr.is_admit_card_live = 1
// ");

$recordsQuery = $conn->prepare("
    SELECT acr.id, acr.first_name, acr.project_id,acr.registration_number, p.name AS project_name, p.column_based
    FROM admit_card_records acr
    JOIN projects p ON acr.project_id = p.id
    WHERE acr.registration_number = ? AND acr.is_admit_card_live = 1
");
$recordsQuery->bind_param("s", $registration_number);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();

$records = [];

while ($row = $recordsResult->fetch_assoc()) {
    $project_id = $row['project_id'];
    $column_based = $row['column_based'];

    // Step 2: Get the actual value of that column from admit_card_records
    // $colValueQuery = $conn->prepare("SELECT `$column_based` FROM admit_card_records WHERE id = ?");
    // $colValueQuery->bind_param("i", $row['id']);
    // $colValueQuery->execute();
    // $valueResult = $colValueQuery->get_result();
    // $valueRow = $valueResult->fetch_assoc();
    // $filter_value = $valueRow[$column_based] ?? '';

    // $row['filter_value'] = $filter_value;
    // $records[] = $row;

    // $colValueQuery->close();
    if ($column_based !== 'all') {
    $colValueQuery = $conn->prepare("SELECT `$column_based` FROM admit_card_records WHERE id = ?");
    $colValueQuery->bind_param("i", $row['id']);
    $colValueQuery->execute();
    $valueResult = $colValueQuery->get_result();
    $valueRow = $valueResult->fetch_assoc();
    $filter_value = $valueRow[$column_based] ?? '';

    $colValueQuery->close();
    } else {
        $filter_value = '';
    }
       $row['filter_value'] = $filter_value;
    $records[] = $row;

}

 // Debugging line to check fetched records

// Fetch project name
// $projectQuery = $conn->prepare("SELECT name  FROM projects WHERE id = ?");
// $projectQuery->bind_param("i", $project_id);
// $projectQuery = $conn->prepare("SELECT p.id, p.name, p.description, pt.template_image_path, pt.template_name, pt.id AS template_id
//     FROM projects p 
//     LEFT JOIN project_templates pt ON p.id = pt.project_id 
//     WHERE p.id = ?");
// $projectQuery->bind_param("i", $project_id);
// $projectQuery->execute();
// $projectResult = $projectQuery->get_result();
// $project = $projectResult->fetch_assoc();
// $projectName = htmlspecialchars($project['name']);
// $template_id = $project['template_id']; 
?>

<div class=" content-container">
  <div class="container mt-5">
    <?php if (!empty($records)): ?>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>S.No</th>
            <th>Name</th>
            <th>Project</th>
            <!-- <th>Filter Column</th>
            <th>Filter Value</th> -->
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php $sno = 1; foreach ($records as $record): ?>
          <tr>
            <td><?= $sno++ ?></td>
            <td><?= htmlspecialchars($record['first_name']) ?></td>
            <td><?= htmlspecialchars($record['project_name']) ?></td>
            <!-- <td><?= htmlspecialchars($record['column_based']) ?></td>
            <td><?= htmlspecialchars($record['filter_value']) ?></td> -->
            <td>
             <form action="../dashboard/generate_pdf_back.php" method="POST" target="_blank" style="display:inline;">
    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
    <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
    <input type="hidden" name="column_based" value="<?= htmlspecialchars($record['column_based']) ?>">
    <input type="hidden" name="filter_column" value="<?= htmlspecialchars($record['filter_value']) ?>">
    <button type="submit" class="btn btn-primary">View</button>
</form>

<form action="../dashboard/download_pdf.php" method="POST" style="display:inline;">
     <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
    <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
    <input type="hidden" name="column_based" value="<?= htmlspecialchars($record['column_based']) ?>">
    <input type="hidden" name="filter_column" value="<?= htmlspecialchars($record['filter_value']) ?>">
    <button type="submit" class="btn btn-success">Download</button>
</form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-warning text-center">
        Your admit card has not been generated yet.
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>