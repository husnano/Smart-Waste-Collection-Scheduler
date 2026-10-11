<?php
require_once '../includes/resident_init.php';

$stmt = $conn->prepare('SELECT u.name, u.neighbourhood_id, n.name AS neighbourhood_name FROM users u LEFT JOIN neighbourhoods n ON u.neighbourhood_id = n.neighbourhood_id WHERE u.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$schedules = null;
if (!empty($user['neighbourhood_id'])) {
    $nid = (int) $user['neighbourhood_id'];
    $sq = $conn->prepare('SELECT * FROM collection_schedules WHERE neighbourhood_id = ? ORDER BY next_collection_date LIMIT 8');
    $sq->bind_param('i', $nid);
    $sq->execute();
    $schedules = $sq->get_result();
}

$pending_pickups = $conn->query("SELECT COUNT(*) AS c FROM pickup_requests WHERE user_id = $user_id AND status IN ('Pending','Approved')")->fetch_assoc()['c'];
$open_complaints = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE user_id = $user_id AND status NOT IN ('Resolved','Closed')")->fetch_assoc()['c'];

$latest_announcements = $conn->query("SELECT * FROM announcements WHERE type = 'Announcement' ORDER BY created_at DESC LIMIT 10");
$latest_campaigns = $conn->query("SELECT * FROM announcements WHERE type = 'Campaign' ORDER BY created_at DESC LIMIT 10");
$reminder_scope = 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>Hello, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
    <p class="page-intro">Manage your waste collection schedule and community updates.</p>

    <?php if (empty($user['neighbourhood_id'])) { ?>
        <div class="alert alert-warning">
            Please <a href="profile.php">select your neighbourhood</a> to view collection schedules.
        </div>
    <?php } else { ?>
        <p><strong>Neighbourhood:</strong> <?php echo htmlspecialchars($user['neighbourhood_name']); ?></p>
    <?php } ?>

    <?php include 'reminder_banner.php'; ?>

    <div class="stat-row">
        <div class="stat-card">
            <span class="stat-num"><?php echo (int) $pending_pickups; ?></span>
            <span class="stat-label">Active pickup requests</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?php echo (int) $open_complaints; ?></span>
            <span class="stat-label">Open complaints</span>
        </div>
        <div class="stat-card">
            <span class="stat-num"><?php echo (int) $nav_unread_all; ?></span>
            <span class="stat-label">Notifications</span>
        </div>
    </div>

    <section class="panel">
        <h2>Upcoming collections</h2>
        <?php if (empty($user['neighbourhood_id'])) { ?>
            <p class="muted">Set your neighbourhood to see schedules.</p>
        <?php } elseif (!$schedules || $schedules->num_rows === 0) { ?>
            <p class="muted">No upcoming schedules listed for your area.</p>
        <?php } else { ?>
            <table class="data-table">
                <tr>
                    <th>Type</th>
                    <th>Day</th>
                    <th>Next date</th>
                    <th>Time</th>
                    <th>Notes</th>
                </tr>
                <?php while ($row = $schedules->fetch_assoc()) {
                    $showDate = $row['next_collection_date'];
                    if ($showDate < date('Y-m-d')) {
                        $showDate = next_date_for_collection_day($row['collection_day']);
                    }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['waste_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['collection_day']); ?></td>
                    <td><?php echo htmlspecialchars($showDate); ?></td>
                    <td><?php echo htmlspecialchars(collection_time_label($row['collection_time'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                </tr>
                <?php } ?>
            </table>
            <p class="dash-schedule-action">
                <button type="button" class="btn-box" onclick="window.location.href='schedules.php'">View full</button>
            </p>
        <?php } ?>
    </section>

    <section class="panel" id="announcements">
        <h2>Announcements</h2>
        <?php if (!$latest_announcements || $latest_announcements->num_rows === 0) { ?>
            <p class="muted">No announcements yet.</p>
        <?php } else { while ($a = $latest_announcements->fetch_assoc()) { ?>
            <article class="info-card compact">
                <h3><?php echo htmlspecialchars($a['title']); ?></h3>
                <p class="card-date"><?php echo htmlspecialchars($a['created_at']); ?></p>
                <p><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
            </article>
        <?php } } ?>
    </section>

    <section class="panel" id="campaigns">
        <h2>Recycling campaigns</h2>
        <?php if (!$latest_campaigns || $latest_campaigns->num_rows === 0) { ?>
            <p class="muted">No campaigns yet.</p>
        <?php } else { while ($a = $latest_campaigns->fetch_assoc()) { ?>
            <article class="info-card compact">
                <span class="badge badge-campaign">Campaign</span>
                <h3><?php echo htmlspecialchars($a['title']); ?></h3>
                <p class="card-date"><?php echo htmlspecialchars($a['created_at']); ?></p>
                <?php if (!empty($a['image'])) { ?>
                    <img src="../<?php echo htmlspecialchars($a['image']); ?>" alt="" class="poster-thumb">
                <?php } ?>
                <p><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
            </article>
        <?php } } ?>
    </section>
</main>
<?php include '../includes/popup.php'; ?>
</body>
</html>
