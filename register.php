<?php
session_start();
include 'config/database.php';

$message = '';
$success = false;

$neighbourhoods = $conn->query('SELECT neighbourhood_id, name FROM neighbourhoods ORDER BY name');

if (isset($_POST['register'])) {

    $name             = trim($_POST['name']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $neighbourhood_id = !empty($_POST['neighbourhood_id']) ? (int) $_POST['neighbourhood_id'] : null;

    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $message = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $message = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $message = 'Password must contain at least one number.';
    } elseif (!preg_match('/[\W_]/', $password)) {
        $message = 'Password must contain at least one special character.';
    } elseif ($password != $confirm_password) {
        $message = 'Passwords do not match.';
    } elseif ($neighbourhood_id === null) {
        $message = 'Please select your neighbourhood.';
    } else {
        $check = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $check->bind_param('s', $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = 'Email already exists.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'resident';

            $sql = 'INSERT INTO users (name, email, password, role, neighbourhood_id) VALUES (?, ?, ?, ?, ?)';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssi', $name, $email, $hashed_password, $role, $neighbourhood_id);

            if ($stmt->execute()) {
                $success = true;
                $message = 'Registration successful! You can now login.';
            } else {
                $message = 'Registration failed. Make sure the database is set up (import database/schema.sql).';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SmartWaste</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<a href="index.php" class="login-page-logo">SmartWaste</a>

<div class="login-container">
    <div class="login-box">
        <a href="index.php" class="back-home">Back to homepage</a>

        <h1>Create Account</h1>
        <p>Register for Smart Waste Collection Scheduler</p>

        <?php if ($message != '') { ?>
            <div class="<?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">

            <label>Email</label>
            <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label>Neighbourhood</label>
            <select name="neighbourhood_id" class="form-select" required>
                <option value="">-- Select neighbourhood --</option>
                <?php
                if ($neighbourhoods) {
                    while ($n = $neighbourhoods->fetch_assoc()) {
                        $sel = (isset($_POST['neighbourhood_id']) && $_POST['neighbourhood_id'] == $n['neighbourhood_id']) ? ' selected' : '';
                        echo '<option value="' . (int) $n['neighbourhood_id'] . '"' . $sel . '>' . htmlspecialchars($n['name']) . '</option>';
                    }
                }
                ?>
            </select>

            <label>Password</label>
            <input type="password" name="password" id="password" required>
            <div class="password-requirements">
                <p>Password must contain:</p>
                <ul>
                    <li>At least 8 characters</li>
                    <li>One uppercase letter (A-Z)</li>
                    <li>One lowercase letter (a-z)</li>
                    <li>One number (0-9)</li>
                    <li>One special character (!@#$%)</li>
                </ul>
            </div>

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" required>

            <button type="submit" name="register">Register</button>
        </form>

        <p class="register-link">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function (e) {
    const pwd = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    let error = '';
    if (pwd.length < 8) error = 'Password must be at least 8 characters long.';
    else if (!/[A-Z]/.test(pwd)) error = 'Password must contain at least one uppercase letter.';
    else if (!/[a-z]/.test(pwd)) error = 'Password must contain at least one lowercase letter.';
    else if (!/[0-9]/.test(pwd)) error = 'Password must contain at least one number.';
    else if (!/[\W_]/.test(pwd)) error = 'Password must contain at least one special character.';
    else if (pwd !== confirm) error = 'Passwords do not match.';
    if (error) { e.preventDefault(); alert(error); }
});
</script>

</body>
</html>
