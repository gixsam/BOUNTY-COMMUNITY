<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Job Comment & Inquiries Handler API
// Path: public/api/comment_handler.php
// Hostinger PHP 8.2 / Apache
// ==============================================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ctx = get_active_user_context();

// 1. GET: Retrieve comments for a job
if ($method === 'GET') {
    $jobId = trim($_GET['job_id'] ?? '');
    if (empty($jobId)) {
        json_response(['error' => 'Missing job_id parameter.'], 400);
    }
    $comments = get_job_comments($jobId);
    json_response([
        'success'  => true,
        'job_id'   => $jobId,
        'comments' => $comments,
    ]);
}

// 2. POST: Submit a quick reply / inquiry under a job
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $jobId   = trim($input['job_id'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($jobId)) {
        json_response(['error' => 'Missing job_id.'], 422);
    }

    if (empty($message)) {
        json_response(['error' => 'Question or reply cannot be empty.'], 422);
    }

    if (!isset($_SESSION['bounty_mock_db']['job_comments'][$jobId])) {
        $_SESSION['bounty_mock_db']['job_comments'][$jobId] = [];
    }

    $newComment = [
        'id'            => 'comm-' . uniqid(),
        'job_id'        => $jobId,
        'sender_name'   => $ctx['display_name'] ?? 'Hunter',
        'sender_handle' => $ctx['handle'] ?? 'user',
        'sender_role'   => $ctx['role'] ?? 'hunter',
        'sender_avatar' => $ctx['avatar_url'] ?? 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150',
        'message'       => $message,
        'created_at'    => 'Just now',
    ];

    $_SESSION['bounty_mock_db']['job_comments'][$jobId][] = $newComment;

    // Optional Supabase broadcast to chat_messages with job metadata
    if (is_supabase_configured()) {
        supabase_request('rest/v1/chat_messages', 'POST', [
            'sender_id'    => $_SESSION['user_id'] ?? $ctx['id'] ?? '11111111-1111-1111-1111-111111111111',
            'message'      => $message,
            'channel'      => 'job_inquiries',
            'message_type' => 'chat',
            'meta_json'    => [
                'type'          => 'job_reply',
                'job_id'        => $jobId,
                'sender_name'   => $newComment['sender_name'],
                'sender_handle' => $newComment['sender_handle'],
                'sender_role'   => $newComment['sender_role'],
                'sender_avatar' => $newComment['sender_avatar'],
            ]
        ], true);
    }

    json_response([
        'success' => true,
        'job_id'  => $jobId,
        'comment' => $newComment,
    ], 201);
}

json_response(['error' => 'Method not allowed.'], 405);
