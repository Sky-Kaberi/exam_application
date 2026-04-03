<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/functions.php';

$applicant = requireApplicantLoginForPage('login.php');
$initialTab = (string) ($_GET['tab'] ?? 'basic');
if (!in_array($initialTab, ['basic', 'address', 'courses', 'image'], true)) {
    $initialTab = 'basic';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Application - Step 2</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f7fb; margin:0; padding:18px; }
        .container { max-width: 980px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#184d9b; color:#fff; padding:18px 20px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
        .header a { color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,.5); border-radius:8px; padding:8px 10px; }
        .body { padding:20px; }
        .tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
        .tab-btn { background:#eef3fd; color:#123f7f; border:1px solid #bfd0ee; border-radius:8px; padding:10px 12px; cursor:pointer; font-size:14px; }
        .tab-btn.active { background:#184d9b; color:#fff; border-color:#184d9b; }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field.full { grid-column:1 / -1; }
        .section-title { margin-top:18px; margin-bottom:8px; color:#123f7f; font-size:17px; }
        input, select, textarea, button { padding:10px 11px; border:1px solid #cad5e2; border-radius:8px; font-size:14px; }
        textarea { min-height:90px; }
        .error { color:#b42318; font-size:12px; min-height:14px; }
        .actions { margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; }
        button { cursor:pointer; border:none; background:#184d9b; color:#fff; }
        .secondary { background:#5b6b83; }
        .muted { color:#5b6b83; }
        .status { margin-top:10px; font-size:13px; }
        .upload-preview img { max-width:220px; max-height:140px; border:1px solid #d4dbe6; border-radius:8px; padding:4px; background:#fff; }
        ul.instructions { margin:0; padding-left:18px; color:#4c5f79; }
        @media (max-width:768px){ .grid{ grid-template-columns:1fr; } .header h1{font-size:20px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Step 2 Application Form</h1>
            <p>Application Number: <strong><?= htmlspecialchars((string) $applicant['application_id'], ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>
        <div>
            <a href="../ajax/logout.php">Logout</a>
            <a href="step3_preview.php">Step 3 Preview</a>
        </div>
    </div>
    <div class="body">
        <div class="tabs">
            <button type="button" class="tab-btn" data-tab="basic">Basic Info</button>
            <button type="button" class="tab-btn" data-tab="address">Correspondence Address</button>
            <button type="button" class="tab-btn" data-tab="courses">Selection of Courses</button>
            <button type="button" class="tab-btn" data-tab="image">Image Upload</button>
        </div>

        <div class="tab-panel" id="tab-basic">
            <form id="basicInfoForm" novalidate>
                <div class="grid">
                    <div class="field"><label>Nationality</label><input type="text" name="nationality" value="Indian" readonly><small class="muted">Only Indian is allowed.</small><div class="error" data-error-for="nationality"></div></div>
                    <div class="field"><label>Domicile</label><select name="domicile"><option value="">Select</option><option value="West Bengal">West Bengal</option><option value="Others">Others</option></select><div class="error" data-error-for="domicile"></div></div>
                    <div class="field"><label>Religion</label><select name="religion"><option value="">Select</option><option>Hinduism</option><option>Islam</option><option>Christianity</option><option>Buddhism</option><option>Sikhism</option><option>Jainism</option><option>Other</option></select><div class="error" data-error-for="religion"></div></div>
                    <div class="field"><label>Category</label><select name="category"><option value="">Select domicile first</option></select><div class="error" data-error-for="category"></div></div>
                    <div class="field full"><label>Sub-category details (optional)</label><textarea name="sub_category_details"></textarea><div class="error" data-error-for="sub_category_details"></div></div>
                    <div class="field"><label>Person with Disability (PwD)</label><select name="pwd_status"><option value="No">No</option><option value="Yes">Yes</option></select><div class="error" data-error-for="pwd_status"></div></div>
                    <div class="field" id="disabilityTypeField" style="display:none;"><label>Type of Disability</label><select name="disability_type"><option value="">Select</option><option>Locomotor disability in lower limb</option><option>Others</option></select><div class="error" data-error-for="disability_type"></div></div>
                    <div class="field" id="disabilityPercentageField" style="display:none;"><label>Percentage of Disability</label><input type="number" step="0.01" name="disability_percentage"><div class="error" data-error-for="disability_percentage"></div></div>
                    <div class="field"><label>Qualifying examination</label><input type="text" name="qualifying_examination"><div class="error" data-error-for="qualifying_examination"></div></div>
                    <div class="field"><label>Pass Status</label><input type="text" name="pass_status" value="Passed" readonly><div class="error" data-error-for="pass_status"></div></div>
                    <div class="field"><label>Year of Passing</label><input type="text" name="year_of_passing" maxlength="4"><div class="error" data-error-for="year_of_passing"></div></div>
                    <div class="field full"><label>Institute Name and address</label><textarea name="institute_name_address"></textarea><div class="error" data-error-for="institute_name_address"></div></div>
                </div>
                <div class="actions"><button type="submit">Save Basic Info</button><button type="button" class="secondary" data-next-tab="address">Save & Continue</button></div>
                <div class="status" id="basicStatus"></div>
            </form>
        </div>

        <div class="tab-panel" id="tab-address">
            <form id="addressForm" novalidate>
                <h3 class="section-title">Correspondence Address</h3>
                <div class="grid">
                    <div class="field"><label>Premises No./ Village Name</label><input type="text" name="corr_premises"><div class="error" data-error-for="corr_premises"></div></div>
                    <div class="field"><label>Sub-locality / Colony / Police Station</label><input type="text" name="corr_sub_locality"><div class="error" data-error-for="corr_sub_locality"></div></div>
                    <div class="field"><label>Locality / City / Town / Village / Post Office</label><input type="text" name="corr_locality"><div class="error" data-error-for="corr_locality"></div></div>
                    <div class="field"><label>Country</label><select name="corr_country"></select><div class="error" data-error-for="corr_country"></div></div>
                    <div class="field"><label>State</label><select name="corr_state"></select><div class="error" data-error-for="corr_state"></div></div>
                    <div class="field"><label>District</label><select name="corr_district"></select><div class="error" data-error-for="corr_district"></div></div>
                    <div class="field"><label>PIN Code</label><input type="text" maxlength="6" name="corr_pin_code"><div class="error" data-error-for="corr_pin_code"></div></div>
                </div>
                <h3 class="section-title">Permanent Address</h3>
                <div class="field full"><label><input type="checkbox" name="same_as_correspondence" value="1"> Same as Correspondence Address</label></div>
                <div class="grid">
                    <div class="field"><label>Premises No./ Village Name</label><input type="text" name="perm_premises"><div class="error" data-error-for="perm_premises"></div></div>
                    <div class="field"><label>Sub-locality / Colony / Police Station</label><input type="text" name="perm_sub_locality"><div class="error" data-error-for="perm_sub_locality"></div></div>
                    <div class="field"><label>Locality / City / Town / Village / Post Office</label><input type="text" name="perm_locality"><div class="error" data-error-for="perm_locality"></div></div>
                    <div class="field"><label>Country</label><select name="perm_country"></select><div class="error" data-error-for="perm_country"></div></div>
                    <div class="field"><label>State</label><select name="perm_state"></select><div class="error" data-error-for="perm_state"></div></div>
                    <div class="field"><label>District</label><select name="perm_district"></select><div class="error" data-error-for="perm_district"></div></div>
                    <div class="field"><label>PIN Code</label><input type="text" maxlength="6" name="perm_pin_code"><div class="error" data-error-for="perm_pin_code"></div></div>
                </div>
                <div class="actions"><button type="submit">Save Address</button><button type="button" class="secondary" data-next-tab="courses">Save & Continue</button></div>
                <div class="status" id="addressStatus"></div>
            </form>
        </div>

        <div class="tab-panel" id="tab-courses">
            <form id="coursesForm" novalidate>
                <div class="grid">
                    <div class="field"><label>Course applied for (Group-1)</label><select name="course_group_1"></select><div class="error" data-error-for="course_group_1"></div></div>
                    <div class="field"><label>Course applied for (Group-2)</label><select name="course_group_2"></select><div class="error" data-error-for="course_group_2"></div></div>
                    <div class="field full"><label>Choice of Exam City</label><select name="exam_city"></select><div class="error" data-error-for="exam_city"></div></div>
                </div>
                <div class="actions"><button type="submit">Save Course Selection</button><button type="button" class="secondary" data-next-tab="image">Save & Continue</button></div>
                <div class="status" id="coursesStatus"></div>
            </form>
        </div>

        <div class="tab-panel" id="tab-image">
            <form id="imagesForm" novalidate enctype="multipart/form-data">
                <h3 class="section-title">Upload Instructions</h3>
                <ul class="instructions">
                    <li>Photograph: JPG/JPEG, size 10 KB to 200 KB, recent colour photo with clear visible face, no mask, both ears visible, white/light background.</li>
                    <li>Signature: JPG/JPEG, size 4 KB to 30 KB.</li>
                    <li>Both photograph and signature must be clear to issue admit card.</li>
                </ul>
                <div class="grid" style="margin-top:10px;">
                    <div class="field"><label>Recent colour photograph</label><input type="file" name="photo" accept=".jpg,.jpeg,image/jpeg"><div class="error" data-error-for="photo"></div><div class="upload-preview" id="photoPreview"></div></div>
                    <div class="field"><label>Signature</label><input type="file" name="signature" accept=".jpg,.jpeg,image/jpeg"><div class="error" data-error-for="signature"></div><div class="upload-preview" id="signaturePreview"></div></div>
                </div>
                <div class="actions"><button type="submit">Upload Images</button><a href="step3_preview.php" class="tab-btn" style="text-decoration:none;">Continue to Step 3 Preview</a></div>
                <div class="status" id="imagesStatus"></div>
            </form>
        </div>
    </div>
</div>
<script>window.step2InitialTab = <?= json_encode($initialTab) ?>;</script>
<script src="../assets/js/step2.js?v=20260403"></script>
</body>
</html>
