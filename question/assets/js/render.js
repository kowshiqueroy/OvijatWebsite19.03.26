// Shared rendering engine: turns paper meta + elements into the printable
// question-paper HTML. Used by both the editor's live preview and view.php.

function escapeAttr(s) {
    return String(s ?? '').replace(/"/g, '&quot;');
}

// Fixed UI labels (not user-entered content) localized by paper language.
const UI_LABELS = {
    en: { setCode: 'Set Code:', subjectCode: 'Subject Code:', time: 'Time:', fullMarks: 'Full Marks:',
        answerAny: (n) => `(Answer any ${n})` },
    bn: { setCode: 'সেট কোড:', subjectCode: 'বিষয় কোড:', time: 'সময়:', fullMarks: 'পূর্ণমান:',
        answerAny: (n) => `(যেকোনো ${n}টি উত্তর দাও)` },
};
function labelsFor(lang) { return UI_LABELS[lang] || UI_LABELS.en; }

function renderHeader(paper) {
    const t = labelsFor(paper.language);
    const setCodeHtml = paper.show_set_code && paper.set_code
        ? `<div class="hdr-setcode">${t.setCode} <span class="code-chip">${escapeAttr(paper.set_code)}</span></div>` : '';
    const subjectCodeHtml = paper.show_subject_code && paper.subject_code
        ? `<div class="hdr-subjectcode">${t.subjectCode} ${
            paper.subject_code.split('').map(d => `<span class="code-chip">${escapeAttr(d)}</span>`).join('')
          }</div>` : '';

    return `
    <div class="paper-header">
        <div class="hdr-row hdr-school">${escapeAttr(paper.school_name)}</div>
        <div class="hdr-row hdr-exam">${escapeAttr(paper.exam_name)}</div>
        <div class="hdr-row hdr-classline">
            ${setCodeHtml}
            <span class="hdr-class">${escapeAttr(paper.class_name)}</span>
            ${subjectCodeHtml}
        </div>
        <div class="hdr-row hdr-subject">${escapeAttr(paper.subject_name)}</div>
        <div class="hdr-row hdr-timemarks">
            <span class="hdr-time">${t.time} ${escapeAttr(paper.time_text)}</span>
            <span class="hdr-marks">${t.fullMarks} ${escapeAttr(paper.full_marks)}</span>
        </div>
    </div>`;
}

function bubbleLabel(style, i, lang) {
    if (style === 'bangla') return BN_LETTERS[i] || String(i + 1);
    if (style === 'paren') return `(${String.fromCharCode(97 + i)})`;
    return String.fromCharCode(97 + i); // a, b, c, d
}

function renderMarks(marks, show) {
    if (!show || marks === '' || marks == null) return '';
    return `<span class="q-marks">${escapeAttr(marks)}</span>`;
}

function renderElement(el, ctx) {
    const lang = ctx.paper.language;

    switch (el.type) {
        case 'instruction': {
            const cls = ['el-instruction', el.align === 'left' ? 'align-left' : 'align-center',
                el.boxed ? 'boxed' : '', el.italic ? 'italic' : ''].filter(Boolean).join(' ');
            const style = el.fontSize ? `font-size:${el.fontSize}px` : '';
            return `<div class="${cls}" style="${style}">${el.text || ''}</div>`;
        }

        case 'section': {
            ctx.qNum = 0; // renumber questions per section
            const cls = ['el-section', el.bold !== false ? 'bold' : '', el.align === 'left' ? 'align-left' : 'align-center'].filter(Boolean).join(' ');
            let marksBadge = '';
            if (el.showMarks && el.marksText) marksBadge = `<span class="section-marks">${escapeAttr(el.marksText)}</span>`;
            let countBadge = '';
            if (el.showQuestionCount && el.questionCount) {
                countBadge = `<span class="section-count">${labelsFor(lang).answerAny(escapeAttr(el.questionCount))}</span>`;
            }
            return `<div class="${cls}"><span class="section-text">${el.text || ''}</span>${countBadge}${marksBadge}</div>`;
        }

        case 'question': {
            // A continuation fragment (the tail half of a question split across a
            // page/column boundary by pagination) keeps showing on the next page
            // but must not consume a fresh question number or restart sub-question
            // lettering — it's the same question, not a new one.
            const numHtml = el.__continued
                ? ''
                : (() => { ctx.qNum++; return `<span class="q-num">${toNumber(ctx.qNum, lang)}.</span>`; })();
            const alignCls = el.align ? `align-${el.align}` : 'align-justify';
            const subOffset = el.__subQOffset || 0;
            const subHtml = (el.subQuestions || []).map((sq, i) => `
                <div class="sub-question">
                    <span class="sub-label">${toLetter(subOffset + i + 1, lang)}.</span>
                    <span class="sub-text">${sq.text || ''}</span>
                    ${renderMarks(sq.marks, true)}
                </div>`).join('');
            return `
            <div class="el-question ${alignCls}">
                <div class="q-main">
                    ${numHtml}
                    <span class="q-text">${el.text || ''}</span>
                    ${renderMarks(el.marks, el.showMarks !== false)}
                </div>
                ${subHtml}
            </div>`;
        }

        case 'mcq': {
            ctx.qNum++;
            const num = toNumber(ctx.qNum, lang);
            const layoutCls = el.layout === '2perline' ? 'mcq-2col' : el.layout === '1perline' ? 'mcq-1col' : 'mcq-4col';
            const opts = (el.options || ['', '', '', '']).map((o, i) =>
                `<div class="mcq-option"><span class="mcq-bubble">${bubbleLabel(el.bubbleStyle, i, lang)}.</span> <span>${o}</span></div>`
            ).join('');
            return `
            <div class="el-mcq">
                <div class="q-main">
                    <span class="q-num">${num}.</span>
                    <span class="q-text">${el.text || ''}</span>
                    ${renderMarks(el.marks, el.showMarks !== false)}
                </div>
                <div class="mcq-options ${layoutCls}">${opts}</div>
            </div>`;
        }

        case 'passage': {
            const alignCls = el.align ? `align-${el.align}` : 'align-justify';
            const imgHtml = el.imageUrl ? `<img src="${escapeAttr(el.imageUrl)}" class="passage-img align-${el.imgAlign || 'center'}">` : '';
            return `<div class="el-passage ${alignCls}">${imgHtml}<div class="passage-text">${el.text || ''}</div></div>`;
        }

        case 'image': {
            const wrapCls = el.wrap === 'behind' ? 'img-behind' : 'img-with';
            const heightLines = el.heightLines || 2;
            return `<div class="el-image align-${el.align || 'center'} ${wrapCls}" style="--img-lines:${heightLines}">
                        <img src="${escapeAttr(el.src)}">
                    </div>`;
        }

        case 'fillblank': {
            ctx.qNum++;
            const num = toNumber(ctx.qNum, lang);
            return `
            <div class="el-fillblank">
                <div class="q-main">
                    <span class="q-num">${num}.</span>
                    <span class="q-text">${el.text || ''}</span>
                    ${renderMarks(el.marks, el.showMarks !== false)}
                </div>
            </div>`;
        }

        case 'pagebreak':
            return `<div class="el-pagebreak" data-break="1"></div>`;

        default:
            return '';
    }
}

// Renders a chunk of elements using an existing running context (so question
// numbering continues correctly across page/column boundaries instead of
// resetting on every chunk).
function renderElementsWithCtx(elements, ctx) {
    return (elements || []).map(el => renderElement(el, ctx)).join('\n');
}

function renderPaperHtml(paper, elements, includeHeader, ctx) {
    const header = includeHeader ? renderHeader(paper) : '';
    return `<div class="paper-page-inner">${header}<div class="paper-body">${renderElementsWithCtx(elements, ctx)}</div></div>`;
}

function fontFamilyFor(paper) {
    return `"${paper.primary_font}", "${paper.secondary_font}", sans-serif`;
}

const PAGE_SIZES_MM = { A4: { w: 210, h: 297 }, Legal: { w: 216, h: 356 } };
const MM_TO_PX = 96 / 25.4;

function pageDimsMm(pageSize, landscape) {
    const base = PAGE_SIZES_MM[pageSize] || PAGE_SIZES_MM.A4;
    return landscape ? { w: base.h, h: base.w } : { w: base.w, h: base.h };
}

// Shared hidden measuring surface used by everything below. Requires a live
// DOM (browser) — not usable from a bare Node context.
function getMeasureMount(contentWidthPx, cssVars) {
    let mount = document.getElementById('__paginationMeasure__');
    if (!mount) {
        mount = document.createElement('div');
        mount.id = '__paginationMeasure__';
        mount.style.position = 'absolute';
        mount.style.visibility = 'hidden';
        mount.style.pointerEvents = 'none';
        mount.style.top = '-99999px';
        mount.style.left = '-99999px';
        mount.style.boxSizing = 'border-box';
        document.body.appendChild(mount);
    }
    mount.className = 'paper-page-inner';
    mount.style.width = contentWidthPx + 'px';
    Object.entries(cssVars).forEach(([k, v]) => mount.style.setProperty(k, v));
    // font-size/line-height are applied as real CSS properties on .paper-page
    // itself (not .paper-page-inner), driven by these same custom properties —
    // but this mount only ever carries the .paper-page-inner class, so setting
    // the custom properties alone does nothing here; without this, the mount
    // measures at the browser's default font (16px/normal), predicting far
    // more line-wrapping — and thus taller heights — than the real page ever
    // renders, which is exactly what caused pages to look "under-filled".
    mount.style.fontSize = cssVars['--base-font-size'] || '13px';
    mount.style.lineHeight = String(cssVars['--line-height'] ?? 1.4);
    return mount;
}

// Measures the header block's rendered height, so the first page/column that
// carries it (and only that one) can have its available content height
// reduced accordingly — otherwise the header silently eats into space the
// pagination algorithm thought was free, overflowing page 1.
function measureHeaderHeight(paper, contentWidthPx, cssVars) {
    const mount = getMeasureMount(contentWidthPx, cssVars);
    mount.innerHTML = renderHeader(paper);
    return mount.offsetHeight;
}

// A block of text is only safe to cut at an arbitrary word/line boundary if it
// has no markup beyond <br> — splitting mid-tag would corrupt a table/image/etc.
function canSafelySplitHtml(html) {
    return typeof html === 'string' && html.length > 0 && !/<(?!br\s*\/?>)[a-zA-Z]/.test(html);
}

// Binary-searches the largest prefix of tokens (words + <br> markers) of `html`
// that renders within availableHeightPx via wrapFn(text). Returns null if not
// even a single token fits, or if there's nothing meaningful left to carry over.
function splitHtmlToFit(mount, wrapFn, html, availableHeightPx) {
    if (!canSafelySplitHtml(html)) return null;
    const tokens = html.split(/(<br\s*\/?>)/i).flatMap(part =>
        /^<br/i.test(part) ? [part] : part.split(/(\s+)/).filter(Boolean)
    );
    if (tokens.length <= 1) return null;

    function heightFor(n) {
        mount.innerHTML = wrapFn(tokens.slice(0, n).join(''));
        return mount.firstElementChild.offsetHeight;
    }
    if (heightFor(1) > availableHeightPx) return null;

    let lo = 1, hi = tokens.length - 1;
    while (lo < hi) {
        const mid = Math.ceil((lo + hi) / 2);
        if (heightFor(mid) <= availableHeightPx) lo = mid; else hi = mid - 1;
    }
    const fitHtml = tokens.slice(0, lo).join('');
    const restHtml = tokens.slice(lo).join('').replace(/^\s+/, '');
    if (!restHtml || !restHtml.replace(/<br\s*\/?>/gi, '').trim()) return null;
    return { fitHtml, restHtml };
}

// Element types whose main "text" field is safe to cut mid-content when it
// doesn't fit — plain questions, passages and instructions are usually free-
// flowing prose. MCQ/section/image stay atomic: splitting 4 options or a
// section title across a page break would look broken, not helpful.
const SPLITTABLE_TEXT_TYPES = ['question', 'passage', 'instruction'];

// Tries to split one element across the current page/column boundary instead
// of moving it whole to the next one (which is what leaves blank space at the
// bottom of the current page). Returns { fitEl, restEl } to place on the
// current and next page respectively, or null if it can't usefully split.
function trySplitElement(paper, el, availableHeightPx, mount) {
    const dummyCtx = () => ({ qNum: 0, paper });

    if (el.type === 'question' && Array.isArray(el.subQuestions) && el.subQuestions.length > 0) {
        for (let count = el.subQuestions.length - 1; count >= 0; count--) {
            const candidate = Object.assign({}, el, { subQuestions: el.subQuestions.slice(0, count), showMarks: false });
            mount.innerHTML = `<div class="paper-body">${renderElement(candidate, dummyCtx())}</div>`;
            if (mount.firstElementChild.offsetHeight <= availableHeightPx) {
                const restEl = Object.assign({}, el, {
                    text: '', subQuestions: el.subQuestions.slice(count), __continued: true, __subQOffset: count,
                });
                return { fitEl: candidate, restEl };
            }
        }
        // Not even the main text + zero sub-questions fits — fall through and
        // try splitting the main text itself below.
    }

    if (SPLITTABLE_TEXT_TYPES.includes(el.type) && el.text) {
        const wrapFn = (t) => {
            const candidate = Object.assign({}, el, { text: t, marks: '', showMarks: false });
            if (el.type === 'question') candidate.subQuestions = [];
            return `<div class="paper-body">${renderElement(candidate, dummyCtx())}</div>`;
        };
        const split = splitHtmlToFit(mount, wrapFn, el.text, availableHeightPx);
        if (split) {
            const fitEl = Object.assign({}, el, { text: split.fitHtml, marks: '', showMarks: false });
            const restEl = Object.assign({}, el, { text: split.restHtml, __continued: true });
            if (el.type === 'question') {
                fitEl.subQuestions = [];
                restEl.subQuestions = el.subQuestions || [];
            }
            return { fitEl, restEl };
        }
    }

    return null;
}

// Greedily packs elements into page-sized chunks based on measured heights.
// Manual "pagebreak" elements force an early break. When an element doesn't
// fit the remaining space, it's split if possible (see trySplitElement) so
// the current page fills up instead of leaving blank space at the bottom;
// otherwise it moves whole to the next page. `firstPageReservedPx` shrinks
// only the first page's budget (for the header it alone carries). `allowSplit`
// disables splitting altogether — booklet mode passes false, because its
// chunks get reordered for physical folding (a sheet shows its LAST page's
// column before its FIRST page's), so a question split right at a chunk
// boundary would have its "(cont'd)" half appear to come out of order when
// viewing the flat, unfolded pages on screen.
function paginateElements(paper, elements, contentWidthPx, contentHeightPx, cssVars, firstPageReservedPx = 0, allowSplit = true) {
    const mount = getMeasureMount(contentWidthPx, cssVars);
    const dummyCtx = () => ({ qNum: 0, paper });
    const queue = (elements || []).slice();
    const pages = [[]];
    let used = 0;

    while (queue.length) {
        const el = queue.shift();
        if (el.type === 'pagebreak') {
            if (pages[pages.length - 1].length > 0) { pages.push([]); used = 0; }
            continue;
        }

        const budget = pages.length === 1 ? contentHeightPx - firstPageReservedPx : contentHeightPx;
        const remaining = budget - used;
        mount.innerHTML = `<div class="paper-body">${renderElement(el, dummyCtx())}</div>`;
        const fullHeight = mount.firstElementChild.offsetHeight;

        if (fullHeight <= remaining) {
            pages[pages.length - 1].push(el);
            used += fullHeight;
            continue;
        }

        // Split whenever there's meaningful room, REGARDLESS of whether the
        // current page already has other content — a single question that's
        // simply too tall for one whole page/column (spans 3, 4, or more of
        // them) must still be split repeatedly across as many as it needs,
        // not just dumped onto an empty page to silently overflow.
        if (allowSplit && remaining > 24) {
            const split = trySplitElement(paper, el, remaining, mount);
            if (split) {
                pages[pages.length - 1].push(split.fitEl);
                pages.push([]);
                used = 0;
                queue.unshift(split.restEl);
                continue;
            }
        }

        if (pages[pages.length - 1].length === 0) {
            // Page is empty but this single element still doesn't fit (huge
            // element, or unsplittable type) — place it anyway, best effort.
            pages[pages.length - 1].push(el);
            used += fullHeight;
        } else {
            pages.push([el]);
            used = fullHeight;
        }
    }
    return pages.filter(p => p.length > 0);
}

function wrapPage(pageClasses, innerHtml) {
    return `<div class="paper-page ${pageClasses}">${innerHtml}</div>`;
}

// Builds the full printable HTML for a given print mode: paginates content
// into real page-sized chunks (measuring actual rendered height) and wraps
// each physical sheet in its own .paper-page block. Shared by the editor's
// live preview and the final print view (view.php).
function buildPaperOutput(paper, elements, mode, pageSize, printSettings) {
    printSettings = printSettings || {};
    const marginMm = Number(printSettings.margin ?? 15);
    const gapMm = Number(printSettings.gap ?? 10);
    const cssVars = {
        '--base-font-size': (printSettings.fontSize ?? 13) + 'px',
        '--line-height': printSettings.lineSpacing ?? 1.4,
        '--para-spacing': (printSettings.paraSpacing ?? 8) + 'px',
        '--header-font-size': (printSettings.headerFontSize ?? 18) + 'px',
        '--paper-font': fontFamilyFor(paper),
    };

    const sizeCls = pageSize === 'Legal' ? 'size-legal' : '';
    const landscape = (mode === 'half-duplicate' || mode === 'half-sequential' || mode === 'booklet');
    const orientCls = landscape ? 'orientation-landscape' : '';
    const headerBoldCls = printSettings.headerBold === false ? 'header-regular' : '';
    const pageClasses = `${sizeCls} ${orientCls} mode-${mode} ${headerBoldCls}`.replace(/\s+/g, ' ').trim();

    const dims = pageDimsMm(pageSize, landscape);
    // Small safety margin: the offscreen measurement pass and the real page can
    // differ slightly (font hinting, subpixel rounding), so pack pages to ~97%
    // of the computed height rather than the exact figure — a fit that's a
    // hair too generous is what causes elements to overflow and print split
    // across a page boundary instead of moving cleanly to the next page.
    const contentHeightPx = (dims.h - marginMm * 2) * MM_TO_PX * 0.97;
    const ctx = { qNum: 0, paper };
    let html = '';

    if (mode === 'full-2col') {
        const colWidthPx = ((dims.w - marginMm * 2 - gapMm) / 2) * MM_TO_PX;
        const headerPx = measureHeaderHeight(paper, colWidthPx, cssVars);
        const pages = paginateElements(paper, elements, colWidthPx, contentHeightPx * 2, cssVars, headerPx);
        pages.forEach((pageEls, i) => {
            html += wrapPage(pageClasses, renderPaperHtml(paper, pageEls, i === 0, ctx));
        });
    } else if (mode === 'half-duplicate') {
        const colWidthPx = ((dims.w - marginMm * 2 - gapMm) / 2) * MM_TO_PX;
        const headerPx = measureHeaderHeight(paper, colWidthPx, cssVars);
        const pages = paginateElements(paper, elements, colWidthPx, contentHeightPx, cssVars, headerPx);
        pages.forEach((pageEls, i) => {
            const isFirst = i === 0;
            const savedQNum = ctx.qNum;
            const left = renderPaperHtml(paper, pageEls, isFirst, ctx);
            const afterQNum = ctx.qNum;
            ctx.qNum = savedQNum;
            const right = renderPaperHtml(paper, pageEls, isFirst, ctx);
            ctx.qNum = afterQNum;
            html += wrapPage(pageClasses, `<div class="half-page-wrap">${left}<div class="half-gap"></div>${right}</div>`);
        });
    } else if (mode === 'half-sequential') {
        const colWidthPx = ((dims.w - marginMm * 2 - gapMm) / 2) * MM_TO_PX;
        const headerPx = measureHeaderHeight(paper, colWidthPx, cssVars);
        const pages = paginateElements(paper, elements, colWidthPx, contentHeightPx, cssVars, headerPx);
        for (let i = 0; i < pages.length; i += 2) {
            const left = renderPaperHtml(paper, pages[i] || [], i === 0, ctx);
            const right = pages[i + 1] ? renderPaperHtml(paper, pages[i + 1], false, ctx) : '<div class="paper-page-inner"></div>';
            html += wrapPage(pageClasses, `<div class="half-page-wrap">${left}<div class="half-gap"></div>${right}</div>`);
        }
    } else if (mode === 'booklet') {
        // .booklet-col has `padding: 0 gap/2` per side with box-sizing:border-box,
        // so each column loses the FULL gap from its own 50% width — NOT half of
        // a shared gap like full-2col's column-gap. (Getting this wrong makes the
        // measured width wider than the real render, so text wraps onto fewer
        // lines than reality — content silently overflows the page and prints
        // split awkwardly.)
        const colWidthPx = ((dims.w - marginMm * 2) / 2 - gapMm) * MM_TO_PX;
        const headerPx = measureHeaderHeight(paper, colWidthPx, cssVars);
        // Splitting is allowed here: a question spanning 3, 4, or more logical
        // pages is fine because imposition (below) guarantees that folding the
        // printed sheets reconstructs plain 1..N reading order regardless of
        // how many columns any one question spans — the physical fold is what
        // makes it "feel like a book", not the flat/unfolded page sequence.
        let chunks = paginateElements(paper, elements, colWidthPx, contentHeightPx, cssVars, headerPx, true);
        // The fold trick only applies to the first 4 logical pages (one
        // folded sheet) — pad up to a full 4 if short. Anything beyond that
        // is plain sequential pages (2 columns each), so only needs padding
        // to an even count, not a further multiple of 4.
        while (chunks.length < 4) chunks.push([]);
        if (chunks.length > 4 && chunks.length % 2 !== 0) chunks.push([]);
        const N = chunks.length;

        // Render every logical page in reading order FIRST (so question
        // numbering comes out correct, top to bottom, page 1..N), then place
        // the already-rendered HTML into physical positions. Each page gets a
        // small page-number footer so the printed/folded booklet is easy to
        // verify against reading order, like a real small book.
        const chunkHtml = chunks.map((chunkEls, i) =>
            renderPaperHtml(paper, chunkEls, i === 0, ctx) + `<div class="booklet-pagenum">${i + 1}</div>`);

        // First 4 logical pages fold like a single sheet: printing page 1 (the
        // sheet's front) holds [4th, 1st] and page 2 (the back) holds [2nd,
        // 3rd] — so page 1 and page 4 share a sheet, and folding it puts them
        // in 1,2,3,4 reading order.
        html += wrapPage(pageClasses, `<div class="booklet-sheet">
            <div class="booklet-col">${chunkHtml[3]}</div>
            <div class="booklet-col">${chunkHtml[0]}</div>
        </div>`);
        html += wrapPage(pageClasses, `<div class="booklet-sheet">
            <div class="booklet-col">${chunkHtml[1]}</div>
            <div class="booklet-col">${chunkHtml[2]}</div>
        </div>`);

        // Anything past the first 4 logical pages is just plain sequential
        // pages from here — page 5 in the next sheet's 1st column, page 6 in
        // its 2nd, page 7 the following sheet's 1st, and so on.
        for (let i = 4; i < N; i += 2) {
            html += wrapPage(pageClasses, `<div class="booklet-sheet">
                <div class="booklet-col">${chunkHtml[i]}</div>
                <div class="booklet-col">${chunkHtml[i + 1] || ''}</div>
            </div>`);
        }
    } else { // full-1col
        const contentWidthPx = (dims.w - marginMm * 2) * MM_TO_PX;
        const headerPx = measureHeaderHeight(paper, contentWidthPx, cssVars);
        const pages = paginateElements(paper, elements, contentWidthPx, contentHeightPx, cssVars, headerPx);
        pages.forEach((pageEls, i) => {
            html += wrapPage(pageClasses, renderPaperHtml(paper, pageEls, i === 0, ctx));
        });
    }

    return { html };
}
