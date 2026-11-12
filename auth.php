<?php
session_start();

require_once "config.php";

header("Content-Type: application/json; charset=UTF-8");

function respond($success, $message, $status = 200, $extra = [])
{
    http_response_code($status);

    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "POST requests only.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    respond(false, "Invalid request data.", 400);
}

$action = $data["action"] ?? "";

try {
    if ($action === "register") {
        $name = trim($data["name"] ?? "");
        $email = strtolower(trim($data["email"] ?? ""));
        $password = $data["password"] ?? "";

        if (
            $name === "" ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !is_string($password) ||
            strlen($password) < 8
        ) {
            respond(
                false,
                "Enter a valid name and email. Passwords must be at least 8 characters.",
                400
            );
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            respond(false, "This email is already registered.", 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role)
             VALUES (?, ?, ?, 'customer')"
        );

        $stmt->execute([$name, $email, $hash]);

        respond(true, "Account created successfully.");
    }

    if ($action === "login") {
        $email = strtolower(trim($data["email"] ?? ""));
        $password = $data["password"] ?? "";
        $requestedRole = $data["role"] ?? "customer";

        if (
            !in_array($requestedRole, ["customer", "admin"], true) ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !is_string($password)
        ) {
            respond(false, "Invalid email or password.", 401);
        }

        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, password_hash, role
             FROM users
             WHERE email = ? AND role = ?
             LIMIT 1"
        );

        $stmt->execute([$email, $requestedRole]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user["password_hash"])) {
            respond(false, "Incorrect email or password.", 401);
        }

        session_regenerate_id(true);

        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["name"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];

        respond(true, "Login successful.", 200, [
            "user" => [
                "id" => (int)$user["id"],
                "name" => $user["full_name"],
                "email" => $user["email"],
                "role" => $user["role"]
            ]
        ]);
    }

    if ($action === "logout") {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        respond(true, "Logged out successfully.");
    }

    if ($action === "session") {
        if (!isset($_SESSION["user_id"])) {
            respond(false, "Not logged in.", 401);
        }

        respond(true, "Session active.", 200, [
            "user" => [
                "id" => $_SESSION["user_id"],
                "name" => $_SESSION["name"],
                "email" => $_SESSION["email"],
                "role" => $_SESSION["role"]
            ]
        ]);
    }

    respond(false, "Unknown action.", 400);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        respond(false, "This email is already registered.", 409);
    }

    respond(false, "A database error occurred.", 500);
}