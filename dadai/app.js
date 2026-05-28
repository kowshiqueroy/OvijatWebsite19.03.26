// ============================================
// Dadai - Core Application Logic
// Vanilla JS SPA with WebLLM integration
// ============================================

import { CreateMLCEngine, hasModelInCache } from 'https://esm.run/@mlc-ai/web-llm';

// ============================================
// CONFIGURATION
// ============================================

const MODELS = [
  {
    id: 'Qwen2.5-0.5B-Instruct-q4f16_1-MLC',
    displayName: 'Mokkel',
    description: 'A nimble, efficient 0.5B model. Perfect for quick answers and light tasks on modest hardware.',
    sizeLabel: '0.5B',
    minMemoryGB: 0
  },
  {
    id: 'Llama-3.2-1B-Instruct-q4f16_1-MLC',
    displayName: 'Siyana',
    description: 'A balanced 1B model offering thoughtful responses with a sweet spot of speed and quality.',
    sizeLabel: '1B',
    minMemoryGB: 2
  },
  {
    id: 'Qwen3-1.7B-q4f16_1-MLC',
    displayName: 'Pondit',
    description: 'A capable 1.7B model built on Qwen3. Strong multilingual support, great for thoughtful conversation on any device.',
    sizeLabel: '1.7B',
    minMemoryGB: 2
  },
  {
    id: 'Qwen2.5-3B-Instruct-q4f16_1-MLC',
    displayName: 'Guru',
    description: 'A powerful 3B model for deep reasoning and complex tasks. Best quality when you have the resources.',
    sizeLabel: '3B',
    minMemoryGB: 4
  },
  {
    id: 'Qwen3-4B-q4f16_1-MLC',
    displayName: 'Ostad',
    description: 'A large 4B model for advanced reasoning, complex analysis, and high-quality responses. For desktop and powerful devices.',
    sizeLabel: '4B',
    minMemoryGB: 6
  },
  {
    id: 'Qwen3-8B-q4f16_1-MLC',
    displayName: 'Moha Purus',
    description: 'The mightiest 8B model. Deep reasoning, complex problem solving, and near-cloud quality — entirely local, entirely private.',
    sizeLabel: '8B',
    minMemoryGB: 8
  }
];

function buildSystemPrompt(modelName) {
  const user = getUserProfile();
  let prompt = `You are ${modelName}. Respond directly and conversationally. Answer questions accurately. If asked in a language, reply in that language.

Privacy: Dadai runs 100% locally in the browser — no server, no cloud, no data leaves the device.`;
  if (user.name) {
    prompt += `\n\nThe user's name is ${user.name}.`;
  }
  if (user.about) {
    prompt += `\n\nUser info: ${user.about}`;
  }
  return prompt;
}

const INACTIVITY_DELAY = 25000; // ms before "What are you thinking?" prompt
const LANDING_DELAY = 2000;
const HARDWARE_RESULT_DELAY = 2500;
const STORAGE_KEY_CONVOS = 'dadai_conversations';
const STORAGE_KEY_OFFLINE = 'dadai_strict_offline';
const STORAGE_KEY_HW_CHECKED = 'dadai_hw_checked';
const STORAGE_KEY_CACHED_MODELS = 'dadai_cached_models';
const STORAGE_KEY_USER_PROFILE = 'dadai_user_profile';

// ============================================
// STATE
// ============================================

const state = {
  engine: null,
  messages: [],
  currentConvoId: null,
  conversations: [],
  isGenerating: false,
  stopRequested: false,
  strictOffline: false,
  inactivityTimer: null,
  statusInterval: null,
  loadedModelId: null,
  loadedModelName: '',
  deviceMemory: null,
  hasWebGPU: false,
  userProfile: { name: '', about: '' }
};

// ============================================
// DOM REFS
// ============================================

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const dom = {};

function cacheDom() {
  dom.landing = $('#landing-view');
  dom.hardware = $('#hardware-view');
  dom.model = $('#model-view');
  dom.chat = $('#chat-view');
  dom.ramDisplay = $('#ram-display');
  dom.webgpuDisplay = $('#webgpu-display');
  dom.modelCards = $('#model-cards');
  dom.messagesInner = $('#messages-inner');
  dom.messagesContainer = $('#messages-container');
  dom.chatInput = $('#chat-input');
  dom.sendBtn = $('#send-btn');
  dom.stopBtn = $('#stop-btn');
  dom.chipsBar = $('#chips-bar');
  dom.offlineToggle = $('#offline-toggle');
  dom.offlineWrap = $('.offline-toggle');
  dom.sidebar = $('#sidebar');
  dom.sidebarOverlay = $('#sidebar-overlay');
  dom.sidebarClose = $('#sidebar-close');
  dom.menuBtn = $('#menu-btn');
  dom.newChatBtn = $('#new-chat-btn');
  dom.conversationList = $('#conversation-list');
  dom.loadingOverlay = $('#loading-overlay');
  dom.progressFill = $('#progress-fill');
  dom.progressText = $('#progress-text');
  dom.progressSub = $('#progress-sub');
  dom.headerName = $('#header-name');
  dom.headerStatus = $('#header-status');
  dom.headerRobot = $('#header-robot');
  dom.inactivityPrompt = $('#inactivity-prompt');
  dom.toastContainer = $('#toast-container');
  dom.profileOverlay = $('#profile-overlay');
  dom.profileName = $('#profile-name');
  dom.profileAbout = $('#profile-about');
  dom.profileSaveBtn = $('#profile-save');
  dom.profileCancelBtn = $('#profile-cancel');
  dom.profileBtn = $('#profile-btn');
}

