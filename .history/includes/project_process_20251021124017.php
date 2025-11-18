<?php

// include("../includes/header.php");
include("../db_connect.php");


$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

$currentStep = $_GET['step'] ?? 1;
$currentStep = intval($currentStep);
$currentPage = basename($_SERVER['PHP_SELF']);
$steps = [
     1 => 'Upload CSV',
   // 1 => 'Upload Excel',
    2 => 'Upload Photo',
    3 => 'Upload Template',
    4 => 'Set Coordinates',
    5 => 'Candidate  Settings',
    6 => 'Admit Cards',
    7 => 'Forget Settings'
    
];
?>
>
<div class="ml-64 ">
    <p class="text-2xl font-bold p-1 text-center">Project Process Steps</p>

    <!-- <p>Current Step: <?= $steps[$currentStep] ?? 'Unknown Step' ?></p>

    <?php if ($currentStep > 1): ?>
    <a href="project_process.php?step=<?= $currentStep - 1 ?>" class="btn btn-secondary mb-4">Previous Step</a>
    <?php endif; ?>

    <?php if ($currentStep < count($steps)): ?>
    <a href="project_process.php?step=<?= $currentStep + 1 ?>" class="btn btn-primary mb-4">Next Step</a>
    <?php endif; ?> -->
    <style>
    .back-button {
        display: inline-block;
        padding: 10px 15px;
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

    .content-container {
        background-color: #f9f9f9;
        min-height: 100vh;
    }
    
    </style>
    <a href="javascript:history.back()" class="back-button btn btn-sm">← Back</a>

    <div class="container-fluid ">

        <div class="row justify-content-center  ">
            <div class="col">

                <?php

                    $pageLinks = [
                          1 => "upload_csv.php?project_id=$project_id",
                        //1 => "upload_excel.php?project_id=$project_id",
                        2 => "upload_photos.php?project_id=$project_id",
                        

                        3 => "upload_template.php?project_id=$project_id",
                        4 => "generate_admit_card.php?project_id=$project_id",
                        5 => "candidate_login_settings.php?project_id=$project_id",
                        6 => "check_admitcard.php?project_id=$project_id",
                        7 => "forgot_settings.php?project_id=$project_id"
                    ];
                    ?>
                <!-- <ul class="progressbar d-flex justify-content-between list-unstyled mb-6">
                    <?php foreach ($steps as $stepNum => $stepLabel): ?>
                    <li
                        class="flex-fill text-center <?= $currentStep == $stepNum ? 'active' : ($currentStep > $stepNum ? 'completed' : '') ?>">
                        <a href="<?= $pageLinks[$stepNum] ?>" style="text-decoration:none;color:inherit;">
                        <a href="<?= $pageLinks[$stepNum] ?>" style="text-decoration:none;color:inherit;">
                            <div class="step-circle mb-2"><?= $stepNum ?></div>
                            <div><?= $stepLabel ?></div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul> -->
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-3 ">
                    <!-- <?php foreach ($steps as $stepNum => $stepLabel): 
                    $fileWithQuery = $pageLinks[$stepNum];
                    $fileOnly = basename(parse_url($fileWithQuery, PHP_URL_PATH)); // This will be "upload_csv.php"
                    $isActive = ($currentPage === $fileOnly); // Correct match!
                    ?>
                                    <a href="<?= $fileWithQuery ?>"
                                        class="ml-2 btn <?= $isActive ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= $stepNum ?>: <?= $stepLabel ?>
                    </a>
                    <?php endforeach; ?> -->
                    <ul class="nav nav-tabs mb-4 justify-content-center">
                        <?php foreach ($steps as $stepNum => $stepLabel): 
                            $fileWithQuery = $pageLinks[$stepNum];
                            $fileOnly = basename(parse_url($fileWithQuery, PHP_URL_PATH)); // "upload_csv.php"
                            $isActive = ($currentPage === $fileOnly);
                        ?>
                            <li class="nav-item">
                                <!-- <a class="ml-1 nav-link <?= $isActive ? 'active' : '' ?>" 
                                href="<?= $fileWithQuery ?>">
                                    <?= $stepNum ?>: <?= $stepLabel ?>
                                </a> -->
                                <a class="ml-1 nav-link <?= $isActive ? 'active' : '' ?>" 
   href="<?= $fileWithQuery ?>" 
   style="font-size: 0.85rem;">
   <?= $stepNum ?>: <?= $stepLabel ?>
                        </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
            </div>
        </div>

    </div>
</div>

<style>
.progressbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-left: 0;
    margin-bottom: 40px;
    position: relative;
    width: 100%;
    max-width: 1400px;
    /* Set your desired width */
    margin-left: auto;
    margin-right: auto;
}

.progressbar li {
    position: relative;
    color: #bbb;
    font-weight: 500;
    flex: 1 1 0;
    min-width: 0;
    /* Allow shrinking/growing */
    list-style: none;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.progressbar li .step-circle {
    width: 36px;
    height: 36px;
    line-height: 36px;
    border-radius: 50%;
    background: #e9ecef;
    display: inline-block;
    color: #bbb;
    font-weight: bold;
    margin-bottom: 5px;
    z-index: 2;
}

.progressbar li.active .step-circle,
.progressbar li.completed .step-circle {
    background: #4a6cf7;
    color: #fff;
}

.progressbar li.completed {
    color: #4a6cf7;
}

.progressbar li:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 18px;
    left: 50%;
    width: 100%;
    height: 4px;
    background: #e9ecef;
    z-index: 0;
    transform: translateX(18px);
}

.progressbar li:last-child::after {
    display: none;
}

.progressbar li.completed:not(:last-child)::after {
    background: #4a6cf7;
}
</style>