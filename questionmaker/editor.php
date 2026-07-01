<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
$user    = requireLogin();
$paperId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor — <?= APP_NAME ?> by <?= APP_BRAND ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/editor.css">
<link rel="stylesheet" href="assets/css/print.css">
<script>const APP_BASE = <?= json_encode(_appBaseUrl()) ?>;</script>
</head>
<body class="editor-body">

<!-- TOP BAR -->
<header class="ed-topbar">
  <div class="ed-topbar-left">
    <a href="dashboard.php" class="btn btn-ghost btn-sm" style="font-weight:700;color:var(--accent);">QM</a>
    <span style="color:var(--border);font-size:18px;">|</span>
    <input type="text" id="paperTitle" class="title-input" value="Untitled Paper" placeholder="Paper title…">
  </div>
  <div class="ed-topbar-right" style="position:relative;">
    <span id="saveStatus" class="save-status">Unsaved</span>
    <button class="btn btn-ghost btn-sm" onclick="QM.openAI()">🤖 AI Generate</button>
    <button class="btn btn-ghost btn-sm" onclick="QM.openQBank()">🗄️ Question Bank</button>
    <button class="btn btn-ghost btn-sm" onclick="QM.openShare()">🔗 Share</button>
    <button class="btn btn-ghost btn-sm" onclick="QM.openJSONModal()">📋 JSON Data</button>
    <button class="btn btn-ghost btn-sm" onclick="QM.openPrintPreview()">👁 Preview</button>
    <button class="btn btn-ghost btn-sm menu-toggle-btn" onclick="QM.toggleHeaderMenu(event)" style="display:none;padding:6px 10px;">⋮ Actions</button>
    <button class="btn btn-primary btn-sm" onclick="QM.save()">💾 Save</button>

    <!-- Mobile Dropdown Menu -->
    <div class="header-action-menu" id="headerActionMenu" style="display:none;position:absolute;top:40px;right:10px;background:var(--bg-1);border:1px solid var(--border);border-radius:10px;padding:6px;box-shadow:var(--shadow);z-index:10000;min-width:160px;text-align:left;">
      <button class="im-btn" onclick="QM.hideHeaderMenu();QM.openAI()">🤖 AI Generate</button>
      <button class="im-btn" onclick="QM.hideHeaderMenu();QM.openQBank()">🗄️ Question Bank</button>
      <button class="im-btn" onclick="QM.hideHeaderMenu();QM.openShare()">🔗 Share</button>
      <button class="im-btn" onclick="QM.hideHeaderMenu();QM.openJSONModal()">📋 JSON Data</button>
      <button class="im-btn" onclick="QM.hideHeaderMenu();QM.openPrintPreview()">👁 Preview</button>
    </div>
  </div>
</header>

