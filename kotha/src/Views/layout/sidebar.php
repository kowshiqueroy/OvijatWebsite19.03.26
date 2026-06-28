<?php
// src/Views/layout/sidebar.php

$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\");

// Read and immediately consume the location prompt flag set by login
$needsLocationPrompt = false;
if (!empty($_SESSION['location_prompt'])) {
    $needsLocationPrompt = true;
    unset($_SESSION['location_prompt']);
}

// Deterministic avatar background from user ID or fallback string
function getAvatarBgById($id): string {
    $palette = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1','#0ea5e9','#14b8a6'];
    if (is_numeric($id)) {
        return $palette[intval($id) % count($palette)];
    }
    return $palette[abs(crc32(strval($id))) % count($palette)];
}
function sidebarAvatarBg(string $name): string {
    return getAvatarBgById($name);
}
$userAvatarBg = getAvatarBgById($_SESSION['user_id'] ?? 0);
?>
<div class="sidebar" id="appSidebar">
    <!-- Brand strip — single row, centered, animated -->
    <div style="display:flex;flex-direction:row;align-items:center;justify-content:center;
                gap:8px;padding:11px 14px 10px;border-bottom:1px solid rgba(0,242,254,.07);
                animation:brandSlideDown .55s cubic-bezier(.22,.6,.36,1) both;">

        <!-- Icon with glow pulse -->
        <img src="<?= $baseUrl ?>/public/img/icon.svg" alt="Kotha"
             style="width:22px;height:22px;flex-shrink:0;
                    filter:drop-shadow(0 0 6px rgba(0,242,254,.45));
                    animation:brandIconGlow 3s ease-in-out infinite;">

        <!-- KOTHA wordmark with shimmer -->
        <span style="font-size:0.9rem;font-weight:900;letter-spacing:3px;
                     background:linear-gradient(90deg,#00f2fe 0%,#4facfe 50%,#00f2fe 100%);
                     background-size:200% 100%;
                     -webkit-background-clip:text;-webkit-text-fill-color:transparent;
                     background-clip:text;
                     animation:brandShimmer 3s linear infinite;">KOTHA</span>

        <!-- Domain tag -->
        <span style="font-size:0.58rem;font-weight:500;letter-spacing:.8px;
                     color:rgba(0,242,254,.4);
                     animation:brandSubFade 1s .35s both;">
            .sohojweb.com
        </span>
    </div>
    <div class="sidebar-header">
        <div class="user-profile-summary" onclick="window.location.href='<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") ?>/dashboard'" title="Dashboard">
            <div class="avatar" style="background:<?= e($userAvatarBg) ?>;color:#fff;flex-shrink:0;"><i class="fa-solid fa-user" style="font-size:0.9rem;"></i></div>
            <div class="user-name-title" title="<?= e($userName) ?>"><?= e($userName) ?></div>
        </div>
        <div class="header-actions">
            <?php if ($isAdmin): ?>
                <a href="<?= $baseUrl ?>/admin" class="header-btn" title="Admin God-Mode"><i class="fa-solid fa-crown" style="color: var(--accent);"></i></a>
            <?php endif; ?>
            <!-- Notification Bell -->
            <button class="header-btn" id="notifBellBtn" title="Notifications" onclick="openNotifInbox()" style="position:relative;">
                <i class="fa-solid fa-bell"></i>
                <span id="notifBadge" style="display:none;position:absolute;top:2px;right:2px;background:#ef4444;color:#fff;
                    border-radius:50%;width:14px;height:14px;font-size:0.6rem;line-height:14px;text-align:center;font-weight:700;"></span>
            </button>
            <button class="header-btn" id="newChatBtn" title="New Chat"><i class="fa-solid fa-comment-medical"></i></button>
            <button class="header-btn" id="createGroupBtn" title="Create Group"><i class="fa-solid fa-users-gear"></i></button>
            
            <!-- Settings Dropdown Button -->
            <div class="settings-menu-container" style="position: relative; display: inline-block;">
                <button class="header-btn" id="settingsMenuBtn" title="Settings"><i class="fa-solid fa-gear"></i></button>
                <div class="attach-menu" id="settingsMenu" style="right: 0; left: auto; top: 125%; bottom: auto; min-width: 165px;">
                    <div class="attach-menu-item" onclick="openPasswordChangeModal()" style="padding: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-primary);">
                        <i class="fa-solid fa-key" style="color: var(--accent); width: 14px;"></i> Password
                    </div>
                    <div class="attach-menu-item" onclick="openPinChangeModal()" style="padding: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-primary);">
                        <i class="fa-solid fa-lock" style="color: var(--accent); width: 14px;"></i> Security PIN
                    </div>
                    <div class="attach-menu-item" onclick="clearLocalCache()" style="padding: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-primary);">
                        <i class="fa-solid fa-trash-can" style="color: var(--accent); width: 14px;"></i> Clear Cache
                    </div>
                </div>
            </div>
            
            <a href="<?= $baseUrl ?>/logout" class="header-btn" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>

    <!-- Search / Direct start -->
    <div class="search-wrapper">
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="chatSearchInput" class="search-input" placeholder="Search or start new chat...">
        </div>
    </div>

    <!-- Plan Usage Panel -->
    <div id="planUsageBar" class="plan-usage-panel" style="display:none;">
        <div class="plan-usage-head">

            <!-- Left: plan name + optional expiry -->
            <div class="plan-badge-group">
                <span id="planBadge" class="plan-badge">TRIAL</span>
                <span id="planExpiry" class="plan-expiry" style="display:none;"></span>
            </div>

            <!-- Centre: clickable overall progress bar -->
            <button class="plan-overall-bar" id="planOverallBar"
                    onclick="togglePlanDetails()"
                    title="Tap to see daily usage breakdown">
                <div class="plan-overall-track">
                    <div class="plan-overall-fill" id="planOverallFill"></div>
                </div>
                <span class="plan-overall-pct" id="planOverallPct">0%</span>
            </button>

            <!-- Right: upgrade button -->
            <button class="plan-upgrade-btn" onclick="openUpgradeModal()">
                <i class="fa-solid fa-circle-arrow-up"></i> Upgrade
            </button>
        </div>

        <!-- Detail rows — hidden until bar is tapped -->
        <div id="planUsageBars" class="plan-usage-list" style="display:none;"></div>
    </div>

    <!-- Active Chats List -->
    <div class="chats-list" id="chatsListContainer">
        <?php if (empty($activeChats)): ?>
            <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                No active conversations.<br>Click the new chat icon above to start!
            </div>
        <?php else: ?>
            <?php foreach ($activeChats as $chat): ?>
                <?php 
                    $isActive = isset($chatId) && ($chatId === $chat['chat_id']);
                    $unread = $chat['unread_count'] > 0;
                    $lastMsgText = '';
                    $lastMsgTime = '';
                    
                    if ($chat['last_message']) {
                        $lastMsg = $chat['last_message'];
                        // created_at is stored as UTC — convert to Asia/Dhaka before displaying
                        try {
                            $dt = new DateTime($lastMsg['created_at'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Asia/Dhaka'));
                            $lastMsgTime = $dt->format('h:i A');
                        } catch (Exception $e) {
                            $lastMsgTime = '';
                        }
                        if ($lastMsg['message_type'] === 'text') {
                            $lastMsgText = $lastMsg['content'];
                        } else {
                            $lastMsgText = '📎 [' . ucfirst($lastMsg['message_type']) . ']';
                        }
                    } else {
                        $lastMsgText = 'No messages yet';
                    }
                ?>
                <?php
                    $isGroup  = ($chat['chat_type'] === 'group');
                    $avLetter = strtoupper(mb_substr($chat['display_name'], 0, 1));
                    $avBg     = sidebarAvatarBg($chat['display_name']);
                    $isOnline = !$isGroup && !empty($chat['last_seen']) && $chat['is_online'];
                ?>
                <div class="chat-item <?= $isActive ? 'active' : '' ?>"
                     data-chat-id="<?= e($chat['chat_id']) ?>"
                     data-chat-type="<?= e($chat['chat_type']) ?>"
                     onclick="navigateToChat('<?= e($chat['chat_id']) ?>')">

                    <!-- Avatar with online ring / group badge -->
                    <div class="chat-item-avatar <?= $isGroup ? 'is-group' : '' ?>"
                         style="background:<?= $isGroup ? 'linear-gradient(135deg,#7c3aed,#a78bfa)' : getAvatarBgById($chat['partner_id'] ?? 0) ?>;color:#fff;position:relative;">
                        <?= $isGroup ? '<i class="fa-solid fa-users" style="font-size:.9rem;"></i>' : '<i class="fa-solid fa-user" style="font-size:.9rem;"></i>' ?>
                        <?php if (!$isGroup && !empty($chat['last_seen'])): ?>
                            <span class="presence-ring <?= $chat['is_online'] ? 'online' : '' ?>"
                                  id="dot-<?= e($chat['chat_id']) ?>"
                                  data-last-seen="<?= e($chat['last_seen']) ?>"></span>
                        <?php endif; ?>
                    </div>

                    <div class="chat-details">
                        <div class="chat-mid-box">
                            <span class="chat-title">
                                <span class="chat-title-text"><?= e($chat['display_name']) ?></span>
                                <!-- Info button (hover) — opens Chat Info modal -->
                                <button class="header-btn rename-btn"
                                        style="font-size:.7rem;width:20px;height:20px;opacity:0;transition:opacity .15s;"
                                        onclick="event.stopPropagation(); openChatInfo('<?= e($chat['chat_id']) ?>','<?= e($chat['chat_type']) ?>','<?= e($chat['display_name']) ?>',<?= $isGroup ? 'null' : intval($chat['partner_id'] ?? 0) ?>)"
                                        title="Chat Info / Nickname">
                                    <i class="fa-solid fa-circle-info"></i>
                                </button>
                            </span>
                            <span class="chat-preview" id="preview-<?= e($chat['chat_id']) ?>"
                                  data-chat-type="<?= e($chat['chat_type']) ?>"
                                  data-last-seen="<?= e($chat['last_seen'] ?? '') ?>"
                                  data-default-text="<?= e($lastMsgText) ?>">
                                Loading…
                            </span>
                            <?php if ($isGroup): ?>
                                <span class="group-online-pill" id="gcount-<?= e($chat['chat_id']) ?>"
                                      style="font-size:0.62rem;color:#22c55e;display:none;margin-top:1px;">
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="chat-right-box">
                            <span class="chat-time" id="time-<?= e($chat['chat_id']) ?>"><?= e($lastMsgTime) ?></span>
                            <?php if ($unread): ?>
                                <span class="unread-badge" id="unread-<?= e($chat['chat_id']) ?>"><?= $chat['unread_count'] ?></span>
                            <?php else: ?>
                                <span class="unread-badge-spacer" style="height:18px;width:1px;display:block;"></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hide button (appears on hover) -->
                    <button class="header-btn hide-chat-btn"
                            style="font-size:.65rem;width:18px;height:18px;position:absolute;right:6px;top:50%;transform:translateY(-50%);opacity:0;transition:opacity .15s;z-index:5;flex-shrink:0;"
                            onclick="event.stopPropagation(); toggleChatVisibility('<?= e($chat['chat_id']) ?>')"
                            title="Hide chat">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- People search results — shown below chats when typing in search box -->
    <div id="peopleSearchResults" style="display:none;border-top:1px solid var(--border-color);padding:8px 0;">
        <div style="padding:0 12px 6px;font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;">
            People
        </div>
    </div>
</div>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- New Chat Modal -->
<div class="modal" id="newChatModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Start a Chat</h3>
            <button class="header-btn" onclick="closeModal('newChatModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <div class="search-container" style="margin-bottom:12px;background-color:var(--bg-active);">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="userSearchInput" class="search-input"
                       placeholder="Type a name or email to search..." oninput="filterNewChatList(this.value)">
            </div>
            <!-- Empty-state hint -->
            <div id="newChatEmptyHint" style="text-align:center;padding:22px 0;color:var(--text-muted);font-size:0.83rem;">
                <i class="fa-solid fa-magnifying-glass" style="font-size:1.4rem;margin-bottom:8px;display:block;opacity:.35;"></i>
                Type above to search users
            </div>
            <div class="multi-select-list" id="usersList" style="max-height:260px;display:none;">
                <?php if (empty($availableUsers)): ?>
                    <p style="color:var(--text-muted);text-align:center;padding:15px;font-size:0.9rem;">No other approved users found.</p>
                <?php else: ?>
                    <?php foreach ($availableUsers as $user): ?>
                        <div class="select-item user-item-select"
                             data-name="<?= strtolower(e($user['full_name'])) ?>"
                             data-email="<?= strtolower(e($user['email'])) ?>"
                             onclick="startDirectChat(<?= $user['id'] ?>); closeModal('newChatModal');"
                             style="display:none;">
                            <div>
                                <div style="font-size:0.9rem;font-weight:500;">
                                    <i class="fa-solid fa-user" style="margin-right:8px;color:var(--text-secondary);font-size:0.8rem;"></i>
                                    <?= e($user['full_name']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-secondary);margin-left:18px;">
                                    <?= e($user['email']) ?><?= !empty($user['institute']) ? ' · ' . e($user['institute']) : '' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="newChatNoResults" style="text-align:center;padding:18px 0;color:var(--text-muted);font-size:0.83rem;display:none;">
                No matching users found.
            </div>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal" id="createGroupModal">
    <div class="modal-content">
        <form action="<?= $baseUrl ?>/group/create" method="POST">
            <div class="modal-header">
                <h3>Create Group Chat</h3>
                <button type="button" class="header-btn" onclick="closeModal('createGroupModal')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="group_name">Group Name</label>
                    <input type="text" id="group_name" name="group_name" placeholder="e.g. Security Audit Team" required>
                </div>

                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:8px;">Add Members</label>

                <!-- Checked-members summary bar -->
                <div id="grpCheckedBar" style="font-size:0.75rem;color:var(--accent);margin-bottom:6px;min-height:16px;"></div>

                <div class="search-container" style="margin-bottom:10px;background:var(--bg-active);">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="groupMemberSearch" class="search-input"
                           placeholder="Search members..." oninput="filterGroupMemberList(this.value)">
                </div>

                <div id="groupMemberEmptyHint" style="text-align:center;padding:16px 0;color:var(--text-muted);font-size:0.82rem;">
                    <i class="fa-solid fa-user-plus" style="font-size:1.3rem;opacity:.35;display:block;margin-bottom:8px;"></i>
                    Type to search members
                </div>

                <div class="multi-select-list" id="groupMemberList" style="max-height:200px;display:none;">
                    <?php if (empty($availableUsers)): ?>
                        <p style="color:var(--text-muted);text-align:center;padding:15px;font-size:0.85rem;">No users available.</p>
                    <?php else: ?>
                        <?php foreach ($availableUsers as $user): ?>
                            <label class="select-item group-member-select"
                                   data-name="<?= strtolower(e($user['full_name'])) ?>"
                                   data-email="<?= strtolower(e($user['email'])) ?>"
                                   style="display:none;align-items:center;cursor:pointer;">
                                <input type="checkbox" name="participants[]" value="<?= $user['id'] ?>"
                                       onchange="updateGrpCheckedBar()">
                                <span style="font-size:0.9rem;margin-left:10px;">
                                    <i class="fa-solid fa-user" style="margin-right:8px;color:var(--text-secondary);font-size:0.8rem;"></i>
                                    <?= e($user['full_name']) ?>
                                    <span style="font-size:0.72rem;color:var(--text-secondary);margin-left:4px;"><?= e($user['email']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-users"></i> Create Group</button>
            </div>
        </form>
    </div>
</div>

<!-- Password Change Modal -->
<div class="pin-modal" id="passwordChangeModal" style="display:none; z-index: 3000;">
    <div class="pin-card" style="max-width: 400px; width: 90%; text-align: left; background: var(--bg-panel); border: 1px solid var(--glass-border); border-radius: 20px; padding: 20px;">
        <h3 style="margin-bottom: 15px; text-align: center; font-family: 'Outfit', sans-serif;"><i class="fa-solid fa-key" style="color: var(--accent); margin-right: 8px;"></i>Change Password</h3>
        
        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">Current Password</label>
            <input type="password" id="changePassCurrent" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary);" required>
        </div>
        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">New Password</label>
            <input type="password" id="changePassNew" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary);" required>
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">Confirm New Password</label>
            <input type="password" id="changePassConfirm" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary);" required>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-primary" onclick="submitPasswordChange()">Update Password</button>
            <button class="btn btn-secondary" onclick="closePasswordChangeModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- PIN Change Modal -->
