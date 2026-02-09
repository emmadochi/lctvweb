<?php
/**
 * Donation Model
 * Handles donation processing, donor management, and payment operations
 */

require_once __DIR__ . '/../config/database.php';

class Donation {
    private static $donationsTable = 'donations';
    private static $campaignsTable = 'donation_campaigns';
    private static $donorsTable = 'donors';
    private static $recurringTable = 'recurring_donations';
    private static $receiptsTable = 'tax_receipts';

    /**
     * Create a new donation
     */
    public static function create($donationData) {
        $conn = getDBConnection();

        // First, ensure donor exists
        $donorId = self::findOrCreateDonor($donationData);

        $sql = "INSERT INTO " . self::$donationsTable . "
                (user_id, donor_id, donor_name, donor_email, amount, currency, payment_method, payment_provider, transaction_id, transaction_status, is_recurring, recurring_interval, donation_type, donation_purpose, is_anonymous, tax_deductible, notes, ip_address, user_agent, utm_source, utm_medium, utm_campaign)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $userId = $donationData['user_id'] ?? null;
        $donorName = $donationData['donor_name'];
        $donorEmail = $donationData['donor_email'];
        $amount = $donationData['amount'];
        $currency = $donationData['currency'] ?? 'USD';
        $paymentMethod = $donationData['payment_method'] ?? '';
        $paymentProvider = $donationData['payment_provider'] ?? '';
        $transactionId = $donationData['transaction_id'] ?? null;
        $transactionStatus = $donationData['transaction_status'] ?? 'pending';
        $isRecurring = $donationData['is_recurring'] ?? false;
        $recurringInterval = $donationData['recurring_interval'] ?? null;
        $donationType = $donationData['donation_type'] ?? 'general';
        $donationPurpose = $donationData['donation_purpose'] ?? '';
        $isAnonymous = $donationData['is_anonymous'] ?? false;
        $taxDeductible = $donationData['tax_deductible'] ?? true;
        $notes = $donationData['notes'] ?? '';
        $ipAddress = $donationData['ip_address'] ?? '';
        $userAgent = $donationData['user_agent'] ?? '';
        $utmSource = $donationData['utm_source'] ?? '';
        $utmMedium = $donationData['utm_medium'] ?? '';
        $utmCampaign = $donationData['utm_campaign'] ?? '';

        $stmt->bind_param("iissdsssssissssssssssss", $userId, $donorId, $donorName, $donorEmail, $amount, $currency, $paymentMethod, $paymentProvider, $transactionId, $transactionStatus, $isRecurring, $recurringInterval, $donationType, $donationPurpose, $isAnonymous, $taxDeductible, $notes, $ipAddress, $userAgent, $utmSource, $utmMedium, $utmCampaign);

        if ($stmt->execute()) {
            $donationId = $conn->insert_id;

            // Update donor's total donated amount
            self::updateDonorTotal($donorId, $amount);

            // If recurring, create recurring donation record
            if ($isRecurring && $recurringInterval) {
                self::createRecurringDonation($donationId, $donationData);
            }

            // Update campaign amount if campaign donation
            if (isset($donationData['campaign_id'])) {
                self::updateCampaignAmount($donationData['campaign_id'], $amount);
            }

            return $donationId;
        }

        return false;
    }

    /**
     * Find or create donor
     */
    private static function findOrCreateDonor($donationData) {
        $conn = getDBConnection();

        // Check if donor exists by email
        $sql = "SELECT id FROM " . self::$donorsTable . " WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $donationData['donor_email']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        }

        // Create new donor
        $sql = "INSERT INTO " . self::$donorsTable . "
                (email, first_name, last_name, phone, address_street, address_city, address_state, address_zip, address_country)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $firstName = $donationData['first_name'] ?? '';
        $lastName = $donationData['last_name'] ?? '';
        $phone = $donationData['phone'] ?? '';
        $street = $donationData['address_street'] ?? '';
        $city = $donationData['address_city'] ?? '';
        $state = $donationData['address_state'] ?? '';
        $zip = $donationData['address_zip'] ?? '';
        $country = $donationData['address_country'] ?? 'USA';

        $stmt->bind_param("sssssssss", $donationData['donor_email'], $firstName, $lastName, $phone, $street, $city, $state, $zip, $country);

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return null;
    }

