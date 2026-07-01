// Numbering/serialization helpers for Bangla and English exam papers.
const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
const BN_LETTERS = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন'];

function toBanglaNumeral(n) {
    return String(n).split('').map(ch => (ch >= '0' && ch <= '9') ? BN_DIGITS[+ch] : ch).join('');
}

function toNumber(n, lang) {
    return lang === 'bn' ? toBanglaNumeral(n) : String(n);
}

function toLetter(n, lang) {
    // n is 1-based
    if (lang === 'bn') return BN_LETTERS[(n - 1) % BN_LETTERS.length] || String(n);
    return String.fromCharCode(64 + n); // 1 -> A
}

function toRoman(n) {
    const map = [[1000,'m'],[900,'cm'],[500,'d'],[400,'cd'],[100,'c'],[90,'xc'],[50,'l'],[40,'xl'],
                 [10,'x'],[9,'ix'],[5,'v'],[4,'iv'],[1,'i']];
    let res = '';
    for (const [val, sym] of map) {
        while (n >= val) { res += sym; n -= val; }
    }
    return res;
}
