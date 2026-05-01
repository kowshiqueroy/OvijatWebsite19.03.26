<?php session_start(); include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#131314">
    <title>Gemini</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="new-chat-btn" onclick="searchUser()">+ New chat</div>
        <div style="font-size:12px; color:var(--gemini-dim); padding-left:12px; margin-bottom:10px;">Recent</div>
        <div id="sidebarChats"></div>
        <button class="new-chat-btn" id="resetCacheBtn" style="margin-top: 20px; background: #ff4444; color: white;">🔄 Reset Cache/SW</button>
        <div style="font-size:12px; color:var(--gemini-dim); padding-left:12px; margin:10px 0;">History</div>
        <div class="recent-item" style="opacity:0.6; cursor:default;"><b>Python Recursion Analysis</b></div>
        <div class="recent-item" style="opacity:0.6; cursor:default;"><b>Mughal Empire History</b></div>
        <div class="recent-item" style="opacity:0.6; cursor:default;"><b>React Virtual DOM Guide</b></div>
        <div class="recent-item" style="opacity:0.6; cursor:default;"><b>CSS Grid Layout Tips</b></div>
    </div>

    <div class="main-container">
        <div class="header">
            <div class="header-left">
                <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
                Gemini <span class="sparkle-logo">✦</span>
            </div>
            <div class="header-right">
                <div class="ai-pro-text">AI Pro</div>
                <div class="timer-container" id="timerContainer">
                    <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timerCircle" cx="17" cy="17" r="14"/></svg>
                    <button class="timer-btn" id="timerBtn">L</button>
                </div>
                <div class="online-status" id="onlineStatus">
                    <span class="online-indicator" id="onlineIndicator"></span>
                </div>
                <button class="logout-btn" id="logoutBtn" style="display: none;">Logout</button>
            </div>
        </div>

        <div class="chat-content" id="msgs">
            <div class="chat-inner" id="chatInner"></div>
        </div>

        <div class="thinking-area" id="thinkingArea">
            <div class="thinking-sparkle" id="thinkingSparkle">✦</div>
            <div class="thinking-content">
                <div class="thinking-main-text" id="thinkingMainText">Gemini is ready</div>
                <div class="shimmer-line"></div>
                <div class="status-log" id="statusLog">[Nodes_Synchronized]</div>
            </div>
        </div>

        <div class="input-outer">
            <div class="input-wrapper">
                <form id="chatForm" style="display:flex; flex:1; align-items:center;">
                    <input type="text" id="msgInput" placeholder="Enter a prompt here" autocomplete="off">
                    <button type="submit" class="send-btn">➤</button>
                </form>
            </div>
        </div>
    </div>

    <script src="main.js"></script>
</body>
</html>