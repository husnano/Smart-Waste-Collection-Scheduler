<?php

function ensure_schema($conn) {
    $col = $conn->query("SHOW COLUMNS FROM pickup_requests LIKE 'fee'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE pickup_requests
            ADD COLUMN fee DECIMAL(10,2) NOT NULL DEFAULT 100.00 AFTER description,
            ADD COLUMN payment_status ENUM('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid' AFTER fee");
    }

    $tbl = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($tbl && $tbl->num_rows === 0) {
        $conn->query("CREATE TABLE notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(30) NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT,
            link VARCHAR(255) DEFAULT NULL,
            related_id INT DEFAULT 0,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_type_related (user_id, type, related_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )");
    } else {
        $idx = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'uniq_user_type_related'");
        if ($idx && $idx->num_rows === 0) {
            $conn->query("DELETE n1 FROM notifications n1
                INNER JOIN notifications n2
                ON n1.user_id = n2.user_id AND n1.type = n2.type AND n1.related_id = n2.related_id
                AND n1.notification_id > n2.notification_id");
            $conn->query("ALTER TABLE notifications ADD UNIQUE KEY uniq_user_type_related (user_id, type, related_id)");
        }
    }

    $ctime = $conn->query("SHOW COLUMNS FROM collection_schedules LIKE 'collection_time'");
    if ($ctime && $ctime->num_rows === 0) {
        $conn->query("ALTER TABLE collection_schedules ADD COLUMN collection_time VARCHAR(40) DEFAULT '7:00 AM' AFTER next_collection_date");
    }
    $conn->query("UPDATE collection_schedules SET collection_time = '7:00 AM' WHERE collection_time IS NULL OR collection_time = '' OR collection_time REGEXP '^[0-9]{4}-'");

    $ptime = $conn->query("SHOW COLUMNS FROM pickup_requests LIKE 'pickup_time'");
    if ($ptime && $ptime->num_rows === 0) {
        $conn->query("ALTER TABLE pickup_requests ADD COLUMN pickup_time VARCHAR(40) DEFAULT NULL AFTER request_date");
    }

    $pay = $conn->query("SHOW COLUMNS FROM pickup_requests LIKE 'payment_method'");
    if ($pay && $pay->num_rows === 0) {
        $conn->query("ALTER TABLE pickup_requests
            ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER payment_status,
            ADD COLUMN payment_reference VARCHAR(40) DEFAULT NULL AFTER payment_method,
            ADD COLUMN payment_details VARCHAR(150) DEFAULT NULL AFTER payment_reference");
    }

    $photo = $conn->query("SHOW COLUMNS FROM complaints LIKE 'photo'");
    if ($photo && $photo->num_rows === 0) {
        $conn->query("ALTER TABLE complaints ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER location");
    }

    $img = $conn->query("SHOW COLUMNS FROM announcements LIKE 'image'");
    if ($img && $img->num_rows === 0) {
        $conn->query("ALTER TABLE announcements ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER content");
    }

    $uploadRoot = dirname(__DIR__) . '/uploads';
    foreach (['complaints', 'campaigns'] as $folder) {
        $dir = $uploadRoot . '/' . $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
    }

    refresh_due_schedule_dates($conn);
    purge_removed_announcement_notifications($conn);
}

function save_uploaded_image($file_field, $folder) {
    if (empty($_FILES[$file_field]) || empty($_FILES[$file_field]['name']) || (int) $_FILES[$file_field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }

    $code = (int) $_FILES[$file_field]['error'];
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'Image is too large. Please use a JPG or PNG under 5MB.'];
    }
    if ($code === UPLOAD_ERR_PARTIAL) {
        return ['ok' => false, 'error' => 'Upload was interrupted. Please try again.'];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed. Please try a JPG or PNG file.'];
    }
    if ($_FILES[$file_field]['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image must be 5MB or smaller.'];
    }

    $tmp = $_FILES[$file_field]['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Could not read the uploaded file. Please try again.'];
    }

    $info = @getimagesize($tmp);
    $mime = '';
    if ($info && !empty($info['mime'])) {
        $mime = strtolower($info['mime']);
    } elseif (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) finfo_file($finfo, $tmp));
        finfo_close($finfo);
    }

    if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
        $mime = 'image/jpeg';
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'This picture format is not supported. Please upload a JPG or PNG (not HEIC from iPhone).'];
    }

    $folder = preg_replace('/[^a-z]/', '', $folder);
    $dir = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    @chmod($dir, 0777);
    if (!is_writable($dir)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable. Please check the uploads folder permission.'];
    }

    $name = $folder . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => 'Could not save the image. Please try again.'];
    }
    @chmod($dest, 0644);
    return ['ok' => true, 'path' => 'uploads/' . $folder . '/' . $name];
}

