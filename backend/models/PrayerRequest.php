<?php
/**
 * PrayerRequest Model
 * Handles database operations for prayer requests.
 */

require_once __DIR__ . '/../config/database.php';

class PrayerRequest {
    private static $table = 'prayer_requests';

    /**
     * Create a new prayer request
     */
    public static function create($data) {
        $conn = getDBConnection();

        $sql = "INSERT INTO " . self::$table . " 
                (user_id, full_name, email, city, country, phone, category, request_text, attachment_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $userId = isset($data['user_id']) ? $data['user_id'] : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $category = isset($data['category']) ? $data['category'] : 'General';
        $attachmentUrl = isset($data['attachment_url']) ? $data['attachment_url'] : null;

        $stmt->bind_param("issssssss", 
            $userId, 
            $data['full_name'], 
            $data['email'], 
            $data['city'], 
            $data['country'], 
            $phone, 
            $category, 
            $data['request_text'],
            $attachmentUrl
        );

        if ($stmt->execute()) {
            return $conn->insert_id;
        }

        return false;
    }

    /**
     * Get prayer request by ID
     */
    public static function getById($id) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT pr.*, u.first_name as responder_first, u.last_name as responder_last 
                               FROM " . self::$table . " pr 
                               LEFT JOIN users u ON pr.responded_by = u.id 
                               WHERE pr.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get all prayer requests (for admin)
     */
    public static function getAll($filters = []) {
        $conn = getDBConnection();
        $sql = "SELECT pr.*, u.first_name as user_first, u.last_name as user_last 
                FROM " . self::$table . " pr 
                LEFT JOIN users u ON pr.user_id = u.id";
        
        $where = [];
        $params = [];
        $types = "";

        if (isset($filters['status'])) {
            $where[] = "pr.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        if (isset($filters['user_id'])) {
            $where[] = "pr.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY pr.created_at DESC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        return $requests;
    }

    /**
     * Respond to a prayer request
     */
    public static function respond($id, $adminId, $response) {
        $conn = getDBConnection();
        $sql = "UPDATE " . self::$table . " 
                SET admin_response = ?, responded_by = ?, responded_at = NOW(), status = 'answered' 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $response, $adminId, $id);
        return $stmt->execute();
    }

    /**
     * Delete a prayer request
     */
    public static function delete($id) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM " . self::$table . " WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /**
     * Get prayer requests by user ID
     */
    public static function getByUser($userId, $limit = 50, $offset = 0) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT pr.*, u.first_name as responder_first, u.last_name as responder_last 
                               FROM " . self::$table . " pr 
                               LEFT JOIN users u ON pr.responded_by = u.id 
                               WHERE pr.user_id = ? 
                               ORDER BY pr.created_at DESC 
                               LIMIT ? OFFSET ?");
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        return $requests;
    }

    /**
     * Get summary statistics for admin dashboard
     */
    public static function getStats() {
        $conn = getDBConnection();
        $stats = [
            'total' => 0,
            'pending' => 0,
            'answered' => 0
        ];

        $res = $conn->query("SELECT status, COUNT(*) as count FROM " . self::$table . " GROUP BY status");
        while ($row = $res->fetch_assoc()) {
            if ($row['status'] == 'pending') $stats['pending'] = (int)$row['count'];
            if ($row['status'] == 'answered') $stats['answered'] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }

        return $stats;
    }
}
?>
