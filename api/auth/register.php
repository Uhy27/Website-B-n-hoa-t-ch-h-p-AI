<?php
declare(strict_types=1);
/** POST /api/auth/register.php */
require_once __DIR__ . '/../../core/bootstrap.php';
init_secure_session(); api_only_method('POST');
try {
    $pdo=getDBConnection(); $in=get_json_input();
    $fullName=trim((string)($in['fullName']??'')); $phone=trim((string)($in['phone']??''));
    $email=strtolower(trim((string)($in['email']??''))); $password=(string)($in['password']??'');
    if($fullName===''||$email===''||$password==='') json_response(['success'=>false,'message'=>'Vui lòng điền đầy đủ họ tên, email và mật khẩu.'],400);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) json_response(['success'=>false,'message'=>'Định dạng email không hợp lệ.'],400);
    if(mb_strlen($password)<6) json_response(['success'=>false,'message'=>'Mật khẩu phải có ít nhất 6 ký tự.'],400);
    $chk=$pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email)=:e'); $chk->execute([':e'=>$email]);
    if((int)$chk->fetchColumn()>0) json_response(['success'=>false,'message'=>'Email này đã được đăng ký.'],400);
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $roleId=(int)($pdo->query("SELECT id FROM roles WHERE name='customer' LIMIT 1")->fetchColumn() ?: 3);
    $avatar='https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80';
    $ins=$pdo->prepare("INSERT INTO users (role_id,email,password_hash,full_name,phone,avatar,status) VALUES (:rid,:e,:p,:n,:ph,:a,'active')");
    $ins->execute([':rid'=>$roleId,':e'=>$email,':p'=>$hash,':n'=>$fullName,':ph'=>$phone,':a'=>$avatar]);
    $id=(int)$pdo->lastInsertId();
    session_regenerate_id(true);
    $_SESSION=['user_id'=>$id,'role_name'=>'customer','full_name'=>$fullName,'email'=>$email,'phone'=>$phone];
    json_response(['success'=>true,'message'=>'Đăng ký tài khoản thành công.','data'=>['id'=>$id,'email'=>$email,'fullName'=>$fullName,'phone'=>$phone,'avatar'=>$avatar,'role'=>'customer']]);
} catch (Throwable $e){ error_log('[Auth Register] '.$e->getMessage()); json_response(['success'=>false,'message'=>'Lỗi tạo tài khoản. Vui lòng thử lại.'],500);} 