function set_popup($title, $message, $receipt = null) {
    $_SESSION['popup_title'] = $title;
    $_SESSION['popup_message'] = $message;
    $_SESSION['popup_receipt'] = $receipt;
}

function take_popup() {
    if (empty($_SESSION['popup_title'])) {
        return null;
    }
    $popup = [
        'title' => $_SESSION['popup_title'],
        'message' => $_SESSION['popup_message'],
        'receipt' => $_SESSION['popup_receipt'] ?? null,
    ];
    unset($_SESSION['popup_title'], $_SESSION['popup_message'], $_SESSION['popup_receipt']);
    return $popup;
}

function add_notification($conn, $user_id, $type, $title, $message, $link = '', $related_id = 0, $force_unread = false) {
    $user_id = (int) $user_id;
    $related_id = (int) $related_id;
    $find = $conn->prepare('SELECT notification_id, title, message FROM notifications WHERE user_id = ? AND type = ? AND related_id = ? LIMIT 1');
    $find->bind_param('isi', $user_id, $type, $related_id);
    $find->execute();
    $existing = $find->get_result()->fetch_assoc();
    if ($existing) {
        $nid = (int) $existing['notification_id'];
        $changed = ($existing['title'] !== $title || $existing['message'] !== $message);
        if ($force_unread || $changed) {
            $upd = $conn->prepare('UPDATE notifications SET title = ?, message = ?, link = ?, is_read = 0, created_at = CURRENT_TIMESTAMP WHERE notification_id = ?');
            $upd->bind_param('sssi', $title, $message, $link, $nid);
        } else {
            $upd = $conn->prepare('UPDATE notifications SET title = ?, message = ?, link = ? WHERE notification_id = ?');
            $upd->bind_param('sssi', $title, $message, $link, $nid);
        }
        $upd->execute();
        return;
    }
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, type, title, message, link, related_id, is_read)
        VALUES (?, ?, ?, ?, ?, ?, 0)');
    $stmt->bind_param('issssi', $user_id, $type, $title, $message, $link, $related_id);
    $stmt->execute();
}

