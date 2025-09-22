<?php
include("../includes/header.php");
include("../db_connect.php");

$project_id = intval($_GET['project_id']);
$filter_column = $_GET['filter_column'] ?? '';

// Get unique column names from uploaded templates
$column_names_query = $conn->query("SELECT DISTINCT columns_name FROM project_templates WHERE project_id = $project_id ORDER BY columns_name ASC");
$columnNames = [];
if ($column_names_query && $column_names_query->num_rows > 0) {
    while ($row = $column_names_query->fetch_assoc()) {
        $columnNames[] = $row['columns_name'];
    }
}

// Fetch templates filtered by column
$template_sql = "SELECT id, template_name, template_image_path, columns_name FROM project_templates WHERE project_id = $project_id";

if (!empty($filter_column)) {
    $safe_col = $conn->real_escape_string($filter_column);
    $template_sql .= " AND columns_name = '$safe_col'";
}
$template_sql .= " ORDER BY columns_name ASC";
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
    }

    .overlay-box {
        box-sizing: border-box;
        cursor: move;
        z-index: 10;
        background: rgba(255, 255, 255, 0.2);
        /* optional */
    }
    .removeFieldBtn {
    float: right;
    background-color: #dc3545 !important;
    color: white !important;
}
</style>
<div class="ml-64 p-5">

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

            <?php foreach ($uploaded_templates as $template):
                $template_id = $template['id'];
                $template_path = $template['template_image_path'];
                $saved_fields = $coordinates[$template_id] ?? [];

            ?>
                <div class="mt-4 p-3 border template-block">
                    <!-- <h5>Template File: <?= basename($template_path); ?></h5>
                    <div style="position:relative; display:inline-block; border:1px solid black;">
                        <img class="templateImage" src="<?= $template_path ?>" data-template-id="<?= $template_id ?>" data-original-width="595" data-original-height="842" style="width:595px; height:842px; cursor:crosshair; border:1px solid black;">
                        <div class="selectionBox<?= $template_id ?>" style="position:absolute; border:2px dashed #000; display:none;"></div>
                        <?php if (!empty($saved_fields)): ?>
                            <?php foreach ($saved_fields as $field):
                                $style = sprintf(
                                    'left:%dpx; top:%dpx; width:%dpx; height:%dpx;',
                                    round($field['x_position'] / 595 * 595),  // scale from original to display width
                                    round($field['y_position'] / 842 * 842),
                                    round($field['width'] / 595 * 595),
                                    round($field['height'] / 842 * 842)
                                );
                            ?>
                                <div class="overlay-box"
                                    title="<?= htmlspecialchars($field['column_name']) ?>"
                                    style="position:absolute; border:2px solid red; <?= $style ?>">
                                    <span style="font-size:10px;background:white;"><?= htmlspecialchars($field['column_name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div> -->
                    <h5 class="text-center">Template File: <?= basename($template_path); ?></h5>
<div style="display: flex; justify-content: center;">
    <div style="position: relative; display: inline-block; border: 1px solid black;">
        <img class="templateImage" 
             src="<?= $template_path ?>" 
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
                <div class="overlay-box"
                    title="<?= htmlspecialchars($field['column_name']) ?>"
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
                                            <input type="number" name="x_position[<?= $template_id ?>][]" value="<?= $field['x_position'] ?>" class="x_position form-control" readonly required>
                                        </div>
                                        <div class="col">
                                            <label>Y:</label>
                                            <input type="number" name="y_position[<?= $template_id ?>][]" value="<?= $field['y_position'] ?>" class="y_position form-control" readonly required>
                                        </div>
                                        <div class="col">
                                            <label>Width:</label>
                                            <input type="number" name="width[<?= $template_id ?>][]" value="<?= $field['width'] ?>" class="width form-control" readonly required>
                                        </div>
                                        <div class="col">
                                            <label>Height:</label>
                                            <input type="number" name="height[<?= $template_id ?>][]" value="<?= $field['height'] ?>" class="height form-control" readonly required>
                                        </div>
                                        <div class="col">
                                            <label>Text Box Type:</label>
                                            <select name="cell_type[<?php echo $template['id']; ?>][]" value="<?= $field['cell_type'] ?>" class="form-control" required>
                                                <option value="Cell">Cell</option>
                                                <option value="MultiCell">MultiCell</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label>Font Size:</label>
                                            <!-- <input type="number" name="font_size[<?= $template_id ?>][]" value="<?= $field['font_size'] ?>" class="form-control"> -->
                                            <select name="font_size[<?php echo $template['id']; ?>][]" class="form-control" value="<?= $field['font_size'] ?>" required>
                                                <?php for ($i = 5; $i <= 36; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                            </select>

                                        </div>
                                        <div class="col">
                                            <label>Font Type:</label>
                                            <select name="font_type[<?php echo $template['id']; ?>][]" value="<?= $field['font_type'] ?>" class="form-control" required>
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
                                            <select name="font_style[<?php echo $template['id']; ?>][]" value="<?= $field['font_style'] ?>" class="form-control" required>
                                                <option value="normal">Normal</option>
                                                <option value="bold">Bold</option>
                                                <option value="italic">Italic</option>
                                                <option value="bold italic">Bold Italic</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="deleted_fields[]" value="" disabled>
                                 <div class="col text-end">
                                    <label>&nbsp;</label><br>
                                    <button type="button" 
                                        class="btn btn-danger removeFieldBtn" 
                                        data-field-id="<?= $field['id'] ?? '' ?>">
                                        &minus;
                                    </button>
                                </div>
                                                                        <!-- <button type="button" class="btn btn-danger mt-2 pull-right removeFieldBtn">-</button> -->
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
                                        <input type="number" name="x_position[<?= $template_id ?>][]" class="x_position form-control" readonly required>
                                    </div>
                                    <div class="col">
                                        <label>Y:</label>
                                        <input type="number" name="y_position[<?= $template_id ?>][]" class="y_position form-control" readonly required>
                                    </div>
                                    <div class="col">
                                        <label>Width:</label>
                                        <input type="number" name="width[<?= $template_id ?>][]" class="width form-control" readonly required>
                                    </div>
                                    <div class="col">
                                        <label>Height:</label>
                                        <input type="number" name="height[<?= $template_id ?>][]" class="height form-control" readonly required>
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
                                <!-- <input type="hidden" name="field_id[<?= $template_id ?>][]" value="<?= $field['id'] ?? '' ?>" class="field_id"> -->
                          <input type="hidden" name="deleted_fields[]" value="" disabled>
                                <!-- <button type="button" class="btn btn-danger mt-2 pull-right removeFieldBtn">-</button> -->
                            <div class="col text-end">
                        <label>&nbsp;</label><br>
                        <button type="button" 
                            class="btn btn-danger removeFieldBtn" 
                            data-field-id="<?= $field['id'] ?? '' ?>">
                            &minus;
                        </button>
                       </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2 addFieldBtn">+</button>

                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-success mt-4">Save All Coordinates</button>
        </form>
    <?php } ?>
</div>

<!-- The JavaScript remains mostly unchanged -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>

<script>
    $(document).ready(function() {

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

            const newField = coordinateFieldsContainer.find('.coordinateField:last');
            newField.find('.x_position').val(Math.round(x));
            newField.find('.y_position').val(Math.round(y));
            newField.find('.width').val(Math.round(width));
            newField.find('.height').val(Math.round(height));

            selectionBox.hide();
        });
    });


       
