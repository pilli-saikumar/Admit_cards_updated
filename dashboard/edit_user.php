<?php
include("../includes/header.php");
include("../db_connect.php");

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM admit_card_records WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$project_id = $data['project_id'];
?>

<div class="ml-64 p-5">
       <a href="javascript:history.back()" class="back-button">← Back</a>
<div class="container mt-5">
<h2 class="text-lg font-bold mb-3">Edit User Record</h2>

<form method="post" action="update_user.php" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $data['id'] ?>">

    <?php
    // $fields = [
    //     'first_name', 'middle_name', 'last_name', 'father_name', 'email', 'mobileNumber', 'sex',
    //     'dob', 'community', 'address', 'district', 'state', 'postoffice', 'landmark', 'pincode',
    //     'exam_center', 'exam_date', 'exam_time', 'roll_number', 'registration_number',
    //     'other_info'
    // ];
    $columnsResult = $conn->query("SHOW COLUMNS FROM admit_card_records");
$excluded = ['id','project_id', 'photo_path', 'signature_path','photo']; // fields you handle separately

$fields = [];
while ($col = $columnsResult->fetch_assoc()) {
    if (!in_array($col['Field'], $excluded)) {
        $fields[] = $col['Field'];
    }
}
    foreach ($fields as $field):
    ?>
        <label class="block mt-2 font-semibold"><?= ucwords(str_replace('_', ' ', $field)) ?>:</label>
        <input type="text" name="<?= $field ?>" value="<?= htmlspecialchars($data[$field]) ?>" class="w-full border rounded px-2 py-1">
    <?php endforeach; ?>

    <!-- Image preview + file input -->
     <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <label class="block mt-4 font-semibold">Photo:</label>
   
    <?php if (!empty($data['photo_path'])):       ?>
       
        <img src="<?= '../' . htmlspecialchars($data['photo_path']) ?>" width="80" class="mb-2 rounded shadow">
    <?php endif; ?>
    <input type="file" name="photo_path" accept="image/*" class="mb-4">
     
    <label class="block font-semibold">Signature:</label>
    <?php if (!empty($data['signature_path'])): ?>
        <img src="<?= '../' . htmlspecialchars($data['signature_path']) ?>" width="80" class="mb-2 rounded shadow">
    <?php endif; ?>
    <input type="file" name="signature_path" accept="image/*">

    <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded">💾 Update</button>
</form>
</div>
</div>
