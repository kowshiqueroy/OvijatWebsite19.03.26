<?php
/**
 * Photo Upload Page
 * Core PHP Employee Management System
 */

define('IS_ADMIN_PAGE', true);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Photo Upload';
$currentPage = 'photo';

$uploadDir = __DIR__ . '/../uploads/photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_data'])) {
    $photoData = $_POST['photo_data'];
    $customName = sanitize($_POST['photo_name'] ?? '');
    
    if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
        $extension = $matches[1];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array(strtolower($extension), $allowedExtensions)) {
            $photoData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $photoData));
            
            $namePart = !empty($customName) ? sanitize($customName) : date('Ymd_His');
            $newFilename = 'Ovijat_Group_' . $namePart . '_' . time() . '.jpg';
            $targetPath = $uploadDir . $newFilename;
            
            if (file_put_contents($targetPath, $photoData)) {
                $message = 'Photo uploaded successfully!';
            } else {
                $message = 'Failed to save image';
                $messageType = 'danger';
            }
        } else {
            $message = 'Invalid image format';
            $messageType = 'danger';
        }
    } else {
        $message = 'Invalid image data';
        $messageType = 'danger';
    }
}

if (isset($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    $filePath = $uploadDir . $fileToDelete;
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM employees WHERE photo = ?");
    $stmt->bind_param("s", $fileToDelete);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = 'Cannot delete: This photo is connected to an employee';
        $messageType = 'danger';
    } else {
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
            $message = 'Photo deleted successfully';
        }
    }
    $stmt->close();
}

$allPhotos = glob($uploadDir . '*.*');
rsort($allPhotos);
$totalPhotos = count($allPhotos);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Photo Management</h4>
        <small class="text-muted">Upload employee photos</small>
    </div>
</div>

<?php if ($message): ?>
    <?php showAlert($message, $messageType); ?>
<?php endif; ?>