<!-- MAIN LAYOUT -->
<div class="ed-layout">

  <!-- LEFT MAIN: Preview Panel (70%) -->
  <main class="ed-left-main">
    <div class="preview-toolbar" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--bg-1);border-bottom:1px solid var(--border);font-size:12px;color:var(--text-2);flex-shrink:0;user-select:none;">
      <span style="font-weight:600;">Pagination Live Preview</span>
      <div style="display:flex;align-items:center;gap:6px;">
        <button class="btn btn-ghost btn-xs" onclick="QM.adjustZoom(-0.05)" title="Zoom Out">-</button>
        <span id="zoomPercent" style="min-width:40px;text-align:center;font-size:11px;font-weight:600;color:var(--text-1);">Fit</span>
        <button class="btn btn-ghost btn-xs" onclick="QM.adjustZoom(0.05)" title="Zoom In">+</button>
        <button class="btn btn-ghost btn-xs" onclick="QM.setZoomMode('fit')">Fit</button>
        <button class="btn btn-ghost btn-xs" onclick="QM.setZoomMode('100')">100%</button>
        <button class="btn btn-ghost btn-xs" onclick="QM.openPrintPreview()">Open Full ↗</button>
      </div>
    </div>
    <div class="preview-scale-wrap" id="previewScaleWrap">
      <div id="previewPageWrap" style="display:flex;flex-direction:column;align-items:center;width:100%;"></div>
    </div>
  </main>

  <!-- RIGHT SIDEBAR: Tabs Panel (30%) -->
  <aside class="ed-right-sidebar">
    <div class="sidebar-tabs">
      <button class="stab-btn active" id="btnTabElements" onclick="QM.switchSidebarTab('elements')">Elements</button>
      <button class="stab-btn" id="btnTabHeader" onclick="QM.switchSidebarTab('header')">Header</button>
      <button class="stab-btn" id="btnTabSettings" onclick="QM.switchSidebarTab('settings')">Settings</button>
    </div>

    <div class="sidebar-tab-content">
      <!-- 1. Elements Pane -->
      <div class="stab-pane active" id="paneElements">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
          <h4 style="font-size:12px;font-weight:600;color:var(--text-1);text-transform:uppercase;letter-spacing:.03em;">Elements list</h4>
          <span class="el-count" id="elCount">0 elements</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(100px, 1fr));gap:6px;margin-bottom:14px;background:var(--bg-2);padding:10px;border-radius:10px;border:1px solid var(--border);">
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('section-header')">+ Section</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('instruction')">+ Instruction</button>
          <button class="btn btn-accent btn-xs" onclick="QM.addElement('mcq')">+ MCQ</button>
          <button class="btn btn-accent btn-xs" onclick="QM.addElement('short')">+ Short Q</button>
          <button class="btn btn-accent btn-xs" onclick="QM.addElement('creative')">+ Creative</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('fill-blank')">+ Fill Blank</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('true-false')">+ True/False</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('image')">+ Image</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('text-block')">+ Text</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('table')">+ Table</button>
          <button class="btn btn-ghost btn-xs" onclick="QM.addElement('page-break')" style="grid-column: 1 / -1;">⎘ Page Break</button>
        </div>
        <div style="margin-bottom:14px;display:flex;gap:6px;">
          <button class="btn btn-ghost btn-sm" style="flex:1;font-weight:600;border:1px dashed var(--border);padding:6px 4px;font-size:11px;" onclick="QM.addBookletDemo()">📖 Load Demo</button>
          <button class="btn btn-ghost btn-sm" style="flex:1;font-weight:600;border:1px dashed var(--border);padding:6px 4px;font-size:11px;" onclick="QM.openSerialsModal()">🔢 Fix Serials</button>
        </div>
        <div class="elements-list" id="elementsList"></div>
      </div>

      <!-- 2. Header Pane -->
      <div class="stab-pane" id="paneHeader" style="display:none;">
        <div class="field-group">
          <label class="field-label">Institution Name</label>
          <input class="field-input" id="hInstitution" placeholder="e.g. ঢাকা সিটি কলেজ">
        </div>
        <div class="field-group">
          <label class="field-label">Exam / Test Name</label>
          <input class="field-input" id="hExam" placeholder="e.g. প্রথম সাময়িক পরীক্ষা">
        </div>
        <div class="field-row">
          <div class="field-group" style="flex:1">
            <label class="field-label">Class</label>
            <input class="field-input" id="hClass" placeholder="e.g. দশম">
          </div>
          <div class="field-group" style="flex:1">
            <label class="field-label">Subject</label>
            <input class="field-input" id="hSubject" placeholder="e.g. বাংলা">
          </div>
        </div>
        <div class="field-row">
          <div class="field-group" style="flex:1">
            <label class="field-label">Time</label>
            <input class="field-input" id="hTime" placeholder="e.g. ৩ ঘণ্টা">
          </div>
          <div class="field-group" style="flex:1">
            <label class="field-label">Full Marks</label>
            <input class="field-input" id="hMarks" type="number" placeholder="100" readonly>
          </div>
        </div>
        <div class="field-row" style="align-items:center;gap:10px;margin-bottom:14px;">
          <label class="toggle-label">
            <input type="checkbox" id="hShowCode" onchange="QM.renderPreview()">
            <span class="toggle-track"></span>
            Show Subject Code
          </label>
        </div>
        <div class="field-group" id="hCodeWrap" style="display:none;">
          <label class="field-label">Subject Code (3 digits)</label>
          <input class="field-input" id="hCode" maxlength="3" placeholder="123">
        </div>
      </div>

      <!-- 3. Settings Pane -->
      <div class="stab-pane" id="paneSettings" style="display:none;">
        <div class="field-group">
          <label class="field-label">Paper Language</label>
          <select class="field-input" id="sLanguage">
            <option value="bangla">Bangla (বাংলা)</option>
            <option value="english">English</option>
          </select>
        </div>
        <div class="field-group" id="sFontFamilyGroup">
          <label class="field-label">Bengali Font</label>
          <select class="field-input" id="sFontFamily" onchange="QM.applyFont()">
            <option value="SutonnyOMJ">SutonnyOMJ</option>
            <option value="Nikosh">Nikosh</option>
            <option value="NikoshBAN">NikoshBAN</option>
            <option value="SolaimanLipi">SolaimanLipi</option>
            <option value="Kalpurush">Kalpurush</option>
          </select>
        </div>
        <div class="field-group" id="sEnglishFontGroup" style="display:none;">
          <label class="field-label">English Font</label>
          <select class="field-input" id="sEnglishFont" onchange="QM.applyFont()">
            <option value="Times New Roman">Times New Roman</option>
            <option value="Arial">Arial</option>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Base Font Size (pt)</label>
          <input class="field-input" type="number" id="sFontSize" value="12" min="8" max="24" onchange="QM.renderPreview()">
        </div>
        <div class="field-group">
          <label class="field-label">Line Height</label>
          <input class="field-input" type="number" id="sLineHeight" value="1.7" min="1" max="3" step="0.1" onchange="QM.renderPreview()">
        </div>
        <div style="margin:16px 0;border-top:1px solid var(--border);padding-top:12px;">
          <h5 style="font-size:11px;color:var(--accent);text-transform:uppercase;margin-bottom:10px;font-weight:600;letter-spacing:.05em;">Type-wise Typography (Overrides)</h5>
          
          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Institution Header</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sHeaderFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sHeaderLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>

          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Section Headers</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sSectionFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sSectionLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>

          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Instructions</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sInstructionFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sInstructionLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>

          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Questions (All Types)</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sQuestionFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sQuestionLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>

          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Standalone/Stimulus Tables</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sTableFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sTableLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>

          <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:600;color:var(--text-1);margin-bottom:6px;">Summary Table (Answer Key)</div>
            <div class="field-row" style="gap:6px;">
              <input class="field-input" type="number" id="sSummaryFontSize" placeholder="Size (pt)" min="8" max="36" onchange="QM.renderPreview()">
              <input class="field-input" type="number" id="sSummaryLineHeight" placeholder="Line Ht" min="0.8" max="3.0" step="0.1" onchange="QM.renderPreview()">
            </div>
          </div>
        </div>
        <div class="field-group">
          <label class="field-label">Paper Size</label>
          <select class="field-input" id="sPaperSize" onchange="QM.renderPreview()">
            <option value="A4">A4 (210 × 297 mm)</option>
            <option value="Legal">Legal (216 × 356 mm)</option>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Layout Mode</label>
          <select class="field-input" id="sLayout" onchange="QM.renderPreview()">
            <option value="portrait">Portrait (Full Page)</option>
            <option value="landscape-half">Half-Page / Landscape</option>
            <option value="booklet">Booklet Mode</option>
          </select>
        </div>
        <div class="field-group" id="sHalfDupWrap" style="display:none;">
          <label class="toggle-label">
            <input type="checkbox" id="sAutoDuplicate">
            <span class="toggle-track"></span>
            Auto-duplicate to second half
          </label>
        </div>
        <div class="field-group">
          <label class="field-label">MCQ Columns</label>
          <select class="field-input" id="sMcqCols" onchange="QM.renderPreview()">
            <option value="1">1 Column</option>
            <option value="2">2 Columns</option>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label">Page Margins (mm)</label>
          <div class="field-row" style="gap:6px;">
            <input class="field-input" type="number" id="mTop" value="20" min="5" max="50" placeholder="Top" title="Top">
            <input class="field-input" type="number" id="mBottom" value="20" min="5" max="50" placeholder="Bottom" title="Bottom">
            <input class="field-input" type="number" id="mLeft" value="20" min="5" max="50" placeholder="Left" title="Left">
            <input class="field-input" type="number" id="mRight" value="20" min="5" max="50" placeholder="Right" title="Right">
          </div>
          <div style="font-size:11px;color:var(--text-3);margin-top:3px;">Top · Bottom · Left · Right</div>
        </div>
        <div class="field-row" style="align-items:center;gap:10px;">
          <label class="toggle-label">
            <input type="checkbox" id="sBorder" onchange="QM.renderPreview()">
            <span class="toggle-track"></span>
            Page Border
          </label>
          <label class="toggle-label">
            <input type="checkbox" id="sAnswerKey" onchange="QM.renderPreview()">
            <span class="toggle-track"></span>
            Answer Key Mode
          </label>
        </div>
      </div>
    </div>
  </aside>
