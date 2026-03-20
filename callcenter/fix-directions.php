<?php
require_once 'config.php';
requireLogin();
if (!in_array(strtolower(trim($_SESSION['department'] ?? '')), ['it', 'management'])) {
    header('Location: ' . APP_URL . '/dashboard.php?error=unauthorized');
    exit;
}
$aid = agentId();
?>
<?php include 'includes/layout.php'; ?>

<div class="page-header">
    <div>
        <h5 class="page-title"><i class="fas fa-arrows-left-right me-2"></i>Fix Call Directions</h5>
        <p class="page-subtitle text-muted mb-0">Analyze all call records and fix direction based on caller/destination vs agent numbers.</p>
    </div>
    <div>
        <a href="calls.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="cc-card mb-3">
    <div class="cc-card-head"><i class="fas fa-info-circle me-2"></i>Direction Logic</div>
    <div class="cc-card-body small">
        <div class="row">
            <div class="col-md-4">
                <strong>Outbound:</strong> Caller is an agent &amp; destination is NOT an agent number
            </div>
            <div class="col-md-4">
                <strong>Inbound:</strong> Destination is an agent &amp; caller is NOT an agent number (or dst = 800 voicemail)
            </div>
            <div class="col-md-4">
                <strong>Conflict:</strong> Both caller and destination are agent numbers
            </div>
        </div>
        <hr>
        <div class="row small text-muted">
            <div class="col-md-4"><strong>Voicemail:</strong> dst = 800 or 8000 → inbound</div>
            <div class="col-md-4"><strong>PBX trunk:</strong> src or dst = internal PBX prefix → skip</div>
            <div class="col-md-4"><strong>External:</strong> Neither side is an agent → inbound (external call)</div>
        </div>
    </div>
</div>

<?php
$agentNumbers = [];

// Ensure conflict enum value exists in call_direction
$conn->query("ALTER TABLE call_logs MODIFY COLUMN call_direction ENUM('inbound','outbound','internal','unknown','conflict') DEFAULT 'unknown'");
$res = $conn->query("SELECT number FROM agent_numbers");
while ($row = $res->fetch_assoc()) {
    $n = preg_replace('/[^0-9+]/', '', $row['number']);
    if ($n) $agentNumbers[$n] = true;
}
$res2 = $conn->query("SHOW COLUMNS FROM agents LIKE 'extension'");
if ($res2 && $res2->num_rows) {
    $res3 = $conn->query("SELECT extension FROM agents WHERE extension IS NOT NULL AND extension != ''");
    while ($row = $res3->fetch_assoc()) {
        $n = preg_replace('/[^0-9+]/', '', $row['extension']);
        if ($n) $agentNumbers[$n] = true;
    }
}

$total = (int)$conn->query("SELECT COUNT(*) c FROM call_logs")->fetch_assoc()['c'];
$unknownCount = (int)$conn->query("SELECT COUNT(*) c FROM call_logs WHERE call_direction='unknown'")->fetch_assoc()['c'];

function fixNorm(string $s): string { return preg_replace('/[^0-9+]/', '', $s); }

function isAgent(string $num, array $agentNumbers): bool {
    $n = fixNorm($num);
    if (!$n) return false;
    if (isset($agentNumbers[$n])) return true;
    foreach ($agentNumbers as $aNum => $_) {
        if (str_ends_with($n, $aNum) || str_ends_with($aNum, $n)) return true;
    }
    return false;
}
function isPbxInternal(string $num): bool {
    $n = fixNorm($num);
    // Short numbers, internal PBX patterns, conference bridges
    if (strlen($n) <= 4) return true;
    if (in_array($n, ['*97','*98','*99','*0','*1','s','**'])) return true;
    return false;
}

$results = ['inbound'=>0, 'outbound'=>0, 'conflict'=>0, 'skipped'=>0, 'updated'=>0];
$conflictRows = [];
$updated = 0;
$skipped = 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch = (int)($_POST['batch'] ?? 0);
    $offset = (int)($_POST['offset'] ?? 0);
    $limit = 500;
    $agentNumbersJson = $_POST['agent_numbers'] ?? '';

    // Re-hydrate agent numbers from hidden field
    $agentNumbersPost = [];
    foreach (json_decode($agentNumbersJson, true) ?? [] as $n) {
        $nn = preg_replace('/[^0-9+]/', '', $n);
        if ($nn) $agentNumbersPost[$nn] = true;
    }

    $rows = $conn->query("SELECT id, src, dst, call_direction FROM call_logs LIMIT $limit OFFSET $offset");
    $processed = 0;

    while ($row = $rows->fetch_assoc()) {
        $src = trim($row['src'] ?? '');
        $dst = trim($row['dst'] ?? '');
        $srcN = fixNorm($src);
        $dstN = fixNorm($dst);

        // Skip PBX internal numbers
        if (isPbxInternal($src) && isPbxInternal($dst)) {
            $skipped++;
            continue;
        }

        $srcIsAgent = isAgent($src, $agentNumbersPost);
        $dstIsAgent = isAgent($dst, $agentNumbersPost);
        $isVoicemail = ($dstN === '800' || $dstN === '8000');

        if ($isVoicemail) {
            $newDir = 'inbound';
        } elseif ($srcIsAgent && !$dstIsAgent) {
            $newDir = 'outbound';
        } elseif ($dstIsAgent && !$srcIsAgent) {
            $newDir = 'inbound';
        } elseif ($srcIsAgent && $dstIsAgent) {
            $newDir = 'conflict';
        } else {
            // Neither is an agent — external call
            $newDir = 'inbound';
        }

        $conn->query("UPDATE call_logs SET call_direction='$newDir', updated_by=$aid WHERE id={$row['id']}");
        $updated++;
        $processed++;

        if ($newDir === 'conflict') {
            $conflictRows[] = [
                'id' => $row['id'],
                'src' => $src,
                'dst' => $dst,
                'calldate' => '',
            ];
        }
    }

    $remaining = $total - $offset - $processed;
    echo json_encode([
        'updated'=>$updated, 'skipped'=>$skipped, 'processed'=>$processed,
        'remaining'=>$remaining, 'offset'=>$offset + $processed,
        'conflict_count'=>count($conflictRows),
    ]);
    exit;
}

