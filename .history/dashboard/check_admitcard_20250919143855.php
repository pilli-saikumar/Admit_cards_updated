<?php 
   include("../includes/header.php");
    include("../includes/project_process.php");
 include("../db_connect.php");

$project_id = intval($_GET['project_id']); // Always sanitize input!

//$columnSql = "SELECT DISTINCT columns_name FROM project_templates WHERE project_id = ?";
// $columnSql = "
//     SELECT DISTINCT pt.columns_name ,p.column_based
//     FROM project_templates pt
//     JOIN projects p ON pt.project_id = p.id
//     WHERE pt.project_id = ?
//     ORDER BY p.column_based ASC
// ";
$columnSql = "
    SELECT pt.columns_name, MIN(pt.id) AS template_id, MIN(p.column_based) AS column_based
    FROM project_templates pt
    JOIN projects p ON pt.project_id = p.id
    WHERE pt.project_id = ?
    GROUP BY pt.columns_name
    ORDER BY column_based ASC
";

$columnStmt = $conn->prepare($columnSql);
$columnStmt->bind_param("i", $project_id);
$columnStmt->execute();
$columnsResult = $columnStmt->get_result();
 
     
$slugQuery = $conn->prepare("SELECT name FROM projects WHERE id = ?");
$slugQuery->bind_param("i", $project_id);
$slugQuery->execute();
$slugResult = $slugQuery->get_result();
$project = $slugResult->fetch_assoc();
// $columnSql = "SELECT DISTINCT pt.columns_name
// FROM project_templates pt
// WHERE pt.project_id = ?
// ORDER BY pt.columns_name ASC";


$project_slug = $project['name'] ?? null;

$projectNameSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '', str_replace(' ', '', $project_slug))));

$candidate_downloads = $conn->prepare("SELECT * FROM  candidate_logs WHERE project_id = ?");
$candidate_downloads->bind_param("i", $project_id);
$candidate_downloads->execute();
$candidate_downloads_result = $candidate_downloads->get_result();

$canidate_settings = $conn->prepare("SELECT * FROM  candidate_settings WHERE project_id = ?");
$canidate_settings->bind_param("i", $project_id);
$canidate_settings->execute();
$canidate_settings_result = $canidate_settings->get_result();




?>

<div class="ml-64 ">
    <?php
 ?>
    <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
  <div class="container ">
    <h1 class="text-center font-bold">Admit Cards For : <?= $project_slug ?> </h1>
   <?php  if (!empty($errors)) {
    echo "<div id='file-error' class='ml-64 p-5' style='color:red'>";
    foreach ($errors as $error) {
        echo "❌ " . htmlspecialchars($error) . "<br>";
    }
    echo "</div>";
} 

 if($candidate_downloads_result->num_rows > 0) { ?>
 <div class="text-right ">
  <a href="download_stacks.php?project_id=<?= $project_id ?>" class="btn btn-success btn-sm">Downloaded Admit Cards </a>
 </div>
 
  <?php } ?>
       <?php print_r($canidate_settings_result); ?>
    <table class="table table-bordered table-responsive table-sm p-2">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Column Names</th>
                <th>Admit card live</th>
            </tr>
        </thead>
        <tbody>
            <?php
     
       $liveCount = 0;