<div class="pin-modal" id="pinChangeModal" style="display:none; z-index: 3000;">
    <div class="pin-card" style="max-width: 400px; width: 90%; text-align: left; background: var(--bg-panel); border: 1px solid var(--glass-border); border-radius: 20px; padding: 20px;">
        <h3 style="margin-bottom: 15px; text-align: center; font-family: 'Outfit', sans-serif;"><i class="fa-solid fa-lock" style="color: var(--accent); margin-right: 8px;"></i>Change Security PIN</h3>
        
        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">Current 4-Digit PIN</label>
            <input type="password" id="changePinCurrent" maxlength="4" placeholder="••••" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary); text-align: center; letter-spacing: 4px;" required>
        </div>
        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">New 4-Digit PIN</label>
            <input type="password" id="changePinNew" maxlength="4" placeholder="••••" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary); text-align: center; letter-spacing: 4px;" required>
        </div>
        <div style="margin-bottom: 20px;">
            <label style="font-size: 0.8rem; color: var(--text-secondary); display:block; margin-bottom:4px;">Confirm New 4-Digit PIN</label>
            <input type="password" id="changePinConfirm" maxlength="4" placeholder="••••" style="width:100%; padding:8px 12px; background:var(--bg-header); border:1px solid var(--glass-border); border-radius:8px; color:var(--text-primary); text-align: center; letter-spacing: 4px;" required>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-primary" onclick="submitPinChange()">Update PIN</button>
            <button class="btn btn-secondary" onclick="closePinChangeModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Notification Inbox Modal -->
