<?php
// ============================================================
// includes/db.php — PDO database connection singleton
// ============================================================

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Shorthand query helpers ───────────────────────────────────

function dbQuery(string $sql, array $params = []): PDOStatement {
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        // Catch Foreign Key Constraint Violations (Code 23000 / 1451)
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1451)) {
            if (stripos(trim($sql), 'DELETE') === 0) {
                $errorMsg = 'Action denied: Cannot delete this item because it is linked to past records (like sales or invoices).';
            } else {
                $errorMsg = 'Action denied: This record is tied to other data and cannot be modified this way.';
            }
        } else {
            // Catch all other unexpected database errors
            $errorMsg = 'Database Error: ' . $e->getMessage();
        }

        // Trigger flash message 
        if (function_exists('flash')) {
            flash('error', $errorMsg);
        }

        // Safely redirect back to the previous page, or to index.php if unknown
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/index.php';
        header("Location: " . $redirectUrl);
        exit;
    }
}

function dbFetch(string $sql, array $params = []): ?array {
    return dbQuery($sql, $params)->fetch() ?: null;
}

function dbFetchAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

function dbInsert(string $table, array $data): int {
    $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
    $vals = implode(', ', array_fill(0, count($data), '?'));
    dbQuery("INSERT INTO `$table` ($cols) VALUES ($vals)", array_values($data));
    return (int) db()->lastInsertId();
}

function dbUpdate(string $table, array $data, string $where, array $whereParams = []): int {
    $set  = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $stmt = dbQuery("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$whereParams]);
    return $stmt->rowCount();
}

function dbDelete(string $table, string $where, array $params = []): int {
    return dbQuery("DELETE FROM `$table` WHERE $where", $params)->rowCount();
}

function lastId(): int {
    return (int) db()->lastInsertId();
}