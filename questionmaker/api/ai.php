<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user   = requireLogin();
$db     = getDB();
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'generate';

// Load user's AI keys
$stmt = $db->prepare('SELECT ai_keys_json, claude_api_key FROM users WHERE id=?');
$stmt->execute([$user['id']]);
$udata   = $stmt->fetch();
$aiKeys  = json_decode($udata['ai_keys_json'] ?? '{}', true) ?: [];
// Fallback: legacy claude_api_key column or env
if (empty($aiKeys['claude']) && !empty($udata['claude_api_key'])) $aiKeys['claude'] = $udata['claude_api_key'];
if (empty($aiKeys['claude'])) $aiKeys['claude'] = CLAUDE_API_KEY;
if (empty($aiKeys['openai'])) $aiKeys['openai'] = OPENAI_API_KEY;
if (empty($aiKeys['gemini'])) $aiKeys['gemini'] = GEMINI_API_KEY;

// ---- Shared input params ----
$topic      = trim($input['topic']      ?? '');
$qtype      = $input['type']            ?? 'mcq';
$count      = min((int)($input['count'] ?? 5), 20);
$difficulty = $input['difficulty']      ?? 'medium';
$language   = $input['language']        ?? 'bangla';
$subject    = $input['subject']         ?? '';
$class      = $input['class']           ?? '';
$provider   = $input['provider']        ?? 'claude';
$mode       = $input['mode']            ?? 'create';
$existing   = trim($input['existing']   ?? '');
$lesson     = trim($input['lesson']     ?? '');
$speciality = trim($input['speciality'] ?? '');

// ---- Build the canonical system prompt & user prompt ----
$langNote = $language === 'bangla'
    ? 'Write all questions, options, and statements in Bengali (Bangla) Unicode script.'
    : 'Write everything in English.';

