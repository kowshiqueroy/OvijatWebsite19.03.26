<?php
/**
 * Custom Report — shareable via GET link
 * No requireLogin() — auto-loads today's data for the logged-in agent if no params given
 */
require_once 'config.php';

$companyName    = getSetting('company_name', APP_NAME);
$sessionAgentId = agentId();   // 0 if not logged in

// ── All agents for the form ───────────────────────────────────────────────────
$allAgents = $conn->query(
    "SELECT id, full_name, department FROM agents WHERE status='active' ORDER BY full_name"
)->fetch_all(MYSQLI_ASSOC);

// ── Parameters ────────────────────────────────────────────────────────────────
// Auto-generate today's report for the current agent if no date given and user is logged in
$autoLoad   = ($sessionAgentId > 0 && !isset($_GET['date_from']) && !isset($_GET['form']));
$hasReport  = !empty($_GET['date_from']) || $autoLoad;

$dateFrom    = $_GET['date_from']  ?? date('Y-m-d');
$dateTo      = $_GET['date_to']    ?? date('Y-m-d');
$selAgents   = $_GET['agents']     ?? ($sessionAgentId ? [$sessionAgentId] : []);
$filterDisp  = $_GET['disposition'] ?? '';
$filterDir   = $_GET['direction']   ?? '';
$showSum     = isset($_GET['summary'])    || $autoLoad;
$showNotes   = isset($_GET['notes'])      || $autoLoad;
$showReplies = isset($_GET['replies']);
$showTasks   = isset($_GET['tasks'])      || $autoLoad;
$showRec     = isset($_GET['recordings']);
$groupBy     = $_GET['group_by'] ?? 'none';
$sortOrder   = $_GET['sort']     ?? 'desc';

$reportData     = [];
$callIds        = [];
$notesMap       = [];   // call_id => [root note ids]
$noteById       = [];   // id => note_data
$noteChildren   = [];   // parent_id => [child ids]
$tasksMap       = [];
$contactIds     = [];
$contactNotesMap = [];   // contact_id => [root note ids]
$contactNoteById = [];   // id => note_data
$contactNoteChildren = []; // parent_id => [child ids]
$globalSummary  = [];
$agentSummaries = [];

