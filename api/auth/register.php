<?php
/**
 * API: Đăng ký tài khoản khách hàng mới
 * Endpoint: POST /api/auth/register.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

init_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST'], 405);
}

try {
    $pdo = getDBConnection();

    $input = get_json_input();
    $fullName = isset($input['fullName']) ? trim($input['fullName']) : '';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';
    $email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        json_response(['success' => false, 'message' => 'Vui lòng điền đầy đủ Họ tên, Email và Mật khẩu!'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success' => false, 'message' => 'Định dạng Email không hợp lệ!'], 400);
    }

    if (mb_strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Mật khẩu phải chứa ít nhất 6 ký tự!'], 400);
    }

    // Kiểm tra trùng email
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = :email");
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetchColumn() > 0) {
        json_response(['success' => false, 'message' => 'Email này đã được đăng ký tài khoản trên hệ thống!'], 400);
    }

    // Mã hóa mật khẩu bảo mật
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Lấy role_id của customer (role_id = 3)
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn() ?: 3;

    // Tạo avatar mặc định
    $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80';

    $insertSql = "INSERT INTO users (role_id, email, password_hash, full_name, phone, avatar, status)
                  VALUES (:role_id, :email, :password_hash, :full_name, :phone, :avatar, 'active')";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':role_id' => $roleId,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':full_name' => $fullName,
        ':phone' => $phone,
        ':avatar' => $avatar
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    // Tái tạo Session ID và tự động đăng nhập sau khi đăng ký
    session_regenerate_id(true);
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['role_name'] = 'customer';
    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;

    json_response([
        'success' => true,
        'message' => 'Đăng ký tài khoản thành công!',
        'data' => [
            'id' => $newUserId,
            'email' => $email,
            'fullName' => $fullName,
            'phone' => $phone,
            'avatar' => $avatar,
            'role' => 'customer'
        ]
    ]);

} catch (Exception $e) {
    error_log("[Auth Register Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi tạo tài khoản. Vui lòng thử lại sau.'
    ], 500);
}
?>