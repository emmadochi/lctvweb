<?php
/**
 * Reaction Controller
 * Handles reaction-related API requests
 */

require_once __DIR__ . '/../models/Reaction.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../utils/Response.php';

class ReactionController {

    /**
     * Get reactions for a video
     */
    public function getByVideo($videoId) {
        try {
            $stats = Reaction::getStats($videoId);
            Response::success($stats);

        } catch (Exception $e) {
            error_log("Reaction getByVideo error: " . $e->getMessage());
            Response::error('Failed to load reactions', 500);
        }
    }

    /**
     * Get user's reaction for a video
     */
    public function getUserReaction($videoId) {
        try {
            // Require authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
                return;
            }

            $reaction = Reaction::getUserReaction($videoId, $user['user_id']);

            Response::success([
                'reaction' => $reaction,
                'has_reacted' => $reaction !== null
            ]);

        } catch (Exception $e) {
            error_log("Reaction getUserReaction error: " . $e->getMessage());
            Response::error('Failed to load user reaction', 500);
        }
    }

    /**
     * Add or update reaction
     */
    public function react($videoId) {
        try {
            // Require authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
                return;
            }

            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['reaction_type'])) {
                Response::badRequest('Reaction type is required');
                return;
            }

            // Verify video exists
            $video = Video::find($videoId);
            if (!$video) {
                Response::notFound('Video not found');
                return;
            }

            // Validate reaction type
            $validTypes = array_keys(Reaction::TYPES);
            if (!in_array($input['reaction_type'], $validTypes)) {
                Response::badRequest('Invalid reaction type. Valid types: ' . implode(', ', $validTypes));
                return;
            }

            $result = Reaction::react($videoId, $user['user_id'], $input['reaction_type']);

            if ($result) {
                // Update video reaction count
                $this->updateVideoReactionCount($videoId);

                Response::success([
                    'action' => $result['action'],
                    'reaction_type' => $result['reaction_type'] ?? null,
                    'emoji' => $result['emoji'] ?? null,
                    'stats' => Reaction::getStats($videoId)
                ], 'Reaction ' . $result['action'] . ' successfully');
            } else {
                Response::error('Failed to process reaction', 500);
            }

        } catch (Exception $e) {
            error_log("Reaction react error: " . $e->getMessage());
            Response::error('Failed to process reaction', 500);
        }
    }

    /**
     * Remove reaction
     */
    public function removeReaction($videoId) {
        try {
            // Require authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
                return;
            }

            $existing = Reaction::getUserReaction($videoId, $user['user_id']);

            if (!$existing) {
                Response::badRequest('No reaction found to remove');
                return;
            }

            // Remove the reaction
            $conn = getDBConnection();
            $sql = "DELETE FROM reactions WHERE video_id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $videoId, $user['user_id']);

            if ($stmt->execute()) {
                // Update video reaction count
                $this->updateVideoReactionCount($videoId);

                Response::success([
                    'action' => 'removed',
                    'stats' => Reaction::getStats($videoId)
                ], 'Reaction removed successfully');
            } else {
                Response::error('Failed to remove reaction', 500);
            }

        } catch (Exception $e) {
            error_log("Reaction remove error: " . $e->getMessage());
            Response::error('Failed to remove reaction', 500);
        }
    }

    /**
     * Get reaction stats for multiple videos
     */
    public function getBulkStats() {
        try {
            // Get JSON input with video IDs
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['video_ids']) || !is_array($input['video_ids'])) {
                Response::badRequest('Video IDs array is required');
                return;
            }

            // Limit to reasonable number of videos
            if (count($input['video_ids']) > 50) {
                Response::badRequest('Cannot request stats for more than 50 videos at once');
                return;
            }

            $stats = Reaction::getBulkStats($input['video_ids']);
            Response::success($stats);

        } catch (Exception $e) {
            error_log("Reaction getBulkStats error: " . $e->getMessage());
            Response::error('Failed to load reaction stats', 500);
        }
    }

    /**
     * Get available reaction types
     */
    public function getReactionTypes() {
        try {
            $types = [];
            foreach (Reaction::TYPES as $type => $emoji) {
                $types[] = [
                    'type' => $type,
                    'emoji' => $emoji,
                    'name' => ucfirst($type)
                ];
            }

            Response::success([
                'types' => $types,
                'total_types' => count($types)
            ]);

        } catch (Exception $e) {
            error_log("Reaction getReactionTypes error: " . $e->getMessage());
            Response::error('Failed to load reaction types', 500);
        }
    }

    /**
     * Get reaction leaderboard (most reacted videos)
     */
    public function getLeaderboard() {
        try {
            $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;

            $conn = getDBConnection();

            $sql = "SELECT v.id, v.title, v.thumbnail_url, v.comment_count, v.reaction_count,
                           COUNT(r.id) as total_reactions,
                           GROUP_CONCAT(DISTINCT r.reaction_type) as reaction_types
                    FROM videos v
                    LEFT JOIN reactions r ON v.id = r.video_id
                    WHERE v.is_active = 1
                    GROUP BY v.id
                    ORDER BY total_reactions DESC, v.reaction_count DESC
                    LIMIT ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();

            $result = $stmt->get_result();
            $leaderboard = [];

            while ($row = $result->fetch_assoc()) {
                $reactionTypes = $row['reaction_types'] ? explode(',', $row['reaction_types']) : [];

                $leaderboard[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'thumbnail_url' => $row['thumbnail_url'],
                    'total_reactions' => (int)$row['total_reactions'],
                    'reaction_count' => (int)$row['reaction_count'],
                    'comment_count' => (int)$row['comment_count'],
                    'reaction_types' => array_map(function($type) {
                        return [
                            'type' => $type,
                            'emoji' => Reaction::TYPES[$type] ?? '👍'
                        ];
                    }, $reactionTypes)
                ];
            }

            Response::success([
                'leaderboard' => $leaderboard,
                'limit' => $limit
            ]);

        } catch (Exception $e) {
            error_log("Reaction leaderboard error: " . $e->getMessage());
            Response::error('Failed to load reaction leaderboard', 500);
        }
    }

    /**
     * Update video reaction count
     */
    private function updateVideoReactionCount($videoId) {
        try {
            $conn = getDBConnection();

            // Get total reaction count for this video
            $sql = "SELECT COUNT(*) as count FROM reactions WHERE video_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $videoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $count = $row['count'];
            Video::update($videoId, ['reaction_count' => $count]);

        } catch (Exception $e) {
            error_log("Failed to update video reaction count: " . $e->getMessage());
        }
    }
}
?>