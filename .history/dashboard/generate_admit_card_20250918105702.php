<?php
include("../includes/header.php");
include("../includes/project_process.php");
include("../db_connect.php");

$project_id = intval($_GET['project_id']);
$filter_column = $_GET['filter_column'] ?? '';

// Get unique column names from uploaded templates
//$column_names_query = $conn->query("SELECT DISTINCT columns_name FROM project_templates WHERE project_id = $project_id ORDER BY columns_name ASC");
$column_names_query = $conn->query("SELECT DISTINCT columns_name FROM project_templates WHERE project_id = $project_id ORDER BY columns_name ASC");
$columnNames = [];
if ($column_names_query && $column_names_query->num_rows > 0) {
    while ($row = $column_names_query->fetch_assoc()) {
        $columnNames[] = $row['columns_name'];
    }
}

// Fetch templates filtered by column
$template_sql = "SELECT id, template_image_path, template_width, template_height,page_order
FROM project_templates 
WHERE project_id = $project_id";

if (!empty($filter_column)) {
    $safe_col = $conn->real_escape_string($filter_column);
    $template_sql .= " AND columns_name = '$safe_col'";
}
//$template_sql .= " ORDER BY columns_name ASC";
$template_sql .= " ORDER BY page_order ASC"; // Order by page_order

$templates = $conn->query($template_sql);
$uploaded_templates = [];
if (!empty($filter_column)) {
    if ($templates && $templates->num_rows > 0) {
        while ($row = $templates->fetch_assoc()) {
            $uploaded_templates[] = $row;
        }
    } else {
        echo "<div id='file-success' class='ml-64 alert alert-warning'>No templates found for this column.</div>";
    }
} else {
    echo "<div id='file-success' class='ml-64 alert alert-warning'>Please select a column to view templates.</div>";
}

// Fetch columns from admit_card_records
$column_definitions = $conn->query("SHOW COLUMNS FROM admit_card_records");
$columns = [];
if ($column_definitions && $column_definitions->num_rows > 0) {
    while ($col = $column_definitions->fetch_assoc()) {
        if (!in_array($col['Field'], ['id', 'project_id'])) {
            $columns[] = $col['Field'];
        }
    }
}

// Fetch saved coordinates for templates
$coordinates = [];
if (!empty($filter_column)) {
    $coords_query = $conn->query("SELECT * FROM field_mappings WHERE project_id = $project_id ");
    if ($coords_query && $coords_query->num_rows > 0) {
        while ($row = $coords_query->fetch_assoc()) {
            $coordinates[$row['template_id']][] = $row;
        }
    }
}

?>
<style>
    .overlay-box {
        box-sizing: border-box;
 cursor: move;
        z-index: 10;
        background: rgba(255, 255, 255, 0.2);
    }
    .removeFieldBtn {
        float: right;
        background-color: #dc3545 !important;
        color: white !important;
    }
