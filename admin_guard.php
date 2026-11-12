<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function requireAdmin(): void
{
    if (($_SESSION["role"] ?? "") !== "admin") {
        http_response_code(401);
        header("Content-Type: application/json; charset=UTF-8");

        echo json_encode([
            "success" => false,
            "message" => "Administrator login required."
        ]);

        exit;
    }
}