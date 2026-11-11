<?php
/**
 * API: Đăng nhập hệ thống (Dùng chung cho cả Khách hàng & Quản trị viên)
 * Endpoint: POST /api/auth/login.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST']);
    exit;
}

try {
    $pdo = getDBConnection();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $requireAdmin = !empty($input['require_admin']); // Đăng nhập từ trang Admin Portal

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ Email và Mật khẩu!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tìm người dùng theo Email
    $sql = "SELECT u.id, u.role_id, u.email, u.password_hash, u.full_name, u.phone, u.avatar, u.status,
                   r.name AS role_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Kiểm tra tài khoản và mật khẩu
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email hoặc mật khẩu không chính xác!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kiểm tra trạng thái tài khoản
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn đang bị khóa hoặc chưa kích hoạt.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Nếu đăng nhập vào Admin, kiểm tra quyền
    if ($requireAdmin && !in_array($user['role_name'], ['admin', 'staff'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Tài khoản của bạn không có quyền truy cập trang Quản trị!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Khởi tạo phiên làm việc (Session)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'];

    echo json_encode([
        'success' => true,
        'message' => 'Đăng nhập thành công!',
        'data' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'role' => $user['role_name']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi máy chủ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

