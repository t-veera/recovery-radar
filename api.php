<?php
/**
 * Recovery Radar — NAS Data API
 * Reads and writes recovery-radar-data.json in the same folder.
 *
 * GET  api.php  → returns { lastModified, data }
 * POST api.php  → body must be { lastModified, data }; saves atomically
 */

header('Content-Type: application/json; charset=utf-8');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 'null';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dataFile = __DIR__ . '/recovery-radar-data.json';

// ── GET ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($dataFile)) {
        echo json_encode(['lastModified' => 0, 'data' => null]);
        exit;
    }

    $raw     = file_get_contents($dataFile);
    $decoded = json_decode($raw, true);

    if (isset($decoded['lastModified']) && isset($decoded['data'])) {
        echo $raw;
    } else {
        echo json_encode(['lastModified' => 0, 'data' => null]);
    }
    exit;
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw     = file_get_contents('php://input');
    $decoded = json_decode($raw, true);

    if ($decoded === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    if (!isset($decoded['data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data field']);
        exit;
    }

    $payload = [
        'lastModified' => $decoded['lastModified'] ?? (int)(microtime(true) * 1000),
        'data'         => $decoded['data'],
    ];

    $tmp = $dataFile . '.tmp';
    if (file_put_contents($tmp, json_encode($payload), LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write data file']);
        exit;
    }
    rename($tmp, $dataFile);

    echo json_encode([
        'ok'           => true,
        'lastModified' => $payload['lastModified'],
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
