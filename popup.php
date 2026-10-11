<?php
$popup = take_popup();
if ($popup) {
?>
<div class="popup-overlay" id="success-popup">
    <div class="popup-box <?php echo !empty($popup['receipt']) ? 'popup-receipt' : ''; ?>" role="dialog" aria-modal="true">
        <div class="popup-icon">OK</div>
        <h3><?php echo htmlspecialchars($popup['title']); ?></h3>
        <p><?php echo nl2br(htmlspecialchars($popup['message'])); ?></p>
        <?php if (!empty($popup['receipt']) && is_array($popup['receipt'])) { ?>
        <div class="receipt-card">
            <div class="receipt-head">Payment receipt</div>
            <table class="receipt-table">
                <?php foreach ($popup['receipt'] as $label => $value) { ?>
                <tr>
                    <th><?php echo htmlspecialchars($label); ?></th>
                    <td><?php echo htmlspecialchars($value); ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>
        <?php } ?>
        <button type="button" class="popup-close" id="popup-close">Close</button>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('success-popup');
    var btn = document.getElementById('popup-close');
    if (!overlay) return;
    function hide() { overlay.style.display = 'none'; }
    if (btn) btn.addEventListener('click', hide);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) hide();
    });
})();
</script>
<?php } ?>
