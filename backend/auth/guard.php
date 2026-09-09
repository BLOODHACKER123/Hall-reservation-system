<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(){
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        exit('Please log in first.');
    }
}

function requireRole($requiredRole){
requireLogin();

    if (($_SESSION['user_role'] ?? '') !== $requiredRole) {
        http_response_code(403);
        exit('Access denied.');
    }
}


?>