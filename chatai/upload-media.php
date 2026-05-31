<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Upload Media - Gemini</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gemini-blue: #4285f4;
            --gemini-bg: #0f0f10;
            --gemini-card: rgba(255, 255, 255, 0.04);
            --gemini-text: #e8eaed;
            --gemini-dim: #9aa0a6;
            --glass-border: rgba(255, 255, 255, 0.08);
            --success-green: #34a853;
            --error-red: #ea4335;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
        
        body { 
            background: var(--gemini-bg); 
            color: var(--gemini-text); 
            font-family: 'Google Sans', sans-serif; 
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 16px 40px;
        }

        /* Header Styles */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(15, 15, 16, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 12px 16px;
            margin: 0 -16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            font-size: 18px;
            font-weight: 500;
        }
        .header h1 span { color: var(--gemini-blue); }
        
        .header-btn {
            background: var(--gemini-card);
            border: 1px solid var(--glass-border);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-size: 20px;
        }
        .header-btn:active { background: rgba(255,255,255,0.1); transform: scale(0.95); }

        /* Quick Action Toolbar */
        .toolbar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .tool-item {
            background: var(--gemini-card);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 20px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tool-item:active { background: rgba(255,255,255,0.08); transform: scale(0.96); }
        .tool-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(66, 133, 244, 0.1);
            color: var(--gemini-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: 0.3s;
        }
        .tool-item:hover .tool-icon { background: var(--gemini-blue); color: #fff; }
        .tool-label { font-size: 12px; font-weight: 500; color: var(--gemini-dim); }

        /* Upload Area */
        .upload-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .drop-zone {
            border: 2px dashed var(--glass-border);
            border-radius: 24px;
            padding: 40px 20px;
            text-align: center;
            background: var(--gemini-card);
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        .drop-zone.dragover {
            border-color: var(--gemini-blue);
            background: rgba(66, 133, 244, 0.05);
        }
        .drop-zone.active {
            border-style: solid;
            border-color: var(--gemini-blue);
        }

        .dz-icon { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.8; }
        .dz-text { font-size: 16px; font-weight: 500; margin-bottom: 4px; }
        .dz-hint { font-size: 12px; color: var(--gemini-dim); line-height: 1.5; }

        /* File Preview Card */
        .preview-card {
            display: none;
            background: var(--gemini-card);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 16px;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .preview-media-wrapper {
            width: 100%;
            border-radius: 16px;
            background: #000;
            overflow: hidden;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        .preview-media-wrapper img, .preview-media-wrapper video {
            max-width: 100%;
            max-height: 300px;
            display: block;
        }
        .preview-media-wrapper audio { width: 90%; margin: 20px 0; }
        .preview-placeholder { font-size: 48px; }

        .file-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 4px 8px;
        }
        .file-name {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 70%;
        }
        .file-size { font-size: 12px; color: var(--gemini-dim); }

        /* Progress Styles */
        .progress-section {
            display: none;
            margin-top: auto;
            padding: 20px 0;
        }
        .progress-track {
            height: 6px;
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--gemini-blue), #9b72cb);
            border-radius: 3px;
            transition: width 0.3s ease-out;
            box-shadow: 0 0 10px rgba(66, 133, 244, 0.4);
        }
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
        }
        .progress-label { color: var(--gemini-dim); }
        .progress-value { color: #fff; font-family: monospace; }

        /* Buttons */
        .action-btn {
            display: none;
            width: 100%;
            padding: 16px;
            border-radius: 16px;
            border: none;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 24px;
        }
        .btn-primary {
            background: var(--gemini-blue);
            color: #fff;
            box-shadow: 0 4px 15px rgba(66, 133, 244, 0.3);
        }
        .btn-primary:active { transform: scale(0.98); opacity: 0.9; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Feedback */
        .feedback {
            display: none;
            padding: 16px;
            border-radius: 16px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            margin-top: 20px;
            border: 1px solid transparent;
        }
        .feedback.success { background: rgba(52, 168, 83, 0.1); color: var(--success-green); border-color: rgba(52, 168, 83, 0.2); }
        .feedback.error { background: rgba(234, 67, 53, 0.1); color: var(--error-red); border-color: rgba(234, 67, 53, 0.2); }

        .hidden { display: none !important; }
    </style>
</head>
<body>
    <header class="header">
        <a href="index.php" class="header-btn">☰</a>
        <h1>Gemini <span>Upload</span></h1>
        <a href="call.php" class="header-btn">📞</a>
    </header>

    <div class="container">
        <!-- Modern Toolbar -->
        <div class="toolbar">
            <div class="tool-item" onclick="triggerCamera()">
                <div class="tool-icon">📸</div>
                <div class="tool-label">Camera</div>
            </div>
            <div class="tool-item" onclick="triggerGallery()">
                <div class="tool-icon">🖼️</div>
                <div class="tool-label">Gallery</div>
            </div>
            <div class="tool-item" onclick="triggerVideo()">
                <div class="tool-icon">🎥</div>
                <div class="tool-label">Video</div>
            </div>
        </div>

        <div class="upload-area">
            <!-- Drop Zone -->
            <div class="drop-zone" id="drop-zone" onclick="triggerGallery()">
                <span class="dz-icon">📎</span>
                <div class="dz-text">Choose Media</div>
                <p class="dz-hint">Select high-quality photos, 4K videos,<br>or high-fidelity audio files.</p>
                <input type="file" id="file-input" accept="image/*,video/*,audio/*" class="hidden">
                <input type="file" id="camera-input" accept="image/*" capture="environment" class="hidden">
                <input type="file" id="video-input" accept="video/*" capture class="hidden">
            </div>

            <!-- Preview Card -->
            <div class="preview-card" id="preview-card">
                <div class="preview-media-wrapper" id="preview-media"></div>
                <div class="file-meta">
                    <span class="file-name" id="file-name">-</span>
                    <span class="file-size" id="file-size">-</span>
                </div>
            </div>

            <!-- Upload Feedback -->
            <div class="feedback" id="feedback-msg"></div>

            <!-- Progress Section -->
            <div class="progress-section" id="progress-section">
                <div class="progress-track">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-info">
                    <span class="progress-label" id="progress-label">Syncing...</span>
                    <span class="progress-value" id="progress-value">0%</span>
                </div>
            </div>

            <!-- Main CTA -->
            <button class="action-btn btn-primary" id="btn-upload" onclick="startUpload()">Upload to Secure Vault</button>

            <!-- Standalone VCall Shortcut -->
            <div style="margin-top: 20px; text-align: center; border-top: 1px solid var(--glass-border); padding-top: 24px;">
                <p style="font-size: 12px; color: var(--gemini-dim); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">External Communication</p>
                <a href="vcall.php" style="display: block; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); color: #fff; padding: 16px; border-radius: 16px; text-decoration: none; font-size: 15px; font-weight: 600; transition: 0.3s;" onmouseover="this.style.borderColor='var(--gemini-blue)'" onmouseout="this.style.borderColor='var(--glass-border)'">
                    <span style="margin-right: 8px;">🚀</span> Launch VCall Standalone
                </a>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

        // Elements
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const cameraInput = document.getElementById('camera-input');
        const videoInput = document.getElementById('video-input');
        const previewCard = document.getElementById('preview-card');
        const previewMedia = document.getElementById('preview-media');
        const fileNameEl = document.getElementById('file-name');
        const fileSizeEl = document.getElementById('file-size');
        const btnUpload = document.getElementById('btn-upload');
        const progressSection = document.getElementById('progress-section');
        const progressFill = document.getElementById('progress-fill');
        const progressValue = document.getElementById('progress-value');
        const progressLabel = document.getElementById('progress-label');
        const feedbackMsg = document.getElementById('feedback-msg');

        let selectedFile = null;

        function triggerCamera() { cameraInput.click(); }
        function triggerGallery() { fileInput.click(); }
        function triggerVideo() { videoInput.click(); }

        [fileInput, cameraInput, videoInput].forEach(input => {
            input.onchange = () => { if (input.files.length > 0) handleFileSelect(input.files[0]); };
        });

        // Drag & Drop
        dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('dragover'); };
        dropZone.ondragleave = () => dropZone.classList.remove('dragover');
        dropZone.ondrop = (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files[0]);
        };

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function handleFileSelect(file) {
            selectedFile = file;
            fileNameEl.textContent = file.name;
            fileSizeEl.textContent = formatFileSize(file.size);
            
            // UI state
            dropZone.classList.add('active');
            previewCard.style.display = 'block';
            btnUpload.style.display = 'block';
            btnUpload.disabled = false;
            btnUpload.textContent = 'Upload to Secure Vault';
            feedbackMsg.style.display = 'none';
            progressSection.style.display = 'none';

            // Preview logic
            previewMedia.innerHTML = '';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                previewMedia.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                const vid = document.createElement('video');
                vid.src = URL.createObjectURL(file);
                vid.controls = true;
                previewMedia.appendChild(vid);
            } else if (file.type.startsWith('audio/')) {
                const aud = document.createElement('audio');
                aud.src = URL.createObjectURL(file);
                aud.controls = true;
                previewMedia.appendChild(aud);
            } else {
                const icon = document.createElement('div');
                icon.className = 'preview-placeholder';
                icon.textContent = '📄';
                previewMedia.appendChild(icon);
            }
        }

        function showFeedback(text, type) {
            feedbackMsg.textContent = text;
            feedbackMsg.className = `feedback ${type}`;
            feedbackMsg.style.display = 'block';
        }

        function startUpload() {
            if (!selectedFile) return;

            btnUpload.disabled = true;
            btnUpload.textContent = 'Processing...';
            progressSection.style.display = 'block';
            feedbackMsg.style.display = 'none';

            const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB chunks
            const totalChunks = Math.ceil(selectedFile.size / CHUNK_SIZE);
            const uploadId = Date.now() + '_' + Math.floor(Math.random() * 1000000);
            let currentChunk = 0;

            function uploadNextChunk() {
                const start = currentChunk * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, selectedFile.size);
                const chunk = selectedFile.slice(start, end);

                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('chunkIndex', currentChunk);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('filename', selectedFile.name);
                formData.append('targetDir', 'premium_vault');

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'api.php?action=upload_chunk&_csrf=' + encodeURIComponent(CSRF_TOKEN));

                const percent = Math.round((currentChunk / totalChunks) * 100);
                progressFill.style.width = percent + '%';
                progressValue.textContent = percent + '%';
                progressLabel.textContent = `Syncing Part ${currentChunk + 1}/${totalChunks}...`;

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const resp = JSON.parse(xhr.responseText);
                            if (resp.success) {
                                if (resp.status === 'partial') {
                                    currentChunk++;
                                    uploadNextChunk();
                                } else {
                                    // Complete
                                    progressFill.style.width = '100%';
                                    progressValue.textContent = '100%';
                                    progressLabel.textContent = 'Finalizing...';
                                    
                                    showFeedback('✓ Sync complete. Media moved to Premium Vault.', 'success');
                                    btnUpload.textContent = 'Uploaded Successfully';
                                    
                                    sendChatMessage(selectedFile.name, resp.path);
                                    setTimeout(() => { location.href = 'index.php'; }, 2000);
                                }
                            } else {
                                throw new Error(resp.error || 'Sync failed');
                            }
                        } catch (e) { handleError(e.message); }
                    } else { handleError(`Protocol Error: ${xhr.status}`); }
                };

                xhr.onerror = () => handleError('Network link disrupted');
                xhr.send(formData);
            }

            function handleError(msg) {
                progressSection.style.display = 'none';
                showFeedback(`✗ Error: ${msg}`, 'error');
                btnUpload.disabled = false;
                btnUpload.textContent = 'Retry Upload';
            }

            uploadNextChunk();
        }

        function sendChatMessage(filename, path) {
            const isImage = selectedFile.type.startsWith('image/');
            const isVideo = selectedFile.type.startsWith('video/');
            const isAudio = selectedFile.type.startsWith('audio/');
            
            const chatMsg = `📎 Vault: ${filename}||${path}`;

            fetch('api.php?action=send_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({
                    content: chatMsg,
                    is_image: isImage ? 1 : 0,
                    is_video: isVideo ? 1 : 0,
                    is_voice: isAudio ? 1 : 0
                })
            }).catch(e => console.error('Signal failed', e));
        }
    </script>
</body>
</html>
