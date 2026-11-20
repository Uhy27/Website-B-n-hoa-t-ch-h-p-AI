<?php
declare(strict_types=1);

/**
 * ReviewService
 * Handles Product Reviews, Real Photo Uploads, Rating Synchronization,
 * Order Verification and Admin Moderation for Bloomora Florist.
 */
class ReviewService {
    private PDO $pdo;
    private string $uploadDir;
    private string $uploadPublicPath;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? getDBConnection();
        $this->uploadDir = __DIR__ . '/../../uploads/reviews/';
        $this->uploadPublicPath = 'uploads/reviews/';

        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Get review summary stats for a product (Approved reviews only)
     */
    public function getProductReviewSummary(int $productId): array {
        $sql = "SELECT 
                    COUNT(*) as total_reviews,
                    COALESCE(AVG(rating), 5.0) as average_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as stars_5,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as stars_4,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as stars_3,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as stars_2,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as stars_1
                FROM reviews 
                WHERE product_id = :pid AND status = 'approved'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($row['total_reviews'] ?? 0);
        $avg = round((float)($row['average_rating'] ?? 5.0), 1);

        // Count reviews with images
        $imgSql = "SELECT COUNT(DISTINCT r.id) as with_images
                   FROM reviews r
                   INNER JOIN review_images ri ON r.id = ri.review_id
                   WHERE r.product_id = :pid AND r.status = 'approved' AND ri.image_status = 'active'";
        $imgStmt = $this->pdo->prepare($imgSql);
        $imgStmt->execute([':pid' => $productId]);
        $imgCount = (int)($imgStmt->fetchColumn() ?: 0);

        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $cnt = (int)($row["stars_$i"] ?? 0);
            $pct = $total > 0 ? round(($cnt / $total) * 100) : 0;
            $breakdown[$i] = [
                'count' => $cnt,
                'percentage' => $pct
            ];
        }

