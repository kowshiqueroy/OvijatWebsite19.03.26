// Only Unicode-compatible Bangla fonts are offered here. Legacy ANSI/Bijoy-encoded
// fonts (e.g. SutonnyMJ) map Bangla glyphs onto Latin character codes and cannot
// render real Unicode Bangla text at all, so picking them silently "does nothing" —
// these three are bundled as real font files in assets/fonts/ instead.
const FONTS = {
    bn: ['Kalpurush', 'Siyam Rupali', 'SutonnyUniBanglaOMJ'],
    en: ['Times New Roman', 'Arial'],
};

const langSelect = document.getElementById('fLanguage');
const primaryFontSelect = document.getElementById('fPrimaryFont');
const secondaryFontSelect = document.getElementById('fSecondaryFont');

function fillFontSelect(select, fonts) {
    select.innerHTML = fonts.map(f => `<option value="${f}">${f}</option>`).join('');
}

function refreshFontOptions() {
    const lang = langSelect.value;
    const other = lang === 'bn' ? 'en' : 'bn';
    fillFontSelect(primaryFontSelect, FONTS[lang]);
    fillFontSelect(secondaryFontSelect, FONTS[other]);
}

langSelect.addEventListener('change', refreshFontOptions);
refreshFontOptions();

// ---- Strict language input filtering ----
// Bangla block U+0980–U+09FF, plus common punctuation/spaces/digits.
const BANGLA_RE = /[^ঀ-৿0-9\s.,:\-()\/]/g;
const ENGLISH_RE = /[^A-Za-z0-9\s.,:\-()\/]/g;

function filterLangInputs() {
    const lang = langSelect.value;
    const re = lang === 'bn' ? BANGLA_RE : ENGLISH_RE;
    document.querySelectorAll('[data-lang-input]').forEach(input => {
        input.value = input.value.replace(re, '');
    });
}

document.querySelectorAll('[data-lang-input]').forEach(input => {
    input.addEventListener('input', () => {
        const lang = langSelect.value;
        const re = lang === 'bn' ? BANGLA_RE : ENGLISH_RE;
        const cleaned = input.value.replace(re, '');
        if (cleaned !== input.value) input.value = cleaned;
    });
});
langSelect.addEventListener('change', filterLangInputs);
langSelect.addEventListener('change', () => {
    const lang = langSelect.value;
    document.getElementById('setCodeBox').dispatchEvent(new Event('input'));
    subjectCodeBoxes.querySelectorAll('.code-box').forEach(box => {
        box.value = box.value.replace(digitFilterFor(lang), '');
    });
});

// ---- Set code / subject code boxes ----
const showSetCode = document.getElementById('fShowSetCode');
const setCodeBoxes = document.getElementById('setCodeBoxes');
const showSubjectCode = document.getElementById('fShowSubjectCode');
const subjectCodeBoxes = document.getElementById('subjectCodeBoxes');

showSetCode.addEventListener('change', () => { setCodeBoxes.hidden = !showSetCode.checked; });
showSubjectCode.addEventListener('change', () => { subjectCodeBoxes.hidden = !showSubjectCode.checked; });

// Strict, single-script only — no mixing Bangla and English digits/letters
// within one paper's set/subject code, matching the rest of the app's
// language-restricted input fields.
function digitFilterFor(lang) {
    return lang === 'bn' ? /[^০-৯]/g : /[^0-9]/g;
}
function scriptFilterFor(lang) {
    return lang === 'bn' ? /[^ঀ-৿]/g : /[^A-Za-z0-9]/g;
}

subjectCodeBoxes.querySelectorAll('.code-box').forEach((box, i, all) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(digitFilterFor(langSelect.value), '').slice(0, 1);
        if (box.value && all[i + 1]) all[i + 1].focus();
    });
});

document.getElementById('setCodeBox').addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(scriptFilterFor(langSelect.value), '').slice(0, 1);
});

// ---- Submit ----
document.getElementById('setupForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('setupError');
    errEl.textContent = '';

    if (showSetCode.checked && !document.getElementById('setCodeBox').value.trim()) {
        errEl.textContent = 'Please enter the Set Code letter, or uncheck Set Code.';
        return;
    }
    const subjectDigits = Array.from(subjectCodeBoxes.querySelectorAll('.code-box')).map(b => b.value.trim());
    if (showSubjectCode.checked && subjectDigits.some(d => d === '')) {
        errEl.textContent = 'Please enter all 3 digits of the Subject Code, or uncheck Subject Code.';
        return;
    }

    const payload = {
        name: document.getElementById('fName').value.trim(),
        language: langSelect.value,
        primary_font: primaryFontSelect.value,
        secondary_font: secondaryFontSelect.value,
    };

    const createRes = await api('/api/paper.php?action=create', { method: 'POST', body: payload });
    if (!createRes.ok) { errEl.textContent = createRes.error || 'Failed to create question'; return; }
    const id = createRes.id;

    const saveRes = await api('/api/paper.php?action=save', {
        method: 'POST',
        body: {
            id,
            page_size: document.getElementById('fPageSize').value,
            school_name: document.getElementById('fSchool').value.trim(),
            exam_name: document.getElementById('fExam').value.trim(),
            class_name: document.getElementById('fClass').value.trim(),
            subject_name: document.getElementById('fSubject').value.trim(),
            time_text: document.getElementById('fTime').value.trim(),
            full_marks: document.getElementById('fFullMarks').value.trim(),
            show_set_code: showSetCode.checked,
            set_code: document.getElementById('setCodeBox').value.trim(),
            show_subject_code: showSubjectCode.checked,
            subject_code: subjectDigits.join(''),
        },
    });

    if (saveRes.ok) {
        window.location.href = APP_BASE + '/editor.php?id=' + id;
    } else {
        errEl.textContent = saveRes.error || 'Failed to save details';
    }
});
