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
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin:0; padding:0; background: #0f0f0f; font-family: 'Roboto', sans-serif; }

        .container {
            max-width: 600px;
            margin: 0 auto;
            min-height: 100vh;
            padding: 0 16px 16px;
        }

        .header {
            display: flex;
            align-items: center;
            padding: 16px 0;
            gap: 12px;
            position: sticky;
            top: 0;
            background: #0f0f0f;
            z-index: 100;
        }
        .back-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }
        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
            flex: 1;
        }
        .header-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .call-btn {
            background: none;
            border: none;
            color: #8ab4f8;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            transition: opacity 0.2s;
        }
        .call-btn:active { opacity: 0.6; }

        .upload-zone {
            border: 2px dashed #333;
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #1a1a1a;
            margin-top: 20px;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #8ab4f8;
            background: rgba(138, 180, 248, 0.05);
        }
        .upload-zone.uploading {
            border-color: #4caf50;
            background: rgba(76, 175, 80, 0.05);
        }
        .upload-icon { font-size: 56px; margin-bottom: 16px; }
        .upload-text {
            font-size: 16px;
            color: #aaa;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .upload-hint {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }

        .file-info {
            display: none;
            margin-top: 20px;
            padding: 16px;
            background: #1a1a1a;
            border-radius: 12px;
        }
        .file-name {
            font-size: 15px;
            color: #fff;
            margin-bottom: 6px;
            word-break: break-all;
            font-weight: 500;
        }
        .file-size {
            font-size: 13px;
            color: #666;
        }

        .media-preview {
            display: none;
            margin-top: 20px;
            text-align: center;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        .media-preview img, .media-preview video {
            max-width: 100%;
            max-height: 50vh;
            object-fit: contain;
        }
        .media-preview audio {
            width: 90%;
            margin: 20px 0;
        }

        .progress-container {
            display: none;
            margin-top: 24px;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #1a1a1a;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #8ab4f8, #4caf50);
            width: 0%;
            transition: width 0.3s;
        }
        .progress-text {
            text-align: center;
            margin-top: 12px;
            font-size: 14px;
            color: #aaa;
            font-weight: 500;
        }

        .success-msg, .error-msg {
            display: none;
            padding: 16px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
            font-weight: 500;
        }
        .success-msg {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .error-msg {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .btn-upload {
            display: none;
            margin-top: 20px;
            width: 100%;
            padding: 16px;
            background: #8ab4f8;
            color: #000;
            border: none;
            border-radius: 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-upload:active { opacity: 0.8; }

        .quick-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .quick-btn {
            flex: 1;
            padding: 14px;
            background: #1a1a1a;
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
        }
        .quick-btn:active { background: #333; }
        .quick-btn span { display: block; font-size: 24px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <button class="back-btn" onclick="location.href='index.php'">☰</button>
            <div class="header-title">Upload Media</div>
            <div class="header-right">
                <button class="call-btn" onclick="location.href='call.php'">📞</button>
            </div>
        </div>

        <div class="quick-actions">
            <button class="quick-btn" onclick="triggerCamera()">
                <span>📷</span> Camera
            </button>
            <button class="quick-btn" onclick="triggerGallery()">
                <span>🖼️</span> Gallery
            </button>
            <button class="quick-btn" onclick="triggerVideo()">
                <span>🎥</span> Video
            </button>
        </div>

        <div class="upload-zone" id="upload-zone">
            <div class="upload-icon">📎</div>
            <div class="upload-text">Tap to select files</div>
            <div class="upload-hint">Supports images, videos, and audio files<br>Up to 512MB per file</div>
            <input type="file" id="file-input" accept="image/*,video/*,audio/*" style="display:none">
            <input type="file" id="camera-input" accept="image/*" capture="environment" style="display:none">
            <input type="file" id="video-input" accept="video/*" capture style="display:none">
        </div>

        <div class="file-info" id="file-info">
            <div class="file-name" id="file-name"></div>
            <div class="file-size" id="file-size"></div>
        </div>

        <div class="media-preview" id="media-preview"></div>

        <button class="btn-upload" id="btn-upload" onclick="startUpload()">Upload to Vault</button>

        <div class="progress-container" id="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text" id="progress-text">Uploading... 0%</div>
        </div>

        <div class="success-msg" id="success-msg"></div>
        <div class="error-msg" id="error-msg"></div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';

        const uploadZone = document.getElementById('upload-zone');
        const fileInput = document.getElementById('file-input');
        const cameraInput = document.getElementById('camera-input');
        const videoInput = document.getElementById('video-input');
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');
        const mediaPreview = document.getElementById('media-preview');
        const btnUpload = document.getElementById('btn-upload');
        const progressContainer = document.getElementById('progress-container');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const successMsg = document.getElementById('success-msg');
        const errorMsg = document.getElementById('error-msg');

        let selectedFile = null;

        function triggerCamera() { cameraInput.click(); }
        function triggerGallery() { fileInput.click(); }
        function triggerVideo() { videoInput.click(); }

        [fileInput, cameraInput, videoInput].forEach(function(input) {
            input.onchange = function() {
                if (input.files.length > 0) handleFileSelect(input.files[0]);
            };
        });

        uploadZone.onclick = function() { fileInput.click(); };

        uploadZone.ondragover = function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        };
        uploadZone.ondragleave = function() { uploadZone.classList.remove('dragover'); };
        uploadZone.ondrop = function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files[0]);
        };

        function handleFileSelect(file) {
            selectedFile = file;
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';

            mediaPreview.innerHTML = '';
            mediaPreview.style.display = 'block';

            if (file.type.startsWith('image/')) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                mediaPreview.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                var vid = document.createElement('video');
                vid.src = URL.createObjectURL(file);
                vid.controls = true;
                mediaPreview.appendChild(vid);
            } else if (file.type.startsWith('audio/')) {
                var aud = document.createElement('audio');
                aud.src = URL.createObjectURL(file);
                aud.controls = true;
                mediaPreview.appendChild(aud);
            }

            btnUpload.style.display = 'block';
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function startUpload() {
            if (!selectedFile) return;

            btnUpload.style.display = 'none';
            progressContainer.style.display = 'block';
            uploadZone.classList.add('uploading');

            var formData = new FormData();
            formData.append('file', selectedFile);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api.php?action=save_image&_csrf=' + encodeURIComponent(CSRF_TOKEN));

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    progressText.textContent = 'Uploading... ' + percent + '%';
                }
            };

            xhr.onload = function() {
                progressContainer.style.display = 'none';
                uploadZone.classList.remove('uploading');

                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        successMsg.textContent = '✓ Media uploaded successfully to Premium Vault!';
                        successMsg.style.display = 'block';
                        sendChatMessage(selectedFile.name);
                        setTimeout(function() { location.href = 'index.php'; }, 2000);
                    } else {
                        errorMsg.textContent = '✗ Upload failed: ' + (resp.error || 'Unknown error');
                        errorMsg.style.display = 'block';
                        btnUpload.style.display = 'block';
                    }
                } catch (e) {
                    errorMsg.textContent = '✗ Upload failed: ' + xhr.responseText;
                    errorMsg.style.display = 'block';
                    btnUpload.style.display = 'block';
                }
            };

            xhr.onerror = function() {
                progressContainer.style.display = 'none';
                uploadZone.classList.remove('uploading');
                errorMsg.textContent = '✗ Network error occurred';
                errorMsg.style.display = 'block';
                btnUpload.style.display = 'block';
            };

            xhr.send(formData);
        }

        function sendChatMessage(filename) {
            var isImage = selectedFile.type.startsWith('image/');
            var isVideo = selectedFile.type.startsWith('video/');
            var isAudio = selectedFile.type.startsWith('audio/');

            var type = 'file';
            if (isImage) type = 'image';
            else if (isVideo) type = 'video';
            else if (isAudio) type = 'voice';

            var msg = '📎 Uploaded ' + type + ': ' + filename;

            fetch('api.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    content: msg,
                    is_image: 0,
                    is_voice: 0,
                    is_video: 0
                })
            }).catch(function(e) {
                console.error('Failed to send chat message', e);
            });
        }
    </script>
</body>
</html>