<div class="pin-modal" id="notifInboxModal" style="display:none; z-index: 3000;">
    <div class="pin-card" style="max-width:480px;width:92%;text-align:left;max-height:80vh;display:flex;flex-direction:column;padding:0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1rem;"><i class="fa-solid fa-bell" style="color:var(--accent);margin-right:8px;"></i>Notifications</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <button onclick="markAllNotifsRead()" style="background:none;border:none;color:var(--accent);cursor:pointer;font-size:0.75rem;font-family:'Outfit',sans-serif;">Mark all read</button>
                <button onclick="closeNotifInbox()" style="background:none;border:none;color:var(--text-primary);cursor:pointer;font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div id="notifInboxList" style="overflow-y:auto;flex:1;padding:12px;display:flex;flex-direction:column;gap:10px;">
            <p style="color:var(--text-muted);text-align:center;padding:30px 0;">Loading notifications...</p>
        </div>
    </div>
</div>

<!-- Upgrade Request Modal -->
<div class="pin-modal" id="upgradeModal" style="display:none; z-index: 3000;">
    <div class="pin-card" style="max-width:500px;width:92%;text-align:left;padding:0;overflow:hidden;max-height:88vh;display:flex;flex-direction:column;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
            <h3 style="margin:0;font-family:'Outfit',sans-serif;font-size:1rem;"><i class="fa-solid fa-rocket" style="color:var(--accent);margin-right:8px;"></i>Upgrade Plan</h3>
            <button onclick="closeUpgradeModal()" style="background:none;border:none;color:var(--text-primary);cursor:pointer;font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="overflow-y:auto;flex:1;padding:16px;">
            <!-- Plan comparison cards loaded by JS -->
            <div id="upgradePlanCards" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;"></div>
            <div style="margin-bottom:12px;">
                <label style="font-size:0.8rem;color:var(--text-secondary);display:block;margin-bottom:4px;">Requested Plan</label>
                <select id="upgradeTargetPlan" style="width:100%;padding:8px 12px;background:var(--bg-header);border:1px solid var(--glass-border);border-radius:8px;color:var(--text-primary);">
                    <option value="heavy">Heavy</option>
                    <option value="unlimited">Unlimited</option>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="font-size:0.8rem;color:var(--text-secondary);display:block;margin-bottom:4px;">Message to Admin (optional)</label>
                <textarea id="upgradeMessage" rows="3" placeholder="Briefly describe your need..."
                    style="width:100%;box-sizing:border-box;padding:8px 12px;background:var(--bg-header);border:1px solid var(--glass-border);border-radius:8px;color:var(--text-primary);resize:vertical;font-family:'Outfit',sans-serif;"></textarea>
            </div>
            <div id="upgradeRequestStatus" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:0.85rem;"></div>
            <button onclick="submitUpgradeRequest()"
                style="width:100%;padding:11px;background:linear-gradient(135deg,#00f2fe,#4facfe);border:none;border-radius:10px;color:#0b141a;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;font-size:0.9rem;">
                <i class="fa-solid fa-paper-plane"></i> Send Upgrade Request
            </button>
        </div>
    </div>
