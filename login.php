<?php
session_start();
include "config/database.php";

$error = "";

if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: resident/dashboard.php");
            }

            exit();

        } else {
            $error = "Incorrect password.";
        }

    } else {
        $error = "Email not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartWaste</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<a href="index.php" class="login-page-logo">SmartWaste</a>

<div class="login-container">

    <div class="login-box">

        <a href="index.php" class="back-home">Back to homepage</a>

        <h1>Welcome Back</h1>

        <p>Login to Smart Waste Collection Scheduler</p>

        <?php if ($error != "") { ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
        <?php } ?>

        <form method="POST">

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit" name="login">
                Login
            </button>

        </form>

        <p class="register-link">
            Don't have an account?
            <a href="register.php">Register</a>
        </p>

    </div>

</div>

</body>
</html>