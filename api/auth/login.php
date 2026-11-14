<?php
/**
 * API: Đăng nhập hệ thống (Dùng chung cho cả Khách hàng & Quản trị viên)
 * Endpoint: POST /api/auth/login.php
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
    $email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $requireAdmin = !empty($input['require_admin']); // Đăng nhập từ trang Admin Portal

    if (empty($email) || empty($password)) {
        json_response(['success' => false, 'message' => 'Vui lòng nhập đầy đủ Email và Mật khẩu!'], 400);
    }

    // Tìm người dùng theo Email
    $sql = "SELECT u.id, u.role_id, u.email, u.password_hash, u.full_name, u.phone, u.avatar, u.status,
                   r.name AS role_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE LOWER(u.email) = :email
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Kiểm tra tài khoản và mật khẩu
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'Email hoặc mật khẩu không chính xác!'], 401);
    }

    // Kiểm tra trạng thái tài khoản
    if ($user['status'] !== 'active') {
        json_response(['success' => false, 'message' => 'Tài khoản của bạn đang bị khóa hoặc chưa kích hoạt.'], 403);
    }

    // Nếu đăng nhập vào Admin, kiểm tra quyền
    if ($requireAdmin && !in_array($user['role_name'], ['admin', 'staff'])) {
        json_response(['success' => false, 'message' => 'Tài khoản của bạn không có quyền truy cập trang Quản trị!'], 403);
    }

    // Tái tạo Session ID chống Session Fixation
    session_regenerate_id(true);

    // Khởi tạo phiên làm việc (Session)
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'];

    json_response([
        'success' => true,
        'message' => 'Đăng nhập thành công!',
        'data' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'role' => $user['role_name']
        ]
    ]);

} catch (Exception $e) {
    error_log("[Auth Login Error] " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Lỗi máy chủ trong quá trình đăng nhập.'
    ], 500);
}
?>