</div>

<!-- Incoming Call Modal -->
<div class="pin-modal" id="incomingCallModal" style="display:none; z-index: 3000;">
    <div class="pin-card" style="max-width: 400px; width: 90%; text-align: center; background: var(--bg-panel); border: 1px solid var(--glass-border); border-radius: 20px; padding: 25px;">
        <div style="font-size: 3rem; color: #22c55e; margin-bottom: 15px;">
            <i class="fa-solid fa-phone-volume" style="animation: blink 1s infinite;"></i>
        </div>
        <h3 id="incomingCallTitle" style="font-family: 'Outfit', sans-serif; margin-bottom: 8px;">Incoming Call</h3>
        <p id="incomingCallText" style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px;">Someone is calling you...</p>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button class="btn btn-primary" id="btnAcceptCall" style="background: linear-gradient(135deg, #22c55e 0%, #15803d 100%); border: none; font-weight: 600; padding: 10px 20px; border-radius: 10px; color: white; cursor: pointer;"><i class="fa-solid fa-phone"></i> Accept</button>
            <button class="btn btn-secondary" id="btnDeclineCall" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-primary); padding: 10px 20px; border-radius: 10px; cursor: pointer;" onclick="declineIncomingCall()"><i class="fa-solid fa-phone-slash"></i> Decline</button>
        </div>
    </div>
