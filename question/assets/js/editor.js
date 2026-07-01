const HALF_MODES = ['half-duplicate', 'half-sequential'];
const MULTI_COL_MODES = ['full-2col', 'half-duplicate', 'half-sequential', 'booklet'];

let paper = null;
let elements = [];
let printSettings = { lineSpacing: 1.4, paraSpacing: 8, fontSize: 13, headerFontSize: 18, headerBold: true, margin: 15, gap: 10 };
const elementDefaults = {
    instruction: { align: 'center', boxed: false, italic: false, fontSize: 14 },
    section: { bold: true, align: 'center', showMarks: false, showQuestionCount: false },
    question: { align: 'justify', showMarks: true },
    mcq: { layout: '4perline', bubbleStyle: 'letter', showMarks: true },
    fillblank: { showMarks: true },
    passage: { align: 'justify', imgAlign: 'center' },
    image: { align: 'center', wrap: 'with', heightLines: 2 },
};

function uid() { return 'el' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }

function sanitizeImageUrl(url) {
    const u = String(url ?? '').trim();
    if (!u || /^(javascript|vbscript|data):/i.test(u)) return null;
    return u;
}

const elementsListEl = document.getElementById('elementsList');
const previewPageEl = document.getElementById('previewPage');
const saveStatusEl = document.getElementById('saveStatus');

// ---------------- Load ----------------
async function loadPaper() {
    const res = await api('/api/paper.php?action=get&id=' + window.PAPER_ID);
    if (!res.ok) { toast(res.error || 'Failed to load question', 'error'); return; }
    paper = res.paper;
    paper.show_set_code = Number(paper.show_set_code) === 1;
    paper.show_subject_code = Number(paper.show_subject_code) === 1;
    elements = res.paper.elements || [];
    printSettings = Object.assign(printSettings, res.paper.print_settings || {});
    document.getElementById('paperTitle').textContent = paper.name;
    document.title = paper.name + ' — Editor';
    applyPrintSettingsToForm();
    populateInitialSettingsForm();
    renderElementsList();
    renderPreview();
}

// ---------------- Initial Settings tab ----------------
const IS_FONTS = {
    bn: ['Kalpurush', 'Siyam Rupali', 'SutonnyUniBanglaOMJ'],
    en: ['Times New Roman', 'Arial'],
};

function fillIsFontSelect(select, fonts, selected) {
    select.innerHTML = fonts.map(f => `<option value="${f}" ${f === selected ? 'selected' : ''}>${f}</option>`).join('');
}

function refreshIsFontOptions() {
    const lang = document.getElementById('isLanguage').value;
    const other = lang === 'bn' ? 'en' : 'bn';
    fillIsFontSelect(document.getElementById('isPrimaryFont'), IS_FONTS[lang], paper.primary_font);
    fillIsFontSelect(document.getElementById('isSecondaryFont'), IS_FONTS[other], paper.secondary_font);
}

function populateInitialSettingsForm() {
    document.getElementById('isName').value = paper.name || '';
    document.getElementById('isLanguage').value = paper.language || 'bn';
    refreshIsFontOptions();
    document.getElementById('isSchool').value = paper.school_name || '';
    document.getElementById('isExam').value = paper.exam_name || '';
    document.getElementById('isClass').value = paper.class_name || '';
    document.getElementById('isSubject').value = paper.subject_name || '';
    document.getElementById('isTime').value = paper.time_text || '';
    document.getElementById('isFullMarks').value = paper.full_marks || '';

    document.getElementById('isShowSetCode').checked = !!paper.show_set_code;
    document.getElementById('isSetCodeBoxes').hidden = !paper.show_set_code;
    document.getElementById('isSetCodeBox').value = paper.set_code || '';

    document.getElementById('isShowSubjectCode').checked = !!paper.show_subject_code;
    document.getElementById('isSubjectCodeBoxes').hidden = !paper.show_subject_code;
    const digits = String(paper.subject_code || '').padEnd(3, ' ').split('');
    document.querySelectorAll('#isSubjectCodeBoxes .code-box').forEach((box, i) => {
        box.value = (digits[i] || '').trim();
    });
}

const tabPanelInitial = document.getElementById('tabPanelInitial');
const tabPanelPrint = document.getElementById('tabPanelPrint');
const TAB_PANELS = { initial: tabPanelInitial, print: tabPanelPrint };

document.querySelectorAll('.top-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const isOpen = btn.classList.contains('active');
        document.querySelectorAll('.top-tab-btn').forEach(b => b.classList.remove('active'));
        Object.values(TAB_PANELS).forEach(p => p.hidden = true);
        if (!isOpen) {
            btn.classList.add('active');
            TAB_PANELS[btn.dataset.tab].hidden = false;
        }
    });
});