// ============================================
// SPA ROUTER
// ============================================

function showView(viewId) {
  $$('.view').forEach(v => v.classList.remove('active'));
  const el = document.getElementById(viewId);
  if (el) el.classList.add('active');
}

// ============================================
// TOAST NOTIFICATIONS
// ============================================

function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  dom.toastContainer.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(20px)'; el.style.transition = '0.3s ease'; setTimeout(() => el.remove(), 300); }, 3500);
}

// ============================================
// HARDWARE DETECTION
// ============================================

async function detectHardware() {
  let ram = navigator.deviceMemory;
  if (ram === undefined) {
    // Fallback: assume 4GB if we can't detect. On most modern devices this is reasonable.
    ram = 4;
    console.warn('navigator.deviceMemory not available — defaulting to 4GB');
  }
  state.deviceMemory = ram;

  // WebGPU check
  let webgpu = false;
  if (navigator.gpu) {
    try {
      const adapter = await navigator.gpu.requestAdapter();
      webgpu = !!adapter;
    } catch { webgpu = false; }
  }
  state.hasWebGPU = webgpu;

  return { ram, webgpu };
}

function displayHardwareResult({ ram, webgpu }) {
  const ramGB = `${ram} GB`;
  dom.ramDisplay.innerHTML = `<span class="hw-checked">${ramGB}</span>`;
  dom.ramDisplay.classList.add('done');

  const wgpuText = webgpu ? 'Available' : 'Not Available';
  dom.webgpuDisplay.innerHTML = `<span class="hw-checked">${wgpuText}</span>`;
  dom.webgpuDisplay.classList.add('done');

  // Show error if no WebGPU
  if (!webgpu) {
    setTimeout(() => {
      showToast('WebGPU is required for Dadai. Please use Chrome 113+ or Edge 113+.', 'error');
    }, 500);
  }
}

// ============================================
// MODEL MANAGEMENT
// ============================================

function getAvailableModels(ram) {
  return MODELS.filter(m => ram >= m.minMemoryGB);
}

function getRecommendedModel(ram) {
  // Return the highest-capability model that fits
  const available = getAvailableModels(ram);
  return available[available.length - 1] || MODELS[0];
}

function renderModelCards() {
  const ram = state.deviceMemory || 4;
  const available = getAvailableModels(ram);
  const cached = JSON.parse(localStorage.getItem(STORAGE_KEY_CACHED_MODELS) || '[]');

  dom.modelCards.innerHTML = available.map(m => {
    const isRec = m.id === getRecommendedModel(ram).id;
    const isCached = cached.includes(m.id);
    const badgeText = isCached ? 'Downloaded' : (isRec ? 'Recommended' : m.sizeLabel);
    const badgeClass = isCached ? 'cached' : (isRec ? 'recommended' : '');
    return `
      <div class="model-card ${isCached ? 'cached' : ''}" data-model="${m.id}">
        <div class="model-card-top">
          <span class="model-card-name">${m.displayName}</span>
          <span class="model-card-badge ${badgeClass}">${badgeText}</span>
        </div>
        <div class="model-card-desc">${m.description}</div>
        <button class="model-card-btn">${isCached ? 'Load ' : 'Select '}${m.displayName}</button>
      </div>
    `;
  }).join('');

  // Click handlers
  dom.modelCards.querySelectorAll('.model-card').forEach(card => {
    card.addEventListener('click', () => {
      const modelId = card.dataset.model;
      handleModelSelect(modelId);
    });
  });
}