if ($hasReport) {
    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);

    // ── Agent filter ──────────────────────────────────────────────────────────
    $agentAll    = (empty($selAgents) || in_array('all', $selAgents));
    $agentIn     = '';
    $selectedIds = [];
    if (!$agentAll && !empty($selAgents)) {
        $selectedIds = array_map('intval', $selAgents);
        $idList = implode(',', $selectedIds);

        $numRows   = $conn->query("SELECT DISTINCT number FROM agent_numbers WHERE agent_id IN ($idList)");
        $agentNums = [];
        while ($nr = $numRows->fetch_assoc())
            $agentNums[] = "'" . $conn->real_escape_string($nr['number']) . "'";

        if ($agentNums) {
            $numList = implode(',', $agentNums);
            $agentIn = "AND (
                cl.agent_id IN ($idList)
                OR cl.src IN ($numList)
                OR cl.dst IN ($numList)
                OR EXISTS (
                    SELECT 1 FROM contacts ct
                    JOIN agent_numbers an ON an.number = ct.phone
                    WHERE ct.id = cl.contact_id AND an.agent_id IN ($idList)
                )
            )";
        } else {
            $agentIn = "AND cl.agent_id IN ($idList)";
        }
    }

    // ── Common filters ────────────────────────────────────────────────────────
    $dispFilter = $filterDisp ? "AND cl.disposition = '" . $conn->real_escape_string($filterDisp) . "'" : '';
    $dirFilter  = $filterDir  ? "AND cl.call_direction = '" . $conn->real_escape_string($filterDir) . "'" : '';
    $orderDir   = $sortOrder === 'asc' ? 'ASC' : 'DESC';

    // ── Main call query ───────────────────────────────────────────────────────
    $res = $conn->query(
        "SELECT cl.id, cl.calldate, cl.src, cl.dst, cl.clid, cl.cnam,
                cl.duration, cl.billsec, cl.disposition, cl.call_direction,
                cl.call_mark, cl.recordingfile, cl.is_manual, cl.manual_notes,
                cl.contact_id, c.name AS contact_name, c.phone AS contact_phone, c.company,
                a.full_name AS agent_name, a.department AS agent_dept, a.id AS agent_id_col
         FROM call_logs cl
         LEFT JOIN contacts c ON c.id = cl.contact_id
         LEFT JOIN agents   a ON a.id = cl.agent_id
         WHERE DATE(cl.calldate) BETWEEN '$df' AND '$dt'
           $agentIn $dispFilter $dirFilter
         ORDER BY cl.calldate $orderDir
         LIMIT 2000"
    );
    $reportData = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($reportData as $r) {
        $callIds[] = (int)$r['id'];
        if (!empty($r['contact_id'])) $contactIds[] = (int)$r['contact_id'];
    }
    $contactIds = array_unique($contactIds);

    // ── Notes + Replies ───────────────────────────────────────────────────────
    if ($showNotes && $callIds) {
        $idStr = implode(',', $callIds);
        $nr = $conn->query(
            "SELECT cn.id, cn.call_id, cn.parent_id, cn.content, cn.note_type,
                    cn.priority, cn.log_status, cn.created_at, a.full_name AS by_name
             FROM call_notes cn LEFT JOIN agents a ON a.id = cn.agent_id
             WHERE cn.call_id IN ($idStr)
             ORDER BY COALESCE(cn.parent_id, cn.id) ASC, cn.id ASC"
        );
        while ($n = $nr->fetch_assoc()) {
            $noteById[$n['id']] = $n;
            if ($n['parent_id']) {
                $noteChildren[$n['parent_id']][] = $n['id'];
            } else {
                $notesMap[$n['call_id']][] = $n['id'];
            }
        }
    }

    // ── Contact Notes + Replies ──────────────────────────────────────────────
    if ($showNotes && $contactIds) {
        $idStr = implode(',', $contactIds);
        $nr = $conn->query(
            "SELECT cn.id, cn.contact_id, cn.parent_id, cn.content, cn.note_type,
                    cn.priority, cn.log_status, cn.created_at, a.full_name AS by_name
             FROM contact_notes cn LEFT JOIN agents a ON a.id = cn.agent_id
             WHERE cn.contact_id IN ($idStr)
             ORDER BY COALESCE(cn.parent_id, cn.id) ASC, cn.id ASC"
        );
        while ($n = $nr->fetch_assoc()) {
            $contactNoteById[$n['id']] = $n;
            if ($n['parent_id']) {
                $contactNoteChildren[$n['parent_id']][] = $n['id'];
            } else {
                $contactNotesMap[$n['contact_id']][] = $n['id'];
            }
        }
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────
    if ($showTasks && $callIds) {
        $idStr = implode(',', $callIds);
        $tr = $conn->query(
            "SELECT t.call_id, t.title, t.status, t.priority, t.due_date,
                    t.description, a.full_name AS assigned_name
             FROM todos t LEFT JOIN agents a ON a.id = t.assigned_to
             WHERE t.call_id IN ($idStr)
             ORDER BY FIELD(t.priority,'urgent','high','medium','low')"
        );
        while ($t = $tr->fetch_assoc()) $tasksMap[$t['call_id']][] = $t;
    }

    // ── Summary ───────────────────────────────────────────────────────────────
    if ($showSum) {
        $mkSum = function(array $r, string $label, string $dept = ''): array {
            $tot = max(1, (int)$r['total']);
            return [
                'label'      => $label,
                'dept'       => $dept,
                'total'      => (int)$r['total'],
                'answered'   => (int)($r['answered']   ?? 0),
                'no_answer'  => (int)($r['no_answer']  ?? 0),
                'busy'       => (int)($r['busy']       ?? 0),
                'failed'     => (int)($r['failed']     ?? 0),
                'congestion' => (int)($r['congestion'] ?? 0),
                'manual'     => (int)($r['manual']     ?? 0),
                'talk_sec'   => (int)($r['talk_sec']   ?? 0),
                'avg_sec'    => (int)($r['avg_sec']    ?? 0),
                'rate'       => (int)$r['total'] > 0 ? round((int)$r['answered'] / (int)$r['total'] * 100) : 0,
            ];
        };

        // Global stats
        $gr = $conn->query(
            "SELECT COUNT(*) AS total,
                    SUM(disposition='ANSWERED')   AS answered,
                    SUM(disposition='NO ANSWER')  AS no_answer,
                    SUM(disposition='BUSY')       AS busy,
                    SUM(disposition='FAILED')     AS failed,
                    SUM(disposition='CONGESTION') AS congestion,
                    SUM(is_manual) AS manual,
                    SUM(billsec)   AS talk_sec,
                    AVG(billsec)   AS avg_sec
             FROM call_logs cl
             WHERE DATE(calldate) BETWEEN '$df' AND '$dt'
               $dispFilter $dirFilter"
        )->fetch_assoc();
        $globalSummary = $mkSum($gr, 'All Agents — Period Total');

        // Per-agent summaries — LEFT JOIN so agents with zero calls still appear.
        // When specific agents selected show only those; otherwise all active agents.
        $sumAgentWhere = (!$agentAll && $selectedIds)
            ? "a.id IN (" . implode(',', $selectedIds) . ")"
            : "a.status = 'active'";
        // Date/disp/dir go into the ON clause so the LEFT JOIN returns zero-rows for no-call agents
        $joinConds = "AND DATE(cl.calldate) BETWEEN '$df' AND '$dt' $dispFilter $dirFilter";
        $ar = $conn->query(
            "SELECT a.id, a.full_name, a.department,
                    COUNT(cl.id)                                  AS total,
                    COALESCE(SUM(cl.disposition='ANSWERED'),0)    AS answered,
                    COALESCE(SUM(cl.disposition='NO ANSWER'),0)   AS no_answer,
                    COALESCE(SUM(cl.disposition='BUSY'),0)        AS busy,
                    COALESCE(SUM(cl.disposition='FAILED'),0)      AS failed,
                    COALESCE(SUM(cl.disposition='CONGESTION'),0)  AS congestion,
                    COALESCE(SUM(cl.is_manual),0)                 AS manual,
                    COALESCE(SUM(cl.billsec),0)                   AS talk_sec,
                    COALESCE(AVG(NULLIF(cl.billsec,0)),0)         AS avg_sec
             FROM agents a
             LEFT JOIN call_logs cl ON cl.agent_id = a.id $joinConds
             WHERE $sumAgentWhere
             GROUP BY a.id ORDER BY total DESC"
        );
        while ($row = $ar->fetch_assoc())
            $agentSummaries[] = $mkSum($row, $row['full_name'], $row['department']);
    }

    // ── Group rows ────────────────────────────────────────────────────────────
    $grouped = [];
    if ($groupBy === 'agent') {
        foreach ($reportData as $row) $grouped[$row['agent_name'] ?: '(Unassigned)'][] = $row;
    } elseif ($groupBy === 'date') {
        foreach ($reportData as $row) $grouped[date('d M Y', strtotime($row['calldate']))][] = $row;
    } else {
        $grouped['_all'] = $reportData;
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function repDispColor(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'   => '#166534', 'NO ANSWER'  => '#991b1b',
        'BUSY'       => '#92400e', 'FAILED'     => '#6b21a8',
        'CONGESTION' => '#1e3a5f', default      => '#374151',
    };
}
function repDispBg(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'   => '#dcfce7', 'NO ANSWER'  => '#fee2e2',
        'BUSY'       => '#fef3c7', 'FAILED'     => '#f3e8ff',
        'CONGESTION' => '#dbeafe', default      => '#f1f5f9',
    };
}
function repDispIcon(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'   => '✓',
        'NO ANSWER'  => '✗',
        'BUSY'       => '◎',
        'FAILED'     => '✕',
        'CONGESTION' => '≈',
        default      => '?',
    };
}
function repDispShort(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'   => 'ANS',
        'NO ANSWER'  => 'MISS',
        'BUSY'       => 'BUSY',
        'FAILED'     => 'FAIL',
        'CONGESTION' => 'CONG',
        default      => $d,
    };
}
function repDirLabel(string $d): string {
    return match($d) { 'inbound'=>'↙','outbound'=>'↗','internal'=>'⇄',default=>'?' };
}
function repDirBg(string $d): array {
    return match($d) {
        'inbound'  => ['#dbeafe','#1e40af'],
        'outbound' => ['#ede9fe','#4c1d95'],
        'internal' => ['#f1f5f9','#374151'],
        default    => ['#f9fafb','#6b7280'],
    };
}
function repPriorityColor(string $p): string {
    return match($p) { 'urgent'=>'#dc2626','high'=>'#d97706','medium'=>'#0ea5e9',default=>'#64748b' };
}
function fmtRate(int $rate): string { return $rate . '%'; }

