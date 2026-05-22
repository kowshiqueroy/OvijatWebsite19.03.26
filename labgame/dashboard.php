<?php
$studentId = $_SESSION['student_id'] ?? 0;
$db = getDB();
$stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$rooms = $db->query("SELECT r.*, s.username as host_name, (SELECT COUNT(*) FROM race_participants WHERE room_id = r.id) as player_count FROM rooms r JOIN students s ON s.id = r.host_id WHERE r.status = 'waiting' ORDER BY r.created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= SITE_NAME ?> - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-page">
    <div class="top-bar">
        <div class="user-info">
            <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= urlencode($student['avatar_seed']) ?>" alt="Avatar" class="avatar" onerror="this.src='assets/img/avatar-fallback.svg'">
            <span class="username"><?= htmlspecialchars($student['username']) ?></span>
            <span class="badge">⭐ <?= (int)$student['points'] ?> pts</span>
            <span class="badge">🏆 <?= (int)$student['races_won'] ?> wins</span>
        </div>
        <a href="index.php?action=logout" class="btn btn-small btn-danger">🚪 Logout</a>
    </div>

    <div class="dashboard-content">
        <div class="action-cards">
            <button class="action-card card-join" id="btnJoinRoom">
                <span class="card-icon">🟢</span>
                <span class="card-title">Join Open Room</span>
                <span class="card-desc">Race with friends!</span>
            </button>
            <button class="action-card card-create" id="btnCreateRoom">
                <span class="card-icon">🚀</span>
                <span class="card-title">Create New Race</span>
                <span class="card-desc">Pick zone, type & go!</span>
            </button>
        </div>

        <div class="rooms-section">
            <h2>🟢 Open Rooms</h2>
            <div class="rooms-grid" id="roomsGrid">
                <?php if (empty($rooms)): ?>
                    <p class="no-rooms">No open rooms yet. Create one! 🎮</p>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <div class="room-card" data-code="<?= htmlspecialchars($room['code']) ?>">
                            <div class="room-zone zone-<?= htmlspecialchars($room['zone']) ?>"><?= getZoneEmoji($room['zone']) ?> <?= ucfirst($room['zone']) ?></div>
                            <div class="room-info">
                                <span>👤 <?= htmlspecialchars($room['host_name']) ?></span>
                                <span>📝 <?= ucfirst($room['question_type']) ?></span>
                                <span>⏱️ <?= $room['duration_minutes'] ?>min</span>
                                <span>👥 <?= $room['player_count'] ?>/<?= $room['max_players'] ?></span>
                            </div>
                            <button class="btn btn-small btn-join-room">Join ➡️</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="createModal">
        <div class="modal-content">
            <h2>🚀 Create New Race</h2>
            <form id="createForm">
                <div class="input-group">
                    <label>🗺️ Choose Zone</label>
                    <div class="picker-row zone-picker">
                        <label class="picker-option" data-value="candyland">
                            <input type="radio" name="zone" value="candyland" checked hidden>
                            <span class="picker-emoji">🍭</span>
                            <span class="picker-label">Candy Land</span>
                        </label>
                        <label class="picker-option" data-value="dinosaur">
                            <input type="radio" name="zone" value="dinosaur" hidden>
                            <span class="picker-emoji">🦕</span>
                            <span class="picker-label">Dino Jungle</span>
                        </label>
                        <label class="picker-option" data-value="space">
                            <input type="radio" name="zone" value="space" hidden>
                            <span class="picker-emoji">🚀</span>
                            <span class="picker-label">Space Orbit</span>
                        </label>
                    </div>
                </div>

                <div class="input-group">
                    <label>📝 Question Type</label>
                    <div class="picker-row type-picker">
                        <label class="picker-option" data-value="math">
                            <input type="radio" name="question_type" value="math" hidden>
                            <span class="picker-emoji">🧮</span>
                            <span class="picker-label">Math</span>
                        </label>
                        <label class="picker-option" data-value="word">
                            <input type="radio" name="question_type" value="word" hidden>
                            <span class="picker-emoji">📖</span>
                            <span class="picker-label">Words</span>
                        </label>
                        <label class="picker-option" data-value="pattern">
                            <input type="radio" name="question_type" value="pattern" hidden>
                            <span class="picker-emoji">🧩</span>
                            <span class="picker-label">IQ/Pattern</span>
                        </label>
                        <label class="picker-option" data-value="random" checked>
                            <input type="radio" name="question_type" value="random" checked hidden>
                            <span class="picker-emoji">🎲</span>
                            <span class="picker-label">Random</span>
                        </label>
                    </div>
                </div>

                <div class="input-group">
                    <label>⏱️ Race Duration: <span id="durationDisplay">3</span> min</label>
                    <input type="range" name="duration" id="durationSlider" min="1" max="10" value="3" step="1">
                    <div class="range-labels"><span>1 min</span><span>10 min</span></div>
                </div>

                <button type="submit" class="btn btn-primary btn-large">🚀 Create & Race!</button>
                <button type="button" class="btn btn-small btn-close-modal">Cancel</button>
            </form>
        </div>
    </div>

    <div class="modal" id="lobbyModal">
        <div class="modal-content lobby-content">
            <h2>🏁 Race Lobby</h2>
            <div id="lobbyRoomCode" class="lobby-code">CODE: -----</div>
            <div id="lobbyPlayers" class="lobby-players">Waiting for players...</div>
            <div id="lobbyStatus" class="lobby-status">⏳ Need at least 2 players...</div>
            <button id="btnStartRace" class="btn btn-primary btn-large hidden">🏁 Start Race!</button>
            <button id="btnLeaveLobby" class="btn btn-small btn-danger">🚪 Leave</button>
        </div>
    </div>

    <script>
        // Duration slider
        const slider = document.getElementById('durationSlider');
        const display = document.getElementById('durationDisplay');
        if (slider && display) {
            slider.addEventListener('input', () => { display.textContent = slider.value; });
        }

        // Picker option selection
        document.querySelectorAll('.picker-option').forEach(el => {
            el.addEventListener('click', function() {
                const parent = this.closest('.picker-row');
                parent.querySelectorAll('.picker-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type=radio]').checked = true;
            });
        });

        // Set default selected
        document.querySelectorAll('.picker-option.selected, .picker-option input:checked').forEach(el => {
            const opt = el.closest('.picker-option') || el;
            opt.classList.add('selected');
        });

        // Create room
        document.getElementById('btnCreateRoom').addEventListener('click', () => {
            document.getElementById('createModal').classList.add('show');
        });

        // Close modals
        document.querySelectorAll('.btn-close-modal').forEach(b => {
            b.addEventListener('click', () => {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));
            });
        });

        // Submit create form
        document.getElementById('createForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action', 'create');
            const resp = await fetch('api/join.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                document.getElementById('createModal').classList.remove('show');
                openLobby(data.code);
            } else {
                alert('Error: ' + (data.error || 'Unknown'));
            }
        });

        // Join room
        document.querySelectorAll('.btn-join-room').forEach(btn => {
            btn.addEventListener('click', async function() {
                const card = this.closest('.room-card');
                const code = card.dataset.code;
                const fd = new FormData();
                fd.append('action', 'join');
                fd.append('code', code);
                const resp = await fetch('api/join.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    openLobby(code);
                } else {
                    alert('Error: ' + (data.error || 'Cannot join'));
                }
            });
        });

        async function openLobby(code) {
            const modal = document.getElementById('lobbyModal');
            const codeEl = document.getElementById('lobbyRoomCode');
            const playersEl = document.getElementById('lobbyPlayers');
            const statusEl = document.getElementById('lobbyStatus');
            const startBtn = document.getElementById('btnStartRace');

            modal.classList.add('show');
            codeEl.textContent = '🏠 Room Code: ' + code;

            // Check if I'm host
            let isHost = false;

            const poll = setInterval(async () => {
                try {
                    const resp = await fetch('api/question.php?room_code=' + encodeURIComponent(code) + '&lobby=1');
                    const data = await resp.json();
                    
                    if (data.players) {
                        playersEl.innerHTML = data.players.map(p => 
                            `<div class="lobby-player">
                                <img src="https://api.dicebear.com/7.x/bottts/svg?seed=${encodeURIComponent(p.avatar_seed)}" onerror="this.src='assets/img/avatar-fallback.svg'" class="lobby-avatar">
                                <span>${p.username} ${p.is_host ? '👑' : ''}</span>
                            </div>`
                        ).join('');
                        isHost = data.is_host;
                    }

                    if (data.room_status === 'racing') {
                        clearInterval(poll);
                        window.location.href = 'index.php?page=race&room=' + encodeURIComponent(code);
                    }

                    if (data.player_count >= 2) {
                        statusEl.textContent = '✅ Ready! Click Start!';
                        if (isHost) {
                            startBtn.classList.remove('hidden');
                        }
                    } else {
                        statusEl.textContent = '⏳ Need at least 2 players (' + data.player_count + '/2)...';
                        startBtn.classList.add('hidden');
                    }
                } catch(e) {}
            }, 1500);

            startBtn.addEventListener('click', async () => {
                const fd = new FormData();
                fd.append('action', 'start');
                fd.append('code', code);
                const resp = await fetch('api/join.php', { method: 'POST', body: fd });
                const data = await resp.json();
                if (data.success) {
                    clearInterval(poll);
                    window.location.href = 'index.php?page=race&room=' + encodeURIComponent(code);
                } else {
                    alert(data.error || 'Cannot start');
                }
            }, { once: true });

            document.getElementById('btnLeaveLobby').addEventListener('click', () => {
                clearInterval(poll);
                modal.classList.remove('show');
            }, { once: true });
        }
    </script>
</body>
</html>
<?php
function getZoneEmoji($zone) {
    return ['candyland'=>'🍭','dinosaur'=>'🦕','space'=>'🚀'][$zone] ?? '🎮';
}
