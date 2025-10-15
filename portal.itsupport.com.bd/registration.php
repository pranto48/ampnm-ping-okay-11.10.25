<?php
require_once 'includes/functions.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        $pdo = getLicenseDbConnection();
        $stmt = $pdo->prepare("SELECT id FROM `customers` WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = 'Email already registered. Please login or use a different email.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO `customers` (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$first_name, $last_name, $email, $hashed_password])) {
                $success_message = 'Registration successful! You can now <a href="login.php" class="text-blue-600 hover:underline">login</a>.';
            } else {
                $error_message = 'Something went wrong during registration. Please try again.';
            }
        }
    }
}

portal_header("Register - IT Support BD Portal");
?>

<div class="max-w-md mx-auto card">
    <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">Register for an Account</h1>

    <?php if ($error_message): ?>
        <div class="alert-error mb-4">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert-success mb-4">
            <?= $success_message ?>
        </div>
    <?php endif; ?>

    <form action="registration.php" method="POST" class="space-y-4">
        <div>
            <label for="first_name" class="block text-gray-700 text-sm font-bold mb-2">First Name:</label>
            <input type="text" id="first_name" name="first_name" class="form-input" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div>
            <label for="last_name" class="block text-gray-700 text-sm font-bold mb-2">Last Name:</label>
            <input type="text" id="last_name" name="last_name" class="form-input" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
        <div>
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
            <input type="email" id="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div>
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
            <input type="password" id="password" name="password" class="form-input" required>
        </div>
        <div>
            <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
        </div>
        <button type="submit" class="btn-primary w-full">Register</button>
    </form>
    <p class="text-center text-gray-600 text-sm mt-4">
        Already have an account? <a href="login.php" class="text-blue-600 hover:underline">Login here</a>.
    </p>
</div>

<?php portal_footer(); ?>