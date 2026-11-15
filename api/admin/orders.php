<?php
declare(strict_types=1);
/**
 * Admin Orders API - list & update status with safe stock/voucher rollback on cancel
 */
require_once __DIR__ . '/../../core/bootstrap.php';

require_admin_auth();

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
        $where = []; $params = [];
        if ($status !== '' && $status !== 'all') { $where[] = 'order_status = :status'; $params[':status'] = $status; }
        $sql = "SELECT id, order_code, user_id, sender_name, sender_phone, is_anonymous,
                       receiver_name, receiver_phone, receiver_address, city,
                       delivery_date, delivery_time_slot, card_message, ribbon_message,
                       subtotal, shipping_fee, discount_amount, total_amount, payment_method, payment_status,
                       order_status, photo_proof, created_at
                FROM orders" . (!empty($where) ? ' WHERE '.implode(' AND ',$where) : '') . " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $orders = $stmt->fetchAll();
        if (!empty($orders)) {
            $ids = array_column($orders,'id');
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $it = $pdo->prepare("SELECT order_id, product_id, product_name, product_sku, unit_price, quantity, total_price FROM order_items WHERE order_id IN ($ph)");
            $it->execute($ids);
            $by = [];
            foreach ($it->fetchAll() as $row) { $by[$row['order_id']][] = $row; }
            foreach ($orders as &$o) { $o['items'] = $by[$o['id']] ?? []; $o['voucher_code'] = $o['voucher_code'] ?? null; }
        }
        json_response(['success'=>true,'count'=>count($orders),'data'=>$orders]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = get_json_input();
        $orderId = (int)($input['orderId'] ?? 0);
        $newStatus = trim((string)($input['status'] ?? ''));
        $photoProof = array_key_exists('photoProof',$input) ? trim((string)$input['photoProof']) : null;
        $note = trim((string)($input['note'] ?? 'Cập nhật trạng thái bởi quản trị viên'));
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        $valid = ['received','preparing','photo_ready','delivering','completed','cancelled'];
        if ($orderId <= 0 || !in_array($newStatus,$valid,true)) {
            json_response(['success'=>false,'message'=>'Mã đơn hàng hoặc trạng thái không hợp lệ.'],400);
        }

        $pdo->beginTransaction();
        $oStmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id FOR UPDATE");
        $oStmt->execute([':id'=>$orderId]);
        $order = $oStmt->fetch();
        if (!$order) { $pdo->rollBack(); json_response(['success'=>false,'message'=>'Đơn hàng không tồn tại.'],404); }
        $oldStatus = $order['order_status'];

        $updateSql = "UPDATE orders SET order_status = :status";
        $params = [':status'=>$newStatus, ':id'=>$orderId];
        if ($photoProof !== null && $photoProof !== '') { $updateSql .= ", photo_proof = :photo"; $params[':photo']=$photoProof; }
        if ($newStatus === 'completed' && ($order['payment_method'] ?? '') === 'cod') { $updateSql .= ", payment_status = 'paid'"; }
        $updateSql .= " WHERE id = :id";
        $pdo->prepare($updateSql)->execute($params);

        // On cancel: restore stock and voucher/budget once
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :oid");
            $itemsStmt->execute([':oid'=>$orderId]);
            $items = $itemsStmt->fetchAll();
            $invUpdate = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :q WHERE id = :pid");
            $invLog = $pdo->prepare("INSERT INTO inventory_logs (product_id,change_quantity,remaining_quantity,reason,order_id,user_id,note,created_at) VALUES (:pid,:chg,:rem,'cancel_refund',:oid,:uid,:note,NOW())");
            $remSel = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = :pid");
            foreach ($items as $it) {
                $invUpdate->execute([':q'=>(int)$it['quantity'], ':pid'=>(int)$it['product_id']]);
                try {
                    $remSel->execute([':pid'=>(int)$it['product_id']]);
                    $rem = (int)$remSel->fetchColumn();
                    $invLog->execute([':pid'=>(int)$it['product_id'], ':chg'=>(int)$it['quantity'], ':rem'=>$rem, ':oid'=>$orderId, ':uid'=>$adminId, ':note'=>'Hoàn kho do hủy đơn '.$order['order_code']]);
                } catch (Throwable $ignore) {}
            }

            // Refund voucher usage & budget if linked
            try {
                $vu = $pdo->prepare("SELECT vu.id, vu.voucher_id, vu.discount_amount, v.campaign_id FROM voucher_usages vu LEFT JOIN vouchers v ON v.id = vu.voucher_id WHERE vu.order_id = :oid AND vu.status = 'used'");
                $vu->execute([':oid'=>$orderId]);
                foreach ($vu->fetchAll() as $u) {
                    $pdo->prepare("UPDATE voucher_usages SET status='refunded' WHERE id=:id")->execute([':id'=>$u['id']]);
                    $pdo->prepare("UPDATE vouchers SET used_count = GREATEST(used_count - 1, 0) WHERE id = :vid")->execute([':vid'=>$u['voucher_id']]);
                    if (!empty($u['campaign_id']) && class_exists('BudgetService')) {
                        BudgetService::refundBudget($pdo, (int)$u['campaign_id'], $orderId, (float)$u['discount_amount']);
                    }
                }
            } catch (Throwable $ignore) {}
        }

        $pdo->prepare("INSERT INTO order_status_history (order_id,status,note,created_by) VALUES (:oid,:st,:note,:uid)")
            ->execute([':oid'=>$orderId, ':st'=>$newStatus, ':note'=>$note, ':uid'=>$adminId]);
        $pdo->commit();
        json_response(['success'=>true,'message'=>'Cập nhật trạng thái đơn hàng thành công.']);
    }

    json_response(['success'=>false,'message'=>'Phương thức HTTP không được hỗ trợ.'],405);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[Admin Orders Error] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Lỗi xử lý đơn hàng quản trị.'],500);
}