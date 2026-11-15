<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/bootstrap.php';
require_admin_auth();
try {
    $pdo=getDBConnection(); $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET'){
        $page=max(1,(int)($_GET['page']??1)); $limit=max(1,min(100,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
        $search=trim((string)($_GET['search']??'')); $where=['u.role_id=3']; $params=[];
        if($search!==''){ $where[]='(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)'; $params=[':s1'=>"%$search%",':s2'=>"%$search%",':s3'=>"%$search%"]; }
        $w='WHERE '.implode(' AND ',$where);
        $c=$pdo->prepare("SELECT COUNT(*) FROM users u $w"); $c->execute($params); $total=(int)$c->fetchColumn();
        $sql="SELECT u.id,u.email,u.full_name,u.phone,u.avatar,u.status,u.created_at,u.updated_at,
                     COUNT(o.id) AS total_orders,
                     COALESCE(SUM(CASE WHEN o.order_status!='cancelled' THEN o.total_amount ELSE 0 END),0) AS total_spent
              FROM users u LEFT JOIN orders o ON o.user_id=u.id $w GROUP BY u.id ORDER BY u.created_at DESC LIMIT :l OFFSET :o";
        $st=$pdo->prepare($sql); foreach($params as $k=>$v) $st->bindValue($k,$v); $st->bindValue(':l',$limit,PDO::PARAM_INT); $st->bindValue(':o',$offset,PDO::PARAM_INT); $st->execute();
        json_response(['success'=>true,'data'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'items'=>$st->fetchAll()]]);
    }
    if($method==='PUT'){
        $id=(int)($_GET['id']??0); $in=get_json_input(); $status=trim((string)($in['status']??''));
        if($id<=0 || !in_array($status,['active','inactive','banned'],true)) json_response(['success'=>false,'message'=>'Dữ liệu không hợp lệ.'],400);
        $st=$pdo->prepare("UPDATE users SET status=:s WHERE id=:id AND role_id=3"); $st->execute([':s'=>$status,':id'=>$id]);
        json_response(['success'=>true,'message'=>'Cập nhật trạng thái khách hàng thành công.']);
    }
    json_response(['success'=>false,'message'=>'Phương thức HTTP không được hỗ trợ.'],405);
} catch (Throwable $e){ error_log('[Admin Customers] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi hệ thống khi xử lý khách hàng.'],500);} 