document.getElementById('isLanguage').addEventListener('change', () => {
    paper.language = document.getElementById('isLanguage').value;
    refreshIsFontOptions();
    paper.primary_font = document.getElementById('isPrimaryFont').value;
    paper.secondary_font = document.getElementById('isSecondaryFont').value;

    const setCodeBox = document.getElementById('isSetCodeBox');
    setCodeBox.value = setCodeBox.value.replace(isScriptFilterFor(paper.language), '');
    paper.set_code = setCodeBox.value;
    const subjBoxes = document.querySelectorAll('#isSubjectCodeBoxes .code-box');
    subjBoxes.forEach(box => { box.value = box.value.replace(isDigitFilterFor(paper.language), ''); });
    paper.subject_code = Array.from(subjBoxes).map(b => b.value.trim()).join('');

    renderPreview();
    scheduleSave();
});

// Strict, single-script only — no mixing Bangla and English digits/letters
// within one paper's set/subject code.
function isDigitFilterFor(lang) {
    return lang === 'bn' ? /[^০-৯]/g : /[^0-9]/g;
}
function isScriptFilterFor(lang) {
    return lang === 'bn' ? /[^ঀ-৿]/g : /[^A-Za-z0-9]/g;
}

tabPanelInitial.addEventListener('input', (e) => {
    const id = e.target.id;
    if (id === 'isSetCodeBox') {
        e.target.value = e.target.value.replace(isScriptFilterFor(paper.language), '').slice(0, 1);
    }
    const fieldMap = {
        isName: 'name', isPrimaryFont: 'primary_font', isSecondaryFont: 'secondary_font',
        isSchool: 'school_name', isExam: 'exam_name', isClass: 'class_name',
        isSubject: 'subject_name', isTime: 'time_text', isFullMarks: 'full_marks',
        isSetCodeBox: 'set_code',
    };
    if (fieldMap[id]) {
        paper[fieldMap[id]] = e.target.value;
        if (id === 'isName') {
            document.getElementById('paperTitle').textContent = e.target.value;
            document.title = e.target.value + ' — Editor';
        }
        renderPreview();
        scheduleSave();
    }

    if (e.target.closest('#isSubjectCodeBoxes')) {
        const boxes = Array.from(document.querySelectorAll('#isSubjectCodeBoxes .code-box'));
        const idx = boxes.indexOf(e.target);
        e.target.value = e.target.value.replace(isDigitFilterFor(paper.language), '').slice(0, 1);
        if (e.target.value && boxes[idx + 1]) boxes[idx + 1].focus();
        paper.subject_code = boxes.map(b => b.value.trim()).join('');
        renderPreview();
        scheduleSave();
    }
});

tabPanelInitial.addEventListener('change', (e) => {
    if (e.target.id === 'isShowSetCode') {
        paper.show_set_code = e.target.checked;
        document.getElementById('isSetCodeBoxes').hidden = !e.target.checked;
        renderPreview();
        scheduleSave();
    }
    if (e.target.id === 'isShowSubjectCode') {
        paper.show_subject_code = e.target.checked;
        document.getElementById('isSubjectCodeBoxes').hidden = !e.target.checked;
        renderPreview();
        scheduleSave();
    }
});

function applyPrintSettingsToForm() {
    document.getElementById('psPageSize').value = paper.page_size || 'A4';
    document.getElementById('psPrintMode').value = paper.print_mode || 'full-1col';
    document.getElementById('psLineSpacing').value = printSettings.lineSpacing ?? 1.4;
    document.getElementById('psParaSpacing').value = printSettings.paraSpacing ?? 8;
    document.getElementById('psFontSize').value = printSettings.fontSize ?? 13;
    document.getElementById('psHeaderFontSize').value = printSettings.headerFontSize ?? 18;
    document.getElementById('psHeaderBold').checked = printSettings.headerBold !== false;
    document.getElementById('psMargin').value = printSettings.margin ?? 15;
    document.getElementById('psGap').value = printSettings.gap ?? 10;
    document.getElementById('psGapWrap').hidden = !MULTI_COL_MODES.includes(paper.print_mode || 'full-1col');
}

// ---------------- Save ----------------
let saveTimer;
function scheduleSave() {
    saveStatusEl.textContent = 'Unsaved changes';
    saveStatusEl.className = 'save-status saving';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(savePaper, 700);
}

async function savePaper() {
    const res = await api('/api/paper.php?action=save', {
        method: 'POST',
        body: {
            id: window.PAPER_ID,
            page_size: document.getElementById('psPageSize').value,
            print_mode: document.getElementById('psPrintMode').value,
            name: paper.name, language: paper.language,
            primary_font: paper.primary_font, secondary_font: paper.secondary_font,
            school_name: paper.school_name, exam_name: paper.exam_name, class_name: paper.class_name,
            subject_name: paper.subject_name, time_text: paper.time_text, full_marks: paper.full_marks,
            subject_code: paper.subject_code, set_code: paper.set_code,
            show_subject_code: paper.show_subject_code, show_set_code: paper.show_set_code,
            elements,
            print_settings: printSettings,
        },
    });
    if (res.ok) {
        saveStatusEl.textContent = 'Saved';
        saveStatusEl.className = 'save-status';
    } else {
        saveStatusEl.textContent = 'Save failed';
        saveStatusEl.className = 'save-status error';
    }
}

