<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Application - Step 2</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f7fb; margin:0; padding:18px; }
        .container { max-width: 980px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:18px 20px; }
        .body { padding:20px; }
        .tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
        .tab-btn { background:#eef3fd; color:#123f7f; border:1px solid #bfd0ee; border-radius:8px; padding:10px 12px; cursor:pointer; font-size:14px; }
        .tab-btn.active { background:#184d9b; color:#fff; border-color:#184d9b; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field.full { grid-column:1 / -1; }
        input, select, textarea, button { padding:10px 11px; border:1px solid #cad5e2; border-radius:8px; font-size:14px; }
        textarea { min-height:90px; }
        .error { color:#b42318; font-size:12px; min-height:14px; }
        .actions { margin-top:14px; display:flex; gap:10px; }
        button { cursor:pointer; border:none; background:#184d9b; color:#fff; }
        .muted { color:#5b6b83; }
        .status { margin-top:10px; font-size:13px; }
        @media (max-width:768px){ .grid{ grid-template-columns:1fr; } .header h1{font-size:20px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Step 2 Application Form</h1>
        <p>Application Number: <strong><?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></strong></p>
    </div>
    <div class="body">
        <div class="tabs">
            <button type="button" class="tab-btn active" data-tab="basic">Basic Info</button>
            <button type="button" class="tab-btn" data-tab="address">Corresponding Address</button>
            <button type="button" class="tab-btn" data-tab="courses">Selection of Courses</button>
            <button type="button" class="tab-btn" data-tab="image">Image Upload</button>
        </div>

        <div class="tab-panel active" id="tab-basic">
            <form id="basicInfoForm" novalidate>
                <div class="grid">
                    <div class="field">
                        <label>Nationality</label>
                        <input type="text" name="nationality" value="Indian" readonly>
                        <small class="muted">Only Indian is allowed.</small>
                        <div class="error" data-error-for="nationality"></div>
                    </div>
                    <div class="field">
                        <label>Domicile</label>
                        <select name="domicile">
                            <option value="">Select</option>
                            <option value="West Bengal">West Bengal</option>
                            <option value="Others">Others</option>
                        </select>
                        <div class="error" data-error-for="domicile"></div>
                    </div>
                    <div class="field">
                        <label>Religion</label>
                        <select name="religion">
                            <option value="">Select</option>
                            <option>Hinduism</option><option>Islam</option><option>Christianity</option><option>Buddhism</option><option>Sikhism</option><option>Jainism</option><option>Other</option>
                        </select>
                        <div class="error" data-error-for="religion"></div>
                    </div>
                    <div class="field">
                        <label>Category</label>
                        <select name="category"><option value="">Select domicile first</option></select>
                        <div class="error" data-error-for="category"></div>
                    </div>
                    <div class="field full">
                        <label>Sub-category details (optional)</label>
                        <textarea name="sub_category_details"></textarea>
                        <div class="error" data-error-for="sub_category_details"></div>
                    </div>
                    <div class="field">
                        <label>Person with Disability (PwD)</label>
                        <select name="pwd_status"><option value="No">No</option><option value="Yes">Yes</option></select>
                        <div class="error" data-error-for="pwd_status"></div>
                    </div>
                    <div class="field" id="disabilityTypeField" style="display:none;">
                        <label>Type of Disability</label>
                        <select name="disability_type">
                            <option value="">Select</option>
                            <option>Locomotor disability in lower limb</option>
                            <option>Others</option>
                        </select>
                        <div class="error" data-error-for="disability_type"></div>
                    </div>
                    <div class="field" id="disabilityPercentageField" style="display:none;">
                        <label>Percentage of Disability</label>
                        <input type="number" step="0.01" name="disability_percentage">
                        <div class="error" data-error-for="disability_percentage"></div>
                    </div>
                    <div class="field">
                        <label>Qualifying examination</label>
                        <input type="text" name="qualifying_examination">
                        <div class="error" data-error-for="qualifying_examination"></div>
                    </div>
                    <div class="field">
                        <label>Pass Status</label>
                        <input type="text" name="pass_status" value="Passed" readonly>
                        <div class="error" data-error-for="pass_status"></div>
                    </div>
                    <div class="field">
                        <label>Year of Passing</label>
                        <input type="text" name="year_of_passing" maxlength="4" placeholder="e.g. 1999">
                        <div class="error" data-error-for="year_of_passing"></div>
                    </div>
                    <div class="field full">
                        <label>Institute Name and address</label>
                        <textarea name="institute_name_address"></textarea>
                        <div class="error" data-error-for="institute_name_address"></div>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit">Save Basic Info</button>
                </div>
                <div class="status" id="basicStatus"></div>
            </form>
        </div>

        <div class="tab-panel" id="tab-address"><p class="muted">Corresponding Address tab is ready for implementation.</p></div>
        <div class="tab-panel" id="tab-courses"><p class="muted">Selection of Courses tab is ready for implementation.</p></div>
        <div class="tab-panel" id="tab-image"><p class="muted">Image Upload tab is ready for implementation.</p></div>
    </div>
</div>
<script src="../assets/js/step2.js?v=20260402"></script>
</body>
</html>
