<?php
// admin/settings.php
require_once 'includes/db.php';
include 'includes/header.php';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
            
            // If key doesn't exist, insert it (upsert)
            if ($stmt->rowCount() == 0) {
                $check = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
                $check->execute([$key]);
                if ($check->rowCount() == 0) {
                    $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$key, $value]);
                }
            }
        }
        $success_msg = "System settings saved successfully!";
    }
}

// Fetch Settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings_data = [];
if ($stmt) {
    while ($row = $stmt->fetch()) {
        $settings_data[$row['setting_key']] = $row['setting_value'];
    }
}
?>

<?php if (isset($success_msg)): ?>
    <div style="background: rgba(16,185,129,0.12); color: #10B981; padding: 14px 20px; border-radius: var(--radius-md); font-weight: 700; border: 1px solid rgba(16,185,129,0.3); margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="check-circle"></i> <?php echo $success_msg; ?>
    </div>
<?php endif; ?>

<form action="settings.php" method="POST">
    <input type="hidden" name="action" value="save_settings">
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Restaurant Information Glass Card -->
        <div class="glass-card">
            <div class="card-header">
                <h2 class="card-title"><i data-lucide="store"></i> Restaurant Profile</h2>
            </div>
            
            <div class="form-group">
                <label class="form-label">Restaurant Name</label>
                <input type="text" name="settings[restaurant_name]" value="<?php echo htmlspecialchars($settings_data['restaurant_name'] ?? 'The Spice Buffet'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Currency Symbol</label>
                <input type="text" name="settings[currency]" value="<?php echo htmlspecialchars($settings_data['currency'] ?? 'USD'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Phone Number</label>
                <input type="text" name="settings[contact_phone]" value="<?php echo htmlspecialchars($settings_data['contact_phone'] ?? '+1 (555) 234-5678'); ?>">
            </div>
        </div>

        <!-- Fixed Buffet Prices & Billing Rules Glass Card -->
        <div class="glass-card">
            <div class="card-header">
                <h2 class="card-title"><i data-lucide="percent"></i> Buffet Pricing & Taxes</h2>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div style="background: var(--light-pink); border-radius: var(--radius-md); padding: 14px; text-align: center;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Adult Buffet Rate</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary-red); margin-top: 2px;">$19.99 / Pax</div>
                    <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 2px;">Fixed Standard Rate</div>
                </div>
                <div style="background: rgba(16,185,129,0.08); border-radius: var(--radius-md); padding: 14px; text-align: center;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Child Buffet Rate</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #10B981; margin-top: 2px;">$9.99 / Pax</div>
                    <div style="font-size: 0.7rem; color: var(--text-secondary); margin-top: 2px;">Fixed Kids Rate</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Sales Tax Rate (%)</label>
                <input type="number" step="0.1" name="settings[tax_rate]" value="<?php echo htmlspecialchars($settings_data['tax_rate'] ?? '8.5'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">No-Show Grace Period (minutes)</label>
                <input type="number" min="1" max="120" name="settings[no_show_grace_mins]" value="<?php echo htmlspecialchars($settings_data['no_show_grace_mins'] ?? $settings_data['auto_cancel_mins'] ?? '5'); ?>" required>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                    Bookings past scheduled time + grace minutes automatically mark as <strong>No Show</strong>.
                </div>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 32px; font-size: 0.95rem;">
            <i data-lucide="save" style="width: 18px;"></i> Save All Settings
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
