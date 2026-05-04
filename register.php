<?php
// register.php

include_template('header', ['page' => 'register']);

// Access the global $connection variable
global $connection;

// Fetch regions for the dropdown
$regions = [];
$stmt_regions = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($stmt_regions) {
    while ($row = mysqli_fetch_assoc($stmt_regions)) {
        $regions[] = $row;
    }
    mysqli_free_result($stmt_regions);
} else {
    flash_message('error', 'Error loading regions: ' . mysqli_error($connection));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $region_id = $_POST['region_id'] ?? null;

    // Input validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($user_type)) {
        flash_message('error', 'All fields are required.');
        redirect('index.php?page=register');
    }

    if ($password !== $confirm_password) {
        flash_message('error', 'Passwords do not match.');
        redirect('index.php?page=register');
    }

    if (strlen($password) < 6) {
        flash_message('error', 'Password must be at least 6 characters long.');
        redirect('index.php?page=register');
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username already exists
    $stmt_check_user = mysqli_prepare($connection, "SELECT COUNT(*) FROM users WHERE username = ?");
    if ($stmt_check_user) {
        mysqli_stmt_bind_param($stmt_check_user, 's', $username);
        mysqli_stmt_execute($stmt_check_user);
        mysqli_stmt_bind_result($stmt_check_user, $user_count);
        mysqli_stmt_fetch($stmt_check_user);
        mysqli_stmt_close($stmt_check_user);

        if ($user_count > 0) {
            flash_message('error', 'Username already exists. Please choose a different one.');
            redirect('index.php?page=register');
        }
    } else {
        flash_message('error', 'Database query error: ' . mysqli_error($connection));
        redirect('index.php?page=register');
    }

    // Specific logic for 'Myanmar' and 'Malay' user types
    if ($user_type === 'Myanmar') {
        $stmt_myanmar_region = mysqli_prepare($connection, "SELECT id FROM regions WHERE region_code = 'MM'");
        if ($stmt_myanmar_region) {
            mysqli_stmt_execute($stmt_myanmar_region);
            $result = mysqli_stmt_get_result($stmt_myanmar_region);
            $myanmar_region = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_myanmar_region);

            if (!$myanmar_region) {
                flash_message('error', 'Myanmar region not found in the database. Please contact administrator.');
                redirect('index.php?page=register');
            }
            $region_id = $myanmar_region['id']; // Force region_id for Myanmar users
        } else {
            flash_message('error', 'Database query error: ' . mysqli_error($connection));
            redirect('index.php?page=register');
        }
    } else if ($user_type === 'Malay') {
        $stmt_malay_region = mysqli_prepare($connection, "SELECT id FROM regions WHERE region_code = 'MY'");
        if ($stmt_malay_region) {
            mysqli_stmt_execute($stmt_malay_region);
            $result = mysqli_stmt_get_result($stmt_malay_region);
            $malay_region = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_malay_region);

            if (!$malay_region) {
                flash_message('error', 'Malaysia region not found in the database for Malay user type. Please contact administrator.');
                redirect('index.php?page=register');
            }
            $region_id = $malay_region['id']; // Force region_id for Malay users
        } else {
            flash_message('error', 'Database query error: ' . mysqli_error($connection));
            redirect('index.php?page=register');
        }
    } else if ($user_type === 'ADMIN' || $user_type === 'General') {
        // For ADMIN or General users, region_id can be null or chosen
        if (empty($region_id)) {
            $region_id = null;
        }
    }

    // Insert user into database
    $stmt_insert_user = mysqli_prepare($connection, "INSERT INTO users (username, password, user_type, region_id) VALUES (?, ?, ?, ?)");
    if ($stmt_insert_user) {
        // region_id is nullable, so use 'i' and pass null if not set
        // If region_id is null, bind_param will insert NULL
        mysqli_stmt_bind_param($stmt_insert_user, 'sssi', $username, $hashed_password, $user_type, $region_id);

        if (mysqli_stmt_execute($stmt_insert_user)) {
            flash_message('success', 'Registration successful! You can now log in.');
            redirect('index.php?page=login');
        } else {
            flash_message('error', 'Registration error: ' . mysqli_stmt_error($stmt_insert_user));
            redirect('index.php?page=register');
        }
        mysqli_stmt_close($stmt_insert_user);
    } else {
        flash_message('error', 'Database statement preparation error: ' . mysqli_error($connection));
        redirect('index.php?page=register');
    }
}
?>

<div class="flex items-center justify-center min-h-screen -mt-20">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Register</h2>
        <form action="index.php?page=register" method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username:</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Choose a username" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password:</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Create a password" required>
            </div>
            <div class="mb-6">
                <label for="confirm_password" class="block text-gray-700 text-sm font-semibold mb-2">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
            </div>
            <div class="mb-6">
                <label for="user_type" class="block text-gray-700 text-sm font-semibold mb-2">User Type:</label>
                <select id="user_type" name="user_type" class="form-select" required onchange="toggleRegionField()">
                    <option value="">Select User Type</option>
                    <option value="ADMIN">ADMIN</option>
                    <option value="Myanmar">Myanmar</option>
                    <option value="Malay">Malay</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div id="region-field" class="mb-6 hidden">
                <label for="region_id" class="block text-gray-700 text-sm font-semibold mb-2">Origin Region:</label>
                <select id="region_id" name="region_id" class="form-select">
                    <option value="">Select Region</option>
                    <?php foreach ($regions as $region): ?>
                        <option value="<?php echo htmlspecialchars($region['id']); ?>"><?php echo htmlspecialchars($region['region_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Myanmar/Malay users will have their region automatically set.</p>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" class="btn w-full">Register</button>
            </div>
            <p class="mt-4 text-center text-gray-600 text-sm">
                Already have an account? <a href="index.php?page=login" class="text-indigo-600 hover:text-indigo-800 font-semibold">Login here</a>
            </p>
        </form>
    </div>
</div>

<script>
    // This script moved to assets/js/main.js
</script>

<?php include_template('footer'); ?>