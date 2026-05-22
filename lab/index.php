<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Computer Lab — Green Signal School</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ═══════════════════════════════════════════════
     LOGIN
     ═══════════════════════════════════════════════ -->
<div id="login-wrapper" class="login-wrapper">
    <div class="login-card">
        <div>🔬</div>
        <h1>Computer Lab</h1>
        <p class="subtitle">Green Signal Residential School</p>

        <div class="login-steps">
            <span class="step active" id="step1"></span>
            <span class="step" id="step2"></span>
        </div>

        <!-- Step 1: Username -->
        <div id="step-username">
            <input type="text" id="username-input" class="input" placeholder="Enter your username" autocomplete="off" autocapitalize="off" spellcheck="false">
        </div>

        <!-- Step 2a: Login PIN (existing user) -->
        <div id="step-pin" class="hidden">
            <p class="text-sm text-dim mb-2">Welcome back! Enter your PIN.</p>
            <input type="password" id="pin-input" class="input" placeholder="Your PIN" autocomplete="current-password">
            <button id="login-btn" class="btn btn-primary">Log In</button>
        </div>

        <!-- Step 2b: Create PIN (new user) -->
        <div id="step-create-pin" class="hidden">
            <p class="text-sm text-dim mb-2">New student! Create a PIN (4+ characters).</p>
            <input type="password" id="create-pin-input" class="input" placeholder="Create a PIN" autocomplete="new-password">
            <button id="register-btn" class="btn btn-primary">Register</button>
        </div>

        <div id="login-loader" class="login-loader hidden">⏳ Checking...</div>
        <div id="login-error" class="login-error hidden"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     TEACHER DASHBOARD
     ═══════════════════════════════════════════════ -->
<div id="teacher-dashboard" class="hidden">
    <div class="header">
        <div class="header-left">
            <span>👨‍🏫</span>
            <div>
                <h1>Teacher Dashboard</h1>
                <div class="school-name">Green Signal School</div>
            </div>
        </div>
        <div class="header-right">
            <button id="logout-btn" class="btn btn-sm">Logout</button>
        </div>
    </div>

    <div class="teacher-grid">
        <!-- Online Students -->
        <div class="card card-full" id="online-students-panel">
            <div class="card-header">
                <h2><span class="icon">🟢</span> Online Students</h2>
                <span class="badge badge-success" id="online-count">0</span>
            </div>
            <div id="student-grid" class="student-grid"></div>
        </div>

        <!-- AI Task Importer -->
        <div class="card">
            <div class="card-header">
                <h2><span class="icon">🤖</span> AI Task Importer</h2>
            </div>
            <p class="text-xs text-dim mb-2">Paste JSON with tasks. Each task needs <code style="font-family:var(--mono)">type</code> (mcq/typing) and <code style="font-family:var(--mono)">content</code>.</p>
            <div class="import-hint">[{"type":"mcq","content":{"questions":[{"question":"...","options":["A","B","C"],"answer":0}]}}]</div>
            <textarea id="ai-json-input" class="input" placeholder='Paste your AI-generated JSON here...'></textarea>
            <button id="import-tasks-btn" class="btn btn-primary mt-2">Import Tasks</button>
            <div id="import-status" class="text-sm mt-2 text-dim"></div>
        </div>

        <!-- Warnings -->
        <div class="card">
            <div class="card-header">
                <h2><span class="icon">⚠️</span> Warnings Log</h2>
            </div>
            <div id="warnings-list" class="warnings-scroll">
                <p class="text-sm text-dim">No warnings logged</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     STUDENT DASHBOARD
     ═══════════════════════════════════════════════ -->