// Inline stacked bar SVG for summary card (w=160, h=10)
function stackedBar(int $answered, int $no_answer, int $busy, int $other, int $total, int $w = 160, int $h = 8): string {
    if ($total <= 0) return '';
    $a = round($answered  / $total * $w);
    $m = round($no_answer / $total * $w);
    $b = round($busy      / $total * $w);
    $o = max(0, $w - $a - $m - $b);
    $x = 0;
    $bars = '';
    foreach ([[$a,'#059669'],[$m,'#dc2626'],[$b,'#d97706'],[$o,'#94a3b8']] as [$len,$col]) {
        if ($len > 0) { $bars .= "<rect x='$x' y='0' width='$len' height='$h' fill='$col'/>"; $x += $len; }
    }
    return "<svg width='$w' height='$h' style='border-radius:3px;overflow:hidden;display:block'><rect width='$w' height='$h' fill='#e2e8f0'/>{$bars}</svg>";
}

// Mini rate arc (SVG donut segment)
function rateArc(int $rate): string {
    $col = $rate >= 70 ? '#059669' : ($rate >= 40 ? '#d97706' : '#dc2626');
    $r = 16; $cx = 20; $cy = 20; $sw = 5;
    $angle = $rate / 100 * 360;
    $rad   = $angle * M_PI / 180;
    $x2 = round($cx + $r * sin($rad), 2);
    $y2 = round($cy - $r * cos($rad), 2);
    $lg  = $angle > 180 ? 1 : 0;
    $arc = $angle >= 360
        ? "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='$col' stroke-width='$sw'/>"
        : "<path d='M $cx " . ($cy-$r) . " A $r $r 0 $lg 1 $x2 $y2' fill='none' stroke='$col' stroke-width='$sw' stroke-linecap='round'/>";
    return "<svg width='40' height='40' viewBox='0 0 40 40'>"
         . "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='#e2e8f0' stroke-width='$sw'/>"
         . $arc
         . "<text x='$cx' y='" . ($cy+4) . "' text-anchor='middle' font-size='8' font-weight='700' fill='$col'>{$rate}%</text>"
         . "</svg>";
}

