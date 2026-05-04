<?php
// assets.php
// Manages dynamic loading of CSS and JavaScript assets.

function load_assets($page_name) {
    // Tailwind CSS CDN
    echo '<!-- Tailwind CSS CDN -->';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    // Google Fonts
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    // Custom CSS
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    // Custom JavaScript
    echo '<script src="assets/js/main.js"></script>';

    // Page-specific scripts (example)
    // if ($page_name === 'voucher_create') {
    //     echo '<script src="assets/js/voucher_create_specific.js"></script>';
    // }
}
?>