async function handleModelSelect(modelId) {
  // Check strict offline mode
  if (state.strictOffline) {
    const cached = await checkModelInCache(modelId);
    if (!cached) {
      showToast('Strict Offline: Model not cached. Connect to internet for first download.', 'error');
      return;
    }
  }

  // Show loading overlay
  showView('chat-view');
  showLoadingOverlay();

  try {
    await loadModel(modelId);
    hideLoadingOverlay();
    const model = MODELS.find(m => m.id === modelId);
    state.loadedModelId = modelId;
    state.loadedModelName = model ? model.displayName : modelId;
    dom.headerName.textContent = 'DADAI';
    dom.headerStatus.className = 'header-status';
    startStatusCycle(state.loadedModelName);
    setHeaderRobotState('robot-state-idle');
    if (state.conversations.length > 0 && state.conversations[0].messages.length > 0) {
      switchConversation(state.conversations[0].id);
    } else {
      startNewConversation();
    }
  } catch (err) {
    hideLoadingOverlay();
    stopStatusCycle();
    console.error('Model load failed:', err);
    showToast(`Failed to load model: ${err.message || 'Unknown error'}`, 'error');
    dom.headerName.textContent = 'DADAI';
    dom.headerStatus.textContent = 'Ready';
    dom.headerStatus.className = 'header-status';
    showView('model-view');
  }
}

async function checkModelInCache(modelId) {
  try {
    if (typeof hasModelInCache === 'function') {
      return await hasModelInCache(modelId);
    }
  } catch {}
  const cached = localStorage.getItem(STORAGE_KEY_CACHED_MODELS) || '[]';
  return JSON.parse(cached).includes(modelId);
}

function markModelCached(modelId) {
  const cached = JSON.parse(localStorage.getItem(STORAGE_KEY_CACHED_MODELS) || '[]');
  if (!cached.includes(modelId)) {
    cached.push(modelId);
    localStorage.setItem(STORAGE_KEY_CACHED_MODELS, JSON.stringify(cached));
  }
}

// ============================================
// WEBLLM ENGINE
// ============================================

async function loadModel(modelId) {
  const modelObj = MODELS.find(m => m.id === modelId);
  const displayName = modelObj ? modelObj.displayName : modelId;

  dom.progressFill.style.width = '0%';
  dom.progressText.innerHTML = `<span class="load-animate">Waking up <strong>${displayName}</strong>...</span>`;
  dom.progressSub.textContent = 'This may take a few minutes on first load';

  dom.headerName.textContent = 'DADAI';
  dom.headerStatus.textContent = `Loading ${displayName}...`;
  dom.headerStatus.className = 'header-status loading';
  setHeaderRobotState('robot-state-thinking');

  try {
    state.engine = await CreateMLCEngine(modelId, {
      initProgressCallback: (progress) => {
        const pct = typeof progress === 'number' ? progress : (progress.progress || 0);
        const text = typeof progress === 'string' ? progress : (progress.text || 'Loading...');
        const pctDisplay = Math.round(pct * 100);
        dom.progressFill.style.width = `${pctDisplay}%`;
        dom.progressText.innerHTML = `<span class="load-animate">Loading <strong>${displayName}</strong>...</span>`;
        dom.progressSub.textContent = `${pctDisplay}% · ${text}`;
      }
    });

    markModelCached(modelId);
    showToast(`${displayName} is ready!`, 'success');
  } catch (err) {
    throw err;
  }
}

// ============================================
// STATUS CYCLE ANIMATION
// ============================================

function startStatusCycle(modelName) {
  stopStatusCycle();
  const el = dom.headerStatus;
  const words = ['Ready', modelName];
  let idx = 0;
  el.textContent = words[0];
  el.className = 'header-status';
  state.statusInterval = setInterval(() => {
    idx = 1 - idx;
    el.classList.remove('status-in');
    el.style.opacity = '0';
    setTimeout(() => {
      el.textContent = words[idx];
      el.style.opacity = '1';
      el.classList.add('status-in');
    }, 250);
  }, 2800);
}

function stopStatusCycle() {
  if (state.statusInterval) {
    clearInterval(state.statusInterval);
    state.statusInterval = null;
  }
}

function setHeaderRobotState(stateClass) {
  const svg = dom.headerRobot?.querySelector('.msg-robot');
  if (!svg) return;
  svg.classList.remove('robot-state-idle', 'robot-state-thinking', 'robot-state-responding');
  svg.classList.add(stateClass);
}

// ============================================
// CHAT
// ============================================

