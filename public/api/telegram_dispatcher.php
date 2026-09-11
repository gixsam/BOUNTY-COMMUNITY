<?php
// public/api/telegram_dispatcher.php
// Endpoint that receives a payload about a newly created bounty and forwards it to a Telegram channel.

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed. Use POST.'], 405);
}

// Parse incoming JSON (fallback to form data)
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload) || empty($payload)) {
    $payload = $_POST;
}

// Expected fields: title, bounty_amount, job_id (or apply_link)
$title          = trim($payload['title'] ?? '');
$bounty_amount  = $payload['bounty_amount'] ?? null;
$job_id         = trim($payload['job_id'] ?? '');
$apply_link     = $payload['apply_link'] ?? '';

if ($title === '' || $bounty_amount === null || $job_id === '' ) {
    json_response(['error' => 'Missing required fields (title, bounty_amount, job_id).'], 400);
}

// Build a deep link to the application page (fallback if not provided)
if ($apply_link === '') {
    $apply_link = BASE_URL . '/portal/candidate_review.php?job_id=' . urlencode($job_id);
}

// Telegram configuration – read from environment or config constants.
$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: (defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : null);
$chatId   = getenv('TELEGRAM_CHAT_ID')   ?: (defined('TELEGRAM_CHAT_ID')   ? TELEGRAM_CHAT_ID   : null);

if (!$botToken || !$chatId) {
    error_log('[TelegramDispatcher] Missing bot token or chat ID configuration.');
    json_response(['error' => 'Telegram configuration not set.'], 500);
}

$message = "🪙 *New Bounty Posted!*\n" .
           "*Title:* " . addslashes($title) . "\n" .
           "*Reward:* $" . number_format((float)$bounty_amount, 2) . "\n" .
           "[Apply Here]($apply_link)";

$apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'chat_id' => $chatId,
    'text'    => $message,
    'parse_mode' => 'Markdown',
]));

$response = curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    error_log('[TelegramDispatcher] cURL error: ' . $err);
    json_response(['error' => 'Telegram dispatch failed (cURL).'], 500);
}

$decoded = json_decode($response, true);
if ($httpCode !== 200 || empty($decoded['ok'])) {
    error_log('[TelegramDispatcher] Telegram API error: ' . $response);
    json_response(['error' => 'Telegram API error.', 'details' => $decoded], 500);
}

json_response(['success' => true, 'message' => 'Notification sent to Telegram.']);
?>