</div>

<!-- ========== MODALS ========== -->

<!-- Element Edit Modal -->
<div class="modal-overlay" id="editModal" style="display:none;">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 id="editModalTitle">Edit Element</h3>
      <button class="modal-close" onclick="QM.closeEditModal()">✕</button>
    </div>
    <div class="modal-body" id="editModalBody"></div>
    <div class="modal-footer">
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn btn-ghost btn-sm" onclick="QM.saveToBank()">🗄️ Save to Bank</button>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ghost btn-sm" onclick="QM.closeEditModal()">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="QM.saveElement()">Apply</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     AI Generator Modal — Multi-provider + Manual mode
     ============================================================ -->
<div class="modal-overlay" id="aiModal" style="display:none;">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3>🤖 AI Question Generator</h3>
      <button class="modal-close" onclick="closeModal('aiModal')">✕</button>
    </div>
    <div class="modal-body">

      <!-- Provider selector -->
      <div class="field-group">
        <label class="field-label">AI Provider</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;" id="providerBtns">
          <button class="prov-btn active" data-p="claude"  onclick="QM.setProvider('claude',this)">✦ Claude</button>
          <button class="prov-btn"        data-p="openai"  onclick="QM.setProvider('openai',this)">⬡ GPT-4o</button>
          <button class="prov-btn"        data-p="gemini"  onclick="QM.setProvider('gemini',this)">✦ Gemini</button>
          <button class="prov-btn"        data-p="manual"  onclick="QM.setProvider('manual',this)">📋 Manual</button>
        </div>
        <input type="hidden" id="aiProvider" value="claude">
        <div id="providerNote" style="font-size:11px;color:var(--text-3);margin-top:6px;"></div>
      </div>

      <!-- Mode & Class -->
      <div class="field-row">
        <div class="field-group" style="flex:1">
          <label class="field-label">Generation Mode</label>
          <select class="field-input" id="aiMode" onchange="QM.toggleAIMode()">
            <option value="create">Create New Questions</option>
            <option value="modify">Modify Existing Question</option>
          </select>
        </div>
        <div class="field-group" style="flex:1">
          <label class="field-label">Class / Level</label>
          <input class="field-input" id="aiClass" placeholder="e.g. Class 9, Class 10">
        </div>
      </div>

      <!-- Existing Question (Hidden by default, shown in modify mode) -->
      <div class="field-group" id="aiExistingGroup" style="display:none;background:var(--bg-2);padding:12px;border-radius:8px;border:1px solid var(--border);margin-bottom:12px;">
        <div class="field-group">
          <label class="field-label">Select Question from Current Paper</label>
          <select class="field-input" id="aiExistingSelect" onchange="QM.onAIExistingSelectChange()">
            <option value="">-- Select Question --</option>
          </select>
        </div>
        <div class="field-group" style="margin-top:8px;">
          <label class="field-label">Or Paste / Edit Existing Question JSON or Text</label>
          <textarea class="field-input" id="aiExistingText" rows="3" placeholder="Pasted question content to modify..."></textarea>
        </div>
      </div>

      <!-- Lesson & Topic -->
      <div class="field-row">
        <div class="field-group" style="flex:1">
          <label class="field-label">Lesson / Chapter</label>
          <input class="field-input" id="aiLesson" placeholder="e.g. Chapter 3, Lesson 2">
        </div>
        <div class="field-group" style="flex:2">
          <label class="field-label">Topic / Specific Focus</label>
          <input class="field-input" id="aiTopic" placeholder="e.g. আলোর প্রতিফলন, Photosynthesis">
        </div>
      </div>

      <!-- Specialities / Custom Instructions -->
      <div class="field-group">
        <label class="field-label">Specialities / Custom Instructions (e.g. Board exam style, focus on math, etc.)</label>
        <textarea class="field-input" id="aiSpeciality" rows="2" placeholder="e.g. focus on mathematical calculations, keep it simple, board exam standard..."></textarea>
      </div>

      <div class="field-row">
        <div class="field-group" style="flex:1">
          <label class="field-label">Question Type</label>
          <select class="field-input" id="aiType">
            <option value="mcq">MCQ</option>
            <option value="short">Short Answer</option>
            <option value="creative">Creative (সৃজনশীল)</option>
            <option value="fill-blank">Fill in the Blank</option>
            <option value="true-false">True / False</option>
          </select>
        </div>
        <div class="field-group" style="flex:0 0 100px">
          <label class="field-label">Count</label>
          <input class="field-input" type="number" id="aiCount" value="5" min="1" max="20">
        </div>
        <div class="field-group" style="flex:1">
          <label class="field-label">Difficulty</label>
          <select class="field-input" id="aiDifficulty">
            <option value="easy">Easy</option>
            <option value="medium" selected>Medium</option>
            <option value="hard">Hard</option>
          </select>
        </div>
        <div class="field-group" style="flex:1">
          <label class="field-label">Language</label>
          <select class="field-input" id="aiLanguage">
            <option value="bangla">Bengali (বাংলা)</option>
            <option value="english">English</option>
          </select>
        </div>
      </div>

      <!-- ---- MANUAL MODE PANEL ---- -->
      <div id="manualPanel" style="display:none;">
        <div style="background:rgba(108,99,255,.07);border:1px solid rgba(108,99,255,.2);border-radius:12px;padding:14px;margin-bottom:14px;">
          <p style="font-size:12px;color:var(--text-2);margin-bottom:10px;">
            <strong style="color:var(--accent);">How to use Manual mode:</strong><br>
            <strong>Option A</strong> — Already have JSON? Paste it directly in the box below and click <strong>Parse &amp; Import</strong>.<br>
            <strong>Option B</strong> — Need a prompt? Click <strong>Generate Prompt</strong>, copy it into any AI (<a href="https://chat.openai.com" target="_blank" style="color:var(--accent);">ChatGPT</a>, <a href="https://gemini.google.com" target="_blank" style="color:var(--accent);">Gemini</a>, Copilot…), then paste the JSON response below.
          </p>
          <button class="btn btn-accent btn-sm" id="getPromptBtn" onclick="QM.getAIPrompt()">📋 Generate Prompt</button>
          <button class="btn btn-ghost btn-sm" onclick="QM.getBulkPrompt()" style="margin-left:8px;">📋 Copy General Bulk Prompt</button>
        </div>

        <!-- Generated prompt display -->
        <div id="promptWrap" style="display:none;margin-bottom:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <label class="field-label" style="margin:0;">Copy this prompt → paste into any AI</label>
            <button class="btn btn-ghost btn-xs" onclick="QM.copyAIPrompt()">📋 Copy All</button>
          </div>
          <textarea id="aiPromptText" class="field-input" rows="7" readonly style="font-family:monospace;font-size:11px;color:var(--text-2);resize:none;"></textarea>
        </div>

        <!-- Paste response -->
        <div id="pasteWrap" style="display:none;">
          <label class="field-label">Paste the AI's response here</label>
          <textarea id="aiPasteText" class="field-input" rows="6" placeholder='Paste the JSON array from the AI here, e.g.&#10;[{"question":"…","marks":1,"options":[…],"correct":0}]'></textarea>
          <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="QM.parseManual()">✓ Parse &amp; Import Questions</button>
        </div>
      </div>

      <!-- Results -->
      <div id="aiResults" style="display:none;max-height:340px;overflow-y:auto;margin-top:14px;border-top:1px solid var(--border);padding-top:12px;"></div>
    </div>

    <div class="modal-footer">
      <div style="font-size:12px;color:var(--text-3);" id="aiStatusNote"></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ghost btn-sm" onclick="closeModal('aiModal')">Close</button>
        <button class="btn btn-primary btn-sm" id="aiGenBtn" onclick="QM.runAI()" style="display:none;">✨ Generate</button>
      </div>
    </div>
  </div>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="shareModal" style="display:none;">
  <div class="modal">
    <div class="modal-header"><h3>🔗 Share Paper</h3><button class="modal-close" onclick="closeModal('shareModal')">✕</button></div>
    <div class="modal-body">
      <div id="shareLinks"></div>
      <hr style="border-color:var(--border);margin:16px 0;">
      <div class="field-group">
        <label class="toggle-label">
          <input type="checkbox" id="shareShowAns">
          <span class="toggle-track"></span>
          Include Answer Key in shared link
        </label>
      </div>
      <div class="field-group">
        <label class="field-label">Link Expiry</label>
        <select class="field-input" id="shareExpiry">
          <option value="0">Never</option>
          <option value="24">24 hours</option>
          <option value="72">3 days</option>
          <option value="168">7 days</option>
        </select>
      </div>
      <button class="btn btn-primary btn-full" onclick="QM.createShareLink()">Generate Share Link</button>
    </div>
  </div>
