<?php
// backend/auth.php
session_start();
require 'config.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}


$data = json_decode(file_get_contents("php://input"), true);
$type = $data['type'] ?? '';

if ($type === 'register') {
    //  Registration logic 
    $full_name = htmlspecialchars(trim($data['full_name'] ?? ''));
    $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $data['password'] ?? '';
    $phone = htmlspecialchars(trim($data['phone'] ?? ''));
    $role = $data['role'] ?? 'customer';

    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }

    // Security: Only  specific roles to be chosen via registration
    if (!in_array($role, ['agent', 'customer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        exit;
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        // Use PASSWORD_DEFAULT (automatically upgrades hash if PHP updates)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $is_approved = ($role === 'customer') ? 1 : 0;

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, is_approved) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$full_name, $email, $hashedPassword, $role, $phone, $is_approved]);

        echo json_encode([
            'message' => 'Registration successful',
            'role' => $role,
            'approved' => $is_approved
        ]);
    } catch (PDOException $e) {
        // Security: Logging locally, so we don't leak details in the JSON response
        error_log("Registration Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error occurred']);
    }
} elseif ($type === 'login') {
    //  Login logic 
    $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, is_approved FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
            exit;
        }

        // Agents must be approved
        if ($user['role'] === 'agent' && !$user['is_approved']) {
            http_response_code(403);
            echo json_encode(['error' => 'Agent not approved by admin yet']);
            exit;
        }

        // SECURITY: PREVENT SESSION FIXATION
        session_regenerate_id(true);

        // Login success → create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Returning exact keys your frontend (auth.js) uses
        echo json_encode([
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]);
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error occurred']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type. Must be "login" or "register".']);
}