function delete_announcement_post($conn, $id) {
    $id = (int) $id;
    if ($id < 1) {
        return;
    }
    $row = $conn->query("SELECT image FROM announcements WHERE announcement_id = $id")->fetch_assoc();
    $conn->query("DELETE FROM notifications WHERE related_id = $id AND type IN ('announcement','campaign')");
    $conn->query("DELETE FROM announcements WHERE announcement_id = $id");
    if (!empty($row['image'])) {
        $file = dirname(__DIR__) . '/' . $row['image'];
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function purge_removed_announcement_notifications($conn) {
    $conn->query("DELETE n FROM notifications n
        LEFT JOIN announcements a ON a.announcement_id = n.related_id
        WHERE n.type IN ('announcement','campaign') AND a.announcement_id IS NULL");
}

function notify_all_residents($conn, $type, $title, $message, $link = '', $related_id = 0) {
    $res = $conn->query("SELECT user_id FROM users WHERE role = 'resident'");
    if (!$res) {
        return;
    }
    while ($u = $res->fetch_assoc()) {
        add_notification($conn, (int) $u['user_id'], $type, $title, $message, $link, $related_id, true);
    }
}

function weekday_name($date) {
    return date('l', strtotime($date));
}

function weekday_iso($name) {
    $map = [
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2, 'tues' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6,
        'sunday' => 7, 'sun' => 7,
    ];
    return $map[strtolower(trim($name))] ?? 0;
}

function next_date_for_collection_day($collection_day, $from = null) {
    $from = $from ? new DateTime($from) : new DateTime('today');
    $from->setTime(0, 0, 0);
    $want = weekday_iso($collection_day);
    if ($want < 1) {
        return $from->format('Y-m-d');
    }
    $n = (int) $from->format('N');
    $add = ($want - $n + 7) % 7;
    if ($add > 0) {
        $from->modify('+' . $add . ' days');
    }
    return $from->format('Y-m-d');
}

function refresh_due_schedule_dates($conn) {
    $today = date('Y-m-d');
    $res = $conn->query('SELECT schedule_id, collection_day, next_collection_date FROM collection_schedules');
    if (!$res) {
        return;
    }
    $upd = $conn->prepare('UPDATE collection_schedules SET next_collection_date = ? WHERE schedule_id = ?');
    while ($row = $res->fetch_assoc()) {
        if ($row['next_collection_date'] >= $today) {
            continue;
        }
        $next = next_date_for_collection_day($row['collection_day']);
        $id = (int) $row['schedule_id'];
        $upd->bind_param('si', $next, $id);
        $upd->execute();
    }
}

function collection_time_slots() {
    return ['7:00 AM', '8:00 AM', '9:00 AM', '10:00 AM'];
}

function collection_time_label($value) {
    $value = trim((string) $value);
    return $value !== '' ? $value : '7:00 AM';
}

function pickup_time_slots() {
    return [
        '8:00 AM - 10:00 AM',
        '10:00 AM - 12:00 PM',
        '2:00 PM - 4:00 PM',
        '4:00 PM - 6:00 PM',
    ];
}

function pickup_time_label($value) {
    $value = trim((string) $value);
    return $value !== '' ? $value : '-';
}

function special_pickup_dates($months = 2, $exclude_dates = []) {
    $dates = [];
    $allowed_weekdays = [1, 5];
    $blocked = array_flip($exclude_dates);
    $start = new DateTime('tomorrow');
    $end = new DateTime('last day of +' . ($months - 1) . ' month');
    while ($start <= $end) {
        $date = $start->format('Y-m-d');
        $weekday = (int) $start->format('N');
        if (in_array($weekday, $allowed_weekdays, true) && !isset($blocked[$date])) {
            $dates[] = $date;
        }
        $start->modify('+1 day');
    }
    return $dates;
}

function collection_dates_range($schedules, $months = 2) {
    $dates = [];
    $cursor = new DateTime('first day of this month');
    for ($i = 0; $i < $months; $i++) {
        $year = (int) $cursor->format('Y');
        $month = (int) $cursor->format('n');
        foreach (collection_dates_in_month($schedules, $year, $month) as $date => $type) {
            $dates[] = $date;
        }
        $cursor->modify('first day of next month');
    }
    return $dates;
}

function collection_dates_in_month($schedules, $year, $month) {
    $days = [];
    if (!$schedules) {
        return $days;
    }
    foreach ($schedules as $row) {
        $wanted = strtolower(trim($row['collection_day']));
        $daysInMonth = (int) date('t', strtotime("$year-$month-01"));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            if (strtolower(date('l', strtotime($date))) === $wanted && $date >= date('Y-m-d')) {
                $days[$date] = $row['waste_type'];
            }
        }
        if (!empty($row['next_collection_date'])) {
            $nd = $row['next_collection_date'];
            if (substr($nd, 0, 7) === sprintf('%04d-%02d', $year, $month) && $nd >= date('Y-m-d')) {
                $days[$nd] = $row['waste_type'];
            }
        }
    }
    return $days;
}

function sync_auto_notifications($conn, $user_id) {
    $user_id = (int) $user_id;
    purge_removed_announcement_notifications($conn);
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $user = $conn->query("SELECT neighbourhood_id FROM users WHERE user_id = $user_id")->fetch_assoc();
    $nid = (int) ($user['neighbourhood_id'] ?? 0);

    if ($nid) {
        $sq = $conn->query("SELECT * FROM collection_schedules WHERE neighbourhood_id = $nid AND next_collection_date IN ('$today', '$tomorrow')");
        if ($sq) {
            while ($s = $sq->fetch_assoc()) {
                $when = $s['next_collection_date'] === $today ? 'today' : 'tomorrow';
                $ctime = collection_time_label($s['collection_time'] ?? '');
                add_notification(
                    $conn,
                    $user_id,
                    'schedule',
                    'Collection reminder',
                    $s['waste_type'] . ' collection is ' . $when . ' at ' . $ctime . ' (' . $s['next_collection_date'] . '). Place your bins out by ' . $ctime . '.',
                    'schedules.php',
                    (int) $s['schedule_id']
                );
            }
        }
    }

    $pk = $conn->query("SELECT * FROM pickup_requests WHERE user_id = $user_id AND status IN ('Pending','Approved') AND request_date >= '$today'");
    if ($pk) {
        while ($p = $pk->fetch_assoc()) {
            $when = $p['request_date'] === $today ? 'today' : ('on ' . $p['request_date']);
            $timebit = '';
            if (!empty($p['pickup_time']) && trim($p['pickup_time']) !== '-') {
                $timebit = ' at ' . trim($p['pickup_time']);
            }
            add_notification(
                $conn,
                $user_id,
                'pickup',
                'Pickup request reminder',
                'Your paid pickup (' . $p['waste_type'] . ') is ' . $when . $timebit . '. Fee RM ' . number_format((float) ($p['fee'] ?? 100), 2) . '. Status: ' . $p['status'] . '.',
                'schedules.php#pickup',
                (int) $p['request_id']
            );
        }
    }

    $cp = $conn->query("SELECT * FROM complaints WHERE user_id = $user_id AND status NOT IN ('Resolved','Closed')");
    if ($cp) {
        while ($c = $cp->fetch_assoc()) {
            add_notification(
                $conn,
                $user_id,
                'complaint',
                'Complaint update reminder',
                'Complaint #' . $c['complaint_id'] . ' (' . $c['subject'] . ') is ' . $c['status'] . '.',
                'complaints.php',
                (int) $c['complaint_id']
            );
        }
    }

    $an = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 20");
    if ($an) {
        while ($a = $an->fetch_assoc()) {
            $type = $a['type'] === 'Campaign' ? 'campaign' : 'announcement';
            add_notification(
                $conn,
                $user_id,
                $type,
                $a['type'] . ': ' . $a['title'],
                $a['content'],
                'dashboard.php',
                (int) $a['announcement_id']
            );
        }
    }
}

function unread_count($conn, $user_id, $types = null) {
    $user_id = (int) $user_id;
    $sql = "SELECT COUNT(*) AS c FROM notifications WHERE user_id = $user_id AND is_read = 0";
    if ($types) {
        $safe = array_map(function ($t) use ($conn) {
            return "'" . $conn->real_escape_string($t) . "'";
        }, $types);
        $sql .= ' AND type IN (' . implode(',', $safe) . ')';
    }
    $row = $conn->query($sql)->fetch_assoc();
    return (int) $row['c'];
}

function get_live_reminders($conn, $user_id) {
    $user_id = (int) $user_id;
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $items = [];

    $user = $conn->query("SELECT neighbourhood_id FROM users WHERE user_id = $user_id")->fetch_assoc();
    $nid = (int) ($user['neighbourhood_id'] ?? 0);
    if ($nid) {
        $sq = $conn->query("SELECT * FROM collection_schedules WHERE neighbourhood_id = $nid AND next_collection_date IN ('$today', '$tomorrow')");
        if ($sq) {
            while ($s = $sq->fetch_assoc()) {
                $when = $s['next_collection_date'] === $today ? 'today' : 'tomorrow';
                $ctime = collection_time_label($s['collection_time'] ?? '');
                $items[] = [
                    'group' => 'collection',
                    'label' => 'Collection',
                    'title' => $s['waste_type'],
                    'date' => $s['next_collection_date'],
                    'time' => $ctime,
                    'message' => 'Collection is ' . $when . '. Place bins out by ' . $ctime . '.',
                    'meta' => '',
                    'link' => 'schedules.php',
                ];
            }
        }
    }

    $pk = $conn->query("SELECT * FROM pickup_requests WHERE user_id = $user_id AND status IN ('Pending','Approved') AND request_date >= '$today'");
    if ($pk) {
        while ($p = $pk->fetch_assoc()) {
            $rawTime = trim((string) ($p['pickup_time'] ?? ''));
            if ($rawTime === '' || $rawTime === '-') {
                $rawTime = '';
            }
            $items[] = [
                'group' => 'pickup',
                'label' => 'Pickup',
                'title' => $p['waste_type'],
                'date' => $p['request_date'],
                'time' => $rawTime,
                'message' => ($rawTime !== '' ? 'Time slot: ' . $rawTime . '. ' : '') . 'Fee RM ' . number_format((float) ($p['fee'] ?? 100), 2) . '. Status: ' . $p['status'] . '.',
                'meta' => '',
                'link' => 'schedules.php#pickup',
            ];
        }
    }

    $cp = $conn->query("SELECT * FROM complaints WHERE user_id = $user_id AND status NOT IN ('Resolved','Closed')");
    if ($cp) {
        while ($c = $cp->fetch_assoc()) {
            $items[] = [
                'group' => 'complaint',
                'label' => 'Complaint',
                'title' => '#' . $c['complaint_id'] . ' ' . $c['subject'],
                'message' => 'Status: ' . $c['status'] . '. We will update you when action is taken.',
                'meta' => $c['status'],
                'link' => 'complaints.php',
            ];
        }
    }

    return $items;
}

function nav_dots($conn, $user_id) {
    $schedule = 0;
    $complaint = 0;
    foreach (get_live_reminders($conn, $user_id) as $item) {
        if (($item['group'] ?? '') === 'complaint') {
            $complaint++;
        } else {
            $schedule++;
        }
    }
    return [
        'schedule' => $schedule,
        'complaint' => $complaint,
        'alerts' => unread_count($conn, $user_id),
    ];
}

function mark_notifications_read($conn, $user_id, $id = 0) {
    $user_id = (int) $user_id;
    $id = (int) $id;
    if ($id > 0) {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $id AND user_id = $user_id");
    } else {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    }
}

function mark_notifications_read_by_types($conn, $user_id, $types) {
    $user_id = (int) $user_id;
    if (empty($types)) {
        return;
    }
    $safe = array_map(function ($t) use ($conn) {
        return "'" . $conn->real_escape_string($t) . "'";
    }, $types);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND type IN (" . implode(',', $safe) . ")");
}

function refresh_nav_counts($conn, $user_id) {
    global $nav_unread_all, $nav_unread_schedule, $nav_unread_complaint;
    $nav_unread_all = unread_count($conn, $user_id);
    $dots = nav_dots($conn, $user_id);
    $nav_unread_schedule = (int) $dots['schedule'];
    $nav_unread_complaint = (int) $dots['complaint'];
}