if ($mode === 'modify') {
    $typeGuides = [
        'mcq'        => 'Convert the input question into a valid MCQ JSON object. Format: {"question":"…","marks":1,"options":["ক. …","খ. …","গ. …","ঘ. …"],"correct":0}  (correct = 0-based index of the correct answer, e.g. 0 for option ক, 1 for খ, etc.)',
        'short'      => 'Convert the input question into a valid Short Answer JSON object. Format: {"question":"…","marks":5}',
        'creative'   => 'Convert the input question into a valid Creative (সৃজনশীল) JSON object. Format: {"stimulus":"উদ্দীপক paragraph","subQuestions":[{"label":"ক)","text":"…","marks":1},{"label":"খ)","text":"…","marks":2},{"label":"গ)","text":"…","marks":3},{"label":"ঘ)","text":"…","marks":4}]}',
        'fill-blank' => 'Convert the input question into a valid Fill in the Blank JSON object. Format: {"template":"sentence with ___ for blanks","answers":["answer1","answer2"],"marks":1}',
        'true-false' => 'Convert the input question into a valid True/False JSON object. Format: {"statement":"…","answer":true,"marks":1}',
    ];

    $systemPrompt = "You are an expert academic question parser and formatting tool for Bangladesh education board exams.
Your task is to take any question input (which may be unorganized raw text, copied from MS Word/Google Docs, or raw JSON) and convert/rewrite it into a strict JSON format. $langNote

{$typeGuides[$qtype]}

OUTPUT RULES (strict):
- Output ONLY a raw valid JSON object (or a 1-element JSON array containing that object). No markdown, no HTML blocks, no code fences (like ```json), no explanation, no comments.
- First character of response must be [ or { and last must be ] or }
- Difficulty level: $difficulty" .
    ($class   ? "\n- Class/Level: $class"   : '') .
    ($subject ? "\n- Subject: $subject" : '');

    $userPrompt = "Task: Parse, clean up, and format the following question input into the requested JSON schema.\n\n" .
        "Input Question (may be raw text, unorganized Word/Doc copy, or JSON):\n\"\"\"\n$existing\n\"\"\"\n\n" .
        "Modification & Formatting Instructions:\n" .
        "- Make sure it is converted into the strict JSON format for $qtype.\n" .
        ($topic ? "- Topic/Focus: $topic\n" : "") .
        ($lesson ? "- Lesson/Chapter: $lesson\n" : "") .
        ($speciality ? "- Speciality/Style/Formatting instructions: $speciality\n" : "") .
        "\nReturn only the final JSON object.";
} else {
    $typeGuides = [
        'mcq'        => 'Generate MCQ questions. Return array of: {"question":"…","marks":1,"options":["ক. …","খ. …","গ. …","ঘ. …"],"correct":0}  (correct = 0-based index)',
        'short'      => 'Generate short-answer questions. Return array of: {"question":"…","marks":5}',
        'creative'   => 'Generate সৃজনশীল creative questions. Return array of: {"stimulus":"উদ্দীপক paragraph","subQuestions":[{"label":"ক)","text":"…","marks":1},{"label":"খ)","text":"…","marks":2},{"label":"গ)","text":"…","marks":3},{"label":"ঘ)","text":"…","marks":4}]}',
        'fill-blank' => 'Generate fill-in-the-blank sentences. Return array of: {"template":"sentence with ___ for blanks","answers":["answer1","answer2"],"marks":1}',
        'true-false' => 'Generate true/false statements. Return array of: {"statement":"…","answer":true,"marks":1}',
    ];

    $systemPrompt = "You are an expert academic question setter for Bangladesh education board exams. $langNote

{$typeGuides[$qtype]}

OUTPUT RULES (strict):
- Output ONLY a raw valid JSON array. No markdown, no code fences, no explanation, no comments.
- First character must be [ and last must be ]
- Generate exactly $count questions.
- Difficulty level: $difficulty" .
    ($class   ? "\n- Class/Level: $class"   : '') .
    ($subject ? "\n- Subject: $subject" : '');

    $userPrompt = "Topic: $topic" .
        ($lesson ? "\nLesson/Chapter: $lesson" : "") .
        ($speciality ? "\nSpeciality/Instructions: $speciality" : "");
}

// =========================================================
// ACTION: get_prompt  — return prompt text for manual mode
// =========================================================
if ($action === 'get_prompt') {
    $manualPrompt = "=== QMaker Prompt (paste this into any AI) ===\n\n"
        . "SYSTEM INSTRUCTION:\n" . $systemPrompt . "\n\n"
        . "YOUR TASK:\n" . $userPrompt . "\n\n"
        . "=== Expected output format for $qtype ===\n"
        . _exampleOutput($qtype, $language)
        . "\n\nRemember: output ONLY the JSON array, nothing else.";
    echo json_encode(['success' => true, 'prompt' => $manualPrompt, 'type' => $qtype]);
    exit;
}

// =========================================================
// ACTION: parse_manual  — parse text pasted by user
// =========================================================
if ($action === 'parse_manual') {
    $raw = trim($input['text'] ?? '');
    $questions = _extractJSON($raw);
    if ($questions !== null) {
        echo json_encode(['success' => true, 'questions' => $questions, 'type' => $qtype]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not parse valid JSON from the pasted text. Make sure you copied the full AI response.']);
    }
    exit;
}

// =========================================================
// ACTION: generate  — call AI provider API
// =========================================================
if (!$topic && !$existing) { echo json_encode(['success' => false, 'error' => 'Topic or Existing Question is required']); exit; }

switch ($provider) {
    case 'claude':
        $key = $aiKeys['claude'] ?? '';
        if (!$key) { echo json_encode(['success' => false, 'error' => 'No Claude API key. Add it in Settings → AI Keys.']); exit; }
        $res = _callClaude($key, $userPrompt, $systemPrompt);
        break;

    case 'openai':
        $key = $aiKeys['openai'] ?? '';
        if (!$key) { echo json_encode(['success' => false, 'error' => 'No OpenAI API key. Add it in Settings → AI Keys.']); exit; }
        $res = _callOpenAI($key, $userPrompt, $systemPrompt);
        break;

    case 'gemini':
        $key = $aiKeys['gemini'] ?? '';
        if (!$key) { echo json_encode(['success' => false, 'error' => 'No Gemini API key. Add it in Settings → AI Keys.']); exit; }
        $res = _callGemini($key, $userPrompt, $systemPrompt);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown provider: ' . htmlspecialchars($provider)]);
        exit;
}

if (!$res['success']) { echo json_encode($res); exit; }

$questions = _extractJSON($res['content']);
if ($questions !== null) {
    echo json_encode(['success' => true, 'questions' => $questions, 'type' => $qtype, 'provider' => $provider]);
} else {
    echo json_encode(['success' => false, 'error' => 'AI returned an unexpected format. Try again or use Manual mode to copy-paste.', 'raw' => substr($res['content'], 0, 400)]);
}

// =========================================================
// Provider functions
// =========================================================
function _callClaude(string $key, string $prompt, string $system): array {
    $body = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 4096,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);
    return _httpPost('https://api.anthropic.com/v1/messages', $body, [
        'Content-Type: application/json',
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
    ], fn($d) => $d['content'][0]['text'] ?? null);
}

function _callOpenAI(string $key, string $prompt, string $system): array {
    $body = json_encode([
        'model'       => OPENAI_MODEL,
        'max_tokens'  => 4096,
        'temperature' => 0.7,
        'messages'    => [
            ['role' => 'system',  'content' => $system],
            ['role' => 'user',    'content' => $prompt],
        ],
    ]);
    return _httpPost('https://api.openai.com/v1/chat/completions', $body, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ], fn($d) => $d['choices'][0]['message']['content'] ?? null);
}

function _callGemini(string $key, string $prompt, string $system): array {
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . urlencode($key);
    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => $system]]],
        'contents'           => [['parts' => [['text' => $prompt]]]],
        'generationConfig'   => ['temperature' => 0.7, 'maxOutputTokens' => 4096],
    ]);
    return _httpPost($url, $body, ['Content-Type: application/json'],
        fn($d) => $d['candidates'][0]['content']['parts'][0]['text'] ?? null);
}

function _httpPost(string $url, string $body, array $headers, callable $extract): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['success' => false, 'error' => 'Connection error: ' . $err];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['success' => false, 'error' => 'Invalid response from AI server'];
    if (isset($data['error'])) return ['success' => false, 'error' => $data['error']['message'] ?? ($data['error'] ?? 'API error')];
    $text = $extract($data);
    if ($text === null) return ['success' => false, 'error' => 'Unexpected API response structure'];
    return ['success' => true, 'content' => $text];
}

function _extractJSON(string $text): ?array {
    $text = preg_replace('/^```(?:json)?\s*/m', '', trim($text));
    $text = preg_replace('/\s*```$/m', '', $text);
    if (preg_match('/\[[\s\S]*\]/m', $text, $m)) {
        $arr = json_decode($m[0], true);
        if (is_array($arr) && count($arr) > 0) return $arr;
    }
    if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
        $obj = json_decode($m[0], true);
        if (is_array($obj) && !isset($obj[0])) return [$obj];
    }
    return null;
}

