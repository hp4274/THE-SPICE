<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span>SP</span>
        </div>
        <div class="sidebar-title-group">
            <div class="sidebar-title">
                The Spice <span style="font-size: 0.65rem; padding: 2px 6px; border-radius: 99px; background: rgba(229,57,53,0.12); color: var(--primary-red); font-weight: 800;">PRO</span>
            </div>
            <div class="sidebar-subtitle">Buffet Management System</div>
        </div>
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
