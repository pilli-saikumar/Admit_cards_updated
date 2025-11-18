<?php 
  include("../includes/header.php");
  include("../includes/project_process.php");
 include("../db_connect.php");
 $project_id = $_GET['project_id'] ?? 0;
 $columns = [];
$result = $conn->query("SHOW COLUMNS FROM admit_card_records");
if ($result) {
    $allowed_columns = ['registration_number','roll_number','first_name', 'last_name', 'dob', 'sex', 'photo_path', 'signature_path','post_applied',''];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}



$projectIdValue = isset($_GET['project_id']) ? htmlspecialchars($_GET['project_id']) : '';
$Admit_Cards = $conn->query("SELECT * FROM admit_card_records WHERE project_id = $project_id");
$Admit_Cards = $Admit_Cards->fetch_all(MYSQLI_ASSOC);

$get_count = $conn->query("SELECT COUNT(registration_number) as toatal_records, COUNT(photo_path) as photo, COUNT(signature_path) as signature FROM admit_card_records WHERE project_id = $project_id");
$get_count = $get_count->fetch_assoc();


?>
<div class="ml-64  ">
     <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
    <?php if (isset($_SESSION['success_message'])) {
    echo "<div id='file-success' class='bg-green-100 text-green-800 px-4 py-2 rounded mb-4'> {$_SESSION['success_message']}</div>";
    unset($_SESSION['success_message']); // Clear it after showing once
}

?>
  <?php if (isset($_SESSION['flash_error'])) {
    echo "<div id='file-success' class='bg-danger-100 text-green-800 px-4 py-2 rounded mb-4'>{$_SESSION['flash_error']}</div>";
    unset($_SESSION['flash_error']); // Clear it after showing once
} ?> 
   <div class="text-center mb-1">
    <?php if (empty($Admit_Cards)): ?>
      <span class="text-red-500"> CSV File not uploaded</span>
      <?php else: ?>
      <span class="text-green-700">CSV File uploaded : <b><?php echo $get_count['toatal_records']; ?> </b> records</span><?php endif; ?>
   </div>
    <!-- <h2 class="text-2xl font-bold text-center">Upload CSV File</h2> -->
    <form action="process_csv.php" method="post" enctype="multipart/form-data" class="bg-white p-6  rounded-lg border border-gray-300 shadow-md text-sm w-1/2 mx-auto">
    <div class="mb-4">
   
 
        <label for="template_images" class="block text-gray-700 text-sm font-bold mb-2">Upload .CSV File </label>
          <input type="hidden" name="project_id" value="<?= $projectIdValue ?>">
          
          
        <input type="file" name="csv_file" multiple class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept=".csv" required>

       
    </div>
    <div class="mb-4">
        <label for="columns" class="block mt-4 text-gray-700 text-sm font-bold mb-2"> Which column should be used to generate the Admit Card? </label>
        <select name="column_based" id="columns"  class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
           <option value="" disabled selected>Select Column</option>  
        <option value="all" >All Columns</option> 
        <?php foreach ($columns as $column): ?>
               
                <option value="<?= htmlspecialchars($column) ?>"><?= htmlspecialchars($column) ?></option>
            <?php endforeach; ?>
        </select>
         <button type="submit" name="add_project" class="mt-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Upload</button>
    </div>
</form>
    
</div>
<script>
  setTimeout(function() {
    var errBox = document.getElementById('file-success');
    if (errBox) {
      errBox.style.display = 'none';
    }
  }, 5000); // 5000ms = 5 seconds
</script>