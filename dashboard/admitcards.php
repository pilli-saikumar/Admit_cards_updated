<?php
  include("../includes/header.php"); 
 include("../db_connect.php");
$projectsQuery = $conn->query("SELECT id, name FROM projects WHERE is_delete = 0");



?>
<div class="ml-64 p-5">
    <a href="javascript:history.back()" class="back-button">← Back</a>
  <div class="container mt-5">
    <h1>Admit Cards</h1>
      <?php
// Display errors if any
if (!empty($errors)) {
    echo "<div id='file-error' class='ml-64 p-5' style='color:red'>";
    foreach ($errors as $error) {
        echo "❌ " . htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
}

// Display success message (if redirected from upload_csv.php, this won't show)
if (isset($_SESSION['success_message'])) {
    echo "<div id='file-error' class='ml-64 p-5' style='color:green'>" . htmlspecialchars($_SESSION['success_message']) . "</div>";
    unset($_SESSION['success_message']);
}
?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Project Name</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sno = 1;
            while ($project = $projectsQuery->fetch_assoc()):
                $projectId = $project['id'];
                $projectName = htmlspecialchars($project['name']);
                $candidate_sessting_details = $conn->query("SELECT * FROM candidate_login_settings WHERE project_id =  $projectId ");
                $candidate_sessting_details = $candidate_sessting_details->fetch_assoc();
                $candidate_sessting_details = $candidate_sessting_details ?? [];
      
            ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td><?= $projectName ?></td>
                    <td> 
                        <?php if (!$candidate_sessting_details): ?>
                            <a href="candidate_login_settings.php?project_id=<?= $projectId ?>" class="btn btn-primary">Candidate Login Settings</a>
                        <?php else: ?>
                        <a href="check_admitcard.php?project_id=<?= $projectId ?>" class="btn btn-primary">Admit Cards Status</a>
                        <?php endif; ?>
                        <!-- <a href="users.php?project_id=<?= $projectId ?>" class="btn btn-primary">User Records</a> -->
                    
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div>
<script>
  setTimeout(function() {
    var errBox = document.getElementById('file-error');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds
</script>
</body>

</html>  

