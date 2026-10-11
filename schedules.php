<?php
require_once '../includes/resident_init.php';

$message = '';
$error = '';
$pickup_fee = 100.00;

$user = $conn->query("SELECT neighbourhood_id FROM users WHERE user_id = $user_id")->fetch_assoc();
$nid = (int) ($user['neighbourhood_id'] ?? 0);

$schedules = null;
$neighbourhood_name = '';
$schedule_rows = [];
if ($nid) {
    $schedules = $conn->query("SELECT cs.*, n.name AS neighbourhood_name FROM collection_schedules cs JOIN neighbourhoods n ON cs.neighbourhood_id = n.neighbourhood_id WHERE cs.neighbourhood_id = $nid ORDER BY next_collection_date");
    if ($schedules && $schedules->num_rows) {
        while ($row = $schedules->fetch_assoc()) {
            $neighbourhood_name = $row['neighbourhood_name'];
            $schedule_rows[] = $row;
        }
    }
}

$available = special_pickup_dates(2, collection_dates_range($schedule_rows, 2));

if (isset($_POST['submit_request'])) {
    $request_date = $_POST['request_date'] ?? '';
    $waste_type   = trim($_POST['waste_type'] ?? '');
    $pickup_time  = trim($_POST['pickup_time'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $pay_method   = $_POST['payment_method'] ?? '';
    $payer_name   = trim($_POST['payer_name'] ?? '');

    $methods = ['FPX' => 'Online Banking (FPX)', 'Card' => 'Debit / Credit Card', 'E-Wallet' => 'E-Wallet'];
    $details = '';

    if (!in_array($request_date, $available, true)) {
        $error = 'Please choose an available special pickup date from the calendar.';
    } elseif ($waste_type === '') {
        $error = 'Please choose a waste type.';
    } elseif (!in_array($pickup_time, pickup_time_slots(), true)) {
        $error = 'Please choose a pickup time.';
    } elseif (!isset($methods[$pay_method])) {
        $error = 'Please choose a payment method.';
    } elseif ($payer_name === '') {
        $error = 'Please enter the name on the payment account.';
    } else {
        if ($pay_method === 'FPX') {
            $bank = trim($_POST['bank_name'] ?? '');
            if ($bank === '') {
                $error = 'Please select your bank.';
            } else {
                $details = $bank;
            }
        } elseif ($pay_method === 'Card') {
            $card = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
            $expiry = trim($_POST['card_expiry'] ?? '');
            $cvv = trim($_POST['card_cvv'] ?? '');
            if (!preg_match('/^[0-9]{16}$/', $card) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry) || !preg_match('/^[0-9]{3,4}$/', $cvv)) {
                $error = 'Please enter a valid 16-digit card number, expiry (MM/YY) and CVV.';
            } else {
                $details = 'Card ending ' . substr($card, -4);
            }
        } else {
            $wallet = trim($_POST['ewallet'] ?? '');
            if ($wallet === '') {
                $error = 'Please select an e-wallet.';
            } else {
                $details = $wallet;
            }
        }
    }

    if ($error === '') {
        $paid = 'Paid';
        $reference = 'SWP' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $stmt = $conn->prepare('INSERT INTO pickup_requests (user_id, request_date, pickup_time, waste_type, description, fee, payment_status, payment_method, payment_reference, payment_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssdssss', $user_id, $request_date, $pickup_time, $waste_type, $description, $pickup_fee, $paid, $pay_method, $reference, $details);
        if ($stmt->execute()) {
            $rid = (int) $conn->insert_id;
            add_notification(
                $conn,
                $user_id,
                'pickup',
                'Pickup booked',
                'Pickup (' . $waste_type . ') on ' . $request_date . ' at ' . $pickup_time . '. Paid RM ' . number_format($pickup_fee, 2) . ' via ' . $pay_method . '. Ref: ' . $reference . '.',
                'schedules.php#pickup',
                $rid,
                true
            );
            set_popup(
                'Payment successful',
                'Your pickup request has been booked and payment was received.',
                [
                    'Reference' => $reference,
                    'Date' => $request_date,
                    'Time' => $pickup_time,
                    'Waste type' => $waste_type,
                    'Amount' => 'RM ' . number_format($pickup_fee, 2),
                    'Method' => $methods[$pay_method] . ' - ' . $details,
                    'Payer' => $payer_name,
                    'Status' => 'Paid',
                ]
            );
            header('Location: schedules.php#pickup');
            exit();
        } else {
            $error = 'Payment could not be completed. Please try again.';
        }
    }
}

$month_param = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) {
    $month_param = date('Y-m');
}
$month_ts = strtotime($month_param . '-01');
$year = (int) date('Y', $month_ts);
$month = (int) date('m', $month_ts);
$prev_month = date('Y-m', strtotime($month_param . '-01 -1 month'));
$next_month = date('Y-m', strtotime($month_param . '-01 +1 month'));
$days_in_month = (int) date('t', $month_ts);
$first_w = (int) date('N', $month_ts);

