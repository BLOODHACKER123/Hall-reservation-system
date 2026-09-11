<?php

require_once __DIR__ .  '/guard.php';

requireLogin();


header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'message' => 'Please log in first.'
    ]);
    exit;
}

echo json_encode([
    'message' => 'You are logged in.',
    'user' => [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role']
    ]
]);

