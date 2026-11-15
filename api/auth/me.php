<?php
declare(strict_types=1);
/** GET /api/auth/me.php */
require_once __DIR__ . '/../../core/bootstrap.php';
init_secure_session();
if (empty($_SESSION['user_id'])) json_response(['success'=>false,'isLoggedIn'=>false,'message'=>'Chưa đăng nhập']);
try {
    $pdo=getDBConnection();
    $st=$pdo->prepare("SELECT u.id,u.email,u.full_name,u.phone,u.avatar,r.name AS role FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=:id AND u.status='active' LIMIT 1");
    $st->execute([':id'=>(int)$_SESSION['user_id']]); $u=$st->fetch();
    if(!$u){ $_SESSION=[]; session_destroy(); json_response(['success'=>false,'isLoggedIn'=>false,'message'=>'Phiên đăng nhập không hợp lệ.']); }
    json_response(['success'=>true,'isLoggedIn'=>true,'data'=>['id'=>(int)$u['id'],'email'=>$u['email'],'fullName'=>$u['full_name'],'phone'=>$u['phone'],'avatar'=>$u['avatar'],'role'=>$u['role']]]);
} catch (Throwable $e){ error_log('[Auth Me] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi kiểm tra phiên.'],500);} 