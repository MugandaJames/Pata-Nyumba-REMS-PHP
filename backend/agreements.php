<?php
session_start();
require 'config.php';

// Authentication
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    //  Secure link for contract download
    if ($method === 'GET' && isset($_GET['download'])) {
        $uuid = $_GET['download'];

        $stmt = $pdo->prepare("
            SELECT a.pdf_path, r.customer_id, p.agent_id
            FROM agreements a
            JOIN requests r ON a.request_id = r.id
            JOIN properties p ON r.property_id = p.id
            WHERE a.uuid = ? AND a.deleted_at IS NULL
        ");
        $stmt->execute([$uuid]);
        $agreement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agreement) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Agreement not found']);
            exit;
        }

        // Access Control
        $is_owner = ($role === 'customer' && $agreement['customer_id'] == $user_id);
        $is_agent = ($role === 'agent' && $agreement['agent_id'] == $user_id);
        $is_admin = ($role === 'admin');

        if (!$is_owner && !$is_agent && !$is_admin) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $filePath = __DIR__ . '/../' . $agreement['pdf_path'];

        if (!file_exists($filePath)) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Physical file missing']);
            exit;
        }

        if (ob_get_level()) ob_end_clean();

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Contract_' . $uuid . '.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // Agreements listed

    elseif ($method === 'GET') {
        header('Content-Type: application/json');

        if ($role === 'customer') {
            $stmt = $pdo->prepare("
                SELECT a.uuid, a.agreement_type, a.generated_at, p.title, p.location, p.price
                FROM agreements a
                JOIN requests r ON a.request_id = r.id
                JOIN properties p ON r.property_id = p.id
                WHERE r.customer_id = ? AND a.deleted_at IS NULL
                ORDER BY a.generated_at DESC
            ");
            $stmt->execute([$user_id]);
        } elseif ($role === 'agent') {
            $stmt = $pdo->prepare("
                SELECT a.uuid, a.agreement_type, a.generated_at, p.title, p.location, u.full_name AS customer_name
                FROM agreements a
                JOIN requests r ON a.request_id = r.id
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.customer_id = u.id
                WHERE p.agent_id = ? AND a.deleted_at IS NULL
                ORDER BY a.generated_at DESC
            ");
            $stmt->execute([$user_id]);
        } elseif ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT a.uuid, a.agreement_type, a.generated_at, p.title, u.full_name AS customer_name
                FROM agreements a
                JOIN requests r ON a.request_id = r.id
                JOIN properties p ON r.property_id = p.id
                JOIN users u ON r.customer_id = u.id
                WHERE a.deleted_at IS NULL
                ORDER BY a.generated_at DESC
            ");
            $stmt->execute();
        }

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    error_log("Agreement Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal error occurred']);
}