</div>

<script>
    function declineIncomingCall() {
        document.getElementById('incomingCallModal').style.display = 'none';
        window.pendingCallData = null;
    }

    function initSettingsMenu() {
        const settingsBtn = document.getElementById('settingsMenuBtn');
        const settingsMenu = document.getElementById('settingsMenu');
        if (settingsBtn && settingsMenu) {
            settingsBtn.onclick = function(e) {
                e.stopPropagation();
                settingsMenu.classList.toggle('show');
            };
            document.addEventListener('click', function() {
                settingsMenu.classList.remove('show');
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSettingsMenu);
    } else {
        initSettingsMenu();
    }

    // Settings Modals opening/closing
    function openPasswordChangeModal() {
        document.getElementById('passwordChangeModal').style.display = 'flex';
    }
    function closePasswordChangeModal() {
        document.getElementById('passwordChangeModal').style.display = 'none';
        document.getElementById('changePassCurrent').value = '';
        document.getElementById('changePassNew').value = '';
        document.getElementById('changePassConfirm').value = '';
    }

    function openPinChangeModal() {
        document.getElementById('pinChangeModal').style.display = 'flex';
    }
    function closePinChangeModal() {
        document.getElementById('pinChangeModal').style.display = 'none';
        document.getElementById('changePinCurrent').value = '';
        document.getElementById('changePinNew').value = '';
        document.getElementById('changePinConfirm').value = '';
    }

    function submitPasswordChange() {
        const currentPass = document.getElementById('changePassCurrent').value.trim();
        const newPass     = document.getElementById('changePassNew').value.trim();
        const confirmPass = document.getElementById('changePassConfirm').value.trim();

        if (!currentPass || !newPass || !confirmPass) {
            showToast('Please fill in all password fields.', 'warning'); return;
        }
        if (newPass !== confirmPass) {
            showToast('New passwords do not match.', 'error'); return;
        }
        if (newPass.length < 6) {
            showToast('New password must be at least 6 characters.', 'warning'); return;
        }

        const fd = new FormData();
        fd.append('current_password', currentPass);
        fd.append('new_password', newPass);

        const baseUrl = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") ?>';
        fetch(`${baseUrl}/api/settings/password`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Password updated successfully!', 'success');
                closePasswordChangeModal();
            } else {
                showToast('Error: ' + data.error, 'error');
            }
        })
        .catch(() => showToast('Failed to update password.', 'error'));
    }

    function submitPinChange() {
        const currentPin = document.getElementById('changePinCurrent').value.trim();
        const newPin     = document.getElementById('changePinNew').value.trim();
        const confirmPin = document.getElementById('changePinConfirm').value.trim();

        if (!currentPin || !newPin || !confirmPin) {
            showToast('Please fill in all PIN fields.', 'warning'); return;
        }
        if (newPin !== confirmPin) {
            showToast('New PINs do not match.', 'error'); return;
        }
        if (!/^\d{4}$/.test(newPin)) {
            showToast('PIN must be exactly 4 digits.', 'warning'); return;
        }

        const fd = new FormData();
        fd.append('current_pin', currentPin);
        fd.append('new_pin', newPin);

        const baseUrl = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") ?>';
        fetch(`${baseUrl}/api/settings/pin`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('PIN updated successfully!', 'success');
                closePinChangeModal();
            } else {
                showToast('Error: ' + data.error, 'error');
            }
        })
        .catch(() => showToast('Failed to update PIN.', 'error'));
    }

    function clearLocalCache() {
        showConfirm('Clear your local cache? This will erase local storage and reload the app.', () => {
            localStorage.clear();
            sessionStorage.clear();
            showToast('Cache cleared. Reloading...', 'success');
            setTimeout(() => window.location.reload(), 800);
        });
    }

    /* ── New Chat modal: filter on type, hidden by default ── */
    function filterNewChatList(val) {
        val = val.trim().toLowerCase();
        const items   = document.querySelectorAll('#usersList .user-item-select');
        const listEl  = document.getElementById('usersList');
        const hint    = document.getElementById('newChatEmptyHint');
        const noRes   = document.getElementById('newChatNoResults');

        if (!val) {
            listEl.style.display  = 'none';
            hint.style.display    = 'block';
            noRes.style.display   = 'none';
            items.forEach(el => el.style.display = 'none');
            return;
        }

        let found = 0;
        items.forEach(el => {
            const matches = el.getAttribute('data-name').includes(val) ||
                            el.getAttribute('data-email').includes(val);
            el.style.display = matches ? 'flex' : 'none';
            if (matches) found++;
        });

        hint.style.display   = 'none';
        listEl.style.display = found > 0 ? 'block' : 'none';
        noRes.style.display  = found === 0 ? 'block' : 'none';
    }

    /* ── Create Group modal: filter on type, hidden by default ── */
    function filterGroupMemberList(val) {
        val = val.trim().toLowerCase();
        const items   = document.querySelectorAll('#groupMemberList .group-member-select');
        const listEl  = document.getElementById('groupMemberList');
        const hint    = document.getElementById('groupMemberEmptyHint');

        if (!val) {
            listEl.style.display = 'none';
            hint.style.display   = 'block';
            items.forEach(el => el.style.display = 'none');
            return;
        }

        let found = 0;
        items.forEach(el => {
            const matches = el.getAttribute('data-name').includes(val) ||
                            el.getAttribute('data-email').includes(val);
            el.style.display = matches ? 'flex' : 'none';
            if (matches) found++;
        });

        listEl.style.display = found > 0 ? 'block' : 'none';
        hint.style.display   = found === 0 ? 'block' : 'none';
        if (found === 0) hint.innerHTML = '<i class="fa-solid fa-user-slash" style="font-size:1.3rem;opacity:.35;display:block;margin-bottom:8px;"></i>No matching users';
    }

    function updateGrpCheckedBar() {
        const checked = [...document.querySelectorAll('#groupMemberList input[type=checkbox]:checked')]
                        .map(cb => cb.closest('.group-member-select')?.querySelector('span')?.innerText?.split('\n')[0]?.trim())
                        .filter(Boolean);
        const bar = document.getElementById('grpCheckedBar');
        if (bar) bar.textContent = checked.length ? `${checked.length} selected: ${checked.join(', ')}` : '';
    }

    // Reset modals when opened so search is fresh
    const _origNewChatOpen = window.openModal;
    window.openModal = function(id) {
        if (id === 'newChatModal') {
            const inp = document.getElementById('userSearchInput');
            if (inp) { inp.value = ''; filterNewChatList(''); }
        }
        if (id === 'createGroupModal') {
            const inp = document.getElementById('groupMemberSearch');
            if (inp) { inp.value = ''; filterGroupMemberList(''); updateGrpCheckedBar(); }
        }
        if (_origNewChatOpen) _origNewChatOpen(id);
        else { const el = document.getElementById(id); if (el) el.classList.add('show'); }
    };
