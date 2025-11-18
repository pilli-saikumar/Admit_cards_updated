<?php
include("../includes/header.php");
include("../db_connect.php");
// include("../includes/project_process.php");
include("../includes/function.php");

$project_id = intval($_GET['project_id']);




$candidate_downloads = $conn->prepare("SELECT COUNT(distinct user_id) AS total_downloads FROM  candidate_logs WHERE project_id = ?");
$candidate_downloads->bind_param("i", $project_id);
$candidate_downloads->execute();
$candidate_downloads_result = $candidate_downloads->get_result();
$candidate_downloads_row = $candidate_downloads_result->fetch_assoc();
$total_downloads = $candidate_downloads_row['total_downloads'];

$total_candidates = "SELECT COUNT(distinct id) AS cadidates FROM admit_card_records WHERE project_id = ?";
$total_candidates_stmt = $conn->prepare($total_candidates);
$total_candidates_stmt->bind_param("i", $project_id);
$total_candidates_stmt->execute();
$total_candidates_result = $total_candidates_stmt->get_result();
$total_candidates_row = $total_candidates_result->fetch_assoc();
$total_candidates_count = $total_candidates_row['cadidates'];

// Calculate pending downloads
$pending_downloads = $total_candidates_count - $total_downloads;
?>

<style>
.stats-container {
    display: flex;
    gap: 30px;
    justify-content: center;
    margin: 10px 0;
    flex-wrap: wrap;
 
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 15px;
    text-align: center;
    min-width: 200px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.15);
}

.stat-box.users {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-box.downloaded {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-box.pending {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
    display: block;
}

.stat-label {
    font-size: 1.1rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.project-title {
    text-align: center;
    font-size: 1.6rem;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
    padding: 10px;
    /* background: #f8f9fa; */
   
}

.stat-box button {
    background: none;
    border: none;
    color: inherit;
    width: 100%;
    height: 100%;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.stat-box button i {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.back-button {
    display: inline-block;
    padding: 10px 20px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 20px;
    transition: background 0.3s ease;
}

.back-button:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.data-display-section {
    margin-top: 40px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.data-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    text-align: center;
}

.data-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.data-content {
    padding: 30px;
    min-height: 200px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.data-table tr:hover {
    background: #f8f9fa;
}

.loading {
    text-align: center;
    color: #666;
    font-style: italic;
}

.no-data {
    text-align: center;
    color: #999;
    font-style: italic;
}
</style>

<div class="ml-64 mt-1 p-1">
    <a href="check_admitcard.php?proje" class="back-button mr-2">← Back</a>
    <div class="project-title">
        <?php echo projectName($project_id); ?>
    </div>

    
    <div class="stats-container">
        <div class="stat-box users">
            <form action="" method="post">
            <button onclick="viewUsers(<?php echo $project_id; ?>)">
            <i class="fa-solid fa-user"></i>
            <span class="stat-number"><?php echo number_format($total_candidates_count); ?></span>
            <span class="stat-label">Total Users</span>
            <input type="hidden" name="type" value="total">
           
            </button>
            </form>
        </div>
        
        <div class="stat-box downloaded">
            <form action="" method="post">
            <button onclick="viewDownloaded(<?php echo $project_id; ?>)">
            <i class="fa-solid fa-download"></i>
            <span class="stat-number"><?php echo number_format($total_downloads); ?></span>
            <span class="stat-label">Downloaded</span>
            </button>
            <input type="hidden" name="type" value="downloaded">
            </form>
        </div>
        
        <div class="stat-box pending">
            <form action="" method="post">
            <button onclick="viewPending(<?php echo $project_id; ?>)">
            <i class="fa-solid fa-clock"></i>
            <span class="stat-number"><?php echo number_format($pending_downloads); ?></span>
            <span class="stat-label">Pending Downloads</span>
            </button>
            <input type="hidden" name="type" value="pending">
            </form>
        </div>
    </div>
<?php if(isset($_POST['type'])) { ?>
    <div class="data-display-section">
        <table class="data-table p-1" id="data-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Candidate Name</th>
                    <th>Registration Number</th>
                    <?php if(isset($_POST['type']) && $_POST['type'] != 'downloaded'){ ?> 
                    <th>DOB</th>
                    <?php } ?>
                    <?php if(isset($_POST['type']) && $_POST['type'] == 'downloaded'){ ?>
                    <th>Admit Card Type</th>
                    <th>Admit Card Downloaded</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                  
            <?php
            $sno = 1;
          
            if(isset($_POST['type'])){
              if($_POST['type'] == 'total'){

                $query = "SELECT id, first_name, last_name, registration_number,dob FROM admit_card_records WHERE project_id = ? AND is_admit_card_live = 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while($row = $result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$sno++."</td>";
                    echo "<td>".$row['first_name']." ".$row['last_name']."</td>";
                    echo "<td>".$row['registration_number']."</td>";
                    echo "<td>".$row['dob']."</td>";
                   
                    echo "</tr>";
                }                
              }else if($_POST['type'] == 'downloaded'){
                $downloaded ="SELECT * FROM candidate_logs WHERE project_id = ?    GROUP BY user_id";
                $stmt = $conn->prepare($downloaded);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while($row = $result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$sno++."</td>";
                    echo "<td>".$row['username']."</td>";
                    echo "<td>".$row['register_id']."</td>";
                    echo "<td>".$row['column_type']."</td>";
                    echo "<td>".$row['download_time']."</td>";
                    echo "</tr>";
                }                
              }else if($_POST['type'] == 'pending'){
                $pending = "SELECT c.id, c.registration_number, c.first_name, c.last_name, c.emailaddress, c.mobileNumber, c.dob FROM admit_card_records c WHERE c.project_id = ? AND c.id NOT IN (SELECT cl.user_id FROM candidate_logs cl WHERE cl.project_id = ?)";
                $stmt = $conn->prepare($pending);
                $stmt->bind_param("ii", $project_id, $project_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while($row = $result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$sno++."</td>";
                    echo "<td>".$row['first_name']." ".$row['last_name']."</td>";
                    echo "<td>".$row['registration_number']."</td>";
                    echo "<td>".$row['dob']."</td>";                  
                    echo "</tr>";
                }                
              }
            }
            ?>
            </tbody>
        </table>
    
        </div>
        <?php } ?>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Buttons Extension -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- HTML5 export buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    $('#data-table').DataTable({
        dom: 'Bfrtip',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Export to Excel',
                title: 'Data Export',
                className: 'dt-button'
            }
        ],
        responsive: true,
        processing: true
    });
});
</script>
    
