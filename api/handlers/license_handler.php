<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

// Ensure only admin can perform these actions
if ($_SESSION['username'] !== 'admin') { // Using session username for simplicity, could use Supabase has_role check
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage licenses.']);
    exit;
}

switch ($action) {
    case 'get_licenses':
        $stmt = $pdo->query("SELECT l.id, l.license_key, l.status, l.issued_at, l.expires_at, l.max_devices, l.current_devices, u.email as user_email FROM public.licenses l LEFT JOIN auth.users u ON l.user_id = u.id ORDER BY l.created_at DESC");
        $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($licenses);
        break;

    case 'create_license':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $input['user_id'] ?? null;
            $license_key = $input['license_key'] ?? '';
            $status = $input['status'] ?? 'active';
            $issued_at = $input['issued_at'] ?? null;
            $expires_at = $input['expires_at'] ?? null;
            $max_devices = $input['max_devices'] ?? 1;

            if (empty($user_id) || empty($license_key)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID and License Key are required.']);
                exit;
            }

            // Check if license key already exists
            $stmt = $pdo->prepare("SELECT id FROM public.licenses WHERE license_key = ?");
            $stmt->execute([$license_key]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'License key already exists.']);
                exit;
            }

            $sql = "INSERT INTO public.licenses (user_id, license_key, status, issued_at, expires_at, max_devices) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $license_key, $status, $issued_at, $expires_at, $max_devices]);
            
            echo json_encode(['success' => true, 'message' => 'License created successfully.']);
        }
        break;

    case 'update_license':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $updates = $input['updates'] ?? [];

            if (!$id || empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'License ID and updates are required.']);
                exit;
            }

            $allowed_fields = ['user_id', 'license_key', 'status', 'issued_at', 'expires_at', 'max_devices', 'current_devices'];
            $fields = [];
            $params = [];

            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update.']);
                exit;
            }

            $params[] = $id;
            $sql = "UPDATE public.licenses SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'License updated successfully.']);
        }
        break;

    case 'delete_license':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'License ID is required.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM public.licenses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'License deleted successfully.']);
        }
        break;
}