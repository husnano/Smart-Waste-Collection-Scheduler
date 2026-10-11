<?php
$scope = isset($reminder_scope) ? $reminder_scope : 'all';
$shown = [];
foreach ($active_reminders as $r) {
    if ($scope === 'schedule' && !in_array($r['group'], ['collection', 'pickup'], true)) {
        continue;
    }
    if ($scope === 'complaint' && $r['group'] !== 'complaint') {
        continue;
    }
    $shown[] = $r;
}
if (!empty($shown)) {
    ?>
<section class="panel reminders-board">
    <h2>Reminders</h2>
    <div class="reminder-box-grid">
        <?php foreach ($shown as $r) { ?>
        <article class="reminder-box reminder-<?php echo htmlspecialchars($r['group']); ?>">
            <span class="reminder-box-type"><?php echo htmlspecialchars($r['label']); ?></span>
            <h3><?php echo htmlspecialchars($r['title']); ?></h3>
            <?php if (!empty($r['date']) || !empty($r['time'])) { ?>
                <p class="reminder-when">
                    <?php if (!empty($r['date'])) { ?><span>Date: <?php echo htmlspecialchars($r['date']); ?></span><?php } ?>
                    <span>Time: <?php echo htmlspecialchars(!empty($r['time']) ? $r['time'] : 'Not set'); ?></span>
                </p>
            <?php } elseif (!empty($r['meta'])) { ?>
                <p class="reminder-meta"><?php echo htmlspecialchars($r['meta']); ?></p>
            <?php } ?>
            <p><?php echo htmlspecialchars($r['message']); ?></p>
            <?php if (!empty($r['link']) && $scope === 'all') { ?>
                <a class="reminder-link" href="<?php echo htmlspecialchars($r['link']); ?>">View</a>
            <?php } ?>
        </article>
        <?php } ?>
    </div>
</section>
<?php } ?>
