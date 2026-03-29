<?php
//timezone set to dhaka
date_default_timezone_set('Asia/Dhaka');
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function dbFetch(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dbFetchAll(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dbInsert(string $table, array $data): int {
    $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
    $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
    $stmt = db()->prepare("INSERT INTO `$table` ($cols) VALUES ($placeholders)");
    foreach ($data as $k => $v) {
        $stmt->bindValue(":$k", $v);
    }
    $stmt->execute();
    return (int) db()->lastInsertId();
}

function dbUpdate(string $table, array $data, array $where): bool {
    $setParts = [];
    foreach (array_keys($data) as $k) {
        $setParts[] = "`$k` = :set_$k";
    }
    $whereParts = [];
    foreach (array_keys($where) as $k) {
        $whereParts[] = "`$k` = :where_$k";
    }
    
    $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
    $stmt = db()->prepare($sql);
    
    foreach ($data as $k => $v) {
        $stmt->bindValue(":set_$k", $v);
    }
    foreach ($where as $k => $v) {
        $stmt->bindValue(":where_$k", $v);
    }
    
    return $stmt->execute();
}

function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