</style>
<div class="ml-64 p-3">
   <!-- <a href="javascript:history.back()" class="back-button">← Back</a> -->
    <h2>Select Column to View/Edit Admit Card Template</h2>
    <form method="GET" action="">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <?php foreach ($columnNames as $col): ?>
            <button type="submit" class="btn btn-success mb-2" name="filter_column" value="<?= htmlspecialchars($col) ?>">
                <?= htmlspecialchars($col) ?>
            </button>
        <?php endforeach; ?>
    </form>
 
    <?php if (!empty($uploaded_templates)) { ?>

   
        <button class="btn btn-primary mb-3" onclick="window.open('preview_Admit_Cards.php?project_id=<?= $project_id ?>&filter_column=<?= urlencode($filter_column) ?>', '_blank')">Preview</button>
        <form method="POST" action="save_filed_mapping.php">
            <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <input type="hidden" name="column_filter" value="<?= htmlspecialchars($filter_column) ?>">
            <h3>Edit/Add Coordinates to Templates</h3>
                  <?php $total_pages = count($uploaded_templates);

                                  ?>
            <?php  foreach ($uploaded_templates as $template):
          
                $template_id = $template['id'];
                $template_path = $template['template_image_path'];
               
              //  $template_path = "/uttarpradeshpoliceandrecruitmentpromotionboard\templates\68cb975cb7b9d_Admit Card Proforma HCMT_page-0001 (3).jpg";
                $saved_fields = $coordinates[$template_id] ?? [];
              
            ?>
                <div class="mt-4 p-3 border template-block">
           
                    <!-- <h5 class="text-center">Template File: <?= basename($template_path); ?></h5> -->
                    <!-- <h5 class="text-center">Template File: <?= basename($filter_column).' | '; ?> </h5> -->
                    <h5 class="text-center">
    Template File: <?= basename($filter_column); ?> 
  
    <form method="POST" action="save_filed_mapping.php">
        <input type="hidden" name="template_id" value="<?= $template_id ?>">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">
        <label for="  Page No:">Change  Page Order :</label>
    <select class="page-order-select"
        name="page_order[<?= $template_id ?>]"
        data-template-id="<?= $template_id ?>">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <option value="<?= $i ?>" <?= ($template['page_order'] == $i) ? 'selected' : '' ?>>
            <?= $i ?>
        </option>
    <?php endfor; ?>
</select>
    
    </form>
</h5>

                    <div style="display: flex; justify-content: center;">
                        <div style="position: relative; display: inline-block; border: 1px solid black;">
                            <img class="templateImage" 
                                 src="<?= "../$template_path" ?>" 
                                 data-template-id="<?= $template_id ?>" 
                                 data-original-width="595" 
                                 data-original-height="842" 
                                 style="width:595px; height:842px; cursor:crosshair; border:1px solid black;">
                            <div class="selectionBox<?= $template_id ?>" 
                                 style="position:absolute; border:2px dashed #000; display:none;"></div>

                            <?php if (!empty($saved_fields)): ?>
                                <?php foreach ($saved_fields as $field):
                                    $style = sprintf(
                                        'left:%dpx; top:%dpx; width:%dpx; height:%dpx;',
                                        round($field['x_position'] / 595 * 595),
                                        round($field['y_position'] / 842 * 842),
                                        round($field['width'] / 595 * 595),
                                        round($field['height'] / 842 * 842)
                                    );
                                ?> 
                                    <!-- <div class="overlay-box"
                                         title="<?= htmlspecialchars($field['column_name']) ?>"
                                         data-field-id="<?= $field['id'] ?>" 
                                         style="position:absolute; border:2px solid red; <?= $style ?>">
                                        <span style="font-size:10px;background:white;"><?= htmlspecialchars($field['column_name']) ?></span>
                                    </div> -->
                                    <div class="overlay-box"
     title="<?= htmlspecialchars($field['column_name']) ?>"
     data-field-id="<?= $field['id'] ?>"
     data-font-type="<?= htmlspecialchars($field['font_type']) ?>"
     data-font-color="<?= htmlspecialchars($field['font_color']) ?>"
     data-font-style="<?= htmlspecialchars($field['font_style']) ?>"
     data-cell-type="<?= htmlspecialchars($field['cell_type']) ?>"
     style="position:absolute; border:2px solid red; <?= $style ?>">
    <span style="font-size:10px;background:white;"><?= htmlspecialchars($field['column_name']) ?></span>
</div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
              <div class="coordinateFieldsContainer mt-3" data-template-id="<?= $template_id ?>">
    <?php if (!empty($saved_fields)): ?>
        <?php foreach ($saved_fields as $field): ?>
            <div class="coordinateField mb-3" data-field-id="<?= $field['id'] ?>">
                <input type="hidden" name="template_id[]" value="<?= $template_id ?>">
                <div class="row row-cols-6">
                    
                    <div class="col-md-2">
                        <label>Column Name:</label>
                        <select name="column_name[<?= $template_id ?>][]" class="form-control" required>
                            <option value="">Select Column</option>
                            <?php foreach ($columns as $colName): ?>
                                <option value="<?= htmlspecialchars($colName) ?>" <?= ($colName == $field['column_name']) ? 'selected' : '' ?>><?= htmlspecialchars($colName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>X:</label>
                        <input type="number" name="x_position[<?= $template_id ?>][]" value="<?= $field['x_position'] ?>" class="x_position form-control"  data-field-id="<?= $field['id'] ?>" required>
                    </div>
                    <div class="col">
                        <label>Y:</label>
                        <input type="number" name="y_position[<?= $template_id ?>][]" value="<?= $field['y_position'] ?>" class="y_position form-control"  data-field-id="<?= $field['id'] ?>" required>
                    </div>
                    <div class="col">
                        <label>Width:</label>
                        <input type="number" name="width[<?= $template_id ?>][]" value="<?= $field['width'] ?>" class="width form-control"  data-field-id="<?= $field['id'] ?>" required>
                    </div>
                    <div class="col">
                        <label>Height:</label>
                        <input type="number" name="height[<?= $template_id ?>][]" value="<?= $field['height'] ?>" class="height form-control"  data-field-id="<?= $field['id'] ?>" required>
                    </div>
                    <!-- <div class="col">
                        <label>Text Box Type:</label>
                        <select name="cell_type[<?= $template_id ?>][]" class="form-control"  data-field-id="<?= $field['id'] ?>" required>
                            <option value="Cell" <?= $field['cell_type'] == 'Cell' ? 'selected' : '' ?>>Cell</option>
                            <option value="MultiCell" <?= $field['cell_type'] == 'MultiCell' ? 'selected' : '' ?>>MultiCell</option>
                        </select>
                    </div> -->
                  <div class="col">
                        <label>Text Box Type:</label>
                        <select name="cell_type[<?= $template_id ?>][]" class="form-control" data-field-id="<?= $field['id'] ?>" required>
                             <option value="Cell" <?= $field['cell_type'] == 'Cell' ? 'selected' : '' ?>>Cell</option>
                            <option value="MultiCell" <?= $field['cell_type'] == 'MultiCell' ? 'selected' : '' ?>>MultiCell</option>
                        </select>
                    </div>

                    <div class="col">
                        <label>Line Break After Characters:</label>
                        <input type="number" name="line_break[<?= $template_id ?>][]" class="form-control" min="1" value="<?= isset($field['line_break']) ? $field['line_break'] : 60 ?>" required>
                    </div>
                    <div class="col">
                        <label>Font Size:</label>
                        <select name="font_size[<?= $template_id ?>][]" class="form-control"  data-field-id="<?= $field['id'] ?>" required>
                            <!-- <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\" ".($field['font_size'] == $i ? 'selected' : $i == 9).">$i</option>"; ?> -->
                                                <?php for($i = 5; $i <= 36; $i++) {
                            $selected = (isset($field['font_size']) && $field['font_size'] == $i) || (!isset($field['font_size']) && $i == 9) ? 'selected' : '';
                            echo "<option value=\"$i\" $selected>$i</option>";
                        }  ?>
                                            
                        </select>
                    </div>
                    <div class="col">
                        <label>Font Type:</label>
                        <select name="font_type[<?= $template_id ?>][]"  data-field-id="<?= $field['id'] ?>"  class="form-control">
                            <option value="Arial" <?= $field['font_type'] == 'Arial' ? 'selected' : '' ?>>Arial</option>
                            <option value="Times New Roman" <?= $field['font_type'] == 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
                            <option value="Verdana" <?= $field['font_type'] == 'Verdana' ? 'selected' : '' ?>>Verdana</option>
                            <option value="Helvetica" <?= $field['font_type'] == 'Helvetica' ? 'selected' : '' ?>>Helvetica</option>
                            <option value="Courier New" <?= $field['font_type'] == 'Courier New' ? 'selected' : '' ?>>Courier New</option>
                        </select>
                    </div>
                    <div class="col">
                        <label>Font Color:</label>
                        <input type="color" name="font_color[<?= $template_id ?>][]" value="<?= $field['font_color'] ?>"  data-field-id="<?= $field['id'] ?>"  class="form-control">
                    </div>
                    <div class="col">
                        <label>Font Style:</label>
                        <select name="font_style[<?= $template_id ?>][]" class="form-control"  data-field-id="<?= $field['id'] ?>" required>
                            <option value="normal" <?= $field['font_style'] == 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="bold" <?= $field['font_style'] == 'bold' ? 'selected' : '' ?>>Bold</option>
                            <option value="italic" <?= $field['font_style'] == 'italic' ? 'selected' : '' ?>>Italic</option>
                            <option value="bold italic" <?= $field['font_style'] == 'bold italic' ? 'selected' : '' ?>>Bold Italic</option>
                        </select>
                    </div>
                    <input type="hidden" name="deleted_fields[]" value="" disabled>
                    <input type="hidden" name="field_id[<?= $template_id ?>][]" value="<?= $field['id'] ?>"  data-field-id="<?= $field['id'] ?>" class="field_id">
                    <div class="col text-end">
                        <button type="button" class="btn btn-danger removeFieldBtn" data-field-id="<?= $field['id'] ?>">−</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- Empty state: Allow adding new fields -->
         <div class="coordinateField mb-3">
        <input type="hidden" name="template_id[]" value="<?= $template_id ?>">
        <div class="row row-cols-6">
            <!-- Column Name -->
            <div class="col-md-2">
                <label>Column Name:</label>
                <select name="column_name[<?= $template_id ?>][]" class="form-control" required>
                    <option value="">Select Column</option>
                    <?php foreach ($columns as $colName): ?>
                        <option value="<?= htmlspecialchars($colName) ?>"><?= htmlspecialchars($colName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- X Position -->
            <div class="col">
                <label>X:</label>
                <input type="number" name="x_position[<?= $template_id ?>][]" value="" class="x_position form-control" required>
            </div>

            <!-- Y Position -->
            <div class="col">
                <label>Y:</label>
                <input type="number" name="y_position[<?= $template_id ?>][]" value="" class="y_position form-control" required>
            </div>

            <!-- Width -->
            <div class="col">
                <label>Width:</label>
                <input type="number" name="width[<?= $template_id ?>][]" value="" class="width form-control" required>
            </div>

            <!-- Height -->
            <div class="col">
                <label>Height:</label>
                <input type="number" name="height[<?= $template_id ?>][]" value="" class="height form-control" required>
            </div>

            <!-- Text Box Type -->
            <!-- <div class="col">
                <label>Text Box Type:</label>
                <select name="cell_type[<?= $template_id ?>][]" class="form-control" required>
                    <option value="Cell">Cell</option>
                    <option value="MultiCell">MultiCell</option>
                </select>
            </div> -->

                                        <div class="col">
                        <label>Text Box Type:</label>
                        <select name="cell_type[<?= $template_id ?>][]" class="form-control" data-field-id="<?= $field['id'] ?>" required>
                     
                            <option value="Cell" selected>Cell</option>   
                        <option value="MultiCell" >MultiCell</option>
                        </select>
                    </div>

                    <div class="col">
                        <label>Line Break After Characters:</label>
                        <input type="number" name="line_break[<?= $template_id ?>][]" class="form-control" min="1" value="<?= isset($field['line_break']) ? $field['line_break'] : 60 ?>" required>
                    </div>
            <!-- Font Size -->
            <div class="col">
                <label>Font Size:</label>
                <select name="font_size[<?= $template_id ?>][]" class="form-control" required>
                    <!-- <?php for ($i = 5; $i <= 36; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?> -->
                         <?php for($i = 5; $i <= 36; $i++) {
    $selected = (isset($field['font_size']) && $field['font_size'] == $i) || (!isset($field['font_size']) && $i == 9) ? 'selected' : '';
    echo "<option value=\"$i\" $selected>$i</option>";
}  ?>
                </select>
            </div>

            <!-- Font Type -->
            <div class="col">
                <label>Font Type:</label>
                <select name="font_type[<?= $template_id ?>][]" class="form-control">
                    <option value="Arial">Arial</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Helvetica">Helvetica</option>
                    <option value="Courier New">Courier New</option>
                </select>
            </div>

            <!-- Font Color -->
            <div class="col">
                <label>Font Color:</label>
                <input type="color" name="font_color[<?= $template_id ?>][]" value="#000000" class="form-control">
            </div>

            <!-- Font Style -->
            <div class="col">
                <label>Font Style:</label>
                <select name="font_style[<?= $template_id ?>][]" class="form-control" required>
                    <option value="normal">Normal</option>
                    <option value="bold">Bold</option>
                    <option value="italic">Italic</option>
                    <option value="bold italic">Bold Italic</option>
                </select>
            </div>

            <!-- Hidden Fields for Field ID and Deletion Flag -->
            <input type="hidden" name="field_id[<?= $template_id ?>][]" value="">
            <input type="hidden" name="deleted_fields[]" value="" disabled>

            <div class=" col align-self-end">
                <button type="button" class="btn btn-danger removeFieldBtn">−</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Button to add new field -->
    <button type="button" class="btn btn-secondary mt-2 addFieldBtn">+</button>
</div>

                    <!-- <div class="coordinateFieldsContainer mt-3" data-template-id="<?= $template_id ?>">
                        <?php if (!empty($saved_fields)): ?>
                            <?php foreach ($saved_fields as $field): ?>
                                <div class="coordinateField mb-3">
                                    <input type="hidden" name="template_id[]" value="<?= $template_id ?>">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label>Column Name:</label>
                                            <select name="column_name[<?= $template_id ?>][]" class="form-control" required>
                                                <option value="">Select Column</option>
                                                <?php foreach ($columns as $colName): ?>
                                                    <option value="<?= htmlspecialchars($colName) ?>" <?= ($colName == $field['column_name']) ? 'selected' : '' ?>><?= htmlspecialchars($colName) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label>X:</label>
                                            <input type="number" name="x_position[<?= $template_id ?>][]" value="<?= $field['x_position'] ?>" class="x_position form-control" required>
                                        </div>
                                        <div class="col">
                                            <label>Y:</label>
                                            <input type="number" name="y_position[<?= $template_id ?>][]" value="<?= $field['y_position'] ?>" class="y_position form-control" required>
                                        </div>
                                        <div class="col">
                                            <label>Width:</label>
                                            <input type="number" name="width[<?= $template_id ?>][]" value="<?= $field['width'] ?>" class="width form-control" required>
                                        </div>
                                        <div class="col">
                                            <label>Height:</label>
                                            <input type="number" name="height[<?= $template_id ?>][]" value="<?= $field['height'] ?>" class="height form-control" required>
                                        </div>
                                        <div class="col">
                                            <label>Text Box Type:</label>
                                            <select name="cell_type[<?php echo $template['id']; ?>][]" class="form-control" required>
                                                <option value="Cell">Cell</option>
                                                <option value="MultiCell">MultiCell</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label>Font Size:</label>
                                            <select name="font_size[<?php echo $template['id']; ?>][]" class="form-control" required>
                                                <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label>Font Type:</label>
                                            <select name="font_type[<?php echo $template['id']; ?>][]" class="form-control" required>
                                                <option value="Arial">Arial</option>
                                                <option value="Times New Roman">Times New Roman</option>
                                                <option value="Verdana">Verdana</option>
                                                <option value="Helvetica">Helvetica</option>
                                                <option value="Courier New">Courier New</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label>Font Color:</label>
                                            <input type="color" name="font_color[<?= $template_id ?>][]" value="<?= $field['font_color'] ?>" class="form-control">
                                        </div>
                                        <div class="col">
                                            <label>Font Style:</label>
                                            <select name="font_style[<?php echo $template['id']; ?>][]" class="form-control" required>
                                                <option value="normal">Normal</option>
                                                <option value="bold">Bold</option>
                                                <option value="italic">Italic</option>
                                                <option value="bold italic">Bold Italic</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="deleted_fields[]" value="" disabled>
                                        <input type="hidden" name="field_id[<?= $template_id ?>][]" value="<?= $field['id'] ?>" class="field_id">
                                        <div class="col text-end">
                                            <label>&nbsp;</label><br>
                                            <button type="button" 
                                                    class="btn btn-danger removeFieldBtn" 
                                                    data-field-id="<?= $field['id'] ?? '' ?>">
                                                &minus;
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="coordinateField mb-3">
                                <input type="hidden" name="template_id[]" value="<?= $template_id ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label>Column Name:</label>
                                        <select name="column_name[<?= $template_id ?>][]" class="form-control" required>
                                            <option value="">Select Column</option>
                                            <?php foreach ($columns as $colName): ?>
                                                <option value="<?= htmlspecialchars($colName) ?>"><?= htmlspecialchars($colName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <label>X:</label>
                                        <input type="number" name="x_position[<?= $template_id ?>][]" class="x_position form-control" required>
                                    </div>
                                    <div class="col">
                                        <label>Y:</label>
                                        <input type="number" name="y_position[<?= $template_id ?>][]" class="y_position form-control" required>
                                    </div>
                                    <div class="col">
                                        <label>Width:</label>
                                        <input type="number" name="width[<?= $template_id ?>][]" class="width form-control" required>
                                    </div>
                                    <div class="col">
                                        <label>Height:</label>
                                        <input type="number" name="height[<?= $template_id ?>][]" class="height form-control" required>
                                    </div>
                                    <div class="col">
                                        <label>Text Box Type:</label>
                                        <select name="cell_type[<?php echo $template['id']; ?>][]" class="form-control" required>
                                            <option value="Cell">Cell</option>
                                            <option value="MultiCell">MultiCell</option>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <label>Font Size:</label>
                                        <select name="font_size[<?php echo $template['id']; ?>][]" class="form-control" required>
                                            <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <label>Font Type:</label>
                                        <select name="font_type[<?php echo $template['id']; ?>][]" class="form-control">
                                            <option value="Arial">Arial</option>
                                            <option value="Times New Roman">Times New Roman</option>
                                            <option value="Verdana">Verdana</option>
                                            <option value="Helvetica">Helvetica</option>
                                            <option value="Courier New">Courier New</option>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <label>Font Color:</label>
                                        <input type="color" name="font_color[<?= $template_id ?>][]" value="#000000" class="form-control">
                                    </div>
                                    <div class="col">
                                        <label>Font Style:</label>
                                        <select name="font_style[<?php echo $template['id']; ?>][]" class="form-control" required>
                                            <option value="normal">Normal</option>
                                            <option value="bold">Bold</option>
                                            <option value="italic">Italic</option>
                                            <option value="bold italic">Bold Italic</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="deleted_fields[]" value="" disabled>
                                     <input type="hidden" name="field_id[<?= $template_id ?>][]" value="" class="field_id">
                                    <div class="col text-end">
                                        <label>&nbsp;</label><br>
                                        <button type="button" 
                                                class="btn btn-danger removeFieldBtn">
                                            &minus;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2 addFieldBtn">+</button>
                </div> -->
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success mt-4">Save All Coordinates</button>
        </form>
    <?php } ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>


<script>
$(document).ready(function () {
    $('.templateImage').each(function () {
        const image = $(this);
        const container = image.parent();
        const templateId = image.data('template-id');
        const originalWidth = image.data('original-width');
        const originalHeight = image.data('original-height');
        const selectionBox = container.find('.selectionBox' + templateId);
        const coordinateFieldsContainer = container.closest('.template-block').find('.coordinateFieldsContainer[data-template-id="' + templateId + '"]');

        let isDragging = false;
        let startX = 0, startY = 0;

        image.on('mousedown', function (e) {
            const rect = image[0].getBoundingClientRect();
            startX = e.clientX - rect.left;
            startY = e.clientY - rect.top;
            isDragging = true;

            selectionBox.css({
                left: startX,
                top: startY,
                width: 0,
                height: 0,
                display: 'block'
            });
        });

        $(document).on('mousemove.template_' + templateId, function (e) {
            if (!isDragging) return;
            const rect = image[0].getBoundingClientRect();
            const currentX = e.clientX - rect.left;
            const currentY = e.clientY - rect.top;

            const width = currentX - startX;
            const height = currentY - startY;

            selectionBox.css({
                left: Math.min(currentX, startX),
                top: Math.min(currentY, startY),
                width: Math.abs(width),
                height: Math.abs(height)
            });
        });

        $(document).on('mouseup.template_' + templateId, function (e) {
            if (!isDragging) return;
            isDragging = false;

            const renderedWidth = image.width();
            const renderedHeight = image.height();
            const scaleX = originalWidth / renderedWidth;
            const scaleY = originalHeight / renderedHeight;

            const rect = image[0].getBoundingClientRect();
            const endX = e.clientX - rect.left;
            const endY = e.clientY - rect.top;

            const x = Math.min(startX, endX) * scaleX;
            const y = Math.min(startY, endY) * scaleY;
            const width = Math.abs(endX - startX) * scaleX;
            const height = Math.abs(endY - startY) * scaleY;

            // Update the last field instead of creating a new one
            const newField = coordinateFieldsContainer.find('.coordinateField:last');
            newField.find('.x_position').val(Math.round(x));
            newField.find('.y_position').val(Math.round(y));
            newField.find('.width').val(Math.round(width));
            newField.find('.height').val(Math.round(height));

            // Update the overlay box position
            const overlayBox = container.find('.overlay-box[data-field-id="' + newField.find('.field_id').val() + '"]');
            overlayBox.css({
                left: Math.round(x) + 'px',
                top: Math.round(y) + 'px',
                width: Math.round(width) + 'px',
                height: Math.round(height) + 'px'
            });

            selectionBox.hide();
           const clonedBox = selectionBox.clone().removeClass('selectionBox').addClass('overlay-box');
clonedBox.attr('data-field-id', newField.find('.field_id').val());
clonedBox.css('position', 'absolute'); // ensure position is set
container.append(clonedBox);

// Reset the temporary selection box for the next use
selectionBox.css({
    left: 0,
    top: 0,
    width: 0,
    height: 0,
    display: 'none'
});
        });
    });

    // $(document).on('click', '.addFieldBtn', function () {
    //     const container = $(this).closest('.template-block').find('.coordinateFieldsContainer');
    //     const firstField = container.find('.coordinateField:first');
    //     const newField = firstField.clone(false);

    //     const uniqueId = 'new_' + Date.now();
    //    console.log(uniqueId);
    //     newField.find('input, select').each(function () {
    //         const input = $(this);
    //         if (input.is('select')) input.prop('selectedIndex', 0);
    //         else input.val('');
    //     });

    //     // Assign unique ID
    //     newField.find('.field_id').val(uniqueId);
    //     container.append(newField);
    // });
    // $('.addFieldBtn').on('click', function() {
    //     const templateId = $(this).closest('.coordinateFieldsContainer').data('template-id');
    //     const newFieldHTML = `
    //         <div class="coordinateField mb-3">
    //             <input type="hidden" name="template_id[]" value="${templateId}">
    //             <div class="row">
    //                 <div class="col-md-3">
    //                     <label>Column Name:</label>
    //                     <select name="column_name[${templateId}][]" class="form-control" required>
    //                         <option value="">Select Column</option>
    //                         <?php foreach ($columns as $colName): ?>
    //                             <option value="<?= htmlspecialchars($colName) ?>"><?= htmlspecialchars($colName) ?></option>
    //                         <?php endforeach; ?>
    //                     </select>
    //                 </div>
    //                 <div class="col">
    //                     <label>X:</label>
    //                     <input type="number" name="x_position[${templateId}][]" class="x_position form-control" required>
    //                 </div>
    //                 <div class="col">
    //                     <label>Y:</label>
    //                     <input type="number" name="y_position[${templateId}][]" class="y_position form-control" required>
    //                 </div>
    //                 <div class="col">
    //                     <label>Width:</label>
    //                     <input type="number" name="width[${templateId}][]" class="width form-control" required>
    //                 </div>
    //                 <div class="col">
    //                     <label>Height:</label>
    //                     <input type="number" name="height[${templateId}][]" class="height form-control" required>
    //                 </div>
    //                 <!-- Text Box Type -->
    //             <div class="col">
    //                 <label>Text Box Type:</label>
    //                 <select name="cell_type[${templateId}][]" class="form-control" required>
    //                     <option value="Cell">Cell</option>
    //                     <option value="MultiCell">MultiCell</option>
    //                 </select>
    //             </div>
    //                 <div class="col">
    //                     <label>Font Size:</label>
    //                     <select name="font_size[${templateId}][]" class="form-control" required>
    //                         <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\">$i</option>"; ?>
    //                     </select>
    //                 </div>
    //                 <!-- Font Color, Style, etc. -->
    //                  <div class="col">
    //                     <label>Font Type:</label>
    //                     <select name="font_type[${templateId}][]" class="form-control" required>
    //                         <option value="Arial">Arial</option>
    //                         <option value="Times New Roman">Times New Roman</option>
    //                         <option value="Verdana">Verdana</option>
    //                         <option value="Helvetica">Helvetica</option>
    //                         <option value="Courier New">Courier New</option>
    //                     </select>
    //                 </div>
    //                 <!-- Font Color -->
    //                 <div class="col">
    //                     <label>Font Color:</label>
    //                     <input type="color" name="font_color[${templateId}][]" value="#000000" class="form-control">
    //                 </div>
    //                 <!-- Font Style -->
    //                 <div class="col">
    //                     <label>Font Style:</label>
    //                     <select name="font_style[${templateId}][]" class="form-control" required>
    //                         <option value="normal">Normal</option>
    //                         <option value="bold">Bold</option>
    //                         <option value="italic">Italic</option>
    //                         <option value="bold italic">Bold Italic</option>
    //                     </select>
    //                 </div>
    //                 <div class="col text-end">
    //                     <button type="button" class="btn btn-danger removeFieldBtn">−</button>
    //                 </div>
    //             </div>
    //         </div>
    //     `;
        
    //     $(this).closest('.coordinateFieldsContainer').append(newFieldHTML);
    // });
$('.addFieldBtn').on('click', function () {
    const templateId = $(this).closest('.coordinateFieldsContainer').data('template-id');
    const uniqueId = 'new_' + Date.now(); // temporary unique ID for JS usage

    const newFieldHTML = `
        <div class="coordinateField mb-3" data-field-id="${uniqueId}">
            <input type="hidden" name="template_id[]" value="${templateId}">
            <input type="hidden" name="field_id[${templateId}][]" value="${uniqueId}" class="field_id">

            <div class="row row-cols-6">
                <div class="col-md-2">
                    <label>Column Name:</label>
                    <select name="column_name[${templateId}][]" class="form-control" required>
                        <option value="">Select Column</option>
                        <?php foreach ($columns as $colName): ?>
                            <option value="<?= htmlspecialchars($colName) ?>"><?= htmlspecialchars($colName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label>X:</label>
                    <input type="number" name="x_position[${templateId}][]" class="x_position form-control" required data-field-id="${uniqueId}">
                </div>
                <div class="col">
                    <label>Y:</label>
                    <input type="number" name="y_position[${templateId}][]" class="y_position form-control" required data-field-id="${uniqueId}">
                </div>
                <div class="col">
                    <label>Width:</label>
                    <input type="number" name="width[${templateId}][]" class="width form-control" required>
                </div>
                <div class="col">
                    <label>Height:</label>
                    <input type="number" name="height[${templateId}][]" class="height form-control" required>
                </div>
               <div class="col">
                        <label>Text Box Type:</label>
                        <select name="cell_type[${templateId}][]" class="form-control cell-type-select" required data-field-id="${uniqueId}">
                               <option value="Cell" selected>Cell</option>   
                            <option value="MultiCell" >MultiCell</option>
                        </select>
                    </div>
                    <div class="col line-break-container">
                        <label>Line Break After Characters:</label>
                        <input type="number" name="line_break[${templateId}][]" class="form-control" min="1" value="60"  data-field-id="${uniqueId}">
                    </div>
                <div class="col">
                    <label>Font Size:</label>
                     <select name="font_size[${templateId}][]" class="form-control" required>
            <?php for ($i = 5; $i <= 36; $i++): ?>
                <option value="<?= $i ?>" <?= $i == 9 ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>
                </div>
                <div class="col">
                    <label>Font Type:</label>
                    <select name="font_type[${templateId}][]" class="form-control" required data-field-id="${uniqueId}">
                        <option value="Arial">Arial</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Helvetica">Helvetica</option>
                        <option value="Courier New">Courier New</option>
                    </select>
                </div>
                <div class="col">
                    <label>Font Color:</label>
                    <input type="color" name="font_color[${templateId}][]" value="#000000" class="form-control" data-field-id="${uniqueId}">
                </div>
                <div class="col">
                    <label>Font Style:</label>
                    <select name="font_style[${templateId}][]" class="form-control" required data-field-id="${uniqueId}">
                        <option value="normal">Normal</option>
                        <option value="bold">Bold</option>
                        <option value="italic">Italic</option>
                        <option value="bold italic">Bold Italic</option>
                    </select>
                </div>
                <div class="col text-end">
                    <button type="button" class="btn btn-danger removeFieldBtn">−</button>
                </div>
            </div>
        </div>
    `;

    // Insert new field **before** the Add button
    $(this).before(newFieldHTML);
});
    $(document).on('click', '.removeFieldBtn', function () {
        const button = $(this);
        const fieldGroup = button.closest('.coordinateField');
        const container = button.closest('.coordinateFieldsContainer');
        const fieldId = button.data('field-id');

        if (confirm('Are you sure you want to remove this field?')) {
            if (fieldId) {
                const hiddenInput = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'deleted_fields[]')
                    .val(fieldId);
                container.append(hiddenInput);
            }
            fieldGroup.remove();
        }
    });

//     function updateFieldValues(box) {
//         const container = box.parents('.template-block');
//         const fieldId = box.data('field-id')?.toString().trim();
//         console.log("filed_id",fieldId);
//         const matchedField = container.find('.coordinateFieldsContainer .coordinateField')
//             // .filter(function () {
//             //     const currentId = $(this).find('.field_id').val()?.toString().trim();
//             //     console.log(currentId);
//             //     return currentId === fieldId;
//             // }).first();
//             .filter(function () {
//     const currentId = $(this).find('.field_id').val()?.toString().trim();
//     console.log("comparing currentId:", currentId, "with fieldId:", fieldId);
//     return currentId === fieldId;
// })

//         if (matchedField.length) {
//             const x = Math.round(parseFloat(box.css('left')) || 0);
//             const y = Math.round(parseFloat(box.css('top')) || 0);
//             const width = Math.round(box.outerWidth());
//             const height = Math.round(box.outerHeight());

//             // Update existing values
//             matchedField.find('.x_position').val(x);
//             matchedField.find('.y_position').val(y);
//             matchedField.find('.width').val(width);
//             matchedField.find('.height').val(height);

//             const columnValue = matchedField.find('select[name^="column_name"]').val();
//             if (columnValue) {
//                 box.attr('title', columnValue);
//                 box.find('span').text(columnValue);
//             }
//         }
//     }

    // $('.overlay-box').each(function () {
    //     $(this).draggable({
    //         containment: "parent",
    //         stop: function () {
    //             updateFieldValues($(this));
    //         }
    //     }).resizable({
    //         containment: "parent",
    //         stop: function () {
    //             updateFieldValues($(this));
    //         }
    //     });
    // });

    // $('form').on('submit', function () {
    //     $('.overlay-box').each(function () {
    //         updateFieldValues($(this));
    //     });
    // });
// $(".overlay-box").draggable({
//     containment: "parent", // Keeps the box within the image
   
//     stop: function(event, ui) {
//         const fieldId = $(this).data('field-id');
//         console.log(fieldId);
//         const newLeft = ui.position.left;
//         const newTop = ui.position.top;
//         const imgWidth = $(".templateImage").width();
//         const imgHeight = $(".templateImage").height();

//         // Calculate relative positions as percentages
//         const xPercentage = (newLeft / imgWidth) * 595;
//         const yPercentage = (newTop / imgHeight) * 842;
//       console.log("New x position (percentage):", xPercentage);
//         console.log("New y position (percentage):", yPercentage);
//         // Update the field's position (for the edit form)
//         $(`input[name="x_position[]"][value="${fieldId}"]`).val(xPercentage);
//         $(`input[name="y_position[]"][value="${fieldId}"]`).val(yPercentage);

//         // Update other field properties
//         const fontType = $(this).data('font-type');
//         const fontColor = $(this).data('font-color');
//         const fontStyle = $(this).data('font-style');
//            const cellType = $(this).data('cell-type');
//  // Log the field properties being updated
//         console.log("Font Type:", fontType);
//         console.log("Font Color:", fontColor);
//         console.log("Font Style:", fontStyle);
//         console.log("Cell Type:", cellType);
//         // Update Font Type
//         $(`select[name="font_type[]"][value="${fieldId}"]`).val(fontType);

//         // Update Font Color
//         $(`input[name="font_color[]"][value="${fieldId}"]`).val(fontColor);

//         // Update Font Style
//         $(`select[name="font_style[]"][value="${fieldId}"]`).val(fontStyle);

//         // If you need to update specific fields related to the position change (for example, font size), you can do it here too.
//         // Optionally, you can also add a `data` attribute to the box for things like font color, etc.
//     }
// });
$(".overlay-box").draggable({
    containment: "parent", // Keeps the box within the image
    stop: function(event, ui) {
        const $box = $(this);
        const fieldId = $box.data('field-id');

        // Confirm fieldId is found
        if (!fieldId) {
            console.warn("No field-id found on .overlay-box");
            return;
        }

        // Get image container size (used for relative scaling)
        const imgWidth = $(".templateImage").width();
        const imgHeight = $(".templateImage").height();

        // Get new position of the box
        const newLeft = ui.position.left;
        const newTop = ui.position.top;

        // Convert position to fixed scale (for PDF or print)
        const xScaled = (newLeft / imgWidth) * 595;
        const yScaled = (newTop / imgHeight) * 842;

        // Debug log
       

        // Build field selectors (using ^ to support name="x_position[xxx][]")
        const xSelector = `input[name^="x_position"][data-field-id="${fieldId}"]`;
        const ySelector = `input[name^="y_position"][data-field-id="${fieldId}"]`;
        const fontTypeSelector = `select[name^="font_type"][data-field-id="${fieldId}"]`;
        const fontColorSelector = `input[name^="font_color"][data-field-id="${fieldId}"]`;
        const fontStyleSelector = `select[name^="font_style"][data-field-id="${fieldId}"]`;
        const cellTypeSelector = `select[name^="cell_type"][data-field-id="${fieldId}"]`;
       
        // Update corresponding fields
 $(xSelector).val(Math.round(xScaled));
$(ySelector).val(Math.round(yScaled));
        $(fontTypeSelector).val($box.data('font-type'));
        $(fontColorSelector).val($box.data('font-color'));
        $(fontStyleSelector).val($box.data('font-style'));
     //   $(cellTypeSelector).val($box.data('cell-type'));
//         const cellType = $box.data('cell-type');
// if (cellType === 'Cell' || cellType === 'MultiCell') {
//     $(cellTypeSelector).val(cellType);
// } else {
//     console.warn("Unknown cell_type for fieldId:", fieldId, "→", cellType);
// }
        let cellType = $box.data("cell-type");
        if (cellType !== "Cell" && cellType !== "MultiCell") {
            console.warn(`⚠️ Unknown or missing cell_type for fieldId: ${fieldId} → Received: ${cellType}, using default: "Cell"`);
            cellType = "Cell"; // fallback default
        }
        $(cellTypeSelector).val(cellType);

        // Optional: live update label
        const selectedColumn = $(`select[name^="column_name"][data-field-id="${fieldId}"]`).val();
        if (selectedColumn) {
            $box.attr('title', selectedColumn);
            $box.find('span').text(selectedColumn);
        }

        console.log("Updated fields for fieldId:", fieldId);
    }
});

    // Scroll to box when column selected
    // $(document).on('change', 'select[name^="column_name"]', function () {
    //     const selected = $(this).val();
    //     const templateBlock = $(this).closest('.template-block');
    //     const matchingBox = templateBlock.find(`.overlay-box[title="${selected}"]`);

    //     if (matchingBox.length) {
    //         $('html, body').animate({
    //             scrollTop: matchingBox.offset().top - 100
    //         }, 500);
    //         matchingBox.css('border', '2px solid blue');

    //         setTimeout(() => {
    //             matchingBox.css('border', '2px solid red');
    //         }, 2000);
    //     }
    // });

    setTimeout(function () {
        var errBox = document.getElementById('file-success');
        if (errBox) {
            errBox.style.display = 'none';
        }
    }, 5000);
});
$(document).ready(function () {
    $('.page-order-select').on('change', function () {
       
        const templateId = $(this).data('template-id');
        const pageOrder = $(this).val();

        $.ajax({
            url: 'update_page_order.php',
            method: 'POST',
            data: {
                template_id: templateId,
                page_order: pageOrder
            },
            success: function (response) {
                
                      //  let msgDiv = $('<div class="alert alert-success text-center" style="position:fixed;top:50px;left:50%;transform:translateX(-50%);z-index:9999;">Page order updated successfully!</div>');
              let msgDiv = $('<div class="aalert alert-success" text-center" style="position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:9999;">Page order updated successfully!</div>');
$('body').append(msgDiv);
msgDiv.fadeIn();

// Optional: remove after a few seconds without reload
setTimeout(function () {
    msgDiv.fadeOut(function () {
        $(this).remove();
        window.location.reload(); // Reload after hiding the message
    });
}, 2000);
            },
            error: function () {
                alert('Failed to update page order');
            }
        });
    });
});
</script>



</body>

</html>