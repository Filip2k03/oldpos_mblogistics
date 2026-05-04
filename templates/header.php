<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo APP_NAME; ?> - <?php echo ucfirst(str_replace('_', ' ', $page)); ?></title>
    <?php load_assets($page); ?>
    <style>
        :root {
            --color-burgundy: #800020;
        }

        .ship-loader {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.7s ease-out, visibility 0.7s ease-out;
        }

        .ship-loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .mb-logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--color-burgundy);
            margin-top: 15px;
        }

        .progress-bar {
            width: 200px;
            height: 4px;
            background: #ecf0f1;
            border-radius: 2px;
            margin-top: 20px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: var(--color-burgundy);
            width: 0%;
            transition: width 0.4s ease-out;
        }

        .ship-svg {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-100 text-gray-900">

<!-- Loader -->
<div class="ship-loader" id="shipLoader">
    <svg class="ship-svg" width="200" height="100" viewBox="0 0 200 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="20" y="70" width="160" height="20" rx="5" fill="#2c3e50" />
        <rect x="35" y="50" width="30" height="20" rx="3" fill="#3498db" stroke="#2980b9" stroke-width="2"/>
        <rect x="70" y="50" width="30" height="20" rx="3" fill="#e74c3c" stroke="#c0392b" stroke-width="2"/>
        <rect x="105" y="50" width="30" height="20" rx="3" fill="#2ecc71" stroke="#27ae60" stroke-width="2"/>
        <rect x="140" y="50" width="30" height="20" rx="3" fill="#f1c40f" stroke="#f39c12" stroke-width="2"/>
        <rect x="110" y="35" width="20" height="15" rx="2" fill="#bdc3c7" />
        <ellipse cx="100" cy="92" rx="80" ry="6" fill="#b3e0fc" opacity="0.5"/>
    </svg>
    <div class="mb-logo">MB SHIPPING</div>
    <div class="progress-bar">
        <div class="progress" id="loaderProgress"></div>
    </div>
</div>

<!-- Header -->
<nav class="bg-gray-800 shadow-md">
    <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
        <!-- ✅ Back Button: hidden by default -->
        <button id="backButton" onclick="window.history.back()" class="hidden text-sm text-white bg-gray-700 px-4 py-2 rounded hover:bg-gray-600 transition">
            ← Back
        </button>

        <!-- Logo / App Name -->
        <a href="index.php?page=dashboard" class="text-white text-xl font-bold hover:underline">
            <?php echo APP_NAME; ?>
        </a>

        <!-- Logout -->
        <div>
            <?php if (is_logged_in()): ?>
                <a href="index.php?page=logout" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition shadow-md">
                    Logout
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script src="../assets/js/main.js"></script>
<script>
    // Loader progress
    function updateLoaderProgress(percent) {
        document.getElementById('loaderProgress').style.width = percent + '%';
    }

    window.addEventListener('load', function () {
        updateLoaderProgress(100);
        setTimeout(() => document.getElementById('shipLoader').classList.add('hidden'), 500);
    });

    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.floor(Math.random() * 10);
        if (progress >= 90) clearInterval(interval);
        updateLoaderProgress(progress);
    }, 300);

    // ✅ Show back button only if there’s history
    window.addEventListener('DOMContentLoaded', () => {
        if (window.history.length > 1) {
            document.getElementById('backButton').classList.remove('hidden');
        }
    });
</script>
</body>
</html>