$collection_map = collection_dates_in_month($schedule_rows, $year, $month);
$selected_date = isset($_POST['request_date']) ? $_POST['request_date'] : (isset($_GET['date']) ? $_GET['date'] : '');
$posted_time = $_POST['pickup_time'] ?? '';
$posted_method = $_POST['payment_method'] ?? '';

$list = $conn->query("SELECT * FROM pickup_requests WHERE user_id = $user_id ORDER BY created_at DESC");
$reminder_scope = 'schedule';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules - SmartWaste</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<main class="page-main">
    <h1>Waste Collection Schedule</h1>
    <?php include 'reminder_banner.php'; ?>

    <?php if (!$nid) { ?>
        <div class="alert alert-warning">Select your neighbourhood in <a href="profile.php">Profile</a> first.</div>
    <?php } else { ?>
        <p class="page-intro">Regular collection for <strong><?php echo htmlspecialchars($neighbourhood_name); ?></strong></p>
        <section class="panel">
            <h2>Regular schedule</h2>
            <table class="data-table">
                <tr>
                    <th>Waste type</th>
                    <th>Collection day</th>
                    <th>Next collection</th>
                    <th>Time</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($schedule_rows as $row) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['waste_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['collection_day']); ?></td>
                    <td><?php echo htmlspecialchars($row['next_collection_date']); ?></td>
                    <td><?php echo htmlspecialchars(collection_time_label($row['collection_time'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                </tr>
                <?php } ?>
            </table>
        </section>
    <?php } ?>

    <section class="panel" id="pickup">
        <h2>Pickup request</h2>
        <p class="page-intro">Choose a green date on the calendar. Pickup request are available on Monday and Friday. Fee RM100.</p>

        <?php if ($message) { ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php } ?>
        <?php if ($error) { ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php } ?>

        <div class="cal-legend">
            <span><i class="cal-swatch swatch-collection"></i> Regular collection</span>
            <span><i class="cal-swatch swatch-available"></i> Available for paid pickup</span>
            <span><i class="cal-swatch swatch-selected"></i> Selected date</span>
        </div>

        <div class="cal-nav">
            <a href="schedules.php?month=<?php echo $prev_month; ?>#pickup">&larr; Previous</a>
            <strong><?php echo date('F Y', $month_ts); ?></strong>
            <a href="schedules.php?month=<?php echo $next_month; ?>#pickup">Next &rarr;</a>
        </div>

        <table class="cal-table">
            <tr>
                <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
            </tr>
            <tr>
            <?php
            $pad_start = $first_w - 1;
            $total_cells = $pad_start + $days_in_month;
            $pad_end = (7 - ($total_cells % 7)) % 7;
            $cell = 0;
            for ($i = 0; $i < $pad_start; $i++, $cell++) {
                echo '<td class="cal-empty"></td>';
            }
            for ($d = 1; $d <= $days_in_month; $d++, $cell++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $classes = ['cal-day'];
                $label = (string) $d;
                $href = '';
                if (isset($collection_map[$date])) {
                    $classes[] = 'cal-collection';
                    $label .= '<span class="cal-tag">' . htmlspecialchars($collection_map[$date]) . '</span>';
                }
                if (in_array($date, $available, true)) {
                    $classes[] = 'cal-available';
                    $href = 'schedules.php?month=' . $month_param . '&date=' . $date . '#pickup';
                }
                if ($selected_date === $date) {
                    $classes[] = 'cal-selected';
                }
                echo '<td class="' . implode(' ', $classes) . '">';
                if ($href) {
                    echo '<a href="' . $href . '">' . $label . '</a>';
                } else {
                    echo $label;
                }
                echo '</td>';
                if (($cell + 1) % 7 === 0 && $d < $days_in_month) {
                    echo '</tr><tr>';
                }
            }
            for ($i = 0; $i < $pad_end; $i++) {
                echo '<td class="cal-empty"></td>';
            }
            ?>
            </tr>
        </table>

        <form method="POST" class="form-grid pickup-form" action="schedules.php?month=<?php echo htmlspecialchars($month_param); ?>#pickup">
            <label>Available pickup date</label>
            <input type="text" name="request_date" value="<?php echo htmlspecialchars($selected_date); ?>" required readonly placeholder="Click a green date on the calendar">

            <label>Waste type</label>
            <select name="waste_type" class="form-select" required>
                <option value="">-- Select --</option>
                <option value="Bulky waste">Bulky waste</option>
                <option value="Garden waste">Garden waste</option>
                <option value="E-waste">E-waste</option>
                <option value="Other">Other</option>
            </select>

            <label>Pickup time</label>
            <select name="pickup_time" class="form-select" required>
                <option value="">-- Select time --</option>
                <?php foreach (pickup_time_slots() as $slot) {
                    $sel = ($posted_time === $slot) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars($slot) . '"' . $sel . '>' . htmlspecialchars($slot) . '</option>';
                } ?>
            </select>

            <label>Description (optional)</label>
            <textarea name="description" rows="4" placeholder="Describe items and location (e.g. front gate)"></textarea>

            <p class="fee-note">Amount due: <strong>RM <?php echo number_format($pickup_fee, 2); ?></strong></p>

            <div class="pay-box">
                <h3>Payment</h3>
                <label>Account / cardholder name</label>
                <input type="text" name="payer_name" required value="<?php echo htmlspecialchars($_POST['payer_name'] ?? $_SESSION['name']); ?>">

                <label>Payment method</label>
                <select name="payment_method" id="payment_method" class="form-select" required>
                    <option value="">-- Select method --</option>
                    <option value="FPX" <?php echo $posted_method === 'FPX' ? 'selected' : ''; ?>>Online Banking (FPX)</option>
                    <option value="Card" <?php echo $posted_method === 'Card' ? 'selected' : ''; ?>>Debit / Credit Card</option>
                    <option value="E-Wallet" <?php echo $posted_method === 'E-Wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                </select>

                <div class="pay-fields" id="fields-fpx" hidden>
                    <label>Bank</label>
                    <select name="bank_name" class="form-select">
                        <option value="">-- Select bank --</option>
                        <option>Maybank</option>
                        <option>CIMB Bank</option>
                        <option>Public Bank</option>
                        <option>RHB Bank</option>
                        <option>Bank Islam</option>
                        <option>Hong Leong Bank</option>
                    </select>
                </div>

                <div class="pay-fields" id="fields-card" hidden>
                    <label>Card number</label>
                    <input type="text" name="card_number" maxlength="19" placeholder="16-digit card number" autocomplete="off">
                    <div class="pay-row">
                        <div>
                            <label>Expiry (MM/YY)</label>
                            <input type="text" name="card_expiry" maxlength="5" placeholder="MM/YY">
                        </div>
                        <div>
                            <label>CVV</label>
                            <input type="password" name="card_cvv" maxlength="4" placeholder="123" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="pay-fields" id="fields-ewallet" hidden>
                    <label>E-Wallet</label>
                    <select name="ewallet" class="form-select">
                        <option value="">-- Select e-wallet --</option>
                        <option>Touch n Go eWallet</option>
                        <option>GrabPay</option>
                        <option>ShopeePay</option>
                        <option>Boost</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="submit_request">Pay RM 100.00 and book pickup</button>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('payment_method');
            var fpx = document.getElementById('fields-fpx');
            var card = document.getElementById('fields-card');
            var wallet = document.getElementById('fields-ewallet');
            function toggle() {
                var v = sel.value;
                fpx.hidden = v !== 'FPX';
                card.hidden = v !== 'Card';
                wallet.hidden = v !== 'E-Wallet';
            }
            sel.addEventListener('change', toggle);
            toggle();
        })();
        </script>
    </section>

    <section class="panel">
        <h2>My pickup requests</h2>
        <?php if (!$list || $list->num_rows === 0) { ?>
            <p class="muted">No requests yet.</p>
        <?php } else { ?>
            <table class="data-table">
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Fee</th>
                    <th>Payment</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Admin note</th>
                </tr>
                <?php while ($r = $list->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['request_date']); ?></td>
                    <td><?php echo htmlspecialchars(pickup_time_label($r['pickup_time'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($r['waste_type']); ?></td>
                    <td>RM <?php echo number_format((float) ($r['fee'] ?? 100), 2); ?></td>
                    <td><?php echo htmlspecialchars(($r['payment_status'] ?? '-') . (empty($r['payment_method']) ? '' : ' · ' . $r['payment_method'])); ?></td>
                    <td><?php echo htmlspecialchars($r['payment_reference'] ?? '-'); ?></td>
                    <td><span class="badge"><?php echo htmlspecialchars($r['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($r['admin_note'] ?? '-'); ?></td>
                </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </section>
</main>
<?php include '../includes/popup.php'; ?>
</body>
</html>
