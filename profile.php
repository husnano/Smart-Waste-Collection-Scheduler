<?php
require_once '../includes/resident_init.php';

$user_id = (int) $_SESSION['user_id'];
$message = '';

if (isset($_POST['update_profile'])) {
    $neighbourhood_id = (int) $_POST['neighbourhood_id'];
    $stmt = $conn->prepare('UPDATE users SET neighbourhood_id = ? WHERE user_id = ?');
    $stmt->bind_param('ii', $neighbourhood_id, $user_id);
    if ($stmt->execute()) {
        $message = 'Neighbourhood updated.';
    }
}

$user = $conn->query("SELECT u.*, n.name AS neighbourhood_name FROM users u LEFT JOIN neighbourhoods n ON u.neighbourhood_id = n.neighbourhood_id WHERE u.user_id = $user_id")->fetch_assoc();
$neighbourhoods = $conn->query('SELECT neighbourhood_id, name FROM neighbourhoods ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>My Profile</h1>
    <?php if ($message) { ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php } ?>

    <section class="panel">
        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p><strong>Current neighbourhood:</strong> <?php echo htmlspecialchars($user['neighbourhood_name'] ?? 'Not set'); ?></p>

        <h2>Change neighbourhood</h2>
        <form method="POST" class="form-grid narrow">
            <label>Neighbourhood</label>
            <select name="neighbourhood_id" class="form-select" required>
                <?php while ($n = $neighbourhoods->fetch_assoc()) {
                    $sel = ($n['neighbourhood_id'] == $user['neighbourhood_id']) ? ' selected' : '';
                    echo '<option value="' . (int) $n['neighbourhood_id'] . '"' . $sel . '>' . htmlspecialchars($n['name']) . '</option>';
                } ?>
            </select>
            <button type="submit" name="update_profile">Save</button>
        </form>
    </section>
</main>
</body>
</html>
