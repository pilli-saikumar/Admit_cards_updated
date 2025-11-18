<?php
include("../includes/header.php");
include("../db_connect.php");

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    echo "Invalid user ID.";
    exit;
}

//$sql = "SELECT id, name, description, template_image_path FROM projects";
// $sql = "SELECT p.id, p.name, p.description, pt.template_image_path ,pt.template_name ,pt.id AS template_id
//         FROM projects p 
//         LEFT JOIN project_templates pt ON p.id = pt.project_id 
//         ORDER BY p.id ASC";
$sql = "SELECT
            p.id,
            p.name,
            p.description,p.header,
            GROUP_CONCAT(pt.template_image_path ORDER BY pt.id ASC SEPARATOR '|||') AS template_image_paths,
         GROUP_CONCAT(DISTINCT pt.columns_name ORDER BY pt.columns_name ASC SEPARATOR '|||') AS columns_name,
            GROUP_CONCAT(pt.template_name ORDER BY pt.id ASC SEPARATOR '|||') AS template_names,
            GROUP_CONCAT(pt.id ORDER BY pt.id ASC SEPARATOR '|||') AS template_ids
        FROM
            projects p
        LEFT JOIN
            project_templates pt ON p.id = pt.project_id
             WHERE
            p.is_delete = 0 AND p.created_by = $user_id
        GROUP BY
            p.id, p.name, p.description
        ORDER BY
            p.id ASC";
$result = $conn->query($sql);





?>

<head>
    <title>Projects with Templates</title>

</head>
<!-- Main Content -->
<div class="ml-60  p-5 ">
    <?php


    if (isset($_SESSION['success_message'])) {
        echo "<div id='success_message' class='alert alert-success'>" . $_SESSION['success_message'] . "</div>";
        unset($_SESSION['success_message']);
    }
    if (!empty($_SESSION['DELETE_SUCCESS'])) {
        echo "<div id='success_message' class='alert alert-success'>" . $_SESSION['DELETE_SUCCESS'] . "</div>";
        unset($_SESSION['DELETE_SUCCESS']);
    }

    ?>
    <h1 style="text-align: center;font-size :20px"><b>Projects List</b></h1>

    <table class="table table-responsive table-bordered table-sm text-sm">
   
        <thead>
            <tr>
                <th>S.no</th>
                <th>Project Name</th>
                <!-- <th>Description</th> -->
                <th>Template Name</th>
                <!-- <th>status</th> -->
                <th>User </th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sno = 1;
            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                    $projectId = $row['id'];

            ?>
                    <tr>
                        <td><?= $sno++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <!-- <td><?= htmlspecialchars($row['description']) ?></td> -->
                        <td>
                            <?php
                            $templateNames = explode('|||', $row['columns_name']);
                            if (!empty($templateNames[0])) { // Check if there are any templates
                                echo implode(' | ', array_map('htmlspecialchars', $templateNames)); // side-by-side with separator
                            } else {
                                echo 'No Template';
                            }
                            ?>
                        </td>

                        <td> <a href="users.php?project_id=<?= $projectId ?>" class="btn btn-sm"> Records</a></td>


                        <td>
                            <?php
                            $projectId = $row['id'];

                            // Check if admit card records exist for this project
                            $recordCount = $conn->query("SELECT COUNT(*) AS count FROM admit_card_records WHERE project_id = $projectId")
                                ->fetch_assoc()['count'] ?? 0;
                            $status = $recordCount > 0 ? 'Completed' : 'Pending';

                            // Get template images and IDs
                            $templateImagePaths = explode('|||', $row['template_image_paths']);
                            $templateIds = explode('|||', $row['template_ids']);
                            $hasTemplates = !empty($templateImagePaths[0]);

                            // Check if field mappings exist for the project
                            $projectHasFieldMappings = false;
                            if (!empty($templateIds[0])) {
                                $mappingCount = $conn->query("SELECT COUNT(*) AS count FROM field_mappings WHERE project_id = $projectId")
                                    ->fetch_assoc()['count'] ?? 0;
                                $projectHasFieldMappings = $mappingCount > 0;
                            }

                        

                          
                           if ($status === 'Pending' && !$hasTemplates): ?>
                                <!-- <a href="upload_csv.php?project_id=<?= $projectId ?>" class=" mr-2" style="color:4338CA"> <i
                                        class="fas fa-file-csv"></i>Upload CSV</a> -->
                                          <a href="upload_excel.php?project_id=<?= $projectId ?>" class=" mr-2" style="color:4338CA"> <i
                                        class="fas fa-file-csv"></i>Upload Exc</a>
                            <?php endif; ?>

                          
                          

                            <!-- Edit and Delete Project -->
                            <!-- <a href="generate_admit_card.php?project_id=<?= $projectId ?>" class="btn btn-primary">Set Coordinates</a> -->
                                            <!-- 
                                <a href="edit_project.php?project_id=<?= $projectId ?>" class="text-green-800 mr-2"><i class="fas fa-pencil-alt text-success"></i></a>
                                <a href="delete_template.php?project_id=<?= $projectId ?>"  class="text-danger-800 mr-2" onclick="return confirm('Are you sure you want to delete this project and its templates?');"><i class="fa-solid fa-trash"></i></a> -->
                            <a href="edit_project.php?project_id=<?= $projectId ?>" class=" mr-2" style="color:4338CA"
                                title="Edit">
                                <i class="fas fa-pencil-alt"></i>
                            </a>

                            <a href="delete_template.php?project_id=<?= $projectId ?>" class="text-red-600 mr-2"
                                title="Delete Project"
                                onclick="return confirm('Are you sure you want to delete this project and its templates?');">
                                <i class="fas fa-trash"></i>
                            </a>

                            <!-- Upload Template if none exist -->
                            <!-- <?php if (!$hasTemplates && $status === "Completed"): ?>
                                <a href="upload_template.php?project_id=<?= $projectId ?>" class="text-green-600 mr-2"
                                    title="Upload Template"> <i class="fas fa-upload"></i></a>
                            <?php endif; ?> -->
                            <a href="upload_csv.php?project_id=<?= $projectId ?>" class="text-blue-600 mr-2"
                                title="View Templates"> <i class="fas fa-eye"></i> click here to view</a>
                        </td>

                    </tr>
                <?php
                endwhile;
            else:
                ?>
                <tr>
                    <td colspan="5">No projects found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    setTimeout(function() {
        var errBox = document.getElementById('success_message');
        if (errBox) {
            errBox.style.display = 'none';
        }
    }, 5000); // 5000ms = 5 seconds
</script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

</body>

</html>