</div>

<!-- Question Bank Picker Modal -->
<div class="modal-overlay" id="qbankModal" style="display:none;">
  <div class="modal modal-lg">
    <div class="modal-header"><h3>🗄️ Question Bank</h3><button class="modal-close" onclick="closeModal('qbankModal')">✕</button></div>
    <div class="modal-body">
      <div style="display:flex;gap:10px;margin-bottom:14px;">
        <select class="field-input" id="qbFilter" style="width:140px;" onchange="QM.loadQBankPicker()">
          <option value="">All Types</option>
          <option value="mcq">MCQ</option>
          <option value="short">Short</option>
          <option value="creative">Creative</option>
          <option value="fill-blank">Fill Blank</option>
          <option value="true-false">True/False</option>
        </select>
        <input class="field-input" type="text" id="qbSearch" placeholder="Search…" oninput="QM.loadQBankPicker()">
      </div>
      <div id="qbPickerList" style="max-height:400px;overflow-y:auto;"></div>
    </div>
  </div>
</div>

<!-- Print Preview Modal -->
<div class="modal-overlay" id="printModal" style="display:none;">
  <div class="modal modal-fullscreen">
    <div class="modal-header">
      <h3>Print Preview</h3>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Print</button>
        <button class="modal-close" onclick="closeModal('printModal')">✕</button>
      </div>
    </div>
    <div class="modal-body" style="padding:0;background:#555;overflow:auto;">
      <div id="printPreviewWrap" style="padding:30px;display:flex;flex-direction:column;align-items:center;width:100%;box-sizing:border-box;"></div>
    </div>
  </div>
