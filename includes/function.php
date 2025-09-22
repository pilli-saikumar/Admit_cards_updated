<?php
include("../db_connect.php");

function logUserAction($conn, $user_name, $user_role, $project_id, $template_id, $action) {
    $stmt = $conn->prepare("INSERT INTO user_actions (user_name, user_role, project_id, template_id, action) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiis", $user_name, $user_role, $project_id, $template_id, $action);
    $stmt->execute();
    $stmt->close();
}

function projectName($project_id) {

    global $conn;
    $stmt = $conn->prepare("SELECT name FROM projects WHERE id = ?");

    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $stmt->close();
    return $result->fetch_assoc()['name'];
}