<?php
// This file is included by api.php and assumes $pdo, $supabaseClient, $action, and $input are available.

// Ensure only admin can perform these actions (using PHP app's session for now)
if ($_SESSION['username'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage licenses.']);
    exit;
}

switch ($action) {
    case 'get_licenses':
        try {
            $response = $supabaseClient->from('licenses')
                                       ->select('id, user_id, license_key, status, issued_at, expires_at, max_devices, current_devices')
                                       ->order('created_at', false) // false for DESC
                                       ->execute();
            $licenses = $response->data;

            // Fetch user emails from auth.users for display
            $user_ids = array_column($licenses, 'user_id');
            if (!empty($user_ids)) {
                $user_response = $supabaseClient->from('users') // auth.users is exposed as 'users' in the client
                                                ->select('id, email')
                                                ->in('id', $user_ids)
                                                ->execute();
                $supabase_users = $user_response->data;
                $user_email_map = [];
                foreach ($supabase_users as $user) {
                    $user_email_map[$user['id']] = $user['email'];
                }

                foreach ($licenses as &$license) {
                    $license['user_email'] = $user_email_map[$license['user_id']] ?? 'N/A';
                }
            }
            echo json_encode($licenses);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch licenses: ' . $e->getMessage()]);
        }
        break;

    case 'create_license':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $input['user_id'] ?? null; // This should be a Supabase user UUID
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

            try {
                // Check if license key already exists
                $existing_license_response = $supabaseClient->from('licenses')
                                                            ->select('id')
                                                            ->eq('license_key', $license_key)
                                                            ->limit(1)
                                                            ->execute();
                if (!empty($existing_license_response->data)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'License key already exists.']);
                    exit;
                }

                $data_to_insert = [
                    'user_id' => $user_id,
                    'license_key' => $license_key,
                    'status' => $status,
                    'issued_at' => $issued_at,
                    'expires_at' => $expires_at,
                    'max_devices' => $max_devices,
                    'current_devices' => 0 // Default to 0
                ];

                $response = $supabaseClient->from('licenses')
                                           ->insert($data_to_insert)
                                           ->execute();
                
                if ($response->hasError()) {
                    throw new Exception($response->getError()->getMessage());
                }
                echo json_encode(['success' => true, 'message' => 'License created successfully.']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create license: ' . $e->getMessage()]);
            }
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
            $data_to_update = [];
            foreach ($updates as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    $data_to_update[$key] = $value;
                }
            }
            // Add updated_at manually as Supabase client doesn't auto-update it for us
            $data_to_update['updated_at'] = date('Y-m-d H:i:s');

            if (empty($data_to_update)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update.']);
                exit;
            }

            try {
                $response = $supabaseClient->from('licenses')
                                           ->update($data_to_update)
                                           ->eq('id', $id)
                                           ->execute();
                
                if ($response->hasError()) {
                    throw new Exception($response->getError()->getMessage());
                }
                echo json_encode(['success' => true, 'message' => 'License updated successfully.']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update license: ' . $e->getMessage()]);
            }
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

            try {
                $response = $supabaseClient->from('licenses')
                                           ->delete()
                                           ->eq('id', $id)
                                           ->execute();
                
                if ($response->hasError()) {
                    throw new Exception($response->getError()->getMessage());
                }
                echo json_encode(['success' => true, 'message' => 'License deleted successfully.']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete license: ' . $e->getMessage()]);
            }
        }
        break;
}
?>