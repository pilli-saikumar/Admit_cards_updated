<?php  
include("../includes/header.php");
include("../includes/project_process.php");
include("../db_connect.php");
include("../includes/function.php");
set_time_limit(0);

$project_id = $_GET['project_id'] ?? 0;

 $columns = [];
 $all_columns = [];
$result = $conn->query("SHOW COLUMNS FROM admit_card_records");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (in_array($row['Field'], ['photo_path', 'signature_path'])) {
            $columns[] = $row['Field'];
        }
        $all_columns[] = $row['Field'];
    }
}

//$get_count = $conn->query("SELECT COUNT(DISTINCT registration_number) as toatal_records, COUNT(DISTINCT CASE WHEN photo_path IS NOT NULL AND photo_path != '' THEN id END) as photo, COUNT(DISTINCT CASE WHEN signature_path IS NOT NULL AND signature_path != '' THEN id END) as signature FROM admit_card_records WHERE project_id = $project_id");
$get_count = $conn->query("SELECT COUNT(registration_number) as toatal_records, COUNT(photo_path) as photo, COUNT(signature_path) as signature FROM admit_card_records WHERE project_id = $project_id");
$get_count = $get_count->fetch_assoc();

// $get_images = $conn->query(" id,roll_number,roll_number,photo_path, signature_path FROM admit_card_records WHERE project_id = $project_id");
?>
<div class="ml-64 ">
    <?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_photo_settings'])) {

   
    $photo_path = $_POST['photo_path'];
    $photo_type = $_POST['photo_type'];
    $photo_column_based = $_POST['photo_column_based'];
    $photo_prefix = $_POST['photo_prefix'];
    $candidate_photo = $_FILES['candidate_photo'];
    $upload_photo_path = $_POST['upload_photo_path'];
     $isReupload = isset($_POST['reupload']) && $_POST['reupload'] == 1;
     $extension = $_POST['extension'];
  $created_by = $_SESSION['user_name'];
    
    
      
    $project_slug = $conn->query("SELECT slug FROM projects WHERE id = $project_id")->fetch_assoc()['slug'];
    $upload_dir = dirname(__DIR__) . "/$project_slug/" . ($photo_type === 'photo_path' ? 'photos' : 'signatures') . "/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // // Get all values from the selected column for this project
    // $result = $conn->query("SELECT id, $photo_column_based FROM admit_card_records WHERE project_id = $project_id");
    // $columnMap = []; // [roll_number => id]

    // while ($row = $result->fetch_assoc()) {
    //     $columnMap[strtolower(trim($row[$photo_column_based]))] = $row['id'];
    // }


    // if (empty($columnMap)) {
    //     $_SESSION['flash_error'] = "No candidate data found for column: $photo_column_based";
    //     header("Location: upload_photos.php?project_id=" . urlencode($project_id));
    //     exit;
    // }
    // $mapped_photos = 0;
    // $unmapped_count = 0;
    // // Process uploaded files
    // foreach ($candidate_photo['tmp_name'] as $index => $tmpName) {
    //     $originalName = $candidate_photo['name'][$index];
    //     $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    //     $baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
 
    //     // Remove prefix if set
    //     // if (!empty($photo_prefix) && str_starts_with($baseName, strtolower($photo_prefix))) {
    //     //     $baseName = substr($baseName, strlen($photo_prefix));
    //     // }

    //     if (isset($columnMap[$baseName])) {
    //         if(!empty($photo_prefix)){
    //      $newFileName = $baseName .$photo_prefix. '.' . $extension;  
    //         }else{
    //              $newFileName = $baseName .'.'. $extension;  
    //         }
              
    //     //    $newFileName = $baseName . '.' . $extension;
    //         $destination = $upload_dir . $newFileName;
    //         $mapped_count = $columnMap[$baseName];
    //         $mapped_photos++;  
        
    //         if (move_uploaded_file($tmpName, $destination)) {
    //             $dbPath = "$project_slug/" . ($photo_type === 'photo_path' ? 'photos' : 'signatures') . "/$newFileName";
    //             $candidateId = $columnMap[$baseName];
        
               
    //             $stmt = $conn->prepare("UPDATE admit_card_records SET `$photo_type` = ? WHERE id = ?");
    //             $stmt->bind_param("si", $dbPath, $candidateId);
    //             $stmt->execute();
              

    //         }
    //     }else{

    //            $unmapped_count++;
    //     }
    // }
   

    // $_SESSION['flash_success'] = "Candidate images uploaded successfully. Mapped Count .$mapped_count. AND UnMapped Count : .$unmapped_count.";

    // header("Location: upload_photos.php?project_id=" . urlencode($project_id));
    // exit;  
    if ($isReupload) {
        $existingFiles = glob($upload_dir . '*.{jpg,jpeg,png}', GLOB_BRACE);
        foreach ($existingFiles as $file) {
            unlink($file); // delete files
        }

        // clear DB path
        $nullUpdate = "UPDATE admit_card_records SET `$photo_type` = NULL WHERE project_id = ?";
        $stmtClear = $conn->prepare($nullUpdate);
        $stmtClear->bind_param("i", $project_id);
        $stmtClear->execute();
    }

    // Fetch existing values from DB
    $result = $conn->query("SELECT id, $photo_column_based FROM admit_card_records WHERE project_id = $project_id");
     
 
    // $columnMap = [];
    // while ($row = $result->fetch_assoc()) {
    //     $columnMap[strtolower(trim($row[$photo_column_based]))] = $row['registration_number'];
    // }

        $columnMap = [];
        while ($row = $result->fetch_assoc()) {
            $key = strtolower(trim($row[$photo_column_based]));
            $columnMap[$key] = $row[$photo_column_based]; // Use the same column value
        }
 
       
    if (empty($columnMap)) {
        $_SESSION['flash_error'] = "No candidate data found for column: $photo_column_based";
        header("Location: upload_photos.php?project_id=" . urlencode($project_id));
        exit;
    }
    if ($photo_path === 'Local Path') {
        
        
            // Track counts
            $mapped_photos = 0;
            $unmapped_count = 0;
            $total_files = count($candidate_photo['name']);

        foreach ($candidate_photo['tmp_name'] as $index => $tmpName) {
            $originalName = $candidate_photo['name'][$index];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));

            // Create new file name
            if (!empty($photo_prefix)) {
                $newFileName = $baseName . $photo_prefix . '.' . $extension;
            } else {
                $newFileName = $baseName . '.' . $extension;
            }
           
            
                if (isset($columnMap[$baseName])) {
                $destination = $upload_dir . $newFileName;
                $candidateId = $columnMap[$baseName];
               
                if (move_uploaded_file($tmpName, $destination)) {
                    $dbPath = "$project_slug/" . ($photo_type === 'photo_path' ? 'photos' : 'signatures') . "/$newFileName";
                    $stmt = $conn->prepare("UPDATE admit_card_records SET `$photo_type` = ?, path_type = ? WHERE registration_number = ?");
                    $stmt->bind_param("sss", $dbPath, $photo_path, $candidateId);
                    $stmt->execute();
                    $mapped_photos++;
                }
               } else {
                $unmapped_count++;
                }

           
        }
        die;
           logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, "Uploaded photos for : " . projectName($conn, $project_id)."path type: $photo_path");
    }else{
       
    //     $mapped_photos = 0;
    // $unmapped_count = 0;
    // $total_files = count($columnMap);
    

    // foreach ($columnMap as $key => $candidateId) {
    //     $extension = $extension;
    
    //     // Build fresh path for each loop
    //     $individual_path = rtrim($upload_photo_path, '/') . '/' . $key;
       
    //     if ($photo_prefix) {
    //         $final_path = $individual_path . $photo_prefix . '.' . $extension;
    //     } else {
    //         $final_path = $individual_path . '.' . $extension;
    //     }
       

    //     // $dbPath = ($photo_type === 'photo_path' ? 'photos' : 'signatures') . "/$final_path";
    //     $dbPath = $final_path;

    //     $stmt = $conn->prepare("UPDATE admit_card_records SET `$photo_type` = ?, path_type = ? WHERE registration_number = ?");
    //     $stmt->bind_param("ssi", $dbPath, $photo_path, $candidateId);

    //     if ($stmt->execute()) {
    //         $mapped_photos++;
    //     } else {
    //         $unmapped_count++;
    //     }
    // }
    //     $extension = $extension;
    
    //     // Build fresh path for each loop
    //     $individual_path = rtrim($upload_photo_path, '/') . '/' . $key;
       
    //     if ($photo_prefix) {
    //         $final_path = $individual_path . $photo_prefix . '.' . $extension;
    //     } else {
    //         $final_path = $individual_path . '.' . $extension;
    //     }
       

    //     // $dbPath = ($photo_type === 'photo_path' ? 'photos' : 'signatures') . "/$final_path";
    //     $dbPath = $final_path;
        $update_path_type = $conn->prepare("UPDATE admit_card_records SET path_type = ? WHERE project_id = ?");
        $update_path_type->bind_param("si", $photo_path, $project_id);
        $update_path_type->execute();

    $stmt = $conn->prepare("
        INSERT INTO photo_path_settings 
            (project_id, photo_path_type, column_based, photo_type, path, prefix, extension, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "isssssss",
        $project_id,
        $photo_path,
        $photo_column_based,
        $photo_type,
        $upload_photo_path,
        $photo_prefix,
        $extension,
        $created_by
    );
    
    if ($stmt->execute()) {
        $success = true;
        $message = "Photo settings saved successfully.";
    } else {
        $success = false;
        $message = "Error saving photo settings: " . $conn->error;
    }
    
    if ($success) {
        logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, 
            "Saved photo settings for: " . projectName($conn, $project_id) . " | Type: $photo_type | Path: $photo_path");
        $_SESSION['flash_success'] = $message;
    } else {
        $_SESSION['flash_error'] = $message;
    }

    logUserAction($conn, $_SESSION['user_name'], $_SESSION['user_role'], $project_id, 0, "Uploaded photos for   : " . projectName($conn, $project_id)) ."path type: $photo_path";

    }

  
    $_SESSION['flash_success'] = "✅ Upload Summary:<br>
        Total Files: <b>$total_files</b>
        Mapped: <b>$mapped_photos</b>
        Unmapped: <b>$unmapped_count</b>";

    header("Location: upload_photos.php?project_id=" . urlencode($project_id));
    exit;
}
?>

      
  <?php if ($get_count['toatal_records'] > 0): ?>
    <div class="flex justify-center gap-4 p-1  text-sm  ">
        <span>Total Records: <b><?= $get_count['toatal_records'] ?></b></span>
        <span>Photo Records: <b><?= $get_count['photo'] ?></b></span>
        <span>Signature Records: <b><?= $get_count['signature'] ?></b></span>
        <span class="ml-8 float-right"> <a href= "unmapped_records.php?project_id=<?= $project_id ?>" class="text-blue-500 rounded border px-2 py-1 font-bold  btn-sm">Unmapped Records list</a></span>
    </div>
