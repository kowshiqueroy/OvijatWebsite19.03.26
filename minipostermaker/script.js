/**
 * Mini Poster Maker - Core Application Logic
 */

const AppState = {
    project: { width: 1080, height: 1920, bgColor: '#121212', bgImage: null },
    elements: [],
    activeElementId: null,
    history: [], historyIndex: -1,
    loopDuration: 5, motionPreset: 'none'
};

const UI = {
    get artboard() { return document.getElementById('artboard'); },
    get wrapper() { return document.getElementById('artboard-wrapper'); },
    get workspace() { return document.getElementById('workspace'); },
    get elementsLayer() { return document.getElementById('elements-layer'); },
    get bgLayer() { return document.getElementById('art-bg'); },
    get canvas() { return document.getElementById('animation-canvas'); },
    get ctx() { return this.canvas ? this.canvas.getContext('2d') : null; },
    get backdrop() { return document.getElementById('sheet-backdrop'); },
    get sheets() { return document.querySelectorAll('.bottom-sheet'); },
    get toolBtns() { return document.querySelectorAll('.tool-btn'); },
    get closeBtns() { return document.querySelectorAll('.close-sheet'); },
    get overlay() { return document.getElementById('transform-overlay'); },
    get guideV() { return document.getElementById('guide-v'); },
    get guideH() { return document.getElementById('guide-h'); },
    get layersList() { return document.getElementById('layers-list'); }
};

const Interaction = {
    isDragging: false, isRotating: false, isResizing: false,
    action: null, startX: 0, startY: 0,
    initialX: 0, initialY: 0, initialRotation: 0, initialScale: 1,
    elementId: null
};

function initApp() {
    setupUI();
    resizeWorkspace();
    window.addEventListener('resize', resizeWorkspace);
    setupToolActions();
    setupInteractionEngine();
    setupAnimationEngine();
    setupExportEngine();
    
    window.toggleLayerVisibility = toggleLayerVisibility;
    window.moveLayer = moveLayer;

    loadState();
    renderWorkspace();
}

function generateId() { return Math.random().toString(36).substr(2, 9); }

function setupUI() {
    UI.toolBtns.forEach(btn => btn.addEventListener('click', () => openSheet(btn.getAttribute('data-target'))));
    document.getElementById('btn-resize')?.addEventListener('click', () => openSheet('sheet-project'));
    UI.closeBtns.forEach(btn => btn.addEventListener('click', closeAllSheets));
    if (UI.backdrop) UI.backdrop.addEventListener('click', closeAllSheets);
    
    if (UI.workspace) {
        UI.workspace.addEventListener('touchmove', (e) => { if(e.touches && e.touches.length > 1) e.preventDefault(); }, { passive: false });
        UI.workspace.addEventListener('pointerdown', (e) => {
            if (e.target === UI.workspace || e.target === UI.wrapper) { AppState.activeElementId = null; updateTransformOverlay(); }
        });
    }

    const btnUndo = document.getElementById('btn-undo'), btnRedo = document.getElementById('btn-redo');
    if (btnUndo) btnUndo.addEventListener('click', undo);
    if (btnRedo) btnRedo.addEventListener('click', redo);
}

function openSheet(id) {
    closeAllSheets();
    const sheet = document.getElementById(id);
    if (sheet) { 
        sheet.classList.add('active'); 
        if (UI.backdrop) UI.backdrop.classList.add('active'); 
        if (id === 'sheet-layers') renderLayersList();
    }
}

function closeAllSheets() {
    UI.sheets.forEach(sheet => sheet.classList.remove('active'));
    if (UI.backdrop) UI.backdrop.classList.remove('active');
}

