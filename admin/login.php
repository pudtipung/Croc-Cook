<?php
/**
 * หน้าเข้าสู่ระบบสำหรับผู้ดูแลระบบ (Admin Login)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// หากเข้าสู่ระบบอยู่แล้ว ให้เปลี่ยนเส้นทางไปยังหน้า Dashboard ทันที
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/../includes/db.php';

$errorMessage = '';
$successMessage = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $successMessage = 'คุณได้ออกจากระบบเรียบร้อยแล้ว';
}

// สร้าง CSRF Token สำหรับป้องกันการโจมตี
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$usernameInput = '';

// 2. จัดการเมื่อมีการ Submit ฟอร์มเข้าสู่ระบบ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    
    // ตรวจสอบ CSRF Token
    if (!hash_equals($_SESSION['admin_csrf_token'] ?? '', $postedToken)) {
        $errorMessage = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $usernameInput = trim($_POST['username'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        if ($usernameInput === '' || $passwordInput === '') {
            $errorMessage = 'กรุณากรอกทั้งชื่อผู้ใช้และรหัสผ่าน';
        } else {
            try {
                // ค้นหาบัญชีผู้ดูแลระบบจากตาราง admins ในฐานข้อมูล MySQL จริง
                $stmt = $pdo->prepare("SELECT id, username, password, name FROM admins WHERE username = :username LIMIT 1");
                $stmt->execute([':username' => $usernameInput]);
                $admin = $stmt->fetch();

                // 3. ตรวจสอบรหัสผ่านด้วย password_verify() กับ bcrypt hash ในฐานข้อมูล
                if ($admin && password_verify($passwordInput, $admin['password'])) {
                    // ป้องกัน Session Fixation
                    session_regenerate_id(true);

                    // 4. บันทึกข้อมูลลงใน Session ผู้ดูแลระบบ
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_login_time'] = time();

                    // รีเซ็ต CSRF token ใหม่
                    unset($_SESSION['admin_csrf_token']);

                    // เปลี่ยนเส้นทางไปยังหน้า Dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $errorMessage = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                }
            } catch (PDOException $e) {
                error_log("Admin Login Error: " . $e->getMessage());
                $errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบผู้ดูแลระบบ | มีอะไรในครัว? (What's in My Kitchen)</title>
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS สำหรับธีมสีส้มครีม -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    body {
        background: radial-gradient(circle at top right, #fff4ee 0%, var(--bg-cream) 70%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }
    .admin-login-card {
        background-color: #ffffff;
        border: 1.5px solid var(--border-subtle);
        border-radius: 24px;
        box-shadow: 0 15px 35px rgba(224, 93, 38, 0.1);
        width: 100%;
        max-width: 440px;
        overflow: hidden;
    }
    .admin-login-header {
        background: linear-gradient(135deg, #fff7f2 0%, #ffede2 100%);
        border-bottom: 1px solid var(--border-subtle);
        padding: 2.5rem 2rem 1.75rem;
        text-align: center;
    }
    .admin-avatar-box {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #ffffff;
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        box-shadow: 0 6px 18px rgba(224, 93, 38, 0.18);
        margin-bottom: 1rem;
    }
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(224, 93, 38, 0.15);
    }
    </style>
</head>
<body>

    <div class="admin-login-card">
        
        <!-- Header ส่วนหัวของฟอร์ม Login -->
        <div class="admin-login-header">
            <div class="admin-avatar-box">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1 class="h4 fw-bold text-dark mb-1">เข้าสู่ระบบผู้ดูแลระบบ</h1>
            <p class="text-muted small mb-0">ระบบจัดการหลังบ้าน “มีอะไรในครัว?”</p>
        </div>

        <!-- Body ส่วนเนื้อหาและช่องกรอกข้อมูล -->
        <div class="p-4 p-md-4">

            <!-- แจ้งเตือนข้อผิดพลาด (ถ้ามี) -->
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger border-0 rounded-3 py-2 px-3 d-flex align-items-center mb-3 small" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($errorMessage); ?></div>
                </div>
            <?php endif; ?>

            <!-- แจ้งเตือนเมื่อออกจากระบบสำเร็จ -->
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success border-0 rounded-3 py-2 px-3 d-flex align-items-center mb-3 small" role="alert">
                    <i class="bi bi-check-circle-fill fs-5 me-2 flex-shrink-0"></i>
                    <div><?php echo htmlspecialchars($successMessage); ?></div>
                </div>
            <?php endif; ?>

            <!-- ฟอร์มเข้าสู่ระบบ -->
            <form action="login.php" method="POST" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'] ?? ''); ?>">

                <!-- ช่องกรอกชื่อผู้ใช้ -->
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-dark">
                        <i class="bi bi-person me-1 text-primary"></i> ชื่อผู้ใช้ (Username)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               class="form-control border-start-0" 
                               placeholder="กรอกชื่อผู้ใช้..." 
                               value="<?php echo htmlspecialchars($usernameInput); ?>" 
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- ช่องกรอกรหัสผ่าน -->
                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-dark">
                        <i class="bi bi-key me-1 text-primary"></i> รหัสผ่าน (Password)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-control border-start-0 border-end-0" 
                               placeholder="กรอกรหัสผ่าน..." 
                               required>
                        <button class="btn btn-outline-secondary border-start-0 bg-white text-muted" 
                                type="button" 
                                id="togglePasswordBtn" 
                                title="แสดง/ซ่อนรหัสผ่าน">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- ปุ่มส่งข้อมูลเข้าสู่ระบบ -->
                <div class="mb-3">
                    <button type="submit" class="btn btn-kitchen-primary w-100 py-2 rounded-pill fw-semibold shadow-xs">
                        <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <!-- ลิงก์กลับสู่หน้าหลักของเว็บไซต์ -->
            <div class="text-center pt-3 border-top">
                <a href="../index.php" class="text-decoration-none small text-muted hover-primary">
                    <i class="bi bi-arrow-left me-1"></i> กลับสู่หน้าหลักเว็บไซต์
                </a>
            </div>

        </div>

    </div>

    <!-- สคริปต์สลับการแสดง/ซ่อนรหัสผ่าน -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (toggleBtn && passwordInput && toggleIcon) {
            toggleBtn.addEventListener('click', function () {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('bi-eye');
                    toggleIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('bi-eye-slash');
                    toggleIcon.classList.add('bi-eye');
                }
            });
        }
    });
    </script>

</body>
</html>

