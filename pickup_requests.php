<?php
require_once '../includes/auth.php';
require_admin();
include '../config/database.php';
require_once '../includes/helpers.php';
ensure_schema($conn);

$message = '';

if (isset($_POST['update_request'])) {
    $request_id = (int) $_POST['request_id'];
    $status = $_POST['status'];
    $admin_note = trim($_POST['admin_note']);
    $stmt = $conn->prepare('UPDATE pickup_requests SET status = ?, admin_note = ? WHERE request_id = ?');
    $stmt->bind_param('ssi', $status, $admin_note, $request_id);
    if ($stmt->execute()) {
        $owner = $conn->query("SELECT user_id, waste_type, request_date, pickup_time FROM pickup_requests WHERE request_id = $request_id")->fetch_assoc();
        if ($owner) {
            $when = $owner['request_date'] . (!empty($owner['pickup_time']) ? ' at ' . $owner['pickup_time'] : '');
            add_notification(
                $conn,
                (int) $owner['user_id'],
                'pickup',
                'Pickup request updated',
                'Your pickup (' . $owner['waste_type'] . ') on ' . $when . ' is now ' . $status . '.',
                'schedules.php#pickup',
                $request_id,
                true
            );
        }
        $message = 'Request updated.';
    }
}

if (isset($_GET['delete'])) {
    $request_id = (int) $_GET['delete'];
    $conn->query("DELETE FROM notifications WHERE type = 'pickup' AND related_id = $request_id");
    $stmt = $conn->prepare('DELETE FROM pickup_requests WHERE request_id = ?');
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    header('Location: pickup_requests.php');
    exit();
}

$list = $conn->query('SELECT pr.*, u.name, u.email FROM pickup_requests pr JOIN users u ON pr.user_id = u.user_id ORDER BY pr.created_at DESC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Requests - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>Pickup Requests</h1>
    <?php if ($message) { ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php } ?>

    <?php if (!$list || $list->num_rows === 0) { ?>
        <p class="muted">No pickup requests yet.</p>
    <?php } else { while ($r = $list->fetch_assoc()) {
        $st = strtolower($r['status']);
        $desc = ($r['description'] !== '' && $r['description'] !== null) ? $r['description'] : '-';
        ?>
        <section class="panel pickup-card">
            <div class="pickup-card-head">
                <h3><?php echo htmlspecialchars($r['waste_type']); ?></h3>
                <span class="badge badge-status badge-<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($r['status']); ?></span>
            </div>
            <p><strong>From:</strong> <?php echo htmlspecialchars($r['name']); ?> (<?php echo htmlspecialchars($r['email']); ?>)</p>
            <div class="pickup-card-meta">
                <span><strong>Date:</strong> <?php echo htmlspecialchars($r['request_date']); ?></span>
                <span><strong>Time:</strong> <?php echo htmlspecialchars(pickup_time_label($r['pickup_time'] ?? '')); ?></span>
                <span><strong>Fee:</strong> RM <?php echo number_format((float) ($r['fee'] ?? 100), 2); ?></span>
                <span><strong>Payment:</strong> <?php echo htmlspecialchars($r['payment_status'] ?? '-'); ?><?php
                    if (!empty($r['payment_method'])) {
                        echo ' · ' . htmlspecialchars($r['payment_method']);
                    }
                    if (!empty($r['payment_reference'])) {
                        echo ' · ' . htmlspecialchars($r['payment_reference']);
                    }
                ?></span>
            </div>
            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($desc)); ?></p>
            <?php if (!empty($r['payment_details'])) { ?>
                <p class="muted"><?php echo htmlspecialchars($r['payment_details']); ?></p>
            <?php } ?>
            <p class="muted">Submitted: <?php echo htmlspecialchars($r['created_at']); ?></p>

            <form method="POST" class="form-grid narrow">
                <input type="hidden" name="request_id" value="<?php echo (int) $r['request_id']; ?>">
                <label>Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['Pending','Approved','Completed','Rejected'] as $opt) {
                        $sel = $r['status'] === $opt ? ' selected' : '';
                        echo '<option' . $sel . '>' . $opt . '</option>';
                    } ?>
                </select>
                <label>Note to resident</label>
                <input type="text" name="admin_note" value="<?php echo htmlspecialchars($r['admin_note'] ?? ''); ?>" placeholder="Optional note">
                <div class="pickup-card-actions">
                    <button type="submit" name="update_request">Save</button>
                    <button type="button" class="btn-delete" onclick="if (confirm('Delete this pickup request? It will be removed from admin and resident records.')) { window.location.href='pickup_requests.php?delete=<?php echo (int) $r['request_id']; ?>'; }">Delete</button>
                </div>
            </form>
        </section>
    <?php } } ?>
</main>
</body>
</html>
