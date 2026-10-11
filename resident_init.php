<?php
require_once __DIR__ . '/auth.php';
require_resident();
include __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

ensure_schema($conn);

$user_id = (int) $_SESSION['user_id'];
sync_auto_notifications($conn, $user_id);

$nav_unread_all = unread_count($conn, $user_id);
$dots = nav_dots($conn, $user_id);
$nav_unread_schedule = (int) $dots['schedule'];
$nav_unread_complaint = (int) $dots['complaint'];
$active_reminders = get_live_reminders($conn, $user_id);
