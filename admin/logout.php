<?php
/**
 * สคริปต์ออกจากระบบสำหรับผู้ดูแลระบบ (Admin Logout)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ล้างตัวแปร Session ทั้งหมด
$_SESSION = [];

// 2. ลบ Cookie Session ของเบราว์เซอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. ทำลาย Session
session_destroy();

// 4. เปลี่ยนเส้นทางกลับไปยังหน้า Login พร้อมพารามิเตอร์แจ้งเตือน
header('Location: login.php?msg=logged_out');
exit;

