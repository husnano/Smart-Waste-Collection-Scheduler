<?php
require_once '../includes/resident_init.php';

$error = '';

if (isset($_POST['submit_complaint'])) {
    $subject     = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $location    = trim($_POST['location']);
    $upload      = save_uploaded_image('photo', 'complaints');

    if (!$upload['ok']) {
        $error = $upload['error'];
    } else {
        $photo = $upload['path'];
        $stmt = $conn->prepare('INSERT INTO complaints (user_id, subject, description, location, photo) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $user_id, $subject, $description, $location, $photo);
        if ($stmt->execute()) {
            $cid = (int) $conn->insert_id;
            add_notification(
                $conn,
                $user_id,
                'complaint',
                'Complaint submitted',
                'Your complaint #' . $cid . ' (' . $subject . ') was submitted. Status: Submitted.',
                'complaints.php',
                $cid,
                true
            );
            set_popup(
                'Complaint submitted',
                'Your complaint has been successfully submitted to the administrator. Please wait patiently for further updates'
            );
            header('Location: complaints.php');
            exit();
        }
        $error = 'Could not submit the complaint.';
    }
}

$list = $conn->query("SELECT * FROM complaints WHERE user_id = $user_id ORDER BY created_at DESC");
$reminder_scope = 'complaint';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>Complaint Reporting</h1>
    <p class="page-intro">Report missed collection, illegal dumping, or other waste-related issues.</p>
    <?php include 'reminder_banner.php'; ?>

    <?php if ($error) { ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php } ?>

    <section class="panel">
        <h2>Submit complaint</h2>
        <form method="POST" class="form-grid" enctype="multipart/form-data">
            <label>Subject</label>
            <input type="text" name="subject" required maxlength="150" placeholder="e.g. Missed collection on Tuesday">

            <label>Location</label>
            <input type="text" name="location" placeholder="Street / area (optional)">

            <label>Details</label>
            <textarea name="description" rows="5" required placeholder="Describe the issue clearly"></textarea>

            <label>Picture (optional)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
            <p class="muted">JPG or PNG works best. Maximum 5MB. iPhone: choose JPEG, not HEIC.</p>

            <button type="submit" name="submit_complaint">Submit complaint</button>
        </form>
    </section>

    <section class="panel">
        <h2>Track my complaints</h2>
        <?php if ($list->num_rows === 0) { ?>
            <p class="muted">No complaints submitted.</p>
        <?php } else { ?>
            <table class="data-table">
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Picture</th>
                    <th>Status</th>
                    <th>Action taken</th>
                    <th>Updated</th>
                </tr>
                <?php while ($c = $list->fetch_assoc()) { ?>
                <tr>
                    <td>#<?php echo (int) $c['complaint_id']; ?></td>
                    <td><?php echo htmlspecialchars($c['subject']); ?></td>
                    <td>
                        <?php if (!empty($c['photo'])) { ?>
                            <a href="../<?php echo htmlspecialchars($c['photo']); ?>" target="_blank">View picture</a>
                        <?php } else { echo '-'; } ?>
                    </td>
                    <td><span class="badge"><?php echo htmlspecialchars($c['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($c['action_taken'] ?? 'Pending review'); ?></td>
                    <td><?php echo htmlspecialchars($c['updated_at']); ?></td>
                </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </section>
</main>
<?php include '../includes/popup.php'; ?>
</body>
</html>