</div>

<!-- Image Upload Modal -->
<div class="modal-overlay" id="imgModal" style="display:none;">
  <div class="modal">
    <div class="modal-header"><h3>Add Image</h3><button class="modal-close" onclick="closeModal('imgModal')">✕</button></div>
    <div class="modal-body">
      <div class="field-group">
        <label class="field-label">Upload Image</label>
        <input type="file" class="field-input" id="imgUpload" accept="image/*" onchange="QM.uploadImg()">
      </div>
      <div style="text-align:center;color:var(--text-3);margin:8px 0;">— or —</div>
      <div class="field-group">
        <label class="field-label">Image URL</label>
        <input class="field-input" type="url" id="imgUrl" placeholder="https://…">
      </div>
      <div id="imgPreview" style="margin-top:10px;text-align:center;"></div>
    </div>
    <div class="modal-footer">
      <div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ghost btn-sm" onclick="closeModal('imgModal')">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="QM.confirmImg()">Insert Image</button>
      </div>
    </div>
  </div>
</div>

<!-- Fix Serials Modal -->
<div class="modal-overlay" id="serialsModal" style="display:none;">
  <div class="modal">
    <div class="modal-header"><h3>🔢 Format & Fix Serial Numbers</h3><button class="modal-close" onclick="closeModal('serialsModal')">✕</button></div>
    <div class="modal-body">
      
      <!-- Primary Serial Format -->
      <div class="field-group">
        <label class="field-label">Primary Question Serial Format</label>
        <select class="field-input" id="fsPrimaryFormat">
          <option value="1">1, 2, 3 (English Digits)</option>
          <option value="bn_num">১, ২, ৩ (Bangla Digits)</option>
          <option value="A">A, B, C (English Uppercase)</option>
          <option value="a">a, b, c (English Lowercase)</option>
          <option value="bn_alpha">ক, খ, গ (Bangla Letters)</option>
          <option value="roman">i, ii, iii (Roman Lowercase)</option>
        </select>
      </div>

      <div class="field-row">
        <div class="field-group" style="flex:1;">
          <label class="field-label">Suffix (e.g. . or )</label>
          <input class="field-input" type="text" id="fsPrimarySuffix" value=".">
        </div>
      </div>

      <div class="field-group">
        <label class="toggle-label">
          <input type="checkbox" id="fsSectionWise" checked>
          <span class="toggle-track"></span>
          Reset numbering for each section (Section-wise)
        </label>
      </div>

      <hr style="border-color:var(--border);margin:16px 0;">

      <!-- Sub-Question Format -->
      <div class="field-group">
        <label class="field-label">Sub-Question Serial Format (Creative)</label>
        <select class="field-input" id="fsSubFormat">
          <option value="bn_alpha">ক, খ, গ (Bangla Letters)</option>
          <option value="a">a, b, c (English Lowercase)</option>
          <option value="1">1, 2, 3 (English Digits)</option>
          <option value="bn_num">১, ২, ৩ (Bangla Digits)</option>
          <option value="A">A, B, C (English Uppercase)</option>
          <option value="roman">i, ii, iii (Roman Lowercase)</option>
        </select>
      </div>

      <div class="field-row">
        <div class="field-group" style="flex:1;">
          <label class="field-label">Suffix (e.g. . or )</label>
          <input class="field-input" type="text" id="fsSubSuffix" value=")">
        </div>
      </div>

      <button class="btn btn-primary btn-full" style="margin-top:10px;" onclick="QM.applySerialsFix()">✓ Apply Formatting</button>
    </div>
  </div>