//        $(document).on('click', '.addFieldBtn', function () {
//     const container = $(this).closest('.template-block').find('.coordinateFieldsContainer');
//     const firstField = container.find('.coordinateField:first');
//     const newField = firstField.clone(true);

//     // Copy values except Column Name
//     firstField.find('input, select').each(function () {
//         const name = $(this).attr('name');
//         const value = $(this).val();

//       //  Skip Column Name field
//         // if (name && !name.includes('column_name')) {
//         //     newField.find(`[name="${name}"]`).val(value);
//         // }
//         if (name) {
//             newField.find(`[name="${name}"]`).val(value);
//         }
//     });

//     // Clear coordinate fields and field ID
//     newField.find('.x_position, .y_position, .width, .height').val('');
//     newField.find('.field_id').val('');
//     newField.find('select[name^="column_name"]').val(''); // Reset column name

//     container.append(newField);
// });
$(document).on('click', '.addFieldBtn', function () {
    const container = $(this).closest('.template-block').find('.coordinateFieldsContainer');
    const firstField = container.find('.coordinateField:first');
    const newField = firstField.clone(true);

    // Get the template ID
    const templateId = container.data('template-id');

    // Count existing fields to determine new index
    const newIndex = container.find('.coordinateField').length;

    // Update name attributes with the new index and reset values where needed
    newField.find('input, select').each(function () {
        const input = $(this);
        const oldName = input.attr('name');

        if (oldName) {
            // Replace the last index in [template_id][index] with newIndex
            const newName = oldName.replace(/\[\d+\]$/, `[${newIndex}]`);
            input.attr('name', newName);

            // Clear coordinate fields and field ID
            if (
                input.hasClass('x_position') ||
                input.hasClass('y_position') ||
                input.hasClass('width') ||
                input.hasClass('height') ||
                input.hasClass('field_id') ||
                input.is('select[name^="column_name"]')
            ) {
                input.val('');
            } else {
                // For font sizes, colors, etc., copy value from first field
                input.val(firstField.find(`[name="${oldName}"]`).val());
            }
        }
    });

    container.append(newField);
});
 
        $(document).on('click', '.removeFieldBtn', function () {
    const button = $(this);
    const fieldGroup = button.closest('.coordinateField');
    const container = button.closest('.coordinateFieldsContainer');
    const fieldId = button.data('field-id');
if (confirm('Are you sure you want to remove this field?')) {

    if (fieldId) {
        // Mark existing field for deletion
        const hiddenInput = $('<input>')
            .attr('type', 'hidden')
            .attr('name', 'deleted_fields[]')
            .val(fieldId);
        container.append(hiddenInput);
    }


    // Always remove field from UI (existing or new)
    fieldGroup.remove();
    }
});

        $('.overlay-box').each(function() {
            const box = $(this);
            box.draggable({
                containment: "parent",
                stop: function() {
                    updateFieldValues(box);
                }
            }).resizable({
                containment: "parent",
                stop: function() {
                    updateFieldValues(box);
                }
            });
        });

        function updateFieldValues(box) {
            const container = box.closest('.template-block');
            const templateId = container.find('.templateImage').data('template-id');

            const left = parseInt(box.css('left'));
            const top = parseInt(box.css('top'));
            const width = box.outerWidth();
            const height = box.outerHeight();

            const columnName = box.attr('title');

            container.find(`.coordinateFieldsContainer[data-template-id="${templateId}"] .coordinateField`).each(function() {
                const field = $(this);
                if (field.find('select[name^="column_name"]').val() === columnName) {
                    field.find('.x_position').val(Math.round(left));
                    field.find('.y_position').val(Math.round(top));
                    field.find('.width').val(Math.round(width));
                    field.find('.height').val(Math.round(height));
                }
            });
        }
        $('form').on('submit', function() {
            $('.overlay-box').each(function() {
                updateFieldValues($(this));
            });
        });

        function initOverlayInteractions() {
            $('.overlay-box').each(function() {
                const box = $(this);
                box.draggable({
                    containment: "parent",
                    stop: function() {
                        updateFieldValues(box);
                    }
                }).resizable({
                    containment: "parent",
                    stop: function() {
                        updateFieldValues(box);
                    }
                });
            });
        }

        $(document).on('change', 'select[name^="column_name"]', function() {
            const selected = $(this).val();
            const templateBlock = $(this).closest('.template-block');
            const matchingBox = templateBlock.find(`.overlay-box[title="${selected}"]`);

            if (matchingBox.length) {
                $('html, body').animate({
                    scrollTop: matchingBox.offset().top - 100
                }, 500);
                matchingBox.css('border', '2px solid blue');

                setTimeout(() => {
                    matchingBox.css('border', '2px solid red');
                }, 2000);
            }
        });
    });




    setTimeout(function() {
        var errBox = document.getElementById('file-success');
        if (errBox) {
            errBox.style.display = 'none';
        }
    }, 5000); // 5000ms = 5 seconds
</script>
</body>

</html>