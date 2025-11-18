<?php

include("../includes/candidate_header.php");

include("../db_connect.php");
$registration_number = $_SESSION["registration_number"];
$project_id = $_SESSION["project_id"] ?? null;
$project_name = $_SESSION['project_name'] ?? '';
if($project_id){
$project_header = $conn->prepare("SELECT header FROM projects WHERE id = ?");
$project_header->bind_param("i", $project_id);
$project_header->execute();
$project_header_result = $project_header->get_result();
$project_header_row = $project_header_result->fetch_assoc();
$project_header = $project_header_row['header'] ?? '';
}

// $recordsQuery = $conn->prepare("
//     SELECT acr.id, acr.first_name, acr.project_id,acr.registration_number, p.name AS project_name, p.column_based
//     FROM admit_card_records acr
//     JOIN projects p ON acr.project_id = p.id
//     WHERE acr.registration_number = ? AND acr.is_admit_card_live = 1
// ");
$recordsQuery = $conn->prepare("
    SELECT acr.id, acr.first_name, acr.registration_number, acr.project_id,acr.is_admit_card_live,
           acr.live_date, acr.unlive_date,
           acr.live_time,
           acr.unlive_time,
           p.name AS project_name, p.column_based,p.header
    FROM admit_card_records acr
    JOIN projects p ON acr.project_id = p.id
    WHERE acr.registration_number = ? AND project_id = ? AND acr.is_admit_card_live = 1
");

$recordsQuery->bind_param("si", $registration_number, $project_id);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();


$records = [];

// while ($row = $recordsResult->fetch_assoc()) {
//     $project_id = $row['project_id'];
//     $column_based = $row['column_based'];


//     if ($column_based !== 'all') {
//     $colValueQuery = $conn->prepare("SELECT `$column_based` FROM admit_card_records WHERE id = ?");
//     $colValueQuery->bind_param("i", $row['id']);
//     $colValueQuery->execute();
//     $valueResult = $colValueQuery->get_result();
//     $valueRow = $valueResult->fetch_assoc();
//     $filter_value = $valueRow[$column_based] ?? '';

//     $colValueQuery->close();
//     } else {)

//         $filter_value = '';
//     }
//        $row['filter_value'] = $filter_value;
//     $records[] = $row;

// }
$candidate_id = null;
$current_date = date('Y-m-d');
$current_time = date('H:i:s');  
$header = '';
while ($row = $recordsResult->fetch_assoc()) {
  $live_date = $row['live_date'] ?? null;
  $unlive_date = $row['unlive_date'] ?? null;
  $live_time = $row['live_time'] ?? null;
  $unlive_time = $row['unlive_time'] ?? null;
  $project_id = $row['project_id'] ?? null;
  $column_based = $row['column_based'] ?? 'all';
  $is_admit_card_live = $row['is_admit_card_live'] ?? 0;
  $candidate_id = $row['id'] ?? null;
  $header = $row['header'] ?? '';

        // $is_live = (
        //   $is_admit_card_live == 1 &&
        //   $live_date && $unlive_date &&
        //   $current_date >= $live_date && $current_date <= $unlive_date
        // );

        // $row['is_live_now'] = $is_live;
        // $row['live_message'] = $is_live
        //   ? ''
        //   : "Your admit card will be live between " .
        //   date('d-m-Y', strtotime($live_date)) . " and " .
        //   date('d-m-Y', strtotime($unlive_date)) . ".";
      //   $is_live = (
      //     $is_admit_card_live == 1 &&
      //     $live_date && $unlive_date &&
      //     $current_date >= $live_date && $current_date <= $unlive_date &&
      //     $current_time >= $live_time && $current_time <= $unlive_time
      // );
      date_default_timezone_set('Asia/Kolkata'); // ✅ Set to your local timezone

      $now = new DateTime();  // Current datetime (Asia/Kolkata)
      $live_start = new DateTime("$live_date $live_time");
      $live_end = new DateTime("$unlive_date $unlive_time");

      $is_live = (
          $is_admit_card_live == 1 &&
          $now >= $live_start &&
          $now <= $live_end
      );

      $row['is_live_now'] = $is_live;

// Prepare live message
      if ($is_live) {
          $row['live_message'] = '';
      } elseif ($now > $live_end) {
          $row['live_message'] = "Admit cards are closed.";
      } else {
          $row['live_message'] = "Your admit card download will be available from <strong>" . date('d M Y', strtotime($live_date)) . " " . $live_time . "</strong>.";
      }
  // Filter value logic remains same
  if ($column_based !== 'all') {
    $colValueQuery = $conn->prepare("SELECT `$column_based` FROM admit_card_records WHERE id = ?  ");
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
  $candidate_name = $records[0]['first_name'] ?? '';
  $registration_number = $records[0]['registration_number'] ?? '';
  $project_name = $records[0]['project_name'] ?? '';
}

$stmt = $conn->prepare("SELECT column_name, label_name FROM candidate_view_details WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$fields_to_show = [];
while ($candidate_view_details = $result->fetch_assoc()) {
    $fields_to_show[] = $candidate_view_details;
}
$stmt->close();
if(!empty($fields_to_show)){
// Step 2: Prepare list of columns to fetch from admit_card_records
$columns = array_column($fields_to_show, 'column_name');

$columns_sql = '`' . implode('`, `', $columns) . '`';


$sql = "SELECT $columns_sql FROM admit_card_records WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$data_result = $stmt->get_result();
$candidate_data = $data_result->fetch_assoc();
$stmt->close();
}
?>

<!-- <div class="content-container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
  <div class="container">
   
    <?php if (!empty($records)): ?>
  
      <div class="text-center mb-4">
        <h4><?= htmlspecialchars($candidate_name) ?></h4>
        <p><strong>Registration No:</strong> <?= htmlspecialchars($registration_number) ?></p>
      </div>
      <div class="row justify-content-center">
        <?php foreach ($records as $record): ?>
          <div class="col-md-6 mb-4 d-flex justify-content-center">
            <div class="card shadow" style="width: 100%; max-width: 600px;">
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($record['filter_value']) ?></h5>
                <?php if ($record['is_live_now']): ?>
                  <form action="../dashboard/generate_pdf_back.php" method="POST" target="_blank" style="display:inline;">
                    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
                    <input type="hidden" name="column_based" value="<?= htmlspecialchars($record['column_based']) ?>">
                    <input type="hidden" name="filter_column" value="<?= htmlspecialchars($record['filter_value']) ?>">
                    <button type="submit" class="btn btn-primary">View Admit Card</button>
                  </form>
                <?php else: ?>
                  <div class="alert alert-warning mt-3 mb-0 p-2 text-center">
                    <?= htmlspecialchars($record['live_message']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-warning text-center">
        Your admit card has not been generated yet.
      </div>
    <?php endif; ?>
  </div>
</div>
<footer class="text-center  py-3" style="background-color: #f8f9fa;">
  <p >&copy; <?= date('Y') ?> <?= $project_name ?>. All rights reserved.</p>
</footer> -->
<div class="content-container d-flex justify-content-center align-items-center" style=" background-color: #f9f9f9;">
  <div class="container py-4">


    <?php if (!empty($records)): ?>
      <div class="row justify-content-center g-4">
      <?php if(!empty($fields_to_show)) : ?>
    <div class="col-6 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white text-center">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-person-circle me-2"></i>Candidate Details
                </h5>
            </div>
            <div class="card-body p-2">
                <?php if ($candidate_data) : ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <tbody>
                                <?php foreach ($fields_to_show as $field) : 
                                    $column = $field['column_name'];
                                    $label = $field['label_name'];
                                    $value = htmlspecialchars($candidate_data[$column] ?? 'N/A');
                                ?>
                                    <tr>
                                        <td class="fw-semibold text-muted" style="width: 40%;">
                                            <?= $label ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= $value ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="text-center py-4">
                        <i class="bi bi-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 text-muted">No candidate data found</h6>
                        <p class="text-muted small">Please contact administrator if this is an error.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
   <?php endif; ?>
        <div class="col-md-7 col-lg-5">
          <div class="card shadow-sm border-1 h-100">
            <div class="card-body text-center">
            
              <!-- <h5 class="fw-bold text-uppercase mb-1"><strong>Name: </strong> <?= htmlspecialchars($candidate_name) ?></h5> -->
              <p class="mb-3"><strong>Name:</strong> <?= htmlspecialchars($candidate_name) ?></p>
              <!-- <h5 class="fw-bold text-uppercase mb-1"><strong>Name: </strong> <?= htmlspecialchars($candidate_name) ?></h5> -->
              <p class="mb-3"><strong>Registration No:</strong> <?= htmlspecialchars($registration_number) ?></p>

              <!-- Post -->
              <?php foreach ($records as $record): ?>
                  <h6 style="color:rgb(29, 147, 226); font-family: 'Arial Black', sans-serif; font-weight: bold; font-size: 18px; margin-top: 1rem;">
          <?= htmlspecialchars($record['filter_value']) ?>
      </h6>

                <!-- Action -->
                <?php if ($record['is_live_now']): ?>
                  <form action="../dashboard/generate_pdf.php" method="POST" target="_blank">
                    <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $record['project_id'] ?>">
                    <input type="hidden" name="column_based" value="<?= htmlspecialchars($record['column_based']) ?>">
                    <input type="hidden" name="filter_column" value="<?= htmlspecialchars($record['filter_value']) ?>">
                    <button type="submit" class="btn btn-success mt-3 w-100">
                      <i class="bi bi-download"></i> Download Admit Card
                    </button>
                  </form>
                <?php else: ?>
                  <div class="alert alert-warning mt-3 text-center mb-0 fw-medium">
                    <?= $record['live_message'] ?>
                  </div>
                             
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
          </div>
        </div>
  
      </div>

    <?php else: ?>
       <?php
        $recordsQuery = $conn->prepare("
    SELECT acr.id, acr.first_name, acr.registration_number, acr.project_id,acr.is_admit_card_live,
           acr.live_date, acr.unlive_date,
           p.name AS project_name, p.column_based
    FROM admit_card_records acr
    JOIN projects p ON acr.project_id = p.id
    WHERE acr.registration_number = ? AND project_id = ?
");

$recordsQuery->bind_param("si", $registration_number, $project_id);
$recordsQuery->execute();
$recordsResult = $recordsQuery->get_result();  
$firtname = '';
        while ($row = $recordsResult->fetch_assoc()) {
          $firtname = $row['first_name'] ?? '';
        }


    
        ?>

      <div class="alert alert-warning text-center">
    Welcome <?= htmlspecialchars($firtname) ?>, your admit card has not been generated yet.
</div>
    <?php endif; ?>

  </div>
</div>

<footer class="text-center py-3 mt-5 bg-light border-top">
  <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($project_header) ?>. All rights reserved.</p>
</footer>


</body>

</html>