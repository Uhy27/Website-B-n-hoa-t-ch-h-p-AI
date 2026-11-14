<?php
/**
 * API Quản Trị Khuyến Mãi (Admin Promotions API)
 * GET:  Danh sách chiến dịch (campaigns) hoặc vouchers
 * POST: Tạo chiến dịch, duyệt, tạo voucher, tạm dừng chiến dịch
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
require_admin_auth();

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET — Danh sách chiến dịch hoặc vouchers
    // ================================================================
    if ($method === 'GET') {

        // --- Danh sách vouchers ---
        if (isset($_GET['vouchers']) && $_GET['vouchers'] == '1') {
            $sql = "SELECT v.id, v.campaign_id, v.code, v.description,
                           v.discount_type, v.discount_value, v.min_order_value, v.max_discount_amount,
                           v.usage_limit, v.used_count, v.per_user_limit,
                           v.start_at, v.end_at, v.is_active, v.created_at,
                           c.name AS campaign_name,
                           (v.usage_limit - v.used_count) AS remaining_usage
                    FROM vouchers v
                    LEFT JOIN campaigns c ON c.id = v.campaign_id
                    ORDER BY v.created_at DESC";

            $stmt = $pdo->query($sql);
            $vouchers = $stmt->fetchAll();

            json_response([
                'success' => true,
                'data'    => $vouchers
            ]);
        }

        // --- Danh sách chiến dịch ---
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[] = "cp.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT cp.id, cp.name, cp.slug, cp.description, cp.campaign_type,
                       cp.discount_type, cp.discount_value, cp.min_order_value, cp.max_discount_amount,
                       cp.budget_limit, cp.budget_used,
                       (cp.budget_limit - cp.budget_used) AS budget_remaining,
                       cp.min_margin, cp.start_at, cp.end_at,
                       cp.allow_stacking, cp.status, cp.approval_status,
                       cp.created_by, cp.created_at, cp.updated_at,
                       COUNT(DISTINCT v.id) AS voucher_count,
                       COALESCE(SUM(v.used_count), 0) AS total_usage
                FROM campaigns cp
                LEFT JOIN vouchers v ON v.campaign_id = cp.id
                {$whereClause}
                GROUP BY cp.id
                ORDER BY cp.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll();

        json_response([
            'success' => true,
            'data'    => $campaigns
        ]);
    }

    // ================================================================
    // POST — Các hành động: create_campaign, approve, create_voucher, pause_campaign
    // ================================================================
    if ($method === 'POST') {
        $input  = get_json_input();
        $action = isset($input['action']) ? trim($input['action']) : '';

        // -------------------------------------------------------
        // Tạo chiến dịch mới
        // -------------------------------------------------------
        if ($action === 'create_campaign') {
            $required = ['name', 'slug', 'campaign_type', 'discount_type', 'discount_value', 'start_at', 'end_at'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                    json_response(['success' => false, 'message' => "Trường '{$field}' là bắt buộc."], 400);
                }
            }

            // Kiểm tra slug trùng
            $checkSlug = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE slug = :slug");
            $checkSlug->execute([':slug' => trim($input['slug'])]);
            if ((int)$checkSlug->fetchColumn() > 0) {
                json_response(['success' => false, 'message' => 'Slug chiến dịch đã tồn tại.'], 409);
            }

            $sql = "INSERT INTO campaigns
                    (name, slug, description, campaign_type, discount_type, discount_value,
                     min_order_value, max_discount_amount, budget_limit, budget_used, min_margin,
                     start_at, end_at, allow_stacking, status, approval_status, created_by)
                    VALUES
                    (:name, :slug, :description, :campaign_type, :discount_type, :discount_value,
                     :min_order_value, :max_discount_amount, :budget_limit, :budget_used, :min_margin,
                     :start_at, :end_at, :allow_stacking, :status, :approval_status, :created_by)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name'               => trim($input['name']),
                ':slug'               => trim($input['slug']),
                ':description'        => isset($input['description']) ? trim($input['description']) : null,
                ':campaign_type'      => trim($input['campaign_type']),
                ':discount_type'      => trim($input['discount_type']),
                ':discount_value'     => (float)$input['discount_value'],
                ':min_order_value'    => isset($input['min_order_value']) ? (float)$input['min_order_value'] : 0,
                ':max_discount_amount'=> isset($input['max_discount_amount']) ? (float)$input['max_discount_amount'] : null,
                ':budget_limit'       => isset($input['budget_limit']) ? (float)$input['budget_limit'] : 0,
                ':budget_used'        => 0,
                ':min_margin'         => isset($input['min_margin']) ? (float)$input['min_margin'] : 20.00,
                ':start_at'           => trim($input['start_at']),
                ':end_at'             => trim($input['end_at']),
                ':allow_stacking'     => isset($input['allow_stacking']) ? (int)$input['allow_stacking'] : 0,
                ':status'             => isset($input['status']) ? trim($input['status']) : 'draft',
                ':approval_status'    => 'pending',
                ':created_by'         => $_SESSION['user_id'] ?? null,
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Ghi log submitted
            $logStmt = $pdo->prepare(
                "INSERT INTO promotion_approvals (campaign_id, admin_id, action, note)
                 VALUES (:cid, :aid, 'submitted', :note)"
            );
            $logStmt->execute([
                ':cid'  => $newId,
                ':aid'  => $_SESSION['user_id'] ?? 1,
                ':note' => 'Tạo chiến dịch mới: ' . trim($input['name']),
            ]);

            json_response([
                'success' => true,
                'message' => 'Tạo chiến dịch khuyến mãi thành công!',
                'data'    => ['id' => $newId]
            ], 201);
        }

        // -------------------------------------------------------
        // Duyệt / Từ chối chiến dịch (chỉ admin)
        // -------------------------------------------------------
        if ($action === 'approve') {
            // Kiểm tra quyền admin
            if (($_SESSION['role_name'] ?? '') !== 'admin') {
                json_response(['success' => false, 'message' => 'Chỉ Quản trị viên (admin) mới có quyền duyệt chiến dịch.'], 403);
            }

            $campaignId    = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
            $approveAction = isset($input['approve_action']) ? trim($input['approve_action']) : (isset($input['action_type']) ? trim($input['action_type']) : '');

            // Fallback: cũng hỗ trợ trường tên khác
            if ($approveAction === '' && isset($input['approval'])) {
                $approveAction = trim($input['approval']);
            }

            $note = isset($input['note']) ? trim($input['note']) : '';

            if ($campaignId <= 0) {
                json_response(['success' => false, 'message' => 'Thiếu tham số campaign_id.'], 400);
            }

            $validActions = ['approved', 'rejected'];
            if (!in_array($approveAction, $validActions, true)) {
                json_response([
                    'success' => false,
                    'message' => "Hành động không hợp lệ. Chỉ chấp nhận: 'approved' hoặc 'rejected'."
                ], 400);
            }

            // Kiểm tra chiến dịch tồn tại
            $check = $pdo->prepare("SELECT id, approval_status, status FROM campaigns WHERE id = :id");
            $check->execute([':id' => $campaignId]);
            $campaign = $check->fetch();

            if (!$campaign) {
                json_response(['success' => false, 'message' => 'Chiến dịch không tồn tại.'], 404);
            }

            $pdo->beginTransaction();

            // Cập nhật approval_status và status
            $newStatus = $approveAction === 'approved' ? 'active' : 'draft';
            $updateStmt = $pdo->prepare(
                "UPDATE campaigns SET approval_status = :approval, status = :status, updated_at = NOW() WHERE id = :id"
            );
            $updateStmt->execute([
                ':approval' => $approveAction,
                ':status'   => $newStatus,
                ':id'       => $campaignId,
            ]);

            // Ghi log duyệt
            $logStmt = $pdo->prepare(
                "INSERT INTO promotion_approvals (campaign_id, admin_id, action, note)
                 VALUES (:cid, :aid, :action, :note)"
            );
            $logStmt->execute([
                ':cid'    => $campaignId,
                ':aid'    => $_SESSION['user_id'] ?? 1,
                ':action' => $approveAction,
                ':note'   => $note !== '' ? $note : null,
            ]);

            $pdo->commit();

            $msgText = $approveAction === 'approved' ? 'Đã duyệt chiến dịch thành công!' : 'Đã từ chối chiến dịch.';
            json_response([
                'success' => true,
                'message' => $msgText
            ]);
        }

        // -------------------------------------------------------
        // Tạo voucher mới
        // -------------------------------------------------------
        if ($action === 'create_voucher') {
            $required = ['code', 'discount_type', 'discount_value', 'start_at', 'end_at'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                    json_response(['success' => false, 'message' => "Trường '{$field}' là bắt buộc."], 400);
                }
            }

            // Kiểm tra mã voucher trùng
            $checkCode = $pdo->prepare("SELECT COUNT(*) FROM vouchers WHERE code = :code");
            $checkCode->execute([':code' => strtoupper(trim($input['code']))]);
            if ((int)$checkCode->fetchColumn() > 0) {
                json_response(['success' => false, 'message' => 'Mã voucher đã tồn tại.'], 409);
            }

            $sql = "INSERT INTO vouchers
                    (campaign_id, code, description, discount_type, discount_value,
                     min_order_value, max_discount_amount, usage_limit, used_count,
                     per_user_limit, start_at, end_at, is_active)
                    VALUES
                    (:campaign_id, :code, :description, :discount_type, :discount_value,
                     :min_order_value, :max_discount_amount, :usage_limit, 0,
                     :per_user_limit, :start_at, :end_at, :is_active)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':campaign_id'        => isset($input['campaign_id']) ? (int)$input['campaign_id'] : null,
                ':code'               => strtoupper(trim($input['code'])),
                ':description'        => isset($input['description']) ? trim($input['description']) : null,
                ':discount_type'      => trim($input['discount_type']),
                ':discount_value'     => (float)$input['discount_value'],
                ':min_order_value'    => isset($input['min_order_value']) ? (float)$input['min_order_value'] : 0,
                ':max_discount_amount'=> isset($input['max_discount_amount']) ? (float)$input['max_discount_amount'] : null,
                ':usage_limit'        => isset($input['usage_limit']) ? (int)$input['usage_limit'] : 100,
                ':per_user_limit'     => isset($input['per_user_limit']) ? (int)$input['per_user_limit'] : 1,
                ':start_at'           => trim($input['start_at']),
                ':end_at'             => trim($input['end_at']),
                ':is_active'          => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            ]);

            $newId = (int)$pdo->lastInsertId();

            json_response([
                'success' => true,
                'message' => 'Tạo voucher thành công!',
                'data'    => ['id' => $newId]
            ], 201);
        }

        // -------------------------------------------------------
        // Tạm dừng chiến dịch
        // -------------------------------------------------------
        if ($action === 'pause_campaign') {
            $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
            if ($campaignId <= 0) {
                json_response(['success' => false, 'message' => 'Thiếu tham số campaign_id.'], 400);
            }

            $check = $pdo->prepare("SELECT id, status FROM campaigns WHERE id = :id");
            $check->execute([':id' => $campaignId]);
            $campaign = $check->fetch();

            if (!$campaign) {
                json_response(['success' => false, 'message' => 'Chiến dịch không tồn tại.'], 404);
            }

            if ($campaign['status'] === 'paused') {
                json_response(['success' => false, 'message' => 'Chiến dịch đã ở trạng thái tạm dừng.'], 400);
            }

            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("UPDATE campaigns SET status = 'paused', updated_at = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $campaignId]);

            // Ghi log
            $logStmt = $pdo->prepare(
                "INSERT INTO promotion_approvals (campaign_id, admin_id, action, note)
                 VALUES (:cid, :aid, 'paused', :note)"
            );
            $logStmt->execute([
                ':cid'  => $campaignId,
                ':aid'  => $_SESSION['user_id'] ?? 1,
                ':note' => isset($input['note']) ? trim($input['note']) : 'Tạm dừng chiến dịch',
            ]);

            $pdo->commit();

            json_response([
                'success' => true,
                'message' => 'Đã tạm dừng chiến dịch thành công!'
            ]);
        }

        // Không khớp action nào
        json_response([
            'success' => false,
            'message' => "Hành động '{$action}' không được hỗ trợ. Các hành động hợp lệ: create_campaign, approve, create_voucher, pause_campaign."
        ], 400);
    }

    json_response(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.'], 405);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[Admin Promotions Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi hệ thống khi xử lý khuyến mãi.'
    ], 500);
}
?>
