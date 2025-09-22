<?php
include("../db_connect.php");


 if (!empty($_POST['template_id']) && !empty($_POST['page_order'])) {
//     $template_id = intval($_POST['template_id']);
//     $page_order = intval($_POST['page_order']);

//     $stmt = $conn->prepare("UPDATE project_templates SET page_order = ? WHERE id = ?");
//     $stmt->bind_param("ii", $page_order, $template_id);

//     if ($stmt->execute()) {
//         echo "success";
//     } else {
//         echo "error";
//     }
// }
$template_id = intval($_POST['template_id']);
    $new_order = intval($_POST['page_order']);

    // Get current order and project_id
    $stmt = $conn->prepare("SELECT page_order, project_id FROM project_templates WHERE id = ?");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $stmt->bind_result($current_order, $project_id);
    $stmt->fetch();
    $stmt->close();

    if ($new_order == $current_order) {
        echo "success";
        exit;
    }

    // Shift other templates' orders
    if ($new_order < $current_order) {
        // Moving up: increment all between new_order and current_order - 1
        $conn->query("UPDATE project_templates SET page_order = page_order + 1 WHERE project_id = $project_id AND page_order >= $new_order AND page_order < $current_order");
    } else {
        // Moving down: decrement all between current_order + 1 and new_order
        $conn->query("UPDATE project_templates SET page_order = page_order - 1 WHERE project_id = $project_id AND page_order <= $new_order AND page_order > $current_order");
    }

    // Set the new order for this template
    $stmt = $conn->prepare("UPDATE project_templates SET page_order = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_order, $template_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
?>
