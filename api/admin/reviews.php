<?php
declare(strict_types=1);

/**
 * Admin Reviews Management API
 * GET  /api/admin/reviews.php - List all reviews with filters & stats
 * POST /api/admin/reviews.php - Moderate (approve/hide/reject) or Reply
 */

require_once __DIR__ . '/../../core/bootstrap.php';

require_admin_auth();

$reviewService = new ReviewService();
$adminId = (int)$_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $filters = [
            'status'     => $_GET['status'] ?? 'all',
            'rating'     => isset($_GET['rating']) && $_GET['rating'] !== 'all' ? $_GET['rating'] : null,
            'q'          => $_GET['q'] ?? '',
            'product_id' => isset($_GET['product_id']) ? (int)$_GET['product_id'] : null,
            'page'       => isset($_GET['page']) ? (int)$_GET['page'] : 1,
            'limit'      => isset($_GET['limit']) ? (int)$_GET['limit'] : 15
        ];

        $result = $reviewService->getAdminReviews($filters);

        json_response([
            'success'    => true,
            'stats'      => $result['stats'],
            'data'       => $result['reviews'],
            'pagination' => $result['pagination']
        ]);
    } catch (Throwable $e) {
        error_log('[Admin Reviews GET Error] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Lỗi tải danh sách đánh giá.'], 500);
    }
}

if ($method === 'POST') {
    try {
        $input = get_json_input();
        $action = trim((string)($input['action'] ?? ''));

        if ($action === 'moderate') {
            $reviewId = (int)($input['review_id'] ?? 0);
            $modAction = trim((string)($input['status'] ?? '')); // approve, hide, reject
            $reason = isset($input['reason']) ? trim((string)$input['reason']) : null;

            if ($reviewId <= 0 || !in_array($modAction, ['approve', 'hide', 'reject'], true)) {
                json_response(['success' => false, 'message' => 'Dữ liệu kiểm duyệt không hợp lệ.'], 400);
            }

            $ok = $reviewService->moderateReview($reviewId, $adminId, $modAction, $reason);
            if (!$ok) {
                json_response(['success' => false, 'message' => 'Không tìm thấy đánh giá cần kiểm duyệt.'], 404);
            }

            $actionMessages = [
                'approve' => 'Đã duyệt đánh giá thành công và cập nhật điểm sao sản phẩm.',
                'hide'    => 'Đã ẩn đánh giá khỏi giao diện sản phẩm.',
                'reject'  => 'Đã từ chối đánh giá vi phạm.'
            ];

            json_response([
                'success' => true,
                'message' => $actionMessages[$modAction] ?? 'Thao tác kiểm duyệt thành công.',
                'new_status' => $modAction === 'approve' ? 'approved' : ($modAction === 'hide' ? 'hidden' : 'rejected')
            ]);
        }

        if ($action === 'reply') {
            $reviewId = (int)($input['review_id'] ?? 0);
            $content = trim((string)($input['reply_content'] ?? ''));

            if ($reviewId <= 0 || $content === '') {
                json_response(['success' => false, 'message' => 'Vui lòng nhập nội dung phản hồi cho đánh giá.'], 400);
            }

            $replyData = $reviewService->replyReview($reviewId, $adminId, $content);

            json_response([
                'success' => true,
                'message' => 'Đã gửi phản hồi chính thức từ Bloomora.',
                'data'    => $replyData
            ]);
        }

        json_response(['success' => false, 'message' => 'Hành động không được hỗ trợ.'], 400);
    } catch (InvalidArgumentException $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('[Admin Reviews POST Error] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Lỗi hệ thống khi xử lý kiểm duyệt đánh giá.'], 500);
    }
}

json_response(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], 405);