// ---------------- Element CRUD ----------------
function addElement(type) {
    const base = { id: uid(), type };
    if (type === 'question') base.subQuestions = [];
    if (type === 'mcq') base.options = ['', '', '', ''];
    Object.assign(base, elementDefaults[type] || {});
    elements.push(base);
    renderElementsList();
    renderPreview();
    scheduleSave();
    setTimeout(() => {
        const card = elementsListEl.querySelector(`[data-id="${base.id}"]`);
        card?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 50);
}

function removeElement(id) {
    elements = elements.filter(e => e.id !== id);
    renderElementsList();
    renderPreview();
    scheduleSave();
}

function duplicateElement(id) {
    const idx = elements.findIndex(e => e.id === id);
    if (idx === -1) return;
    const copy = JSON.parse(JSON.stringify(elements[idx]));
    copy.id = uid();
    elements.splice(idx + 1, 0, copy);
    renderElementsList();
    renderPreview();
    scheduleSave();
}

function getElement(id) { return elements.find(e => e.id === id); }

function rerenderCard(id) {
    const el = getElement(id);
    const card = elementsListEl.querySelector(`[data-id="${id}"]`);
    if (!el || !card) return;
    card.outerHTML = buildCard(el);
}

// ---------------- Card templates ----------------
const TYPE_LABELS = {
    instruction: 'Instruction', section: 'Section Header', question: 'Question',
    mcq: 'MCQ', fillblank: 'Fill in the Blank',
    passage: 'Passage', image: 'Image', pagebreak: 'Page Break',
};

function cardHeader(el) {
    return `
    <div class="el-card-header" draggable="true">
        <span class="el-card-type">${TYPE_LABELS[el.type]}</span>
        <div class="el-card-actions">
            <button data-act="dup" title="Duplicate">⧉</button>
            <button data-act="del" title="Delete">🗑</button>
        </div>
    </div>`;
}

function richTextToolbar() {
    return `
    <div class="richtext-toolbar" data-rt-toolbar>
        <button type="button" data-cmd="bold"><b>B</b></button>
        <button type="button" data-cmd="italic"><i>I</i></button>
        <button type="button" data-cmd="table">⊞ Table</button>
        <button type="button" data-cmd="image">🖼 Image</button>
        <button type="button" data-cmd="symbol">Σ Symbol</button>
    </div>`;
}

function buildCard(el) {
    let body = '';
    switch (el.type) {
        case 'instruction':
            body = `
            ${richTextToolbar()}
            <div class="richtext-editable" contenteditable="true" data-field="text">${el.text || ''}</div>
            <div class="inline-row">
                <label>Align
                    <select data-field="align">
                        <option value="center" ${el.align === 'center' ? 'selected' : ''}>Center</option>
                        <option value="left" ${el.align === 'left' ? 'selected' : ''}>Left</option>
                    </select>
                </label>
                <label><input type="checkbox" data-field="boxed" ${el.boxed ? 'checked' : ''}> Boxed</label>
                <label><input type="checkbox" data-field="italic" ${el.italic ? 'checked' : ''}> Italic</label>
                <label>Font size <input type="number" data-field="fontSize" value="${el.fontSize || 14}" style="width:60px"></label>
            </div>`;
            break;

        case 'section':
            body = `
            <label>Section Text</label>
            <input type="text" data-field="text" value="${escapeAttr(el.text)}">
            <div class="inline-row">
                <label><input type="checkbox" data-field="bold" ${el.bold !== false ? 'checked' : ''}> Bold</label>
                <label>Align
                    <select data-field="align">
                        <option value="center" ${el.align === 'center' ? 'selected' : ''}>Center</option>
                        <option value="left" ${el.align === 'left' ? 'selected' : ''}>Left</option>
                    </select>
                </label>
                <label><input type="checkbox" data-field="showMarks" ${el.showMarks ? 'checked' : ''}> Show marks</label>
                <label><input type="checkbox" data-field="showQuestionCount" ${el.showQuestionCount ? 'checked' : ''}> Show question count</label>
            </div>
            ${el.showMarks ? `<label>Marks text (e.g. 5 x 3 = 15)</label><input type="text" data-field="marksText" value="${escapeAttr(el.marksText)}">` : ''}
            ${el.showQuestionCount ? `<label>Question count note</label><input type="text" data-field="questionCount" value="${escapeAttr(el.questionCount)}">` : ''}
            `;
            break;

        case 'question': {
            const subRows = (el.subQuestions || []).map((sq, i) => `
                <div class="subq-row" data-sub-idx="${i}">
                    <textarea rows="1" data-subfield="text" placeholder="Sub-question text">${sq.text || ''}</textarea>
                    <input type="text" data-subfield="marks" placeholder="Marks" value="${escapeAttr(sq.marks)}">
                    <button type="button" class="subq-remove" data-act="removeSub">&times;</button>
                </div>`).join('');
            body = `
            ${richTextToolbar()}
            <div class="richtext-editable" contenteditable="true" data-field="text">${el.text || ''}</div>
            <div class="inline-row">
                <label>Marks <input type="text" data-field="marks" value="${escapeAttr(el.marks)}" style="width:60px"></label>
                <label><input type="checkbox" data-field="showMarks" ${el.showMarks !== false ? 'checked' : ''}> Show marks (top right)</label>
                <label>Align
                    <select data-field="align">
                        <option value="justify" ${el.align === 'justify' ? 'selected' : ''}>Justify</option>
                        <option value="left" ${el.align === 'left' ? 'selected' : ''}>Left</option>
                        <option value="right" ${el.align === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </label>
            </div>
            <div class="subq-list">${subRows}</div>
            <button type="button" class="tool-btn subq-add" data-act="addSub">+ Sub-question</button>
            `;
            break;
        }

        case 'mcq': {
            const opts = (el.options || ['', '', '', '']).map((o, i) => `
                <div class="mcq-option-row">
                    <span>${bubbleLabel(el.bubbleStyle, i, paper?.language || 'bn')}.</span>
                    <input type="text" data-optidx="${i}" value="${escapeAttr(o)}" placeholder="Option ${i + 1}">
                </div>`).join('');
            body = `
            ${richTextToolbar()}
            <div class="richtext-editable" contenteditable="true" data-field="text">${el.text || ''}</div>
            <label>Options</label>
            ${opts}
            <div class="inline-row">
                <label>Marks <input type="text" data-field="marks" value="${escapeAttr(el.marks)}" style="width:60px"></label>
                <label><input type="checkbox" data-field="showMarks" ${el.showMarks !== false ? 'checked' : ''}> Show marks</label>
                <label>Layout
                    <select data-field="layout">
                        <option value="4perline" ${el.layout === '4perline' ? 'selected' : ''}>4 in one line</option>
                        <option value="2perline" ${el.layout === '2perline' ? 'selected' : ''}>2 per line</option>
                        <option value="1perline" ${el.layout === '1perline' ? 'selected' : ''}>1 per line</option>
                    </select>
                </label>
                <label>Bubble
                    <select data-field="bubbleStyle">
                        <option value="letter" ${el.bubbleStyle === 'letter' ? 'selected' : ''}>a, b, c, d</option>
                        <option value="bangla" ${el.bubbleStyle === 'bangla' ? 'selected' : ''}>ক, খ, গ, ঘ</option>
                        <option value="paren" ${el.bubbleStyle === 'paren' ? 'selected' : ''}>(a), (b)...</option>
                    </select>
                </label>
            </div>`;
            break;
        }

        case 'fillblank':
            body = `
            ${richTextToolbar()}
            <div class="richtext-editable" contenteditable="true" data-field="text">${el.text || ''}</div>
            <p style="color:var(--muted);font-size:12px;margin:4px 0 0">Type the blank directly in the text, e.g. using underscores: ______</p>
            <div class="inline-row">
                <label>Marks <input type="text" data-field="marks" value="${escapeAttr(el.marks)}" style="width:60px"></label>
                <label><input type="checkbox" data-field="showMarks" ${el.showMarks !== false ? 'checked' : ''}> Show marks</label>
            </div>`;
            break;

        case 'passage':
            body = `
            ${richTextToolbar()}
            <div class="richtext-editable" contenteditable="true" data-field="text">${el.text || ''}</div>
            <div class="inline-row">
                <label>Image URL <input type="text" data-field="imageUrl" value="${escapeAttr(el.imageUrl)}" placeholder="or use Insert Image above"></label>
                <label>Image Align
                    <select data-field="imgAlign">
                        <option value="left" ${el.imgAlign === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${el.imgAlign === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${el.imgAlign === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </label>
                <label>Align
                    <select data-field="align">
                        <option value="justify" ${el.align === 'justify' ? 'selected' : ''}>Justify</option>
                        <option value="left" ${el.align === 'left' ? 'selected' : ''}>Left</option>
                        <option value="right" ${el.align === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </label>
            </div>`;
            break;

        case 'image':
            body = `
            <label>Image URL</label>
            <input type="text" data-field="src" value="${escapeAttr(el.src)}" placeholder="https://... or upload below">
            <button type="button" class="tool-btn" data-act="uploadImage" style="margin-top:8px">Upload Image Instead</button>
            ${el.src ? `<img src="${escapeAttr(el.src)}" style="max-width:100%;margin-top:8px;border:1px solid #eee">` : ''}
            <div class="inline-row">
                <label>Align
                    <select data-field="align">
                        <option value="left" ${el.align === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${el.align === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${el.align === 'right' ? 'selected' : ''}>Right</option>
                    </select>
                </label>
                <label>Wrap
                    <select data-field="wrap">
                        <option value="with" ${el.wrap === 'with' ? 'selected' : ''}>With text</option>
                        <option value="behind" ${el.wrap === 'behind' ? 'selected' : ''}>Behind text</option>
                    </select>
                </label>
                <label>Height (lines, max 5) <input type="number" min="2" max="5" data-field="heightLines" value="${el.heightLines || 2}" style="width:60px"></label>
            </div>`;
            break;

        case 'pagebreak':
            body = `<p style="color:var(--muted);font-size:13px;margin:0">Content after this point starts a new page (used for Half Page and Booklet layout routing).</p>`;
            break;
    }
    return `<div class="el-card" draggable="true" data-id="${el.id}" data-type="${el.type}">${cardHeader(el)}${body}</div>`;
}

function renderElementsList() {
    elementsListEl.innerHTML = elements.map(buildCard).join('');
}

// ---------------- Preview ----------------
function renderPreview() {
    if (!paper) return;
    const mode = document.getElementById('psPrintMode').value;
    const size = document.getElementById('psPageSize').value;

    previewPageEl.className = 'pages-stack';
    previewPageEl.style.setProperty('--base-font-size', printSettings.fontSize + 'px');
    previewPageEl.style.setProperty('--line-height', printSettings.lineSpacing);
    previewPageEl.style.setProperty('--para-spacing', printSettings.paraSpacing + 'px');
    previewPageEl.style.setProperty('--header-font-size', printSettings.headerFontSize + 'px');
    previewPageEl.style.setProperty('--page-margin', printSettings.margin + 'mm');
    previewPageEl.style.setProperty('--gap-margin', printSettings.gap + 'mm');
    previewPageEl.style.setProperty('--paper-font', fontFamilyFor(paper));

    const { html } = buildPaperOutput(paper, elements, mode, size, printSettings);
    previewPageEl.innerHTML = html;
    applyPreviewZoom();
}

// ---------------- Preview zoom ----------------
const previewZoomSelect = document.getElementById('previewZoom');

function applyPreviewZoom() {
    const mode = previewZoomSelect.value;
    previewPageEl.style.zoom = 1;
    const firstPage = previewPageEl.querySelector('.paper-page');
    if (mode === 'fit') {
        const container = document.querySelector('.editor-preview');
        const naturalWidth = firstPage ? firstPage.getBoundingClientRect().width : 0;
        const availWidth = container.clientWidth - 32;
        previewPageEl.style.zoom = naturalWidth > 0 ? Math.min(1, availWidth / naturalWidth) : 1;
    } else {
        previewPageEl.style.zoom = Number(mode);
    }
}

previewZoomSelect.addEventListener('change', applyPreviewZoom);
window.addEventListener('resize', () => {
    if (previewZoomSelect.value === 'fit') applyPreviewZoom();
});

// ---------------- Rich text: toolbar actions ----------------
let lastFocusedEditable = null;
document.addEventListener('focusin', (e) => {
    if (e.target.matches('.richtext-editable')) lastFocusedEditable = e.target;
});

const symbolPicker = document.getElementById('symbolPicker');
const SYMBOLS = ['∴', '∵', '∝', '√', 'π', 'θ', '∞', '±', '≈', '≠', '≤', '≥', '∑', '∫', 'Δ', 'α',
    'β', 'γ', 'λ', 'μ', '÷', '×', '°', '²', '³', '½', '¼', '→', '∈', '∪', '∩', '≡'];

function openSymbolPicker(anchorRect) {
    symbolPicker.innerHTML = SYMBOLS.map(s => `<button type="button" data-sym="${s}">${s}</button>`).join('');
    symbolPicker.style.left = anchorRect.left + 'px';
    symbolPicker.style.top = (anchorRect.bottom + 6) + 'px';
    symbolPicker.hidden = false;
}
symbolPicker.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-sym]');
    if (!btn || !lastFocusedEditable) return;
    lastFocusedEditable.focus();
    document.execCommand('insertText', false, btn.dataset.sym);
    symbolPicker.hidden = true;
    lastFocusedEditable.dispatchEvent(new Event('input', { bubbles: true }));
});
document.addEventListener('click', (e) => {
    if (!symbolPicker.hidden && !symbolPicker.contains(e.target) && !e.target.closest('[data-cmd="symbol"]')) {
        symbolPicker.hidden = true;
    }
});

