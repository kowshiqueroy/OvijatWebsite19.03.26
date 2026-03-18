<?php
// ============================================================
// setup.php — Run once to create DB tables and seed data
// ============================================================
require_once __DIR__ . '/includes/bootstrap.php';

// Only allow admin or first-run
$ran = true;
try {
    $pdo = db();
    $pdo->query("SELECT 1 FROM users LIMIT 1");
} catch (Exception $e) {
    $ran = false;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = db();

        // ── Schema ────────────────────────────────────────────
        $sql = "
        SET FOREIGN_KEY_CHECKS = 0;

        CREATE TABLE IF NOT EXISTS settings (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            `key`       VARCHAR(100) NOT NULL UNIQUE,
            `value`     TEXT,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(60)  NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            full_name   VARCHAR(120) NOT NULL,
            role        ENUM('admin','sr') NOT NULL DEFAULT 'sr',
            active      TINYINT(1) NOT NULL DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

          CREATE TABLE IF NOT EXISTS brands (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(100) NOT NULL UNIQUE,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        

       CREATE TABLE IF NOT EXISTS products (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  VARCHAR(20)  NOT NULL UNIQUE,
        category_id INT,
        brand_id    INT,
        name        VARCHAR(200) NOT NULL,
        description TEXT,
        memo_number VARCHAR(100),
        memo_date   DATE,
        notes       TEXT,
        active      TINYINT(1) NOT NULL DEFAULT 1,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
    );


CREATE TABLE IF NOT EXISTS product_variants (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        product_id   INT NOT NULL,
        variant_name VARCHAR(100),
        size         VARCHAR(50),
        color        VARCHAR(50), 
        cost         DECIMAL(12,2) NOT NULL DEFAULT 0,
        regular      DECIMAL(12,2) NOT NULL DEFAULT 0,
        price        DECIMAL(12,2) NOT NULL DEFAULT 0,
        quantity     INT NOT NULL DEFAULT 0,
        barcode      VARCHAR(50)  UNIQUE,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

        CREATE TABLE IF NOT EXISTS customers (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL,
            phone       VARCHAR(30),
            email       VARCHAR(150),
            points      INT NOT NULL DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sales (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            invoice_no      VARCHAR(30) NOT NULL UNIQUE,
            customer_id     INT,
            user_id         INT NOT NULL,
            subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount_pct    DECIMAL(5,2)  NOT NULL DEFAULT 0,
            points_used     INT           NOT NULL DEFAULT 0,
            points_value    DECIMAL(12,2) NOT NULL DEFAULT 0,
            vat_rate        DECIMAL(5,2)  NOT NULL DEFAULT 0,
            vat_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
            total           DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method  VARCHAR(100) DEFAULT 'cash',
            status          ENUM('draft','completed','cancelled') DEFAULT 'draft',
            notes           TEXT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS sale_items (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            sale_id         INT NOT NULL,
            variant_id      INT NOT NULL,
            product_name    VARCHAR(200),
            notes           TEXT,
            size            VARCHAR(50),
            color           VARCHAR(50),
            qty             INT NOT NULL DEFAULT 1,
            unit_price      DECIMAL(12,2) NOT NULL,
            total_price     DECIMAL(12,2) NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (variant_id) REFERENCES product_variants(id)
        );

        CREATE TABLE IF NOT EXISTS finance_entries (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            type        ENUM('income','expense','transfer','opening') NOT NULL DEFAULT 'expense',
            category    VARCHAR(100),
            account     VARCHAR(30) NOT NULL DEFAULT 'shop_cash',
            to_account  VARCHAR(30) DEFAULT NULL,
            sub_account VARCHAR(30) DEFAULT NULL,
            party       VARCHAR(30) DEFAULT 'shop',
            amount      DECIMAL(12,2) NOT NULL,
            description TEXT,
            ref_sale_id INT,
            user_id     INT NOT NULL,
            entry_date  DATE NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (ref_sale_id) REFERENCES sales(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS action_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT,
            action      VARCHAR(60)  NOT NULL,
            module      VARCHAR(60)  NOT NULL,
            record_id   FLOAT(11,2),
            note        TEXT,
            ip          VARCHAR(45),
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS product_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    variant_id    INT NOT NULL,
    product_name  VARCHAR(200),
    variant_name  VARCHAR(100),
    size          VARCHAR(50),
    color         VARCHAR(50),
    cost          DECIMAL(12,2) NOT NULL DEFAULT 0,
    price         DECIMAL(12,2) NOT NULL DEFAULT 0,
    regular       DECIMAL(12,2) NOT NULL DEFAULT 0,
    qty_added     INT NOT NULL DEFAULT 0,
    memo_number   VARCHAR(100),
    memo_date     DATE,
    user_id       INT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

        SET FOREIGN_KEY_CHECKS = 1;
        ";

        // Execute each statement separately
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }

        // ── Seed admin user ───────────────────────────────────
        $existing = dbFetch('SELECT id FROM users WHERE username = ?', ['admin']);
        if (!$existing) {
            dbInsert('users', [
                'username'   => 'admin',
                'password'   => password_hash('12345678', PASSWORD_DEFAULT),
                'full_name'  => 'System Admin',
                'role'       => 'admin',
                'active'     => 1,
                'created_at' => now(),
            ]);
        }

        // ── Seed sample categories ────────────────────────────
        foreach (['Shirt', 'Pant', 'Tie', 'Belt'] as $cat) {
            try {
                dbInsert('categories', ['name' => $cat, 'created_at' => now()]);
            } catch (Exception) {}
        }

        $ran = true;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup — <?= APP_NAME ?></title>
<style>
  body{font-family:system-ui,sans-serif;max-width:600px;margin:60px auto;padding:0 20px;background:#f5f5f5}
  .card{background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 16px rgba(0,0,0,.08)}
  h1{margin:0 0 8px;font-size:1.6rem}
  p{color:#555}
  .btn{display:inline-block;padding:12px 28px;background:#1a73e8;color:#fff;border:none;border-radius:8px;
       font-size:1rem;cursor:pointer;text-decoration:none}
  .success{background:#e6f4ea;border:1px solid #34a853;color:#1e8737;padding:16px;border-radius:8px;margin-bottom:16px}
  .error{background:#fce8e6;border:1px solid #d93025;color:#c5221f;padding:16px;border-radius:8px;margin-bottom:16px}
  ul{margin:4px 0 0;padding-left:20px}
</style>
</head>
<body>
<div class="card">
  <h1>🛒 <?= APP_NAME ?> — Setup</h1>
  <p>This will create all database tables and seed an initial admin user.</p>

  <?php if ($ran): ?>
    <div class="success">
      ✅ Setup complete!<br>
      Default login: <strong>admin</strong> / <strong>admin123</strong><br>
      Please change the password after first login.
    </div>
    <a href="index.php" class="btn">Go to App →</a>
  <?php elseif ($errors): ?>
    <div class="error">❌ Errors occurred:<ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach ?></ul></div>
  <?php endif ?>

  <?php if (!$ran): ?>
  <form method="POST">
    <p><strong>Database:</strong> <?= DB_HOST ?> / <?= DB_NAME ?></p>
    <button type="submit" class="btn">Run Setup</button>
  </form>
  <?php endif ?>
</div>
</body>
</html>
