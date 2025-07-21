<?php
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role_id() {
    return $_SESSION['role_id'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role(array $allowed_roles) {
    require_login();
    $role_id = get_user_role_id();
    if (!in_array($role_id, $allowed_roles)) {
        http_response_code(403);
        echo "Access denied. You do not have permission to view this page.";
        exit;
    }
}
?>