async function sendMessage(text) {
  if (!text.trim() || state.isGenerating || !state.engine) return;

  const userMsg = text.trim();
  dom.chatInput.value = '';
  autoResizeInput();
  clearInactivityTimer();

  addMessage('user', userMsg);
  state.messages.push({ role: 'user', content: userMsg });
  saveCurrentConversation();

  const thinkingEl = addThinkingIndicator();
  setHeaderRobotState('robot-state-thinking');

  state.isGenerating = true;
  state.stopRequested = false;
  dom.sendBtn.style.display = 'none';
  dom.stopBtn.style.display = 'flex';
  dom.chatInput.disabled = true;

  try {
    const systemPrompt = buildSystemPrompt(state.loadedModelName);
    const history = [
      { role: 'system', content: systemPrompt },
      ...state.messages.map(m => ({ role: m.role, content: m.content }))
    ];

    thinkingEl.remove();
    const streamEl = addMessage('assistant', '', true);
    const streamBubble = streamEl.querySelector('.message-bubble');
    setHeaderRobotState('robot-state-responding');

    console.log('[Dadai] Calling chat.completions.create with', history.length, 'messages');
    const raw = await state.engine.chat.completions.create({
      messages: history,
      stream: true
    });
    const streamType = raw?.constructor?.name || typeof raw;
    console.log('[Dadai] Stream type:', streamType, 'has asyncIterator:', typeof raw?.[Symbol.asyncIterator]);

    let fullContent = '';
    let chunkCount = 0;
    const TIMEOUT = 180000;
    let timedOut = false;
    let timer = setTimeout(() => { timedOut = true; }, TIMEOUT);
    function resetTimer() { clearTimeout(timer); timer = setTimeout(() => { timedOut = true; }, TIMEOUT); }

    function bubbleUpdate(content) {
      if (streamBubble) {
        streamBubble.innerHTML = renderMarkdown(content);
      } else {
        updateStreamingMessage(streamEl, content);
      }
    }

    try {
      if (typeof raw?.[Symbol.asyncIterator] === 'function') {
        console.log('[Dadai] Using async iterator');
        for await (const chunk of raw) {
          if (timedOut) throw new Error('Response timed out (60s)');
          resetTimer();
          if (state.stopRequested) break;
          chunkCount++;
          const choice = chunk?.choices?.[0];
          const text = choice?.delta?.content ?? choice?.text ?? choice?.content ?? '';
          if (chunkCount <= 2) console.log('[Dadai] Chunk', chunkCount, ':', JSON.stringify(chunk).slice(0, 300));
          if (text) {
            fullContent += text;
            bubbleUpdate(fullContent);
          }
        }
      } else if (typeof raw?.[Symbol.iterator] === 'function') {
        console.log('[Dadai] Using sync iterator');
        for (const chunk of raw) {
          if (timedOut) throw new Error('Response timed out (60s)');
          resetTimer();
          if (state.stopRequested) break;
          const choice = chunk?.choices?.[0];
          const text = choice?.delta?.content ?? choice?.text ?? choice?.content ?? '';
          if (text) {
            fullContent += text;
            bubbleUpdate(fullContent);
          }
          chunkCount++;
        }
      } else if (raw?.getReader) {
        console.log('[Dadai] Using ReadableStream reader');
        const reader = raw.getReader();
        const decoder = new TextDecoder();
        let buf = '';
        while (true) {
          if (timedOut) throw new Error('Response timed out (60s)');
          resetTimer();
          if (state.stopRequested) break;
          const { done, value } = await reader.read();
          if (done) break;
          buf += decoder.decode(value, { stream: true });
          const lines = buf.split('\n');
          buf = lines.pop() || '';
          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const data = line.slice(6).trim();
              if (data === '[DONE]') break;
              try {
                const json = JSON.parse(data);
                const text = json?.choices?.[0]?.delta?.content ?? json?.choices?.[0]?.text ?? '';
                if (text) {
                  fullContent += text;
                  bubbleUpdate(fullContent);
                  chunkCount++;
                }
              } catch {}
            }
          }
        }
      } else if (typeof raw?.then === 'function') {
        console.log('[Dadai] Stream is a Promise, awaiting');
        const resolved = await raw;
        console.log('[Dadai] Resolved to:', resolved?.constructor?.name);
        const text = resolved?.choices?.[0]?.message?.content || resolved?.choices?.[0]?.text || JSON.stringify(resolved);
        if (text && text !== '{}') {
          fullContent = text;
          bubbleUpdate(fullContent);
          chunkCount = 1;
        }
      } else {
        console.log('[Dadai] Unknown stream type, logging full object');
        console.log(raw);
        const text = raw?.choices?.[0]?.message?.content || raw?.choices?.[0]?.text || raw?.message?.content || raw?.content || '';
        if (text) {
          fullContent = text;
          bubbleUpdate(fullContent);
          chunkCount = 1;
        } else {
          fullContent = String(raw);
          bubbleUpdate(fullContent);
          chunkCount = 1;
        }
      }
    } finally {
      clearTimeout(timer);
    }

    console.log('[Dadai] Stream done, chunks:', chunkCount, 'chars:', fullContent.length);
    if (chunkCount === 0) console.warn('[Dadai] No chunks — engine may need reset:', state.engine);

    if (streamBubble) {
      streamBubble.innerHTML = renderMarkdown(fullContent || '...');
      delete streamEl.dataset.streaming;
      streamEl.classList.remove('robot-state-responding');
      streamEl.classList.add('robot-state-idle');
      setHeaderRobotState('robot-state-idle');
    } else {
      finalizeStreamingMessage(streamEl, fullContent);
      setHeaderRobotState('robot-state-idle');
    }
    state.messages.push({ role: 'assistant', content: fullContent || '...' });
    saveCurrentConversation();
    startInactivityTimer();

  } catch (err) {
    console.error('[Dadai] Chat error:', err);
    if (err.stack) console.error('[Dadai] Stack:', err.stack);
    thinkingEl.remove();
    setHeaderRobotState('robot-state-idle');
    const errMsg = `I apologize, but I encountered an error: ${err.message || 'Please try again.'}`;
    addMessage('assistant', errMsg);
    state.messages.push({ role: 'assistant', content: errMsg });
    saveCurrentConversation();
    showToast('Generation error', 'error');
  } finally {
    state.isGenerating = false;
    dom.stopBtn.style.display = 'none';
    dom.stopBtn.disabled = false;
    dom.sendBtn.style.display = 'flex';
    dom.chatInput.disabled = false;
    scrollToBottom();
  }
}

