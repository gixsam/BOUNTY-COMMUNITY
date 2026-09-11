<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Social Emoji Reaction Handler API
// Path: public/api/reaction_handler.php
// Hostinger PHP 8.2 / Apache
// ==============================================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = $_SESSION['user_id'] ?? ($_SESSION['active_persona_role'] ?? 'guest');
$allowedEmojis = ['like', 'launch', 'bounty', 'fire'];

// 1. GET: Retrieve current reactions for an item
if ($method === 'GET') {
    $itemId = trim($_GET['item_id'] ?? '');
    if (empty($itemId)) {
        json_response(['error' => 'Missing item_id parameter.'], 400);
    }
    $data = get_item_reactions($itemId);
    json_response([
        'success' => true,
        'item_id' => $itemId,
        'counts' => $data['counts'],
        'user_active' => $data['user_active'],
    ]);
}

// 2. POST: Toggle an emoji reaction
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $itemId = trim($input['item_id'] ?? '');
    $emoji = strtolower(trim($input['emoji'] ?? ''));

    if (empty($itemId)) {
        json_response(['error' => 'Missing item_id.'], 422);
    }

    if (!in_array($emoji, $allowedEmojis, true)) {
        json_response(['error' => 'Invalid emoji type. Allowed: like, launch, bounty, fire.'], 422);
    }

    if (!isset($_SESSION['bounty_mock_db']['reactions'][$itemId])) {
        $_SESSION['bounty_mock_db']['reactions'][$itemId] = [
            'like'   => 0,
            'launch' => 0,
            'bounty' => 0,
            'fire'   => 0,
        ];
    }

    if (!isset($_SESSION['bounty_mock_db']['user_reactions'][$itemId])) {
        $_SESSION['bounty_mock_db']['user_reactions'][$itemId] = [];
    }

    if (!isset($_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId])) {
        $_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId] = [];
    }

    $hasReacted = !empty($_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId][$emoji]);
    $newActiveState = false;

    if ($hasReacted) {
        // Untoggle / Decrement
        unset($_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId][$emoji]);
        $_SESSION['bounty_mock_db']['reactions'][$itemId][$emoji] = max(
            0,
            ($_SESSION['bounty_mock_db']['reactions'][$itemId][$emoji] ?? 1) - 1
        );
        $newActiveState = false;
    } else {
        // Toggle / Increment
        $_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId][$emoji] = true;
        $_SESSION['bounty_mock_db']['reactions'][$itemId][$emoji] = (
            $_SESSION['bounty_mock_db']['reactions'][$itemId][$emoji] ?? 0
        ) + 1;
        $newActiveState = true;
    }

    $allCounts = $_SESSION['bounty_mock_db']['reactions'][$itemId];

    // Optional Supabase broadcast if configured
    if (is_supabase_configured()) {
        supabase_request('rest/v1/chat_messages', 'POST', [
            'sender_id'    => $_SESSION['user_id'] ?? '11111111-1111-1111-1111-111111111111',
            'message'      => "reaction:{$emoji}",
            'channel'      => 'lounge',
            'message_type' => 'chat',
            'meta_json'    => [
                'type'     => 'reaction_event',
                'item_id'  => $itemId,
                'emoji'    => $emoji,
                'active'   => $newActiveState,
                'counts'   => $allCounts,
            ]
        ], true);
    }

    json_response([
        'success'     => true,
        'item_id'     => $itemId,
        'emoji'       => $emoji,
        'active'      => $newActiveState,
        'count'       => $allCounts[$emoji],
        'counts'      => $allCounts,
        'user_active' => $_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId],
    ]);
}

json_response(['error' => 'Method not allowed.'], 405);
