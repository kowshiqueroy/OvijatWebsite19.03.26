async function loadAndRender() {
    const res = await api('/api/paper.php?action=get&id=' + window.PAPER_ID);
    if (!res.ok) { toast(res.error || 'Failed to load question', 'error'); return; }

    const paper = res.paper;
    paper.show_set_code = Number(paper.show_set_code) === 1;
    paper.show_subject_code = Number(paper.show_subject_code) === 1;
    const elements = paper.elements || [];
    const printSettings = Object.assign(
        { lineSpacing: 1.4, paraSpacing: 8, fontSize: 13, headerFontSize: 18, headerBold: true, margin: 15, gap: 10 },
        paper.print_settings || {}
    );
    const mode = paper.print_mode || 'full-1col';
    const size = paper.page_size || 'A4';

    document.title = paper.name + ' — Print Preview';

    const root = document.getElementById('printRoot');
    root.className = 'pages-stack';
    root.style.setProperty('--base-font-size', printSettings.fontSize + 'px');
    root.style.setProperty('--line-height', printSettings.lineSpacing);
    root.style.setProperty('--para-spacing', printSettings.paraSpacing + 'px');
    root.style.setProperty('--header-font-size', printSettings.headerFontSize + 'px');
    root.style.setProperty('--page-margin', printSettings.margin + 'mm');
    root.style.setProperty('--gap-margin', printSettings.gap + 'mm');
    root.style.setProperty('--paper-font', fontFamilyFor(paper));

    const { html } = buildPaperOutput(paper, elements, mode, size, printSettings);
    root.innerHTML = html;

    const orientation = (mode === 'half-duplicate' || mode === 'half-sequential' || mode === 'booklet') ? 'landscape' : 'portrait';
    const pageSize = size === 'Legal' ? 'legal' : 'A4';
    document.getElementById('dynamicPageStyle').textContent =
        `@page { size: ${pageSize} ${orientation}; margin: 0; }`;
}

document.getElementById('doPrintBtn').addEventListener('click', () => window.print());

loadAndRender();
