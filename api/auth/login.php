<?php
declare(strict_types=1);
/** POST /api/auth/login.php */
require_once __DIR__ . '/../../core/bootstrap.php';
init_secure_session(); api_only_method('POST');
try {
    $pdo=getDBConnection(); $in=get_json_input();
    $email=strtolower(trim((string)($in['email']??'')));
    $password=(string)($in['password']??'');
    $requireAdmin=!empty($in['require_admin']);
    if($email===''||$password==='') json_response(['success'=>false,'message'=>'Vui lòng nhập email và mật khẩu.'],400);
    $st=$pdo->prepare("SELECT u.id,u.role_id,u.email,u.password_hash,u.full_name,u.phone,u.avatar,u.status,r.name AS role_name
                       FROM users u INNER JOIN roles r ON u.role_id=r.id WHERE LOWER(u.email)=:email LIMIT 1");
    $st->execute([':email'=>$email]); $user=$st->fetch();
    if(!$user || !password_verify($password,(string)$user['password_hash'])) json_response(['success'=>false,'message'=>'Email hoặc mật khẩu không chính xác.'],401);
    if(($user['status']??'')!=='active') json_response(['success'=>false,'message'=>'Tài khoản đang bị khóa hoặc chưa kích hoạt.'],403);
    if($requireAdmin && !in_array($user['role_name'],['admin','staff'],true)) json_response(['success'=>false,'message'=>'Tài khoản không có quyền truy cập quản trị.'],403);
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id']; $_SESSION['role_name']=$user['role_name'];
    $_SESSION['full_name']=$user['full_name']; $_SESSION['email']=$user['email']; $_SESSION['phone']=$user['phone']??'';
    json_response(['success'=>true,'message'=>'Đăng nhập thành công.','data'=>[
        'id'=>(int)$user['id'],'email'=>$user['email'],'fullName'=>$user['full_name'],
        'phone'=>$user['phone'],'avatar'=>$user['avatar'],'role'=>$user['role_name']
    ]]);
} catch (Throwable $e){ error_log('[Auth Login] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi máy chủ khi đăng nhập.'],500);} 