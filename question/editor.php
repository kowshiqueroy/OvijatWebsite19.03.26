<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$paperId = (int)($_GET['id'] ?? 0);
if (!$paperId) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor — Question Paper Maker</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/setup.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
</head>
<body class="editor-body">

<div class="topbar">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-ghost btn-sm">&larr;</a>
    <div class="topbar-title" id="paperTitle">Loading…</div>
    <div class="topbar-actions">
        <span id="saveStatus" class="save-status">Saved</span>
        <button class="btn btn-primary btn-sm" id="printPreviewBtn">Print Preview</button>
    </div>
</div>

<div class="top-tabs">
    <button type="button" class="top-tab-btn" data-tab="initial">⚙ Initial Settings</button>
    <button type="button" class="top-tab-btn" data-tab="print">🖨 Print &amp; Page Settings</button>
</div>

<div class="top-tab-panel setup-form" id="tabPanelInitial" hidden>
    <label>Question Name</label>
    <input type="text" id="isName">

    <div class="row2">
        <div>
            <label>Language</label>
            <select id="isLanguage">
                <option value="bn">Bangla</option>
                <option value="en">English</option>
            </select>
        </div>
        <div>
            <label>Primary Font</label>
            <select id="isPrimaryFont"></select>
        </div>
    </div>

    <label>Secondary Font <span class="hint">(for the other language, when mixed in)</span></label>
    <select id="isSecondaryFont"></select>

    <hr>

    <label>School Name</label>
    <input type="text" id="isSchool">

    <div class="row2">
        <div><label>Exam Name</label><input type="text" id="isExam"></div>
        <div><label>Class Name</label><input type="text" id="isClass"></div>
    </div>

    <div class="row2">
        <div><label>Subject Name</label><input type="text" id="isSubject"></div>
        <div><label>Time</label><input type="text" id="isTime"></div>
    </div>

    <label>Full Marks</label>
    <input type="text" id="isFullMarks">

    <hr>

    <div class="code-row">
        <div class="code-block">
            <label class="checkbox-label">
                <input type="checkbox" id="isShowSetCode"> Set Code <span class="hint">(left side)</span>
            </label>
            <div class="code-boxes" id="isSetCodeBoxes" hidden>
                <span class="code-caption">Set Code:</span>
                <input type="text" maxlength="1" class="code-box" id="isSetCodeBox">
            </div>
        </div>

        <div class="code-block">
            <label class="checkbox-label">
                <input type="checkbox" id="isShowSubjectCode"> Subject Code <span class="hint">(right side)</span>
            </label>
            <div class="code-boxes" id="isSubjectCodeBoxes" hidden>
                <span class="code-caption">Subject Code:</span>
                <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="0">
                <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="1">
                <input type="text" maxlength="1" inputmode="numeric" class="code-box" data-idx="2">
            </div>
        </div>
    </div>
</div>

<div class="top-tab-panel print-settings-panel" id="tabPanelPrint" hidden>
    <div class="settings-grid">
        <label>Page Size
            <select id="psPageSize">
                <option value="A4">A4</option>
                <option value="Legal">Legal</option>
            </select>
        </label>
        <label>Print Mode
            <select id="psPrintMode">
                <option value="full-1col">Full Page — 1 Column</option>
                <option value="full-2col">Full Page — 2 Columns</option>
                <option value="half-duplicate">Half Page — Duplicate (landscape)</option>
                <option value="half-sequential">Half Page — No Duplicate (landscape)</option>
                <option value="booklet">Booklet (landscape, bookbinding)</option>
            </select>
        </label>
        <label>Line Spacing
            <input type="range" id="psLineSpacing" min="1" max="2.2" step="0.05" value="1.4">
        </label>
        <label>Paragraph Spacing (px)
            <input type="number" id="psParaSpacing" min="0" max="40" value="8">
        </label>
        <label>Question/Body Font Size (px)
            <input type="number" id="psFontSize" min="10" max="24" value="13">
        </label>
        <label>Header Font Size (px)
            <input type="number" id="psHeaderFontSize" min="10" max="36" value="18">
        </label>
        <label>Page Margin (mm)
            <input type="number" id="psMargin" min="5" max="40" value="15">
        </label>
        <label id="psGapWrap" hidden>Column Gap (mm)
            <input type="number" id="psGap" min="0" max="40" value="10">
        </label>
        <label class="checkbox-label">
            <input type="checkbox" id="psHeaderBold" checked> Bold Header
        </label>
    </div>
</div>

<div class="editor-layout">
    <aside class="editor-controls">

        <div class="toolbox">
            <button class="tool-btn" data-add="instruction">+ Instruction</button>
            <button class="tool-btn" data-add="section">+ Section Header</button>
            <button class="tool-btn" data-add="question">+ Question</button>
            <button class="tool-btn" data-add="mcq">+ MCQ</button>
            <button class="tool-btn" data-add="fillblank">+ Fill in the Blank</button>
            <button class="tool-btn" data-add="passage">+ Passage</button>
            <button class="tool-btn" data-add="image">+ Image</button>
            <button class="tool-btn" data-add="pagebreak">+ Page Break</button>
        </div>

        <button type="button" class="btn btn-ghost btn-block ai-import-open" id="aiImportOpenBtn">🤖 Import Questions via External AI</button>

        <div id="elementsList" class="elements-list"></div>
    </aside>

    <main class="editor-preview">
        <div class="preview-zoom-bar">
            <span>Zoom</span>
            <select id="previewZoom">
                <option value="fit">Fit width</option>
                <option value="0.5">50%</option>
                <option value="0.75">75%</option>
                <option value="1">100%</option>
            </select>
        </div>
        <div class="preview-scale-wrap">
            <div id="previewPage" class="pages-stack"></div>
        </div>
    </main>
</div>

<input type="file" id="imageFileInput" accept="image/*" hidden>

<div class="symbol-picker" id="symbolPicker" hidden></div>

<!-- AI Import Modal -->
<div class="modal-overlay" id="aiImportOverlay">
    <div class="modal ai-import-modal" id="aiImportModal">
        <button class="modal-close" id="aiImportClose">&times;</button>
        <h2>Import Questions via External AI</h2>
        <p class="modal-sub">
            Step 1 — copy the prompt below and paste it into an external AI (ChatGPT, Claude, Gemini, etc.),
            along with your existing question document. Step 2 — paste the AI's reply back here.
            New questions will be added to the end of this paper.
        </p>

        <label>1. Prompt to copy</label>
        <textarea id="aiPromptText" class="ai-textarea" rows="10" readonly></textarea>
        <button type="button" class="btn btn-ghost btn-sm" id="copyPromptBtn">Copy Prompt</button>

        <label style="margin-top:18px">2. Paste the AI's result here</label>
        <textarea id="aiResultText" class="ai-textarea" rows="8" placeholder="Paste the AI's JSON output here..."></textarea>
        <div class="form-error" id="aiImportError"></div>
        <button type="button" class="btn btn-primary btn-block" id="aiParseBtn">Parse &amp; Add to Question</button>
    </div>
</div>

<script>window.APP_BASE = <?= json_encode(BASE_URL) ?>; window.PAPER_ID = <?= (int)$paperId ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bangla.js"></script>
<script src="<?= BASE_URL ?>/assets/js/render.js"></script>
<script src="<?= BASE_URL ?>/assets/js/editor.js"></script>
</body>
</html>