// Show current stats
$byDir = [];
$res = $conn->query("SELECT call_direction, COUNT(*) c FROM call_logs GROUP BY call_direction");
while ($row = $res->fetch_assoc()) $byDir[$row['call_direction']] = (int)$row['c'];
?>

<div class="cc-card mb-3">
    <div class="cc-card-head"><i class="fas fa-chart-bar me-2"></i>Current Direction Breakdown</div>
    <div class="cc-card-body">
        <div class="row text-center">
            <div class="col">
                <div class="h4 mb-0 text-info"><?= number_format($byDir['inbound'] ?? 0) ?></div>
                <small class="text-muted">Inbound</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-primary"><?= number_format($byDir['outbound'] ?? 0) ?></div>
                <small class="text-muted">Outbound</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-warning"><?= number_format($byDir['unknown'] ?? 0) ?></div>
                <small class="text-muted">Unknown</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-danger"><?= number_format($byDir['conflict'] ?? 0) ?></div>
                <small class="text-muted">Conflict</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-secondary"><?= number_format($total) ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
        <div class="mt-3">
            <div class="detail-label mb-1">Agent numbers used:</div>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach (array_keys($agentNumbers) as $n): ?>
                    <span class="badge bg-secondary"><?= e($n) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="cc-card mb-3" id="progressCard" style="display:none">
    <div class="cc-card-head"><i class="fas fa-spinner fa-spin me-2"></i>Processing…</div>
    <div class="cc-card-body">
        <div class="progress mb-2" style="height:24px">
            <div class="progress-bar" id="progBar" style="width:0%"></div>
        </div>
        <div class="small text-muted">
            <span id="progText">Processing…</span>
        </div>
    </div>
</div>

<div id="resultCard" style="display:none" class="cc-card mb-3">
    <div class="cc-card-head"><i class="fas fa-check-circle text-success me-2"></i>Results</div>
    <div class="cc-card-body">
        <div id="resultText" class="small"></div>
    </div>
</div>

<div class="d-flex gap-2 mb-3">
    <button class="btn btn-primary" onclick="runFix()" id="runBtn">
        <i class="fas fa-play me-1"></i>Fix All Directions
    </button>
</div>

<?php $agentNumbersJson = json_encode(array_keys($agentNumbers)); ?>

<script>
const total = <?= $total ?>;
const agentNumbers = <?= $agentNumbersJson ?>;

async function runFix() {
    const btn = document.getElementById('runBtn');
    const progCard = document.getElementById('progressCard');
    const resultCard = document.getElementById('resultCard');
    btn.disabled = true;
    progCard.style.display = 'block';
    resultCard.style.display = 'none';

    let offset = 0;
    let totalUpdated = 0, totalSkipped = 0;
    let conflicts = [];

    while (true) {
        try {
            const r = await fetch(window.location.pathname, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    batch: 1, offset: offset,
                    agent_numbers: JSON.stringify(agentNumbers)
                })
            });
            const text = await r.text();
            let d;
            try { d = JSON.parse(text); } catch { break; }

            totalUpdated += d.updated || 0;
            totalSkipped += d.skipped || 0;

            const pct = Math.round(((offset + d.processed) / total) * 100);
            document.getElementById('progBar').style.width = pct + '%';
            document.getElementById('progText').textContent =
                `Processed ${offset + d.processed} / ${total} (${pct}%) — Updated: ${totalUpdated}, Skipped: ${totalSkipped}`;

            if (d.remaining <= 0) break;
            offset = d.offset;

            // Small delay to not overload server
            await new Promise(r => setTimeout(r, 50));
        } catch(e) {
            showToast('Error: ' + e.message, 'danger');
            break;
        }
    }

    document.getElementById('progBar').style.width = '100%';
    document.getElementById('progText').textContent = 'Done!';

    resultCard.style.display = 'block';
    document.getElementById('resultText').innerHTML =
        `<strong>${totalUpdated} directions fixed</strong> (${totalSkipped} skipped as internal PBX).<br>
         Refresh the page to see updated stats.`;
    btn.disabled = false;
    showToast('Direction fix complete!', 'success');
}
</script>

<?php include 'includes/footer.php'; ?>
