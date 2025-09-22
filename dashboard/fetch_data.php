<?php
include("../db_connect.php");
include("../includes/function.php");

if ($_POST['action'] && $_POST['project_id']) {
    $action = $_POST['action'];
    $project_id = intval($_POST['project_id']);
    
    switch ($action) {
        case 'users':
            // Fetch all users/candidates for this project
            $query = "SELECT id, name, email, phone, created_at FROM admit_card_records WHERE project_id = ? ORDER BY created_at DESC LIMIT 50";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo '<table class="data-table">';
                echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Created Date</th></tr></thead>';
                echo '<tbody>';
                while ($row = $result->fetch_assoc()) {
                    $created_date = date('Y-m-d H:i', strtotime($row['created_at']));
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>' . $created_date . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="no-data">No users found for this project</div>';
            }
            break;
            
        case 'downloaded':
            // Fetch users who have downloaded their admit cards
            $query = "SELECT DISTINCT acr.id, acr.name, acr.email, acr.phone, cl.downloaded_at 
                     FROM admit_card_records acr 
                     INNER JOIN candidate_logs cl ON acr.id = cl.user_id 
                     WHERE acr.project_id = ? 
                     ORDER BY cl.downloaded_at DESC LIMIT 50";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo '<table class="data-table">';
                echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Downloaded Date</th></tr></thead>';
                echo '<tbody>';
                while ($row = $result->fetch_assoc()) {
                    $downloaded_date = date('Y-m-d H:i', strtotime($row['downloaded_at']));
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>' . $downloaded_date . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="no-data">No downloaded records found</div>';
            }
            break;
            
        case 'pending':
            // Fetch users who have NOT downloaded their admit cards
            $query = "SELECT acr.id, acr.name, acr.email, acr.phone, acr.created_at 
                     FROM admit_card_records acr 
                     LEFT JOIN candidate_logs cl ON acr.id = cl.user_id AND acr.project_id = cl.project_id
                     WHERE acr.project_id = ? AND cl.user_id IS NULL 
                     ORDER BY acr.created_at DESC LIMIT 50";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $project_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo '<table class="data-table">';
                echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Created Date</th></tr></thead>';
                echo '<tbody>';
                while ($row = $result->fetch_assoc()) {
                    $created_date = date('Y-m-d H:i', strtotime($row['created_at']));
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
                    echo '<td>' . $created_date . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="no-data">No pending downloads found</div>';
            }
            break;
            
        default:
            echo '<div class="no-data">Invalid action</div>';
            break;
    }
} else {
    echo '<div class="no-data">Missing required parameters</div>';
}
?>
