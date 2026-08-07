<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header" style="justify-content: center; padding: 14px 10px; background: linear-gradient(135deg, #E53935 0%, #B71C1C 100%); border-bottom: 1px solid rgba(255, 255, 255, 0.15); overflow: hidden;">
        <a href="index.php" style="display: flex; align-items: center; justify-content: center; text-decoration: none; width: 100%;">
            <img src="../img/logo-text.png" alt="The Spice Logo" style="height: 95px; width: 100%; max-width: 230px; object-fit: contain; transform: scale(1.45); filter: drop-shadow(0 2px 6px rgba(0,0,0,0.2));" onerror="this.onerror=null; this.src='../img/main_logo.png';">
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leads.php" class="nav-link <?php echo $current_page == 'leads.php' ? 'active' : ''; ?>">
                    <i data-lucide="inbox"></i>
                    <span>Lead Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reservations.php" class="nav-link <?php echo $current_page == 'reservations.php' ? 'active' : ''; ?>">
                    <i data-lucide="calendar-check"></i>
                    <span>Reservations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="tables.php" class="nav-link <?php echo $current_page == 'tables.php' ? 'active' : ''; ?>">
                    <i data-lucide="grid-2x2"></i>
                    <span>Active Tables</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="bills.php" class="nav-link <?php echo $current_page == 'bills.php' ? 'active' : ''; ?>">
                    <i data-lucide="receipt"></i>
                    <span>Bills & Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sales.php" class="nav-link <?php echo $current_page == 'sales.php' ? 'active' : ''; ?>">
                    <i data-lucide="trending-up"></i>
                    <span>Today's Sales</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="transactions.php" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>">
                    <i data-lucide="history"></i>
                    <span>Transactions</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-bottom">
        <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i data-lucide="settings"></i>
            <span>Settings</span>
        </a>
        <a href="#" class="nav-link" style="color: var(--primary-red);" onclick="alert('Logging out of Admin Dashboard...'); return false;">
            <i data-lucide="log-out"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
