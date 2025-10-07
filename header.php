<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dyad Network Security</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="/" data-navigo class="flex items-center gap-2 text-white font-bold">
                        <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>Network Security</span>
                    </a>
                </div>
                <div class="hidden md:block">
                    <div id="main-nav" class="ml-10 flex items-baseline space-x-4">
                        <a href="/" data-navigo class="px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="/devices" data-navigo class="px-3 py-2 rounded-md text-sm font-medium">Devices</a>
                        <a href="/history" data-navigo class="px-3 py-2 rounded-md text-sm font-medium">History</a>
                        <a href="/map" data-navigo class="px-3 py-2 rounded-md text-sm font-medium">Map</a>
                        <?php if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin'): ?>
                            <a href="/users" data-navigo class="px-3 py-2 rounded-md text-sm font-medium">Users</a>
                        <?php endif; ?>
                        <a href="logout.php" class="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>