</script>

<!-- ── Chat Info Modal ─────────────────────────────────────── -->
<div class="pin-modal" id="chatInfoModal" style="display:none;z-index:3000;">
<div class="pin-card" style="max-width:480px;width:92%;text-align:left;max-height:88vh;display:flex;flex-direction:column;padding:0;overflow:hidden;">

    <!-- Header -->
    <div style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <div>
            <h3 id="ciTitle" style="margin:0;font-size:0.95rem;font-family:'Outfit',sans-serif;"></h3>
            <div id="ciSubtitle" style="font-size:0.72rem;color:var(--text-secondary);margin-top:2px;"></div>
        </div>
        <button onclick="closeChatInfo()" style="background:none;border:none;color:var(--text-primary);cursor:pointer;font-size:1.1rem;"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Loading spinner -->
    <div id="ciLoading" style="text-align:center;padding:30px;color:var(--text-muted);font-size:0.85rem;">
        <i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent);margin-bottom:8px;display:block;font-size:1.4rem;"></i>
        Loading...
    </div>

    <!-- Scrollable content (hidden until loaded) -->
    <div id="ciContent" style="overflow-y:auto;flex:1;padding:16px;display:none;">

        <!-- Nickname for this chat/group -->
        <div style="margin-bottom:16px;">
            <label id="ciNickLabel" style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-bottom:5px;"></label>
            <div style="display:flex;gap:8px;">
                <input id="ciNickInput" type="text" placeholder="Enter nickname..."
                    style="flex:1;padding:8px 12px;background:var(--bg-header);border:1px solid var(--glass-border);border-radius:8px;color:var(--text-primary);font-family:'Outfit',sans-serif;font-size:0.85rem;">
                <button onclick="saveChatNickname()"
                    style="background:linear-gradient(135deg,#00f2fe,#4facfe);border:none;border-radius:8px;padding:8px 14px;color:#0b141a;font-weight:700;cursor:pointer;font-size:0.8rem;font-family:'Outfit',sans-serif;">
                    Save
                </button>
            </div>
        </div>

        <!-- Members section (group only) -->
        <div id="ciMembersSection" style="display:none;">
            <div style="font-size:0.75rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px;">
                <i class="fa-solid fa-users"></i>
                Members (<span id="ciMemberCount">0</span>)
                &nbsp;<span id="ciGroupOnline" style="font-weight:400;color:#22c55e;font-size:0.7rem;"></span>
            </div>
            <div id="ciMembersList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;"></div>

            <!-- Add member search -->
            <div style="border-top:1px solid var(--border-color);padding-top:12px;">
                <div style="font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:6px;">Add Member</div>
                <div style="position:relative;">
                    <input id="ciAddMemberInput" type="text" placeholder="Search users to add..."
                        oninput="filterCiAddMember(this.value)"
                        style="width:100%;box-sizing:border-box;padding:7px 12px;background:var(--bg-header);border:1px solid var(--glass-border);border-radius:8px;color:var(--text-primary);font-family:'Outfit',sans-serif;font-size:0.83rem;">
                </div>
                <div id="ciAddMemberResults" style="max-height:140px;overflow-y:auto;margin-top:6px;"></div>
            </div>
        </div>

        <!-- Direct chat partner section -->
        <div id="ciPartnerSection" style="display:none;">
            <!-- partner info shown here by JS -->
        </div>

        <!-- Hide / Unhide chat -->
        <div style="border-top:1px solid var(--border-color);padding-top:14px;margin-top:14px;">
            <button id="ciHideBtn" onclick="toggleHideCurrentChat()"
                style="width:100%;padding:9px;background:rgba(255,255,255,.04);border:1px solid var(--border-color);border-radius:9px;color:var(--text-secondary);font-family:'Outfit',sans-serif;font-size:0.83rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <i class="fa-solid fa-eye-slash"></i>
                <span id="ciHideLabel">Hide Chat</span>
            </button>
        </div>
    </div>
