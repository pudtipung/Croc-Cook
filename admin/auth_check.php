<?php
/**
 * ไฟล์ตรวจสอบสิทธิ์ผู้ดูแลระบบและฟังก์ชันช่วยเหลือ (Admin Auth & Helper)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบสถานะการเข้าสู่ระบบ หากยังไม่ได้ Login ให้ redirect ไปหน้า login.php
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. เรียกใช้การเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/../includes/db.php';

// 3. จัดการ CSRF Token
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * สร้าง HTML สำหรับ CSRF Token Input
 */
function admin_csrf_input() {
    $token = htmlspecialchars($_SESSION['admin_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * ตรวจสอบความถูกต้องของ CSRF Token จากคำขอ POST
 */
function admin_verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $postedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';
    return hash_equals($sessionToken, $postedToken);
}

/**
 * จัดการอัปโหลดรูปภาพสูตรอาหารอย่างปลอดภัย
 * 
 * @param array $file อาร์เรย์ $_FILES['...']
 * @param string|null $fallbackUrl URL หรือชื่อไฟล์เดิม
 * @param string|null &$error ข้อความแจ้งเตือนข้อผิดพลาด
 * @return string|null ชื่อไฟล์ที่บันทึก หรือ URL หรือค่าว่าง
 */
function handle_recipe_image_upload($file, $fallbackUrl = null, &$error = null) {
    // กรณีมีการเลือกไฟล์อัปโหลด
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์รูปภาพ (รหัสข้อผิดพลาด: ' . (int)$file['error'] . ')';
            return false;
        }

        // ตรวจสอบขนาดไฟล์ (จำกัดสูงสุด 2MB)
        $maxSize = 2 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $error = 'ไฟล์รูปภาพมีขนาดใหญ่เกินกำหนด (สูงสุด 2 MB)';
            return false;
        }

        // ตรวจสอบนามสกุลไฟล์
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $error = 'อนุญาตเฉพาะไฟล์รูปภาพนามสกุล .jpg, .jpeg, .png, .webp, .gif เท่านั้น';
            return false;
        }

        // ตรวจสอบ MIME type จริงของไฟล์ด้วย finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/x-png',
            'image/webp',
            'image/gif'
        ];
        if (!in_array($mime, $allowedMimes, true)) {
            $error = 'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะไฟล์ภาพจริงเท่านั้น (พบ: ' . htmlspecialchars($mime) . ')';
            return false;
        }

        // ตรวจสอบความถูกต้องของรูปภาพด้วย getimagesize
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $error = 'ไฟล์ที่อัปโหลดไม่ใช่ไฟล์รูปภาพที่สมบูรณ์';
            return false;
        }

        // สร้างชื่อไฟล์ใหม่ที่ปลอดภัย ป้องกันชื่อภาษาไทยหรืออักขระพิเศษและป้องกันทับไฟล์เดิม
        $newFilename = 'recipe_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $targetDir = __DIR__ . '/../assets/images/';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $destination = $targetDir . $newFilename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = 'ไม่สามารถบันทึกไฟล์ภาพลงในเซิร์ฟเวอร์ได้';
            return false;
        }

        return $newFilename;
    }

    // หากไม่ได้อัปโหลดไฟล์ใหม่ แต่มีการกรอก URL หรือชื่อไฟล์
    if (!empty($fallbackUrl)) {
        $trimmedUrl = trim($fallbackUrl);
        // ตรวจสอบว่าไม่อนุญาตให้กรอกสคริปต์อันตราย
        if (preg_match('/<script|<\?php/i', $trimmedUrl)) {
            $error = 'รูปแบบ URL หรือชื่อไฟล์รูปภาพไม่ถูกต้อง';
            return false;
        }
        return $trimmedUrl;
    }

    return null;
}