const imageFileInput = document.getElementById('imageFileInput');
let imageUploadTarget = null; // 'richtext' | element id for image-type card

async function uploadImageFile(file) {
    const fd = new FormData();
    fd.append('image', file);
    const res = await fetch(APP_BASE + '/api/upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    return res.json();
}

imageFileInput.addEventListener('change', async () => {
    const file = imageFileInput.files[0];
    if (!file) return;
    const result = await uploadImageFile(file);
    imageFileInput.value = '';
    if (!result.ok) { toast(result.error || 'Upload failed', 'error'); return; }

    if (imageUploadTarget === 'richtext' && lastFocusedEditable) {
        lastFocusedEditable.focus();
        document.execCommand('insertHTML', false, `<img src="${result.url}" style="max-width:100%">`);
        lastFocusedEditable.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (imageUploadTarget) {
        const el = getElement(imageUploadTarget);
        if (el) { el.src = result.url; rerenderCard(el.id); renderPreview(); scheduleSave(); }
    }
});

// ---------------- Event delegation: toolbox, cards, panel ----------------
document.querySelector('.toolbox').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-add]');
    if (btn) addElement(btn.dataset.add);
});

elementsListEl.addEventListener('click', (e) => {
    const card = e.target.closest('.el-card');
    if (!card) return;
    const id = card.dataset.id;
    const el = getElement(id);

    const actBtn = e.target.closest('button[data-act]');
    if (actBtn) {
        const act = actBtn.dataset.act;
        if (act === 'del') removeElement(id);
        else if (act === 'dup') duplicateElement(id);
        else if (act === 'uploadImage') { imageUploadTarget = id; imageFileInput.click(); }
        else if (act === 'addSub') {
            el.subQuestions = el.subQuestions || [];
            el.subQuestions.push({ text: '', marks: '' });
            rerenderCard(id); renderPreview(); scheduleSave();
        }
        else if (act === 'removeSub') {
            const row = e.target.closest('[data-sub-idx]');
            const idx = Number(row.dataset.subIdx);
            el.subQuestions.splice(idx, 1);
            rerenderCard(id); renderPreview(); scheduleSave();
        }
        return;
    }

    const cmdBtn = e.target.closest('button[data-cmd]');
    if (cmdBtn) {
        const editable = card.querySelector('.richtext-editable');
        if (!editable) return;
        editable.focus();
        lastFocusedEditable = editable;
        const cmd = cmdBtn.dataset.cmd;
        if (cmd === 'bold') document.execCommand('bold');
        else if (cmd === 'italic') document.execCommand('italic');
        else if (cmd === 'table') {
            document.execCommand('insertHTML', false,
                '<table><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table>');
        }
        else if (cmd === 'image') {
            const url = window.prompt('Paste an image URL (leave blank to upload a file from your device instead):', '');
            if (url === null) {
                // cancelled
            } else if (url.trim()) {
                const clean = sanitizeImageUrl(url);
                if (!clean) toast('That URL is not allowed', 'error');
                else document.execCommand('insertHTML', false, `<img src="${escapeAttr(clean)}" style="max-width:100%">`);
            } else {
                imageUploadTarget = 'richtext';
                imageFileInput.click();
            }
        }
        else if (cmd === 'symbol') openSymbolPicker(cmdBtn.getBoundingClientRect());
        editable.dispatchEvent(new Event('input', { bubbles: true }));
    }
});

