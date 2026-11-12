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
$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");
$description = trim($_POST["description"] ?? "");
$price = filter_var($_POST["price"] ?? null, FILTER_VALIDATE_FLOAT);

$allowedCategories = ["Meals", "Snacks", "Drinks", "Desserts", "Others"];

if (
    !$id ||
    $name === "" ||
    strlen($name) > 150 ||
    !in_array($category, $allowedCategories, true) ||
    $price === false ||
    $price <= 0
) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Please provide valid product details."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT image FROM products WHERE id = ?"
    );
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Product not found."
        ]);
        exit;
    }

    $imagePath = $existing["image"];

    if (isset($_POST["remove_image"]) && $_POST["remove_image"] === "1") {
        $imagePath = null;
    }

    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Image upload failed.");
        }

        if ($_FILES["image"]["size"] > 5 * 1024 * 1024) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Image must be 5 MB or smaller."
            ]);
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES["image"]["tmp_name"]);

        $extensions = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($extensions[$mime])) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Only JPG, PNG, and WEBP images are allowed."
            ]);
            exit;
        }

        $uploadDirectory = __DIR__ . "/uploads/";

        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true)) {
                throw new RuntimeException("Unable to create upload directory.");
            }
        }

        $filename = bin2hex(random_bytes(16)) . "." . $extensions[$mime];

        if (!move_uploaded_file(
            $_FILES["image"]["tmp_name"],
            $uploadDirectory . $filename
        )) {
            throw new RuntimeException("Unable to save uploaded image.");
        }

        $imagePath = "uploads/" . $filename;
    }

    $stmt = $pdo->prepare(
        "UPDATE products
         SET name = ?, category = ?, price = ?, image = ?, description = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $name,
        $category,
        $price,
        $imagePath,
        $description,
        $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product updated successfully."
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Unable to update the product."
    ]);
}
```