<style>
.crop-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    background: #1a1a1a;
    border-radius: 8px;
    overflow: hidden;
    user-select: none;
    cursor: default;
}
.crop-container img {
    display: block;
    width: 100%;
    height: auto;
    pointer-events: none;
}
#cropArea {
    position: absolute;
    border: 3px solid #fff;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
    cursor: move;
    box-sizing: border-box;
    border-radius: 0;
}
#cropArea::before, #cropArea::after {
    content: '';
    position: absolute;
    background: rgba(255,255,255,0.3);
}
#cropArea::before {
    top: 33.33%; left: 0; right: 0; height: 1px;
}
#cropArea::after {
    top: 0; bottom: 0; left: 33.33%; width: 1px;
}
.handle {
    position: absolute;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    z-index: 10;
}
.handle.tl { top: -10px; left: -10px; cursor: nw-resize; }
.handle.tr { top: -10px; right: -10px; cursor: ne-resize; }
.handle.bl { bottom: -10px; left: -10px; cursor: sw-resize; }
.handle.br { bottom: -10px; right: -10px; cursor: se-resize; }
.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
}
.photo-item {
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 8px;
    position: relative;
    transition: transform 0.2s;
    border: 3px solid transparent;
}
.photo-item:hover { transform: scale(1.02); }
.photo-item.connected { border-color: #0d6efd; }
.photo-item img { width: 100%; height: 100%; object-fit: cover; }
.photo-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(13, 110, 253, 0.9);
    color: white;
    padding: 3px 5px;
    font-size: 8px;
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}
.photo-actions {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}
.photo-item:hover .photo-actions { opacity: 1; }
.photo-item.connected .photo-actions { display: none; }
</style>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Upload Employee Photo</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">1. Select Photo</label>
                    <input type="file" id="photoInput" class="form-control" accept="image/*">
                </div>
                
                <div id="cropSection" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label">2. Adjust 1:1 Crop Area</label>
                        <div class="crop-container" id="cropContainer">
                            <img id="sourceImage" src="">
                            <div id="cropArea">
                                <div class="handle tl" data-handle="tl"></div>
                                <div class="handle tr" data-handle="tr"></div>
                                <div class="handle bl" data-handle="bl"></div>
                                <div class="handle br" data-handle="br"></div>
                            </div>
                        </div>
                        <small class="text-muted">Drag box or corners to adjust</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">3. Photo Name</label>
                        <input type="text" id="photoName" class="form-control" placeholder="Ovijat_Group_[name]_datetime">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Size: <span id="imageSize">0</span> KB</label>
                        <div class="progress" style="height: 18px;">
                            <div id="sizeProgress" class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <button type="button" id="processBtn" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-check2 me-2"></i> Process & Preview
                    </button>
                </div>
                
                <div id="previewSection" class="text-center mb-3" style="display:none;">
                    <label class="form-label">Final Preview</label>
                    <div class="border rounded p-2">
                        <img id="finalPreview" style="max-width: 100%; max-height: 200px; border-radius: 50%;">
                    </div>
                </div>
                
                <button type="button" id="uploadBtn" class="btn btn-primary w-100" disabled>
                    <i class="bi bi-cloud-upload me-2"></i> Upload Photo
                </button>
                
                <form id="uploadForm" method="POST" style="display:none;">
                    <input type="hidden" name="photo_data" id="photoData">
                    <input type="hidden" name="photo_name" id="photoNameField">
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-images me-2"></i>Photos (<?php echo $totalPhotos; ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($allPhotos)): ?>
                    <p class="text-muted text-center py-4">No photos yet</p>
                <?php else: ?>
                    <div class="photo-grid">
                        <?php 
                        $conn = getDBConnection();
                        foreach ($allPhotos as $photo): 
                            $photoName = basename($photo);
                            $empResult = $conn->query("SELECT id, emp_name, office_code, dept_code FROM employees WHERE photo = '" . $conn->real_escape_string($photoName) . "' LIMIT 1");
                            $empInfo = $empResult ? $empResult->fetch_assoc() : null;
                            $isConnected = $empInfo ? true : false;
                            if ($empInfo) {
                                $empID = generateEmployeeID($empInfo['id'], $empInfo['office_code'], $empInfo['dept_code']);
                                $displayLabel = $empID . ' - ' . $empInfo['emp_name'];
                            }
                        ?>
                        <div class="photo-item <?php echo $isConnected ? 'connected' : ''; ?>">
                            <img src="../uploads/photos/<?php echo htmlspecialchars($photoName); ?>" alt="">
                            <?php if ($isConnected): ?>
                                <div class="photo-info"><?php echo htmlspecialchars($displayLabel); ?></div>
                            <?php endif; ?>
                            <div class="photo-actions">
                                <button class="btn btn-sm btn-primary" onclick="copyName('<?php echo htmlspecialchars($photoName); ?>')"><i class="bi bi-clipboard"></i></button>
                                <?php if (!$isConnected): ?>
                                <a href="?delete=<?php echo urlencode($photoName); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const MAX_SIZE_KB = 500;
const MAX_SIZE_BYTES = MAX_SIZE_KB * 1024;

let srcImg = null;
let naturalWidth = 0, naturalHeight = 0;
let cropData = { x:0, y:0, w:100, h:100 };
let dragging = false, resizing = false, handleType = '';
let startX = 0, startY = 0, startCrop = {};

document.getElementById('photoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        srcImg = new Image();
        srcImg.onload = function() {
            setupCropper();
        };
        srcImg.src = ev.target.result;
    };
    reader.readAsDataURL(file);
});

function setupCropper() {
    document.getElementById('cropSection').style.display = 'block';
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('uploadBtn').disabled = true;
    document.getElementById('photoName').value = '';
    
    const img = document.getElementById('sourceImage');
    img.src = srcImg.src;
    
    naturalWidth = srcImg.naturalWidth;
    naturalHeight = srcImg.naturalHeight;
    
    const size = Math.min(naturalWidth, naturalHeight) * 0.7;
    cropData = {
        x: (naturalWidth - size) / 2,
        y: (naturalHeight - size) / 2,
        w: size,
        h: size
    };
    
    updateCropDisplay();
}

function updateCropDisplay() {
    const img = document.getElementById('sourceImage');
    const container = document.getElementById('cropContainer');
    const displayWidth = img.offsetWidth;
    const displayHeight = img.offsetHeight;
    
    const scaleX = displayWidth / naturalWidth;
    const scaleY = displayHeight / naturalHeight;
    
    const area = document.getElementById('cropArea');
    area.style.left = (cropData.x * scaleX) + 'px';
    area.style.top = (cropData.y * scaleY) + 'px';
    area.style.width = (cropData.w * scaleX) + 'px';
    area.style.height = (cropData.h * scaleY) + 'px';
}

