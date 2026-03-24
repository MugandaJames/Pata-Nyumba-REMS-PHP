<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$method = $_SERVER['REQUEST_METHOD'];

try {

    // creating property
    if ($method === 'POST') {
        if ($role !== 'agent') {
            http_response_code(403);
            echo json_encode(['error' => 'Only agents can add properties']);
            exit;
        }

        // Sanitizing inputs to prevent XSS and DB breaks
        $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
        $price = floatval($_POST['price'] ?? 0);
        $property_type = in_array($_POST['property_type'] ?? '', ['rent', 'sale']) ? $_POST['property_type'] : 'rent';
        $location = htmlspecialchars(trim($_POST['location'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$title || $price <= 0 || !$location) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid title, price, and location are required']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO properties 
            (agent_id, title, description, price, property_type, location, is_approved) 
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([$user_id, $title, $description, $price, $property_type, $location]);
        $property_id = $pdo->lastInsertId();

        // Handle Multiple Image Uploads
        if (isset($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/images/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if (empty($tmpName)) continue;

                $originalName = $_FILES['images']['name'][$key];
                $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                // Validate extension & actual file content (MIME)
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($tmpName);

                if (in_array($fileExt, $allowedExts) && strpos($mimeType, 'image/') === 0) {
                    // Generate unique random name to prevent directory traversal/overwriting
                    $newFileName = bin2hex(random_bytes(12)) . '.' . $fileExt;
                    $targetPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $dbPath = 'uploads/images/' . $newFileName;
                        $stmt = $pdo->prepare("INSERT INTO property_images (property_id, image_path) VALUES (?, ?)");
                        $stmt->execute([$property_id, $dbPath]);
                    }
                }
            }
        }

        $pdo->commit();
        echo json_encode(['message' => 'Property submitted successfully with images']);
    }

    // Reports
    elseif ($method === 'GET') {
        if ($role === 'agent') {
            // Agents see their own properties + first image
            $stmt = $pdo->prepare("
                SELECT p.*, MIN(pi.image_path) AS image_path
                FROM properties p
                LEFT JOIN property_images pi ON p.id = pi.property_id
                WHERE p.agent_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$user_id]);
        } elseif ($role === 'admin') {
            // Admins see everything + agent name
            $stmt = $pdo->query("
                SELECT p.*, u.full_name AS agent_name, MIN(pi.image_path) AS image_path
                FROM properties p
                JOIN users u ON p.agent_id = u.id
                LEFT JOIN property_images pi ON p.id = pi.property_id
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
        } else {
            // Customers see only approved AND available properties
            $stmt = $pdo->query("
                SELECT p.*, MIN(pi.image_path) AS image_path
                FROM properties p
                LEFT JOIN property_images pi ON p.id = pi.property_id
                WHERE p.is_approved = 1 
                AND p.status = 'available' 
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
        }

        $properties = $stmt->fetchAll();
        echo json_encode($properties);
    }

    // Updating Property
    elseif ($method === 'PUT') {
        if ($role !== 'agent') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $id = intval($data['id'] ?? 0);

        // Security check: Does this agent own this property?
        $stmt = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
        $stmt->execute([$id]);
        $prop = $stmt->fetch();

        if (!$prop || $prop['agent_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized update attempt']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE properties 
            SET title=?, description=?, price=?, property_type=?, location=?, is_approved=0 
            WHERE id=?
        ");

        $stmt->execute([
            htmlspecialchars(trim($data['title'] ?? '')),
            htmlspecialchars(trim($data['description'] ?? '')),
            floatval($data['price'] ?? 0),
            $data['property_type'] ?? 'rent',
            htmlspecialchars(trim($data['location'] ?? '')),
            $id
        ]);

        echo json_encode(['message' => 'Property updated and sent for re-approval']);
    }

    // Deleting a Property
    elseif ($method === 'DELETE') {
        if ($role !== 'agent') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $id = intval($data['id'] ?? 0);

        // Get images before deleting record to clean up storage
        $stmt = $pdo->prepare("SELECT pi.image_path FROM property_images pi JOIN properties p ON pi.property_id = p.id WHERE p.id = ? AND p.agent_id = ?");
        $stmt->execute([$id, $user_id]);
        $images = $stmt->fetchAll();

        if ($images) {
            foreach ($images as $img) {
                $fullPath = __DIR__ . '/../' . $img['image_path'];
                if (file_exists($fullPath)) unlink($fullPath);
            }

            $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ? AND agent_id = ?");
            $stmt->execute([$id, $user_id]);
            echo json_encode(['message' => 'Property and images deleted successfully']);
        } else {
            // Try deleting even if no images exist
            $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ? AND agent_id = ?");
            $stmt->execute([$id, $user_id]);
            echo json_encode(['message' => 'Property record deleted']);
        }
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Properties Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