function robotSvgSmall(stateClass = '') {
  return `<svg class="msg-robot ${stateClass}" viewBox="0 0 24 24" width="20" height="20" fill="none">
    <line x1="12" y1="4" x2="12" y2="1" stroke="#00d4ff" stroke-width="1.2" stroke-linecap="round" class="robot-antenna"/>
    <circle cx="12" cy="1" r="1.5" fill="#00d4ff" class="robot-antenna-ball"/>
    <rect x="4" y="4" width="16" height="15" rx="4" stroke="#00d4ff" stroke-width="1.2" class="robot-head"/>
    <g class="robot-eyes">
      <ellipse cx="9" cy="10.5" rx="2.5" ry="3" stroke="#00d4ff" stroke-width="1" class="robot-eye-l"/>
      <circle cx="9" cy="10.5" r="1.2" fill="#00d4ff" class="robot-pupil-l"/>
      <ellipse cx="15" cy="10.5" rx="2.5" ry="3" stroke="#00d4ff" stroke-width="1" class="robot-eye-r"/>
      <circle cx="15" cy="10.5" r="1.2" fill="#00d4ff" class="robot-pupil-r"/>
    </g>
    <path d="M9 16 Q12 18 15 16" stroke="#00d4ff" stroke-width="1" stroke-linecap="round" class="robot-mouth"/>
  </svg>`;
}

function addMessage(role, content, isStreaming = false) {
  const div = document.createElement('div');
  div.className = `message message-${role === 'user' ? 'user' : 'bot'}`;
  if (isStreaming) {
    div.dataset.streaming = 'true';
    div.classList.add('robot-state-responding');
  } else if (role === 'assistant') {
    div.classList.add('robot-state-idle');
  }

  const avatar = document.createElement('div');
  avatar.className = 'message-avatar';
  if (role === 'assistant') {
    avatar.innerHTML = robotSvgSmall();
  } else {
    avatar.innerHTML = `<svg viewBox="0 0 20 20" width="18" height="18"><circle cx="10" cy="10" r="7" fill="rgba(255,255,255,0.9)"/><path d="M7 8.5c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3zm0 4.5h6c1.66 0 3 1.34 3 3v1H4v-1c0-1.66 1.34-3 3-3z" fill="#0a0a1a"/></svg>`;
  }
  div.appendChild(avatar);

  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.title = 'Click to copy';
  if (content) {
    bubble.innerHTML = content;
  } else if (isStreaming) {
    bubble.textContent = 'thinking.....';
  }
  bubble.addEventListener('click', () => {
    const text = bubble.textContent || '';
    if (!text || text === 'thinking.....') return;
    navigator.clipboard.writeText(text).then(() => showToast('Copied!', 'success')).catch(() => {});
  });
  div.appendChild(bubble);

  if (role === 'user') {
    const resendBtn = document.createElement('button');
    resendBtn.className = 'msg-resend';
    resendBtn.innerHTML = '<svg viewBox="0 0 16 16" width="14" height="14"><path d="M2 8a6 6 0 0 1 10.5-4M14 4v4h-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 8a6 6 0 0 1-10.5 4M2 12v-4h4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    resendBtn.title = 'Re-send';
    resendBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const raw = content || bubble.textContent || '';
      if (raw.trim()) sendMessage(raw);
    });
    div.appendChild(resendBtn);
  }

  dom.messagesInner.appendChild(div);
  scrollToBottom();
  return div;
}

function addThinkingIndicator() {
  const div = document.createElement('div');
  div.className = 'message message-bot thinking-message robot-state-thinking';
  div.id = 'thinking-indicator';
  const avatar = document.createElement('div');
  avatar.className = 'message-avatar';
  avatar.innerHTML = robotSvgSmall('robot-state-thinking');
  div.appendChild(avatar);
  const bubble = document.createElement('div');
  bubble.className = 'message-bubble';
  bubble.innerHTML = '<div class="thinking-dots"><span></span><span></span><span></span></div>';
  div.appendChild(bubble);
  dom.messagesInner.appendChild(div);
  scrollToBottom();
  return div;
}

