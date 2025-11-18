<?php
include("../includes/header.php");

include("../db_connect.php");
$project_id = $_GET['project_id'] ?? 0;

?>
<div class="ml-64 p-3 mt-5">
    <a href="javascript:history.back()" class="back-button">← Back</a>
    <h2 class="text-center mb-4">Unmapped Records</h2>
    <div class="container">
    <?php $photo_column_based = $_POST['photo_column_based'] ?? 'photo_path'; ?>

<form action="" method="post" class="flex justify-center">
    <select name="photo_column_based" id="photo_column_based" onchange="this.form.submit()">
        <option value="photo_path" <?= ($photo_column_based === 'photo_path') ? 'selected' : '' ?>>PHOTO</option>
        <option value="signature_path" <?= ($photo_column_based === 'signature_path') ? 'selected' : '' ?>>SIGNATURE</option>
    </select>   
</form>

        <table class="table table-sm" id="unmappedRecordsTable" >
            <thead class=" text-center">
                <tr>
                    <th class="text-center">Name</th>
                    <th class="text-center">Registration Number</th>
                    <!-- <th class="text-center">edit</th> -->
                    
                </tr>
            </thead>
            <tbody>
                <?php
                $photo_column_based = $_POST['photo_column_based'] ?? 'photo_path';
     

             //   $result = $conn->query("SELECT id, first_name, registration_number  FROM admit_card_records WHERE project_id = $project_id AND ($photo_column_based IS NULL)");
                $stmt = $conn->prepare("SELECT DISTINCT registration_number, id, first_name FROM admit_card_records WHERE project_id = ? AND ($photo_column_based IS NULL )");
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $result = $stmt->get_result();
               
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td class="text-center"><?= $row['first_name'] ?></td>
                        <td class="text-center"><?= $row['registration_number'] ?></td>
                        <!-- <td class="text-center"><a href="edit_user.php?id=<?= $row['id'] ?>" class="btn btn-primary">Edit</a></td>                        -->
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#unmappedRecordsTable').DataTable();
    });
</script>

