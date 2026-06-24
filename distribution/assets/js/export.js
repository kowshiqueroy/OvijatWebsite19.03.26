/**
 * Ovijat Distribution — Export Utilities
 * CSV download + WhatsApp copy helpers (client-side, no server required).
 */

/**
 * Download a 2-D array as a UTF-8 CSV file.
 * @param {string} filename
 * @param {string[]} headers
 * @param {any[][]} rows
 */
function downloadCSV(filename, headers, rows) {
    const escape = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const lines  = [headers.map(escape).join(',')];
    rows.forEach(r => lines.push(r.map(escape).join(',')));
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/**
 * Download every visible <table> on the page as CSV.
 * Falls back to the first table with class .export-table if multiple exist.
 * @param {string} filename
 * @param {string} [tableSelector='.export-table']
 */
function downloadTableCSV(filename, tableSelector) {
    const table = document.querySelector(tableSelector || '.export-table') || document.querySelector('table');
    if (!table) { alert('No table found to export.'); return; }

    const rows = [];
    table.querySelectorAll('tr').forEach(tr => {
        const cells = [];
        tr.querySelectorAll('th, td').forEach(cell => {
            // Skip action columns tagged with data-no-export
            if (cell.hasAttribute('data-no-export')) return;
            cells.push(cell.innerText.trim().replace(/\s+/g, ' '));
        });
        if (cells.length) rows.push(cells);
    });

    const headers = rows.shift() || [];
    downloadCSV(filename, headers, rows);
}

/**
 * Copy text to clipboard and briefly flash a button.
 * @param {string} text
 * @param {HTMLElement|null} btn  Optional button to flash "Copied!"
 */
function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied!';
            btn.classList.add('btn-success');
            setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('btn-success'); }, 2000);
        }
    }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }
    });
}

/**
 * Build a WhatsApp-friendly text block from a table element.
 * @param {string} title
 * @param {string} [tableSelector='.export-table']
 * @param {string} [footer]
 * @returns {string}
 */
function tableToWhatsApp(title, tableSelector, footer) {
    const table = document.querySelector(tableSelector || '.export-table') || document.querySelector('table');
    if (!table) return '';

    const lines = ['*' + title + '*', '─'.repeat(32)];
    const headers = [];
    const headerRow = table.querySelector('thead tr');
    if (headerRow) {
        headerRow.querySelectorAll('th').forEach(th => {
            if (!th.hasAttribute('data-no-export')) headers.push(th.innerText.trim());
        });
    }

    table.querySelectorAll('tbody tr').forEach(tr => {
        const cells = [];
        tr.querySelectorAll('td').forEach((td, i) => {
            if (td.hasAttribute('data-no-export')) return;
            const label = headers[i] || '';
            const val   = td.innerText.trim().replace(/\s+/g, ' ');
            if (val && val !== '—') cells.push(label ? label + ': ' + val : val);
        });
        if (cells.length) lines.push(cells.join(' | '));
    });

    if (footer) { lines.push('─'.repeat(32)); lines.push(footer); }
    return lines.join('\n');
}