elementsListEl.addEventListener('input', (e) => {
    const card = e.target.closest('.el-card');
    if (!card) return;
    const id = card.dataset.id;
    const el = getElement(id);
    if (!el) return;

    const field = e.target.dataset.field;
    if (field) {
        let val;
        if (e.target.matches('.richtext-editable')) val = e.target.innerHTML;
        else if (e.target.type === 'checkbox') val = e.target.checked;
        else val = e.target.value;
        el[field] = val;
        if (elementDefaults[el.type] && field in elementDefaults[el.type]) elementDefaults[el.type][field] = val;
    }

    const optIdx = e.target.dataset.optidx;
    if (optIdx !== undefined) el.options[Number(optIdx)] = e.target.value;

    const subRow = e.target.closest('[data-sub-idx]');
    if (subRow) {
        const idx = Number(subRow.dataset.subIdx);
        const subField = e.target.dataset.subfield;
        el.subQuestions[idx][subField] = e.target.value;
    }

    renderPreview();
    scheduleSave();
});

elementsListEl.addEventListener('change', (e) => {
    // structural toggles that change which sub-fields are visible (e.g. section marks toggle)
    if (e.target.dataset.field === 'showMarks' || e.target.dataset.field === 'showQuestionCount') {
        rerenderCard(e.target.closest('.el-card').dataset.id);
        renderPreview();
        scheduleSave();
    }
    if (e.target.dataset.field === 'bubbleStyle') {
        rerenderCard(e.target.closest('.el-card').dataset.id);
    }
    if (e.target.dataset.field === 'src') {
        rerenderCard(e.target.closest('.el-card').dataset.id);
    }
});

