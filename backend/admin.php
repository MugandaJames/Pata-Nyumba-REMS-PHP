<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Administrator privileges required.']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

try {
    // Fetch all users
    if ($action === 'get_all_users') {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, role, phone, is_approved, created_at 
            FROM users 
            WHERE id != ? 
            ORDER BY role ASC, created_at DESC
        ");
        $stmt->execute([$admin_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Fetch Pending agents
    elseif ($action === 'get_pending_agents') {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, phone, created_at 
            FROM users 
            WHERE role = 'agent' AND is_approved = 0
        ");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Approve agents
    elseif ($action === 'approve_agent') {
        $agentId = intval($data['agent_id'] ?? 0);
        if (!$agentId) {
            http_response_code(400);
            echo json_encode(['error' => 'Agent ID required']);
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'agent'");
        $stmt->execute([$agentId]);

        $log = $pdo->prepare("INSERT INTO audit_log (user_id, action_type, table_name, record_id, new_value) VALUES (?, 'USER_APPROVAL', 'users', ?, 'is_approved=1')");
        $log->execute([$admin_id, $agentId]);

        $pdo->commit();
        echo json_encode(['message' => 'Agent approved and logged']);
        exit;
    }

    // Fetch pending properties
    elseif ($action === 'get_pending_properties') {
        $stmt = $pdo->query("
            SELECT p.*, u.full_name AS agent_name, MIN(pi.image_path) AS image_path
            FROM properties p
            JOIN users u ON p.agent_id = u.id
            LEFT JOIN property_images pi ON p.id = pi.property_id
            WHERE p.is_approved = 0
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Approve Property
    elseif ($action === 'approve_property') {
        $propertyId = intval($data['property_id'] ?? 0);
        if (!$propertyId) {
            http_response_code(400);
            echo json_encode(['error' => 'Property ID required']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE properties 
            SET is_approved = 1, status = 'available' 
            WHERE id = ?
        ");
        $stmt->execute([$propertyId]);

        // Audit Log Entry
        $log = $pdo->prepare("INSERT INTO audit_log (user_id, action_type, table_name, record_id, new_value) VALUES (?, 'PROPERTY_APPROVAL', 'properties', ?, 'is_approved=1, status=available')");
        $log->execute([$admin_id, $propertyId]);

        $pdo->commit();
        echo json_encode(['message' => 'Property approved and listed as available']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Admin Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A server error occurred.']);
}
