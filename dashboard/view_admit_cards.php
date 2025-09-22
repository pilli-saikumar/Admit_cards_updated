<?php
include("../includes/header.php");
include("../db_connect.php");

$project_id = intval($_GET['project_id']);

// Fetch admit card records for the project
$recordsQuery = $conn->prepare("SELECT id, first_name FROM admit_card_records WHERE project_id = ?");
$recordsQuery->bind_param("i", $project_id);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();
 // Debugging line to check fetched records

// Fetch project name
// $projectQuery = $conn->prepare("SELECT name  FROM projects WHERE id = ?");
// $projectQuery->bind_param("i", $project_id);
$projectQuery = $conn->prepare("SELECT p.id, p.name, p.description, pt.template_image_path, pt.template_name, pt.id AS template_id
    FROM projects p 
    LEFT JOIN project_templates pt ON p.id = pt.project_id 
    WHERE p.id = ?");
$projectQuery->bind_param("i", $project_id);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$project = $projectResult->fetch_assoc();
$projectName = htmlspecialchars($project['name']);
$template_id = $project['template_id']; 
?>

<div class="ml-64 p-5">
<div class="container mt-5">
    <h1>Admit Cards for Project: <?= $projectName ?></h1>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>S.No</th>
                <th>User Name</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sno = 1;
            while ($record = $recordsResult->fetch_assoc()):
                $recordId = $record['id'];
                $userName = htmlspecialchars($record['first_name']);
            ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td><?= $userName ?></td>
                    <td>
                        <a href="generate_pdf.php?record_id=<?= $recordId ?>&project_id=<?= $project_id ?>" class="btn btn-primary">View Admit Card</a>

<a href="download_pdf.php?record_id=<?= $recordId ?>&project_id=<?= $project_id ?>" class="btn btn-success">Download PDF</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div>
</body>
</html>