// ---------------- Drag reorder ----------------
let dragId = null;
elementsListEl.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.el-card');
    if (!card) return;
    dragId = card.dataset.id;
    card.classList.add('dragging');
});
elementsListEl.addEventListener('dragend', (e) => {
    e.target.closest('.el-card')?.classList.remove('dragging');
});
elementsListEl.addEventListener('dragover', (e) => {
    e.preventDefault();
    const card = e.target.closest('.el-card');
    if (!card || card.dataset.id === dragId) return;
    const rect = card.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;
    card.parentNode.insertBefore(elementsListEl.querySelector(`[data-id="${dragId}"]`), before ? card : card.nextSibling);
});
elementsListEl.addEventListener('drop', (e) => {
    e.preventDefault();
    const newOrderIds = Array.from(elementsListEl.querySelectorAll('.el-card')).map(c => c.dataset.id);
    elements.sort((a, b) => newOrderIds.indexOf(a.id) - newOrderIds.indexOf(b.id));
    renderPreview();
    scheduleSave();
});

// ---------------- Print settings panel ----------------
document.querySelectorAll('.print-settings-panel select, .print-settings-panel input').forEach(input => {
    input.addEventListener('input', () => {
        printSettings.lineSpacing = Number(document.getElementById('psLineSpacing').value);
        printSettings.paraSpacing = Number(document.getElementById('psParaSpacing').value);
        printSettings.fontSize = Number(document.getElementById('psFontSize').value);
        printSettings.headerFontSize = Number(document.getElementById('psHeaderFontSize').value);
        printSettings.headerBold = document.getElementById('psHeaderBold').checked;
        printSettings.margin = Number(document.getElementById('psMargin').value);
        printSettings.gap = Number(document.getElementById('psGap').value);
        document.getElementById('psGapWrap').hidden = !MULTI_COL_MODES.includes(document.getElementById('psPrintMode').value);
        renderPreview();
        scheduleSave();
    });
});

