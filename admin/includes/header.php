<?php
// admin/includes/header.php
require_once __DIR__ . '/../services/SyncService.php';
SyncService::checkAutoNoShows();

$current_page = basename($_SERVER['PHP_SELF']);

// Map page names to titles & breadcrumbs
$page_titles = [
    'index.php' => ['Dashboard Overview', 'Dashboard / Overview'],
    'leads.php' => ['Lead Management', 'Dashboard / Leads'],
    'reservations.php' => ['Reservations', 'Dashboard / Reservations'],
    'tables.php' => ['Active Tables Floor Plan', 'Dashboard / Floor Plan'],
    'bills.php' => ['Bills & Billing System', 'Dashboard / Bills'],
    'sales.php' => ['Today\'s Sales Analytics', 'Dashboard / Analytics'],
    'transactions.php' => ['Transactions History', 'Dashboard / Transactions'],
    'settings.php' => ['System Settings', 'Dashboard / Settings'],
];

$title_info = $page_titles[$current_page] ?? ['Admin Panel', 'Dashboard'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title_info[0]; ?> - The Spice Buffet Admin</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Red & White Glassmorphism Styles -->
    <link rel="stylesheet" href="css/admin-style.css">
    <link rel="stylesheet" href="css/custom.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Realtime & Custom JS -->
    <script src="js/realtime.js?v=<?php echo time(); ?>"></script>
</head>
<body>

    <div class="app-wrapper">
        <!-- Sidebar Include -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <main class="main-content">
            <!-- Floating Topbar Navigation -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" id="menu-toggle">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="topbar-title-group">
                        <div class="topbar-breadcrumb">
                            <i data-lucide="home" style="width: 12px; color: var(--primary-red);"></i>
                            <span><?php echo $title_info[1]; ?></span>
                        </div>
                        <h1 class="topbar-page-title"><?php echo $title_info[0]; ?></h1>
                    </div>
                </div>

                <div class="topbar-right">
                    <!-- Global Search Pill -->
                    <button class="global-search-btn" onclick="openGlobalSearch()">
                        <i data-lucide="search" style="width: 16px; color: var(--primary-red);"></i>
                        <span>Search reservations, leads, tables...</span>
                        <kbd>Ctrl K</kbd>
                    </button>

                    <!-- Live Date Display -->
                    <div class="header-date-badge">
                        <i data-lucide="calendar"></i>
                        <span><?php echo date('M d, Y'); ?></span>
                    </div>

                    <!-- Quick Action Trigger -->
                    <button class="btn btn-primary btn-sm" onclick="openQuickActionModal()">
                        <i data-lucide="plus" style="width: 16px;"></i>
                        <span>Quick Action</span>
                    </button>

                    <!-- Notification Bell Button -->
                    <div style="position: relative;">
                        <?php
                        $notifications = [];
                        if (isset($pdo)) {
                            try {
                                $notif_stmt = $pdo->query("SELECT * FROM leads WHERE status='New' ORDER BY created_at DESC LIMIT 5");
                                $notifications = $notif_stmt->fetchAll();
                            } catch (Exception $e) {
                                $notifications = [];
                            }
                        }
                        $hasNotifs = count($notifications) > 0;
                        ?>
                        <button class="icon-btn" id="notifBtn" onclick="toggleNotifications()">
                            <i data-lucide="bell" style="width: 18px;"></i>
                            <span class="badge-dot" style="<?php echo $hasNotifs ? 'display: block;' : 'display: none;'; ?>"></span>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div class="glass-dropdown" id="notificationsDropdown" style="display: none;">
                            <div style="padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--text-primary);">Notifications</h3>
                                <span style="font-size: 12px; color: var(--primary-red); font-weight: 700; cursor: pointer;" onclick="markNotificationsRead()">Mark read</span>
                            </div>
                            <div id="notifListContainer" style="max-height: 300px; overflow-y: auto;">
                                <?php
                                if ($hasNotifs) {
                                    foreach ($notifications as $n) {
                                        $guestStr = ($n['guest_count']) ? $n['guest_count'] . ' Guests' : 'Guest';
                                        $timeStr = !empty($n['booking_time']) ? date('g:i A', strtotime($n['booking_time'])) : '';
                                        echo '<a href="leads.php" class="notif-item" style="display: block; text-decoration: none; padding: 14px 18px; border-bottom: 1px solid rgba(0,0,0,0.04); transition: background 0.2s;" onmouseover="this.style.background=\'rgba(229,57,53,0.04)\'" onmouseout="this.style.background=\'transparent\'">';
                                        echo '<div style="display: flex; align-items: flex-start; gap: 12px;">';
                                        echo '<div style="background: var(--light-pink); color: var(--primary-red); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><i data-lucide="users" style="width: 16px;"></i></div>';
                                        echo '<div style="flex: 1;">';
                                        echo '<div style="font-size: 13px; font-weight: 700; color: var(--text-primary);">New Lead Request</div>';
                                        echo '<div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">' . htmlspecialchars($n['customer_name']) . ' (' . htmlspecialchars($guestStr) . ')</div>';
                                        echo '<div style="font-size: 11px; color: var(--primary-red); margin-top: 4px; font-weight: 600;">' . htmlspecialchars($n['booking_date']) . ($timeStr ? ' at ' . htmlspecialchars($timeStr) : '') . '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</a>';
                                    }
                                } else {
                                    echo '<div id="noNotifPlaceholder" style="padding: 32px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">No pending notifications</div>';
                                }
                                ?>
                            </div>
                            <div style="padding: 12px; text-align: center; border-top: 1px solid rgba(0,0,0,0.06); background: rgba(255,255,255,0.6);">
                                <a href="leads.php" style="font-size: 12px; color: var(--primary-red); font-weight: 800;">View All Leads →</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Avatar Menu -->
                    <div class="profile-menu" onclick="alert('Admin Profile Settings')">
                        <img src="https://ui-avatars.com/api/?name=Spice+Admin&background=E53935&color=fff" alt="Admin" class="avatar">
                    </div>
                </div>
            </header>

            <script>
            function markNotificationsRead() {
                const dot = document.querySelector('.badge-dot');
                if (dot) dot.style.display = 'none';
                const notifContainer = document.getElementById('notifListContainer');
                if (notifContainer) {
                    notifContainer.innerHTML = '<div id="noNotifPlaceholder" style="padding: 32px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">No pending notifications</div>';
                }
            }
            
            function toggleNotifications() {
                const dropdown = document.getElementById('notificationsDropdown');
                if (dropdown.style.display === 'none' || !dropdown.style.display) {
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            }
            
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('notificationsDropdown');
                const notifBtn = document.getElementById('notifBtn');
                if (dropdown && notifBtn && !dropdown.contains(event.target) && !notifBtn.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            });
            </script>

            <!-- Page Content Starts -->
            <div class="page-content">
