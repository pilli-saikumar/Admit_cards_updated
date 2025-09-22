<?php
// Execute migration to add path_type column
require_once 'db_connect.php';

try {
    // Add the path_type column
    $sql = "ALTER TABLE admit_card_records ADD COLUMN path_type VARCHAR(55)";
    
    if ($conn->query($sql) === TRUE) {
        echo "Column 'path_type' added successfully to admit_card_records table.\n";
        
        // Verify the column was added
        $result = $conn->query("SHOW COLUMNS FROM admit_card_records LIKE 'path_type'");
        if ($result->num_rows > 0) {
            echo "Verification: Column 'path_type' exists in the table.\n";
            $row = $result->fetch_assoc();
            echo "Column details: " . print_r($row, true);
        }
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
