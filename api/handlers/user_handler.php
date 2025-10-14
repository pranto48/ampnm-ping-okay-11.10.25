<?php
// This file is included by api.php and assumes $pdo, $supabaseClient, $action, and $input are available.

// Ensure only admin can perform these actions
if ($_SESSION['username'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage users.']);
    exit;
}

switch ($action) {
    case 'get_users':
        $stmt = $pdo->query("SELECT id, username, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
        break;

    case 'get_supabase_users': // New action to fetch users from Supabase
        try {
            $response = $supabaseClient->from('users') // 'users' is the public schema view of auth.users
                                       ->select('id, email')
                                       ->order('email', false) // Order by email ascending
                                       ->execute();
            $supabase_users = $response->data;
            echo json_encode($supabase_users);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch Supabase users: ' . $e->getMessage()]);
        }
        break;

    case 'create_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and password are required.']);
                exit;
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists.']);
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed_password]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        }
        break;

    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                exit;
            }

            // Prevent admin from deleting themselves
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['username'] === 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot delete the admin user.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        }
        break;
}