function setupToolActions() {
    // Text
    document.getElementById('btn-add-text')?.addEventListener('click', () => {
        const textInput = document.getElementById('text-input'), fontInput = document.getElementById('text-font'), colorInput = document.getElementById('text-color'), strokeInput = document.getElementById('text-stroke-color');
        const newEl = {
            id: generateId(), type: 'text', content: textInput.value || 'Hello',
            fontFamily: fontInput.value, color: colorInput.value,
            fontSize: 80, stroke: true, strokeColor: strokeInput.value,
            x: AppState.project.width/2, y: AppState.project.height/2, scale: 1, rotation: 0, visible: true
        };
        AppState.elements.push(newEl); AppState.activeElementId = newEl.id;
        saveState(); renderWorkspace(); closeAllSheets();
    });

    // Image Upload
    document.getElementById('image-upload')?.addEventListener('change', (e) => {
        const file = e.target.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => {
            const blendInput = document.getElementById('image-blend');
            const newEl = {
                id: generateId(), type: 'image', src: event.target.result,
                blendMode: blendInput.value,
                x: AppState.project.width/2, y: AppState.project.height/2, scale: 0.5, rotation: 0, visible: true
            };
            AppState.elements.push(newEl); AppState.activeElementId = newEl.id;
            saveState(); renderWorkspace(); closeAllSheets();
        };
        reader.readAsDataURL(file);
    });

    // Shapes
    document.querySelectorAll('[data-shape]').forEach(btn => {
        btn.addEventListener('click', () => {
            const colorInput = document.getElementById('shape-color'), radiusInput = document.getElementById('shape-radius');
            const newEl = {
                id: generateId(), type: 'shape', shapeType: btn.getAttribute('data-shape'),
                color: colorInput.value, radius: parseInt(radiusInput.value),
                width: 400, height: 400, x: AppState.project.width/2, y: AppState.project.height/2, scale: 1, rotation: 0, visible: true
            };
            AppState.elements.push(newEl); AppState.activeElementId = newEl.id;
            saveState(); renderWorkspace(); closeAllSheets();
        });
    });

    // Background
    document.getElementById('bg-color')?.addEventListener('input', (e) => { 
        AppState.project.bgColor = e.target.value; 
        if(UI.bgLayer) UI.bgLayer.style.backgroundColor = e.target.value; 
        saveState(); 
    });
    document.getElementById('bg-image-upload')?.addEventListener('change', (e) => {
        const file = e.target.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = (event) => { AppState.project.bgImage = event.target.result; renderWorkspace(); saveState(); };
        reader.readAsDataURL(file);
    });

    // Project Size Selector
    document.querySelectorAll('[data-size]').forEach(btn => {
        btn.addEventListener('click', () => {
            const [w, h] = btn.getAttribute('data-size').split('x');
            document.getElementById('project-w').value = w; 
            document.getElementById('project-h').value = h;
        });
    });
    document.getElementById('btn-apply-size')?.addEventListener('click', () => {
        AppState.project.width = parseInt(document.getElementById('project-w').value);
        AppState.project.height = parseInt(document.getElementById('project-h').value);
        document.getElementById('project-size').innerText = `${AppState.project.width}x${AppState.project.height}`;
        resizeWorkspace(); saveState(); closeAllSheets();
    });
}

function resizeWorkspace() {
    if (!UI.workspace || !UI.artboard) return;
    
    // Set actual artboard dimensions
    UI.artboard.style.width = `${AppState.project.width}px`;
    UI.artboard.style.height = `${AppState.project.height}px`;

    const rect = UI.workspace.getBoundingClientRect(), padding = 60;
    const scale = Math.min((rect.width - padding) / AppState.project.width, (rect.height - padding) / AppState.project.height);
    
    if (UI.wrapper) UI.wrapper.style.transform = `translate(-50%, -50%) scale(${scale})`;
    if (UI.canvas) { 
        UI.canvas.width = AppState.project.width; 
        UI.canvas.height = AppState.project.height; 
    }
}

