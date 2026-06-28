<?php
// src/Controller/PlanController.php

namespace Controller;

use Models\Plan;

class PlanController extends AuthController {

    private function checkAuth(): void {
        if (!isset($_SESSION['user_id']) || \Models\User::getById($_SESSION['user_id']) === null) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
    }

    /** GET /api/plan/status — current plan + today's usage for the logged-in user */
    public function status(): void {
        $this->checkAuth();
        $userId  = $_SESSION['user_id'];
        $summary = Plan::getUsageSummary($userId);
        $request = Plan::getUserRequest($userId);

        header('Content-Type: application/json');
        echo json_encode([
            'success'         => true,
            'summary'         => $summary,
            'upgrade_request' => $request,
        ]);
        exit;
    }

    /** GET /api/plan/templates — plan tier info + contact details (for upgrade modal) */
    public function templates(): void {
        $this->checkAuth();
        header('Content-Type: application/json');
        echo json_encode(['templates' => Plan::getAllTemplates()]);
        exit;
    }

    /** POST /api/plan/request-upgrade */
    public function requestUpgrade(): void {
        $this->checkAuth();
        $userId  = $_SESSION['user_id'];
        $plan    = trim($_POST['plan']   ?? '');
        $message = trim($_POST['message'] ?? '');

        header('Content-Type: application/json');

        if (!in_array($plan, ['heavy', 'unlimited'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid plan requested.']);
            exit;
        }

        $ok = Plan::submitUpgradeRequest($userId, $plan, $message);
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'You already have a pending upgrade request.']);
        }
        exit;
    }

    /** GET /api/notifications — notifications for the current user */
    public function getNotifications(): void {
        $this->checkAuth();
        $userId = $_SESSION['user_id'];
        $notifs = Plan::getNotificationsForUser($userId);
        $unread = Plan::countUnread($userId);

        header('Content-Type: application/json');
        echo json_encode(['notifications' => $notifs, 'unread' => $unread]);
        exit;
    }

    /** POST /api/notifications/read/{id} */
    public function markRead(string $id): void {
        $this->checkAuth();
        Plan::markNotificationRead($_SESSION['user_id'], (int)$id);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(): void {
        $this->checkAuth();
        Plan::markAllRead($_SESSION['user_id']);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    /** GET /api/plan/unread-count — lightweight badge refresh */
    public function unreadCount(): void {
        $this->checkAuth();
        header('Content-Type: application/json');
        echo json_encode(['unread' => Plan::countUnread($_SESSION['user_id'])]);
        exit;
    }
}