$totalProjects = 0;
$projectNamesForSlug = [];
            $sno = 1;
            while ($project = $columnsResult->fetch_assoc()):
                
           
            
                $projectName = htmlspecialchars($project['columns_name']);
                $column_based = htmlspecialchars($project['column_based']);

              
                 $template_id = $project['template_id'];
                 $totalProjects++; // count total
   
    if ($project['column_based'] === 'all') {
    // Just check if project_id has any admit card live
    $statusQuery = $conn->prepare("
        SELECT is_admit_card_live ,live_date, unlive_date, live_time, unlive_time
        FROM admit_card_records 
        WHERE project_id = ?
        LIMIT 1
    ");
    $statusQuery->bind_param("i", $project_id);
} else {
    // Normal condition
    $statusQuery = $conn->prepare("
        SELECT is_admit_card_live,
               live_date,
               unlive_date,
               live_time,
               unlive_time
        FROM admit_card_records 
        WHERE project_id = ? AND `{$project['column_based']}` = ?
        LIMIT 1
    ");
    $statusQuery->bind_param("is", $project_id, $projectName);
}
   // $statusQuery->bind_param("is", $project_id, $projectName);
    $statusQuery->execute();
    $statusResult = $statusQuery->get_result();
    $row = $statusResult->fetch_assoc();
    // $live_date = $row['live_date'] ?? '';
    // $unlive_date = $row['unlive_date'] ?? '';


    $check_coordinates = $conn->prepare("SELECT template_id FROM field_mappings WHERE project_id = ? AND template_id = ? LIMIT 1 ");
    $check_coordinates->bind_param("ii",$project_id,$template_id);
    $check_coordinates->execute();

    $coordinatesresult = $check_coordinates->get_result();
    $coordinatesresult = $coordinatesresult->fetch_assoc();

  $check_coordinates->close();
     
  $today = date('Y-m-d');

// Get values from DB
$is_live       = $row['is_admit_card_live'] ?? 0;
$live_date     = $row['live_date'] ?? '';
$unlive_date   = $row['unlive_date'] ?? '';
$live_time     = $row['live_time'] ?? '';
$unlive_time   = $row['unlive_time'] ?? '';
$coordinatesOk = $coordinatesresult ?? ''; // true or false from your logic
 

     if (!$coordinatesresult) {
        $buttonLabel = ($is_live == 1) ? "Deactivate" : "Activate (No Coordinates)";
    } else {
        $buttonLabel = ($is_live == 1) ? "Deactivate" : "Activate";
    }

//     $btnClass = 'secondary'; // default fallback

// if ($is_live == 1) {
//     $btnClass = 'danger'; // red for deactivate
// } else {
//     $btnClass = $coordinatesresult ? 'success' : 'warning'; // green or yellow
// }
  //   $buttonLabel = ($is_live == 1) ? "Inactive" : "Active";
    $newStatus = ($is_live == 1) ? 0 : 1;
            ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td><?= $projectName ?></td>
                    <td class="text-center">
                     
                     <form method="POST" action="make_live.php" style="display:inline;"  onsubmit="return confirmLiveStatus(this, <?= $is_live ?>, <?= $coordinatesresult ? 'true' : 'false' ?>);">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <input type="hidden" name="column_based" value="<?= $column_based ?>">
            <input type="hidden" name="columns_name" value="<?= $projectName ?>">
            <input type="hidden" name="is_admit_card_live" value="<?= $newStatus ?>">
                  <!-- <?php if ($is_live != 1 ): ?>
              <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 0;">
                <label for="live_date" style="font-size: 13px; font-weight: 500; margin-bottom:0;">Start Date</label>
                <input type="date" name="live_date" id="live_date" required class="form-control" style="width: 130px;">
                <label for="unlive_date" style="font-size: 13px; font-weight: 500; margin-bottom:0;">End Date</label>
                <input type="date" name="unlive_date" id="unlive_date" required class="form-control" style="width: 130px;">
                <button type="submit" class="btn btn-<?= ($is_live == 1) ? 'danger' : 'primary' ?>">
                    <?= $buttonLabel ?>
                </button>
            </div>
             <?php endif; ?> -->
                  <?php if ($coordinatesresult ){ 
                    
                    
                    ?>
                    
              <div style="display: flex; justify-content: space-between; gap: 8px; align-items: center; margin-bottom: 0;">
                <label for="live_date" style="font-size: 13px; font-weight: 500; margin-bottom:0;">Start Date</label>
                <input type="date" name="live_date" value="<?= $live_date ?>" id="live_date" required class="form-control" style="width: 130px;" min="<?= date('Y-m-d') ?>">
                <input type = "time" name="live_time" value="<?= $live_time ?>" id="live_time" required class="form-control" style="width: 130px;">
                <label for="unlive_date" style="font-size: 13px; font-weight: 500; margin-bottom:0;">End Date</label>
                <input type="date" name="unlive_date" id="unlive_date" value="<?= $unlive_date ?>" required class="form-control" style="width: 130px;">
                <input type = "time" name="unlive_time" value="<?= $unlive_time ?>" id="unlive_time" required class="form-control" style="width: 130px;">
                <button type="submit" class="btn btn-<?= ($is_live == 1) ? 'danger' : 'primary' ?>">
                    <?= $buttonLabel ?>
                </button>
            </div>
             <?php  }else {?>

               <p class="text-danger" style="font-size: 14px; font-weight: 500;color:red; margin-bottom:0;">Coordinates not set!</p>

      <?php       }  ?>


        <!-- <button type="submit" class="btn btn-<?= ($is_live == 1) ? 'danger' : 'primary' ?>">
                <?= $buttonLabel ?>
            </button> -->
   
        </form>
            <form method="POST" action="create_individual.php">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <input type="hidden" name="column_based" value="<?= $column_based ?>">
                <input type="hidden" name="columns_name" value="<?= $projectName ?>">
                <button type="submit" class="btn btn-success btn-sm">Create pdf </button>
                </form> 
                     
                    </td>
                </tr>
            <?php endwhile; ?>
        
                <?php
              
              $path_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
                    $baseUrl = "$path_url/Admit_Cards/$projectNameSlug";
                    $projectName = basename(dirname(__DIR__));
                  
               
              
                ?>
                <?php  if(isset($coordinatesresult) &&$coordinatesresult > 1) { ?>
                <div class="text-center mt-3">
                    <button onclick="window.open('<?= $baseUrl ?>', '_blank')" class="btn btn-success btn-sm">
                        Generate Admit Card URL
                    </button>
                    
                  
                </div>
                <?php } else { ?>
                <div class="text-center mt-3">
                    <button onclick="alert('Coordinates not set! Please set the coordinates before generating the URL.')" style="color: red;" class="btn btn-warning btn-sm">
                        Generate Admit Card URL
                    </button>
                </div>
                <?php } ?>
                <!-- <form method="POST" action="create_individual.php">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <button type="submit" class="btn btn-success btn-sm">Create Individual Admit Cards </button>
                </form> -->
                
        </tbody>

    </table>
               
</div>
</div>
<script>
function confirmLiveStatus(form, isLive, coordinatesExist) {
    // Trying to activate without coordinates
    if (!coordinatesExist && isLive === 0) {
        return confirm("Coordinates not set! Do you want to continue making it live without setting the box?");
    }
    return true;
}

  setTimeout(function() {
    var errBox = document.getElementById('file-error');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds
</script>
</body>
</html>  