function saveState() {
    const snap = JSON.stringify({ project: AppState.project, elements: AppState.elements, motionPreset: AppState.motionPreset, loopDuration: AppState.loopDuration });
    AppState.history = AppState.history.slice(0, AppState.historyIndex + 1);
    AppState.history.push(snap); if(AppState.history.length > 30) AppState.history.shift();
    AppState.historyIndex = AppState.history.length - 1;
    localStorage.setItem('miniposter_autosave', snap);
    updateHistoryButtons();
}

function loadState() {
    const saved = localStorage.getItem('miniposter_autosave');
    if (saved) {
        try {
            const data = JSON.parse(saved); Object.assign(AppState, data);
            AppState.history = [saved]; AppState.historyIndex = 0;
            if(document.getElementById('project-size')) document.getElementById('project-size').innerText = `${AppState.project.width}x${AppState.project.height}`;
        } catch(e) { saveState(); }
    } else saveState();
    updateHistoryButtons();
}

function undo() {
    if (AppState.historyIndex > 0) {
        AppState.historyIndex--; const data = JSON.parse(AppState.history[AppState.historyIndex]);
        Object.assign(AppState, data); renderWorkspace(); updateHistoryButtons();
    }
}
function redo() {
    if (AppState.historyIndex < AppState.history.length - 1) {
        AppState.historyIndex++; const data = JSON.parse(AppState.history[AppState.historyIndex]);
        Object.assign(AppState, data); renderWorkspace(); updateHistoryButtons();
    }
}
function updateHistoryButtons() {
    const u = document.getElementById('btn-undo'), r = document.getElementById('btn-redo');
    if (u) u.disabled = AppState.historyIndex <= 0;
    if (r) r.disabled = AppState.historyIndex >= AppState.history.length - 1;
}

function getPointerPos(e) {
    const rect = UI.artboard.getBoundingClientRect(), scale = rect.width / AppState.project.width;
    let cx = e.clientX, cy = e.clientY;
    if (e.touches && e.touches.length > 0) { cx = e.touches[0].clientX; cy = e.touches[0].clientY; }
    return { x: (cx - rect.left) / scale, y: (cy - rect.top) / scale };
}

function setupInteractionEngine() {
    if (!UI.overlay) return;
    UI.overlay.querySelectorAll('.handle').forEach(h => {
        h.addEventListener('pointerdown', (e) => {
            e.stopPropagation(); if (!AppState.activeElementId) return;
            const el = AppState.elements.find(el => el.id === AppState.activeElementId), pos = getPointerPos(e);
            Interaction.elementId = AppState.activeElementId; Interaction.action = h.getAttribute('data-action');
            Interaction.startX = pos.x; Interaction.startY = pos.y;
            Interaction.initialX = el.x; Interaction.initialY = el.y;
            Interaction.initialScale = el.scale; Interaction.initialRotation = el.rotation;
            if (Interaction.action === 'rotate') Interaction.isRotating = true;
            else if (Interaction.action === 'delete') deleteActiveElement();
            else Interaction.isResizing = true;
        });
    });

    document.addEventListener('pointermove', (e) => {
        if (!Interaction.elementId) return;
        const el = AppState.elements.find(el => el.id === Interaction.elementId); if (!el) return;
        const pos = getPointerPos(e);
        if (Interaction.isDragging) {
            let nx = Interaction.initialX + (pos.x - Interaction.startX), ny = Interaction.initialY + (pos.y - Interaction.startY);
            const thresh = 30, cx = AppState.project.width/2, cy = AppState.project.height/2;
            if (Math.abs(nx - cx) < thresh) { nx = cx; if(UI.guideV) UI.guideV.style.display = 'block'; } else if(UI.guideV) UI.guideV.style.display = 'none';
            if (Math.abs(ny - cy) < thresh) { ny = cy; if(UI.guideH) UI.guideH.style.display = 'block'; } else if(UI.guideH) UI.guideH.style.display = 'none';
            el.x = nx; el.y = ny;
        } else if (Interaction.isRotating) {
            el.rotation = Math.atan2(pos.y - el.y, pos.x - el.x) * (180/Math.PI) - 90;
        } else if (Interaction.isResizing) {
            const dist = Math.hypot(pos.x - el.x, pos.y - el.y), startDist = Math.hypot(Interaction.startX - el.x, Interaction.startY - el.y);
            if (startDist > 0) el.scale = Math.max(0.1, Interaction.initialScale * (dist/startDist));
        }
        fastUpdateElementDOM(el); updateTransformOverlay();
    });

    document.addEventListener('pointerup', () => {
        if (Interaction.elementId) { saveState(); if(UI.guideV) UI.guideV.style.display = 'none'; if(UI.guideH) UI.guideH.style.display = 'none'; }
        Interaction.isDragging = false; Interaction.isRotating = false; Interaction.isResizing = false; Interaction.elementId = null;
    });
}

