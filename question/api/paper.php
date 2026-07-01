<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$action = $_GET['action'] ?? '';
$body = jsonBody();

function paperRow(int $id, int $userId): ?array {
    $stmt = getDB()->prepare('SELECT * FROM papers WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

switch ($action) {
    case 'list': {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $stmt = getDB()->prepare(
                'SELECT id, name, language, subject_name, class_name, updated_at FROM papers
                 WHERE user_id = ? AND (name LIKE ? OR subject_name LIKE ? OR class_name LIKE ?)
                 ORDER BY updated_at DESC'
            );
            $like = "%$q%";
            $stmt->execute([$user['id'], $like, $like, $like]);
        } else {
            $stmt = getDB()->prepare(
                'SELECT id, name, language, subject_name, class_name, updated_at FROM papers
                 WHERE user_id = ? ORDER BY updated_at DESC'
            );
            $stmt->execute([$user['id']]);
        }
        jsonResponse(['ok' => true, 'papers' => $stmt->fetchAll()]);
    }

    case 'get': {
        $id = (int)($_GET['id'] ?? 0);
        $paper = paperRow($id, $user['id']);
        if (!$paper) jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
        $paper['elements'] = json_decode($paper['elements_json'], true) ?: [];
        $paper['print_settings'] = json_decode($paper['print_settings_json'], true) ?: [];
        jsonResponse(['ok' => true, 'paper' => $paper]);
    }

    case 'create': {
        $stmt = getDB()->prepare(
            'INSERT INTO papers (user_id, name, language, primary_font, secondary_font)
             VALUES (?, ?, ?, ?, ?)'
        );
        $name = trim((string)($body['name'] ?? 'Untitled Question')) ?: 'Untitled Question';
        $language = ($body['language'] ?? 'bn') === 'en' ? 'en' : 'bn';
        $primaryFont = trim((string)($body['primary_font'] ?? ($language === 'bn' ? 'Kalpurush' : 'Times New Roman')));
        $secondaryFont = trim((string)($body['secondary_font'] ?? ($language === 'bn' ? 'Times New Roman' : 'Kalpurush')));
        $stmt->execute([$user['id'], $name, $language, $primaryFont, $secondaryFont]);
        $id = (int) getDB()->lastInsertId();
        jsonResponse(['ok' => true, 'id' => $id]);
    }

    case 'save': {
        $id = (int)($body['id'] ?? 0);
        $paper = paperRow($id, $user['id']);
        if (!$paper) jsonResponse(['ok' => false, 'error' => 'Not found'], 404);

        $fields = [
            'name', 'language', 'primary_font', 'secondary_font', 'school_name', 'exam_name',
            'class_name', 'subject_name', 'time_text', 'full_marks', 'subject_code', 'set_code',
            'page_size', 'print_mode',
        ];
        $set = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $set[] = "$f = ?";
                $params[] = (string) $body[$f];
            }
        }
        foreach (['show_subject_code', 'show_set_code', 'is_answer_key'] as $f) {
            if (array_key_exists($f, $body)) {
                $set[] = "$f = ?";
                $params[] = (int) (bool) $body[$f];
            }
        }
        if (array_key_exists('elements', $body)) {
            $set[] = 'elements_json = ?';
            $params[] = json_encode($body['elements'], JSON_UNESCAPED_UNICODE);
        }
        if (array_key_exists('print_settings', $body)) {
            $set[] = 'print_settings_json = ?';
            $params[] = json_encode($body['print_settings'], JSON_UNESCAPED_UNICODE);
        }
        if (empty($set)) jsonResponse(['ok' => true]);

        $set[] = "updated_at = datetime('now')";
        $params[] = $id;
        $params[] = $user['id'];
        $sql = 'UPDATE papers SET ' . implode(', ', $set) . ' WHERE id = ? AND user_id = ?';
        getDB()->prepare($sql)->execute($params);
        jsonResponse(['ok' => true]);
    }

    case 'duplicate': {
        $id = (int)($body['id'] ?? 0);
        $paper = paperRow($id, $user['id']);
        if (!$paper) jsonResponse(['ok' => false, 'error' => 'Not found'], 404);
        $stmt = getDB()->prepare(
            'INSERT INTO papers (user_id, name, language, primary_font, secondary_font, school_name,
                exam_name, class_name, subject_name, time_text, full_marks, show_subject_code,
                subject_code, show_set_code, set_code, page_size, print_mode, print_settings_json, elements_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'], $paper['name'] . ' (Copy)', $paper['language'], $paper['primary_font'], $paper['secondary_font'],
            $paper['school_name'], $paper['exam_name'], $paper['class_name'], $paper['subject_name'],
            $paper['time_text'], $paper['full_marks'], $paper['show_subject_code'], $paper['subject_code'],
            $paper['show_set_code'], $paper['set_code'], $paper['page_size'], $paper['print_mode'],
            $paper['print_settings_json'], $paper['elements_json'],
        ]);
        jsonResponse(['ok' => true, 'id' => (int) getDB()->lastInsertId()]);
    }

    case 'delete': {
        $id = (int)($body['id'] ?? 0);
        $stmt = getDB()->prepare('DELETE FROM papers WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        jsonResponse(['ok' => true]);
    }

    default:
        jsonResponse(['ok' => false, 'error' => 'Unknown action'], 400);
}