const cropArea = document.getElementById('cropArea');

cropArea.addEventListener('mousedown', function(e) {
    if (e.target.classList.contains('handle')) {
        resizing = true;
        handleType = e.target.dataset.handle;
    } else {
        dragging = true;
    }
    startX = e.clientX;
    startY = e.clientY;
    startCrop = { ...cropData };
    e.preventDefault();
    e.stopPropagation();
});

document.addEventListener('mousemove', function(e) {
    if (!dragging && !resizing) return;
    
    const img = document.getElementById('sourceImage');
    const rect = img.getBoundingClientRect();
    const scaleX = naturalWidth / rect.width;
    const scaleY = naturalHeight / rect.height;
    
    const dx = (e.clientX - startX) * scaleX;
    const dy = (e.clientY - startY) * scaleY;
    
    if (dragging) {
        cropData.x = Math.max(0, Math.min(startCrop.x + dx, naturalWidth - cropData.w));
        cropData.y = Math.max(0, Math.min(startCrop.y + dy, naturalHeight - cropData.h));
    }
    else if (resizing) {
        let delta = 0;
        
        if (handleType === 'br') delta = Math.max(dx, dy);
        else if (handleType === 'bl') delta = Math.max(-dx, dy);
        else if (handleType === 'tr') delta = Math.max(dx, -dy);
        else if (handleType === 'tl') delta = Math.max(-dx, -dy);
        
        let newSize = Math.max(50, Math.min(startCrop.w + delta, naturalWidth, naturalHeight));
        let newX = startCrop.x, newY = startCrop.y;
        
        if (handleType === 'tl' || handleType === 'bl') newX = startCrop.x + startCrop.w - newSize;
        if (handleType === 'tl' || handleType === 'tr') newY = startCrop.y + startCrop.h - newSize;
        
        newX = Math.max(0, newX);
        newY = Math.max(0, newY);
        if (newX + newSize > naturalWidth) newSize = naturalWidth - newX;
        if (newY + newSize > naturalHeight) newSize = naturalHeight - newY;
        
        cropData.x = newX;
        cropData.y = newY;
        cropData.w = newSize;
        cropData.h = newSize;
    }
    
    updateCropDisplay();
});

document.addEventListener('mouseup', function() {
    dragging = false;
    resizing = false;
    handleType = '';
});

window.addEventListener('resize', updateCropDisplay);

document.getElementById('processBtn').addEventListener('click', function() {
    if (!srcImg) return;
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    const outputSize = 1200;
    canvas.width = outputSize;
    canvas.height = outputSize;
    
    ctx.drawImage(
        srcImg,
        cropData.x, cropData.y, cropData.w, cropData.h,
        0, 0, outputSize, outputSize
    );
    
    let quality = 0.92;
    let dataUrl = canvas.toDataURL('image/jpeg', quality);
    
    while (dataUrl.length > MAX_SIZE_BYTES * 1.37 && quality > 0.1) {
        quality -= 0.05;
        dataUrl = canvas.toDataURL('image/jpeg', quality);
    }
    
    document.getElementById('finalPreview').src = dataUrl;
    document.getElementById('previewSection').style.display = 'block';
    
    const sizeKB = Math.round(dataUrl.length / 1024);
    document.getElementById('imageSize').textContent = sizeKB;
    document.getElementById('sizeProgress').style.width = (sizeKB/MAX_SIZE_KB*100) + '%';
    document.getElementById('sizeProgress').className = 'progress-bar ' + (sizeKB>MAX_SIZE_KB?'bg-danger':'bg-success');
    document.getElementById('uploadBtn').disabled = false;
});

document.getElementById('uploadBtn').addEventListener('click', function() {
    const dataUrl = document.getElementById('finalPreview').src;
    document.getElementById('photoData').value = dataUrl;
    document.getElementById('photoNameField').value = document.getElementById('photoName').value;
    document.getElementById('uploadForm').submit();
});

function copyName(n) { navigator.clipboard.writeText(n); alert('Copied: '+n); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
