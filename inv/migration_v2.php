<?php
/**
 * migration_v2.php
 * New Schema updates for Suppliers, Expenses and Profit tracking
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireRole('Admin');

echo "<h2>System Upgrade Migration (V2)</h2>";

try {
    // 1. Suppliers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(50),
        email VARCHAR(100),
        address TEXT,
        branch_id INT,
        balance DECIMAL(15, 2) DEFAULT 0.00,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id)
    ) ENGINE=InnoDB");
    echo "<p>Suppliers table created/verified.</p>";

    // 2. Expenses Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT,
        category VARCHAR(100),
        amount DECIMAL(15, 2) NOT NULL,
        description TEXT,
        expense_date DATE,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (branch_id) REFERENCES branches(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    echo "<p>Expenses table created/verified.</p>";

    // 3. Stock Transfers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        from_branch_id INT,
        to_branch_id INT,
        quantity_pcs INT NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (from_branch_id) REFERENCES branches(id),
        FOREIGN KEY (to_branch_id) REFERENCES branches(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB");
    echo "<p>Stock Transfers table created/verified.</p>";

    // 4. Update Stock Ledger to include Purchase Price
    $cols = $pdo->query("SHOW COLUMNS FROM stock_ledger LIKE 'purchase_price'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE stock_ledger ADD COLUMN purchase_price DECIMAL(15, 2) DEFAULT 0.00 AFTER quantity_pcs");
        echo "<p>Added purchase_price to stock_ledger.</p>";
    }

    // 5. Update Sale Items to include Purchase Price (for profit per sale)
    $cols = $pdo->query("SHOW COLUMNS FROM sale_items LIKE 'purchase_price'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE sale_items ADD COLUMN purchase_price DECIMAL(15, 2) DEFAULT 0.00 AFTER unit_price");
        echo "<p>Added purchase_price to sale_items.</p>";
    }

    // 6. Update Inventory to include average Purchase Price
    $cols = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'avg_purchase_price'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN avg_purchase_price DECIMAL(15, 2) DEFAULT 0.00");
        echo "<p>Added avg_purchase_price to inventory.</p>";
    }

    echo "<p style='color:green'><b>Migration V2 completed successfully!</b></p>";
    echo "<p><a href='modules/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Migration Failed: " . $e->getMessage() . "</p>";
}
?>
