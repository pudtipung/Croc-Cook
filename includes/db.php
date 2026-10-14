<?php
/**
 * Database Connection - มีอะไรในครัว? (What's in My Kitchen)
 * จัดการการเชื่อมต่อฐานข้อมูล MySQL ผ่าน PHP Data Objects (PDO)
 */

// ==============================================================================
// 1. กำหนดค่าการเชื่อมต่อฐานข้อมูล (Database Configuration)
// กำหนดไว้ที่จุดเดียวเพื่อให้ง่ายต่อการปรับเปลี่ยนสภาพแวดล้อมการทำงาน (Environment)
// ==============================================================================
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'whats_in_my_kitchen');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// ==============================================================================
// 2. กำหนดค่าตัวเลือกสำหรับ PDO (PDO Options)
// ==============================================================================
$pdoOptions = [
    // กำหนดให้โยน PDOException เมื่อเกิดข้อผิดพลาดในการรัน SQL
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // กำหนดให้คืนค่าข้อมูลแบบ Associative Array เป็นค่าเริ่มต้น
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // ปิดการ Emulate Prepared Statements เพื่อความปลอดภัยระดับ Native Prepared Statements
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ==============================================================================
// 3. ดำเนินการเชื่อมต่อฐานข้อมูล (Establish Connection)
// ==============================================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    // บันทึกรายละเอียดข้อผิดพลาดลงในระบบ System/PHP Error Log
    error_log("Database Connection Error: " . $e->getMessage());

    // แสดงข้อความแจ้งเตือนที่เข้าใจง่ายสำหรับผู้พัฒนา โดยไม่เปิดเผยรหัสผ่านหรือข้อมูลลับ
    die("<div style=\"font-family: 'Prompt', Tahoma, sans-serif; padding: 20px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 8px; margin: 30px auto; max-width: 600px;\">"
        . "<h3 style=\"margin-top: 0; color: #721c24;\">⚠️ การเชื่อมต่อฐานข้อมูลไม่สำเร็จ</h3>"
        . "<p>ไม่สามารถเชื่อมต่อกับฐานข้อมูล MySQL ได้ กรุณาตรวจสอบ:</p>"
        . "<ul style=\"margin-bottom: 10px;\">"
        . "<li>สถานะของบริการ MySQL ใน XAMPP Control Panel ว่าเปิดทำงาน (Running) อยู่หรือไม่</li>"
        . "<li>ชื่อฐานข้อมูล '<strong>" . htmlspecialchars(DB_NAME) . "</strong>' ถูกสร้างแล้วหรือไม่</li>"
        . "<li>การตั้งค่า Host และสิทธิ์ผู้ใช้งานในไฟล์ <code>includes/db.php</code></li>"
        . "</ul>"
        . "<small style=\"color: #6c757d;\">รหัสข้อผิดพลาด (Error Code): " . htmlspecialchars($e->getCode()) . "</small>"
        . "</div>");
}

