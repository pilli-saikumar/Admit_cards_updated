<?php
include("../includes/header.php");
include("../db_connect.php");

$project_id = intval($_GET['project_id']);
// $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
// $limit = 10;
// $offset = ($page - 1) * $limit;

// // Fetch total count
// $countResult = $conn->prepare("SELECT COUNT(*) as total FROM admit_card_records WHERE project_id = ?");
// $countResult->bind_param("i", $project_id);
// $countResult->execute();
// $total = $countResult->get_result()->fetch_assoc()['total'];
// $totalPages = ceil($total / $limit);

// // Fetch paginated records
// $recordsStmt = $conn->prepare("SELECT id, registration_number, first_name, email, mobileNumber FROM admit_card_records WHERE project_id = ? LIMIT ? OFFSET ?");
// $recordsStmt->bind_param("iii", $project_id, $limit, $offset);
// $recordsStmt->execute();
// $records = $recordsStmt->get_result();
?>

<div class="ml-64 p-5">
     <a href="javascript:history.back()" class="back-button">← Back</a>
<div class="container mt-5">
<h2 class="text-xl font-bold mb-3">User Records</h2>
<table id="userTable" class="display">
  <thead>
    <tr>
      <th>Reg. No</th>
      <th>First Name</th>
      <th>Email</th>
      <th>Mobile</th>
      <th>Edit</th>
    </tr>
  </thead>
  
</table>




</div>
</div>
<!-- DataTables JS -->
 <!-- jQuery -->
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
    $('#userTable').DataTable({
        dom: 'Bfrtip', // 'B' stands for Buttons
        buttons: [
            {
                extend: 'excelHtml5',  // Export to Excel
                title: 'User Data Export'  // Optional: Set the title of the exported file
            }
        ],
        "processing": true,
        "serverSide": true,
        
        "ajax": {
            "url": "fetch_users.php?project_id=<?= $project_id ?>",
            "type": "POST"
        },
        "columns": [
            { "data": "registration_number" },
            { "data": "first_name" },
            { "data": "emailaddress" },
            { "data": "mobileNumber" },
            { "data": "dob" },
            { "data": "action", "orderable": false, "searchable": false }
        ]
    });
});
</script>