</div>

<!-- Import / Export JSON Modal -->
<div class="modal-overlay" id="jsonModal" style="display:none;">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3>📋 Import / Export Paper JSON</h3>
      <button class="modal-close" onclick="closeModal('jsonModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:var(--text-2);margin-bottom:12px;">
        You can copy the entire paper data (including header and elements) below to edit it manually or keep a backup. Paste modified JSON back and click <strong>Load Paper Data</strong> to apply.
      </p>
      <div class="field-group">
        <label class="field-label">Paper JSON Data</label>
        <textarea id="jsonPaperText" class="field-input" rows="15" style="font-family:monospace;font-size:11px;color:var(--text-1);"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <div style="display:flex;gap:8px;justify-content:space-between;width:100%;">
        <div>
          <button class="btn btn-accent btn-sm" onclick="QM.copyPaperJSON()">📋 Copy to Clipboard</button>
        </div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-ghost btn-sm" onclick="closeModal('jsonModal')">Close</button>
          <button class="btn btn-primary btn-sm" onclick="QM.loadPaperJSON()">📥 Load Paper Data</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Math Symbol Picker -->
<div class="symbol-picker" id="symbolPicker" style="display:none;">
  <div style="display:flex;gap:4px;margin-bottom:6px;flex-wrap:wrap;">
    <button class="sp-special-btn" onclick="insertSymbol('\n')" title="New Line">↵ New Line</button>
    <button class="sp-special-btn" onclick="insertSymbol('  ')" title="Space">⎵ Space</button>
    <button class="sp-special-btn" onclick="insertSymbol('—')" title="Em dash">— Em dash</button>
    <button class="sp-special-btn" onclick="insertSymbol('…')" title="Ellipsis">… Ellipsis</button>
  </div>
  <div class="sp-grid" id="spGrid"></div>
</div>

<input type="hidden" id="paperId" value="<?= $paperId ?>">
<script src="assets/js/bangla.js?v=<?= time() ?>"></script>
<script src="assets/js/editor.js?v=<?= time() ?>"></script>
<script src="assets/js/qbank.js?v=<?= time() ?>"></script>
</body>
</html>
