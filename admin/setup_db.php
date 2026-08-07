<?php
// admin/setup_db.php
require_once __DIR__ . '/includes/db.php';

echo "Setting up database tables...\n";

$sqls = [
    "CREATE TABLE IF NOT EXISTS leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id VARCHAR(20) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100),
        guest_count INT NOT NULL,
        booking_date DATE NOT NULL,
        booking_time TIME NOT NULL,
        occasion VARCHAR(50),
        special_request TEXT,
        booking_source VARCHAR(50) DEFAULT 'Online',
        assigned_table_id INT NULL,
        status ENUM('New', 'Contacted', 'Confirmed', 'Waiting List', 'Cancelled', 'Rejected', 'No Show', 'Completed') DEFAULT 'New',
        rejection_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS tables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_number VARCHAR(10) UNIQUE NOT NULL,
        capacity INT NOT NULL,
        status ENUM('Available', 'Reserved', 'Occupied', 'Billing', 'Cleaning', 'Blocked', 'Maintenance', 'Merged') DEFAULT 'Available',
        merged_with INT NULL,
        current_reservation_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_number VARCHAR(20) UNIQUE NOT NULL,
        lead_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        guests INT NOT NULL,
        reservation_date DATE NOT NULL,
        reservation_time TIME NOT NULL,
        table_id INT NULL,
        status ENUM('Upcoming', 'Arrived', 'Dining', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Upcoming',
        bill_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_number VARCHAR(20) UNIQUE NOT NULL,
        reservation_id INT NULL,
        table_id INT NULL,
        customer_name VARCHAR(100) NOT NULL,
        subtotal DECIMAL(10, 2) DEFAULT 0.00,
        discount DECIMAL(10, 2) DEFAULT 0.00,
        gst DECIMAL(10, 2) DEFAULT 0.00,
        grand_total DECIMAL(10, 2) DEFAULT 0.00,
        status ENUM('Pending', 'Active', 'Payment Pending', 'Paid', 'Closed', 'Cancelled') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS bill_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price DECIMAL(10, 2) NOT NULL,
        total DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) UNIQUE NOT NULL,
        bill_id INT NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method ENUM('Cash', 'UPI', 'Card', 'Wallet', 'Bank Transfer', 'Refund', 'Partial Payment') NOT NULL,
        status ENUM('Completed', 'Failed', 'Refunded', 'Cancelled') DEFAULT 'Completed',
        reference_number VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "Successfully executed query.\n";
    } catch (\PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Seed some tables if empty
$stmt = $pdo->query("SELECT COUNT(*) FROM tables");
if ($stmt->fetchColumn() == 0) {
    echo "Seeding tables...\n";
    for ($i = 1; $i <= 10; $i++) {
        $cap = ($i % 3 == 0) ? 6 : (($i % 2 == 0) ? 4 : 2);
        $pdo->exec("INSERT INTO tables (table_number, capacity) VALUES ('T$i', $cap)");
    }
}

echo "Database setup complete.\n";
?>