function deleteActiveElement() { 
    AppState.elements = AppState.elements.filter(e => e.id !== AppState.activeElementId); 
    AppState.activeElementId = null; saveState(); renderWorkspace(); 
}

function renderWorkspace() {
    if (!UI.bgLayer) return;
    UI.bgLayer.style.backgroundColor = AppState.project.bgColor;
    UI.bgLayer.style.backgroundImage = AppState.project.bgImage ? `url(${AppState.project.bgImage})` : 'none';
    UI.elementsLayer.innerHTML = '';
    AppState.elements.forEach((el, idx) => {
        if (!el.visible) return;
        const dom = document.createElement('div'); dom.className = 'canvas-element'; dom.id = `el-${el.id}`; dom.style.zIndex = idx;
        if (el.type === 'text') {
            dom.innerText = el.content; dom.style.fontFamily = el.fontFamily; dom.style.color = el.color; dom.style.fontSize = `${el.fontSize}px`;
            if (el.stroke) dom.style.webkitTextStroke = `2px ${el.strokeColor}`;
        } else if (el.type === 'image') {
            const img = document.createElement('img'); img.src = el.src; img.style.width = '100%'; dom.appendChild(img);
            dom.style.mixBlendMode = el.blendMode || 'normal';
        } else if (el.type === 'shape') {
            dom.style.width = `400px`; dom.style.height = `400px`; dom.style.backgroundColor = el.color;
            dom.style.borderRadius = el.shapeType === 'circle' ? '50%' : `${el.radius}px`;
        }
        applyElementStyles(dom, el);
        dom.addEventListener('pointerdown', (e) => {
            e.stopPropagation(); AppState.activeElementId = el.id;
            const p = getPointerPos(e); Interaction.elementId = el.id; Interaction.isDragging = true;
            Interaction.startX = p.x; Interaction.startY = p.y; Interaction.initialX = el.x; Interaction.initialY = el.y;
            updateTransformOverlay();
        });
        UI.elementsLayer.appendChild(dom);
    });
    requestAnimationFrame(updateTransformOverlay);
}

function applyElementStyles(dom, el) {
    dom.style.left = `${el.x}px`; dom.style.top = `${el.y}px`;
    dom.style.transform = `translate(-50%, -50%) rotate(${el.rotation}deg) scale(${el.scale})`;
}

function fastUpdateElementDOM(el) { const dom = document.getElementById(`el-${el.id}`); if (dom) applyElementStyles(dom, el); }

function updateTransformOverlay() {
    if (!UI.overlay) return;
    if (!AppState.activeElementId) { UI.overlay.style.display = 'none'; return; }
    const el = AppState.elements.find(e => e.id === AppState.activeElementId), dom = document.getElementById(`el-${el.id}`);
    if (!el || !dom || !el.visible) { UI.overlay.style.display = 'none'; return; }
    UI.overlay.style.display = 'block'; UI.overlay.style.left = `${el.x}px`; UI.overlay.style.top = `${el.y}px`;
    UI.overlay.style.transform = `translate(-50%, -50%) rotate(${el.rotation}deg)`;
    UI.overlay.style.width = `${dom.offsetWidth * el.scale}px`; UI.overlay.style.height = `${dom.offsetHeight * el.scale}px`;
}

