<?php
declare(strict_types=1);

/**
 * Customer Review API
 * GET  /api/reviews.php?product_id={id}&rating={1-5}&has_image={0|1}&sort={priority|newest|highest|lowest}&page={1}&limit={5}
 * POST /api/reviews.php (Form-data: product_id, rating, comment, title, quality_tag, images[])
 */

require_once __DIR__ . '/../core/bootstrap.php';

init_secure_session();

$reviewService = new ReviewService();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Thiếu hoặc sai mã sản phẩm (product_id).'], 400);
        }

        $userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        $filters = [
            'rating'    => isset($_GET['rating']) && $_GET['rating'] !== 'all' ? $_GET['rating'] : null,
            'has_image' => !empty($_GET['has_image']),
            'sort'      => trim((string)($_GET['sort'] ?? 'priority')),
            'page'      => isset($_GET['page']) ? (int)$_GET['page'] : 1,
            'limit'     => isset($_GET['limit']) ? (int)$_GET['limit'] : 5
        ];

        // 1. Get summary stats
        $summary = $reviewService->getProductReviewSummary($productId);

        // 2. Check user eligibility to write a review
        $eligibility = $reviewService->checkUserReviewEligibility($userId, $productId);

        // 3. Get reviews list
        $reviewData = $reviewService->getProductReviews($productId, $filters);

        json_response([
            'success'     => true,
            'product_id'  => $productId,
            'summary'     => $summary,
            'eligibility' => $eligibility,
            'data'        => $reviewData['reviews'],
            'pagination'  => $reviewData['pagination']
        ]);
    } catch (Throwable $e) {
        error_log('[Reviews API GET Error] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Lỗi máy chủ khi tải đánh giá sản phẩm.'], 500);
    }
}

if ($method === 'POST') {
    try {
        if (empty($_SESSION['user_id'])) {
            json_response(['success' => false, 'message' => 'Vui lòng đăng nhập để gửi đánh giá.'], 401);
        }

        $userId = (int)$_SESSION['user_id'];
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim((string)($_POST['comment'] ?? ''));
        $title = isset($_POST['title']) ? trim((string)$_POST['title']) : null;
        $qualityTag = isset($_POST['quality_tag']) ? trim((string)$_POST['quality_tag']) : null;

        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Mã sản phẩm không hợp lệ.'], 400);
        }

        if ($rating < 1 || $rating > 5) {
            json_response(['success' => false, 'message' => 'Vui lòng chọn số sao từ 1 đến 5.'], 400);
        }

        $uploadedFiles = !empty($_FILES['images']) ? $_FILES['images'] : [];

        $result = $reviewService->submitReview(
            $userId,
            $productId,
            $rating,
            $comment,
            $title,
            $qualityTag,
            $uploadedFiles
        );

        json_response($result, 201);
    } catch (InvalidArgumentException $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (RuntimeException $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 403);
    } catch (Throwable $e) {
        error_log('[Reviews API POST Error] ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Lỗi hệ thống khi gửi đánh giá. Vui lòng thử lại.'], 500);
    }
}

json_response(['success' => false, 'message' => 'Phương thức không được hỗ trợ.'], 405);
