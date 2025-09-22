<?php
include("../includes/header.php");
include("../db_connect.php");

$project_id = intval($_GET['project_id']);

// Fetch existing field mappings grouped by template_id
$fieldMappingsQuery = $conn->prepare("SELECT * FROM field_mappings WHERE project_id = ?");
$fieldMappingsQuery->bind_param("i", $project_id);
$fieldMappingsQuery->execute();
$fieldMappingsResult = $fieldMappingsQuery->get_result();
$fieldMappingsByTemplate = [];
while ($row = $fieldMappingsResult->fetch_assoc()) {
    $fieldMappingsByTemplate[$row['template_id']][] = $row;
}
$fieldMappingsQuery->close();

// Fetch templates
$templateQuery = $conn->prepare("SELECT id, template_name, template_image_path FROM project_templates WHERE project_id = ?");
$templateQuery->bind_param("i", $project_id);
$templateQuery->execute();
$templateResult = $templateQuery->get_result();
$templates = [];
while ($row = $templateResult->fetch_assoc()) {
    $templates[] = $row;
}
$templateQuery->close();

// Fetch admit card columns
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM admit_card_records");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}
?>

<div class="ml-64 p-5">
    <h2 class="mb-4">Update Coordinates for Template:</h2>

    <?php if (!empty($templates)): ?>
    <form method="POST" action="save_filed_mapping.php">
        <input type="hidden" name="project_id" value="<?= $project_id ?>">

        <?php foreach ($templates as $template):
            $template_id = $template['id'];
            $fieldMappings = $fieldMappingsByTemplate[$template_id] ?? [];
        ?>
        <div class="mt-4 p-3 border">
            <h5 class="mb-3">Template: <?= htmlspecialchars($template['template_name']) ?></h5>
           
            <div style="position:relative; display:inline-block;  border:1px solid black;">
                    <img class="templateImage"
                        src="<?= htmlspecialchars($template['template_image_path']) ?>"
                        data-template-id="<?= $template_id ?>"
                        style="width:595px; height:842px; cursor:crosshair;">

                    <div class="selectionBox selectionBox<?= $template_id ?>" style="position:absolute; border:2px dashed #000; display:none;"></div>
                </div>

            <div class="coordinateFieldsContainer mt-4" data-template-id="<?= $template_id ?>">
                <?php if (empty($fieldMappings)): ?>
                    <p>No field mappings found. Click '+' to add a new field.</p>
                <?php endif; ?>

                <?php foreach ($fieldMappings as $field): ?>
                    <div class="coordinateField border p-3 mb-3" data-field-id="<?= $field['id'] ?>">
                        <input type="hidden" name="field_id[]" value="<?= $field['id'] ?>">
                        <input type="hidden" name="template_id[]" value="<?= $template_id ?>">
                        <div class="row g-3">
                            <!-- Column Selection -->
                            <div class="col-md-4">
                                <label class="form-label">Column Name:</label>
                                <select name="column_name[<?php echo $template['id']; ?>][]" class="form-select column_name_select" required>
                                    <option value="">Select Column</option>
                                    <?php foreach ($columns as $colName): ?>
                                        <option value="<?= $colName ?>" <?= $field['column_name'] === $colName ? 'selected' : '' ?>><?= $colName ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>X:</label>
                                <input type="number" name="x_position[<?php echo $template['id']; ?>][]" value="<?= $field['x_position'] ?>" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label>Y:</label>
                                <input type="number" name="y_position[<?php echo $template['id']; ?>][]" value="<?= $field['y_position'] ?>" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label>Width:</label>
                                <input type="number" name="width[<?php echo $template['id']; ?>][]" value="<?= $field['width'] ?>" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label>Height:</label>
                                <input type="number" name="height[<?php echo $template['id']; ?>][]" value="<?= $field['height'] ?>" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label>Text Type:</label>
                                <select name="cell_type[<?php echo $template['id']; ?>][]" class="form-select">
                                    <option value="Cell" <?= $field['cell_type'] === 'Cell' ? 'selected' : '' ?>>Cell</option>
                                    <option value="MultiCell" <?= $field['cell_type'] === 'MultiCell' ? 'selected' : '' ?>>MultiCell</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Font Size:</label>
                                <input type="number" name="font_size[<?php echo $template['id']; ?>][]" value="<?= $field['font_size'] ?>" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Font Type:</label>
                                <select name="font_type[<?php echo $template['id']; ?>][]" class="form-select">
                                    <option <?= $field['font_type'] === 'Arial' ? 'selected' : '' ?>>Arial</option>
                                    <option <?= $field['font_type'] === 'Times' ? 'selected' : '' ?>>Times</option>
                                    <option <?= $field['font_type'] === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
                                    <option <?= $field['font_type'] === 'Helvetica' ? 'selected' : '' ?>>Helvetica</option>
                                    <option <?= $field['font_type'] === 'Courier' ? 'selected' : '' ?>>Courier</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Font Color:</label>
                                <input type="color" name="font_color[<?php echo $template['id']; ?>][]" value="<?= $field['font_color'] ?>" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Font Style:</label>
                                <select name="font_style[<?php echo $template['id']; ?>][]" class="form-select">
                                    <option value="normal" <?= $field['font_style'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="B" <?= $field['font_style'] === 'B' ? 'selected' : '' ?>>Bold</option>
                                    <option value="I" <?= $field['font_style'] === 'I' ? 'selected' : '' ?>>Italic</option>
                                    <option value="BI" <?= $field['font_style'] === 'BI' ? 'selected' : '' ?>>Bold Italic</option>
                                </select>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="button" class="btn btn-sm btn-danger removeFieldInstanceBtn">Remove</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary mt-2 addFieldBtn">+</button>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-success mt-4">Save Updated Coordinates</button>
    </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
$(document).ready(function() {
    let isDragging = false;
    let startX, startY;
    let $selectionBox;
    let $currentImage;
    let $currentFieldInputs;

    // Update selection box from input values
    function updateSelectionBoxFromInputs($fieldContainer) {
        if (!$fieldContainer.length) return;

        const x = parseFloat($fieldContainer.find('.x_position').val());
        const y = parseFloat($fieldContainer.find('.y_position').val());
        const width = parseFloat($fieldContainer.find('.width').val());
        const height = parseFloat($fieldContainer.find('.height').val());

        const templateId = $fieldContainer.closest('.coordinateFieldsContainer').data('template-id');
        const $box = $('.selectionBox' + templateId);

        if (!isNaN(x) && !isNaN(y) && !isNaN(width) && !isNaN(height)) {
            $box.css({
                left: x + 'px',
                top: y + 'px',
                width: width + 'px',
                height: height + 'px',
                display: 'block'
            });
        } else {
            $box.hide();
        }
    }

    // Highlight selected field
    $('.coordinateFieldsContainer').on('click', '.coordinateField', function () {
        $('.coordinateField').removeClass('highlighted-field');
        $(this).addClass('highlighted-field');
        $currentFieldInputs = $(this);
        updateSelectionBoxFromInputs($currentFieldInputs);
    });

    // React to input changes
    $('.coordinateFieldsContainer').on('change', 'input[type="number"], select', function () {
        const $fieldContainer = $(this).closest('.coordinateField');
        updateSelectionBoxFromInputs($fieldContainer);
        refreshPdfPreview(); // optional
    });

    // Mouse down on image
    $('.templateImage').on('mousedown', function (e) {
        if (!$currentFieldInputs || !$currentFieldInputs.length) {
            alert('Please select a field before drawing.');
            return;
        }

        isDragging = true;
        startX = e.offsetX;
        startY = e.offsetY;

        $currentImage = $(this);
        const templateId = $currentImage.data('template-id');
        $selectionBox = $('.selectionBox' + templateId);

        $selectionBox.css({
            left: startX + 'px',
            top: startY + 'px',
            width: '0px',
            height: '0px',
            display: 'block'
        });
    });

    // Mouse move on document
    $(document).on('mousemove', function (e) {
        if (!isDragging || !$currentImage || !$selectionBox) return;

        const offset = $currentImage.offset();
        const currentX = e.pageX - offset.left;
        const currentY = e.pageY - offset.top;

        const x = Math.min(startX, currentX);
        const y = Math.min(startY, currentY);
        const width = Math.abs(currentX - startX);
        const height = Math.abs(currentY - startY);

        $selectionBox.css({ left: x, top: y, width: width, height: height });

        if ($currentFieldInputs && $currentFieldInputs.length) {
            $currentFieldInputs.find('.x_position').val(Math.round(x));
            $currentFieldInputs.find('.y_position').val(Math.round(y));
            $currentFieldInputs.find('.width').val(Math.round(width));
            $currentFieldInputs.find('.height').val(Math.round(height));
        }
    });

    // Mouse up on document
    $(document).on('mouseup', function () {
        if (isDragging) {
            isDragging = false;
            refreshPdfPreview();
        }
    });

    // Stop dragging if Enter key pressed
    $(document).on('keydown', function (e) {
        if (e.key === 'Enter' && isDragging) {
            e.preventDefault();
            isDragging = false;
            if ($selectionBox) $selectionBox.hide();
        }
    });


    // Add Field Button
    let fieldCounter = <?= empty($fieldMappings) ? 0 : max(array_column($fieldMappings, 'id')) ?>;

$(document).on('click', '.addFieldBtn', function () {
    fieldCounter++;
    const newFieldId = 'new_' + fieldCounter;
   // const newFieldId =  fieldCounter;

    // Get the related container based on this "+" button
    const $templateSection = $(this).closest('.mt-4.p-3.border');
    const templateId = $templateSection.find('.templateImage').data('template-id');
    const $container = $templateSection.find('.coordinateFieldsContainer');

    const newFieldHtml = `
        <div class="coordinateField border p-3 mb-3" data-field-id="${newFieldId}">
            <input type="hidden" name="field_id[]" value="${newFieldId}">
            <input type="hidden" name="template_id[]" value="${templateId}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Column Name:</label>
                    <select name="column_name[${newFieldId}]" class="form-select column_name_select" required>
                        <option value="">Select Column</option>
                        <?php foreach ($columns as $colName): ?>
                            <option value="<?= htmlspecialchars($colName) ?>">
                                <?= htmlspecialchars($colName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">X:</label>
                    <input type="number" name="x_position[${newFieldId}]" value="0" class="x_position form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Y:</label>
                    <input type="number" name="y_position[${newFieldId}]" value="0" class="y_position form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Width:</label>
                    <input type="number" name="width[${newFieldId}]" value="0" class="width form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Height:</label>
                    <input type="number" name="height[${newFieldId}]" value="0" class="height form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Text Box Type:</label>
                    <select name="cell_type[${newFieldId}]" class="form-select" required>
                        <option value="Cell">Cell</option>
                        <option value="MultiCell">MultiCell</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Font Size:</label>
                    <select name="font_size[${newFieldId}]" class="form-select">
                        <?php for ($i = 5; $i <= 36; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Font Type:</label>
                    <select name="font_type[${newFieldId}]" class="form-select">
                        <option value="Arial">Arial</option>
                        <option value="Times">Times New Roman</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Helvetica">Helvetica</option>
                        <option value="Courier">Courier New</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Font Color:</label>
                    <input type="color" name="font_color[${newFieldId}]" value="#000000" class="form-control form-control-color">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Font Style:</label>
                    <select name="font_style[${newFieldId}]" class="form-select">
                        <option value="normal">Normal</option>
                        <option value="B">Bold</option>
                        <option value="I">Italic</option>
                        <option value="BI">Bold Italic</option>
                    </select>
                </div>
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-sm btn-danger removeFieldInstanceBtn">Remove</button>
                </div>
            </div>
        </div>
    `;

    $container.append(newFieldHtml);

    // Highlight logic (if needed)
    const $newField = $container.children().last();
    $templateSection.find('.coordinateField').removeClass('highlighted-field');
    $newField.addClass('highlighted-field');
    $currentFieldInputs = $newField;
    $templateSection.find('.selectionBox').hide();
});

    // Remove Field Instance Button
    $('.coordinateFieldsContainer').on('click', '.removeFieldInstanceBtn', function() {
        if (confirm('Are you sure you want to remove this field mapping?')) {
            $(this).closest('.coordinateField').remove();
            // Clear current field selection if the removed field was selected
            if ($currentFieldInputs && $(this).closest('.coordinateField').is($currentFieldInputs)) {
                $currentFieldInputs = null;
                $selectionBox.hide();
            }
            refreshPdfPreview();
        }
    });

    // Refresh PDF preview
    function refreshPdfPreview() {
        const iframe = document.getElementById('pdfPreviewFrame');
        if (iframe) {
            // Reload the iframe to reflect changes
            iframe.src = iframe.src.split('&_nocache=')[0] + '&_nocache=' + new Date().getTime();
        }
    }

    // Initial highlight for the first field if any exist
    if ($('.coordinateField').length > 0) {
        const $firstField = $('.coordinateField').first();
        $firstField.addClass('highlighted-field');
        $currentFieldInputs = $firstField;
        updateSelectionBoxFromInputs($firstField);
    }
});
</script>
</body>
</html>