// Build plain-text summary for copy link
function buildTextSummary(array $global, array $agentSums, string $company, string $from, string $to, bool $agentAll, array $selAgents, array $allAgents): string {
    $agentLabel = $agentAll ? 'All Agents' : implode(', ', array_map(function($id) use ($allAgents) {
        foreach ($allAgents as $a) if ($a['id'] == $id) return $a['full_name'];
        return "#$id";
    }, $selAgents));
    $lines   = [];
    $lines[] = "📊 CALL REPORT — {$company}";
    $lines[] = "Period : {$from}  →  {$to}";
    $lines[] = "Agents : {$agentLabel}";
    $lines[] = "";
    $lines[] = "── PERIOD TOTAL (All Agents) ─────────────────";
    $lines[] = sprintf("  Total     : %d", $global['total']);
    $lines[] = sprintf("  Answered  : %d  (%s)", $global['answered'], fmtRate($global['rate']));
    $lines[] = sprintf("  Missed    : %d  |  Busy : %d  |  Failed : %d", $global['no_answer'], $global['busy'], $global['failed']);
    $lines[] = sprintf("  Talk Total: %s  |  Avg : %s", formatDuration($global['talk_sec']), formatDuration($global['avg_sec']));
    foreach ($agentSums as $ag) {
        $lines[] = "";
        $lines[] = "── " . strtoupper($ag['label']) . ($ag['dept'] ? " ({$ag['dept']})" : "") . " ──";
        $lines[] = sprintf("  Total     : %d  (%.0f%% of period)", $ag['total'],
            $global['total'] > 0 ? $ag['total'] / $global['total'] * 100 : 0);
        $lines[] = sprintf("  Answered  : %d  (%s)", $ag['answered'], fmtRate($ag['rate']));
        $lines[] = sprintf("  Missed    : %d  |  Busy : %d  |  Failed : %d", $ag['no_answer'], $ag['busy'], $ag['failed']);
        $lines[] = sprintf("  Talk Total: %s  |  Avg : %s", formatDuration($ag['talk_sec']), formatDuration($ag['avg_sec']));
    }
    return implode("\n", $lines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Custom Report<?= $hasReport ? ' — '.e($dateFrom).' to '.e($dateTo) : '' ?> | <?= e($companyName) ?></title>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<style>
/* ── Base ─────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:13px}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh;font-size:12px}

/* ── Screen toolbar ───────────────────────────────── */
.screen-bar{background:#1e293b;color:#e2e8f0;padding:.55rem 1.25rem;display:flex;align-items:center;
    justify-content:space-between;gap:.75rem;flex-wrap:wrap;position:sticky;top:0;z-index:200}
.screen-bar-title{font-size:.82rem;font-weight:600;opacity:.9}
.screen-bar-btns{display:flex;gap:.4rem;flex-wrap:wrap}
.sbtn{padding:.28rem .75rem;border-radius:5px;border:none;font-size:.75rem;font-weight:600;cursor:pointer;
    display:inline-flex;align-items:center;gap:.3rem;text-decoration:none;white-space:nowrap}
.sbtn-primary{background:#6366f1;color:#fff}
.sbtn-success{background:#059669;color:#fff}
.sbtn-warn{background:#d97706;color:#fff}
.sbtn-ghost{background:rgba(255,255,255,.1);color:#e2e8f0}
.sbtn:hover{opacity:.82}

/* ── Page ─────────────────────────────────────────── */
.page{max-width:1080px;margin:0 auto;padding:1.25rem 1rem 4rem}

/* ── Form card ────────────────────────────────────── */
.form-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:1.25rem;overflow:hidden}
.form-card-head{background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:.65rem 1.1rem;
    font-weight:700;font-size:.85rem;display:flex;align-items:center;justify-content:space-between;
    cursor:pointer;user-select:none}
.form-card-body{padding:1.1rem}
.fsec{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6366f1;
    border-bottom:1px solid #e2e8f0;padding-bottom:.3rem;margin-bottom:.75rem;margin-top:.9rem}
.fsec:first-child{margin-top:0}
.form-row{display:flex;flex-wrap:wrap;gap:.65rem;margin-bottom:.65rem}
.fg{display:flex;flex-direction:column;gap:.25rem}
.fg label{font-size:.74rem;font-weight:600;color:#475569}
.fg input,.fg select{border:1px solid #cbd5e1;border-radius:5px;padding:.35rem .6rem;
    font-size:.8rem;color:#1e293b;background:#fff;outline:none}
.fg input:focus,.fg select:focus{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.15)}
.agent-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.35rem;
    max-height:190px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:7px;padding:.55rem;background:#f8fafc}
.ach{display:flex;align-items:center;gap:.4rem;font-size:.78rem;cursor:pointer}
.ach input{width:13px;height:13px;cursor:pointer;accent-color:#6366f1}
.opt-row{display:flex;flex-wrap:wrap;gap:.6rem}
.opt-check{display:flex;align-items:center;gap:.35rem;border:1px solid #e2e8f0;border-radius:7px;
    padding:.4rem .75rem;font-size:.78rem;cursor:pointer;background:#f8fafc;transition:border-color .15s,background .15s}
.opt-check:has(input:checked){border-color:#6366f1;background:#eef2ff;color:#4f46e5}
.opt-check input{accent-color:#6366f1}
.btn-generate{background:#6366f1;color:#fff;border:none;border-radius:7px;padding:.5rem 1.35rem;
    font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.35rem;margin-top:.85rem}
.btn-generate:hover{background:#4f46e5}

/* ── Report wrapper ───────────────────────────────── */
#reportOutput{background:#fff;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden}

/* ── Report header ────────────────────────────────── */
.rpt-header{background:#1e293b;color:#fff;padding:.85rem 1.25rem;
    display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.rpt-company{font-size:1rem;font-weight:800}
.rpt-subtitle{font-size:.72rem;opacity:.65;margin-top:.12rem}
.rpt-meta{text-align:right;font-size:.7rem;opacity:.7;line-height:1.8}
.rpt-meta strong{color:#fff;opacity:1}

/* ── Summary grid ─────────────────────────────────── */
.sum-section{padding:.75rem 1rem;background:#f8fafc;border-bottom:2px solid #e2e8f0}
.sum-section-title{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
    color:#6366f1;margin-bottom:.55rem}
.sum-cards{display:flex;flex-wrap:wrap;gap:.55rem}

/* Summary card */
.sum-card{border:1px solid #e2e8f0;border-radius:8px;background:#fff;min-width:180px;flex:1 1 180px;
    overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.05);max-width:320px}
.sum-card-head{background:#f1f5f9;padding:.38rem .65rem;font-size:12px;font-weight:700;
    border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:.4rem}
.sum-card-head .dept{font-size:11px;color:#64748b;font-weight:400}
.sum-body{padding:.4rem .6rem .5rem}
/* Rate row */
.sum-rate-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem}
.sum-rate-text{font-size:1.4rem;font-weight:800;line-height:1}
.sum-total-text{font-size:12px;color:#64748b;line-height:1.4}
/* Stacked bar area */
.sum-bar-area{margin-bottom:.4rem}
.sum-bar-label{display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-top:2px}
/* Stats grid (2-col: His | All) */
.sum-stats{width:100%;border-collapse:collapse;font-size:12px}
.sum-stats th{font-size:11px;font-weight:700;color:#94a3b8;text-align:right;padding:1px 3px;
    border-bottom:1px solid #e2e8f0;background:#f8fafc}
.sum-stats th.lft{text-align:left}
.sum-stats td{padding:2px 3px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.sum-stats tr:last-child td{border-bottom:none}
.sum-stats .lbl{color:#475569;font-size:12px}
.sum-stats .his{font-weight:700;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.sum-stats .all{color:#94a3b8;text-align:right;font-size:11px;white-space:nowrap}
.sum-stats .his.rate{font-size:13px}
.mini-share{height:3px;background:#e2e8f0;border-radius:2px;overflow:hidden;margin-top:1px}
.mini-share-fill{height:100%;background:#f59e0b;border-radius:2px}

/* ── Excel-like call table ────────────────────────── */
.tbl-wrap{overflow-x:auto}
.call-table{border-collapse:collapse;font-size:12px;table-layout:auto;width:auto;min-width:100%}
.call-table th,.call-table td{
    border:1px solid #cbd5e1;padding:3px 5px;vertical-align:middle;line-height:1.35;
    white-space:nowrap}
.call-table thead th{
    background:#e2e8f0;font-weight:700;font-size:11px;text-transform:uppercase;
    letter-spacing:.03em;color:#374151;white-space:nowrap;text-align:center;padding:4px 5px}
.call-table thead th.lal{text-align:left}
.call-table tbody tr:nth-child(even){background:#f9fafb}
.call-table tbody tr.missed-row{background:#fff5f5}
.call-table tbody tr.missed-row:nth-child(even){background:#fee2e2}
.call-table .ctr{text-align:center}
.call-table .num{text-align:right;font-variant-numeric:tabular-nums}
.call-table .mono{font-family:'Consolas','Courier New',monospace;font-size:12px}
/* Sub-row */
.call-table .sub-tr td{
    border-top:none;padding:2px 6px 4px;background:#f8fafc;vertical-align:top;white-space:normal}
.call-table .sub-tr.has-notes td{border-left:3px solid #3b82f6}
.call-table .sub-tr.has-tasks td{border-left:3px solid #d97706}
.call-table .sub-tr.has-both td{border-left:3px solid #6366f1}
.sub-inner{font-size:12px;color:#374151}
.sub-note{padding:1px 0 2px;display:flex;flex-wrap:wrap;gap:.25rem;align-items:flex-start;line-height:1.35}
.sub-note+.sub-note{border-top:1px dashed #e2e8f0;margin-top:2px;padding-top:2px}
.sub-reply{padding:1px 0 1px 1rem;display:flex;flex-wrap:wrap;gap:.2rem;align-items:flex-start;
    font-size:11px;color:#64748b;border-left:2px solid #bfdbfe;margin-left:.5rem;margin-top:1px}
.sub-task{padding:1px 0 2px;display:flex;flex-wrap:wrap;gap:.25rem;align-items:flex-start;line-height:1.35}
.sub-task+.sub-task{border-top:1px dashed #fde68a;margin-top:2px;padding-top:2px}
.sub-note-tasks-sep{border-top:1px dashed #c7d2fe;margin:3px 0}
.by{color:#94a3b8;font-size:11px}

/* Inline badges */
.bd{display:inline-block;padding:1px 4px;border-radius:3px;font-size:11px;
    font-weight:700;line-height:1.4;white-space:nowrap}
/* Status cell */
.stat-icon{display:inline-block;width:16px;height:16px;border-radius:2px;text-align:center;
    font-size:.72rem;font-weight:900;line-height:16px}
/* Extras cell */
.extras{display:flex;flex-wrap:wrap;gap:2px;align-items:center;justify-content:flex-start;white-space:normal}

/* Group heading */
.group-head{background:#1e293b;color:#fff;padding:.38rem 1rem;font-size:.72rem;
    font-weight:700;letter-spacing:.05em;text-transform:uppercase;
    display:flex;align-items:center;justify-content:space-between}
.group-head .gcnt{font-weight:400;opacity:.7;font-size:.68rem}

/* Footer */
.rpt-footer{border-top:1px solid #e2e8f0;padding:.5rem 1rem;
    display:flex;justify-content:space-between;font-size:.66rem;color:#94a3b8;background:#f8fafc}

/* No data */
.no-data{text-align:center;padding:2.5rem 1rem;color:#94a3b8;font-size:.85rem}

/* ── Print ────────────────────────────────────────── */
@media print{
    .screen-bar,.form-card{display:none!important}
    body{background:#fff;font-size:10.5pt}
    html{font-size:10.5pt}
    .page{max-width:100%;padding:0}
    #reportOutput{border:none;border-radius:0}
    .rpt-header{background:#1e293b!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;
        padding:.5rem .75rem}
    .sum-section{background:#f8fafc!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .sum-card-head{background:#f1f5f9!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .sum-card{break-inside:avoid;page-break-inside:avoid}
    .call-table{font-size:9pt;table-layout:auto;width:100%;min-width:0}
    .call-table th,.call-table td{padding:2px 3px;font-size:9pt}
    .call-table thead th{background:#e2e8f0!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;font-size:8pt}
    .call-table .mono{font-size:9pt}
    .sub-inner{font-size:8.5pt}
    .bd{font-size:8pt}
    .by{font-size:8pt}
    .sum-card{min-width:130px;max-width:none;flex:1 1 130px}
    .sum-cards{flex-wrap:wrap}
    .sum-body{padding:.2rem .35rem .3rem}
    .sum-stats,.sum-stats .lbl{font-size:8.5pt}
    .sum-stats th,.sum-stats .all{font-size:8pt}
    .sum-card-head{font-size:8pt;padding:.25rem .4rem}
    .sum-section-title{font-size:8pt}
    .tbl-wrap{overflow:visible}
    .call-table tbody tr.missed-row{background:#fff5f5!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .call-table tbody tr:nth-child(even){background:#f9fafb!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .group-head{background:#1e293b!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .bd,.stat-icon{-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .sub-tr td{background:#f8fafc!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .mini-share-fill,.mini-share{-webkit-print-color-adjust:exact;print-color-adjust:exact}
    @page{margin:1cm 1.2cm;size:A4 portrait}
    .sum-stats th,.sum-stats td{font-size:7.5pt}
    .sum-body{padding:.25rem .4rem .35rem}
    .sum-rate-text{font-size:1.1rem}
}
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-bar">
    <div class="screen-bar-title">
        &#128196; <?= e($companyName) ?> — Custom Report
        <?php if ($hasReport): ?>
        <span style="opacity:.5;margin:0 .3rem">|</span>
        <span style="opacity:.65;font-weight:400"><?= e($dateFrom) ?> → <?= e($dateTo) ?> · <?= count($reportData) ?> calls</span>
        <?php endif; ?>
    </div>
    <div class="screen-bar-btns">
        <?php if ($hasReport): ?>
        <button class="sbtn sbtn-ghost" onclick="toggleForm()">&#9881; Filters</button>
        <?php
        $fullUrl = 'custom_report.php?date_from='.urlencode($dateFrom).'&date_to='.urlencode($dateTo)
            .'&agents%5B%5D=all&summary=1&notes=1&replies=1&tasks=1&recordings=1&group_by=agent';
        ?>
        <?php if (!$agentAll || !$showSum || !$showNotes || !$showTasks): ?>
        <a href="<?= $fullUrl ?>" class="sbtn" style="background:rgba(99,102,241,.2);color:#a5b4fc;border:1px solid rgba(99,102,241,.4)">
            &#128202; Full Report
        </a>
        <?php endif; ?>
        <button class="sbtn sbtn-primary" onclick="window.print()">&#128438; Print / PDF</button>
        <button class="sbtn sbtn-success" id="imgBtn" onclick="downloadImage()">&#128247; Save Image</button>
        <button class="sbtn sbtn-warn" id="copyBtn" onclick="copyLink()">&#128279; Copy Link</button>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/reports.php" class="sbtn sbtn-ghost">← Reports</a>
    </div>
</div>

<div class="page">

<!-- ── Filter form ─────────────────────────────────────────────────────────── -->
<div class="form-card" id="formCard">
    <div class="form-card-head" onclick="toggleForm()">
        <span>&#9881; Report Filters</span>
        <span id="formToggleIcon" style="font-size:.75rem;opacity:.6"><?= $hasReport ? '▼ Expand' : '▲ Collapse' ?></span>
    </div>
    <div class="form-card-body" id="formBody" style="<?= $hasReport ? 'display:none' : '' ?>">
        <form method="GET" action="custom_report.php">
            <div class="fsec">Date Range</div>
            <div class="form-row">
                <div class="fg"><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>" required></div>
                <div class="fg"><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>" required></div>
                <div class="fg"><label>Disposition</label>
                    <select name="disposition">
                        <option value="">All Statuses</option>
                        <?php foreach (['ANSWERED','NO ANSWER','BUSY','FAILED','CONGESTION'] as $d): ?>
                        <option value="<?= $d ?>" <?= $filterDisp===$d?'selected':'' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Direction</label>
                    <select name="direction">
                        <option value="">All Directions</option>
                        <?php foreach (['inbound','outbound','internal'] as $d): ?>
                        <option value="<?= $d ?>" <?= $filterDir===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg"><label>Group By</label>
                    <select name="group_by">
                        <option value="none"  <?= $groupBy==='none' ?'selected':'' ?>>None</option>
                        <option value="agent" <?= $groupBy==='agent'?'selected':'' ?>>Agent</option>
                        <option value="date"  <?= $groupBy==='date' ?'selected':'' ?>>Date</option>
                    </select>
                </div>
                <div class="fg"><label>Sort</label>
                    <select name="sort">
                        <option value="desc" <?= $sortOrder==='desc'?'selected':'' ?>>Newest First</option>
                        <option value="asc"  <?= $sortOrder==='asc' ?'selected':'' ?>>Oldest First</option>
                    </select>
                </div>
            </div>

            <div class="fsec">Agents</div>
            <div style="margin-bottom:.4rem">
                <label class="ach" style="font-weight:700;color:#6366f1">
                    <input type="checkbox" name="agents[]" value="all" id="checkAll"
                           <?= (empty($selAgents)||in_array('all',$selAgents))?'checked':'' ?>
                           onchange="toggleAllAgents(this)">
                    All Agents
                </label>
            </div>
            <div class="agent-grid" id="agentGrid">
                <?php foreach ($allAgents as $ag): ?>
                <label class="ach">
                    <input type="checkbox" name="agents[]" value="<?= $ag['id'] ?>" class="agent-cb"
                           <?= (!empty($selAgents)&&!in_array('all',$selAgents)&&in_array($ag['id'],array_map('intval',$selAgents)))?'checked':'' ?>>
                    <span><?= e($ag['full_name']) ?><?php if($ag['department']): ?> <span style="color:#94a3b8;font-size:.68rem">(<?= e($ag['department']) ?>)</span><?php endif; ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="fsec">Include in Report</div>
            <div class="opt-row">
                <label class="opt-check"><input type="checkbox" name="summary"    value="1" <?= $showSum    ?'checked':'' ?>> &#128202; Summary</label>
                <label class="opt-check"><input type="checkbox" name="notes"      value="1" <?= $showNotes  ?'checked':'' ?>> &#128172; Notes</label>
                <label class="opt-check"><input type="checkbox" name="replies"    value="1" <?= $showReplies?'checked':'' ?>> &#8617; Replies</label>
                <label class="opt-check"><input type="checkbox" name="tasks"      value="1" <?= $showTasks  ?'checked':'' ?>> &#9989; Tasks</label>
                <label class="opt-check"><input type="checkbox" name="recordings" value="1" <?= $showRec    ?'checked':'' ?>> &#127908; Recordings</label>
            </div>
            <button type="submit" class="btn-generate">&#128196; Generate Report</button>
        </form>
    </div>
</div>

<?php if ($hasReport): ?>
<div id="reportOutput">

    <!-- Header -->
    <div class="rpt-header">
        <div>
            <div class="rpt-company"><?= e($companyName) ?></div>
            <div class="rpt-subtitle">
                Call Report &mdash; <?= e($dateFrom) ?> to <?= e($dateTo) ?>
                <?= !$agentAll && !empty($selAgents) ? ' &middot; ' . count($selAgents) . ' agent(s) selected' : ' &middot; All agents' ?>
                <?= $filterDisp ? ' &middot; ' . e($filterDisp) : '' ?>
                <?= $filterDir  ? ' &middot; ' . ucfirst(e($filterDir)) : '' ?>
            </div>
        </div>
        <div class="rpt-meta">
            <div>Generated <strong><?= date('d M Y, h:i A') ?></strong></div>
            <div>Records: <strong><?= number_format(count($reportData)) ?></strong></div>
        </div>
    </div>

    <?php if ($showSum && $globalSummary):
        $G = $globalSummary;
        $statRows = [
            ['Answered',   'answered',  '#059669'],
            ['Missed',     'no_answer', '#dc2626'],
            ['Busy',       'busy',      '#d97706'],
            ['Failed',     'failed',    '#6b21a8'],
            ['Congestion', 'congestion','#2563eb'],
            ['Manual',     'manual',    '#475569'],
        ];
    ?>
    <div class="sum-section">
        <div class="sum-section-title">&#128202; Summary — <?= e($dateFrom) ?> → <?= e($dateTo) ?></div>
        <div class="sum-cards">

            <!-- Global card -->
            <div class="sum-card">
                <div class="sum-card-head">
                    <span>All Agents &mdash; Period Total</span>
                    <span class="dept"><?= e($dateFrom) ?> → <?= e($dateTo) ?></span>
                </div>
                <div class="sum-body">
                    <div class="sum-rate-row">
                        <?= rateArc($G['rate']) ?>
                        <div>
                            <div class="sum-total-text">Total <strong><?= number_format($G['total']) ?></strong></div>
                            <div class="sum-total-text">Ans <strong style="color:#059669"><?= $G['answered'] ?></strong>
                                / Missed <strong style="color:#dc2626"><?= $G['no_answer'] ?></strong>
                                / Busy <strong style="color:#d97706"><?= $G['busy'] ?></strong></div>
                            <div class="sum-total-text">Talk <strong><?= formatDuration($G['talk_sec']) ?></strong>
                                &middot; Avg <strong><?= formatDuration($G['avg_sec']) ?></strong></div>
                        </div>
                    </div>
                    <div class="sum-bar-area">
                        <?= stackedBar($G['answered'],$G['no_answer'],$G['busy'],$G['failed']+$G['congestion'],$G['total'],180) ?>
                        <div class="sum-bar-label">
                            <span style="color:#059669">&#9632; Ans <?= $G['total']>0?round($G['answered']/$G['total']*100):0 ?>%</span>
                            <span style="color:#dc2626">&#9632; Miss <?= $G['total']>0?round($G['no_answer']/$G['total']*100):0 ?>%</span>
                            <span style="color:#d97706">&#9632; Busy <?= $G['total']>0?round($G['busy']/$G['total']*100):0 ?>%</span>
                            <span style="color:#94a3b8">&#9632; Other</span>
                        </div>
                    </div>
                    <table class="sum-stats">
                        <?php foreach ($statRows as [$lbl,$key,$col]): if (!$G[$key]) continue; ?>
                        <tr>
                            <td class="lbl"><?= $lbl ?></td>
                            <td class="his" style="color:<?= $col ?>"><?= number_format($G[$key]) ?></td>
                            <td class="all"><?= $G['total']>0?round($G[$key]/$G['total']*100):0 ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Per-agent cards -->
            <?php foreach ($agentSummaries as $AG): ?>
            <div class="sum-card">
                <div class="sum-card-head">
                    <span><?= e($AG['label']) ?></span>
                    <?php if ($AG['dept']): ?><span class="dept"><?= e($AG['dept']) ?></span><?php endif; ?>
                </div>
                <div class="sum-body">
                    <div class="sum-rate-row">
                        <?= rateArc($AG['rate']) ?>
                        <div>
                            <div class="sum-total-text">His <strong><?= number_format($AG['total']) ?></strong>
                                <span style="color:#f59e0b">(<?= $G['total']>0?round($AG['total']/$G['total']*100):0 ?>% of all)</span></div>
                            <div class="sum-total-text">Ans <strong style="color:#059669"><?= $AG['answered'] ?></strong>
                                / Miss <strong style="color:#dc2626"><?= $AG['no_answer'] ?></strong>
                                / Busy <strong style="color:#d97706"><?= $AG['busy'] ?></strong></div>
                            <div class="sum-total-text">Talk <strong><?= formatDuration($AG['talk_sec']) ?></strong>
                                &middot; Avg <strong><?= formatDuration($AG['avg_sec']) ?></strong></div>
                        </div>
                    </div>
                    <div class="sum-bar-area">
                        <?= stackedBar($AG['answered'],$AG['no_answer'],$AG['busy'],$AG['failed']+$AG['congestion'],$AG['total'],180) ?>
                        <div class="sum-bar-label">
                            <span style="color:#059669">&#9632; Ans <?= $AG['total']>0?round($AG['answered']/$AG['total']*100):0 ?>%</span>
                            <span style="color:#dc2626">&#9632; Miss <?= $AG['total']>0?round($AG['no_answer']/$AG['total']*100):0 ?>%</span>
                            <span style="color:#d97706">&#9632; Busy <?= $AG['total']>0?round($AG['busy']/$AG['total']*100):0 ?>%</span>
                        </div>
                    </div>
                    <table class="sum-stats">
                        <thead>
                            <tr>
                                <th class="lft"></th>
                                <th style="color:#6366f1">His</th>
                                <th>All</th>
                                <th>His%</th>
                            </tr>
                        </thead>
                        <?php foreach ($statRows as [$lbl,$key,$col]): if (!$AG[$key] && !$G[$key]) continue; ?>
                        <tr>
                            <td class="lbl"><?= $lbl ?></td>
                            <td class="his" style="color:<?= $col ?>"><?= number_format($AG[$key]) ?></td>
                            <td class="all"><?= number_format($G[$key]) ?></td>
                            <td class="all"><?= $AG['total']>0?round($AG[$key]/$AG['total']*100):0 ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f0f9ff">
                            <td class="lbl" style="font-weight:700">Rate</td>
                            <td class="his rate" style="color:<?= $AG['rate']>=70?'#059669':($AG['rate']>=40?'#d97706':'#dc2626') ?>"><?= $AG['rate'] ?>%</td>
                            <td class="all"><?= $G['rate'] ?>%</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="lbl">Share</td>
                            <td class="his" style="color:#92400e" colspan="3">
                                <?= $G['total']>0?round($AG['total']/$G['total']*100):0 ?>%
                                <div class="mini-share"><div class="mini-share-fill" style="width:<?= $G['total']>0?round($AG['total']/$G['total']*100):0 ?>%"></div></div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- Call detail table -->
    <?php if (empty($reportData)): ?>
    <div class="no-data">&#128222; No calls found for the selected filters.</div>
    <?php else:
    // Col count for colspan: #, Date/Time, From→To, Status, Dur, Extras = 6
    $colCount = 6;
    ?>

    <?php foreach ($grouped as $groupKey => $groupRows): ?>

        <?php if ($groupBy !== 'none'): ?>
        <div class="group-head">
            <span><?= $groupBy==='agent' ? '&#128100;' : '&#128197;' ?> <?= e($groupKey) ?></span>
            <span class="gcnt"><?= count($groupRows) ?> call<?= count($groupRows)>1?'s':'' ?></span>
        </div>
        <?php endif; ?>

        <div class="tbl-wrap">
        <table class="call-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="lal">Date/Time</th>
                    <th class="lal">From → To</th>
                    <th>Status</th>
                    <th>Dur</th>
                    <th>Extras</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $rn = 0;
            foreach ($groupRows as $c):
                $rn++;
                $isMissed  = in_array($c['disposition'], ['NO ANSWER','BUSY','FAILED','CONGESTION']);
                $cNoteIds  = $notesMap[$c['id']] ?? [];
                $cTasks    = $tasksMap[$c['id']] ?? [];
                $contactId = $c['contact_id'] ?? null;
                $cContactNoteIds = $contactId ? ($contactNotesMap[$contactId] ?? []) : [];
                $hasNotes  = $showNotes && (!empty($cNoteIds) || !empty($cContactNoteIds));
                $hasTasks  = $showTasks && !empty($cTasks);
                $hasExtra  = $hasNotes || $hasTasks;
                [$dBg,$dFg]= repDirBg($c['call_direction'] ?? '');
                $dirSymbol = repDirLabel($c['call_direction'] ?? '');
                $billFmt   = formatDuration((int)$c['billsec']);
                $durFmt    = formatDuration((int)$c['duration']);
                $durCell   = ($billFmt === $durFmt || $c['billsec'] == $c['duration'])
                             ? $billFmt
                             : $billFmt . '<span style="color:#94a3b8;font-size:.65rem"> /' . $durFmt . '</span>';
            ?>
            <tr class="<?= $isMissed ? 'missed-row' : '' ?>">
                <td class="ctr" style="color:#94a3b8"><?= $rn ?></td>
                <td class="mono" style="white-space:nowrap">
                    <?= date('d/m', strtotime($c['calldate'])) ?>
                    <span style="color:#64748b"><?= date('H:i', strtotime($c['calldate'])) ?></span>
                </td>
                <td class="mono">
                    <span class="bd" style="background:<?= $dBg ?>;color:<?= $dFg ?>;margin-right:2px"><?= $dirSymbol ?></span><?= e($c['src'] ?: '—') ?> <span style="color:#94a3b8">→</span> <?= e($c['dst'] ?: '—') ?>
                </td>
                <td class="ctr">
                    <span class="stat-icon" style="background:<?= repDispBg($c['disposition']) ?>;color:<?= repDispColor($c['disposition']) ?>"
                          title="<?= e($c['disposition']) ?>">
                        <?= repDispIcon($c['disposition']) ?>
                    </span>
                    <div style="font-size:.6rem;color:<?= repDispColor($c['disposition']) ?>;font-weight:700"><?= repDispShort($c['disposition']) ?></div>
                </td>
                <td class="num mono"><?= $durCell ?></td>
                <td>
                    <div class="extras">
                        <?php if ($c['call_mark'] && $c['call_mark'] !== 'normal'): ?>
                        <span class="bd" style="background:#e0e7ff;color:#3730a3" title="Mark"><?= mb_substr(ucwords(str_replace('_',' ',$c['call_mark'])),0,3) ?></span>
                        <?php endif; ?>
                        <?php if ($showRec && $c['recordingfile']): ?>
                        <span class="bd" style="background:#dcfce7;color:#166534" title="Recording">&#9679;Rec</span>
                        <?php endif; ?>
                        <?php if ($hasNotes): ?>
                        <span class="bd" style="background:#dbeafe;color:#1d4ed8" title="<?= count($cNoteIds) ?> call note(s)"><?= count($cNoteIds) ?>CN</span>
                        <?php if (!empty($cContactNoteIds)): ?>
                        <span class="bd" style="background:#fce7f3;color:#be185d" title="<?= count($cContactNoteIds) ?> contact note(s)"><?= count($cContactNoteIds) ?>CtN</span>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($hasTasks): ?>
                        <span class="bd" style="background:#fef3c7;color:#92400e" title="<?= count($cTasks) ?> task(s)"><?= count($cTasks) ?>T</span>
                        <?php endif; ?>
                        <?php if ($c['is_manual']): ?>
                        <span class="bd" style="background:#f3e8ff;color:#6b21a8" title="Manual">M</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($c['contact_name']): ?><div style="font-size:.63rem;color:#475569;margin-top:1px"><?= e($c['contact_name']) ?></div><?php endif; ?>
                    <?php if ($c['agent_name']): ?><div style="font-size:.62rem;color:#7c3aed"><?= e($c['agent_name']) ?></div><?php endif; ?>
                </td>
            </tr>

            <?php if ($hasExtra): ?>
            <?php
            $subClass = ($hasNotes && $hasTasks) ? 'has-both' : ($hasNotes ? 'has-notes' : 'has-tasks');
            ?>
            <tr class="sub-tr <?= $subClass ?>">
                <td colspan="<?= $colCount ?>">
                    <div class="sub-inner">
                    <?php if ($hasNotes): ?>
                        <?php foreach ($cNoteIds as $rootId):
                            $note = $noteById[$rootId] ?? null;
                            if (!$note) continue;
                            $repliesOfNote = $noteChildren[$rootId] ?? [];
                        ?>
                        <div class="sub-note">
                            <span class="bd" style="background:#dbeafe;color:#1d4ed8"><?= e($note['note_type']) ?></span>
                            <?php if ($note['priority'] !== 'low'): ?>
                            <span class="bd" style="background:<?= repPriorityColor($note['priority']) ?>20;color:<?= repPriorityColor($note['priority']) ?>"><?= e($note['priority']) ?></span>
                            <?php endif; ?>
                            <span><?= e($note['content']) ?></span>
                            <span class="by">— <?= e($note['by_name']) ?> <?= date('d/m H:i',strtotime($note['created_at'])) ?></span>
                        </div>
                        <?php if ($showReplies && $repliesOfNote): ?>
                            <?php foreach ($repliesOfNote as $replyId):
                                $reply = $noteById[$replyId] ?? null;
                                if (!$reply) continue;
                            ?>
                            <div class="sub-reply">
                                <span class="bd" style="background:#e0f2fe;color:#0369a1">↪ reply</span>
                                <span><?= e($reply['content']) ?></span>
                                <span class="by">— <?= e($reply['by_name']) ?> <?= date('d/m H:i',strtotime($reply['created_at'])) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (!empty($cContactNoteIds)): ?>
                        <div class="sub-note" style="margin-top:4px;padding-top:4px;border-top:1px dashed #fbcfe8">
                            <span class="bd" style="background:#fce7f3;color:#be185d;font-weight:700">Contact Notes:</span>
                        </div>
                        <?php foreach ($cContactNoteIds as $rootId):
                            $note = $contactNoteById[$rootId] ?? null;
                            if (!$note) continue;
                            $repliesOfNote = $contactNoteChildren[$rootId] ?? [];
                        ?>
                        <div class="sub-note">
                            <span class="bd" style="background:#fce7f3;color:#be185d"><?= e($note['note_type']) ?></span>
                            <?php if ($note['priority'] !== 'low'): ?>
                            <span class="bd" style="background:<?= repPriorityColor($note['priority']) ?>20;color:<?= repPriorityColor($note['priority']) ?>"><?= e($note['priority']) ?></span>
                            <?php endif; ?>
                            <span><?= e($note['content']) ?></span>
                            <span class="by">— <?= e($note['by_name']) ?> <?= date('d/m H:i',strtotime($note['created_at'])) ?></span>
                        </div>
                        <?php if ($showReplies && $repliesOfNote): ?>
                            <?php foreach ($repliesOfNote as $replyId):
                                $reply = $contactNoteById[$replyId] ?? null;
                                if (!$reply) continue;
                            ?>
                            <div class="sub-reply">
                                <span class="bd" style="background:#fce7f3;color:#be185d">↪ reply</span>
                                <span><?= e($reply['content']) ?></span>
                                <span class="by">— <?= e($reply['by_name']) ?> <?= date('d/m H:i',strtotime($reply['created_at'])) ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($hasNotes && $hasTasks): ?>
                    <div class="sub-note-tasks-sep"></div>
                    <?php endif; ?>
                    <?php if ($hasTasks): ?>
                        <?php foreach ($cTasks as $t): ?>
                        <div class="sub-task">
                            <span class="bd" style="background:<?= repPriorityColor($t['priority']) ?>20;color:<?= repPriorityColor($t['priority']) ?>"><?= e($t['priority']) ?></span>
                            <span class="bd" style="background:#f0fdf4;color:#166534"><?= e($t['status']) ?></span>
                            <strong><?= e($t['title']) ?></strong>
                            <?php if ($t['assigned_name']): ?><span class="by">&#128100; <?= e($t['assigned_name']) ?></span><?php endif; ?>
                            <?php if ($t['due_date']): ?><span class="by">Due <?= date('d/m/y',strtotime($t['due_date'])) ?></span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endforeach; ?>
    <?php endif; ?>

    <div class="rpt-footer">
        <span><?= e($companyName) ?> — Confidential</span>
        <span>Generated <?= date('d M Y, h:i A') ?> · <?= count($reportData) ?> records</span>
    </div>

</div><!-- #reportOutput -->
<?php endif; ?>

</div><!-- .page -->

<?php
$textSummary = '';
if ($hasReport && $showSum && $globalSummary) {
    $agentAll2   = (empty($selAgents) || in_array('all', $selAgents));
    $textSummary = buildTextSummary($globalSummary, $agentSummaries, $companyName, $dateFrom, $dateTo, $agentAll2, $selAgents, $allAgents);
}
?>
<script>
function toggleForm() {
    const b = document.getElementById('formBody');
    const i = document.getElementById('formToggleIcon');
    const hidden = b.style.display === 'none';
    b.style.display = hidden ? '' : 'none';
    i.textContent   = hidden ? '▲ Collapse' : '▼ Expand';
}
function toggleAllAgents(cb) {
    document.querySelectorAll('.agent-cb').forEach(c => c.checked = !cb.checked);
}
function copyLink() {
    const btn  = document.getElementById('copyBtn');
    const text = <?= json_encode($textSummary) ?>;
    const url  = window.location.href;
    const full = text ? text + "\n\n🔗 " + url : url;
    navigator.clipboard.writeText(full).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '✓ Copied!';
        btn.style.background = '#059669';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = ''; }, 2500);
    });
}
function downloadImage() {
    const btn  = document.getElementById('imgBtn');
    const orig = btn.innerHTML;
    btn.innerHTML = '⏳ Rendering…';
    btn.disabled  = true;
    html2canvas(document.getElementById('reportOutput'), {
        scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false
    }).then(canvas => {
        const a    = document.createElement('a');
        a.download = 'report_<?= e($dateFrom) ?>_<?= e($dateTo) ?>.png';
        a.href     = canvas.toDataURL('image/png');
        a.click();
        btn.innerHTML = orig;
        btn.disabled  = false;
    }).catch(() => {
        alert('Image generation failed — try Print → Save as PDF.');
        btn.innerHTML = orig;
        btn.disabled  = false;
    });
}
<?php if ($hasReport): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('reportOutput')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>
</body>
</html>