</div>
</div>

<!-- JS data injected into window scope for sidebar search & chat info -->
<script>
window.AVAILABLE_USERS = <?= json_encode(array_map(function($u) {
    return [
        'id'        => (int)$u['id'],
        'full_name' => $u['full_name'],
        'email'     => $u['email'],
        'institute' => $u['institute'] ?? '',
    ];
}, $availableUsers ?? [])) ?>;

/* hover CSS: show hide button and info button on chat-item hover */
(function() {
    if (document.getElementById('_ciHoverStyle')) return;
    const s = document.createElement('style');
    s.id = '_ciHoverStyle';
    s.textContent = `
        .chat-item { position: relative; }
        .chat-item:hover .hide-chat-btn { opacity: 0.6 !important; }
        .chat-item:hover .rename-btn    { opacity: 1  !important; }
        .hide-chat-btn:hover            { opacity: 1  !important; color: var(--accent) !important; }
        .group-online-pill              { display: block; }
    `;
    document.head.appendChild(s);
})();

// Expose location prompt flag to JS
window.LOCATION_PROMPT = <?= $needsLocationPrompt ? 'true' : 'false' ?>;
window.CURRENT_USER_NAME_FULL = '<?= e($_SESSION['user_name'] ?? '') ?>';
</script>

<!-- ── Location Permission Modal ─────────────────────────────── -->
<div id="locPermModal" style="display:none;position:fixed;inset:0;z-index:10000;
     background:rgba(7,13,20,.92);backdrop-filter:blur(16px);
     align-items:center;justify-content:center;padding:20px;">
    <div style="background:rgba(11,20,30,.95);border:1px solid rgba(0,242,254,.2);
                border-radius:22px;padding:32px 28px;max-width:380px;width:100%;
                text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.7);">
        <div style="width:72px;height:72px;border-radius:50%;
                    background:linear-gradient(135deg,rgba(0,242,254,.15),rgba(79,172,254,.1));
                    border:2px solid rgba(0,242,254,.3);
                    display:flex;align-items:center;justify-content:center;
                    margin:0 auto 20px;animation:locPulse 2.5s ease-in-out infinite;">
            <i class="fa-solid fa-location-dot" style="font-size:1.9rem;color:#00f2fe;"></i>
        </div>
        <h3 style="font-family:'Outfit',sans-serif;font-size:1.1rem;font-weight:800;
                   color:#fff;margin-bottom:10px;letter-spacing:-.3px;">
            Location Verification Required
        </h3>
        <p style="font-size:.82rem;color:rgba(255,255,255,.45);line-height:1.65;margin-bottom:6px;font-weight:300;">
            For corporate security compliance, Kotha requires a one-time location verification each session.
        </p>
        <p style="font-size:.75rem;color:rgba(0,242,254,.5);line-height:1.6;margin-bottom:26px;">
            <i class="fa-solid fa-shield-halved" style="margin-right:5px;"></i>
            Your coordinates are encrypted and visible only to your administrator.
        </p>
        <button onclick="requestUserLocation()" id="locAllowBtn"
            style="width:100%;padding:13px;background:linear-gradient(135deg,#00f2fe,#4facfe);
                   border:none;border-radius:12px;color:#040d14;font-family:'Outfit',sans-serif;
                   font-size:.9rem;font-weight:700;cursor:pointer;
                   box-shadow:0 6px 24px rgba(0,242,254,.3);transition:all .2s;
                   display:flex;align-items:center;justify-content:center;gap:8px;">
            <i class="fa-solid fa-location-dot"></i> Allow Location Access
        </button>
        <p style="font-size:.67rem;color:rgba(255,255,255,.2);margin-top:12px;line-height:1.5;text-align:center;">
            <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
            Location access is mandatory for corporate security compliance. You cannot proceed without it.
        </p>
    </div>
