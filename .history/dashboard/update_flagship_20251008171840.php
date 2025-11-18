<?php
include("../db_connect.php");

if(isset($_POST['project_id']) && isset($_POST['flagship'])){
    $projectId = intval($_POST['project_id']);
    $flagship = intval($_POST['flagship']);
    

    $sql = "UPDATE projects SET live_url = $flagship WHERE id = $projectId";
    if(mysqli_query($conn, $sql)){
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
}
?>
