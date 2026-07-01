function showToast(msg, type='info') {
  let c = document.getElementById('toastContainer');
  if (!c) { c = Object.assign(document.createElement('div'),{id:'toastContainer'}); document.body.appendChild(c); }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = (type==='success'?'✓ ':type==='error'?'✕ ':'ℹ ') + msg;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 2800);
}
