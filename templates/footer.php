   <?php
// templates/header.php
// Common header for all pages, including Tailwind CSS CDN

// The $page variable is passed to include_template function
// No redirect logic here. Redirects MUST happen before any HTML output.
// Ensure assets.php is included to load CSS/JS
require_once __DIR__ . '/../assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo ucfirst(str_replace('_', ' ', $page)); ?></title>

</head>
<body class="min-h-screen flex flex-col">
    <footer class="bg-gray-800 text-white text-center p-4 mt-8 shadow-inner">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        <p class="text-sm mt-1">Developed by Payvia POS System</p>
    </footer>
</body>
</html>