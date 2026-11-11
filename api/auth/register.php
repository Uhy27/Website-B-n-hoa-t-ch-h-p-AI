<?php
/**
 * API: Đăng ký tài khoản khách hàng mới
 * Endpoint: POST /api/auth/register.php
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
    $fullName = isset($input['fullName']) ? trim($input['fullName']) : '';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';
    $email = isset($input['email']) ? strtolower(trim($input['email'])) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ Họ tên, Email và Mật khẩu!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Định dạng Email không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mật khẩu phải chứa ít nhất 6 ký tự!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kiểm tra trùng email
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email này đã được sử dụng. Vui lòng đăng nhập hoặc dùng email khác!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Băm mật khẩu chuẩn bảo mật BCRYPT
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Mặc định khách hàng là role_id = 3 (customer)
    $sql = "INSERT INTO users (role_id, email, password_hash, full_name, phone, status)
            VALUES (3, :email, :password_hash, :full_name, :phone, 'active')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':full_name' => $fullName,
        ':phone' => $phone
    ]);

    $userId = $pdo->lastInsertId();

    // Tự động đăng nhập luôn sau khi tạo tài khoản thành công
    $_SESSION['user_id'] = $userId;
    $_SESSION['role_name'] = 'customer';
    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;

    echo json_encode([
        'success' => true,
        'message' => 'Đăng ký tài khoản thành công!',
        'data' => [
            'id' => $userId,
            'email' => $email,
            'fullName' => $fullName,
            'phone' => $phone,
            'role' => 'customer'
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi tạo tài khoản: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
