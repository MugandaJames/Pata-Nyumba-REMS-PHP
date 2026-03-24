<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true) ?? [];

try {
    // Creating requests
    if ($method === 'POST') {
        if ($role !== 'customer') {
            http_response_code(403);
            echo json_encode(['error' => 'Only customers can submit requests']);
            exit;
        }

        $property_id = intval($data['property_id'] ?? 0);
        $request_type = in_array($data['request_type'] ?? '', ['rent', 'purchase']) ? $data['request_type'] : null;
        $message = htmlspecialchars(trim($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');

        if (!$property_id || !$request_type) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid property or request type']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT status, is_approved FROM properties WHERE id = ?");
        $stmt->execute([$property_id]);
        $prop = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prop || $prop['status'] !== 'available' || !$prop['is_approved']) {
            http_response_code(400);
            echo json_encode(['error' => 'Property not available for requests']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM requests WHERE property_id=? AND customer_id=? AND status='pending'");
        $stmt->execute([$property_id, $user_id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Pending request already exists']);
            exit;
        }

        $uuid = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO requests (uuid, property_id, customer_id, request_type, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uuid, $property_id, $user_id, $request_type, $message]);

        echo json_encode(['message' => 'Request submitted successfully']);
    }

    // Requests retrieval
    elseif ($method === 'GET') {
        if ($role === 'customer') {
            $stmt = $pdo->prepare("SELECT r.*, p.title, p.location, p.price FROM requests r JOIN properties p ON r.property_id = p.id WHERE r.customer_id=? ORDER BY r.created_at DESC");
            $stmt->execute([$user_id]);
        } elseif ($role === 'agent') {
            $stmt = $pdo->prepare("SELECT r.*, p.title, p.location, p.price, u.full_name AS customer_name FROM requests r JOIN properties p ON r.property_id = p.id JOIN users u ON r.customer_id = u.id WHERE p.agent_id=? ORDER BY r.created_at DESC");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->query("SELECT r.*, p.title, p.location, p.price, u.full_name AS customer_name FROM requests r JOIN properties p ON r.property_id = p.id JOIN users u ON r.customer_id = u.id ORDER BY r.created_at DESC");
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Update Property Status
    elseif ($method === 'PUT') {
        if (!in_array($role, ['agent', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $request_id = intval($data['id'] ?? 0);
        $new_status = $data['status'] ?? ''; // 'approved' or 'rejected'

        if (!$request_id || !in_array($new_status, ['approved', 'rejected'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Fetch Request & Property ID
        $stmt = $pdo->prepare("SELECT r.*, p.agent_id FROM requests r JOIN properties p ON r.property_id = p.id WHERE r.id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request || ($role === 'agent' && $request['agent_id'] != $user_id)) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized or not found']);
            exit;
        }

        if ($new_status === 'approved') {
            // 2. Lock property row and check availability
            $stmt = $pdo->prepare("SELECT status FROM properties WHERE id = ? FOR UPDATE");
            $stmt->execute([$request['property_id']]);
            $propertyRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($propertyRecord['status'] !== 'available') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Property is already taken']);
                exit;
            }

            // 3. Update Property Status 
            $finalPropStatus = ($request['request_type'] === 'rent') ? 'rented' : 'sold';
            $updateProp = $pdo->prepare("UPDATE properties SET status = ? WHERE id = ?");
            $updateProp->execute([$finalPropStatus, $request['property_id']]);

            // 4. Update Request Status
            $updateReq = $pdo->prepare("UPDATE requests SET status = 'approved' WHERE id = ?");
            $updateReq->execute([$request_id]);

            // 5. Auto-reject other pending requests
            $rejectOthers = $pdo->prepare("UPDATE requests SET status = 'rejected' WHERE property_id = ? AND id != ? AND status = 'pending'");
            $rejectOthers->execute([$request['property_id'], $request_id]);

            // 6. Generate Agreement PDF
            require_once __DIR__ . '/generate_contract.php';

            $custStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $custStmt->execute([$request['customer_id']]);
            $customerData = $custStmt->fetch(PDO::FETCH_ASSOC);

            $propStmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
            $propStmt->execute([$request['property_id']]);
            $propertyData = $propStmt->fetch(PDO::FETCH_ASSOC);

            $pdfPath = generateContractPDF($request, $propertyData, $customerData);
            $agreement_uuid = bin2hex(random_bytes(16));
            $agreement_type = ($request['request_type'] === 'rent') ? 'rental' : 'sale';

            $stmt = $pdo->prepare("INSERT INTO agreements (uuid, request_id, agreement_type, pdf_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$agreement_uuid, $request_id, $agreement_type, $pdfPath]);
        } else {
            // Simple Rejection
            $stmt = $pdo->prepare("UPDATE requests SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$request_id]);
        }

        $pdo->commit();
        echo json_encode(['message' => 'Status updated successfully']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Request Logic Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server logic failed: ' . $e->getMessage()]);
}