document.getElementById('printPreviewBtn').addEventListener('click', async () => {
    await savePaper();
    window.location.href = APP_BASE + '/view.php?id=' + window.PAPER_ID;
});

// ---------------- Import questions via external AI ----------------
function textToHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML.replace(/\n/g, '<br>');
}

const AI_JSON_SCHEMA = `{
  "header": {
    "school_name": "string, optional - only include to change the existing value",
    "exam_name": "string, optional",
    "class_name": "string, optional",
    "subject_name": "string, optional",
    "time_text": "string, optional, e.g. '3 Hours'",
    "full_marks": "string, optional, e.g. '100'",
    "subject_code": "string, optional, exactly 3 digits, e.g. '137'",
    "set_code": "string, optional, exactly 1 letter, e.g. 'A'"
  },
  "elements": [
    { "type": "instruction", "text": "plain text, use \\n for line breaks", "align": "center | left", "boxed": true|false, "italic": true|false, "fontSize": 14 },
    { "type": "section", "text": "e.g. 'Section A' or 'বিভাগ ক'", "bold": true|false, "align": "center | left", "showMarks": true|false, "marksText": "e.g. '5 x 3 = 15'", "showQuestionCount": true|false, "questionCount": "e.g. 'Answer any 3'" },
    { "type": "question", "text": "the question text, plain text with \\n for line breaks", "marks": "e.g. '5'", "showMarks": true|false, "align": "justify | left | right", "subQuestions": [ { "text": "sub-question text", "marks": "e.g. '2'" } ] },
    { "type": "mcq", "text": "the MCQ stem/question text", "options": ["option A text","option B text","option C text","option D text"], "layout": "4perline | 2perline | 1perline", "bubbleStyle": "letter | bangla | paren", "marks": "e.g. '1'", "showMarks": true|false },
    { "type": "fillblank", "text": "statement with the blank written as underscores, e.g. 'The capital of Bangladesh is ______.'", "marks": "e.g. '1'", "showMarks": true|false },
    { "type": "passage", "text": "the passage text for reading comprehension, no marks", "align": "justify | left | right" },
    { "type": "pagebreak" }
  ]
}`;

function buildAiPrompt() {
    const lang = paper?.language === 'bn' ? 'Bangla (বাংলা)' : 'English';
    return `You are converting an exam question paper into a strict JSON format for an automated question-paper-builder web app called Prosnopotro.

THE SYSTEM'S CAPABILITIES (so you convert correctly):
- The system auto-numbers questions (1, 2, 3... or ১, ২, ৩...) and auto-labels sub-questions (A, B, C... or ক, খ, গ...) and MCQ options (a, b, c, d) itself. Do NOT put any numbers or option letters inside any "text" field yourself.
- Element types available: "instruction" (notice/instructions text, can be centered or boxed), "section" (a section header like "Section A", can show marks like "5 x 3 = 15" and a question-count note like "Answer any 3"), "question" (a regular question, optionally with lettered sub-questions, each with its own marks — use this for creative/সৃজনশীল questions too), "mcq" (multiple choice with exactly 4 options, 3 possible layouts and 3 possible bubble styles), "fillblank" (a fill-in-the-blank statement), "passage" (a reading passage with no marks), "pagebreak" (marks a manual page break — only use this if the source document clearly marks a page boundary).
- The paper header (school name, exam name, class, subject, time, full marks, subject code, set code) is set once and rarely needs changing — only include the "header" object in your JSON if the source document gives you clearly different/updated header values; otherwise omit "header" entirely.
- Text fields must be PLAIN TEXT ONLY — no HTML tags, no markdown formatting, no numbering. Use "\\n" for line breaks within a field if genuinely needed.
- Write all question/option/section content in ${lang}, matching the language of this question paper.

OUTPUT FORMAT — return exactly one JSON object matching this schema (omit "header" if not needed, omit optional fields you have no data for):

${AI_JSON_SCHEMA}

STRICT RULES:
- Output ONLY the JSON object. No markdown code fences (no \`\`\`), no explanation, no extra text before or after it.
- The JSON must be valid and parseable.
- Preserve the original order of questions/sections as they appear in the source document.

Now convert the following question paper content into that JSON schema:

"""
<PASTE YOUR QUESTION PAPER TEXT OR DOCUMENT CONTENT HERE>
"""`;
}

const HEADER_FIELDS_IMPORTABLE = ['school_name', 'exam_name', 'class_name', 'subject_name', 'time_text', 'full_marks'];

