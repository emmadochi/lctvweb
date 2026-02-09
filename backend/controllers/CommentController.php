<?php
/**
 * Comment Controller
 * Handles comment-related API requests
 */

require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../utils/Response.php';

class CommentController {

    /**
     * Get comments for a video
     */
    public function getByVideo($videoId) {
        try {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 20;

            $result = Comment::getByVideo($videoId, $page, $limit);
            Response::success($result);

        } catch (Exception $e) {
            error_log("Comment getByVideo error: " . $e->getMessage());
            Response::error('Failed to load comments', 500);
        }
    }

    /**
     * Get single comment with replies
     */
    public function show($id) {
        try {
            $comment = Comment::find($id);

            if (!$comment) {
                Response::notFound('Comment not found');
                return;
            }

            // Get replies if this is a parent comment
            $replies = Comment::getReplies($id);
            $comment['replies'] = $replies;

            Response::success($comment);

        } catch (Exception $e) {
            error_log("Comment show error: " . $e->getMessage());
            Response::error('Failed to load comment', 500);
        }
    }

    /**
     * Create a new comment
     */
    public function create() {
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

            if (!$input) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            // Validate required fields
            if (empty($input['video_id']) || empty($input['content'])) {
                Response::badRequest('Video ID and content are required');
                return;
            }

            // Validate content length
            if (strlen(trim($input['content'])) < 2) {
                Response::badRequest('Comment must be at least 2 characters long');
                return;
            }

            if (strlen(trim($input['content'])) > 1000) {
                Response::badRequest('Comment cannot exceed 1000 characters');
                return;
            }

            // Verify video exists
            $video = Video::find($input['video_id']);
            if (!$video) {
                Response::notFound('Video not found');
                return;
            }

            // Create comment data
            $commentData = [
                'video_id' => (int)$input['video_id'],
                'user_id' => $user['user_id'],
                'content' => trim($input['content']),
                'parent_id' => isset($input['parent_id']) ? (int)$input['parent_id'] : null,
                'is_approved' => 1 // Auto-approve for now
            ];

            // Validate parent comment if provided
            if ($commentData['parent_id']) {
                $parentComment = Comment::find($commentData['parent_id']);
                if (!$parentComment) {
                    Response::badRequest('Parent comment not found');
                    return;
                }
                if ($parentComment['video_id'] !== $commentData['video_id']) {
                    Response::badRequest('Parent comment must be on the same video');
                    return;
                }
            }

            $commentId = Comment::create($commentData);

            if ($commentId) {
                // Update video comment count
                $this->updateVideoCommentCount($commentData['video_id']);

                $comment = Comment::find($commentId);
                Response::success($comment, 'Comment posted successfully', 201);
            } else {
                Response::error('Failed to create comment', 500);
            }

        } catch (Exception $e) {
            error_log("Comment create error: " . $e->getMessage());
            Response::error('Failed to create comment', 500);
        }
    }

    /**
     * Update a comment
     */
    public function update($id) {
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

            if (!$input || !isset($input['content'])) {
                Response::badRequest('Content is required');
                return;
            }

            // Find comment and verify ownership
            $comment = Comment::find($id);

            if (!$comment) {
                Response::notFound('Comment not found');
                return;
            }

            if ($comment['user_id'] !== $user['user_id']) {
                Response::forbidden('You can only edit your own comments');
                return;
            }

            // Validate content
            if (strlen(trim($input['content'])) < 2) {
                Response::badRequest('Comment must be at least 2 characters long');
                return;
            }

            if (strlen(trim($input['content'])) > 1000) {
                Response::badRequest('Comment cannot exceed 1000 characters');
                return;
            }

            $updateData = [
                'content' => trim($input['content']),
                'is_approved' => $comment['is_approved'] // Preserve approval status
            ];

            if (Comment::update($id, $updateData)) {
                $updatedComment = Comment::find($id);
                Response::success($updatedComment, 'Comment updated successfully');
            } else {
                Response::error('Failed to update comment', 500);
            }

        } catch (Exception $e) {
            error_log("Comment update error: " . $e->getMessage());
            Response::error('Failed to update comment', 500);
        }
    }

    /**
     * Delete a comment
     */
    public function delete($id) {
        try {
            // Require authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user) {
                Response::unauthorized();
                return;
            }

            // Find comment and verify ownership
            $comment = Comment::find($id);

            if (!$comment) {
                Response::notFound('Comment not found');
                return;
            }

            if ($comment['user_id'] !== $user['user_id']) {
                Response::forbidden('You can only delete your own comments');
                return;
            }

            $videoId = $comment['video_id'];

            if (Comment::delete($id)) {
                // Update video comment count
                $this->updateVideoCommentCount($videoId);

                Response::success(null, 'Comment deleted successfully');
            } else {
                Response::error('Failed to delete comment', 500);
            }

        } catch (Exception $e) {
            error_log("Comment delete error: " . $e->getMessage());
            Response::error('Failed to delete comment', 500);
        }
    }

    /**
     * Get comments for moderation (admin only)
     */
    public function getForModeration() {
        try {
            // Require admin authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                Response::forbidden('Admin access required');
                return;
            }

            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;

            $result = Comment::getAllForModeration($page, $limit);
            Response::success($result);

        } catch (Exception $e) {
            error_log("Comment moderation error: " . $e->getMessage());
            Response::error('Failed to load comments for moderation', 500);
        }
    }

    /**
     * Approve a comment (moderation)
     */
    public function approve($id) {
        try {
            // Require admin authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                Response::forbidden('Admin access required');
                return;
            }

            $comment = Comment::find($id);
            if (!$comment) {
                Response::notFound('Comment not found');
                return;
            }

            if (Comment::approve($id)) {
                // Update video comment count
                $this->updateVideoCommentCount($comment['video_id']);

                Response::success(null, 'Comment approved successfully');
            } else {
                Response::error('Failed to approve comment', 500);
            }

        } catch (Exception $e) {
            error_log("Comment approve error: " . $e->getMessage());
            Response::error('Failed to approve comment', 500);
        }
    }

    /**
     * Reject a comment (moderation)
     */
    public function reject($id) {
        try {
            // Require admin authentication
            require_once __DIR__ . '/../utils/Auth.php';
            Auth::requireAuth();
            $user = Auth::getUserFromToken();

            if (!$user || $user['role'] !== 'admin') {
                Response::forbidden('Admin access required');
                return;
            }

            $comment = Comment::find($id);
            if (!$comment) {
                Response::notFound('Comment not found');
                return;
            }

            $videoId = $comment['video_id'];

            if (Comment::reject($id)) {
                // Update video comment count
                $this->updateVideoCommentCount($videoId);

                Response::success(null, 'Comment rejected successfully');
            } else {
                Response::error('Failed to reject comment', 500);
            }

        } catch (Exception $e) {
            error_log("Comment reject error: " . $e->getMessage());
            Response::error('Failed to reject comment', 500);
        }
    }

    /**
     * Update video comment count
     */
    private function updateVideoCommentCount($videoId) {
        try {
            $count = Comment::getCount($videoId);
            Video::update($videoId, ['comment_count' => $count]);
        } catch (Exception $e) {
            error_log("Failed to update video comment count: " . $e->getMessage());
        }
    }
}
?>