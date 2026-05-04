<?php
// login.php

// No include_template('header') here. It's handled in index.php
// The header.php will be included by the template system IF no redirect happens.

// Access the global $connection variable
global $connection;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        flash_message('error', 'Please enter both username and password.');
        redirect('index.php?page=login'); // Redirect immediately
    }

    // Use prepared statements for security
    $stmt = mysqli_prepare($connection, "SELECT id, username, password, user_type FROM users WHERE username = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];

            // Handle "Remember Me"
            if (!empty($_POST['remember_me'])) {
                setcookie('remember_username', $username, time() + (86400 * 30), "/"); // 30 days
            } else {
                setcookie('remember_username', '', time() - 3600, "/"); // delete cookie
            }

            flash_message('success', 'Logged in successfully!');
            // Redirect based on user type
            if ($user['user_type'] === 'ADMIN') {
                redirect('index.php?page=dashboard');
            } else {
                redirect('index.php?page=dashboard');
            }
            // NO CODE SHOULD BE EXECUTED AFTER A REDIRECT
        } else {
            flash_message('error', 'Invalid username or password.');
            redirect('index.php?page=login'); // Redirect immediately
        }
    } else {
        flash_message('error', 'Database query error: ' . mysqli_error($connection));
        redirect('index.php?page=login'); // Redirect immediately
    }
}

// If no POST request or if login failed and redirected,
// the script will continue to render the login form.
// Only include the header and footer if no redirect has occurred.
include_template('header', ['page' => 'login']);
?>

<div class="flex items-center justify-center min-h-screen -mt-20">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Login</h2>
        <form action="index.php?page=login" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username:</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="bg-white text-gray-700 border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-sky-400"
                    placeholder="Enter your username"
                    required
                    value="<?= htmlspecialchars($_COOKIE['remember_username'] ?? '') ?>"
                >
            </div>
            <div class="mb-6 relative"> <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password:</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="bg-white text-gray-700 border border-gray-300 rounded-lg p-2 pr-10 w-full focus:outline-none focus:ring-2 focus:ring-sky-400"
                    placeholder="Enter your password"
                    required
                >
                <span class="absolute inset-y-0 right-0 pr-3 flex items-center pt-8 cursor-pointer" id="togglePassword">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </span>
            </div>
            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="remember_me" class="form-checkbox text-sky-600">
                    <span class="ml-2 text-sm text-gray-700">Remember me</span>
                </label>
            </div>
            <div class="flex items-center justify-between">
                <button
                    type="submit"
                    class="bg-gray-800 text-white border hover:bg-gray-950 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-sky-400"
                >
                    Login
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        // toggle the eye icon
        const icon = this.querySelector('svg');
        if (type === 'password') {
            // Eye open icon
            icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;
        } else {
            // Eye crossed out icon
            icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.981 18.025A10.603 10.603 0 0 0 12 21.75c4.638 0 8.573-3.007 9.963-7.178.07-.207.07-.431 0-.639C20.577 7.51 16.64 4.5 12 4.5a10.603 10.603 0 0 0-7.019 3.725M12 12c-1.29 0-2.502.261-3.606.724L12 12Zm0 0c1.29 0 2.502.261 3.606.724L12 12Zm-3.606.724L4.019 18.025m7.981-5.301L19.981 18.025m-7.981-5.301a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;
        }
    });
</script>

<?php include_template('footer'); ?>