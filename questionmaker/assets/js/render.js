/* ===== Render engine stub for view.php ===== */
// view.php includes bangla.js + this file; editor.js is NOT loaded there.
// Keep this in sync with the Render IIFE in editor.js.

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