</div>

<!-- ── Location DENIED — Full-Screen Warning ─────────────────── -->
<div id="locDeniedScreen" style="display:none;position:fixed;inset:0;z-index:10001;
     background:#070d14;align-items:center;justify-content:center;
     flex-direction:column;padding:24px;text-align:center;">
    <!-- Animated warning pulse -->
    <div style="position:relative;margin-bottom:28px;">
        <div style="width:90px;height:90px;border-radius:50%;background:rgba(239,68,68,.08);
                    border:2px solid rgba(239,68,68,.25);
                    display:flex;align-items:center;justify-content:center;margin:0 auto;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:2.2rem;color:#ef4444;"></i>
        </div>
        <div style="position:absolute;inset:-8px;border-radius:50%;border:1.5px solid rgba(239,68,68,.15);animation:locRipple 2s linear infinite;"></div>
        <div style="position:absolute;inset:-20px;border-radius:50%;border:1px solid rgba(239,68,68,.07);animation:locRipple 2s linear .7s infinite;"></div>
    </div>
    <h2 style="font-family:'Outfit',sans-serif;font-size:1.3rem;font-weight:800;color:#fff;
               margin-bottom:12px;max-width:340px;">
        Location Access Denied
    </h2>
    <p style="font-size:.85rem;color:rgba(255,255,255,.4);line-height:1.7;max-width:320px;margin-bottom:8px;font-weight:300;">
        This session has been flagged for <strong style="color:rgba(239,68,68,.8);">location non-compliance</strong>.
        Your administrator will be notified of this denial.
    </p>
    <p style="font-size:.75rem;color:rgba(255,255,255,.25);line-height:1.6;max-width:300px;margin-bottom:28px;">
        To enable location: open your browser settings → Site Settings → Location → Allow for this site.
    </p>
    <div style="display:flex;flex-direction:column;gap:10px;width:100%;max-width:280px;">
        <button onclick="retryLocation()" id="locRetryBtn"
            style="padding:13px;background:linear-gradient(135deg,#ef4444,#b91c1c);
                   border:none;border-radius:12px;color:#fff;font-family:'Outfit',sans-serif;
                   font-size:.88rem;font-weight:700;cursor:pointer;
                   display:flex;align-items:center;justify-content:center;gap:8px;
                   box-shadow:0 6px 20px rgba(239,68,68,.3);">
            <i class="fa-solid fa-rotate-right"></i> Try Again
        </button>
        <button onclick="continueWithoutLocation()"
            style="padding:10px;background:transparent;border:1px solid rgba(255,255,255,.07);
                   border-radius:10px;color:rgba(255,255,255,.25);font-family:'Outfit',sans-serif;
                   font-size:.75rem;cursor:pointer;">
            Continue without location (not recommended)
        </button>
    </div>
    <div style="margin-top:24px;font-size:.68rem;color:rgba(255,255,255,.15);line-height:1.7;max-width:280px;">
        <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
        Denial count: <span id="locDenyCount" style="color:rgba(239,68,68,.5);font-weight:700;">—</span>
        &nbsp;·&nbsp; Your administrator reviews all location denials.
    </div>
</div>

<style>
/* Brand strip animations */
@keyframes brandSlideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
@keyframes brandIconGlow{0%,100%{filter:drop-shadow(0 0 4px rgba(0,242,254,.3))}50%{filter:drop-shadow(0 0 14px rgba(0,242,254,.85))}}
@keyframes brandShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes brandSubFade{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}

/* Location animations */
@keyframes locPulse{0%,100%{box-shadow:0 0 0 0 rgba(0,242,254,.25)}60%{box-shadow:0 0 0 16px rgba(0,242,254,0)}}
@keyframes locRipple{0%{transform:scale(1);opacity:.6}100%{transform:scale(1.4);opacity:0}}
</style>
