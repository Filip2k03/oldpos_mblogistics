<?php
// includes/functions.php
// Contains helper functions for the application

/**
 * Redirects to a specified URL.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    // It's crucial that no output (HTML, spaces, etc.) occurs before header()
    // This function must be called BEFORE any HTML is echoed/printed.
    header("Location: " . $url);
    exit(); // Always exit after a redirect to prevent further script execution
}

/**
 * Sets a flash message in the session.
 * @param string $type Type of message (e.g., 'success', 'error', 'info', 'warning').
 * @param string $message The message content.
 */
function flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Displays and clears all flash messages with transition.
 */
function display_flash_messages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $key => $msg) {
            $class = '';
            switch ($msg['type']) {
                case 'success':
                    $class = 'bg-green-100 border-green-400 text-green-700';
                    break;
                case 'error':
                    $class = 'bg-red-100 border-red-400 text-red-700';
                    break;
                case 'info':
                    $class = 'bg-blue-100 border-blue-400 text-blue-700';
                    break;
                case 'warning':
                    $class = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                    break;
            }
            echo "<div class='flash-message {$class}' id='flash-message-{$key}'>{$msg['message']}</div>";
        }
        unset($_SESSION['flash_messages']); // Clear messages after displaying
        ?>
        <script>
            // Automatically hide flash messages after a few seconds
            document.querySelectorAll('.flash-message').forEach(messageDiv => {
                setTimeout(() => {
                    messageDiv.classList.add('hidden'); // Add hidden class for CSS transition
                    setTimeout(() => messageDiv.remove(), 500); // Remove from DOM after transition
                }, 3000); // 3 seconds
            });
        </script>
        <?php
    }
}

/**
 * Checks if a user is authenticated.
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user is an admin.
 * @return bool True if admin, false otherwise.
 */
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADMIN';
}

/**
 * Generates a unique voucher code based on region prefix and sequence.
 * @param string $prefix The region prefix (e.g., 'MM', 'MY').
 * @param int $sequence The current sequence number.
 * @return string The formatted voucher code.
 */
function generate_voucher_code($prefix, $sequence) {
    // Pad the sequence number with leading zeros to the defined length
    $padded_sequence = str_pad($sequence, VOUCHER_CODE_LENGTH, '0', STR_PAD_LEFT);
    return $prefix . $padded_sequence;
}

/**
 * Includes a template file, passing data to it.
 * @param string $template_name The name of the template file (e.g., 'header', 'footer').
 * @param array $data An associative array of variables to extract into the template scope.
 */
function include_template($template_name, $data = []) {
    extract($data); // Extract array variables into the current symbol table
    require_once __DIR__ . "/../templates/{$template_name}.php"; // Adjusted path
}
?>