function normalizeImportedElement(raw) {
    if (!raw || !TYPE_LABELS[raw.type]) return null;
    const type = raw.type;
    const base = Object.assign({ id: uid(), type }, elementDefaults[type] || {});

    if (type === 'instruction' || type === 'question' || type === 'mcq' || type === 'passage' || type === 'fillblank') {
        base.text = textToHtml(raw.text);
    }
    if (type === 'section') base.text = String(raw.text ?? '');

    if (type === 'instruction') {
        if (raw.align === 'left' || raw.align === 'center') base.align = raw.align;
        base.boxed = !!raw.boxed;
        base.italic = !!raw.italic;
        if (raw.fontSize) base.fontSize = Number(raw.fontSize) || 14;
    } else if (type === 'section') {
        base.bold = raw.bold !== false;
        if (raw.align === 'left' || raw.align === 'center') base.align = raw.align;
        base.showMarks = !!raw.showMarks;
        base.marksText = String(raw.marksText ?? '');
        base.showQuestionCount = !!raw.showQuestionCount;
        base.questionCount = String(raw.questionCount ?? '');
    } else if (type === 'question') {
        base.marks = String(raw.marks ?? '');
        base.showMarks = raw.showMarks !== false;
        if (['justify', 'left', 'right'].includes(raw.align)) base.align = raw.align;
        base.subQuestions = Array.isArray(raw.subQuestions)
            ? raw.subQuestions.map(sq => ({ text: textToHtml(sq?.text), marks: String(sq?.marks ?? '') }))
            : [];
    } else if (type === 'mcq') {
        let opts = Array.isArray(raw.options) ? raw.options.slice(0, 4).map(o => String(o ?? '')) : [];
        while (opts.length < 4) opts.push('');
        base.options = opts;
        base.marks = String(raw.marks ?? '');
        base.showMarks = raw.showMarks !== false;
        if (['4perline', '2perline', '1perline'].includes(raw.layout)) base.layout = raw.layout;
        if (['letter', 'bangla', 'paren'].includes(raw.bubbleStyle)) base.bubbleStyle = raw.bubbleStyle;
    } else if (type === 'passage') {
        if (['justify', 'left', 'right'].includes(raw.align)) base.align = raw.align;
    } else if (type === 'fillblank') {
        base.marks = String(raw.marks ?? '');
        base.showMarks = raw.showMarks !== false;
    }

    return base;
}

const aiImportOverlay = document.getElementById('aiImportOverlay');
const aiPromptTextEl = document.getElementById('aiPromptText');
const aiResultTextEl = document.getElementById('aiResultText');
const aiImportErrorEl = document.getElementById('aiImportError');

document.getElementById('aiImportOpenBtn').addEventListener('click', () => {
    aiPromptTextEl.value = buildAiPrompt();
    aiResultTextEl.value = '';
    aiImportErrorEl.textContent = '';
    aiImportOverlay.classList.add('open');
});
document.getElementById('aiImportClose').addEventListener('click', () => aiImportOverlay.classList.remove('open'));
aiImportOverlay.addEventListener('click', (e) => { if (e.target === aiImportOverlay) aiImportOverlay.classList.remove('open'); });

document.getElementById('copyPromptBtn').addEventListener('click', async () => {
    try {
        await navigator.clipboard.writeText(aiPromptTextEl.value);
        toast('Prompt copied');
    } catch (e) {
        aiPromptTextEl.select();
        document.execCommand('copy');
        toast('Prompt copied');
    }
});

document.getElementById('aiParseBtn').addEventListener('click', () => {
    aiImportErrorEl.textContent = '';
    let parsed;
    try {
        parsed = JSON.parse(aiResultTextEl.value.trim());
    } catch (e) {
        aiImportErrorEl.textContent = 'That doesn\'t look like valid JSON. Make sure you pasted the AI\'s full, unmodified reply.';
        return;
    }

    const rawElements = Array.isArray(parsed) ? parsed : parsed?.elements;
    if (!Array.isArray(rawElements) || rawElements.length === 0) {
        aiImportErrorEl.textContent = 'No questions found in that JSON (expected an "elements" array).';
        return;
    }

    const normalized = rawElements.map(normalizeImportedElement).filter(Boolean);
    if (normalized.length === 0) {
        aiImportErrorEl.textContent = 'None of the items had a recognized "type". Nothing was added.';
        return;
    }

    if (!Array.isArray(parsed) && parsed.header && typeof parsed.header === 'object') {
        const h = parsed.header;
        HEADER_FIELDS_IMPORTABLE.forEach(f => {
            if (typeof h[f] === 'string' && h[f].trim()) paper[f] = h[f].trim();
        });
        if (typeof h.subject_code === 'string' && /^\d{3}$/.test(h.subject_code.trim())) {
            paper.subject_code = h.subject_code.trim();
            paper.show_subject_code = true;
        }
        if (typeof h.set_code === 'string' && h.set_code.trim()) {
            paper.set_code = h.set_code.trim().charAt(0);
            paper.show_set_code = true;
        }
    }

    elements.push(...normalized);
    renderElementsList();
    renderPreview();
    scheduleSave();
    aiImportOverlay.classList.remove('open');
    toast(`Added ${normalized.length} item${normalized.length === 1 ? '' : 's'} from AI import`);
});

loadPaper();