<?php endif; ?> 
    
    <div class="form-wrapper p-3">
   <?php if (isset($_SESSION['flash_success'])): ?>
    <div style="color: green;" id="flash_success">
        <?= $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
    </div>
    <?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div style="color: red;">
        <?= $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>
    <!-- <h2 class="text-center mb-4">Upload Candiate Photos</h2> -->


    <form method="POST" action="" enctype="multipart/form-data" class="p-3  border border-gray-300 rounded-lg shadow-md text-sm w-1/2 mx-auto">
        <div id="templateSettingsContainer">
            <div class="templateSettings row align-items-end">
                <div class=" row">
                 <div class="col-12 mb-6">
                    <label class="form-label">Select Photo Path:</label>
                    <select name="photo_path" class="form-select" required id="photo_path">
                        <option value="Local Path">Local Path </option>
                        <option value="External Path">External Path </option>
                    </select>

                 </div>
              
                <div class="col-12 mb-6">
                   
                     
                    <label class="form-label">Select Photo Type:</label>
                    <select name="photo_type" class="form-select columnDropdown" required id="photo_type">
                        <option value=""> Select type</option>
                        <?php
                        foreach ($columns as $column): ?>
                            <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars(str_replace('_', ' ', $column)) ?></option>
                        <?php endforeach; ?>
                      
                    </select>
                </div>
                <div class= "col-12 mb-6">
                    <label class="form-label">Which  column based on that photo will be uploaded:</label>
                            <select name="photo_column_based" class="form-select" required id="photo_column_based">
                                <option value=""> Select column </option>
                                <?php
                                foreach ($all_columns as $column): ?>
                                    <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars(str_replace('_', ' ', $column)) ?></option>
                                <?php endforeach; ?>
                              
                            </select>
                    
                </div>
                <div class= "col-12 mb-6">
                    <lable>do you have any prefix for photo name ?</lable>
                    <input type="text" name="photo_prefix" class="form-control"  id="photo_prefix">
                    
                </div>
                
   
                <div class="col-12 mb-6" id="candidate_photo_container">
                    <label class="form-label">Upload Photos:</label>
                    <input type="file" name="candidate_photo[]" multiple class="form-control" id="candidate_photo">
                </div>
                <span id="sample_path" style="display: none;"></span>
                <div class="col-12-mb-6" id="upload_photo_path" style="display: none;">
                    <label class="form-label">Upload Photo Path:</label>
                    <input type="text" name="upload_photo_path" class="form-control" id="upload_photo_path">

               </div>
               <div class="col-12 mb-6" id="extension" style="display: none;">
                <lable>Photo extension</lable>
                <select name="extension" class="form-select" required id="extension">
                    <option value="jpg">.jpg</option>
                    <option value="png">.png</option>
                    <option value="jpeg">.jpeg</option>
                </select>
                
                </div>
                            
                <label class="form-label" for="reupload" id="reupload">
                        <input type="checkbox" name="reupload" value="1"> Re-upload (Delete previous records and files)
                    </label>

                <!-- <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger removeButton d-none">❌ Remove</button>
                </div> -->
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          
            <button type="submit" name="add_photo_settings" class="btn btn-success">💾 Save Settings</button>
        </div>
    </form>
