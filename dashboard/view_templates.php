<?php
 include("../includes/header.php");
 include("../db_connect.php");
 // Start session if not already started (optional, but good practice if you use sessions here)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if project_id is provided in the URL
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    die("Error: Project ID not specified or invalid.");
}

$projectId = intval($_GET['project_id']);

// Fetch project name for display
$projectQuery = $conn->prepare("SELECT name,column_based FROM projects WHERE id = ?");
$projectQuery->bind_param("i", $projectId);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
// $projectName = "Unknown Project";
// if ($projectRow = $projectResult->fetch_assoc()) {
//     $projectName = htmlspecialchars($projectRow['name']);
// }
$projectName = "Unknown Project";
$columnBased = "";

if ($projectRow = $projectResult->fetch_assoc()) {
    $projectName = htmlspecialchars($projectRow['name']);
    $columnBased = htmlspecialchars($projectRow['column_based']);
}

 $projectQuery->close();

// Fetch all templates associated with this project
$templatesQuery = $conn->prepare("SELECT id, template_name, template_image_path,columns_name FROM project_templates WHERE project_id = ? ORDER BY id ASC");
$templatesQuery->bind_param("i", $projectId);
$templatesQuery->execute();
$templatesResult = $templatesQuery->get_result();

?>

    <style>
       /* body {
    font-family: Arial, sans-serif;
    /* margin: 20px; */
 
/* Remove border and padding from the template container */
.template-container {
    border: 1px solid #ddd; /* REMOVE THIS LINE
    padding: 0; /* Changed from 20px to 0 */
    margin-bottom: 20px; /* Reduced space between templates */
    text-align: center;
    background-color: #fff; /* Changed to white or remove for transparent */
    border-radius: 0; /* Removed border-radius */
    box-shadow: none; /* Removed box-shadow */
}

.template-container h3 {
    margin-top: 0;
    color: #2c3e50;
    font-size: 1.5em;
    margin-bottom: 15px; /* Keep this for spacing between title and image */
}

.template-container img {
    max-width: 100%;
    height: auto;
    /* border: 1px solid #eee; /* REMOVE THIS LINE */
    /* box-shadow: 2px 2px 8px rgba(0,0,0,0.1); /* REMOVE THIS LINE */
    border-radius: 0; /* Removed border-radius */
    display: block; /* Ensures the image takes full width and prevents extra space below it */
    margin: 0 auto; /* Centers the image */
}

/* This is a new rule for a cleaner gap between templates */
.template-separator {
    width: 80%; /* Or 100% depending on desired line length */
    height: 1px;
    background-color: #e0e0e0; /* Light grey line */
    margin: 30px auto; /* Centered with top/bottom margin for the gap */
}


.no-templates {
    text-align: center;
    color: #888;
    font-size: 1.1em;
    padding: 20px;
    background-color: #f0f0f0;
    border-radius: 5px;
}

.back-button {
    display: inline-block;
    margin-bottom: 25px;
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 1em;
    transition: background-color 0.3s ease;
}

.back-button:hover {
    background-color: #0056b3;
}

/* h1 {
    text-align: center;
    color: #34495e;
    margin-bottom: 30px;
} */
    </style>
  <div class="ml-64 p-5">
    <a href="javascript:history.back()" class="back-button">← Back </a>
    <h1>Templates for Project: <?= $projectName ?></h1>

    <?php if ($templatesResult->num_rows > 0): ?>
        <?php while ($template = $templatesResult->fetch_assoc()): ?>
            <div class="template-container mb-4 p-3 border rounded shadow-sm">
                <h5>Template ID: <?= $template['id'] ?></h5>

                <!-- Show Template Image -->
                <?php if (!empty($template['template_image_path'])): ?>
                    <img src="<?= htmlspecialchars($template['template_image_path']) ?>" alt="Template Image" class="img-fluid" style="max-width: 400px;">
                <?php else: ?>
                    <p>No image available for this template.</p>
                <?php endif; ?>

                <!-- Edit Form -->
                <form action="update_template.php" method="POST" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $project_id ?>">

                    <div class="form-group">
                        <label for="column_based_<?= $template['id'] ?>">Column Based (e.g., community):</label>
                        <input type="text" class="form-control" name="column_based" id="column_based_<?= $template['id'] ?>" value="<?= htmlspecialchars($template['columns_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="columns_name_<?= $template['id'] ?>">Column Value (e.g., OBC, SC):</label>
                        <input type="text" class="form-control" name="columns_name" id="columns_name_<?= $template['id'] ?>" value="<?= htmlspecialchars($template['columns_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="template_image_<?= $template['id'] ?>">Replace Template Image (optional):</label>
                        <input type="file" class="form-control" name="template_image" id="template_image_<?= $template['id'] ?>" accept=".jpg,.jpeg,.png">
                    </div>

                    <button type="submit" class="btn btn-primary">Update Template</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="no-templates">No templates found for this project.</p>
    <?php endif; ?>

    <?php $templatesQuery->close(); ?>
</div>
</body>
</html>

