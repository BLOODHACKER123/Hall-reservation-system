<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function requireLogin(){
     global $pdo;
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        exit('Please log in first.');
    }


 try{
     $statement = $pdo->prepare(
            'SELECT id, name, role, status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $_SESSION['user_id']]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION = [];
            session_destroy();

            http_response_code(401);
            exit('Please log in again.');
        }

        if ($user['status'] !== 'active') {
            $_SESSION = [];
            session_destroy();

            http_response_code(403);
            exit('Your account is blocked.');
        }

        // Refresh session value 
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];


 }catch(PDOException $error){
    error_log($error->getMessage());

    http_response_code(500);
    exit('Unable to verify your account.');
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


