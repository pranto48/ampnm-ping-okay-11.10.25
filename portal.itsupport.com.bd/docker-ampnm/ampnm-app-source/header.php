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
    <title>AMPNM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-300 min-h-screen">
    <!-- Navigation is now handled by the React application (AppNavigation.tsx) -->
    <!-- License status display is also handled by the React application -->
    <div class="page-content">