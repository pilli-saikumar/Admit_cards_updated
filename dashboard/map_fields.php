<?php 
include("../includes/header.php");
 include("../db_connect.php");
$project_id = $_GET['project_id'];

$template_result = $conn->query("SELECT * FROM project_templates WHERE project_id = $project_id ORDER BY page_order ASC");
$templates = [];
while ($row = $template_result->fetch_assoc()) {
    $templates[] = $row;
}
 
$record_result = $conn->query("SELECT data FROM project_records WHERE project_id = $project_id LIMIT 1");
$record_row = $record_result->fetch_assoc();
$fields = $record_row ? array_keys(json_decode($record_row['data'], true)) : [];


?>

<style>
  /* #template-container {
    position: relative;
    display: inline-block;
    border: 1px solid #ccc;
  }

  .field-box {
    position: absolute;
    border: 2px dashed #007bff;
    padding: 2px;
    cursor: move;
    background-color: rgba(0,123,255,0.2);
  }

  .field-box select,
  .field-box input {
    font-size: 10px;
    display: block;
    margin-bottom: 2px;
    width: 100px;
  }

  .add-btn {
    margin: 15px 0;
    padding: 6px 12px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 4px;
  }

  #template {
    max-width: 100%;
  } */
    .coordinateField {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .coordinateField:first-child {
            margin-bottom: 10px; 
        }
        .template-wrapper {
  position: relative;
  display: inline-block;
  border: 1px solid black;
}

.selectionBox {
  position: absolute;
  border: 2px dashed red;
  display: none;
  pointer-events: none;
  z-index: 9999;
}
.d-flex {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
</style>
 <div class="ml-64  p-5">
<?php if (!empty($templates)) {
    
    
    
    ?>
   <a href="javascript:history.back()" class="back-button">← Back</a>
    <form method="POST" action="save_filed_mapping.php" class="mt-5">
        <h3>Add Coordinates to All Uploaded Templates</h3>
        <?php foreach ($templates as $template) { ?>
            <div class="mt-4 p-3 border">
                <h5>Template: <?php echo basename($template['template_image_path']); ?></h5>
             
                <div class="template-wrapper">
                    <img class="templateImage"
                        src="<?php echo $template['template_image_path']; ?>"
                        data-template-id="<?php echo $template['id']; ?>"
                        style="width:595px; height:842px; cursor:crosshair; pointer-events:auto;">
                    <div class="selectionBox selectionBox<?php echo $template['id']; ?>"></div>
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                </div>

                <div class="coordinateFieldsContainer mt-3" data-template-id="<?php echo $template['id']; ?>">
                    <div class="coordinateField">
                        <input type="hidden" name="template_id[]" value="<?php echo $template['id']; ?>">
                        
                                        <div class="d-flex flex-wrap gap-3">
                    <div class="form-group" style="width: 180px;">
                        <label>Column Name:</label>
                        <select name="column_name[<?php echo $template['id']; ?>][]" class="form-control" required>
                          
                            <?php foreach ($fields as $field) { ?>
                                <option value="<?php echo $field; ?>"><?php echo $field; ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="width: 120px;">
                        <label>X:</label>
                        <input type="number" name="x_position[<?php echo $template['id']; ?>][]" class="form-control x_position" readonly required>
                    </div>

                    <div class="form-group" style="width: 120px;">
                        <label>Y:</label>
                        <input type="number" name="y_position[<?php echo $template['id']; ?>][]" class="form-control y_position" readonly required>
                    </div>

                    <div class="form-group" style="width: 120px;">
                        <label>Width:</label>
                        <input type="number" name="width[<?php echo $template['id']; ?>][]" class="form-control width" readonly required>
                    </div>

                    <div class="form-group" style="width: 120px;">
                        <label>Height:</label>
                        <input type="number" name="height[<?php echo $template['id']; ?>][]" class="form-control height" readonly required>
                    </div>

                    <div class="form-group" style="width: 150px;">
                        <label>Text Box Type:</label>
                        <select name="cell_type[<?php echo $template['id']; ?>][]" class="form-control" required>
                            <option value="Cell">Cell</option>
                            <option value="MultiCell">MultiCell</option>
                        </select>
                    </div>

                    <div class="form-group" style="width: 100px;">
                        <label>Font Size:</label>
                        <select name="font_size[<?php echo $template['id']; ?>][]" class="form-control">
                            <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                        </select>
                    </div>

                    <div class="form-group" style="width: 150px;">
                        <label>Font Type:</label>
                        <select name="font_type[<?php echo $template['id']; ?>][]" class="form-control">
                            <option value="Arial">Arial</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Courier New">Courier New</option>
                        </select>
                    </div>

                    <div class="form-group" style="width: 120px;">
                        <label>Font Color:</label>
                        <input type="color" name="font_color[<?php echo $template['id']; ?>][]" value="#000000" class="form-control">
                    </div>

                    <div class="form-group" style="width: 150px;">
                        <label>Font Style:</label>
                        <select name="font_style[<?php echo $template['id']; ?>][]" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="bold">Bold</option>
                            <option value="italic">Italic</option>
                            <option value="bold italic">Bold Italic</option>
                        </select>
                    </div>
                </div>``
                    </div>
                </div>
                <button type="button" class="btn btn-secondary mt-2 addFieldBtn">+</button>
                <button type="button" class="btn btn-danger mt-2 removeFieldBtn">-</button>
            </div>
        <?php } ?>
        <button type="submit" class="btn btn-success mt-4">Save All Coordinates</button>
    </form>
<?php } ?>
</div>


<script>
$(document).ready(function () {
    $('.templateImage').each(function () {
        const image = $(this);
        const container = image.closest('.template-wrapper');
        const templateId = image.data('template-id');
        const selectionBox = container.find('.selectionBox' + templateId);
        const coordinateFieldsContainer = container.parent().find('.coordinateFieldsContainer[data-template-id="' + templateId + '"]');

        let isDragging = false, startX = 0, startY = 0;

        image.on('mousedown', function (e) {
            e.preventDefault();
            isDragging = true;

            const offset = image.offset();
            startX = e.pageX - offset.left;
            startY = e.pageY - offset.top;

            selectionBox.css({
                left: startX,
                top: startY,
                width: 0,
                height: 0,
                display: 'block'
            });
        });

        image.on('mousemove', function (e) {
            if (!isDragging) return;
            const offset = image.offset();
            const currentX = e.pageX - offset.left;
            const currentY = e.pageY - offset.top;

            const width = currentX - startX;
            const height = currentY - startY;

            selectionBox.css({
                left: Math.min(currentX, startX),
                top: Math.min(currentY, startY),
                width: Math.abs(width),
                height: Math.abs(height)
            });
        });

        $(document).on('mouseup', function (e) {
            if (!isDragging) return;
            isDragging = false;

            const offset = image.offset();
            const endX = e.pageX - offset.left;
            const endY = e.pageY - offset.top;

            const x = Math.round(Math.min(startX, endX));
            const y = Math.round(Math.min(startY, endY));
            const width = Math.round(Math.abs(endX - startX));
            const height = Math.round(Math.abs(endY - startY));

            const newField = coordinateFieldsContainer.find('.coordinateField:last');
            newField.find('.x_position').val(x);
            newField.find('.y_position').val(y);
            newField.find('.width').val(width);
            newField.find('.height').val(height);
        });
    });

    // Add / Remove Field logic
    $(document).on('click', '.addFieldBtn', function () {
        // const container = $(this).siblings('.coordinateFieldsContainer');
        // const newField = container.find('.coordinateField:first').clone();
        // newField.find('input').val('');
        // newField.find('select').val('');
        // container.append(newField);
        const container = $(this).siblings('.coordinateFieldsContainer');
    const firstField = container.find('.coordinateField:first');
    const newField = firstField.clone();

    // Clear all input values
    newField.find('input[type="text"], input[type="number"]').val('');
    newField.find('input[type="hidden"]').val(firstField.find('input[type="hidden"]').val()); // keep template_id
    newField.find('select[name^="column_name"]').val('');

    // Copy font size, type, style, color from first field
    newField.find('select[name^="font_size"]').val(firstField.find('select[name^="font_size"]').val());
    newField.find('select[name^="font_type"]').val(firstField.find('select[name^="font_type"]').val());
    newField.find('select[name^="font_style"]').val(firstField.find('select[name^="font_style"]').val());
    newField.find('input[name^="font_color"]').val(firstField.find('input[name^="font_color"]').val());

    container.append(newField);
    });

    $(document).on('click', '.removeFieldBtn', function () {
        const container = $(this).siblings('.coordinateFieldsContainer');
        if (container.find('.coordinateField').length > 1) {
            container.find('.coordinateField').last().remove();
        }
    });
});
</script>
</body>
</html>
