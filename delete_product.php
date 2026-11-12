```php
<?php
require_once "config.php";
require_once "admin_guard.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireAdmin();

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST requests only."
    ]);
    exit;
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Invalid product ID."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE products SET is_available = 0 WHERE id = ?"
    );

    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare(
            "SELECT id FROM products WHERE id = ? AND is_available = 0"
        );
        $check->execute([$id]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Product not found."
            ]);
            exit;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Product removed from the menu."
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to remove the product."
    ]);
}