</div>

  
</div>

<script>
    $(document).ready(function() {
        $('#photo_path').on('change', function() {
            if (this.value === 'Local Path') {
                $('#candidate_photo_container').show();
                $('#upload_photo_path').hide();
                $('#extension').hide();
                $('#sample_path').show();
                $('#reupload').show();
            } else {
                $('#candidate_photo_container').hide();
                $('#upload_photo_path').show();
                $('#extension').show();
                $('#sample_path').hide();
                $('#reupload').hide();
            }
        });
       

    

  setTimeout(function() {
    var errBox = document.getElementById('flash_success');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds


  $('#candidate_photo').on('change', function() {
    var photo_type = $('#photo_type').val();
    var photo_prefix = $('#photo_prefix').val();
    var photo_column_based = $('#photo_column_based').val();
     var photo_type = photo_type === 'photo_path' ? 'photos' : 'signatures';
    // Get the selected file(s)
    var files = this.files;
    
    if (files.length > 0) {
      // Get the first file's name
      var fileName = files[0].name;
      
      // Remove file extension to get just the name
      var fileNameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
      
      // Construct sample path
      var sample_path = '';
      
      if (photo_type) {
        sample_path += photo_type + '/';
      }
      
      if (photo_prefix) {
        sample_path += fileNameWithoutExt+photo_prefix;
      } else {
        sample_path += fileNameWithoutExt;
      }
      
      // Add file extension
      sample_path += fileName.substring(fileName.lastIndexOf('.'));
      
      // Show the sample path
      $('#sample_path').show();
      $('#sample_path').html('<strong>Sample Path: </strong>' + sample_path);
    } else {
      $('#sample_path').hide();
    }
  })
});
</script>