function _exampleOutput(string $type, string $lang): string {
    $isBn = $lang === 'bangla';
    switch ($type) {
        case 'mcq':
            return $isBn
                ? '[{"question":"আলোর প্রতিফলন কোন নিয়ম মেনে চলে?","marks":1,"options":["ক. স্নেলের সূত্র","খ. প্রতিফলন সূত্র","গ. বয়েলের সূত্র","ঘ. হুকের সূত্র"],"correct":1}]'
                : '[{"question":"Which law governs reflection of light?","marks":1,"options":["a. Snell\'s law","b. Law of reflection","c. Boyle\'s law","d. Hooke\'s law"],"correct":1}]';
        case 'short':
            return $isBn ? '[{"question":"আলোর প্রতিফলন বলতে কী বোঝায়?","marks":5}]' : '[{"question":"What is reflection of light?","marks":5}]';
        case 'creative':
            return '[{"stimulus":"একটি আয়নার সামনে একটি মোমবাতি রাখা হলো...","subQuestions":[{"label":"ক)","text":"প্রতিফলন কাকে বলে?","marks":1},{"label":"খ)","text":"আলোর প্রতিফলনের নিয়ম ব্যাখ্যা কর।","marks":2},{"label":"গ)","text":"নিয়মিত ও অনিয়মিত প্রতিফলনের মধ্যে পার্থক্য লেখ।","marks":3},{"label":"ঘ)","text":"উদ্দীপকের ঘটনাটি বিশ্লেষণ কর।","marks":4}]}]';
        case 'fill-blank':
            return $isBn ? '[{"template":"আলোর প্রতিফলনের সময় আপতন কোণ ও ___ কোণ সমান হয়।","answers":["প্রতিফলন"],"marks":1}]' : '[{"template":"The angle of incidence equals the angle of ___.","answers":["reflection"],"marks":1}]';
        case 'true-false':
            return $isBn ? '[{"statement":"আলো সব সময় সরলরেখায় চলে।","answer":true,"marks":1}]' : '[{"statement":"Light always travels in a straight line.","answer":true,"marks":1}]';
        default: return '[]';
    }
}