function renderLayersList() {
    if (!UI.layersList) return;
    UI.layersList.innerHTML = '';
    [...AppState.elements].reverse().forEach((el) => {
        const li = document.createElement('li'); li.className = 'layer-item';
        li.innerHTML = `<span class="layer-name">${el.type.toUpperCase()}: ${el.content || el.shapeType || 'Image'}</span><div class="layer-actions"><button onclick="toggleLayerVisibility('${el.id}')">${el.visible ? '👁️' : '🙈'}</button><button onclick="moveLayer('${el.id}', 1)">🔼</button><button onclick="moveLayer('${el.id}', -1)">🔽</button></div>`;
        UI.layersList.appendChild(li);
    });
}

function toggleLayerVisibility(id) { const el = AppState.elements.find(e => e.id === id); if (el) { el.visible = !el.visible; renderWorkspace(); renderLayersList(); saveState(); } }
function moveLayer(id, dir) {
    const idx = AppState.elements.findIndex(e => e.id === id);
    if ((dir === 1 && idx < AppState.elements.length - 1) || (dir === -1 && idx > 0)) {
        const el = AppState.elements.splice(idx, 1)[0]; AppState.elements.splice(idx + dir, 0, el); renderWorkspace(); renderLayersList(); saveState();
    }
}

let startTime = performance.now(), particles = [];
function setupAnimationEngine() {
    document.getElementById('motion-preset')?.addEventListener('change', (e) => { AppState.motionPreset = e.target.value; initParticles(); saveState(); });
    document.getElementById('motion-duration')?.addEventListener('change', (e) => { AppState.loopDuration = parseInt(e.target.value); initParticles(); saveState(); });
    initParticles(); requestAnimationFrame(renderLoop);
}

function initParticles() {
    particles = []; const w = AppState.project.width, h = AppState.project.height;
    if (AppState.motionPreset === 'rain') for(let i=0; i<100; i++) particles.push({ x: Math.random()*w, y: Math.random()*h, length: Math.random()*20+20, speed: Math.random()*500+500, alpha: Math.random()*0.5+0.1 });
    else if (AppState.motionPreset === 'snow') for(let i=0; i<150; i++) particles.push({ x: Math.random()*w, y: Math.random()*h, radius: Math.random()*3+1, speed: Math.random()*100+100, drift: Math.random()*50-25, alpha: Math.random()*0.8+0.2 });
    else if (AppState.motionPreset === 'flower') for(let i=0; i<30; i++) particles.push({ x: Math.random()*w, y: Math.random()*h, size: Math.random()*15+10, speed: Math.random()*150+100, rotationSpeed: Math.random()*180-90, initialRotation: Math.random()*360, drift: Math.random()*60-30 });
    else if (AppState.motionPreset === 'clouds') for(let i=0; i<8; i++) particles.push({ x: Math.random()*w, y: Math.random()*(h/2), scale: Math.random()*1.5+0.5, speed: Math.random()*30+20, alpha: Math.random()*0.4+0.1 });
}

