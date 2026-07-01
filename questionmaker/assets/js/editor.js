/* ===== QuestionMaker Pro — Editor Engine ===== */
const QM = (() => {

  /* ---- State ---- */
  let paper = {
    settings: {
      language: 'bangla',
      fontFamily: 'SutonnyOMJ', englishFont: 'Times New Roman', fontSize: 12, lineHeight: 1.7,
      paperSize: 'A4', layout: 'portrait', mcqCols: 1,
      margins: { top: 20, bottom: 20, left: 20, right: 20 },
      border: false, answerKey: false, autoDuplicate: false,
    },
    header: {
      institution: '', exam: '', class: '', subject: '', subjectCode: '',
      showSubjectCode: false, time: '', totalMarks: 0,
    },
    elements: [],
  };
  let paperId      = 0;
  let editIdx      = -1;   // index of element being edited
  let editPending  = null; // temp data from the open modal
  let autoSaveTimer = null;
  let imageTarget   = null; // 'paper' | {el index for image insert in question}
  let currentImgUrl = '';
  let insertAfterIdx = -1; // position for next insert-from-menu/image
  let zoomScale     = 0.45;
  let zoomMode      = 'fit'; // 'fit' | 'manual'

  /* ---- Boot ---- */
  function init() {
    paperId = parseInt(document.getElementById('paperId').value) || 0;
    if (paperId) loadPaper(paperId);
    _bindHeaderInputs();
    _bindSettingsInputs();
    _buildSymbolPicker();
    document.getElementById('paperTitle').addEventListener('input', scheduleSave);
    document.addEventListener('click', e => {
      if (!e.target.closest('#symbolPicker') && !e.target.closest('.sp-trigger')) {
        document.getElementById('symbolPicker').style.display = 'none';
      }
    });
    window.addEventListener('resize', () => {
      if (zoomMode === 'fit') applyZoom();
    });
    setTimeout(applyZoom, 200);
  }

  async function loadPaper(id) {
    const r = await api('paper.php?action=get&id=' + id);
    if (!r.success) { showToast('Paper not found', 'error'); return; }
    const p = r.paper;
    paper = p.paper_json;
    if (!paper.elements) paper.elements = [];
    document.getElementById('paperTitle').value = p.title;
    _populateHeader();
    _populateSettings();
    renderList();
    renderPreview();
    updateSaveStatus('saved');
  }

  /* ---- Header bindings ---- */
  function _bindHeaderInputs() {
    ['hInstitution','hExam','hClass','hSubject','hTime','hCode'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', () => { _syncHeader(); renderPreview(); scheduleSave(); });
    });
    document.getElementById('hShowCode').addEventListener('change', e => {
      paper.header.showSubjectCode = e.target.checked;
      document.getElementById('hCodeWrap').style.display = e.target.checked ? '' : 'none';
      renderPreview(); scheduleSave();
    });
  }

  function _syncHeader() {
    paper.header.institution    = v('hInstitution');
    paper.header.exam           = v('hExam');
    paper.header.class          = v('hClass');
    paper.header.subject        = v('hSubject');
    paper.header.time           = v('hTime');
    paper.header.subjectCode    = v('hCode');
    paper.header.totalMarks     = _calcTotalMarks();
    document.getElementById('hMarks').value = paper.header.totalMarks;
  }

  function _populateHeader() {
    const h = paper.header || {};
    sv('hInstitution', h.institution); sv('hExam', h.exam);
    sv('hClass', h.class); sv('hSubject', h.subject);
    sv('hTime', h.time); sv('hCode', h.subjectCode);
    document.getElementById('hShowCode').checked = h.showSubjectCode || false;
    document.getElementById('hCodeWrap').style.display = h.showSubjectCode ? '' : 'none';
    document.getElementById('hMarks').value = h.totalMarks || 0;
  }

  /* ---- Settings bindings ---- */
  function _bindSettingsInputs() {
    ['sLanguage','sFontFamily','sEnglishFont','sFontSize','sLineHeight','sPaperSize','sLayout','sMcqCols',
     'mTop','mBottom','mLeft','mRight',
     'sHeaderFontSize','sHeaderLineHeight','sSectionFontSize','sSectionLineHeight',
     'sInstructionFontSize','sInstructionLineHeight','sQuestionFontSize','sQuestionLineHeight',
     'sSummaryFontSize','sSummaryLineHeight','sTableFontSize','sTableLineHeight'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', () => { _syncSettings(); renderPreview(); scheduleSave(); });
    });
    document.getElementById('sBorder').addEventListener('change',    e => { paper.settings.border    = e.target.checked; renderPreview(); scheduleSave(); });
    document.getElementById('sAnswerKey').addEventListener('change', e => { paper.settings.answerKey = e.target.checked; renderPreview(); scheduleSave(); });
    document.getElementById('sAutoDuplicate').addEventListener('change', e => { paper.settings.autoDuplicate = e.target.checked; renderPreview(); scheduleSave(); });
    document.getElementById('sLayout').addEventListener('change', e => {
      const halfWrap = document.getElementById('sHalfDupWrap');
      halfWrap.style.display = e.target.value === 'landscape-half' ? '' : 'none';
      if (e.target.value === 'landscape-half' || e.target.value === 'booklet') {
        sv('mTop', 10); sv('mBottom', 10); sv('mLeft', 10); sv('mRight', 10);
      } else {
        sv('mTop', 20); sv('mBottom', 20); sv('mLeft', 20); sv('mRight', 20);
      }
      _syncSettings();
      renderPreview();
      scheduleSave();
    });
  }

  function _syncSettings() {
    const s = paper.settings;
    s.language     = v('sLanguage') || 'bangla';
    s.fontFamily   = v('sFontFamily');
    s.englishFont  = v('sEnglishFont') || 'Times New Roman';
    s.fontSize     = parseFloat(v('sFontSize')) || 12;
    s.lineHeight   = parseFloat(v('sLineHeight')) || 1.7;
    s.paperSize    = v('sPaperSize');
    s.layout       = v('sLayout');
    s.mcqCols      = parseInt(v('sMcqCols')) || 1;
    s.margins      = { top: +v('mTop')||15, bottom: +v('mBottom')||15, left: +v('mLeft')||20, right: +v('mRight')||15 };
    
    s.headerFontSize        = parseFloat(v('sHeaderFontSize')) || null;
    s.headerLineHeight      = parseFloat(v('sHeaderLineHeight')) || null;
    s.sectionFontSize       = parseFloat(v('sSectionFontSize')) || null;
    s.sectionLineHeight      = parseFloat(v('sSectionLineHeight')) || null;
    s.instructionFontSize   = parseFloat(v('sInstructionFontSize')) || null;
    s.instructionLineHeight = parseFloat(v('sInstructionLineHeight')) || null;
    s.questionFontSize      = parseFloat(v('sQuestionFontSize')) || null;
    s.questionLineHeight     = parseFloat(v('sQuestionLineHeight')) || null;
    s.tableFontSize         = parseFloat(v('sTableFontSize')) || null;
    s.tableLineHeight       = parseFloat(v('sTableLineHeight')) || null;
    s.summaryFontSize       = parseFloat(v('sSummaryFontSize')) || null;
    s.summaryLineHeight     = parseFloat(v('sSummaryLineHeight')) || null;
  }

  function _populateSettings() {
    const s = paper.settings || {};
    sv('sLanguage', s.language || 'bangla');
    sv('sFontFamily', s.fontFamily); sv('sEnglishFont', s.englishFont || 'Times New Roman');
    sv('sFontSize', s.fontSize); sv('sLineHeight', s.lineHeight);
    sv('sPaperSize', s.paperSize);   sv('sLayout', s.layout);     sv('sMcqCols', s.mcqCols);
    const m = s.margins || {};
    sv('mTop', m.top); sv('mBottom', m.bottom); sv('mLeft', m.left); sv('mRight', m.right);
    
    sv('sHeaderFontSize', s.headerFontSize || '');
    sv('sHeaderLineHeight', s.headerLineHeight || '');
    sv('sSectionFontSize', s.sectionFontSize || '');
    sv('sSectionLineHeight', s.sectionLineHeight || '');
    sv('sInstructionFontSize', s.instructionFontSize || '');
    sv('sInstructionLineHeight', s.instructionLineHeight || '');
    sv('sQuestionFontSize', s.questionFontSize || '');
    sv('sQuestionLineHeight', s.questionLineHeight || '');
    sv('sTableFontSize', s.tableFontSize || '');
    sv('sTableLineHeight', s.tableLineHeight || '');
    sv('sSummaryFontSize', s.summaryFontSize || '');
    sv('sSummaryLineHeight', s.summaryLineHeight || '');

    document.getElementById('sBorder').checked    = s.border    || false;
    document.getElementById('sAnswerKey').checked = s.answerKey || false;
    document.getElementById('sHalfDupWrap').style.display = s.layout === 'landscape-half' ? '' : 'none';
    document.getElementById('sAutoDuplicate').checked = s.autoDuplicate || false;
    applyFont();
  }

  function applyFont() {
    paper.settings.fontFamily  = v('sFontFamily');
    paper.settings.englishFont = v('sEnglishFont') || 'Times New Roman';
    renderPreview();
  }

  /* ---- Element list rendering ---- */
  function renderList() {
    const list = document.getElementById('elementsList');
    const els  = paper.elements;
    document.getElementById('elCount').textContent = els.length + ' element' + (els.length !== 1 ? 's' : '');

    if (!els.length) {
      list.innerHTML = `<div style="text-align:center;padding:40px 20px;color:var(--text-3);">
        <div style="font-size:40px;margin-bottom:12px;">📝</div>
        <p>No elements yet. Use the buttons above to add questions.</p></div>
        <div class="insert-slot"><button class="insert-btn" title="Insert here" onclick="QM.showInsertMenu(-1,this)">+</button></div>`;
      return;
    }

    const slot = i => `<div class="insert-slot"><button class="insert-btn" title="Insert here" onclick="QM.showInsertMenu(${i},this)">+</button></div>`;
    list.innerHTML = slot(-1) + els.map((el, i) => _elCardHTML(el, i) + slot(i)).join('');

    // Drag-to-reorder
    list.querySelectorAll('.el-card[draggable]').forEach(card => {
      card.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', card.dataset.idx); card.style.opacity = '.4'; });
      card.addEventListener('dragend',   e => { card.style.opacity = '1'; });
      card.addEventListener('dragover',  e => { e.preventDefault(); card.classList.add('drag-over'); });
      card.addEventListener('dragleave', e => { card.classList.remove('drag-over'); });
      card.addEventListener('drop',      e => {
        e.preventDefault(); card.classList.remove('drag-over');
        const from = parseInt(e.dataTransfer.getData('text/plain'));
        const to   = parseInt(card.dataset.idx);
        if (from !== to) { const tmp = els.splice(from,1)[0]; els.splice(to,0,tmp); renderList(); renderPreview(); scheduleSave(); }
      });
    });
  }

  function _elCardHTML(el, i) {
    const type = el.type;
    let badge = type.replace('-',' ').toUpperCase();
    let preview = '';
    let sub = '';

    switch (type) {
      case 'section-header':
        preview = el.text || '(empty section header)';
        badge = 'SECTION';
        break;
      case 'instruction':
        preview = el.text || '(empty instruction)';
        badge = 'INSTRUCTION';
        break;
      case 'mcq':
        preview = `<span class="el-num">${el.number||'?'}</span>${el.question || '(no question text)'}`;
        sub = `${(el.options||[]).length} options · ${el.marks||1} mark${(el.marks||1)!==1?'s':''}`;
        badge = 'MCQ';
        break;
      case 'short':
        preview = `<span class="el-num">${el.number||'?'}</span>${el.question || '(no question text)'}`;
        sub = `${el.marks||1} mark${(el.marks||1)!==1?'s':''}`;
        badge = 'SHORT Q';
        break;
      case 'creative':
        preview = `<span class="el-num">${el.number||'?'}</span>${el.stimulus || '(no stimulus)'}`;
        sub = `${(el.subQuestions||[]).length} sub-questions · ${el.marks||10} marks`;
        badge = 'CREATIVE';
        break;
      case 'fill-blank':
        preview = `<span class="el-num">${el.number||'?'}</span>${el.template||'(empty)'}`;
        sub = `${el.marks||1} mark${(el.marks||1)!==1?'s':''}`;
        badge = 'FILL BLANK';
        break;
      case 'true-false':
        preview = `<span class="el-num">${el.number||'?'}</span>${el.statement||'(empty)'}`;
        sub = `${el.marks||1} mark${(el.marks||1)!==1?'s':''}`;
        badge = 'TRUE/FALSE';
        break;
      case 'image':
        preview = el.url ? `🖼️ ${el.url.split('/').pop()}` : '(no image)';
        badge = 'IMAGE';
        break;
      case 'text-block':
        preview = (el.text || '(empty)').substring(0, 60);
        badge = 'TEXT';
        break;
      case 'table':
        preview = el.caption ? `📊 ${el.caption}` : `📊 Table (${(el.rows||[]).length} rows × ${(el.headers||[]).length||((el.rows||[[]])[0]||[]).length} cols)`;
        badge = 'TABLE';
        break;
      case 'page-break':
        preview = '——— Page Break ———';
        badge = 'PAGE BREAK';
        break;
    }

    const isPageBreak = type === 'page-break';
    return `<div class="el-card${isPageBreak?' page-break':''}" draggable="true" data-idx="${i}">
      <span class="el-drag">⠿</span>
      <div class="el-body">
        <span class="el-type-badge">${badge}</span>
        <div class="el-preview">${preview}</div>
        ${sub ? `<div class="el-sub">${sub}</div>` : ''}
      </div>
      <div class="el-actions">
        <button class="el-action-btn edit" title="Edit" onclick="QM.openEditModal(${i})">✏️</button>
        <button class="el-action-btn clone" title="Duplicate" onclick="QM.cloneElement(${i})">⧉</button>
        ${!isPageBreak && type !== 'section-header' && type !== 'instruction' && type !== 'page-break'
          ? `<button class="el-action-btn bank" title="Save to Bank" onclick="QM.saveSingleToBank(${i})">🗄️</button>` : ''}
        <button class="el-action-btn del" title="Delete" onclick="QM.deleteElement(${i})">🗑</button>
        ${i > 0 ? `<button class="el-action-btn up" title="Move Up" onclick="QM.moveEl(${i},-1)">▲</button>` : ''}
        ${i < paper.elements.length-1 ? `<button class="el-action-btn dn" title="Move Down" onclick="QM.moveEl(${i},1)">▼</button>` : ''}
      </div>
    </div>`;
  }

  /* ---- Add / Delete / Move elements ---- */
  function addElement(type, afterIdx) {
    const pos = (afterIdx !== undefined) ? afterIdx + 1 : paper.elements.length;
    const defaults = {
      'section-header': { type:'section-header', text:'ক বিভাগ', alignment:'center', fontSize: null, marksInfo:'' },
      'instruction':    { type:'instruction', text:'যে কোনো ৫টি প্রশ্নের উত্তর দাও।', alignment:'left' },
      'mcq':            { type:'mcq', number:_nextNum('mcq', pos), question:'', marks:1, options:['','','',''], optionLayout:'4x1', correctAnswer:0, image:null },
      'short':          { type:'short', number:_nextNum('short', pos), question:'', marks:5, image:null },
      'creative':       { type:'creative', number:_nextNum('creative', pos), stimulus:'', marks:10, image:null, freeMove:false, stimulusTable:null,
                          subQuestions:[{label:'ক)',text:'',marks:1},{label:'খ)',text:'',marks:2},{label:'গ)',text:'',marks:3},{label:'ঘ)',text:'',marks:4}] },
      'fill-blank':     { type:'fill-blank', number:_nextNum('fill-blank', pos), template:'', marks:1, answers:[] },
      'true-false':     { type:'true-false', number:_nextNum('true-false', pos), statement:'', marks:1, answer:true },
      'image':          { type:'image', url:'', caption:'', width:100, align:'center', freeMove:false, zBehind:false, top:0, left:0 },
      'text-block':     { type:'text-block', text:'', alignment:'left', fontSize:null },
      'table':          { type:'table', caption:'', headers:[], rows:[['','',''],['','','']], borderStyle:'full' },
      'page-break':     { type:'page-break' },
    };
    const el = defaults[type];
    if (!el) return;
    if (type === 'image') { insertAfterIdx = pos - 1; openImgModal('paper'); return; }
    paper.elements.splice(pos, 0, el);
    renderList(); renderPreview(); scheduleSave();
    if (type !== 'page-break') openEditModal(pos);
  }

  function deleteElement(i) {
    paper.elements.splice(i, 1);
    renderList(); renderPreview(); scheduleSave();
  }

  function cloneElement(i) {
    const orig = paper.elements[i];
    if (!orig) return;
    const cloned = JSON.parse(JSON.stringify(orig));
    if (cloned.number !== undefined) {
      cloned.number = _nextNum(cloned.type, i + 1);
    }
    paper.elements.splice(i + 1, 0, cloned);
    renderList(); renderPreview(); scheduleSave();
    showToast('Element duplicated!', 'success');
  }

  function moveEl(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= paper.elements.length) return;
    [paper.elements[i], paper.elements[j]] = [paper.elements[j], paper.elements[i]];
    renderList(); renderPreview(); scheduleSave();
  }

  function _parseSerialNum(n) {
    if (n == null) return 0;
    // Convert Bangla digits (০-৯) to ASCII so parseInt works
    const ascii = String(n).replace(/[০-৯]/g, d => '০১২৩৪৫৬৭৮৯'.indexOf(d));
    const num = parseInt(ascii);
    return isNaN(num) ? 0 : num;
  }

  function _nextNum(type, pos) {
    const scanLimit = pos !== undefined ? pos : paper.elements.length;
    let lastSecIdx = -1;
    for (let k = scanLimit - 1; k >= 0; k--) {
      if (paper.elements[k].type === 'section-header') {
        lastSecIdx = k;
        break;
      }
    }
    const elementsToScan = lastSecIdx >= 0
      ? paper.elements.slice(lastSecIdx + 1, scanLimit)
      : paper.elements.slice(0, scanLimit);
    const parsed = elementsToScan.filter(e => e.type === type && e.number != null).map(e => _parseSerialNum(e.number));
    const maxNum = parsed.length ? Math.max(...parsed) : 0;
    // If serials are numeric, increment max; otherwise fall back to count+1
    if (maxNum > 0) return maxNum + 1;
    return elementsToScan.filter(e => e.type === type).length + 1;
  }

  function _calcTotalMarks() {
    return paper.elements.reduce((acc, el) => acc + (parseInt(el.marks) || 0), 0);
  }

  /* ---- Edit Modal ---- */
  function openEditModal(i) {
    editIdx = i;
    const el = JSON.parse(JSON.stringify(paper.elements[i])); // deep copy
    editPending = el;
    document.getElementById('editModalTitle').textContent = 'Edit: ' + el.type.replace('-',' ').toUpperCase();
    document.getElementById('editModalBody').innerHTML = _buildEditForm(el);
    document.getElementById('editModal').style.display = 'flex';
    _hookEditFormEvents(el);
  }

  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    editIdx = -1; editPending = null;
  }

  function saveElement() {
    if (editIdx < 0 || !editPending) return;
    _readEditForm();
    paper.elements[editIdx] = editPending;
    paper.header.totalMarks = _calcTotalMarks();
    document.getElementById('hMarks').value = paper.header.totalMarks;
    closeEditModal();
    renderList(); renderPreview(); scheduleSave();
  }

  function _buildTypographyFields(el) {
    return `
      <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:14px;margin-bottom:14px;">
        <div style="font-size:11px;font-weight:600;color:var(--accent);text-transform:uppercase;margin-bottom:8px;letter-spacing:.05em;">Granular Typography (Optional)</div>
        <div class="field-row">
          <div class="field-group" style="flex:1">
            <label class="field-label" style="font-size:10px;">Font Size (pt)</label>
            <input class="field-input" type="number" id="ef_ty_fs" value="${el.fontSize||''}" placeholder="Default" min="6" max="36" step="0.5">
          </div>
          <div class="field-group" style="flex:1">
            <label class="field-label" style="font-size:10px;">Line Height</label>
            <input class="field-input" type="number" id="ef_ty_lh" value="${el.lineHeight||''}" placeholder="Default" min="0.8" max="3" step="0.1">
          </div>
          <div class="field-group" style="flex:1">
            <label class="field-label" style="font-size:10px;">Letter Spacing (px)</label>
            <input class="field-input" type="number" id="ef_ty_ls" value="${el.letterSpacing||''}" placeholder="Default" min="-2" max="10" step="0.5">
          </div>
        </div>
      </div>
    `;
  }

  function _buildEditForm(el) {
    const isEn    = paper.settings.language === 'english';
    const font    = isEn ? (paper.settings.englishFont || 'Times New Roman') : paper.settings.fontFamily;
    const ffStyle = `font-family:'${font}', serif;font-size:14px;`;

    switch (el.type) {
      case 'section-header':
        return `
          <div class="field-group"><label class="field-label">Section Header Text <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_text',event)">∑</button></label>
            <input class="field-input" id="ef_text" value="${esc(el.text)}" placeholder="e.g. ক বিভাগ" style="${ffStyle}"></div>
          <div class="field-group"><label class="field-label">Alignment</label>
            <select class="field-input" id="ef_align">
              <option value="center"${el.alignment==='center'?' selected':''}>Center</option>
              <option value="left"${el.alignment==='left'?' selected':''}>Left</option>
              <option value="right"${el.alignment==='right'?' selected':''}>Right</option>
            </select></div>
          <div class="field-group"><label class="field-label">Marks Info (right side, e.g. ৫×১=৫)</label>
            <input class="field-input" id="ef_marksInfo" value="${esc(el.marksInfo||'')}" placeholder="e.g. ৩০×১=৩০ or leave blank" style="${ffStyle}"></div>` + _buildTypographyFields(el);

      case 'instruction':
        return `
          <div class="field-group"><label class="field-label">Instruction Text <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_text',event)">∑ / ↵</button></label>
            <textarea class="field-input" id="ef_text" rows="3" style="${ffStyle}">${esc(el.text)}</textarea></div>
          <div class="field-group"><label class="field-label">Alignment</label>
            <select class="field-input" id="ef_align">
              <option value="left"${el.alignment==='left'?' selected':''}>Left</option>
              <option value="center"${el.alignment==='center'?' selected':''}>Center</option>
            </select></div>` + _buildTypographyFields(el);

      case 'mcq':
        const optLabels = paper.settings.language === 'english' ? ['A','B','C','D'] : ['ক','খ','গ','ঘ'];
        return `
          <div class="field-row">
            <div class="field-group" style="flex:0 0 70px"><label class="field-label">Q No.</label><input class="field-input" type="text" id="ef_num" value="${el.number||1}"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Marks</label><input class="field-input" type="number" id="ef_marks" value="${el.marks||1}" min="0.5" step="0.5"></div>
            <div class="field-group" style="flex:1"><label class="field-label">Option Layout</label>
              <div class="option-layout-btns">
                <button class="opt-layout-btn${el.optionLayout==='4x1'?' active':''}" onclick="setOptLayout('4x1',this)">4 in a row</button>
                <button class="opt-layout-btn${el.optionLayout==='2x2'?' active':''}" onclick="setOptLayout('2x2',this)">2×2</button>
                <button class="opt-layout-btn${el.optionLayout==='1x4'?' active':''}" onclick="setOptLayout('1x4',this)">1 per row</button>
              </div>
              <input type="hidden" id="ef_optLayout" value="${el.optionLayout||'4x1'}">
            </div>
          </div>
          <div class="field-group"><label class="field-label">Question Text <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_q',event)">∑ Symbol</button></label>
            <textarea class="field-input" id="ef_q" rows="3" style="${ffStyle}" placeholder="Question text…">${esc(el.question)}</textarea></div>
          <div class="field-group"><label class="field-label">Options — click option label to mark as correct</label>
            <div class="mcq-options">
              ${optLabels.map((lbl,oi) => `
                <div class="mcq-option">
                  <button class="opt-label${el.correctAnswer===oi?' correct-mark':''}" style="background:${el.correctAnswer===oi?'var(--success)':'var(--bg-2)'};color:${el.correctAnswer===oi?'#fff':'var(--accent)'};border:1px solid ${el.correctAnswer===oi?'var(--success)':'var(--border)'};border-radius:6px;width:28px;height:28px;cursor:pointer;font-family:inherit;" onclick="setCorrect(${oi})">
                    ${lbl}
                  </button>
                  <input class="field-input" id="ef_opt${oi}" value="${esc(el.options[oi]||'')}" style="${ffStyle};flex:1;" placeholder="Option ${lbl}">
                  <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_opt${oi}',event)">∑</button>
                </div>`).join('')}
            </div></div>
          <div class="field-group"><label class="field-label">Image (optional)</label>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              ${el.image ? `<img id="ef_img_thumb" src="${el.image}" style="height:50px;border-radius:6px;">` : ''}
              <button class="btn btn-ghost btn-sm" onclick="QM.openImgModal('edit-mcq')">📷 ${el.image?'Change':'Add'} Image</button>
              ${el.image ? `<button class="btn btn-danger btn-sm" onclick="_clearMcqImg()">Remove</button>` : ''}
            </div>
            <input type="hidden" id="ef_img" value="${el.image||''}">
          </div>` + _buildTypographyFields(el);

      case 'short':
        return `
          <div class="field-row">
            <div class="field-group" style="flex:0 0 70px"><label class="field-label">Q No.</label><input class="field-input" type="text" id="ef_num" value="${el.number||1}"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Marks</label><input class="field-input" type="number" id="ef_marks" value="${el.marks||5}" min="0.5" step="0.5"></div>
          </div>
          <div class="field-group"><label class="field-label">Question Text <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_q',event)">∑ Symbol</button></label>
            <textarea class="field-input" id="ef_q" rows="4" style="${ffStyle}">${esc(el.question)}</textarea></div>
          <div class="field-group"><label class="field-label">Image (optional)</label>
            <div style="display:flex;gap:8px;align-items:center;">
              ${el.image ? `<img src="${el.image}" style="height:50px;border-radius:6px;">` : ''}
              <button class="btn btn-ghost btn-sm" onclick="QM.openImgModal('edit-short')">📷 ${el.image?'Change':'Add'} Image</button>
            </div>
            <input type="hidden" id="ef_img" value="${el.image||''}">
          </div>` + _buildTypographyFields(el);

      case 'creative': {
        const tbl = el.stimulusTable;
        const tblRows = tbl ? tbl.rows.length : 2;
        const tblCols = tbl ? (tbl.headers||tbl.rows[0]||[]).length : 3;
        return `
          <div class="field-row">
            <div class="field-group" style="flex:0 0 70px"><label class="field-label">Q No.</label><input class="field-input" type="text" id="ef_num" value="${el.number||1}"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Total Marks</label><input class="field-input" type="number" id="ef_marks" value="${el.marks||10}" readonly></div>
          </div>
          <div class="field-group"><label class="field-label">Stimulus / উদ্দীপক <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_stimulus',event)">∑</button></label>
            <textarea class="field-input" id="ef_stimulus" rows="4" style="${ffStyle}" placeholder="Stimulus paragraph…">${esc(el.stimulus)}</textarea></div>

          <div class="field-group">
            <label class="field-label">Stimulus Table
              <button class="btn btn-ghost btn-xs" onclick="toggleTableBuilder()">📊 ${tbl ? 'Edit' : 'Add'} Table</button>
              ${tbl ? `<button class="btn btn-danger btn-xs" onclick="clearStimulusTable()">Remove Table</button>` : ''}
            </label>
            <div id="tblBuilder" style="display:${tbl?'block':'none'};background:var(--bg-2);border-radius:8px;padding:10px;margin-top:6px;">
              <div class="field-row" style="gap:8px;margin-bottom:8px;">
                <div class="field-group" style="flex:0 0 80px"><label class="field-label" style="font-size:10px;">Rows</label>
                  <input class="field-input" type="number" id="tbl_rows" value="${tblRows}" min="1" max="15" onchange="rebuildTableGrid()"></div>
                <div class="field-group" style="flex:0 0 80px"><label class="field-label" style="font-size:10px;">Columns</label>
                  <input class="field-input" type="number" id="tbl_cols" value="${tblCols}" min="1" max="8" onchange="rebuildTableGrid()"></div>
              </div>
              <div id="tblGrid"></div>
            </div>
          </div>

          <div class="field-group"><label class="field-label">Stimulus Image <button class="btn btn-ghost btn-xs" onclick="QM.openImgModal('edit-creative')">📷 ${el.image?'Change':'Add'}</button></label>
            <input type="hidden" id="ef_img" value="${el.image||''}">
            ${el.image ? `<img src="${el.image}" style="max-height:60px;border-radius:6px;margin-top:6px;">` : ''}
          </div>
          <div class="field-group">
            <label class="field-label">Sub-Questions</label>
            ${(el.subQuestions||[]).map((sq,si) => `
              <div class="creative-sub">
                <div class="creative-sub-header">
                  <strong>${sq.label}</strong>
                  <input type="number" id="ef_sqm${si}" value="${sq.marks}" min="1" max="10" style="width:55px;" class="field-input" onchange="updateSQMark(${si},this.value)">
                </div>
                <textarea class="field-input" id="ef_sq${si}" rows="2" style="${ffStyle};font-size:13px;" placeholder="Sub-question text…">${esc(sq.text)}</textarea>
                <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_sq${si}',event)" style="margin-top:2px;">∑ / ↵</button>
              </div>`).join('')}
          </div>` + _buildTypographyFields(el);
      }

      case 'fill-blank':
        return `
          <div class="field-row">
            <div class="field-group" style="flex:0 0 70px"><label class="field-label">Q No.</label><input class="field-input" type="text" id="ef_num" value="${el.number||1}"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Marks</label><input class="field-input" type="number" id="ef_marks" value="${el.marks||1}" min="0.5" step="0.5"></div>
          </div>
          <div class="field-group"><label class="field-label">Sentence (use ___ for blanks)</label>
            <textarea class="field-input" id="ef_tmpl" rows="3" style="${ffStyle}" placeholder="e.g. বাংলাদেশের রাজধানী ___ এবং মুদ্রার নাম ___।">${esc(el.template)}</textarea></div>
          <div class="field-group"><label class="field-label">Answers (one per line)</label>
            <textarea class="field-input" id="ef_answers" rows="3" placeholder="ঢাকা&#10;টাকা">${(el.answers||[]).join('\n')}</textarea></div>` + _buildTypographyFields(el);

      case 'true-false':
        return `
          <div class="field-row">
            <div class="field-group" style="flex:0 0 70px"><label class="field-label">Q No.</label><input class="field-input" type="text" id="ef_num" value="${el.number||1}"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Marks</label><input class="field-input" type="number" id="ef_marks" value="${el.marks||1}" min="0.5" step="0.5"></div>
          </div>
          <div class="field-group"><label class="field-label">Statement</label>
            <textarea class="field-input" id="ef_stmt" rows="3" style="${ffStyle}">${esc(el.statement)}</textarea></div>
          <div class="field-group"><label class="field-label">Correct Answer</label>
            <select class="field-input" id="ef_ans">
              <option value="true"${el.answer===true?' selected':''}>True (সত্য)</option>
              <option value="false"${el.answer===false?' selected':''}>False (মিথ্যা)</option>
            </select></div>` + _buildTypographyFields(el);

      case 'image':
        return `
          <div class="field-group"><label class="field-label">Image</label>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              ${el.url ? `<img src="${el.url}" style="max-height:80px;border-radius:6px;border:1px solid var(--border);">` : '<span style="color:var(--text-3);">No image selected</span>'}
              <button class="btn btn-ghost btn-sm" onclick="QM.openImgModal('edit-img')">📷 ${el.url?'Change':'Upload / URL'}</button>
              ${el.url ? `<button class="btn btn-danger btn-sm" onclick="document.getElementById('ef_url').value='';this.closest('.field-group').querySelector('img')?.remove();this.closest('.field-group').querySelector('span')&&(this.closest('.field-group').querySelector('span').textContent='No image selected');this.remove();">✕ Remove</button>` : ''}
            </div>
            <input type="hidden" id="ef_url" value="${el.url||''}">
          </div>
          <div class="field-row">
            <div class="field-group" style="flex:1"><label class="field-label">Width (%)</label>
              <input class="field-input" type="number" id="ef_width" value="${el.width||100}" min="10" max="100"></div>
            <div class="field-group" style="flex:1"><label class="field-label">Alignment</label>
              <select class="field-input" id="ef_align">
                <option value="left"${(el.align||'center')==='left'?' selected':''}>Left</option>
                <option value="center"${(el.align||'center')==='center'?' selected':''}>Center</option>
                <option value="right"${(el.align||'center')==='right'?' selected':''}>Right</option>
              </select></div>
          </div>
          <div class="field-group"><label class="field-label">Caption</label>
            <input class="field-input" id="ef_caption" value="${esc(el.caption||'')}"></div>
          <div class="field-row" style="align-items:center;gap:16px;">
            <label class="toggle-label">
              <input type="checkbox" id="ef_freeMove" ${el.freeMove?'checked':''} onchange="document.getElementById('ef_posWrap').style.display=this.checked?'':'none'">
              <span class="toggle-track"></span> Free Move (absolute)
            </label>
            <label class="toggle-label">
              <input type="checkbox" id="ef_zBehind" ${el.zBehind?'checked':''}>
              <span class="toggle-track"></span> Behind Text (watermark)
            </label>
          </div>
          <div id="ef_posWrap" style="display:${el.freeMove?'':'none'};" class="field-row">
            <div class="field-group" style="flex:1"><label class="field-label">Top (%)</label>
              <input class="field-input" type="number" id="ef_top" value="${el.top||0}" min="0" max="100"></div>
            <div class="field-group" style="flex:1"><label class="field-label">Left (%)</label>
              <input class="field-input" type="number" id="ef_left" value="${el.left||0}" min="0" max="100"></div>
          </div>`;

      case 'text-block':
        return `
          <div class="field-group"><label class="field-label">Text Content <button class="btn btn-ghost btn-xs sp-trigger" onclick="openSP('ef_text',event)">∑ / ↵</button></label>
            <textarea class="field-input" id="ef_text" rows="6" style="${ffStyle}" placeholder="Enter paragraph text…">${esc(el.text||'')}</textarea></div>
          <div class="field-group"><label class="field-label">Alignment</label>
            <select class="field-input" id="ef_align">
              <option value="left"${(el.alignment||'left')==='left'?' selected':''}>Left</option>
              <option value="center"${el.alignment==='center'?' selected':''}>Center</option>
              <option value="right"${el.alignment==='right'?' selected':''}>Right</option>
              <option value="justify"${el.alignment==='justify'?' selected':''}>Justify</option>
            </select></div>` + _buildTypographyFields(el);

      case 'table': {
        const tRows = (el.rows||[]).length || 2;
        const tCols = (el.headers||[]).length || ((el.rows||[[]])[0]||[]).length || 3;
        return `
          <div class="field-group"><label class="field-label">Caption (optional)</label>
            <input class="field-input" id="ef_caption" value="${esc(el.caption||'')}" placeholder="Table title or description"></div>
          <div class="field-row" style="gap:8px;margin-bottom:8px;">
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Rows</label>
              <input class="field-input" type="number" id="st_rows" value="${tRows}" min="1" max="20" onchange="rebuildStGrid()"></div>
            <div class="field-group" style="flex:0 0 80px"><label class="field-label">Columns</label>
              <input class="field-input" type="number" id="st_cols" value="${tCols}" min="1" max="10" onchange="rebuildStGrid()"></div>
            <div class="field-group" style="flex:1"><label class="field-label">Border Style</label>
              <select class="field-input" id="ef_borderStyle">
                <option value="full"${(el.borderStyle||'full')==='full'?' selected':''}>Full Grid</option>
                <option value="outer"${el.borderStyle==='outer'?' selected':''}>Outer Only</option>
                <option value="none"${el.borderStyle==='none'?' selected':''}>None</option>
              </select></div>
          </div>
          <div id="stGrid"></div>` + _buildTypographyFields(el);
      }

      default:
        return '<p style="color:var(--text-3);">No editable properties for this element.</p>';
    }
  }

  function _readStandaloneTableGrid() {
    const rows = parseInt(document.getElementById('st_rows')?.value) || 0;
    const cols = parseInt(document.getElementById('st_cols')?.value) || 0;
    if (!rows || !cols) return { headers: [], rows: [] };
    const headers = [];
    for (let c = 0; c < cols; c++) headers.push(document.getElementById(`st_h${c}`)?.value || '');
    const dataRows = [];
    for (let r = 0; r < rows; r++) {
      const row = [];
      for (let c = 0; c < cols; c++) row.push(document.getElementById(`st_r${r}_c${c}`)?.value || '');
      dataRows.push(row);
    }
    return { headers, rows: dataRows };
  }

  function _readTableGrid() {
    const builder = document.getElementById('tblBuilder');
    if (!builder || builder.style.display === 'none') return null;
    const rows = parseInt(document.getElementById('tbl_rows')?.value) || 0;
    const cols = parseInt(document.getElementById('tbl_cols')?.value) || 0;
    if (!rows || !cols) return null;
    const headers = [];
    for (let c = 0; c < cols; c++) headers.push(document.getElementById(`tbl_h${c}`)?.value || '');
    const dataRows = [];
    for (let r = 0; r < rows; r++) {
      const row = [];
      for (let c = 0; c < cols; c++) row.push(document.getElementById(`tbl_r${r}_c${c}`)?.value || '');
      dataRows.push(row);
    }
    const allEmpty = !headers.some(h => h) && !dataRows.some(r => r.some(c => c));
    if (allEmpty) return null;
    return { headers, rows: dataRows };
  }

  function _hookEditFormEvents(el) {
    window._clearMcqImg = () => {
      if (!editPending) return;
      editPending.image = null;
      const inp = document.getElementById('ef_img');
      if (inp) inp.value = '';
      const thumb = document.getElementById('ef_img_thumb');
      if (thumb) thumb.remove();
      document.querySelectorAll('#editModalBody .btn-danger').forEach(b => { if (b.textContent.trim() === 'Remove') b.remove(); });
    };

    // Correct answer radio for MCQ
    window.setCorrect = (idx) => {
      editPending.correctAnswer = idx;
      document.querySelectorAll('#editModalBody .opt-label').forEach((b,i) => {
        b.style.background = i === idx ? 'var(--success)' : 'var(--bg-2)';
        b.style.color      = i === idx ? '#fff' : 'var(--accent)';
        b.style.border     = `1px solid ${i === idx ? 'var(--success)' : 'var(--border)'}`;
      });
    };
    window.setOptLayout = (val, btn) => {
      document.getElementById('ef_optLayout').value = val;
      document.querySelectorAll('.opt-layout-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    };
    window.updateSQMark = (si, val) => {
      if (editPending.subQuestions && editPending.subQuestions[si]) {
        editPending.subQuestions[si].marks = parseInt(val) || 1;
        editPending.marks = editPending.subQuestions.reduce((a,s)=>a+(s.marks||0),0);
      }
    };

    // Table builder for creative stimulus
    window.rebuildTableGrid = () => {
      const rows = parseInt(document.getElementById('tbl_rows')?.value) || 2;
      const cols = parseInt(document.getElementById('tbl_cols')?.value) || 3;
      const grid = document.getElementById('tblGrid');
      if (!grid) return;
      const existingTbl = editPending.stimulusTable;
      let html = '<table style="width:100%;border-collapse:collapse;margin-bottom:6px;">';
      html += '<tr>';
      for (let c = 0; c < cols; c++) {
        const val = existingTbl?.headers?.[c] || '';
        html += `<td style="padding:2px;"><input id="tbl_h${c}" value="${val.replace(/"/g,'&quot;')}" placeholder="হেডার ${c+1}" style="width:100%;padding:4px;font-size:12px;border:1px solid #999;border-radius:4px;background:#eee;font-weight:bold;"></td>`;
      }
      html += '</tr>';
      for (let r = 0; r < rows; r++) {
        html += '<tr>';
        for (let c = 0; c < cols; c++) {
          const val = existingTbl?.rows?.[r]?.[c] || '';
          html += `<td style="padding:2px;"><input id="tbl_r${r}_c${c}" value="${val.replace(/"/g,'&quot;')}" placeholder="${r+1},${c+1}" style="width:100%;padding:4px;font-size:12px;border:1px solid #ccc;border-radius:4px;"></td>`;
        }
        html += '</tr>';
      }
      html += '</table>';
      grid.innerHTML = html;
    };
    window.toggleTableBuilder = () => {
      const div = document.getElementById('tblBuilder');
      if (!div) return;
      const nowVisible = div.style.display !== 'none';
      div.style.display = nowVisible ? 'none' : '';
      if (!nowVisible) window.rebuildTableGrid();
    };
    window.clearStimulusTable = () => {
      editPending.stimulusTable = null;
      const builder = document.getElementById('tblBuilder');
      if (builder) builder.style.display = 'none';
      showToast('Table removed', 'success');
    };

    // Auto-initialize table grid if creative element already has a table
    if (el.type === 'creative' && el.stimulusTable) {
      window.rebuildTableGrid();
    }

    // Standalone table grid builder
    window.rebuildStGrid = () => {
      const rows = parseInt(document.getElementById('st_rows')?.value) || 2;
      const cols = parseInt(document.getElementById('st_cols')?.value) || 3;
      const grid = document.getElementById('stGrid');
      if (!grid) return;
      let html = '<table style="width:100%;border-collapse:collapse;margin-bottom:6px;">';
      html += '<tr>';
      for (let c = 0; c < cols; c++) {
        const val = (editPending.headers?.[c] || '').replace(/"/g, '&quot;');
        html += `<td style="padding:2px;"><input id="st_h${c}" value="${val}" placeholder="Header ${c+1}" style="width:100%;padding:4px;font-size:12px;border:1px solid #999;border-radius:4px;background:#eee;font-weight:bold;"></td>`;
      }
      html += '</tr>';
      for (let r = 0; r < rows; r++) {
        html += '<tr>';
        for (let c = 0; c < cols; c++) {
          const val = ((editPending.rows?.[r] || [])[c] || '').replace(/"/g, '&quot;');
          html += `<td style="padding:2px;"><input id="st_r${r}_c${c}" value="${val}" placeholder="${r+1},${c+1}" style="width:100%;padding:4px;font-size:12px;border:1px solid #ccc;border-radius:4px;"></td>`;
        }
        html += '</tr>';
      }
      html += '</table>';
      grid.innerHTML = html;
    };
    if (el.type === 'table') window.rebuildStGrid();
  }

  function _readEditForm() {
    const el = editPending;

    const tyFs = document.getElementById('ef_ty_fs');
    if (tyFs) el.fontSize = parseFloat(tyFs.value) || null;
    const tyLh = document.getElementById('ef_ty_lh');
    if (tyLh) el.lineHeight = parseFloat(tyLh.value) || null;
    const tyLs = document.getElementById('ef_ty_ls');
    if (tyLs) el.letterSpacing = parseFloat(tyLs.value) || null;

    switch (el.type) {
      case 'section-header':
        el.text = v('ef_text'); el.alignment = v('ef_align');
        el.marksInfo = v('ef_marksInfo');
        break;
      case 'instruction':
        el.text = v('ef_text'); el.alignment = v('ef_align');
        break;
      case 'mcq':
        el.number = v('ef_num') || el.number || 1; el.marks = parseFloat(v('ef_marks'))||1;
        el.question = v('ef_q'); el.optionLayout = v('ef_optLayout');
        el.options = [v('ef_opt0'),v('ef_opt1'),v('ef_opt2'),v('ef_opt3')];
        el.image = v('ef_img') || null;
        break;
      case 'short':
        el.number = v('ef_num') || el.number || 1; el.marks = parseFloat(v('ef_marks'))||5;
        el.question = v('ef_q'); el.image = v('ef_img') || null;
        break;
      case 'creative':
        el.number = v('ef_num') || el.number || 1;
        el.stimulus = v('ef_stimulus'); el.image = v('ef_img') || null;
        el.stimulusTable = _readTableGrid();
        (el.subQuestions||[]).forEach((sq,si) => {
          sq.text = v('ef_sq'+si); sq.marks = parseInt(v('ef_sqm'+si))||1;
        });
        el.marks = (el.subQuestions||[]).reduce((a,s)=>a+(s.marks||0),0);
        break;
      case 'fill-blank':
        el.number = v('ef_num') || el.number || 1; el.marks = parseFloat(v('ef_marks'))||1;
        el.template = v('ef_tmpl');
        el.answers = v('ef_answers').split('\n').map(s=>s.trim()).filter(Boolean);
        break;
      case 'true-false':
        el.number = v('ef_num') || el.number || 1; el.marks = parseFloat(v('ef_marks'))||1;
        el.statement = v('ef_stmt'); el.answer = v('ef_ans') === 'true';
        break;
      case 'image':
        el.url = v('ef_url'); el.caption = v('ef_caption');
        el.width = parseInt(v('ef_width'))||100;
        el.align = v('ef_align') || 'center';
        el.freeMove = document.getElementById('ef_freeMove')?.checked || false;
        el.zBehind  = document.getElementById('ef_zBehind')?.checked  || false;
        el.top  = parseInt(v('ef_top'))  || 0;
        el.left = parseInt(v('ef_left')) || 0;
        break;
      case 'text-block':
        el.text = v('ef_text');
        el.alignment = v('ef_align') || 'left';
        break;
      case 'table': {
        el.caption = v('ef_caption');
        el.borderStyle = v('ef_borderStyle') || 'full';
        const td = _readStandaloneTableGrid();
        el.headers = td.headers;
        el.rows = td.rows;
        break;
      }
    }
  }

  /* ---- Image modal ---- */
  function openImgModal(target) {
    imageTarget = target;
    currentImgUrl = '';
    document.getElementById('imgUpload').value = '';
    document.getElementById('imgUrl').value = '';
    document.getElementById('imgPreview').innerHTML = '';
    document.getElementById('imgModal').style.display = 'flex';
  }

  async function uploadImg() {
    try {
      const file = document.getElementById('imgUpload').files[0];
      if (!file) return;
      const fd = new FormData(); fd.append('image', file);
      const r = await api('upload.php', fd);
      if (r.success) {
        currentImgUrl = r.url;
        document.getElementById('imgPreview').innerHTML = `<img src="${r.url}" style="max-height:120px;border-radius:8px;">`;
      } else { showToast(r.error || 'Upload failed', 'error'); }
    } catch (err) {
      console.error(err);
      showToast('Upload failed: ' + err.message, 'error');
    }
  }

  function confirmImg() {
    const url = currentImgUrl || document.getElementById('imgUrl').value.trim();
    if (!url) { showToast('Please upload or enter a URL', 'error'); return; }
    closeModal('imgModal');
    if (imageTarget === 'paper') {
      const el = { type:'image', url, caption:'', width:100, align:'center', freeMove:false, zBehind:false, top:0, left:0 };
      const pos = (insertAfterIdx >= -1) ? insertAfterIdx + 1 : paper.elements.length;
      paper.elements.splice(pos, 0, el);
      insertAfterIdx = -1;
      renderList(); renderPreview(); scheduleSave();
    } else if (imageTarget && editPending) {
      editPending.image = url;
      const hiddenInp = document.querySelector('#editModalBody #ef_img, #editModalBody #ef_url');
      if (hiddenInp) {
        hiddenInp.value = url;
        const fg = hiddenInp.closest('.field-group');
        if (fg) {
          const prevImg = fg.querySelector('img');
          if (prevImg) {
            prevImg.src = url;
          } else {
            const imgEl = document.createElement('img');
            imgEl.id = 'ef_img_thumb';
            imgEl.src = url;
            imgEl.style.cssText = 'height:50px;border-radius:6px;';
            const wrap = fg.querySelector('div[style*="display:flex"]');
            if (wrap) wrap.insertBefore(imgEl, wrap.firstChild);
          }
        }
      }
    }
  }

  /* ---- Math Symbol Picker ---- */
  function _buildSymbolPicker() {
    const grid = document.getElementById('spGrid');
    if (!grid) return;
    grid.innerHTML = Bangla.MATH_SYMBOLS.map(s =>
      `<button class="sp-btn" onclick="insertSymbol('${s}')" title="${s}">${s}</button>`
    ).join('');
  }

  window.openSP = (targetId, evt) => {
    evt.preventDefault(); evt.stopPropagation();
    const sp = document.getElementById('symbolPicker');
    sp.dataset.target = targetId;
    const btn = evt.target;
    const r = btn.getBoundingClientRect();
    sp.style.position = 'fixed';
    sp.style.top = (r.bottom + 4) + 'px';
    sp.style.left = Math.min(r.left, window.innerWidth - 290) + 'px';
    sp.style.display = 'block';
  };

  window.insertSymbol = (sym) => {
    const id = document.getElementById('symbolPicker').dataset.target;
    const el = document.getElementById(id);
    if (!el) return;
    const s = el.selectionStart, e = el.selectionEnd;
    el.value = el.value.slice(0, s) + sym + el.value.slice(e);
    el.selectionStart = el.selectionEnd = s + sym.length;
    el.focus();
    document.getElementById('symbolPicker').style.display = 'none';
  };

  /* ---- Question Bank ---- */
  async function saveToBank() {
    if (editIdx < 0 || !editPending) return;
    _readEditForm();
    const el = editPending;
    const payload = {
      question_type: el.type,
      subject: paper.header.subject || '',
      topic: '',
      content: el,
      tags: '',
    };
    const r = await api('qbank.php?action=save', JSON.stringify(payload), 'application/json');
    if (r.success) showToast('Saved to Question Bank!', 'success');
    else showToast(r.error || 'Save failed', 'error');
  }

  async function saveSingleToBank(i) {
    const el = paper.elements[i];
    const payload = { question_type: el.type, subject: paper.header.subject || '', topic: '', content: el, tags: '' };
    const r = await api('qbank.php?action=save', JSON.stringify(payload), 'application/json');
    if (r.success) showToast('Saved to Bank!', 'success');
    else showToast(r.error || 'Failed', 'error');
  }

  async function loadQBankPicker() {
    const type = v('qbFilter'), search = v('qbSearch');
    const r = await api(`qbank.php?action=list&type=${type}&search=${encodeURIComponent(search)}`);
    const list = document.getElementById('qbPickerList');
    const qs = r.questions || [];
    if (!qs.length) { list.innerHTML = '<p style="color:var(--text-3);text-align:center;padding:20px;">No questions found.</p>'; return; }
    list.innerHTML = qs.map(q => {
      const c = q.content || {};
      const preview = c.question || c.statement || c.stimulus || c.template || '(no preview)';
      return `<div style="background:var(--bg-2);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:8px;display:flex;gap:10px;align-items:flex-start;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:11px;color:var(--accent);font-weight:600;text-transform:uppercase;margin-bottom:3px;">${q.question_type}</div>
          <div style="font-size:13px;color:var(--text-0);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(String(preview).slice(0,120))}</div>
          ${q.tags ? `<div style="font-size:11px;color:var(--text-3);margin-top:3px;">${esc(q.tags)}</div>` : ''}
        </div>
        <button class="btn btn-accent btn-sm" onclick="QM.importFromBank(${q.id})">+ Add</button>
      </div>`;
    }).join('');
  }

  async function importFromBank(id) {
    const r = await api(`qbank.php?action=get&id=${id}`);
    const q = r.question;
    if (!r.success || !q || !q.content) { showToast('Not found', 'error'); return; }
    const el = JSON.parse(JSON.stringify(q.content));
    el.number = _nextNum(el.type);
    paper.elements.push(el);
    await api(`qbank.php?action=increment_use&id=${id}`);
    closeModal('qbankModal');
    renderList(); renderPreview(); scheduleSave();
    showToast('Question added from bank!', 'success');
  }

  function openQBank() { loadQBankPicker(); document.getElementById('qbankModal').style.display = 'flex'; }

  /* ---- AI Generator ---- */

  // Provider notes shown under the selector
  const PROVIDER_NOTES = {
    claude: '✦ Anthropic Claude — requires Claude API key in Settings.',
    openai: '⬡ OpenAI GPT-4o — requires OpenAI API key in Settings.',
    gemini: '✦ Google Gemini 1.5 Pro — requires Gemini API key in Settings.',
    manual: '📋 Manual mode — QMaker builds the prompt for you. Copy it into any AI (ChatGPT, Gemini, Copilot…) and paste the result back.',
  };

  function setProvider(p, btn) {
    document.getElementById('aiProvider').value = p;
    document.querySelectorAll('.prov-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('providerNote').textContent = PROVIDER_NOTES[p] || '';
    const isManual = p === 'manual';
    document.getElementById('manualPanel').style.display = isManual ? '' : 'none';
    document.getElementById('aiGenBtn').style.display    = isManual ? 'none' : '';
    // Reset manual panels on switch
    if (isManual) {
      document.getElementById('promptWrap').style.display = 'none';
      document.getElementById('pasteWrap').style.display  = '';   // always visible in manual mode
    }
  }

  function _aiPayload() {
    return {
      mode:       v('aiMode'),
      existing:   v('aiExistingText'),
      class:      v('aiClass'),
      lesson:     v('aiLesson'),
      topic:      v('aiTopic'),
      speciality: v('aiSpeciality'),
      type:       v('aiType'),
      count:      parseInt(v('aiCount')) || 5,
      difficulty: v('aiDifficulty'),
      language:   v('aiLanguage'),
      provider:   v('aiProvider'),
      subject:    paper.header.subject || '',
    };
  }

  async function runAI() {
    const topic = v('aiTopic');
    const existing = v('aiExistingText');
    if (!topic && !existing) { showToast('Enter a topic or existing question first', 'error'); return; }
    const btn = document.getElementById('aiGenBtn');
    btn.textContent = '⏳ Generating…'; btn.disabled = true;
    document.getElementById('aiStatusNote').textContent = '';

    const r = await api('ai.php', JSON.stringify({ action:'generate', ..._aiPayload() }), 'application/json');
    btn.textContent = '✨ Generate'; btn.disabled = false;

    if (!r.success) {
      showToast(r.error || 'AI failed', 'error');
      document.getElementById('aiStatusNote').textContent = r.error || '';
      return;
    }
    _renderAIResults(r.questions, r.type);
  }

  // Manual mode: generate the prompt
  async function getAIPrompt() {
    const topic = v('aiTopic');
    const existing = v('aiExistingText');
    if (!topic && !existing) { showToast('Enter a topic or existing question first', 'error'); return; }
    const btn = document.getElementById('getPromptBtn');
    btn.textContent = '⏳ Building…'; btn.disabled = true;

    const r = await api('ai.php', JSON.stringify({ action:'get_prompt', ..._aiPayload() }), 'application/json');
    btn.textContent = '📋 Generate Prompt'; btn.disabled = false;

    if (!r.success) { showToast(r.error || 'Failed', 'error'); return; }
    document.getElementById('aiPromptText').value = r.prompt;
    document.getElementById('promptWrap').style.display = '';
    document.getElementById('pasteWrap').style.display  = '';
    showToast('Prompt ready — copy and paste into any AI!', 'success');
  }

  function getBulkPrompt() {
    const s = paper.settings;
    const h = paper.header || {};
    
    // Prepare header details
    const inst = h.institution || '(Not specified)';
    const exam = h.exam || '(Not specified)';
    const cls  = v('aiClass') || h.class || '(Not specified)';
    const subj = h.subject || '(Not specified)';
    const code = h.subjectCode || '(Not specified)';
    const lang = v('aiLanguage') === 'bangla' ? 'Bengali (Bangla) Unicode script' : 'English';

    const bulkPrompt = `You are an expert academic question parser and formatting tool for Bangladesh education board exams.
Your task is to take external questions (copied from MS Word, Google Docs, or text files) and convert them into the structured JSON format used by this question generator.

EXAM CONTEXT / HEADER DETAILS:
- Institution: ${inst}
- Exam: ${exam}
- Class/Level: ${cls}
- Subject: ${subj}
- Subject Code: ${code}

DIFFICULTY LEVEL: ${v('aiDifficulty') || 'medium'}
LANGUAGE: Write everything in ${lang}.

YOUR INSTRUCTIONS:
Parse the external questions pasted below. Clean up any typos, correct errors, and translate/rephrase if necessary to match the exam context. Convert them into a single valid JSON array. Each element in the array MUST represent a question/section and include a "type" property specifying its layout.

SUPPORTED SCHEMAS PER TYPE:
1. MCQ:
   {"type": "mcq", "question": "Question text here", "options": ["Option 1", "Option 2", "Option 3", "Option 4"], "correct": 0, "marks": 1}
   (correct is the 0-based index of the correct option: 0 for Option 1, 1 for Option 2, etc.)

2. Short Answer:
   {"type": "short", "question": "Question text here", "marks": 5}

3. Creative (সৃজনশীল):
   {"type": "creative", "stimulus": "উদ্দীপক paragraph or context here", "subQuestions": [{"label": "ক)", "text": "Question A", "marks": 1}, {"label": "খ)", "text": "Question B", "marks": 2}, {"label": "গ)", "text": "Question C", "marks": 3}, {"label": "ঘ)", "text": "Question D", "marks": 4}], "marks": 10}

4. Fill in the Blank:
   {"type": "fill-blank", "template": "Sentence with ___ for blanks", "answers": ["blank_answer1"], "marks": 1}

5. True / False:
   {"type": "true-false", "statement": "Statement here", "answer": true, "marks": 1}

6. Section Header:
   {"type": "section-header", "text": "Section title (e.g. ক-বিভাগ)", "marksInfo": "Marks detail (optional)"}

7. Instruction:
   {"type": "instruction", "text": "General instruction text"}

OUTPUT RULES (strict):
- Output ONLY the raw valid JSON array. No markdown formatting, no code blocks (no \`\`\`json), no introductory or concluding text, no explanation.
- First character must be [ and last character must be ]

PASTE YOUR EXTERNAL QUESTIONS BELOW THIS LINE:
----------------------------------------------
`;

    document.getElementById('aiPromptText').value = bulkPrompt;
    document.getElementById('promptWrap').style.display = '';
    document.getElementById('pasteWrap').style.display  = '';
    
    // Copy to clipboard automatically
    navigator.clipboard.writeText(bulkPrompt).then(() => {
      showToast('General Bulk Prompt copied to clipboard!', 'success');
    });
  }

  function copyAIPrompt() {
    const txt = document.getElementById('aiPromptText').value;
    navigator.clipboard.writeText(txt).then(() => showToast('Prompt copied!', 'success'));
  }

  // Manual mode: parse the pasted AI response
  async function parseManual() {
    const text = document.getElementById('aiPasteText').value.trim();
    if (!text) { showToast('Paste the AI response first', 'error'); return; }
    const r = await api('ai.php', JSON.stringify({ action:'parse_manual', text, type: v('aiType') }), 'application/json');
    if (!r.success) { showToast(r.error || 'Parse failed', 'error'); return; }
    _renderAIResults(r.questions, r.type);
    showToast(`${r.questions.length} questions parsed!`, 'success');
  }

  function _renderAIResults(questions, type) {
    const container = document.getElementById('aiResults');
    container.style.display = 'block';
    const labels = paper.settings.language === 'english' ? ['A','B','C','D'] : ['ক','খ','গ','ঘ'];
    container.innerHTML = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <span style="font-size:12px;color:var(--text-2);font-weight:500;">${questions.length} questions ready</span>
        <button class="btn btn-accent btn-xs" onclick="QM.addAllAIQuestions()">+ Add All</button>
      </div>` +
      questions.map((q, i) => {
        const qType = q.type || type;
        const preview = q.question || q.statement || q.stimulus || q.template || q.text || '?';
        let opts = '';
        if (qType === 'mcq' && q.options) {
          opts = `<div class="ai-result-opts">${q.options.map((o,oi)=>`<span style="margin-right:8px;">${labels[oi]}) ${esc(String(o).slice(0,50))}</span>`).join('')}</div>`;
        }
        let ansHint = '';
        if (qType === 'mcq' && q.correct !== undefined) ansHint = `<div class="ai-result-ans">✓ Correct: ${labels[q.correct]}</div>`;
        if (qType === 'true-false') ansHint = `<div class="ai-result-ans">✓ ${q.answer ? 'True' : 'False'}</div>`;
        const typeBadge = `<span style="font-size:9px;background:var(--bg-3);color:var(--text-3);padding:2px 6px;border-radius:4px;font-weight:bold;text-transform:uppercase;margin-right:6px;">${qType}</span>`;
        return `<div class="ai-result-item">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="flex:1;">
              <div class="ai-result-q">${typeBadge}${esc(String(preview).slice(0,200))}</div>
              ${opts}${ansHint}
            </div>
            <button class="btn btn-accent btn-xs" style="flex-shrink:0;" onclick="QM.addAIQuestion(${i})">+ Add</button>
          </div>
        </div>`;
      }).join('');
    window._aiQuestions = { questions, type };
    const body = container.closest('.modal-body');
    if (body) body.scrollTop = body.scrollHeight;
  }

  // Strip "ক. " / "খ. " / "গ. " / "ঘ. " prefixes AI sometimes includes in options
  const _stripOptLabel = s => String(s).replace(/^[কখগঘ][.)]\s*/u, '');

  function addAIQuestion(i) {
    if (!window._aiQuestions) return;
    const { questions, type } = window._aiQuestions;
    const q   = questions[i];
    const qType = q.type || type;
    const num = _nextNum(qType);
    let el;

    if (qType === 'mcq') {
      el = { type:'mcq', number:num, question:q.question||'', marks:q.marks||1,
             options:(q.options||[]).map(_stripOptLabel), optionLayout:'4x1', correctAnswer:q.correct||0, image:null };
    } else if (qType === 'short') {
      el = { type:'short', number:num, question:q.question||'', marks:q.marks||5, image:null };
    } else if (qType === 'creative') {
      el = { type:'creative', number:num, stimulus:q.stimulus||'', marks:q.marks||10, image:null, freeMove:false,
             subQuestions:(q.subQuestions||[]).map(sq=>({label:sq.label||'ক)',text:sq.text||'',marks:sq.marks||1})) };
    } else if (qType === 'fill-blank') {
      el = { type:'fill-blank', number:num, template:q.template||'', marks:q.marks||1, answers:q.answers||[] };
    } else if (qType === 'true-false') {
      el = { type:'true-false', number:num, statement:q.statement||'', marks:q.marks||1, answer:q.answer!==false };
    } else if (qType === 'section-header') {
      el = { type:'section-header', text:q.text||q.question||'', marksInfo:q.marksInfo||'' };
    } else if (qType === 'instruction') {
      el = { type:'instruction', text:q.text||q.question||'' };
    }

    if (el) {
      paper.elements.push(el);
      renderList(); renderPreview(); scheduleSave();
      showToast('Question added to paper!', 'success');
    }
  }

  function addAllAIQuestions() {
    if (!window._aiQuestions) return;
    const { questions, type } = window._aiQuestions;
    let added = 0;
    questions.forEach(q => {
      const qType = q.type || type;
      const num = _nextNum(qType);
      let el;
      if (qType === 'mcq') {
        el = { type:'mcq', number:num, question:q.question||'',
               options:(q.options||[]).map(_stripOptLabel), optionLayout:'4x1', correctAnswer:q.correct||0, image:null };
      } else if (qType === 'short') {
        el = { type:'short', number:num, question:q.question||'', marks:q.marks||5, image:null };
      } else if (qType === 'creative') {
        el = { type:'creative', number:num, stimulus:q.stimulus||'', marks:q.marks||10, image:null, freeMove:false,
               subQuestions:(q.subQuestions||[]).map(sq=>({label:sq.label||'ক)',text:sq.text||'',marks:sq.marks||1})) };
      } else if (qType === 'fill-blank') {
        el = { type:'fill-blank', number:num, template:q.template||'', marks:q.marks||1, answers:q.answers||[] };
      } else if (qType === 'true-false') {
        el = { type:'true-false', number:num, statement:q.statement||'', marks:q.marks||1, answer:q.answer!==false };
      } else if (qType === 'section-header') {
        el = { type:'section-header', text:q.text||q.question||'', marksInfo:q.marksInfo||'' };
      } else if (qType === 'instruction') {
        el = { type:'instruction', text:q.text||q.question||'' };
      }
      if (el) { paper.elements.push(el); added++; }
    });
    if (added) {
      renderList(); renderPreview(); scheduleSave();
      showToast(`${added} questions added to paper!`, 'success');
    }
  }

  function openAI() {
    // Populate existing questions dropdown
    const sel = document.getElementById('aiExistingSelect');
    if (sel) {
      sel.innerHTML = '<option value="">-- Select Question --</option>';
      (paper.elements || []).forEach((el, idx) => {
        if (el.type !== 'page-break' && el.type !== 'section-header' && el.type !== 'instruction') {
          const qText = el.question || el.stimulus || el.statement || el.text || `Element ${idx + 1}`;
          const cleanText = qText.replace(/<[^>]*>/g, '').slice(0, 60) + (qText.length > 60 ? '...' : '');
          const opt = document.createElement('option');
          opt.value = JSON.stringify(el);
          opt.textContent = `Q${el.number || idx+1}: ${cleanText}`;
          sel.appendChild(opt);
        }
      });
    }

    // Prefill Class field from paper header if available
    const clsInput = document.getElementById('aiClass');
    if (clsInput && paper.header && paper.header.class) {
      clsInput.value = paper.header.class;
    }

    // Reset fields
    document.getElementById('aiMode').value = 'create';
    document.getElementById('aiExistingGroup').style.display = 'none';
    document.getElementById('aiExistingText').value = '';

    // Load preferred provider from user's settings
    fetch('api/auth.php?action=me').then(r=>r.json()).then(d => {
      const pref = (d.user && d.user.ai_keys && d.user.ai_keys.preferred) || 'claude';
      const btn  = document.querySelector(`.prov-btn[data-p="${pref}"]`);
      if (btn) setProvider(pref, btn);
    });
    document.getElementById('aiResults').style.display = 'none';
    document.getElementById('aiModal').style.display   = 'flex';
  }

  function toggleAIMode() {
    const mode = v('aiMode');
    const isModify = mode === 'modify';
    document.getElementById('aiExistingGroup').style.display = isModify ? '' : 'none';
    
    // In modify mode, count defaults to 1
    const countInput = document.getElementById('aiCount');
    if (isModify && countInput) {
      countInput.value = '1';
    }
  }

  function onAIExistingSelectChange() {
    const sel = document.getElementById('aiExistingSelect');
    const txt = document.getElementById('aiExistingText');
    if (sel && txt) {
      const val = sel.value;
      if (val) {
        try {
          const parsed = JSON.parse(val);
          txt.value = JSON.stringify(parsed, null, 2);
          
          // Auto-select matching question type
          const typeSelect = document.getElementById('aiType');
          if (typeSelect && parsed.type) {
            typeSelect.value = parsed.type;
          }
        } catch (e) {
          txt.value = val;
        }
      } else {
        txt.value = '';
      }
    }
  }

  /* ---- Share ---- */
  async function openShare() {
    if (!paperId) { await save(); }
    if (!paperId) { showToast('Save the paper first', 'error'); return; }
    loadShareLinks();
    document.getElementById('shareModal').style.display = 'flex';
  }

  async function loadShareLinks() {
    const r = await api(`share.php?action=list&paper_id=${paperId}`);
    const links = r.links || [];
    const host  = location.origin;
    const base  = (typeof APP_BASE !== 'undefined' ? APP_BASE : '/questionmaker/');
    document.getElementById('shareLinks').innerHTML = links.length
      ? links.map(l => `<div class="share-link-item">
          <span class="link-url">${host}${base}view.php?token=${l.share_token}</span>
          ${l.show_answers ? '<span style="font-size:11px;color:var(--success);">+Answers</span>' : ''}
          <button class="btn btn-ghost btn-xs copy-btn" onclick="copyLink('${host}${base}view.php?token=${l.share_token}')">📋 Copy</button>
          <button class="btn btn-danger btn-xs" onclick="QM.deleteLink('${l.share_token}')">✕</button>
        </div>`).join('')
      : '<p style="color:var(--text-3);font-size:13px;">No share links yet.</p>';
  }

  async function createShareLink() {
    const payload = { paper_id: paperId, show_answers: document.getElementById('shareShowAns').checked?1:0, expires_hours: parseInt(v('shareExpiry'))||0 };
    const r = await api('share.php?action=create', JSON.stringify(payload), 'application/json');
    if (r.success) { showToast('Link created!', 'success'); copyLink(r.url); loadShareLinks(); }
    else showToast(r.error || 'Failed', 'error');
  }

  async function deleteLink(token) {
    await api(`share.php?action=delete&token=${token}`);
    loadShareLinks();
  }

  window.copyLink = url => { navigator.clipboard.writeText(url).then(()=>showToast('Copied!','success')); };

  /* ---- Insert Menu ---- */
  function showInsertMenu(afterIdx, btn) {
    let menu = document.getElementById('insertMenu');
    if (!menu) {
      menu = document.createElement('div');
      menu.id = 'insertMenu';
      menu.className = 'insert-menu';
      document.body.appendChild(menu);
    }
    const items = [
      ['📝 Text Block', 'text-block'],
      ['📊 Table', 'table'],
      ['🖼️ Image', 'image'],
      null,
      ['Section Header', 'section-header'],
      ['Instruction', 'instruction'],
      null,
      ['MCQ Question', 'mcq'],
      ['Short Question', 'short'],
      ['Creative Question', 'creative'],
      ['Fill in the Blank', 'fill-blank'],
      ['True / False', 'true-false'],
      null,
      ['⎘ Page Break', 'page-break'],
    ];
    menu.innerHTML = items.map(it => it
      ? `<button class="im-btn" onclick="QM.hideInsertMenu();QM.addElement('${it[1]}',${afterIdx})">${it[0]}</button>`
      : '<div class="im-div"></div>'
    ).join('');
    const rect = btn.getBoundingClientRect();
    const mw = 175;
    let left = rect.left + window.scrollX;
    if (left + mw > window.innerWidth) left = window.innerWidth - mw - 8;
    menu.style.cssText = `display:block;top:${rect.bottom + window.scrollY + 4}px;left:${left}px;`;
    setTimeout(() => {
      document.addEventListener('click', function h(e) {
        if (!menu.contains(e.target)) { menu.style.display = 'none'; document.removeEventListener('click', h); }
      });
    }, 20);
  }

  function hideInsertMenu() {
    const m = document.getElementById('insertMenu');
    if (m) m.style.display = 'none';
  }

  function switchSidebarTab(tab) {
    document.querySelectorAll('.stab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.stab-pane').forEach(p => p.style.display = 'none');
    
    const activeBtn = document.getElementById('btnTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activeBtn) activeBtn.classList.add('active');
    
    const activePane = document.getElementById('pane' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activePane) activePane.style.display = 'block';
  }

  function toggleHeaderMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('headerActionMenu');
    if (!menu) return;
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) {
      document.addEventListener('click', function closeMenu(evt) {
        if (!menu.contains(evt.target)) {
          menu.style.display = 'none';
          document.removeEventListener('click', closeMenu);
        }
      });
    }
  }

  function hideHeaderMenu() {
    const menu = document.getElementById('headerActionMenu');
    if (menu) menu.style.display = 'none';
  }

  function adjustZoom(amount) {
    zoomMode = 'manual';
    zoomScale = Math.min(Math.max(zoomScale + amount, 0.1), 2.0);
    applyZoom();
  }
  
  function setZoomMode(mode) {
    zoomMode = mode === 'fit' ? 'fit' : 'manual';
    if (mode === '100') zoomScale = 1.0;
    applyZoom();
  }

  function applyZoom() {
    const container = document.getElementById('previewScaleWrap');
    if (!container) return;
    const cards = document.querySelectorAll('.preview-page-container');
    if (!cards.length) return;
    
    const s = paper.settings;
    const isLegal = s.paperSize === 'Legal';
    const isLandscape = s.layout === 'landscape-half' || s.layout === 'booklet';
    
    let unscaledWidth = 794;
    let unscaledHeight = 1123;
    if (isLegal) {
      unscaledWidth = 816;
      unscaledHeight = 1346;
    }
    if (isLandscape) {
      const tmp = unscaledWidth;
      unscaledWidth = unscaledHeight;
      unscaledHeight = tmp;
    }

    if (zoomMode === 'fit') {
      const parentWidth = container.clientWidth - 40;
      zoomScale = parentWidth / unscaledWidth;
      if (zoomScale > 1.2) zoomScale = 1.2;
    }
    
    cards.forEach(card => {
      const sheet = card.querySelector('.preview-page-sheet');
      if (sheet) {
        sheet.style.transform = `scale(${zoomScale})`;
        const scaledWidth = unscaledWidth * zoomScale;
        const scaledHeight = unscaledHeight * zoomScale;
        
        sheet.style.width = unscaledWidth + 'px';
        sheet.style.height = unscaledHeight + 'px';
        sheet.style.overflow = 'hidden';
        
        card.style.width = scaledWidth + 'px';
        card.style.height = (scaledHeight + 20) + 'px';

        // Remove any existing overflow warning
        const oldWarning = card.querySelector('.overflow-warning');
        if (oldWarning) oldWarning.remove();

        // Check for page overflow
        if (sheet.scrollHeight > unscaledHeight + 5) {
          const warning = document.createElement('div');
          warning.className = 'overflow-warning';
          warning.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(239, 68, 68, 0.95);
            color: #fff;
            text-align: center;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: bold;
            z-index: 100;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
            font-family: sans-serif;
            pointer-events: none;
          `;
          warning.innerHTML = `⚠️ PAGE OVERFLOW! Content exceeds sheet height. Please add a Page Break element.`;
          card.appendChild(warning);
        }
      }
    });

    const zoomPercent = document.getElementById('zoomPercent');
    if (zoomPercent) zoomPercent.textContent = Math.round(zoomScale * 100) + '%';
  }

  /* ---- Live Preview ---- */
  function renderPreview() {
    _syncHeader();
    const wrap = document.getElementById('previewPageWrap');
    if (!wrap) return;
    const s = paper.settings;
    const isEn = s.language === 'english';
    const fg = document.getElementById('sFontFamilyGroup');
    if (fg) fg.style.display = isEn ? 'none' : '';
    const efg = document.getElementById('sEnglishFontGroup');
    if (efg) efg.style.display = isEn ? '' : 'none';

    let html = '';
    if (s.layout === 'booklet') {
      const bookletPages = Render.getAutoPaginatedPages(paper);
      let P = bookletPages.length;
      let N = Math.max(4, Math.ceil(P / 4) * 4);
      while (bookletPages.length < N) {
        bookletPages.push([]);
      }

      let sheets = [];
      for (let sheetIdx = 1; sheetIdx <= N / 4; sheetIdx++) {
        // Front Sheet
        sheets.push({
          leftIdx: N - 2 * (sheetIdx - 1) - 1,
          rightIdx: 2 * (sheetIdx - 1),
        });
        // Back Sheet
        sheets.push({
          leftIdx: 2 * (sheetIdx - 1) + 1,
          rightIdx: N - 2 * (sheetIdx - 1) - 2,
        });
      }

      sheets.forEach((sheet, idx) => {
        const leftEls = bookletPages[sheet.leftIdx];
        const rightEls = bookletPages[sheet.rightIdx];
        const leftHeader = sheet.leftIdx === 0 ? paper.header : {};
        const rightHeader = sheet.rightIdx === 0 ? paper.header : {};
        const leftAns = sheet.leftIdx === N - 1;
        const rightAns = sheet.rightIdx === N - 1;

        const tempPaper = {
          settings: s,
          leftPane: { elements: leftEls, header: leftHeader, showAnswers: leftAns },
          rightPane: { elements: rightEls, header: rightHeader, showAnswers: rightAns }
        };

        const pageHtml = Render.buildPrintHtml(tempPaper, s.answerKey);
        const sideLabel = idx % 2 === 0 ? 'Front' : 'Back';
        const physicalSheetNum = Math.floor(idx / 2) + 1;

        html += `
          <div class="preview-page-container" style="margin-bottom:30px;position:relative;">
            <div class="preview-page-num" style="font-size:11px;color:var(--text-3);margin-bottom:6px;font-weight:600;">
              Sheet ${physicalSheetNum} (${sideLabel}) — Left: Page ${sheet.leftIdx+1}, Right: Page ${sheet.rightIdx+1}
            </div>
            <div class="preview-page-sheet" style="transform-origin:top left;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.15);box-sizing:border-box;">
              ${pageHtml}
            </div>
          </div>
        `;
      });
    } else {
      // buildPrintHtml now dynamically paginates the whole document by measured
      // content height; split the resulting <div class="print-page"> sheets
      // apart so each one gets its own preview wrapper + page-number label.
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = Render.buildPrintHtml(paper, s.answerKey);
      const pages = Array.from(tempDiv.children);

      pages.forEach((pageEl, idx) => {
        html += `
          <div class="preview-page-container" style="margin-bottom:30px;position:relative;">
            <div class="preview-page-num" style="font-size:11px;color:var(--text-3);margin-bottom:6px;font-weight:600;">Page ${idx+1} of ${pages.length}</div>
            <div class="preview-page-sheet" style="transform-origin:top left;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.15);box-sizing:border-box;">
              ${pageEl.outerHTML}
            </div>
          </div>
        `;
      });
    }
    wrap.innerHTML = html;
    applyZoom();
  }

  function openPrintPreview() {
    const wrap = document.getElementById('printPreviewWrap');
    if (!wrap) return;

    const s = paper.settings;
    // buildPrintHtml self-paginates dynamically for every layout now.
    wrap.innerHTML = Render.buildPrintHtml(paper, s.answerKey);
    document.getElementById('printModal').style.display = 'flex';
  }

  /* ---- Save ---- */
  async function save() {
    updateSaveStatus('saving');
    _syncHeader(); _syncSettings();
    const title = document.getElementById('paperTitle').value.trim() || 'Untitled Paper';
    const payload = { id: paperId, title, paper_json: paper };
    const r = await api('paper.php?action=save', JSON.stringify(payload), 'application/json');
    if (r.success) {
      paperId = r.id;
      document.getElementById('paperId').value = paperId;
      history.replaceState(null, '', '?id=' + paperId);
      updateSaveStatus('saved');
      showToast('Saved!', 'success');
    } else {
      updateSaveStatus('unsaved');
      showToast(r.error || 'Save failed', 'error');
    }
  }

  function scheduleSave() {
    updateSaveStatus('unsaved');
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(save, 3000);
  }

  function updateSaveStatus(state) {
    const el = document.getElementById('saveStatus');
    el.className = 'save-status ' + state;
    el.textContent = state === 'saved' ? '✓ Saved' : state === 'saving' ? 'Saving…' : 'Unsaved';
  }

  /* ---- Panel switching ---- */
  window.switchPanel = (panel, btn) => {
    document.querySelectorAll('.ptab').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('panelHeader').style.display   = panel==='header'   ? '' : 'none';
    document.getElementById('panelSettings').style.display = panel==='settings' ? '' : 'none';
  };

  /* ---- Helpers ---- */
  function v(id)       { const el=document.getElementById(id); return el ? el.value : ''; }
  function sv(id, val) { const el=document.getElementById(id); if (el && val!==undefined) el.value = val; }
  function esc(s)      { return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function api(endpoint, body, ct) {
    const opts = { method: body ? 'POST' : 'GET' };
    if (body) {
      opts.body = body;
      if (ct) {
        opts.headers = { 'Content-Type': ct };
      }
    }
    const r = await fetch('api/' + endpoint, opts);
    return r.json();
  }

  window.closeModal = id => { document.getElementById(id).style.display = 'none'; };
  window.showToast  = showToast;

  function addBookletDemo() {
    paper.header = {
      institution: "ঢাকা রেসিডেনসিয়াল মডেল কলেজ",
      exam: "অর্ধ-বার্ষিক পরীক্ষা - ২০২৬",
      class: "দশম শ্রেণী",
      subject: "বাংলা ও বাংলাদেশ শিক্ষা",
      subjectCode: "১০১",
      showSubjectCode: true,
      time: "২ ঘণ্টা ৩০ মিনিট",
      totalMarks: 19
    };
    _populateHeader();

    paper.settings.language = 'bangla';
    paper.settings.fontFamily = 'SutonnyOMJ';
    paper.settings.layout = 'booklet';
    _populateSettings();

    paper.elements = [
      // Page 1
      { type: 'section-header', text: 'ক-বিভাগ: বহুনির্বাচনী প্রশ্ন (MCQ)', alignment: 'center', marksInfo: '১ মান' },
      { type: 'mcq', number: 1, question: 'বাংলাদেশের জাতীয় কবির নাম কী?', marks: 1, options: ['রবীন্দ্রনাথ ঠাকুর', 'কাজী নজরুল ইসলাম', 'জীবনানন্দ দাশ', 'জসীমউদ্দীন'], optionLayout: '4x1', correctAnswer: 1, image: null },
      { type: 'page-break' },
      
      // Page 2
      { type: 'section-header', text: 'খ-বিভাগ: সংক্ষিপ্ত প্রশ্ন', alignment: 'center', marksInfo: '৫ মান' },
      { type: 'short', number: 2, question: 'বাংলাদেশের স্বাধীনতা যুদ্ধের পটভূমি সংক্ষেপে আলোচনা করো।', marks: 5, image: null },
      { type: 'page-break' },

      // Page 3
      { type: 'section-header', text: 'গ-বিভাগ: সৃজনশীল প্রশ্ন', alignment: 'center', marksInfo: '১০ মান' },
      {
        type: 'creative',
        number: 3,
        stimulus: 'উদ্দীপকঃ মজিদ তার এলাকার একজন প্রভাবশালী ব্যক্তি। সে গ্রামের নিরীহ মানুষদের অন্ধ বিশ্বাসের সুযোগ নিয়ে নিজের আখের গোছাতে ব্যস্ত থাকে। গ্রামের যুবক শফিক তার এই অপকর্মের প্রতিবাদ করতে চেষ্টা করে।',
        marks: 10,
        subQuestions: [
          { label: 'ক)', text: 'লালসালু উপন্যাসের রচয়িতা কে?', marks: 1 },
          { label: 'খ)', text: '\'মহব্বতনগর\' গ্রামের মানুষের মানসিক অবস্থা কেমন ছিল?', marks: 2 },
          { label: 'গ)', text: 'উদ্দীপকের মজিদের চরিত্রটির সাথে আপনার পঠিত কোনো চরিত্রের তুলনা করুন।', marks: 3 },
          { label: 'ঘ)', text: 'শফিকের প্রতিবাদ কি মহব্বতনগর গ্রামের সমাজ পরিবর্তনের ইঙ্গিত দেয়? মতামত দাও।', marks: 4 }
        ],
        image: null
      },
      { type: 'page-break' },

      // Page 4
      { type: 'section-header', text: 'ঘ-বিভাগ: শূন্যস্থান ও সত্য/মিথ্যা', alignment: 'center', marksInfo: '৩ মান' },
      { type: 'fill-blank', number: 4, template: 'বাংলাদেশের রাষ্ট্রীয় ভাষার নাম ___ এবং রাজধানী হলো ___।', marks: 2, answers: ['বাংলা', 'ঢাকা'] },
      { type: 'true-false', number: 5, statement: 'The Sundarbans is the largest mangrove forest in the world.', marks: 1, answer: true }
    ];

    renderList();
    renderPreview();
    scheduleSave();
    showToast('Booklet Demo Elements Loaded!', 'success');
  }

  function openSerialsModal() {
    const isEn = paper.settings.language === 'english';
    document.getElementById('fsPrimaryFormat').value = isEn ? '1' : 'bn_num';
    document.getElementById('fsPrimarySuffix').value = '.';
    document.getElementById('fsSubFormat').value = isEn ? 'a' : 'bn_alpha';
    document.getElementById('fsSubSuffix').value = ')';
    document.getElementById('serialsModal').style.display = 'flex';
  }

  function applySerialsFix() {
    const pFormat = v('fsPrimaryFormat');
    const pSuffix = v('fsPrimarySuffix') ?? '.';
    const sFormat = v('fsSubFormat');
    const sSuffix = v('fsSubSuffix') || '';

    // Helpers
    const toRoman = num => {
      const val = [1000,900,500,400,100,90,50,40,10,9,5,4,1];
      const sy = ["m","cm","d","cd","c","xc","l","xl","x","ix","v","iv","i"];
      let roman = "";
      for (let i = 0; i < val.length; i++) {
        while (num >= val[i]) { roman += sy[i]; num -= val[i]; }
      }
      return roman;
    };

    const toAlpha = (num, upper = false) => {
      let str = "";
      while (num > 0) {
        let m = (num - 1) % 26;
        str = String.fromCharCode((upper ? 65 : 97) + m) + str;
        num = Math.floor((num - 1) / 26);
      }
      return str;
    };

    const toBanglaDigits = num => {
      return String(num).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[+d]);
    };

    const toBanglaAlpha = num => {
      const bnLetters = [
        'ক', 'খ', 'গ', 'ঘ', 'ঙ',
        'চ', 'ছ', 'জ', 'ঝ', 'ঞ',
        'ট', 'ঠ', 'ড', 'ঢ', 'ণ',
        'ত', 'থ', 'দ', 'ধ', 'ন',
        'প', 'ফ', 'ব', 'ভ', 'ম',
        'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ'
      ];
      return bnLetters[(num - 1) % bnLetters.length] || 'ক';
    };

    const formatNum = (num, style) => {
      if (style === 'bn_num') return toBanglaDigits(num);
      if (style === 'A') return toAlpha(num, true);
      if (style === 'a') return toAlpha(num, false);
      if (style === 'bn_alpha') return toBanglaAlpha(num);
      if (style === 'roman') return toRoman(num);
      return String(num); // default '1'
    };

    const sectionWise = document.getElementById('fsSectionWise').checked;
    let qIdx = 1;
    const numberedTypes = ['mcq', 'short', 'creative', 'fill-blank', 'true-false'];
    
    paper.elements.forEach(el => {
      if (el.type === 'section-header' && sectionWise) {
        qIdx = 1;
      }
      if (numberedTypes.includes(el.type)) {
        el.number = formatNum(qIdx, pFormat);
        qIdx++;

        // Fix sub-questions if creative
        if (el.type === 'creative' && el.subQuestions) {
          el.subQuestions.forEach((sq, sqIdx) => {
            sq.label = formatNum(sqIdx + 1, sFormat) + sSuffix;
          });
        }
      }
    });

    paper.settings.primarySerialSuffix = pSuffix;
    closeModal('serialsModal');
    renderList();
    renderPreview();
    scheduleSave();
    showToast('Serial numbers formatted and updated!', 'success');
  }

  function openJSONModal() {
    const area = document.getElementById('jsonPaperText');
    if (area) {
      area.value = JSON.stringify(paper, null, 2);
    }
    document.getElementById('jsonModal').style.display = 'flex';
  }

  function copyPaperJSON() {
    const area = document.getElementById('jsonPaperText');
    if (area) {
      navigator.clipboard.writeText(area.value).then(() => {
        showToast('JSON data copied to clipboard!', 'success');
      });
    }
  }

  function loadPaperJSON() {
    const area = document.getElementById('jsonPaperText');
    if (!area) return;
    try {
      const parsed = JSON.parse(area.value);
      if (!parsed || typeof parsed !== 'object') {
        showToast('Invalid JSON structure. Must be a valid object.', 'error');
        return;
      }
      
      // Update global paper variable
      if (!Array.isArray(parsed.elements)) {
        parsed.elements = [];
      }
      if (!parsed.settings) {
        parsed.settings = {};
      }
      if (!parsed.header) {
        parsed.header = {};
      }
      
      Object.assign(paper, parsed);
      
      // Sync UI elements
      const h = paper.header || {};
      const s = paper.settings || {};
      
      if (document.getElementById('headerInst')) document.getElementById('headerInst').value = h.institution || '';
      if (document.getElementById('headerExam')) document.getElementById('headerExam').value = h.exam || '';
      if (document.getElementById('headerClass')) document.getElementById('headerClass').value = h.class || '';
      if (document.getElementById('headerSubject')) document.getElementById('headerSubject').value = h.subject || '';
      if (document.getElementById('headerSubjectCode')) document.getElementById('headerSubjectCode').value = h.subjectCode || '';
      if (document.getElementById('headerTime')) document.getElementById('headerTime').value = h.timeAllowed || '';
      if (document.getElementById('headerMarks')) document.getElementById('headerMarks').value = h.fullMarks || '';
      
      if (document.getElementById('paperTitle')) document.getElementById('paperTitle').value = paper.title || 'Loaded Paper';
      
      if (document.getElementById('setLayout')) document.getElementById('setLayout').value = s.layout || 'portrait';
      if (document.getElementById('setPaperSize')) document.getElementById('setPaperSize').value = s.paperSize || 'A4';
      if (document.getElementById('setFontSize')) document.getElementById('setFontSize').value = s.fontSize || 12;
      if (document.getElementById('setLineHeight')) document.getElementById('setLineHeight').value = s.lineHeight || 1.7;
      if (document.getElementById('setFontFamily')) document.getElementById('setFontFamily').value = s.fontFamily || 'SutonnyOMJ';
      if (document.getElementById('setLang')) document.getElementById('setLang').value = s.language || 'bangla';
      if (document.getElementById('toggleAnswers')) document.getElementById('toggleAnswers').checked = !!s.answerKey;
      if (document.getElementById('toggleBorder')) document.getElementById('toggleBorder').checked = !!s.border;
      
      const m = s.margins || {};
      if (document.getElementById('marginT')) document.getElementById('marginT').value = m.top !== undefined ? m.top : '';
      if (document.getElementById('marginB')) document.getElementById('marginB').value = m.bottom !== undefined ? m.bottom : '';
      if (document.getElementById('marginL')) document.getElementById('marginL').value = m.left !== undefined ? m.left : '';
      if (document.getElementById('marginR')) document.getElementById('marginR').value = m.right !== undefined ? m.right : '';

      renderList();
      renderPreview();
      scheduleSave();
      closeModal('jsonModal');
      showToast('Paper JSON loaded and applied successfully!', 'success');
    } catch (e) {
      showToast('JSON Parse Error: ' + e.message, 'error');
    }
  }

  document.addEventListener('DOMContentLoaded', init);

  return { openEditModal, closeEditModal, saveElement, addElement, deleteElement, moveEl, cloneElement,
           save, scheduleSave, applyFont, renderPreview, openPrintPreview,
           openAI, runAI, addAIQuestion, addAllAIQuestions, setProvider, getAIPrompt, copyAIPrompt, parseManual,
           toggleAIMode, onAIExistingSelectChange, getBulkPrompt,
           openShare, createShareLink, deleteLink, loadShareLinks,
           openQBank, loadQBankPicker, importFromBank, saveToBank, saveSingleToBank,
           openImgModal: openImgModal, uploadImg, confirmImg,
           showInsertMenu, hideInsertMenu,
           switchSidebarTab, toggleHeaderMenu, hideHeaderMenu, adjustZoom, setZoomMode, applyZoom,
           addBookletDemo, openSerialsModal, applySerialsFix, openJSONModal, copyPaperJSON, loadPaperJSON };
})();

