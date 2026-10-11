<?php
require_once '../includes/resident_init.php';

$list = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC");
$items = [];
if ($list) {
    while ($row = $list->fetch_assoc()) {
        $items[] = $row;
    }
}

mark_notifications_read($conn, $user_id, 0);
refresh_nav_counts($conn, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>Notifications</h1>
    <p class="page-intro">Updates for announcements, campaigns, complaints, pickup requests and collection reminders.</p>

    <?php if (empty($items)) { ?>
        <p class="muted">No notifications yet. New updates will appear here.</p>
    <?php } else { foreach ($items as $n) { ?>
        <article class="info-card <?php echo $n['is_read'] ? '' : 'unread-card'; ?>">
            <span class="badge"><?php echo htmlspecialchars(ucfirst($n['type'])); ?></span>
            <h3><?php echo htmlspecialchars($n['title']); ?></h3>
            <p class="card-date"><?php echo htmlspecialchars($n['created_at']); ?></p>
            <p><?php echo nl2br(htmlspecialchars($n['message'])); ?></p>
            <?php if (!empty($n['link'])) { ?>
                <p><a href="<?php echo htmlspecialchars($n['link']); ?>">Open</a></p>
            <?php } ?>
        </article>
    <?php } } ?>
</main>
<?php include '../includes/popup.php'; ?>
</body>
</html>