<div id="student-dashboard" class="hidden">
    <div class="header">
        <div class="header-left">
            <span>🖥️</span>
            <div>
                <h1>Computer Lab</h1>
                <div class="school-name" id="user-greeting">Welcome</div>
            </div>
        </div>
        <div class="header-right">
            <span id="student-points" class="points-badge">⭐ 0</span>
            <button id="logout-btn-student" class="btn btn-sm">Logout</button>
        </div>
    </div>

    <div class="student-layout">

        <!-- SIDEBAR: Leaderboard + Podium + Online -->
        <div class="sidebar">

            <!-- Podium -->
            <div class="card">
                <div class="card-header">
                    <h2><span class="icon">🏆</span> Top Arcade</h2>
                </div>
                <div id="podium" class="podium-compact"></div>
            </div>

            <!-- Leaderboard -->
            <div class="card">
                <div class="card-header">
                    <h2><span class="icon">📚</span> Academic Leaders</h2>
                </div>
                <div id="academic-leaderboard"></div>
            </div>

            <!-- Online -->
            <div class="card">
                <div class="card-header">
                    <h2><span class="icon">🟢</span> Online</h2>
                </div>
                <div id="online-list"></div>
            </div>
        </div>

        <!-- MAIN: Games -->
        <div class="main-content">

            <!-- Arcade Games Grid -->
            <div class="card">
                <div class="card-header">
                    <h2><span class="icon">🎮</span> Arcade</h2>
                    <span class="text-xs text-dim">Click a game to play</span>
                </div>
                <div class="game-lobby">
                    <div class="game-lobby-card" data-game="sprint">
                        <div class="emoji">🏎️</div>
                        <h3>The Sprint</h3>
                        <p>1v1 racing — answer math questions to move your car!</p>
                        <span class="players">👥 2 players</span>
                    </div>
                    <div class="game-lobby-card" data-game="defend">
                        <div class="emoji">🛡️</div>
                        <h3>Defend the Lab</h3>
                        <p>Solo — type answers to destroy math/word problems!</p>
                        <span class="players">👤 1 player</span>
                    </div>
                    <div class="game-lobby-card" data-game="clash">
                        <div class="emoji">🧠</div>
                        <h3>Knowledge Clash</h3>
                        <p>1v1 Tic-Tac-Toe — answer MCQs to claim cells!</p>
                        <span class="players">👥 2 players</span>
                    </div>
                </div>

                <!-- ══ OPPONENT SELECT ══ -->
                <div id="opponent-select" class="opponent-select hidden">
                    <div class="opponent-header">
                        <button class="btn btn-sm" id="opp-back">← Back</button>
                        <h3 id="opp-title">🎮 Select Opponent</h3>
                        <button class="btn btn-sm btn-primary" id="opp-auto">⚡ Auto Match</button>
                    </div>
                    <p class="text-xs text-dim mb-2">Choose an opponent or auto-match:</p>
                    <div id="opp-list" class="opp-list"></div>
                </div>

                <!-- ══ SPRINT ══ -->
                <div id="sprint-game" class="game-screen hidden">
                    <div class="game-title">
                        <h3>🏎️ The Sprint</h3>
                        <div class="flex gap-2">
                            <button class="btn btn-sm btn-danger hidden" id="cancel-sprint">✕ Cancel Race</button>
                        </div>
                    </div>
                    <div class="sprint-track">
                        <div class="lane"></div>
                        <div class="lane lane2"></div>
                        <span class="track-label l1">You</span>
                        <span class="track-label l2">Opponent</span>
                        <span class="sprint-car-name n1" id="sprint-name1">🚗 You</span>
                        <span class="sprint-car-name n2" id="sprint-name2">🚀 Opp</span>
                        <span class="sprint-speed s1" id="speed1">0 km/h</span>
                        <span class="sprint-speed s2" id="speed2">0 km/h</span>
                        <span class="sprint-boost-text" id="boost1" style="color:var(--primary);left:50%;">⚡ BOOST!</span>
                        <span class="sprint-boost-text" id="boost2" style="color:var(--accent);left:50%;">⚡ BOOST!</span>
                        <div class="car car1" id="car1">🚗</div>
                        <div class="car car2" id="car2">🚀</div>
                        <div class="finish">🏁</div>
                    </div>
                    <div id="sprint-question" class="sprint-question"><span class="hint">Type the answer, press Enter</span></div>
                    <input type="text" id="sprint-answer" class="input sprint-input" placeholder="Answer..." autocomplete="off">
                    <div id="sprint-streak" class="sprint-streak"></div>
                    <div id="sprint-status" class="text-sm text-center mt-1 text-dim"></div>
                </div>

                <!-- ══ DEFEND CONFIG ══ -->
                <div id="defend-config" class="opponent-select hidden">
                    <div class="opponent-header">
                        <button class="btn btn-sm" id="defend-back">← Back</button>
                        <h3>🛡️ Defend the Lab</h3>
                        <div></div>
                    </div>
                    <div class="defend-options">
                        <div class="defend-option-group">
                            <label>Problem Type</label>
                            <div class="defend-pills" id="defend-type">
                                <button class="pill active" data-value="mixed">Mixed</button>
                                <button class="pill" data-value="math">Math</button>
                                <button class="pill" data-value="words">Words</button>
                            </div>
                        </div>
                        <div class="defend-option-group">
                            <label>Mode</label>
                            <div class="defend-pills" id="defend-mode">
                                <button class="pill active" data-value="lives">❤️ Lives (3)</button>
                                <button class="pill" data-value="timed">⏱️ Timed (60s)</button>
                            </div>
                        </div>
                        <button class="btn btn-primary" id="defend-start">⚔️ Start Game</button>
                    </div>
                </div>

                <!-- ══ DEFEND ══ -->
                <div id="defend-game" class="game-screen hidden">
                    <div class="game-title">
                        <h3 id="defend-title">🛡️ Defend the Lab</h3>
                        <button class="btn btn-sm btn-danger hidden" id="cancel-defend">✕ End Game</button>
                    </div>
                    <div class="defend-wrapper">
                        <canvas id="defend-canvas" width="560" height="360"></canvas>
                        <div class="defend-hud">
                            <span>⭐ <strong id="defend-score">0</strong></span>
                            <span id="defend-lives">❤️❤️❤️</span>
                            <span id="defend-timer" class="hidden">⏱️ <strong id="defend-timer-value">60</strong>s</span>
                        </div>
                        <input type="text" id="defend-input" class="input defend-input" placeholder="Type the answer, press Enter to shoot" autocomplete="off">
                        <div id="defend-status" class="text-sm text-center mt-1 text-dim"></div>
                    </div>
                </div>

                <!-- ══ CLASH ══ -->
                <div id="clash-game" class="game-screen hidden">
                    <div class="game-title">
                        <h3>🧠 Knowledge Clash</h3>
                        <button class="btn btn-sm btn-danger hidden" id="cancel-clash">✕ Cancel</button>
                    </div>
                    <div id="clash-board" class="ttt-board"></div>
                    <div id="clash-status" class="text-sm text-center mt-2 text-dim"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     LOCK OVERLAY
     ═══════════════════════════════════════════════ -->
<div id="lock-overlay" class="lock-overlay hidden">
    <div class="lock-card card">
        <h1>🔒</h1>
        <h2 class="text-red">Locked</h2>
        <p>Tab switch detected. Warning sent to teacher.</p>
        <button id="unlock-btn" class="btn btn-primary">Continue</button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     MCQ MODAL
     ═══════════════════════════════════════════════ -->
<div id="mcq-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <h2>📝 Question</h2>
        <div id="mcq-body"></div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════
     TOAST + SSE STATUS
     ═══════════════════════════════════════════════ -->
<div id="toast" class="toast hidden"></div>

<div id="sse-status" class="disconnected">
    <span class="dot"></span>
    <span id="sse-status-text">Disconnected</span>
</div>

<script src="app.js"></script>
</body>
</html>
