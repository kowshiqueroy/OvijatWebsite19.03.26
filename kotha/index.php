<?php
// index.php

require_once __DIR__ . '/config.php';

// Initialize router
$router = new Router();

// ----------------------------------------------------
// Public Routes
// ----------------------------------------------------
$router->add('GET', '/', 'AuthController@landing');
$router->add('GET', '/login', 'AuthController@showLogin');
$router->add('POST', '/login', 'AuthController@login');
$router->add('GET', '/registration', 'AuthController@showRegistration');
$router->add('POST', '/registration', 'AuthController@register');
$router->add('GET', '/logout', 'AuthController@logout');

// ----------------------------------------------------
// Authenticated User Routes (Dashboard & Chat)
// ----------------------------------------------------
$router->add('GET', '/dashboard', 'ChatController@dashboard');
$router->add('GET', '/chat/{chatId}', 'ChatController@chatInterface');

// Group Creation
$router->add('POST', '/group/create', 'ChatController@createGroup');

// ----------------------------------------------------
// Chat & Messaging APIs
// ----------------------------------------------------
$router->add('GET', '/api/chats', 'ChatController@getChats');
$router->add('POST', '/api/chats/create', 'ChatController@startChat');
$router->add('POST', '/api/chats/verify-pin', 'ChatController@verifyPin');
$router->add('POST', '/api/chats/typing', 'ChatController@setTypingStatus');
$router->add('GET', '/api/messages/{chatId}', 'ChatController@getMessages');
$router->add('POST', '/api/messages/upload-chunk', 'ChatController@uploadChunk');
$router->add('POST', '/api/messages/{chatId}', 'ChatController@sendMessage');
$router->add('POST', '/api/messages/delete/{chatId}/{messageId}', 'ChatController@deleteMessage');
$router->add('POST', '/api/chats/nickname', 'ChatController@setNickname');
$router->add('GET',  '/api/chats/{chatId}/info',       'ChatController@getChatInfo');
$router->add('POST', '/api/group/{chatId}/members/add',    'ChatController@addGroupMember');
$router->add('POST', '/api/group/{chatId}/members/remove', 'ChatController@removeGroupMember');
$router->add('POST', '/api/settings/password', 'AuthController@changePassword');
$router->add('POST', '/api/settings/pin', 'AuthController@changePin');

// Location tracking
$router->add('POST', '/api/location/save',              'ChatController@saveLocation');
$router->add('POST', '/api/location/deny',              'ChatController@denyLocation');
$router->add('GET',  '/admin/locations',                'AdminController@listLocations');
$router->add('GET',  '/admin/user/{userId}/locations',  'AdminController@getUserLocations');
$router->add('POST', '/admin/settings/auto-approve',    'AdminController@toggleAutoApprove');

// ----------------------------------------------------
// SSE (Server-Sent Events) for Real-Time Updates
// ----------------------------------------------------
$router->add('GET', '/api/sse', 'ChatController@sseStream');

// ----------------------------------------------------
// Presence API (heartbeat + sidebar refresh)
// ----------------------------------------------------
$router->add('POST', '/api/presence/ping', 'ChatController@presencePing');
$router->add('GET',  '/api/presence',      'ChatController@getPresence');

// ----------------------------------------------------
// Plan / Usage / Notifications (user-facing)
// ----------------------------------------------------
$router->add('GET',  '/api/plan/status',          'PlanController@status');
$router->add('GET',  '/api/plan/templates',       'PlanController@templates');
$router->add('POST', '/api/plan/request-upgrade', 'PlanController@requestUpgrade');
$router->add('GET',  '/api/notifications',        'PlanController@getNotifications');
$router->add('POST', '/api/notifications/read/{id}', 'PlanController@markRead');
$router->add('POST', '/api/notifications/read-all',  'PlanController@markAllRead');
$router->add('GET',  '/api/plan/unread-count',    'PlanController@unreadCount');

// ----------------------------------------------------
// Calling (PeerJS Integration)
// ----------------------------------------------------
$router->add('GET', '/call', 'CallController@callInterface');
$router->add('POST', '/api/call/save-record', 'CallController@saveCallRecord');

// ----------------------------------------------------
// Admin God-Mode Routes
// ----------------------------------------------------
$router->add('GET', '/admin', 'AdminController@dashboard');
$router->add('POST', '/admin/users/approve/{userId}', 'AdminController@approveUser');
$router->add('POST', '/admin/users/block/{userId}', 'AdminController@blockUser');
$router->add('POST', '/admin/users/delete/{userId}', 'AdminController@deleteUser');
$router->add('GET', '/admin/chat/{chatId}', 'AdminController@viewChatHistory');
$router->add('POST', '/admin/chat/delete/{chatId}/{messageId}', 'AdminController@deleteChatMessage');
$router->add('GET',  '/admin/users/list',                       'AdminController@listUsers');
$router->add('GET',  '/admin/plans/users',                     'AdminController@listUsersWithPlans');
$router->add('POST', '/admin/plans/set/{userId}',              'AdminController@setPlan');
$router->add('POST', '/admin/plans/templates/update',          'AdminController@updatePlanTemplate');
$router->add('POST', '/admin/upgrade-requests/handle/{id}',   'AdminController@handleUpgradeRequest');
$router->add('POST', '/admin/notifications/send',              'AdminController@sendNotification');
$router->add('GET',  '/admin/recordings',                    'AdminController@listRecordings');
$router->add('GET',  '/admin/recording/play/{id}',           'AdminController@playRecording');
$router->add('GET',  '/admin/recording/live/{id}',           'AdminController@liveStream');
$router->add('POST', '/admin/recording/delete/{id}',         'AdminController@deleteRecording');
$router->add('POST', '/admin/recordings/delete-all',         'AdminController@deleteAllRecordings');
$router->add('POST', '/admin/chats/purge-vanished',          'AdminController@purgeVanishedMessages');
$router->add('POST', '/admin/chats/purge-messages/{chatId}', 'AdminController@purgeChatMessages');
$router->add('POST', '/admin/chats/purge-all',               'AdminController@purgeAllChatsMessages');

// Storage management — orphaned recordings + chat media files
$router->add('GET',  '/admin/storage/orphaned',              'AdminController@listOrphanedRecordings');
$router->add('POST', '/admin/storage/delete-orphaned',       'AdminController@deleteOrphanedRecording');
$router->add('POST', '/admin/storage/delete-all-orphaned',   'AdminController@deleteAllOrphanedRecordings');
$router->add('GET',  '/admin/storage/media',                 'AdminController@listChatMedia');
$router->add('POST', '/admin/storage/delete-media',          'AdminController@deleteMediaFile');
$router->add('POST', '/admin/storage/delete-all-media',      'AdminController@deleteAllChatMedia');
$router->add('POST', '/admin/storage/delete-orphaned-media', 'AdminController@deleteOrphanedChatMedia');

// Dispatch request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