/* ===== Render engine (shared with view.php) ===== */
const Render = (() => {

  function getAutoPaginatedPages(paper) {
    const s = paper.settings || {};
    const isEn = s.language === 'english';
    
    let unscaledWidth = 794;
    let unscaledHeight = 1123;
    if (s.paperSize === 'Legal') {
      unscaledWidth = 816;
      unscaledHeight = 1346;
    }
    const isLandscape = s.layout === 'landscape-half' || s.layout === 'booklet';
    if (isLandscape) {
      const tmp = unscaledWidth;
      unscaledWidth = unscaledHeight;
      unscaledHeight = tmp;
    }

    const m = s.margins || {};
    const defaultMargin = isLandscape ? 10 : 20;
    const paddingT = (m.top !== undefined ? m.top : defaultMargin) * 3.78;
    const paddingB = (m.bottom !== undefined ? m.bottom : defaultMargin) * 3.78;
    const paddingL = (m.left !== undefined ? m.left : defaultMargin) * 3.78;
    const paddingR = (m.right !== undefined ? m.right : defaultMargin) * 3.78;
    const maxHeight = unscaledHeight - paddingT - paddingB;

    const sandbox = document.createElement('div');
    sandbox.style.cssText = 'position:absolute;top:-9999px;left:-9999px;box-sizing:border-box;background:#fff;';
    sandbox.style.width = unscaledWidth + 'px';
    
    let colWidth = unscaledWidth;
    if (isLandscape) {
      colWidth = Math.floor(unscaledWidth / 2);
    }
    
    const colContainer = document.createElement('div');
    colContainer.style.cssText = `
      width: ${colWidth}px;
      box-sizing: border-box;
      padding-left: ${paddingL}px;
      padding-right: ${paddingR}px;
      font-size: ${s.fontSize || 12}pt;
      line-height: ${s.lineHeight || 1.7};
      font-family: ${isEn ? `'${s.englishFont || 'Times New Roman'}', serif` : `'${s.fontFamily || 'SutonnyOMJ'}', serif`};
    `;
    sandbox.appendChild(colContainer);
    document.body.appendChild(sandbox);

    let headerHtml = '';
    const h = paper.header || {};
    if (h.institution || h.exam || h.class || h.subject) {
      const headerFs = s.headerFontSize || (s.fontSize ? s.fontSize + 2 : 14);
      const headerLh = s.headerLineHeight || 1.3;
      headerHtml += `<div class="qp-header-wrap" style="font-size:${headerFs}pt;line-height:${headerLh};"><div class="qp-header">`;
      headerHtml += `<div class="qp-header-main">`;
      if (h.institution) headerHtml += `<div class="inst">${h.institution}</div>`;
      if (h.exam)        headerHtml += `<div class="exam">${h.exam}</div>`;
      if (h.class)       headerHtml += `<div class="class-name">${h.class}</div>`;
      headerHtml += `</div></div></div>`;
    }
    colContainer.innerHTML = headerHtml;
    const headerHeight = colContainer.offsetHeight;

    let bookletPages = [[]];
    let currentPageHeight = headerHeight;
    let prevMarginBottom = 0;
    
    for (let el of paper.elements) {
      if (el.type === 'page-break') {
        bookletPages.push([]);
        currentPageHeight = 0;
        prevMarginBottom = 0;
        continue;
      }

      const elDiv = document.createElement('div');
      const tempPaper = { settings: { ...s, layout: 'portrait' }, elements: [el], _isChunk: true };
      elDiv.innerHTML = buildPrintHtml(tempPaper, false);
      
      const qBlock = elDiv.querySelector('.qp-q, .qp-section-header, .qp-instruction, .qp-creative, .qp-text-block, .qp-table-wrap, .qp-img');
      if (qBlock) {
        colContainer.appendChild(qBlock);
        
        const style = window.getComputedStyle(qBlock);
        const marginTop = parseFloat(style.marginTop) || 0;
        const marginBottom = parseFloat(style.marginBottom) || 0;
        
        const collapsedMargin = bookletPages[bookletPages.length - 1].length === 0 
          ? marginTop 
          : Math.max(prevMarginBottom, marginTop);
          
        const elHeight = qBlock.offsetHeight + collapsedMargin;
        
        if (currentPageHeight + elHeight > maxHeight && bookletPages[bookletPages.length - 1].length > 0) {
          bookletPages.push([el]);
          currentPageHeight = qBlock.offsetHeight + marginTop;
          prevMarginBottom = marginBottom;
        } else {
          bookletPages[bookletPages.length - 1].push(el);
          currentPageHeight += elHeight;
          prevMarginBottom = marginBottom;
        }
      } else {
        bookletPages[bookletPages.length - 1].push(el);
      }
    }

    sandbox.remove();
    return bookletPages;
  }

  function buildPrintHtml(paper, showAnswers) {
    const s      = paper.settings || {};
    const h      = paper.header  || {};
    const els    = paper.elements || [];
    const layout = s.layout || 'portrait';

    /* ---- Dynamic pagination: split a full, un-chunked document into pages
       purely by measured content height. Skipped when the caller already
       handed us a single pre-chunked page/sheet (_isChunk, or leftPane/rightPane
       from the booklet imposition branch below). ---- */
    if (!paper._isChunk && !paper.leftPane && !paper.rightPane && layout !== 'booklet') {
      const chunks = getAutoPaginatedPages(paper);
      let html = '';
      if (layout === 'landscape-half' && !s.autoDuplicate) {
        const lastRealIdx = chunks.length - 1;
        if (chunks.length % 2 !== 0) chunks.push([]);
        for (let i = 0; i < chunks.length; i += 2) {
          const tempPaper = {
            settings: s,
            header: i === 0 ? h : {},
            leftPane:  { elements: chunks[i],   header: i === 0 ? h : {}, showAnswers: showAnswers && i === lastRealIdx },
            rightPane: { elements: chunks[i+1], header: {},               showAnswers: showAnswers && (i+1) === lastRealIdx },
            _isChunk: true,
          };
          html += buildPrintHtml(tempPaper, showAnswers);
        }
      } else {
        chunks.forEach((chunkEls, idx) => {
          const tempPaper = { settings: s, header: idx === 0 ? h : {}, elements: chunkEls, _isChunk: true };
          html += buildPrintHtml(tempPaper, showAnswers && idx === chunks.length - 1);
        });
      }
      return html;
    }

    const font = s.fontFamily || 'SutonnyOMJ';
    const isEn = s.language === 'english';
    const bn = n => {
      if (isEn) return String(n);
      return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[+d]);
    };

    const esc = v => String(v||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const txth = raw => (!raw && raw !== 0) ? '' : esc(String(raw));

    const translateSubLabel = (lbl, index) => {
      if (!isEn) return txth(lbl);
      const enChars = ['a', 'b', 'c', 'd'];
      const cleanLbl = String(lbl || '');
      let replaced = cleanLbl
        .replace(/ক/g, 'a').replace(/খ/g, 'b')
        .replace(/গ/g, 'c').replace(/ঘ/g, 'd')
        .replace(/A/gi, 'a').replace(/B/gi, 'b')
        .replace(/C/gi, 'c').replace(/D/gi, 'd');
      if (!/[a-d]/i.test(replaced) && index < 4) {
        replaced = enChars[index] + (cleanLbl.includes(')') ? ')' : cleanLbl.includes('.') ? '.' : ')');
      }
      return txth(replaced);
    };

    const elStyle = (el, extraStyle = '') => {
      let styles = [];
      let defaultFs = (el.type === 'section-header' || el.type === 'instruction') ? null : s.questionFontSize;
      let defaultLh = (el.type === 'section-header' || el.type === 'instruction') ? null : s.questionLineHeight;

      if (el.type === 'table') {
        if (s.tableFontSize) defaultFs = s.tableFontSize;
        if (s.tableLineHeight) defaultLh = s.tableLineHeight;
      }

      const fs = el.fontSize || defaultFs || s.fontSize;
      const lh = el.lineHeight || defaultLh || s.lineHeight;

      if (fs) styles.push(`font-size:${fs}pt;`);
      if (lh) styles.push(`line-height:${lh};`);
      if (el.letterSpacing !== undefined && el.letterSpacing !== null) styles.push(`letter-spacing:${el.letterSpacing}px;`);
      if (extraStyle) styles.push(extraStyle);
      return styles.length ? ` style="${styles.join('')}"` : '';
    };

    const m = s.margins || {};
    const fontStack = isEn
      ? `'${s.englishFont || 'Times New Roman'}', serif`
      : `'${font}', serif`;

    const buildPaneContent = (paneEls, paneHeader, includeAnsKey) => {
      let html = '';
      if (paneHeader.institution || paneHeader.exam || paneHeader.class || paneHeader.subject) {
        const headerFs = s.headerFontSize || (s.fontSize ? s.fontSize + 2 : 14);
        const headerLh = s.headerLineHeight || 1.3;
        const headerStyle = ` style="font-size:${headerFs}pt;line-height:${headerLh};"`;
        html += `<div class="qp-header-wrap"${headerStyle}><div class="qp-header">`;
        html += `<div class="qp-header-main">`;
        if (paneHeader.institution) html += `<div class="inst">${txth(paneHeader.institution)}</div>`;
        if (paneHeader.exam)        html += `<div class="exam">${txth(paneHeader.exam)}</div>`;
        if (paneHeader.class)       html += `<div class="class-name">${txth(paneHeader.class)}</div>`;
        html += `</div>`;
        if (paneHeader.showSubjectCode && paneHeader.subjectCode) {
          const digits = [...String(paneHeader.subjectCode)].map(d => `<span class="sc-digit">${txth(d)}</span>`).join('');
          const scLabel = isEn ? 'Subject Code' : 'বিষয় কোডঃ';
          html += `<div class="qp-subj-code-box">
            <span class="sc-label">${txth(scLabel)}</span>
            <div class="sc-digits">${digits}</div>
          </div>`;
        }
        html += `</div></div>`;

        html += `<div class="qp-meta-bar">`;
        if (paneHeader.subject) {
          html += `<div class="meta-subj-row"><span class="meta-subj">${txth(paneHeader.subject)}</span></div>`;
        }
        if (paneHeader.time || paneHeader.totalMarks) {
          html += `<div class="meta-details-row">`;
          html += `<span class="meta-time">${paneHeader.time ? (isEn ? 'Time: ' : txth('সময়ঃ ')) + txth(paneHeader.time) : ''}</span>`;
          html += `<span class="meta-marks">${paneHeader.totalMarks ? (isEn ? 'Full Marks: ' : txth('পূর্ণমানঃ ')) + bn(paneHeader.totalMarks) : ''}</span>`;
          html += `</div>`;
        }
        html += `</div>`;
      }

      const mcqCols      = parseInt(s.mcqCols) || 1;
      const mcqWrapOpen  = mcqCols === 2 ? '<div class="mcq-two-col">' : '';
      const mcqWrapClose = mcqCols === 2 ? '</div>' : '';
      let inMcqBlock = false;
      const labels = isEn ? ['A', 'B', 'C', 'D'] : ['ক', 'খ', 'গ', 'ঘ'];

      const qSuffix = txth(s.primarySerialSuffix ?? '.');
      for (const el of paneEls) {
        if (inMcqBlock && el.type !== 'mcq') {
          if (mcqCols === 2) { html += mcqWrapClose; inMcqBlock = false; }
        }

        switch (el.type) {
          case 'section-header': {
            const secFs = el.fontSize || s.sectionFontSize || (s.fontSize ? s.fontSize + 1 : 13);
            const secLh = el.lineHeight || s.sectionLineHeight || 1.4;
            const extraStyle = `font-size:${secFs}pt;line-height:${secLh};${el.alignment && el.alignment!=='center' ? 'justify-content:'+el.alignment+';' : ''}`;
            html += `<div class="qp-section-header"${elStyle(el, extraStyle)}>
              <span class="sec-text">${txth(el.text)}</span>
              ${el.marksInfo ? `<span class="sec-marks">${txth(el.marksInfo)}</span>` : ''}
            </div>`;
            break;
          }
          case 'instruction': {
            const instFs = el.fontSize || s.instructionFontSize || (s.fontSize ? s.fontSize - 1 : 11);
            const instLh = el.lineHeight || s.instructionLineHeight || 1.5;
            const extraStyle = `font-size:${instFs}pt;line-height:${instLh};text-align:${el.alignment||'left'};`;
            html += `<div class="qp-instruction"${elStyle(el, extraStyle)}>${txth(el.text)}</div>`;
            break;
          }
          case 'mcq': {
            if (mcqCols === 2 && !inMcqBlock) { html += mcqWrapOpen; inMcqBlock = true; }
            const optLayout = el.optionLayout || '4x1';
            let optClass  = optLayout === '4x1' ? 'layout-4x1' : optLayout === '2x2' ? 'layout-2x2' : 'layout-1x4';
            if (mcqCols === 2 && optLayout === '4x1') optClass = 'layout-2x2';
            html += `<div class="qp-q qp-mcq"${elStyle(el)}>
              <span class="qp-q-num">${bn(el.number)}${qSuffix}</span>
              ${txth(el.question)}
              ${el.image ? `<div class="qp-img align-center"><img src="${esc(el.image)}" style="max-width:100%;max-height:150px;"></div>` : ''}
              <div class="qp-options ${optClass}">
                ${(el.options||[]).map((opt, oi) => {
                  const isCorrect = showAnswers && el.correctAnswer === oi;
                  const bubbleStyle = isEn ? ` style="font-family:'${s.englishFont || 'Times New Roman'}', serif;"` : '';
                  return `<div class="opt${isCorrect ? ' correct' : ''}"><span class="opt-bubble"${bubbleStyle}>${txth(labels[oi])}</span>${txth(opt)}</div>`;
                }).join('')}
              </div>
            </div>`;
            break;
          }
          case 'short':
            html += `<div class="qp-q qp-short"${elStyle(el)}>
              <span class="qp-marks">${bn(el.marks)}</span>
              <span class="qp-q-num">${bn(el.number)}${qSuffix}</span>
              ${txth(el.question)}
              ${el.image ? `<div class="qp-img align-center"><img src="${esc(el.image)}" style="max-width:100%;margin-top:6pt;"></div>` : ''}
            </div>`;
            break;
          case 'creative': {
            let tblHtml = '';
            if (el.stimulusTable && el.stimulusTable.rows && el.stimulusTable.rows.length) {
              let stimTblStyle = '';
              if (s.tableFontSize) stimTblStyle += `font-size:${s.tableFontSize}pt;`;
              if (s.tableLineHeight) stimTblStyle += `line-height:${s.tableLineHeight};`;
              const stimTblStyleAttr = stimTblStyle ? ` style="${stimTblStyle}"` : '';
              tblHtml = `<table class="stimulus-table"${stimTblStyleAttr}>`;
              if (el.stimulusTable.headers && el.stimulusTable.headers.some(hd => hd)) {
                tblHtml += `<tr>${el.stimulusTable.headers.map(hd => `<th>${txth(hd)}</th>`).join('')}</tr>`;
              }
              tblHtml += el.stimulusTable.rows.map(row =>
                `<tr>${row.map(cell => `<td>${txth(cell)}</td>`).join('')}</tr>`
              ).join('');
              tblHtml += `</table>`;
            }
            html += `<div class="qp-creative"${elStyle(el)}>
              <div class="stimulus-wrap">
                <span class="qp-q-num">${bn(el.number)}${qSuffix}</span>
                ${el.stimulus ? ' ' + txth(el.stimulus) : ''}
                ${tblHtml}
                ${el.image ? `<div class="qp-img align-center"><img src="${esc(el.image)}" style="max-width:80%;${el.zBehind?'opacity:.25;':''}"></div>` : ''}
              </div>
              <div class="sub-qs">
                ${(el.subQuestions||[]).map((sq, sqIdx) => `<div class="sub-q">
                  <span class="sub-label">${translateSubLabel(sq.label, sqIdx)}</span>
                  <span class="sub-q-body">${txth(sq.text)}</span>
                  <span class="sub-marks">${bn(sq.marks)}</span>
                </div>`).join('')}
              </div>
            </div>`;
            break;
          }
          case 'fill-blank': {
            const parts   = (el.template||'').split('___');
            const answers = el.answers || [];
            let rendered  = '';
            parts.forEach((part, pi) => {
              rendered += txth(part);
              if (pi < parts.length - 1) {
                rendered += answers[pi] && showAnswers
                  ? `<span class="qp-blank filled">${txth(answers[pi])}</span>`
                  : `<span class="qp-blank"></span>`;
              }
            });
            html += `<div class="qp-q qp-tf"${elStyle(el)}>
              <span class="qp-marks">${bn(el.marks)}</span>
              <span class="qp-q-num">${bn(el.number)}${qSuffix}</span>
              ${rendered}
            </div>`;
            break;
          }
          case 'true-false':
            html += `<div class="qp-q qp-tf"${elStyle(el)}>
              <span class="qp-marks">${bn(el.marks)}</span>
              <span class="qp-q-num">${bn(el.number)}${qSuffix}</span>
              ${txth(el.statement)}
              ${showAnswers ? `<span class="tf-ans ${el.answer ? 'true' : 'false'}">${isEn ? (el.answer ? 'True' : 'False') : txth(el.answer ? 'সত্য' : 'মিথ্যা')}</span>` : ''}
            </div>`;
            break;
          case 'image': {
            const align = el.align || 'center';
            const posStyle = el.freeMove ? `position:absolute;top:${el.top||0}%;left:${el.left||0}%;` : '';
            html += `<div class="qp-img align-${align}${el.freeMove ? ' freeMove' : ''}${el.zBehind ? ' behind' : ''}"
                style="${posStyle}width:${el.width||100}%;">
              <img src="${esc(el.url)}" style="max-width:100%;${el.zBehind ? 'opacity:.25;' : ''}">
              ${el.caption ? `<div class="caption">${txth(el.caption)}</div>` : ''}
            </div>`;
            break;
          }
          case 'page-break':
            html += `<div class="qp-page-break"></div>`;
            break;
          case 'text-block': {
            html += `<div class="qp-text-block"${elStyle(el, `text-align:${el.alignment||'left'};`)}>${txth(el.text||'').replace(/\n/g,'<br>')}</div>`;
            break;
          }
          case 'table': {
            const borderClass = el.borderStyle === 'none' ? ' no-border' : el.borderStyle === 'outer' ? ' outer-border' : '';
            let tHtml = `<div class="qp-table-wrap"${elStyle(el)}><table class="standalone-table${borderClass}">`;
            if (el.headers && el.headers.some(hd => hd)) {
              tHtml += `<tr>${(el.headers||[]).map(hd => `<th>${txth(hd)}</th>`).join('')}</tr>`;
            }
            tHtml += (el.rows||[]).map(row =>
              `<tr>${(row||[]).map(cell => `<td>${txth(cell)}</td>`).join('')}</tr>`
            ).join('');
            tHtml += `</table>`;
            if (el.caption) tHtml += `<div class="table-caption">${txth(el.caption)}</div>`;
            tHtml += `</div>`;
            html += tHtml;
            break;
          }
        }
      }

      if (inMcqBlock && mcqCols === 2) html += mcqWrapClose;

      if (includeAnsKey && showAnswers) {
        const mcqs = paneEls.filter(e => e.type === 'mcq');
        if (mcqs.length) {
          const akTitle = isEn ? 'Answer Key' : `${txth('উত্তরমালা')} (Answer Key)`;
          const thNo = isEn ? 'Q. No.' : txth('নং');
          const thAns = isEn ? 'Answer' : txth('উত্তর');
          
          let akStyle = '';
          if (s.summaryFontSize) akStyle += `font-size:${s.summaryFontSize}pt;`;
          if (s.summaryLineHeight) akStyle += `line-height:${s.summaryLineHeight};`;
          const akStyleAttr = akStyle ? ` style="${akStyle}"` : '';

          html += `<div class="answer-key-section"${akStyleAttr}><h3>${akTitle}</h3>
            <table class="ans-table"><tr><th>${thNo}</th><th>${thAns}</th></tr>
            ${mcqs.map(q => `<tr><td>${bn(q.number)}</td><td>${txth(labels[q.correctAnswer||0])}</td></tr>`).join('')}
            </table></div>`;
        }
      }

      return html;
    };

    const isLegal = s.paperSize === 'Legal';
    const isBorder = s.border;

    let outerClass = 'print-page';
    if (isLegal) outerClass += ' legal';
    if (isBorder) outerClass += ' border';
    if (layout === 'landscape-half') outerClass += ' half-landscape';
    if (layout === 'booklet') outerClass += ' booklet';

    let panePadding = '';
    let pageStyles = `font-family:${fontStack};font-size:${s.fontSize||12}pt;line-height:${s.lineHeight||1.7};`;
    
    if (layout === 'landscape-half' || layout === 'booklet') {
      pageStyles += 'padding:0;';
      panePadding = ` style="padding:${m.top||10}mm ${m.right||10}mm ${m.bottom||10}mm ${m.left||10}mm;"`;
    } else {
      pageStyles += `padding:${m.top||20}mm ${m.right||20}mm ${m.bottom||20}mm ${m.left||20}mm;`;
    }
    
    if (layout === 'booklet' && !paper.leftPane) {
      // Automatic full-paper booklet imposition
      let bookletPages = getAutoPaginatedPages(paper);

      let P = bookletPages.length;
      let N = Math.max(4, Math.ceil(P / 4) * 4);
      while (bookletPages.length < N) {
        bookletPages.push([]);
      }

      let sheetsHtml = '';
      for (let sheetIdx = 1; sheetIdx <= N / 4; sheetIdx++) {
        // Front Sheet
        const leftPageIdxFront = N - 2 * (sheetIdx - 1) - 1;
        const rightPageIdxFront = 2 * (sheetIdx - 1);
        
        const leftHeaderF = leftPageIdxFront === 0 ? h : {};
        const rightHeaderF = rightPageIdxFront === 0 ? h : {};
        const leftAnsF = leftPageIdxFront === N - 1;
        const rightAnsF = rightPageIdxFront === N - 1;

        const leftContentF = buildPaneContent(bookletPages[leftPageIdxFront], leftHeaderF, showAnswers && leftAnsF);
        const rightContentF = buildPaneContent(bookletPages[rightPageIdxFront], rightHeaderF, showAnswers && rightAnsF);

        sheetsHtml += `<div class="${outerClass}" style="${pageStyles}">`;
        sheetsHtml += `<div class="half-pane"${panePadding}>${leftContentF}</div>`;
        sheetsHtml += `<div class="half-pane"${panePadding}>${rightContentF}</div>`;
        sheetsHtml += `</div>`;

        // Back Sheet
        const leftPageIdxBack = 2 * (sheetIdx - 1) + 1;
        const rightPageIdxBack = N - 2 * (sheetIdx - 1) - 2;

        const leftHeaderB = leftPageIdxBack === 0 ? h : {};
        const rightHeaderB = rightPageIdxBack === 0 ? h : {};
        const leftAnsB = leftPageIdxBack === N - 1;
        const rightAnsB = rightPageIdxBack === N - 1;

        const leftContentB = buildPaneContent(bookletPages[leftPageIdxBack], leftHeaderB, showAnswers && leftAnsB);
        const rightContentB = buildPaneContent(bookletPages[rightPageIdxBack], rightHeaderB, showAnswers && rightAnsB);

        sheetsHtml += `<div class="${outerClass}" style="${pageStyles}">`;
        sheetsHtml += `<div class="half-pane"${panePadding}>${leftContentB}</div>`;
        sheetsHtml += `<div class="half-pane"${panePadding}>${rightContentB}</div>`;
        sheetsHtml += `</div>`;
      }
      return sheetsHtml;
    }

    // Individual pane rendering (e.g. single sheet or landscape-half)
    let outerHtml = `<div class="${outerClass}" style="${pageStyles}">`;

    if (layout === 'landscape-half' || layout === 'booklet') {
      if (paper.leftPane && paper.rightPane) {
        const leftContent = buildPaneContent(paper.leftPane.elements, paper.leftPane.header, showAnswers && paper.leftPane.showAnswers);
        const rightContent = buildPaneContent(paper.rightPane.elements, paper.rightPane.header, showAnswers && paper.rightPane.showAnswers);
        outerHtml += `<div class="half-pane"${panePadding}>${leftContent}</div>`;
        outerHtml += `<div class="half-pane"${panePadding}>${rightContent}</div>`;
      } else if (s.autoDuplicate && layout === 'landscape-half') {
        const paneContent = buildPaneContent(els, h, showAnswers);
        outerHtml += `<div class="half-pane"${panePadding}>${paneContent}</div>`;
        outerHtml += `<div class="half-pane"${panePadding}>${paneContent}</div>`;
      }
    } else {
      outerHtml += buildPaneContent(els, h, showAnswers);
    }

    outerHtml += '</div>';
    return outerHtml;
  }

  return { buildPrintHtml, getAutoPaginatedPages };
})();

/* ===== Toast system ===== */
function showToast(msg, type='info') {
  let c = document.getElementById('toastContainer');
  if (!c) { c = Object.assign(document.createElement('div'),{id:'toastContainer'}); document.body.appendChild(c); }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = (type==='success'?'✓ ':type==='error'?'✕ ':'ℹ ') + msg;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 2800);
}
