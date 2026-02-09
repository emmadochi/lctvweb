<?php
/**
 * Create Donations Tables
 * Sets up comprehensive donation and donor management system
 */

require_once 'config/database.php';

echo "Creating Donations Tables\n";
echo "=========================\n\n";

try {
    $conn = getDBConnection();
    echo "✓ Connected to database\n";

    // Create donations table
    $donationsTableSql = "CREATE TABLE IF NOT EXISTS donations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        donor_name VARCHAR(255) NOT NULL,
        donor_email VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'USD',
        payment_method VARCHAR(50),
        payment_provider VARCHAR(50), -- stripe, paypal, etc.
        transaction_id VARCHAR(255) UNIQUE,
        transaction_status VARCHAR(50) DEFAULT 'pending',
        is_recurring BOOLEAN DEFAULT FALSE,
        recurring_interval ENUM('monthly', 'quarterly', 'yearly') NULL,
        next_payment_date DATE NULL,
        donation_type ENUM('general', 'building_fund', 'missions', 'youth', 'children', 'outreach', 'other') DEFAULT 'general',
        donation_purpose TEXT,
        is_anonymous BOOLEAN DEFAULT FALSE,
        tax_deductible BOOLEAN DEFAULT TRUE,
        receipt_issued BOOLEAN DEFAULT FALSE,
        receipt_number VARCHAR(100),
        notes TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        utm_source VARCHAR(100),
        utm_medium VARCHAR(100),
        utm_campaign VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_donations_user (user_id),
        INDEX idx_donations_email (donor_email),
        INDEX idx_donations_transaction (transaction_id),
        INDEX idx_donations_status (transaction_status),
        INDEX idx_donations_recurring (is_recurring),
        INDEX idx_donations_type (donation_type),
        INDEX idx_donations_date (created_at),
        INDEX idx_donations_amount (amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($donationsTableSql) === TRUE) {
        echo "✓ Donations table created\n";
    } else {
        echo "✗ Error creating donations table: " . $conn->error . "\n";
        exit(1);
    }

    // Create donation_campaigns table
    $campaignsTableSql = "CREATE TABLE IF NOT EXISTS donation_campaigns (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        goal_amount DECIMAL(12,2),
        current_amount DECIMAL(12,2) DEFAULT 0.00,
        currency VARCHAR(3) DEFAULT 'USD',
        start_date DATE NOT NULL,
        end_date DATE,
        campaign_type ENUM('general', 'building', 'missions', 'emergency', 'project') DEFAULT 'general',
        is_active BOOLEAN DEFAULT TRUE,
        is_featured BOOLEAN DEFAULT FALSE,
        image_url VARCHAR(500),
        video_url VARCHAR(500),
        thank_you_message TEXT,
        impact_description TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id),
        INDEX idx_campaigns_active (is_active),
        INDEX idx_campaigns_featured (is_featured),
        INDEX idx_campaigns_dates (start_date, end_date),
        INDEX idx_campaigns_type (campaign_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($campaignsTableSql) === TRUE) {
        echo "✓ Donation campaigns table created\n";
    } else {
        echo "✗ Error creating donation campaigns table: " . $conn->error . "\n";
        exit(1);
    }

    // Create donors table for guest donors
    $donorsTableSql = "CREATE TABLE IF NOT EXISTS donors (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) UNIQUE NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        phone VARCHAR(20),
        address_street VARCHAR(255),
        address_city VARCHAR(100),
        address_state VARCHAR(100),
        address_zip VARCHAR(20),
        address_country VARCHAR(100) DEFAULT 'USA',
        total_donated DECIMAL(12,2) DEFAULT 0.00,
        donation_count INT DEFAULT 0,
        last_donation_date TIMESTAMP NULL,
        is_subscribed BOOLEAN DEFAULT TRUE,
        preferred_currency VARCHAR(3) DEFAULT 'USD',
        tax_id VARCHAR(50), -- For tax receipt purposes
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_donors_email (email),
        INDEX idx_donors_total (total_donated DESC),
        INDEX idx_donors_subscribed (is_subscribed),
        INDEX idx_donors_last_donation (last_donation_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($donorsTableSql) === TRUE) {
        echo "✓ Donors table created\n";
    } else {
        echo "✗ Error creating donors table: " . $conn->error . "\n";
        exit(1);
    }

    // Create recurring_donations table
    $recurringTableSql = "CREATE TABLE IF NOT EXISTS recurring_donations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        donation_id INT NOT NULL,
        user_id INT NULL,
        donor_id INT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'USD',
        interval_type ENUM('monthly', 'quarterly', 'yearly') NOT NULL,
        next_payment_date DATE NOT NULL,
        end_date DATE NULL,
        payment_method_id VARCHAR(255), -- Stripe/PayPal subscription ID
        status ENUM('active', 'paused', 'cancelled', 'expired') DEFAULT 'active',
        failure_count INT DEFAULT 0,
        last_failure_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (donation_id) REFERENCES donations(id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL,
        INDEX idx_recurring_user (user_id),
        INDEX idx_recurring_donor (donor_id),
        INDEX idx_recurring_next_payment (next_payment_date),
        INDEX idx_recurring_status (status),
        INDEX idx_recurring_interval (interval_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($recurringTableSql) === TRUE) {
        echo "✓ Recurring donations table created\n";
    } else {
        echo "✗ Error creating recurring donations table: " . $conn->error . "\n";
        exit(1);
    }

    // Create tax_receipts table
    $receiptsTableSql = "CREATE TABLE IF NOT EXISTS tax_receipts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        donation_id INT NOT NULL,
        receipt_number VARCHAR(100) UNIQUE NOT NULL,
        donor_name VARCHAR(255) NOT NULL,
        donor_email VARCHAR(255) NOT NULL,
        donor_address TEXT,
        donation_date DATE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'USD',
        tax_year YEAR NOT NULL,
        organization_name VARCHAR(255) NOT NULL,
        organization_ein VARCHAR(50),
        organization_address TEXT,
        pdf_path VARCHAR(500),
        email_sent BOOLEAN DEFAULT FALSE,
        email_sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (donation_id) REFERENCES donations(id),
        INDEX idx_receipts_donation (donation_id),
        INDEX idx_receipts_number (receipt_number),
        INDEX idx_receipts_year (tax_year),
        INDEX idx_receipts_email (donor_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($receiptsTableSql) === TRUE) {
        echo "✓ Tax receipts table created\n";
    } else {
        echo "✗ Error creating tax receipts table: " . $conn->error . "\n";
        exit(1);
    }

    // Update donations table to link to donors
    $alterDonationsSql = "ALTER TABLE donations
        ADD COLUMN IF NOT EXISTS donor_id INT NULL AFTER user_id,
        ADD FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL,
        ADD INDEX IF NOT EXISTS idx_donations_donor (donor_id)";

    if ($conn->query($alterDonationsSql) === TRUE) {
        echo "✓ Donations table updated with donor relationship\n";
    } else {
        if (strpos($conn->error, 'Duplicate key') === false &&
            strpos($conn->error, 'Duplicate column') === false) {
            echo "✗ Error updating donations table: " . $conn->error . "\n";
        } else {
            echo "✓ Donations table donor relationship already exists\n";
        }
    }

    // Add sample donation data
    echo "\nAdding sample donation data...\n";

    // Sample donors
    $sampleDonors = [
        ['john.doe@example.com', 'John', 'Doe', '+1234567890', '123 Main St', 'Anytown', 'CA', '12345', 'USA'],
        ['jane.smith@example.com', 'Jane', 'Smith', '+1987654321', '456 Oak Ave', 'Springfield', 'IL', '62701', 'USA'],
        ['bob.johnson@church.org', 'Bob', 'Johnson', '+1555123456', '789 Pine Rd', 'Riverside', 'TX', '78301', 'USA'],
    ];

    $donorStmt = $conn->prepare("INSERT IGNORE INTO donors
        (email, first_name, last_name, phone, address_street, address_city, address_state, address_zip, address_country)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $donorCount = 0;
    foreach ($sampleDonors as $donor) {
        $donorStmt->bind_param("sssssssss", $donor[0], $donor[1], $donor[2], $donor[3], $donor[4], $donor[5], $donor[6], $donor[7], $donor[8]);
        if ($donorStmt->execute()) {
            $donorCount++;
        }
    }
    echo "✓ Added $donorCount sample donors\n";

    // Sample donation campaigns
    $sampleCampaigns = [
        ['Building Fund', 'Help us expand our sanctuary to accommodate our growing congregation', 50000.00, 25000.00, '2024-01-01', '2024-12-31', 'building', 1, 1],
        ['Missions Support', 'Support our missionaries spreading the Gospel worldwide', 25000.00, 12500.00, '2024-01-01', null, 'missions', 1, 0],
        ['Youth Ministry', 'Invest in our youth programs and activities', 15000.00, 7500.00, '2024-01-01', '2024-06-30', 'general', 1, 0],
    ];

    $campaignStmt = $conn->prepare("INSERT IGNORE INTO donation_campaigns
        (title, description, goal_amount, current_amount, start_date, end_date, campaign_type, is_active, is_featured, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    $campaignCount = 0;
    foreach ($sampleCampaigns as $campaign) {
        $campaignStmt->bind_param("ssdssssii", $campaign[0], $campaign[1], $campaign[2], $campaign[3], $campaign[4], $campaign[5], $campaign[6], $campaign[7], $campaign[8]);
        if ($campaignStmt->execute()) {
            $campaignCount++;
        }
    }
    echo "✓ Added $campaignCount sample donation campaigns\n";

    // Sample donation data can be added manually through the admin interface
    echo "✓ Tables ready for donations\n";

    $conn->close();
    echo "\n🎉 Donations tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Install Stripe/PayPal PHP SDKs\n";
    echo "2. Create DonationController.php\n";
    echo "3. Update API routes\n";
    echo "4. Create donation UI components\n";
    echo "5. Set up webhook handlers\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>