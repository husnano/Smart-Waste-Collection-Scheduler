<?php
/**
 * Run once: http://localhost/SmartWasteCollectionScheduler/setup/create_admin.php
 * Creates/updates admin: admin@smartwaste.com / Admin@123
 */
require __DIR__ . '/../config/database.php';

$email = 'admin@smartwaste.com';
$password = password_hash('Admin@123', PASSWORD_DEFAULT);
$name = 'System Administrator';

$check = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $stmt = $conn->prepare('UPDATE users SET password = ?, role = ? WHERE email = ?');
    $role = 'admin';
    $stmt->bind_param('sss', $password, $role, $email);
    $stmt->execute();
    echo 'Admin password updated. Login: admin@smartwaste.com / Admin@123';
} else {
    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    $role = 'admin';
    $stmt->bind_param('ssss', $name, $email, $password, $role);
    $stmt->execute();
    echo 'Admin created. Login: admin@smartwaste.com / Admin@123';
}