function renderLoop(now) {
    requestAnimationFrame(renderLoop); const elapsed = (now - startTime) / 1000, loopTime = elapsed % AppState.loopDuration;
    if (!UI.ctx) return;
    UI.ctx.clearRect(0, 0, UI.canvas.width, UI.canvas.height); if (AppState.motionPreset === 'none') return;
    const w = UI.canvas.width, h = UI.canvas.height;
    particles.forEach(p => {
        if (AppState.motionPreset === 'rain') {
            const s = Math.ceil(p.speed / (h/AppState.loopDuration)) * (h/AppState.loopDuration); let y = (p.y + s*loopTime)%h;
            UI.ctx.beginPath(); UI.ctx.strokeStyle = `rgba(255,255,255,${p.alpha})`; UI.ctx.lineWidth = 2; UI.ctx.moveTo(p.x, y); UI.ctx.lineTo(p.x, y+p.length); UI.ctx.stroke();
            if (y > h-p.length) { UI.ctx.beginPath(); UI.ctx.moveTo(p.x, y-h); UI.ctx.lineTo(p.x, y-h+p.length); UI.ctx.stroke(); }
        } else if (AppState.motionPreset === 'snow') {
            const sy = Math.ceil(p.speed / (h/AppState.loopDuration)) * (h/AppState.loopDuration), sx = Math.round(p.drift / (w/AppState.loopDuration)) * (w/AppState.loopDuration);
            let y = (p.y + sy*loopTime)%h, x = (p.x + sx*loopTime)%w; if (x<0) x+=w;
            UI.ctx.beginPath(); UI.ctx.fillStyle = `rgba(255,255,255,${p.alpha})`; UI.ctx.arc(x,y,p.radius,0,Math.PI*2); UI.ctx.fill();
        } else if (AppState.motionPreset === 'flower') {
            const sy = Math.ceil(p.speed / (h/AppState.loopDuration)) * (h/AppState.loopDuration), sx = Math.round(p.drift / (w/AppState.loopDuration)) * (w/AppState.loopDuration);
            let y = (p.y + sy*loopTime)%h, x = (p.x + sx*loopTime)%w; if (x<0) x+=w;
            let rot = p.initialRotation + (Math.round((p.rotationSpeed*AppState.loopDuration)/360)*360/AppState.loopDuration)*loopTime;
            UI.ctx.save(); UI.ctx.translate(x,y); UI.ctx.rotate(rot*Math.PI/180); UI.ctx.fillStyle = `rgba(255,182,193,0.8)`; UI.ctx.beginPath(); UI.ctx.moveTo(0,0); UI.ctx.quadraticCurveTo(p.size, -p.size, p.size*1.5, 0); UI.ctx.quadraticCurveTo(p.size, p.size, 0, 0); UI.ctx.fill(); UI.ctx.restore();
        } else if (AppState.motionPreset === 'clouds') {
            const sx = Math.ceil(p.speed / (w/AppState.loopDuration)) * (w/AppState.loopDuration); let x = (p.x + sx*loopTime)%w;
            const d = (tx, ty, ts, ta) => { UI.ctx.save(); UI.ctx.translate(tx,ty); UI.ctx.scale(ts,ts); UI.ctx.fillStyle = `rgba(255,255,255,${ta})`; UI.ctx.beginPath(); UI.ctx.arc(0,0,20,0,Math.PI*2); UI.ctx.arc(20,-10,25,0,Math.PI*2); UI.ctx.arc(40,0,20,0,Math.PI*2); UI.ctx.fill(); UI.ctx.restore(); };
            d(x, p.y, p.scale, p.alpha); if (x > w-(80*p.scale)) d(x-w, p.y, p.scale, p.alpha);
        }
    });
}

