<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Question — Setup</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/setup.css">
</head>
<body>

<div class="topbar">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost btn-sm">&larr; Back</a>
    <div class="topbar-title">New Question Setup</div>
    <div style="width:70px"></div>
</div>

<div class="container">
    <form id="setupForm" class="card setup-form">

        <label>Question Name</label>
        <input type="text" id="fName" placeholder="e.g. Physics Half-Yearly 2026" required>

        <div class="row2">
            <div>
                <label>Language</label>
                <select id="fLanguage">
                    <option value="bn">Bangla</option>
                    <option value="en">English</option>
                </select>
            </div>
            <div>
                <label>Primary Font</label>
                <select id="fPrimaryFont"></select>
            </div>
        </div>

        <label>Secondary Font <span class="hint">(for the other language, when mixed in)</span></label>
        <select id="fSecondaryFont"></select>

        <label>Page Size</label>
        <select id="fPageSize">
            <option value="A4">A4</option>
            <option value="Legal">Legal</option>
        </select>

        <hr>

        <label>School Name</label>
        <input type="text" id="fSchool" data-lang-input required>

        <div class="row2">
            <div><label>Exam Name</label><input type="text" id="fExam" data-lang-input required></div>
            <div><label>Class Name</label><input type="text" id="fClass" data-lang-input required></div>
        </div>

        <div class="row2">
            <div><label>Subject Name</label><input type="text" id="fSubject" data-lang-input required></div>
            <div><label>Time</label><input type="text" id="fTime" data-lang-input required></div>
        </div>

        <label>Full Marks</label>
        <input type="text" id="fFullMarks" data-lang-input required>

        <hr>

        <div class="code-row">
            <div class="code-block">
                <label class="checkbox-label">
                    <input type="checkbox" id="fShowSetCode"> Set Code <span class="hint">(left side)</span>
                </label>
                <div class="code-boxes" id="setCodeBoxes" hidden>
                    <span class="code-caption">Set Code:</span>
                    <input type="text" maxlength="1" class="code-box" id="setCodeBox">
                </div>
            </div>

            <div class="code-block">
                <label class="checkbox-label">
                    <input type="checkbox" id="fShowSubjectCode"> Subject Code <span class="hint">(right side)</span>
                </label>
                <div class="code-boxes" id="subjectCodeBoxes" hidden>
                    <span class="code-caption">Subject Code:</span>
                    <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="0">
                    <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="1">
                    <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="2">
                </div>
            </div>
        </div>

        <div class="form-error" id="setupError"></div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Continue to Builder &rarr;</button>
    </form>
</div>

<script>window.APP_BASE = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/setup.js"></script>
</body>
</html>