function updateStreamingMessage(el, content) {
  const bubble = el.querySelector('.message-bubble');
  const parentCount = dom.messagesInner.children.length;
  if (bubble) {
    const html = renderMarkdown(content);
    bubble.innerHTML = html;
    console.log('[Dadai] updateStreamingMessage - bubble found, parent has', parentCount, 'children, html length:', html.length, 'text:', content);
  } else {
    console.warn('[Dadai] updateStreamingMessage - bubble NOT FOUND. el classes:', el.className, 'children:', el.children.length);
  }
  scrollToBottom();
}

function finalizeStreamingMessage(el, content) {
  const bubble = el.querySelector('.message-bubble');
  if (bubble) {
    bubble.innerHTML = renderMarkdown(content);
  } else {
    console.warn('[Dadai] finalize - bubble missing, recreating');
    const newBubble = document.createElement('div');
    newBubble.className = 'message-bubble';
    newBubble.innerHTML = renderMarkdown(content || '...');
    el.appendChild(newBubble);
  }
  delete el.dataset.streaming;
  el.classList.remove('robot-state-responding');
  el.classList.add('robot-state-idle');
}

function renderMarkdown(text) {
  // Simple markdown-like rendering (no dependencies)
  let html = text
    // Code blocks
    .replace(/```(\w*)\n([\s\S]*?)```/g, (_, lang, code) => {
      return `<pre><code>${escapeHtml(code)}</code></pre>`;
    })
    // Inline code
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    // Bold
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    // Italic
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    // Links
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
    // Newlines to paragraphs
    .split('\n\n').map(p => {
      const trimmed = p.trim();
      if (!trimmed) return '';
      // Check if it's already wrapped in a block tag
      if (/^<(pre|code|ul|ol|h[1-6]|blockquote)/i.test(trimmed)) return trimmed;
      // Single newlines within a paragraph become <br>
      const lines = trimmed.split('\n').map(l => l.trim() ? (l.trim() + '<br>') : '').join('');
      return `<p>${lines.replace(/<br>$/, '')}</p>`;
    }).join('\n');

  return html;
}

