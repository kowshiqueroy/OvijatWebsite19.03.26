<?php
require_once 'config.php';

$setup_pin = "5877";
$msg = "";
$authenticated = false;

if (isset($_POST['pin']) && $_POST['pin'] === $setup_pin) {
    $_SESSION['setup_authenticated'] = true;
    $authenticated = true;
} elseif (isset($_SESSION['setup_authenticated']) && $_SESSION['setup_authenticated'] === true) {
    $authenticated = true;
}

if (!$authenticated) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup &mdash; Security Required</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7f6; height:100vh; display:flex; align-items:center; justify-content:center; }
        .pin-card { max-width:400px; width:100%; padding:30px; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,.1); background:#fff; }
    </style>
</head>
<body>
    <div class="pin-card text-center">
        <h3 class="mb-4">Setup Security</h3>
        <p class="text-muted mb-4">Enter the 4-digit PIN to access system setup.</p>
        <form method="POST">
            <input type="password" name="pin" maxlength="4" class="form-control form-control-lg text-center mb-3" placeholder="Enter PIN" required autofocus>
            <button type="submit" class="btn btn-primary btn-lg w-100">Unlock</button>
        </form>
    </div>
</body>
</html>
<?php exit; }

/* ---------------------------------------------------------------
   ACTION HANDLERS
--------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'setup') {

        $tables = [];

        /* ── CORE TABLES ── */

        // 1. companies
        $tables[] = "CREATE TABLE IF NOT EXISTS companies (
            id         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(255) NOT NULL,
            address    VARCHAR(255),
            phone      VARCHAR(50),
            email      VARCHAR(100),
            website    VARCHAR(100),
            logo       VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 2. users  (roles: 0=Super Admin, 1=Manager, 2=SR, 9=Viewer)
        $tables[] = "CREATE TABLE IF NOT EXISTS users (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(50)  NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            role        TINYINT(1)   NOT NULL DEFAULT 2,
            company_id  INT(11) UNSIGNED,
            status      TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login  TIMESTAMP NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 3. divisions
        $tables[] = "CREATE TABLE IF NOT EXISTS divisions (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            description TEXT,
            company_id  INT(11) UNSIGNED NOT NULL,
            manager_id  INT(11) UNSIGNED,
            status      TINYINT(1) DEFAULT 1,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by  INT(11) UNSIGNED,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 4. sales_groups
        $tables[] = "CREATE TABLE IF NOT EXISTS sales_groups (
            id           INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(255) NOT NULL,
            description  TEXT,
            division_id  INT(11) UNSIGNED NOT NULL,
            company_id   INT(11) UNSIGNED NOT NULL,
            team_lead_id INT(11) UNSIGNED,
            status       TINYINT(1) DEFAULT 1,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by   INT(11) UNSIGNED,
            FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id)  REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 5. user_group_assignments  (which SR belongs to which group)
        $tables[] = "CREATE TABLE IF NOT EXISTS user_group_assignments (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT(11) UNSIGNED NOT NULL,
            group_id    INT(11) UNSIGNED NOT NULL,
            company_id  INT(11) UNSIGNED NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT(11) UNSIGNED,
            is_active   TINYINT(1) DEFAULT 1,
            FOREIGN KEY (user_id)    REFERENCES users(id)        ON DELETE CASCADE,
            FOREIGN KEY (group_id)   REFERENCES sales_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id)    ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 6. routes
        $tables[] = "CREATE TABLE IF NOT EXISTS routes (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_name  VARCHAR(255) NOT NULL,
            company_id  INT(11) UNSIGNED NOT NULL,
            status      TINYINT(1) DEFAULT 1,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 7. shops
        $tables[] = "CREATE TABLE IF NOT EXISTS shops (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            shop_name   VARCHAR(255) NOT NULL,
            route_id    INT(11) UNSIGNED NOT NULL,
            company_id  INT(11) UNSIGNED NOT NULL,
            user_id     INT(11) UNSIGNED,
            balance     DECIMAL(15,2) DEFAULT 0,
            status      TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id)   REFERENCES routes(id)    ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 8. items
        $tables[] = "CREATE TABLE IF NOT EXISTS items (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_name   VARCHAR(255)  NOT NULL,
            price       DECIMAL(15,2) NOT NULL,
            stock       INT(11) DEFAULT 0,
            company_id  INT(11) UNSIGNED NOT NULL,
            status      TINYINT(1) DEFAULT 1,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 9. orders  (lat/lng removed; truck_load_id added)
        $tables[] = "CREATE TABLE IF NOT EXISTS orders (
            id            INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id      INT(11) UNSIGNED NOT NULL,
            shop_id       INT(11) UNSIGNED NOT NULL,
            truck_load_id INT(11) UNSIGNED DEFAULT NULL,
            order_date    DATE NOT NULL,
            delivery_date DATE NOT NULL,
            order_status  TINYINT(1) DEFAULT 0,
            remarks       VARCHAR(255),
            company_id    INT(11) UNSIGNED NOT NULL,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by    INT(11) UNSIGNED,
            updated_by    INT(11) UNSIGNED,
            approved_at   TIMESTAMP NULL,
            approved_by   INT(11) UNSIGNED,
            status        TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id)   REFERENCES routes(id)    ON DELETE CASCADE,
            FOREIGN KEY (shop_id)    REFERENCES shops(id)     ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_company_date   (company_id, order_date),
            INDEX idx_company_status (company_id, order_status),
            INDEX idx_created_by     (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 10. order_items
        $tables[] = "CREATE TABLE IF NOT EXISTS order_items (
            id         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id   INT(11) UNSIGNED NOT NULL,
            item_id    INT(11) UNSIGNED NOT NULL,
            quantity   INT(11) NOT NULL,
            price      DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id)  REFERENCES items(id)  ON DELETE CASCADE,
            INDEX idx_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 11. cash_collections
        $tables[] = "CREATE TABLE IF NOT EXISTS cash_collections (
            id              INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id        INT(11) UNSIGNED NOT NULL,
            shop_id         INT(11) UNSIGNED NOT NULL,
            amount          DECIMAL(15,2) NOT NULL,
            collection_date TIMESTAMP,
            collected_by    INT(11) UNSIGNED NOT NULL,
            remarks         VARCHAR(255),
            approved_at     TIMESTAMP NULL,
            approved_by     INT(11) UNSIGNED,
            company_id      INT(11) UNSIGNED NOT NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status          TINYINT(1) DEFAULT 1,
            FOREIGN KEY (shop_id)    REFERENCES shops(id)    ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            INDEX idx_company_date (company_id, collection_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 12. surveys
        $tables[] = "CREATE TABLE IF NOT EXISTS surveys (
            id              INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            survey_name     VARCHAR(255) NOT NULL,
            survey_type     VARCHAR(255) NOT NULL,
            survey_address  VARCHAR(255) NOT NULL,
            survey_phone    VARCHAR(255) NOT NULL,
            route_id        INT(11) UNSIGNED NOT NULL,
            company_id      INT(11) UNSIGNED NOT NULL,
            user_id         INT(11) UNSIGNED,
            balance         DECIMAL(15,2) DEFAULT 0,
            status          TINYINT(1) DEFAULT 1,
            FOREIGN KEY (route_id)   REFERENCES routes(id)    ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 13. serials  (kept for legacy data; new loads use truck_loads)
        $tables[] = "CREATE TABLE IF NOT EXISTS serials (
            id          INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            serial_name VARCHAR(255) NOT NULL,
            order_ids   VARCHAR(1000) NOT NULL,
            user_id     INT(11) UNSIGNED NOT NULL,
            company_id  INT(11) UNSIGNED NOT NULL,
            status      TINYINT(1) DEFAULT 1,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by  INT(11) UNSIGNED,
            printed_at  TIMESTAMP NULL,
            FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 14. order_returns
        $tables[] = "CREATE TABLE IF NOT EXISTS order_returns (
            id                INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_ids         VARCHAR(1000) NOT NULL,
            route_id          INT(11) UNSIGNED NOT NULL,
            shop_id           INT(11) UNSIGNED NOT NULL,
            user_id           INT(11) UNSIGNED NOT NULL,
            company_id        INT(11) UNSIGNED NOT NULL,
            total_return_value DECIMAL(15,2) DEFAULT 0,
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by        INT(11) UNSIGNED,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 15. order_return_items
        $tables[] = "CREATE TABLE IF NOT EXISTS order_return_items (
            id         INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            return_id  INT(11) UNSIGNED NOT NULL,
            order_id   INT(11) UNSIGNED NOT NULL,
            item_id    INT(11) UNSIGNED NOT NULL,
            return_qty INT(11) NOT NULL,
            price      DECIMAL(15,2) NOT NULL,
            FOREIGN KEY (return_id) REFERENCES order_returns(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        /* ── NEW ADVANCED TABLES ── */

        // 16. targets  (monthly delivery targets per SR / group / division)
        $tables[] = "CREATE TABLE IF NOT EXISTS targets (
            id                INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id        INT(11) UNSIGNED NOT NULL,
            target_type       ENUM('sr','group','division') NOT NULL DEFAULT 'sr',
            target_entity_id  INT(11) UNSIGNED NOT NULL,
            month             TINYINT(2) UNSIGNED NOT NULL,
            year              SMALLINT(4) UNSIGNED NOT NULL,
            target_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
            target_orders     INT(11)       NOT NULL DEFAULT 0,
            notes             TEXT,
            created_by        INT(11) UNSIGNED,
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_by        INT(11) UNSIGNED,
            updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_target (company_id, target_type, target_entity_id, month, year),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 17. truck_loads  (full lifecycle truck/delivery management)
        $tables[] = "CREATE TABLE IF NOT EXISTS truck_loads (
            id                    INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            load_name             VARCHAR(100) NOT NULL,
            company_id            INT(11) UNSIGNED NOT NULL,
            division_id           INT(11) UNSIGNED,
            group_id              INT(11) UNSIGNED,
            assigned_sr_id        INT(11) UNSIGNED NOT NULL,
            status                ENUM('draft','submitted','approved','loading','ready','in_transit','delivered','cancelled','returned')
                                  NOT NULL DEFAULT 'draft',
            total_orders          INT(11) DEFAULT 0,
            total_value           DECIMAL(15,2) DEFAULT 0,
            notes                 TEXT,
            created_by            INT(11) UNSIGNED NOT NULL,
            created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_by          INT(11) UNSIGNED,
            submitted_at          TIMESTAMP NULL,
            approved_by           INT(11) UNSIGNED,
            approved_at           TIMESTAMP NULL,
            loading_started_by    INT(11) UNSIGNED,
            loading_started_at    TIMESTAMP NULL,
            ready_marked_by       INT(11) UNSIGNED,
            ready_marked_at       TIMESTAMP NULL,
            dispatched_by         INT(11) UNSIGNED,
            dispatched_at         TIMESTAMP NULL,
            delivered_by          INT(11) UNSIGNED,
            delivered_at          TIMESTAMP NULL,
            cancelled_by          INT(11) UNSIGNED,
            cancelled_at          TIMESTAMP NULL,
            cancel_reason         TEXT,
            FOREIGN KEY (company_id)     REFERENCES companies(id)    ON DELETE CASCADE,
            FOREIGN KEY (assigned_sr_id) REFERENCES users(id)        ON DELETE CASCADE,
            INDEX idx_company_status  (company_id, status),
            INDEX idx_sr_status       (assigned_sr_id, status),
            INDEX idx_company_created (company_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 18. truck_load_orders  (proper junction; replaces comma-separated order_ids)
        $tables[] = "CREATE TABLE IF NOT EXISTS truck_load_orders (
            id             INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            truck_load_id  INT(11) UNSIGNED NOT NULL,
            order_id       INT(11) UNSIGNED NOT NULL,
            added_by       INT(11) UNSIGNED,
            added_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            removed_by     INT(11) UNSIGNED,
            removed_at     TIMESTAMP NULL,
            remove_reason  VARCHAR(255),
            is_active      TINYINT(1) DEFAULT 1,
            UNIQUE KEY uq_active_load_order (truck_load_id, order_id, is_active),
            FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id)      REFERENCES orders(id)       ON DELETE CASCADE,
            INDEX idx_truck_load (truck_load_id),
            INDEX idx_order      (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 19. truck_load_status_log  (immutable audit trail of every status change)
        $tables[] = "CREATE TABLE IF NOT EXISTS truck_load_status_log (
            id             INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            truck_load_id  INT(11) UNSIGNED NOT NULL,
            from_status    VARCHAR(20),
            to_status      VARCHAR(20) NOT NULL,
            changed_by     INT(11) UNSIGNED NOT NULL,
            changed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes          TEXT,
            action_type    ENUM('manual','request_approved','auto') DEFAULT 'manual',
            FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id) ON DELETE CASCADE,
            INDEX idx_truck_load (truck_load_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        // 20. status_change_requests  (SR requests manager to advance/change status)
        $tables[] = "CREATE TABLE IF NOT EXISTS status_change_requests (
            id                INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            truck_load_id     INT(11) UNSIGNED NOT NULL,
            requested_by      INT(11) UNSIGNED NOT NULL,
            current_status    VARCHAR(20) NOT NULL,
            requested_status  VARCHAR(20) NOT NULL,
            reason            TEXT,
            urgency           ENUM('normal','urgent') DEFAULT 'normal',
            request_status    ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_by       INT(11) UNSIGNED,
            resolved_at       TIMESTAMP NULL,
            resolution_note   TEXT,
            FOREIGN KEY (truck_load_id) REFERENCES truck_loads(id) ON DELETE CASCADE,
            FOREIGN KEY (requested_by)  REFERENCES users(id)        ON DELETE CASCADE,
            INDEX idx_truck_load    (truck_load_id),
            INDEX idx_request_status (request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        /* ── RUN ALL TABLE CREATES ── */
        $errors = [];
        foreach ($tables as $sql) {
            if (!$conn->query($sql)) {
                $errors[] = $conn->error;
            }
        }

        /* ── ALTER existing tables to add new columns if missing ── */
        $alters = [
            // orders: add truck_load_id if not present
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS truck_load_id INT(11) UNSIGNED DEFAULT NULL AFTER shop_id",
            // orders: drop lat/lng columns if they still exist
            "ALTER TABLE orders DROP COLUMN IF EXISTS latitude",
            "ALTER TABLE orders DROP COLUMN IF EXISTS longitude",
            // Add missing indexes (IF NOT EXISTS via try)
            "ALTER TABLE routes ADD INDEX IF NOT EXISTS idx_company (company_id)",
            "ALTER TABLE shops  ADD INDEX IF NOT EXISTS idx_company_route (company_id, route_id)",
            "ALTER TABLE items  ADD INDEX IF NOT EXISTS idx_company (company_id)",
        ];
        foreach ($alters as $sql) {
            $conn->query($sql); // ignore errors (column may already be in right state)
        }

        /* ── Seed default data ── */
        $res = $conn->query("SELECT COUNT(*) AS c FROM companies");
        if ($res && $res->fetch_assoc()['c'] == 0) {
            $conn->query("INSERT INTO companies (name) VALUES ('Default Company')");
        }

        $res = $conn->query("SELECT COUNT(*) AS c FROM users");
        if ($res && $res->fetch_assoc()['c'] == 0) {
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, company_id) VALUES (?, ?, ?, 1)");
            $u = 'admin';
            $p = password_hash('1234', PASSWORD_DEFAULT);
            $r = 0;
            $stmt->bind_param("ssi", $u, $p, $r);
            $stmt->execute();
            $stmt->close();
            $msg = "System initialized. Default Super Admin: <strong>admin / 1234</strong>";
        } else {
            $msg = "Database tables checked / updated successfully.";
        }

        if (!empty($errors)) {
            $msg .= "<br><span style='color:#dc2626;'>Errors: " . implode(', ', $errors) . "</span>";
        }

    } elseif ($_POST['action'] === 'reset') {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $conn->query("SHOW TABLES");
        while ($row = $tables->fetch_array()) {
            $conn->query("DROP TABLE IF EXISTS `{$row[0]}`");
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $msg = "All tables dropped. You can now re-run setup.";

    } elseif ($_POST['action'] === 'logout') {
        unset($_SESSION['setup_authenticated']);
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Setup Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#1d2b64,#f8cdda); min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .card { border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,.2); max-width:640px; width:100%; }
    </style>
</head>
<body>
<div class="card p-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Admin Setup &mdash; v<?= VERSION_NAME ?></h2>
        <form method="POST">
            <button type="submit" name="action" value="logout" class="btn btn-sm btn-outline-secondary">Exit</button>
        </form>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= $msg ?></div>
    <?php endif; ?>

    <p class="text-muted mb-4">Use this panel to initialize or reset the system database tables.</p>

    <h6 class="text-muted mb-3">New tables added in v3.0.0:</h6>
    <ul class="text-muted small mb-4">
        <li><strong>divisions</strong> &mdash; Organizational divisions per company</li>
        <li><strong>sales_groups</strong> &mdash; SR groups within divisions</li>
        <li><strong>user_group_assignments</strong> &mdash; SR &rarr; Group membership</li>
        <li><strong>targets</strong> &mdash; Monthly delivery targets per SR / group / division</li>
        <li><strong>truck_loads</strong> &mdash; Full delivery lifecycle (replaces serials for new entries)</li>
        <li><strong>truck_load_orders</strong> &mdash; Orders within each truck load</li>
        <li><strong>truck_load_status_log</strong> &mdash; Immutable audit log of status changes</li>
        <li><strong>status_change_requests</strong> &mdash; SR requests to manager for status changes</li>
    </ul>

    <div class="d-grid gap-3">
        <form method="POST">
            <button type="submit" name="action" value="setup" class="btn btn-success btn-lg w-100">
                Initialize / Update Database
            </button>
        </form>
        <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL data. Continue?')">
            <button type="submit" name="action" value="reset" class="btn btn-danger btn-lg w-100">
                Reset Database (Delete All Data)
            </button>
        </form>
    </div>
</div>
</body>
</html>
