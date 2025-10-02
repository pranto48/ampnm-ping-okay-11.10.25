<?php
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-700 hover:text-white';
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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Inter', sans-serif; }
        body {
            background-color: #0f172a; /* slate-900 */
            background-image: radial-gradient(#334155 1px, transparent 0);
            background-size: 30px 30px;
        }
        .status-indicator { width: 10px; height: 10px; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
        .status-online { background-color: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .status-offline { background-color: #ef4444; box-shadow: 0 0 8px #ef4444; animation: none; }
        .status-unknown { background-color: #64748b; animation: none; }
        .loader { border: 4px solid #334155; border-top: 4px solid #22d3ee; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <nav class="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center gap-2 text-white font-bold">
                        <i class="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>Network Security</span>
                    </a>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="index.php" class="px-3 py-2 rounded-md text-sm font-medium <?= isActive('index.php') ?>">Dashboard</a>
                        <a href="devices.php" class="px-3 py-2 rounded-md text-sm font-medium <?= isActive('devices.php') ?>">Devices</a>
                        <a href="history.php" class="px-3 py-2 rounded-md text-sm font-medium <?= isActive('history.php') ?>">History</a>
                        <a href="map.php" class="px-3 py-2 rounded-md text-sm font-medium <?= isActive('map.php') ?>">Map</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <main>