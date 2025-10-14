<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Required - AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md text-center">
        <i class="fas fa-lock text-red-500 text-6xl mb-4"></i>
        <h1 class="text-3xl font-bold text-white mb-4">License Required</h1>
        <p class="text-slate-400 mb-6">
            A valid license is required to access this application.
        </p>
        <?php
        session_start();
        if (isset($_SESSION['license_message'])) {
            echo '<div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 mb-4 mx-auto max-w-xs">';
            echo htmlspecialchars($_SESSION['license_message']);
            echo '</div>';
        }
        ?>
        <p class="text-slate-500 text-sm">
            Please contact support or visit our licensing portal to obtain or renew your license.
        </p>
        <div class="mt-6">
            <a href="logout.php" class="px-6 py-3 bg-slate-700 text-white rounded-lg hover:bg-slate-600">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout
            </a>
        </div>
    </div>
</body>
</html>