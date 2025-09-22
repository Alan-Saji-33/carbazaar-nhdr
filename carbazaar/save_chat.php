<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $assistant = $_POST['assistant'] ?? '';
    
    if ($user && $assistant) {
        $_SESSION['chat_history'][] = [
            'user' => $user,
            'assistant' => $assistant
        ];
    }
}
?>