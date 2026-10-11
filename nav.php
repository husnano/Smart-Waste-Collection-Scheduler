<?php
if (!isset($nav_unread_all)) {
    $nav_unread_all = 0;
    $nav_unread_schedule = 0;
    $nav_unread_complaint = 0;
}
$badge = (int) $nav_unread_all;
$badge_text = $badge > 9 ? '9+' : (string) $badge;
?>
<nav class="navbar dashboard-nav">
    <div class="logo"><a href="dashboard.php">SmartWaste</a></div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="schedules.php" class="nav-with-dot">
            Schedules
            <?php if ($nav_unread_schedule > 0) { ?><span class="nav-dot"></span><?php } ?>
        </a>
        <a href="complaints.php" class="nav-with-dot">
            Complaints
            <?php if ($nav_unread_complaint > 0) { ?><span class="nav-dot"></span><?php } ?>
        </a>
        <a href="profile.php">Profile</a>
        <a href="notifications.php" class="nav-bell-link" title="Notifications" aria-label="<?php echo $badge > 0 ? 'Notifications, ' . $badge . ' unread' : 'Notifications'; ?>">
            <svg class="bell-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M15 17h5l-1.4-1.6A2 2 0 0 1 18 14V10a6 6 0 1 0-12 0v4c0 .5-.2 1.1-.6 1.4L4 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9.2 17a2.8 2.8 0 0 0 5.6 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <?php if ($badge > 0) { ?>
                <span class="nav-badge" aria-hidden="true"><?php echo htmlspecialchars($badge_text); ?></span>
            <?php } ?>
        </a>
    </div>
    <div class="auth-buttons">
        <a href="../logout.php" class="register-btn">Logout</a>
    </div>
</nav>
<script>
(function () {
    var unread = <?php echo (int) $badge; ?>;
    if (navigator.setAppBadge) {
        if (unread > 0) {
            navigator.setAppBadge(unread);
        } else if (navigator.clearAppBadge) {
            navigator.clearAppBadge();
        }
    }
})();
</script>
