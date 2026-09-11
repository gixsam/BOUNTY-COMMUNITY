<?php
// ==============================================================================
// CHAT & LOUNGE BROADCAST HANDLER API
// Path: public/api/chat_handler.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$persona = get_active_persona();

// 1. GET: Fetch latest chat messages
if ($method === 'GET') {
    if (is_supabase_configured()) {
        $res = supabase_request('rest/v1/chat_messages?select=*,profiles:sender_id(display_name,handle,avatar_url,role)&order=created_at.asc&limit=50');
        json_response($res['data'] ?? []);
    } else {
        $messages = $_SESSION['bounty_mock_db']['chat'] ?? [];
        json_response($messages);
    }
}

// 2. POST: Send lounge message or broadcast card
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) $input = $_POST;
    $message = trim($input['message'] ?? '');
    $messageType = $input['message_type'] ?? 'chat';
    $meta = $input['meta'] ?? null;

    if (empty($message)) {
        json_response(['error' => 'Message text cannot be empty.'], 422);
    }

    if (is_supabase_configured()) {
        $payload = [
            'sender_id' => $persona['id'],
            'message' => $message,
            'channel' => 'lounge',
            'message_type' => $messageType,
            'meta_json' => $meta ?? new stdClass()
        ];
        $res = supabase_request('rest/v1/chat_messages', 'POST', $payload);
        if (isset($res['error'])) {
            json_response(['error' => $res['error']], 500);
        }
        json_response($res['data'] ?? ['success' => true]);
    } else {
        // Mock fallback
        $newMessage = [
            'id' => 'chat-' . uniqid(),
            'sender_name' => $persona['display_name'],
            'sender_handle' => $persona['handle'],
            'sender_role' => $persona['role'],
            'sender_avatar' => $persona['avatar_url'],
            'message' => $message,
            'message_type' => $messageType,
            'meta' => $meta,
            'created_at' => date('H:i')
        ];

        $_SESSION['bounty_mock_db']['chat'][] = $newMessage;

        // Keep last 100 messages in session
        if (count($_SESSION['bounty_mock_db']['chat']) > 100) {
            array_shift($_SESSION['bounty_mock_db']['chat']);
        }

        json_response([
            'success' => true,
            'message' => $newMessage
        ], 201);
    }
}