function setupExportEngine() {
    document.getElementById('btn-export-menu')?.addEventListener('click', () => openSheet('sheet-export'));
    document.getElementById('btn-export-png')?.addEventListener('click', () => {
        const c = createCompositeCanvas(parseInt(document.getElementById('export-scale').value));
        const a = document.createElement('a'); a.download = `p_${Date.now()}.png`; a.href = c.toDataURL(); a.click();
    });
    document.getElementById('btn-export-video')?.addEventListener('click', async () => {
        const b = document.getElementById('btn-export-video'), s = document.getElementById('recording-status');
        b.disabled = true; s.style.display = 'block';
        const c = document.createElement('canvas'); c.width = AppState.project.width; c.height = AppState.project.height;
        const ctx = c.getContext('2d'), stream = c.captureStream(30);
        const rec = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp9' });
        const ch = []; rec.ondataavailable = e => ch.push(e.data);
        rec.onstop = () => {
            const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(ch, {type:'video/webm'})); a.download = `v_${Date.now()}.webm`; a.click();
            b.disabled = false; s.style.display = 'none';
        };
        rec.start(); const startMs = performance.now();
        const tick = () => {
            if (performance.now()-startMs > AppState.loopDuration*1000) { rec.stop(); return; }
            ctx.clearRect(0,0,c.width,c.height); if (AppState.project.bgColor) { ctx.fillStyle = AppState.project.bgColor; ctx.fillRect(0,0,c.width,c.height); }
            if (UI.canvas) ctx.drawImage(UI.canvas, 0, 0);
            AppState.elements.forEach(el => {
                if (!el.visible) return;
                ctx.save(); ctx.translate(el.x, el.y); ctx.rotate(el.rotation*Math.PI/180); ctx.scale(el.scale, el.scale);
                if (el.type === 'text') {
                    ctx.font = `${el.fontSize}px ${el.fontFamily}`; ctx.fillStyle = el.color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    const lines = el.content.split('\n'), lh = el.fontSize*1.2, sy = -(lines.length-1)*lh/2;
                    lines.forEach((l, i) => { if (el.stroke) { ctx.lineWidth=4; ctx.strokeStyle=el.strokeColor; ctx.lineJoin='round'; ctx.strokeText(l,0,sy+(i*lh)); } ctx.fillText(l,0,sy+(i*lh)); });
                } else if (el.type === 'image') {
                    const img = new Image(); img.src = el.src; ctx.drawImage(img, -img.width/2, -img.height/2);
                } else if (el.type === 'shape') {
                    ctx.fillStyle = el.color; if (el.shapeType === 'circle') { ctx.beginPath(); ctx.arc(0,0,200,0,Math.PI*2); ctx.fill(); }
                    else { ctx.beginPath(); ctx.roundRect(-200, -200, 400, 400, el.radius); ctx.fill(); }
                }
                ctx.restore();
            });
            requestAnimationFrame(tick);
        };
        tick();
    });
}

function createCompositeCanvas(s = 1) {
    const c = document.createElement('canvas'); c.width = AppState.project.width*s; c.height = AppState.project.height*s;
    const ctx = c.getContext('2d'); if (AppState.project.bgColor) { ctx.fillStyle = AppState.project.bgColor; ctx.fillRect(0,0,c.width,c.height); }
    if (UI.canvas) ctx.drawImage(UI.canvas, 0, 0, c.width, c.height);
    AppState.elements.forEach(el => {
        if (!el.visible) return;
        ctx.save(); ctx.translate(el.x*s, el.y*s); ctx.rotate(el.rotation*Math.PI/180); ctx.scale(el.scale*s, el.scale*s);
        if (el.type === 'text') {
            ctx.font = `${el.fontSize}px ${el.fontFamily}`; ctx.fillStyle = el.color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            const lines = el.content.split('\n'), lh = el.fontSize*1.2, sy = -(lines.length-1)*lh/2;
            lines.forEach((l, i) => { if (el.stroke) { ctx.lineWidth=6; ctx.strokeStyle=el.strokeColor; ctx.lineJoin='round'; ctx.strokeText(l,0,sy+(i*lh)); } ctx.fillText(l,0,sy+(i*lh)); });
        } else if (el.type === 'image') {
            const img = new Image(); img.src = el.src; ctx.drawImage(img, -img.width/2, -img.height/2);
        } else if (el.type === 'shape') {
            ctx.fillStyle = el.color; if (el.shapeType === 'circle') { ctx.beginPath(); ctx.arc(0,0,200*s,0,Math.PI*2); ctx.fill(); }
            else { ctx.beginPath(); ctx.roundRect(-200*s, -200*s, 400*s, 400*s, el.radius*s); ctx.fill(); }
        }
        ctx.restore();
    });
    return c;
}

document.addEventListener('DOMContentLoaded', initApp);