    /**
     * Update donor's total donated amount
     */
    private static function updateDonorTotal($donorId, $amount) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$donorsTable . "
                SET total_donated = total_donated + ?,
                    donation_count = donation_count + 1,
                    last_donation_date = NOW()
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $donorId);
        $stmt->execute();
    }

    /**
     * Create recurring donation record
     */
    private static function createRecurringDonation($donationId, $donationData) {
        $conn = getDBConnection();

        // Calculate next payment date
        $nextPaymentDate = self::calculateNextPaymentDate($donationData['recurring_interval']);

        $sql = "INSERT INTO " . self::$recurringTable . "
                (donation_id, user_id, donor_id, amount, currency, interval_type, next_payment_date, payment_method_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";

        $stmt = $conn->prepare($sql);

        $userId = $donationData['user_id'] ?? null;
        $donorId = $donationData['donor_id'] ?? null;
        $amount = $donationData['amount'];
        $currency = $donationData['currency'] ?? 'USD';
        $intervalType = $donationData['recurring_interval'];
        $paymentMethodId = $donationData['payment_method_id'] ?? '';

        $stmt->bind_param("iiidssss", $donationId, $userId, $donorId, $amount, $currency, $intervalType, $nextPaymentDate, $paymentMethodId);
        $stmt->execute();
    }

    /**
     * Update campaign current amount
     */
    private static function updateCampaignAmount($campaignId, $amount) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$campaignsTable . "
                SET current_amount = current_amount + ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $campaignId);
        $stmt->execute();
    }

    /**
     * Calculate next payment date based on interval
     */
    private static function calculateNextPaymentDate($interval) {
        $date = new DateTime();

        switch ($interval) {
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
            default:
                $date->modify('+1 month');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Get donation by ID
     */
    public static function find($id) {
        $conn = getDBConnection();

        $sql = "SELECT d.*, c.title as campaign_title, dr.first_name, dr.last_name, dr.email as donor_email_full
                FROM " . self::$donationsTable . " d
                LEFT JOIN " . self::$campaignsTable . " c ON d.donation_type = 'campaign' AND c.id = d.campaign_id
                LEFT JOIN " . self::$donorsTable . " dr ON d.donor_id = dr.id
                WHERE d.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get donations by user
     */
    public static function getByUser($userId, $limit = 50, $offset = 0) {
        $conn = getDBConnection();

        $sql = "SELECT d.*, c.title as campaign_title
                FROM " . self::$donationsTable . " d
                LEFT JOIN " . self::$campaignsTable . " c ON d.donation_type = 'campaign' AND c.id = d.campaign_id
                WHERE d.user_id = ?
                ORDER BY d.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $donations = [];

        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }

        return $donations;
    }

    /**
     * Get donations by donor
     */
    public static function getByDonor($donorId, $limit = 50, $offset = 0) {
        $conn = getDBConnection();

        $sql = "SELECT d.*, c.title as campaign_title
                FROM " . self::$donationsTable . " d
                LEFT JOIN " . self::$campaignsTable . " c ON d.donation_type = 'campaign' AND c.id = d.campaign_id
                WHERE d.donor_id = ?
                ORDER BY d.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $donorId, $limit, $offset);
        $stmt->execute();

        $result = $stmt->get_result();
        $donations = [];

        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }

        return $donations;
    }

    /**
     * Get donation campaigns
     */
    public static function getCampaigns($activeOnly = true, $limit = 20) {
        $conn = getDBConnection();

        $whereClause = $activeOnly ? "WHERE is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE())" : "";
        $sql = "SELECT * FROM " . self::$campaignsTable . " {$whereClause}
                ORDER BY is_featured DESC, created_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        $campaigns = [];

        while ($row = $result->fetch_assoc()) {
            // Calculate progress percentage
            if ($row['goal_amount'] > 0) {
                $row['progress_percentage'] = min(100, ($row['current_amount'] / $row['goal_amount']) * 100);
            } else {
                $row['progress_percentage'] = 0;
            }

            $campaigns[] = $row;
        }

        return $campaigns;
    }

    /**
     * Get donation statistics
     */
    public static function getStats($period = '30') {
        $conn = getDBConnection();

        $dateFilter = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL {$period} DAY)";

        // Total donations
        $totalSql = "SELECT
                        COUNT(*) as total_donations,
                        SUM(amount) as total_amount,
                        AVG(amount) as avg_donation,
                        COUNT(DISTINCT donor_id) as unique_donors
                     FROM " . self::$donationsTable . "
                     WHERE transaction_status = 'completed' AND {$dateFilter}";

        $totalResult = $conn->query($totalSql);
        $stats = $totalResult->fetch_assoc();

        // Monthly breakdown
        $monthlySql = "SELECT
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as donations,
                          SUM(amount) as amount
                       FROM " . self::$donationsTable . "
                       WHERE transaction_status = 'completed' AND {$dateFilter}
                       GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                       ORDER BY month DESC";

        $monthlyResult = $conn->query($monthlySql);
        $monthly = [];
        while ($row = $monthlyResult->fetch_assoc()) {
            $monthly[] = $row;
        }

        // Top donors
        $topDonorsSql = "SELECT
                            d.first_name, d.last_name, d.email,
                            SUM(dn.amount) as total_donated,
                            COUNT(dn.id) as donation_count
                         FROM " . self::$donorsTable . " d
                         JOIN " . self::$donationsTable . " dn ON d.id = dn.donor_id
                         WHERE dn.transaction_status = 'completed' AND {$dateFilter}
                         GROUP BY d.id, d.first_name, d.last_name, d.email
                         ORDER BY total_donated DESC
                         LIMIT 10";

        $topDonorsResult = $conn->query($topDonorsSql);
        $topDonors = [];
        while ($row = $topDonorsResult->fetch_assoc()) {
            $topDonors[] = $row;
        }

        // Donation types breakdown
        $typesSql = "SELECT
                        donation_type,
                        COUNT(*) as count,
                        SUM(amount) as amount
                     FROM " . self::$donationsTable . "
                     WHERE transaction_status = 'completed' AND {$dateFilter}
                     GROUP BY donation_type
                     ORDER BY amount DESC";

        $typesResult = $conn->query($typesSql);
        $types = [];
        while ($row = $typesResult->fetch_assoc()) {
            $types[] = $row;
        }

        return [
            'overview' => $stats,
            'monthly' => $monthly,
            'top_donors' => $topDonors,
            'by_type' => $types,
            'period_days' => (int)$period
        ];
    }

    /**
     * Process recurring donations (to be called by cron job)
     */
    public static function processRecurringDonations() {
        $conn = getDBConnection();

        // Get recurring donations due today
        $sql = "SELECT rd.*, d.donor_email, d.donor_name
                FROM " . self::$recurringTable . " rd
                JOIN " . self::$donationsTable . " d ON rd.donation_id = d.id
                WHERE rd.status = 'active'
                AND rd.next_payment_date <= CURDATE()
                AND rd.failure_count < 3";

        $result = $conn->query($sql);
        $processed = 0;
        $failed = 0;

        while ($recurring = $result->fetch_assoc()) {
            try {
                // Process payment through payment provider
                $paymentResult = self::processRecurringPayment($recurring);

                if ($paymentResult['success']) {
                    // Create new donation record
                    $donationData = [
                        'user_id' => $recurring['user_id'],
                        'donor_id' => $recurring['donor_id'],
                        'donor_name' => $recurring['donor_name'],
                        'donor_email' => $recurring['donor_email'],
                        'amount' => $recurring['amount'],
                        'currency' => $recurring['currency'],
                        'payment_method' => $recurring['payment_method_id'],
                        'payment_provider' => 'recurring',
                        'transaction_id' => $paymentResult['transaction_id'],
                        'transaction_status' => 'completed',
                        'is_recurring' => true,
                        'recurring_interval' => $recurring['interval_type'],
                        'donation_type' => 'recurring',
                        'donation_purpose' => 'Recurring donation - ' . $recurring['interval_type']
                    ];

                    self::create($donationData);

                    // Update next payment date
                    $nextDate = self::calculateNextPaymentDate($recurring['interval_type']);
                    $updateSql = "UPDATE " . self::$recurringTable . "
                                 SET next_payment_date = ?, failure_count = 0
                                 WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $nextDate, $recurring['id']);
                    $updateStmt->execute();

                    $processed++;
                } else {
                    // Payment failed
                    $updateSql = "UPDATE " . self::$recurringTable . "
                                 SET failure_count = failure_count + 1,
                                     last_failure_reason = ?
                                 WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("si", $paymentResult['error'], $recurring['id']);
                    $updateStmt->execute();

                    // Deactivate if too many failures
                    if ($recurring['failure_count'] + 1 >= 3) {
                        $deactivateSql = "UPDATE " . self::$recurringTable . "
                                        SET status = 'cancelled'
                                        WHERE id = ?";
                        $deactivateStmt = $conn->prepare($deactivateSql);
                        $deactivateStmt->bind_param("i", $recurring['id']);
                        $deactivateStmt->execute();
                    }

                    $failed++;
                }
            } catch (Exception $e) {
                error_log("Recurring donation processing error: " . $e->getMessage());
                $failed++;
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * Process recurring payment (placeholder for payment provider integration)
     */
    private static function processRecurringPayment($recurring) {
        // This would integrate with Stripe/PayPal to process the recurring payment
        // For now, return success for testing
        return [
            'success' => true,
            'transaction_id' => 'rec_' . time() . '_' . rand(1000, 9999)
        ];
    }

    /**
     * Generate tax receipt
     */
    public static function generateTaxReceipt($donationId) {
        $conn = getDBConnection();

        $donation = self::find($donationId);
        if (!$donation || !$donation['tax_deductible']) {
            return false;
        }

        // Generate receipt number
        $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad($donationId, 6, '0', STR_PAD_LEFT);

        // Check if receipt already exists
        $checkSql = "SELECT id FROM " . self::$receiptsTable . " WHERE donation_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $donationId);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {
            return false; // Receipt already exists
        }

        // Create receipt record
        $sql = "INSERT INTO " . self::$receiptsTable . "
                (donation_id, receipt_number, donor_name, donor_email, donor_address, donation_date, amount, currency, tax_year, organization_name, organization_ein, organization_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'LCMTV Church', '12-3456789', '123 Church Street, Anytown, USA 12345')";

        $stmt = $conn->prepare($sql);

        $donorAddress = '';
        if ($donation['address_street']) {
            $donorAddress = $donation['address_street'];
            if ($donation['address_city']) $donorAddress .= ', ' . $donation['address_city'];
            if ($donation['address_state']) $donorAddress .= ', ' . $donation['address_state'];
            if ($donation['address_zip']) $donorAddress .= ' ' . $donation['address_zip'];
        }

        $donationDate = date('Y-m-d', strtotime($donation['created_at']));
        $taxYear = date('Y', strtotime($donation['created_at']));

        $stmt->bind_param("isssssdsi", $donationId, $receiptNumber, $donation['donor_name'], $donation['donor_email'], $donorAddress, $donationDate, $donation['amount'], $donation['currency'], $taxYear);

        if ($stmt->execute()) {
            // Update donation to mark receipt as issued
            $updateSql = "UPDATE " . self::$donationsTable . "
                         SET receipt_issued = 1, receipt_number = ?
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $receiptNumber, $donationId);
            $updateStmt->execute();

            return $receiptNumber;
        }

        return false;
    }

    /**
     * Get donor by email
     */
    public static function getDonorByEmail($email) {
        $conn = getDBConnection();

        $sql = "SELECT * FROM " . self::$donorsTable . " WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Update donation status
     */
    public static function updateStatus($donationId, $status, $transactionId = null) {
        $conn = getDBConnection();

        $sql = "UPDATE " . self::$donationsTable . "
                SET transaction_status = ?, updated_at = NOW()
                WHERE id = ?";

        $params = [$status, $donationId];
        $types = "si";

        if ($transactionId) {
            $sql = str_replace("WHERE id = ?", "SET transaction_id = ? WHERE id = ?", $sql);
            $params = [$status, $transactionId, $donationId];
            $types = "ssi";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }
}
?>