function escapeHtml(str) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return str.replace(/[&<>"']/g, c => map[c]);
}

function scrollToBottom() {
  requestAnimationFrame(() => {
    dom.messagesContainer.scrollTop = dom.messagesContainer.scrollHeight;
  });
}

function autoResizeInput() {
  dom.chatInput.style.height = 'auto';
  dom.chatInput.style.height = Math.min(dom.chatInput.scrollHeight, 150) + 'px';
}

// ============================================
// HELPERS
// ============================================

function formatTime(ts) {
  const diff = Date.now() - ts;
  if (diff < 60000) return 'Just now';
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
  if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;
  return new Date(ts).toLocaleDateString();
}

// ============================================
// USER PROFILE
// ============================================

function getUserProfile() {
  if (state.userProfile.name || state.userProfile.about) {
    return state.userProfile;
  }
  try {
    const data = localStorage.getItem(STORAGE_KEY_USER_PROFILE);
    if (data) {
      const parsed = JSON.parse(data);
      state.userProfile = { name: parsed.name || '', about: parsed.about || '' };
      return state.userProfile;
    }
  } catch {}
  return state.userProfile;
}

function saveUserProfile() {
  try {
    localStorage.setItem(STORAGE_KEY_USER_PROFILE, JSON.stringify(state.userProfile));
  } catch {}
}

function openProfileSettings() {
  dom.profileName.value = state.userProfile.name;
  dom.profileAbout.value = state.userProfile.about;
  dom.profileOverlay.classList.add('active');
}

function closeProfileSettings() {
  dom.profileOverlay.classList.remove('active');
}

function saveProfileSettings() {
  state.userProfile.name = dom.profileName.value.trim();
  state.userProfile.about = dom.profileAbout.value.trim();
  saveUserProfile();
  showToast('Profile saved', 'success');
  closeProfileSettings();
}

// ============================================
// INACTIVITY TIMER
// ============================================

function startInactivityTimer() {
  clearInactivityTimer();
  state.inactivityTimer = setTimeout(() => {
    // Show the prompt only if not already generating and there's been at least one exchange
    if (!state.isGenerating && state.messages.length > 0) {
      dom.inactivityPrompt.classList.add('show');
      setTimeout(() => dom.inactivityPrompt.classList.remove('show'), 3000);
    }
  }, INACTIVITY_DELAY);
}

function clearInactivityTimer() {
  if (state.inactivityTimer) {
    clearTimeout(state.inactivityTimer);
    state.inactivityTimer = null;
  }
  dom.inactivityPrompt.classList.remove('show');
}

// ============================================
// CONVERSATION MANAGEMENT
// ============================================

function loadConversations() {
  try {
    const data = localStorage.getItem(STORAGE_KEY_CONVOS);
    state.conversations = data ? JSON.parse(data) : [];
  } catch { state.conversations = []; }
}

function saveConversations() {
  try {
    localStorage.setItem(STORAGE_KEY_CONVOS, JSON.stringify(state.conversations));
  } catch {}
}

function saveCurrentConversation() {
  const convo = state.conversations.find(c => c.id === state.currentConvoId);
  if (convo) {
    convo.messages = JSON.parse(JSON.stringify(state.messages));
    convo.updatedAt = Date.now();
    const firstUser = state.messages.find(m => m.role === 'user');
    if (firstUser) {
      convo.title = firstUser.content.slice(0, 60);
    }
    saveConversations();
    renderConversationList();
  }
}

function startNewConversation() {
  // Save current if exists
  if (state.currentConvoId) {
    saveCurrentConversation();
  }

  const convo = {
    id: Date.now().toString(36),
    title: 'New conversation',
    messages: [],
    model: state.loadedModelId,
    createdAt: Date.now(),
    updatedAt: Date.now()
  };

  state.conversations.unshift(convo);
  state.currentConvoId = convo.id;
  state.messages = [];

  // Clear chat
  dom.messagesInner.innerHTML = '';

  const modelName = state.loadedModelName || 'Dadai';
  const userName = state.userProfile.name ? `, ${state.userProfile.name}` : '';
  const welcomeText = `Hello${userName}! I'm **${modelName}**. How can I help you today?`;
  addMessage('assistant', renderMarkdown(welcomeText));
  state.messages.push({ role: 'assistant', content: welcomeText });

  saveCurrentConversation();
  renderConversationList();
  startInactivityTimer();
}

function switchConversation(convoId) {
  if (state.isGenerating) return;

  saveCurrentConversation();
  clearInactivityTimer();

  const convo = state.conversations.find(c => c.id === convoId);
  if (!convo) return;

  state.currentConvoId = convoId;
  state.messages = JSON.parse(JSON.stringify(convo.messages));

  // Re-render messages
  dom.messagesInner.innerHTML = '';
  state.messages.forEach(m => {
    addMessage(m.role, renderMarkdown(m.content));
  });

  if (state.messages.length === 0) {
    const modelName = state.loadedModelName || 'Dadai';
    const userName = state.userProfile.name ? `, ${state.userProfile.name}` : '';
    const welcomeText = `Hello${userName}! I'm **${modelName}**. How can I help you today?`;
    addMessage('assistant', renderMarkdown(welcomeText));
    state.messages.push({ role: 'assistant', content: welcomeText });
    saveCurrentConversation();
  }

  closeSidebar();
  startInactivityTimer();
}

function deleteConversation(convoId, e) {
  e.stopPropagation();
  state.conversations = state.conversations.filter(c => c.id !== convoId);
  saveConversations();
  renderConversationList();

  if (state.currentConvoId === convoId) {
    if (state.conversations.length > 0) {
      switchConversation(state.conversations[0].id);
    } else {
      startNewConversation();
    }
  }
}

function renderConversationList() {
  if (state.conversations.length === 0) {
    dom.conversationList.innerHTML = '<div class="sidebar-empty">No conversations yet</div>';
    return;
  }

  dom.conversationList.innerHTML = state.conversations.map(c => {
    const isActive = c.id === state.currentConvoId;
    const title = c.title || 'New conversation';
    const date = formatTime(c.updatedAt || c.createdAt);
    return `
      <div class="convo-item ${isActive ? 'active' : ''}" data-id="${c.id}">
        <div class="convo-item-title">${escapeHtml(title)}</div>
        <div class="convo-item-meta">${date} · ${c.messages.length} messages</div>
      </div>
    `;
  }).join('');

  dom.conversationList.querySelectorAll('.convo-item').forEach(item => {
    item.addEventListener('click', () => switchConversation(item.dataset.id));
  });
}

// ============================================
// SIDEBAR
// ============================================

function openSidebar() {
  dom.sidebar.classList.add('open');
  dom.sidebarOverlay.classList.add('show');
  renderConversationList();
}

function closeSidebar() {
  dom.sidebar.classList.remove('open');
  dom.sidebarOverlay.classList.remove('show');
}

// ============================================
// LOADING OVERLAY
// ============================================

function showLoadingOverlay() {
  dom.loadingOverlay.classList.add('active');
}

function hideLoadingOverlay() {
  dom.loadingOverlay.classList.remove('active');
}

// ============================================
// STRICT OFFLINE MODE
// ============================================

function initOfflineMode() {
  const saved = localStorage.getItem(STORAGE_KEY_OFFLINE);
  state.strictOffline = saved === 'true';
  dom.offlineToggle.checked = state.strictOffline;
  dom.offlineWrap.classList.toggle('offline', state.strictOffline);

  dom.offlineToggle.addEventListener('change', () => {
    state.strictOffline = dom.offlineToggle.checked;
    localStorage.setItem(STORAGE_KEY_OFFLINE, state.strictOffline);
    dom.offlineWrap.classList.toggle('offline', state.strictOffline);
  });
}

// ============================================
// APP FLOW
// ============================================

async function startApp() {
  loadConversations();
  getUserProfile();
  initOfflineMode();

  // Check if hardware check was done before
  const hwChecked = localStorage.getItem(STORAGE_KEY_HW_CHECKED);

  if (hwChecked) {
    // Restore state and go straight to model selection
    state.deviceMemory = parseFloat(localStorage.getItem('dadai_ram') || '4');
    state.hasWebGPU = localStorage.getItem('dadai_webgpu') === 'true';
    goToModelSelection();
  } else {
    // Landing → Hardware → Models
    showView('landing-view');
    setTimeout(() => {
      showView('hardware-view');
      runHardwareCheck();
    }, LANDING_DELAY);
  }

  // Register service worker
  registerSW();
}

async function runHardwareCheck() {
  const result = await detectHardware();

  // Animate the scan circle
  const scanCircle = document.getElementById('scan-circle');
  if (scanCircle) {
    scanCircle.style.strokeDashoffset = '0';
    scanCircle.style.transition = 'stroke-dashoffset 2s ease-in-out';
  }

  setTimeout(() => {
    displayHardwareResult(result);

    // Persist
    localStorage.setItem(STORAGE_KEY_HW_CHECKED, 'true');
    localStorage.setItem('dadai_ram', String(result.ram));
    localStorage.setItem('dadai_webgpu', String(result.webgpu));

    setTimeout(() => {
      goToModelSelection();
    }, HARDWARE_RESULT_DELAY);
  }, 2000);
}

function goToModelSelection() {
  renderModelCards();
  showView('model-view');
}

// ============================================
// PWA - SERVICE WORKER
// ============================================

function registerSW() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js').catch(err => {
      console.warn('SW registration failed:', err);
    });
  }
}