        return [
            'total_reviews'     => $total,
            'average_rating'    => $avg,
            'stars_breakdown'   => $breakdown,
            'with_images_count' => $imgCount,
        ];
    }

    /**
     * Get approved reviews for product detail page with filtering, sorting, pagination
     */
    public function getProductReviews(int $productId, array $filters = []): array {
        $rating = isset($filters['rating']) && is_numeric($filters['rating']) ? (int)$filters['rating'] : null;
        $hasImage = !empty($filters['has_image']);
        $sort = $filters['sort'] ?? 'priority'; // priority, newest, highest, lowest
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(20, (int)($filters['limit'] ?? 5)));
        $offset = ($page - 1) * $limit;

        $where = ["r.product_id = :pid", "r.status = 'approved'"];
        $params = [':pid' => $productId];

        if ($rating !== null && $rating >= 1 && $rating <= 5) {
            $where[] = "r.rating = :rating";
            $params[':rating'] = $rating;
        }

        if ($hasImage) {
            $where[] = "EXISTS (SELECT 1 FROM review_images ri WHERE ri.review_id = r.id AND ri.image_status = 'active')";
        }

        $whereClause = implode(' AND ', $where);

        // Order by
        switch ($sort) {
            case 'highest':
                $orderBy = "r.rating DESC, r.created_at DESC";
                break;
            case 'lowest':
                $orderBy = "r.rating ASC, r.created_at DESC";
                break;
            case 'newest':
                $orderBy = "r.created_at DESC";
                break;
            case 'priority':
            default:
                // Prioritize: 1. Has photos -> 2. Verified purchase -> 3. Newest
                $orderBy = "(SELECT COUNT(*) FROM review_images ri WHERE ri.review_id = r.id AND ri.image_status = 'active') DESC, r.is_verified_purchase DESC, r.created_at DESC";
                break;
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM reviews r WHERE $whereClause";
        $cStmt = $this->pdo->prepare($countSql);
        $cStmt->execute($params);
        $totalItems = (int)$cStmt->fetchColumn();
        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 1;

        // Fetch paginated reviews
        $sql = "SELECT r.id, r.product_id, r.order_id, r.user_id, r.customer_name, r.customer_avatar,
                       r.rating, r.title, r.comment, r.flower_quality_tag, r.is_verified_purchase,
                       r.created_at, o.order_code
                FROM reviews r
                LEFT JOIN orders o ON r.order_id = o.id
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($reviews)) {
            $reviewIds = array_column($reviews, 'id');
            $inClause = implode(',', array_fill(0, count($reviewIds), '?'));

            // Fetch images
            $imgStmt = $this->pdo->prepare("SELECT review_id, image_path FROM review_images WHERE review_id IN ($inClause) AND image_status = 'active' ORDER BY id ASC");
            $imgStmt->execute($reviewIds);
            $imagesByReview = [];
            foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $imgRow) {
                $imagesByReview[$imgRow['review_id']][] = $imgRow['image_path'];
            }

            // Fetch shop replies
            $replyStmt = $this->pdo->prepare("SELECT review_id, staff_name, reply_content, created_at FROM review_replies WHERE review_id IN ($inClause) ORDER BY id ASC");
            $replyStmt->execute($reviewIds);
            $repliesByReview = [];
            foreach ($replyStmt->fetchAll(PDO::FETCH_ASSOC) as $replyRow) {
                $repliesByReview[$replyRow['review_id']] = $replyRow;
            }

            foreach ($reviews as &$r) {
                $r['id'] = (int)$r['id'];
                $r['rating'] = (int)$r['rating'];
                $r['is_verified_purchase'] = (bool)$r['is_verified_purchase'];
                $r['images'] = $imagesByReview[$r['id']] ?? [];
                $r['reply'] = $repliesByReview[$r['id']] ?? null;
                $r['created_at_formatted'] = $this->timeElapsedString($r['created_at']);
            }
        }

        return [
            'reviews'     => $reviews,
            'pagination'  => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total_items'  => $totalItems,
                'total_pages'  => $totalPages,
                'has_more'     => $page < $totalPages
            ]
        ];
    }

    /**
     * Check if a logged-in user is eligible to review a product
     * Rule: User must have an order containing this product that is in 'completed' status, and hasn't reviewed it yet.
     */
    public function checkUserReviewEligibility(?int $userId, int $productId): array {
        if (empty($userId)) {
            return [
                'can_review' => false,
                'reason' => 'login_required',
                'message' => 'Vui lòng đăng nhập để đánh giá sản phẩm bạn đã mua.'
            ];
        }

        // Check if user has an order containing this product with status = 'completed'
        $sql = "SELECT o.id, o.order_code, o.order_status, o.created_at
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                WHERE o.user_id = :uid 
                  AND oi.product_id = :pid 
                  AND o.order_status = 'completed'
                ORDER BY o.id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        $completedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$completedOrder) {
            // Check if there is an order in another status (delivering/preparing)
            $pendingOrderStmt = $this->pdo->prepare("SELECT o.id, o.order_status FROM orders o INNER JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = :uid AND oi.product_id = :pid LIMIT 1");
            $pendingOrderStmt->execute([':uid' => $userId, ':pid' => $productId]);
            $pendingOrder = $pendingOrderStmt->fetch(PDO::FETCH_ASSOC);

            if ($pendingOrder) {
                return [
                    'can_review' => false,
                    'reason' => 'order_not_completed',
                    'message' => 'Đơn hoa của bạn đang được tiến hành. Bạn có thể gửi đánh giá sau khi đơn hàng được giao thành công!'
                ];
            }

            return [
                'can_review' => false,
                'reason' => 'not_purchased',
                'message' => 'Chỉ khách hàng đã đặt mua và nhận hoa thành công mới có thể gửi đánh giá xác minh.'
            ];
        }

        $orderId = (int)$completedOrder['id'];

        // Check if user has already reviewed this product for this order
        $revStmt = $this->pdo->prepare("SELECT id, rating, comment, status, created_at FROM reviews WHERE order_id = :oid AND product_id = :pid AND user_id = :uid");
        $revStmt->execute([':oid' => $orderId, ':pid' => $productId, ':uid' => $userId]);
        $existingReview = $revStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingReview) {
            $statusText = [
                'pending' => 'đang chờ quản trị viên duyệt',
                'approved' => 'đã được duyệt và xuất bản',
                'rejected' => 'đã được xử lý',
                'hidden' => 'đang được tạm ẩn'
            ][$existingReview['status']] ?? 'đã ghi nhận';

            return [
                'can_review' => false,
                'reason' => 'already_reviewed',
                'message' => "Bạn đã gửi đánh giá cho sản phẩm này theo đơn hàng #{$completedOrder['order_code']} ({$statusText}).",
                'existing_review' => $existingReview
            ];
        }

        return [
            'can_review' => true,
            'reason' => 'eligible',
            'order_id' => $orderId,
            'order_code' => $completedOrder['order_code'],
            'message' => "Đơn hàng #{$completedOrder['order_code']} đủ điều kiện gửi đánh giá xác minh."
        ];
    }

    /**
     * Submit a customer review with safe multi-image upload
     */
    public function submitReview(
        int $userId,
        int $productId,
        int $rating,
        string $comment,
        ?string $title = null,
        ?string $qualityTag = null,
        array $uploadedFiles = []
    ): array {
        // 1. Validate rating
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Số sao đánh giá phải từ 1 đến 5 sao.');
        }

        // 2. Verify eligibility at backend
        $eligibility = $this->checkUserReviewEligibility($userId, $productId);
        if (!$eligibility['can_review']) {
            throw new RuntimeException($eligibility['message']);
        }

        $orderId = (int)$eligibility['order_id'];

        // 3. Fetch user info
        $userStmt = $this->pdo->prepare("SELECT full_name, avatar FROM users WHERE id = :id");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $customerName = !empty($user['full_name']) ? trim($user['full_name']) : 'Khách hàng Bloomora';
        $customerAvatar = $user['avatar'] ?? null;

        // 4. Sanitize inputs
        $title = $title !== null ? sanitize_string($title, 150) : null;
        $comment = sanitize_string($comment, 2000);
        $qualityTag = $qualityTag !== null ? sanitize_string($qualityTag, 100) : null;

        // 5. Safe Image Validation & Upload (Max 5 images)
        $savedImagePaths = [];
        if (!empty($uploadedFiles['name'])) {
            $savedImagePaths = $this->processUploadedImages($uploadedFiles);
        }

        // 6. Insert review inside transaction
        $this->pdo->beginTransaction();
        try {
            $insertSql = "INSERT INTO reviews (
                            product_id, order_id, user_id, customer_name, customer_avatar,
                            rating, title, comment, flower_quality_tag, status, is_verified_purchase
                          ) VALUES (
                            :pid, :oid, :uid, :cname, :cavatar,
                            :rating, :title, :comment, :qtag, 'pending', 1
                          )";
            $stmt = $this->pdo->prepare($insertSql);
            $stmt->execute([
                ':pid'     => $productId,
                ':oid'     => $orderId,
                ':uid'     => $userId,
                ':cname'   => $customerName,
                ':cavatar' => $customerAvatar,
                ':rating'  => $rating,
                ':title'   => $title,
                ':comment' => $comment,
                ':qtag'    => $qualityTag
            ]);

            $reviewId = (int)$this->pdo->lastInsertId();

            // Insert image records
            if (!empty($savedImagePaths)) {
                $imgStmt = $this->pdo->prepare("INSERT INTO review_images (review_id, image_path, image_status) VALUES (:rid, :path, 'active')");
                foreach ($savedImagePaths as $path) {
                    $imgStmt->execute([
                        ':rid'  => $reviewId,
                        ':path' => $path
                    ]);
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'review_id' => $reviewId,
                'status' => 'pending',
                'message' => 'Cảm ơn bạn đã chia sẻ trải nghiệm cùng Bloomora. Đánh giá của bạn đang chờ kiểm duyệt.'
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            // Clean up uploaded files if DB insert failed
            foreach ($savedImagePaths as $relPath) {
                $full = __DIR__ . '/../../' . $relPath;
                if (is_file($full)) @unlink($full);
            }
            throw $e;
        }
    }

    /**
     * Process and validate up to 5 uploaded review images
     */
    private function processUploadedImages(array $files): array {
        $saved = [];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        $count = min(count($names), 5); // Max 5 images

        for ($i = 0; $i < $count; $i++) {
            if ($errors[$i] !== UPLOAD_ERR_OK || empty($tmpNames[$i]) || !is_uploaded_file($tmpNames[$i])) {
                continue;
            }

            if ($sizes[$i] > $maxFileSize) {
                throw new InvalidArgumentException("Ảnh {$names[$i]} vượt quá dung lượng tối đa cho phép (5MB).");
            }

            // Verify real MIME type using finfo
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpNames[$i]);

            if (!isset($allowedMimes[$mime])) {
                throw new InvalidArgumentException("Định dạng file {$names[$i]} không hợp lệ. Chỉ chấp nhận JPG, PNG, WEBP.");
            }

            // Verify image contents with getimagesize
            $imageInfo = @getimagesize($tmpNames[$i]);
            if ($imageInfo === false) {
                throw new InvalidArgumentException("File {$names[$i]} không phải là ảnh hợp lệ.");
            }

            $ext = $allowedMimes[$mime];
            $newFileName = 'rev_' . bin2hex(random_bytes(10)) . '_' . time() . '.' . $ext;
            $destination = $this->uploadDir . $newFileName;

            if (!move_uploaded_file($tmpNames[$i], $destination)) {
                throw new RuntimeException("Không thể lưu ảnh {$names[$i]}. Vui lòng thử lại.");
            }

            $saved[] = $this->uploadPublicPath . $newFileName;
        }

        return $saved;
    }

    /**
     * Recalculate and synchronize rating and reviews_count on products table
     */
    public function syncProductRating(int $productId): void {
        $sql = "SELECT 
                    COUNT(*) as total_count,
                    COALESCE(AVG(rating), 5.0) as avg_rating
                FROM reviews 
                WHERE product_id = :pid AND status = 'approved'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pid' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $cnt = (int)($row['total_count'] ?? 0);
        $avg = $cnt > 0 ? round((float)$row['avg_rating'], 2) : 5.0;

        $update = $this->pdo->prepare("UPDATE products SET rating = :rating, reviews_count = :cnt WHERE id = :pid");
        $update->execute([
            ':rating' => $avg,
            ':cnt'    => $cnt,
            ':pid'    => $productId
        ]);
    }

    /**
     * Admin: Get all reviews with filters, pagination and order context
     */
    public function getAdminReviews(array $filters = []): array {
        $status = isset($filters['status']) && $filters['status'] !== 'all' ? trim((string)$filters['status']) : '';
        $rating = isset($filters['rating']) && is_numeric($filters['rating']) ? (int)$filters['rating'] : null;
        $keyword = trim((string)($filters['q'] ?? ''));
        $productId = !empty($filters['product_id']) ? (int)$filters['product_id'] : null;
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(50, (int)($filters['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if ($status !== '' && in_array($status, ['pending', 'approved', 'hidden', 'rejected'], true)) {
            $where[] = "r.status = :status";
            $params[':status'] = $status;
        }

        if ($rating !== null && $rating >= 1 && $rating <= 5) {
            $where[] = "r.rating = :rating";
            $params[':rating'] = $rating;
        }

        if ($productId !== null && $productId > 0) {
            $where[] = "r.product_id = :pid";
            $params[':pid'] = $productId;
        }

        if ($keyword !== '') {
            $where[] = "(r.customer_name LIKE :kw1 OR r.comment LIKE :kw2 OR p.name LIKE :kw3 OR o.order_code LIKE :kw4)";
            $kw = '%' . $keyword . '%';
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
        }

        $whereClause = implode(' AND ', $where);

        // Stats summary for admin
        $statsSql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) as hidden,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                     FROM reviews";
        $statsStmt = $this->pdo->query($statsSql);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        // Total matching
        $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews r LEFT JOIN products p ON r.product_id = p.id LEFT JOIN orders o ON r.order_id = o.id WHERE $whereClause");
        $cStmt->execute($params);
        $totalItems = (int)$cStmt->fetchColumn();
        $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 1;

        // Query reviews
        $sql = "SELECT r.id, r.product_id, r.order_id, r.user_id, r.customer_name, r.rating,
                       r.title, r.comment, r.flower_quality_tag, r.status, r.is_verified_purchase,
                       r.rejection_reason, r.moderated_at, r.created_at,
                       p.name AS product_name, p.thumbnail AS product_image, p.sku AS product_sku,
                       o.order_code, o.order_status, o.total_amount,
                       u.email AS customer_email, u.phone AS customer_phone,
                       m.full_name AS moderator_name
                FROM reviews r
                INNER JOIN products p ON r.product_id = p.id
                INNER JOIN orders o ON r.order_id = o.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users m ON r.moderated_by = m.id
                WHERE $whereClause
                ORDER BY (r.status = 'pending') DESC, r.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($reviews)) {
            $ids = array_column($reviews, 'id');
            $inClause = implode(',', array_fill(0, count($ids), '?'));

            // Images
            $imgStmt = $this->pdo->prepare("SELECT review_id, image_path FROM review_images WHERE review_id IN ($inClause)");
            $imgStmt->execute($ids);
            $imgs = [];
            foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $imgs[$row['review_id']][] = $row['image_path'];
            }

            // Replies
            $replyStmt = $this->pdo->prepare("SELECT review_id, staff_name, reply_content, created_at FROM review_replies WHERE review_id IN ($inClause)");
            $replyStmt->execute($ids);
            $replies = [];
            foreach ($replyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $replies[$row['review_id']] = $row;
            }

            foreach ($reviews as &$r) {
                $r['id'] = (int)$r['id'];
                $r['rating'] = (int)$r['rating'];
                $r['images'] = $imgs[$r['id']] ?? [];
                $r['reply'] = $replies[$r['id']] ?? null;
            }
        }

        return [
            'stats'      => $stats,
            'reviews'    => $reviews,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total_items'  => $totalItems,
                'total_pages'  => $totalPages
            ]
        ];
    }

    /**
     * Admin: Moderate a review (approve, hide, reject)
     */
    public function moderateReview(int $reviewId, int $adminId, string $action, ?string $reason = null): bool {
        if (!in_array($action, ['approve', 'hide', 'reject'], true)) {
            throw new InvalidArgumentException('Hành động kiểm duyệt không hợp lệ.');
        }

        $statusMap = [
            'approve' => 'approved',
            'hide'    => 'hidden',
            'reject'  => 'rejected'
        ];
        $newStatus = $statusMap[$action];

        $this->pdo->beginTransaction();
        try {
            // Get current review
            $stmt = $this->pdo->prepare("SELECT product_id, status FROM reviews WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $reviewId]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$review) {
                $this->pdo->rollBack();
                return false;
            }

            $update = $this->pdo->prepare("UPDATE reviews SET 
                                            status = :status, 
                                            rejection_reason = :reason, 
                                            moderated_by = :mid, 
                                            moderated_at = NOW() 
                                          WHERE id = :id");
            $update->execute([
                ':status' => $newStatus,
                ':reason' => $action === 'reject' ? sanitize_string($reason ?? 'Nội dung không phù hợp với tiêu chuẩn đánh giá.', 255) : null,
                ':mid'    => $adminId,
                ':id'     => $reviewId
            ]);

            // Log audit
            $audit = $this->pdo->prepare("INSERT INTO review_audit_logs (review_id, admin_id, action, note) VALUES (:rid, :aid, :act, :note)");
            $audit->execute([
                ':rid'  => $reviewId,
                ':aid'  => $adminId,
                ':act'  => $newStatus,
                ':note' => $reason
            ]);

            // Sync product rating
            $this->syncProductRating((int)$review['product_id']);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Admin: Reply to a review (Official Shop Response)
     */
    public function replyReview(int $reviewId, int $adminId, string $replyContent): array {
        $content = sanitize_string($replyContent, 1000);
        if ($content === '') {
            throw new InvalidArgumentException('Nội dung phản hồi không được để trống.');
        }

        // Get admin name
        $uStmt = $this->pdo->prepare("SELECT full_name FROM users WHERE id = :id");
        $uStmt->execute([':id' => $adminId]);
        $staffName = $uStmt->fetchColumn() ?: 'Bloomora Florist';

        $this->pdo->beginTransaction();
        try {
            // Insert or update reply
            $sql = "INSERT INTO review_replies (review_id, user_id, staff_name, reply_content, updated_at)
                    VALUES (:rid, :uid, :name, :content, NOW())
                    ON DUPLICATE KEY UPDATE 
                        user_id = VALUES(user_id),
                        staff_name = VALUES(staff_name),
                        reply_content = VALUES(reply_content),
                        updated_at = NOW()";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':rid'     => $reviewId,
                ':uid'     => $adminId,
                ':name'    => $staffName,
                ':content' => $content
            ]);

            // Audit log
            $audit = $this->pdo->prepare("INSERT INTO review_audit_logs (review_id, admin_id, action, note) VALUES (:rid, :aid, 'replied', :note)");
            $audit->execute([
                ':rid'  => $reviewId,
                ':aid'  => $adminId,
                ':note' => mb_substr($content, 0, 100, 'UTF-8')
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'staff_name' => $staffName,
                'reply_content' => $content,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function timeElapsedString(string $datetime): string {
        $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $ago = new DateTime($datetime, new DateTimeZone('Asia/Ho_Chi_Minh'));
        $diff = $now->diff($ago);

        if ($diff->y > 0) return $diff->y . ' năm trước';
        if ($diff->m > 0) return $diff->m . ' tháng trước';
        if ($diff->d > 0) {
            return $diff->d === 1 ? 'Hôm qua' : $diff->d . ' ngày trước';
        }
        if ($diff->h > 0) return $diff->h . ' giờ trước';
        if ($diff->i > 0) return $diff->i . ' phút trước';
        return 'Vừa xong';
    }
}
