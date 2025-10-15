<?php
require_once 'includes/functions.php';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectToDashboard();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Email and password are required.';
    } else {
        if (authenticateCustomer($email, $password)) {
            redirectToDashboard();
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

portal_header("Login - IT Support BD Portal");
?>

<div class="max-w-md mx-auto card">
    <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">Login to Your Account</h1>

    <?php if ($error_message): ?>
        <div class="alert-error mb-4">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST" class="space-y-4">
        <div>
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
            <input type="email" id="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div>
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
            <input type="password" id="password" name="password" class="form-input" required>
        </div>
        <button type="submit" class="btn-primary w-full">Login</button>
    </form>
    <p class="text-center text-gray-600 text-sm mt-4">
        Don't have an account? <a href="registration.php" class="text-blue-600 hover:underline">Register here</a>.
    </p>
</div>

<?php portal_footer(); ?>