// ============================================
// EVENT BINDING
// ============================================

function bindEvents() {
  // Send message
  dom.sendBtn.addEventListener('click', () => {
    if (dom.chatInput.value.trim()) sendMessage(dom.chatInput.value);
  });
  dom.stopBtn.addEventListener('click', () => {
    state.stopRequested = true;
    dom.stopBtn.disabled = true;
  });

  dom.chatInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (dom.chatInput.value.trim()) sendMessage(dom.chatInput.value);
    }
  });

  dom.chatInput.addEventListener('input', autoResizeInput);

  // Action chips
  dom.chipsBar.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    if (chip.dataset.action === 'copy-convo') {
      const text = state.messages.map(m => `${m.role === 'user' ? 'You' : 'AI'}: ${m.content}`).join('\n\n');
      navigator.clipboard.writeText(text).then(() => showToast('Conversation copied!', 'success')).catch(() => {
        dom.chatInput.value = text;
        autoResizeInput();
        showToast('Conversation pasted to input', 'info');
      });
      return;
    }
    if (chip.dataset.prompt) {
      if (state.isGenerating) return;
      dom.chatInput.value = chip.dataset.prompt;
      autoResizeInput();
      sendMessage(chip.dataset.prompt);
    }
  });

  // Sidebar
  dom.menuBtn.addEventListener('click', openSidebar);
  dom.sidebarClose.addEventListener('click', closeSidebar);
  dom.sidebarOverlay.addEventListener('click', closeSidebar);

  // New chat
  dom.newChatBtn.addEventListener('click', () => {
    if (!state.engine) {
      showToast('Please select a model first', 'info');
      return;
    }
    startNewConversation();
  });

  // Close inactivity prompt on interaction
  dom.chatInput.addEventListener('focus', () => {
    dom.inactivityPrompt.classList.remove('show');
  });

  // Profile
  dom.profileBtn.addEventListener('click', openProfileSettings);
  dom.profileSaveBtn.addEventListener('click', saveProfileSettings);
  dom.profileCancelBtn.addEventListener('click', closeProfileSettings);
  dom.profileOverlay.addEventListener('click', (e) => {
    if (e.target === dom.profileOverlay) closeProfileSettings();
  });
  dom.profileAbout.addEventListener('input', () => {
    const el = document.getElementById('profile-count');
    if (el) el.textContent = `${dom.profileAbout.value.length} / 500`;
  });
}

function init() {
  cacheDom();
  bindEvents();
  startApp();
}

// ============================================
// BOOT
// ============================================

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
