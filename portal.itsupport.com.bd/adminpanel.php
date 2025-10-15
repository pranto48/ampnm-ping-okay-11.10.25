<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin/index.php'); // Redirect to actual admin dashboard
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Username and password are required.';
    } else {
        if (authenticateAdmin($username, $password)) {
            header('Location: admin/index.php'); // Redirect to actual admin dashboard
            exit;
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}

admin_header("Admin Login");
?>

<div class="max-w-md mx-auto admin-card p-8">
    <h1 class="text-3xl font-bold text-blue-400 mb-6 text-center">Admin Login</h1>

    <?php if ($error_message): ?>
        <div class="alert-admin-error mb-4">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <form action="adminpanel.php" method="POST" class="space-y-4">
        <div>
            <label for="username" class="block text-gray-300 text-sm font-bold mb-2">Username:</label>
            <input type="text" id="username" name="username" class="form-admin-input" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div>
            <label for="password" class="block text-gray-300 text-sm font-bold mb-2">Password:</label>
            <input type="password" id="password" name="password" class="form-admin-input" required>
        </div>
        <button type="submit" class="btn-admin-primary w-full">Login</button>
    </form>
</div>

<?php admin_footer(); ?>