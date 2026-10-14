<?php
/**
 * Database Connection Test - มีอะไรในครัว? (What's in My Kitchen)
 * ไฟล์สำหรับทดสอบการเชื่อมต่อฐานข้อมูลผ่าน includes/db.php
 */

// โหลดไฟล์การเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/includes/db.php';

// ดึงชื่อฐานข้อมูลที่กำลังเชื่อมต่ออยู่จริง
$dbName = '';
$tables = [];
$error = null;

try {
    // ตรวจสอบชื่อฐานข้อมูลปัจจุบัน
    $stmtDb = $pdo->query("SELECT DATABASE()");
    $dbName = $stmtDb->fetchColumn();

    // ตรวจสอบและอ่านรายชื่อตารางทั้งหมดในฐานข้อมูลจริง
    $stmtTables = $pdo->query("SHOW TABLES");
    $tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "ไม่สามารถอ่านข้อมูลโครงสร้างตารางได้: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดสอบการเชื่อมต่อฐานข้อมูล | มีอะไรในครัว?</title>
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8f9fa;
            color: #333333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .test-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .test-card-header {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .test-card-body {
            padding: 28px 24px;
        }
        .status-badge {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }
        .table-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>

<div class="test-card">
    <!-- Card Header: แสดงสถานะการเชื่อมต่อ -->
    <div class="test-card-header">
        <i class="bi bi-check-circle-fill display-4 mb-2"></i>
        <h1 class="h4 fw-bold mb-1">เชื่อมต่อฐานข้อมูลสำเร็จ</h1>
        <div class="status-badge">
            <i class="bi bi-hdd-network"></i>
            <span>ระบบเชื่อมต่อฐานข้อมูลทำงานได้อย่างสมบูรณ์</span>
        </div>
    </div>

    <!-- Card Body: แสดงรายละเอียดฐานข้อมูลและรายชื่อตาราง -->
    <div class="test-card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <!-- ข้อมูลฐานข้อมูล -->
            <div class="mb-4">
                <label class="form-label text-muted small fw-semibold text-uppercase mb-1">ชื่อฐานข้อมูลที่เชื่อมต่อ</label>
                <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between border">
                    <span class="fw-bold text-dark fs-5">
                        <i class="bi bi-database text-success me-2"></i><?php echo htmlspecialchars($dbName); ?>
                    </span>
                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2 rounded-pill">
                        <i class="bi bi-check2 me-1"></i>พร้อมใช้งาน
                    </span>
                </div>
            </div>

            <!-- รายชื่อตารางในฐานข้อมูล -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label text-muted small fw-semibold text-uppercase mb-0">ตารางที่พบในฐานข้อมูล</label>
                    <span class="badge bg-secondary rounded-pill"><?php echo count($tables); ?> ตาราง</span>
                </div>
                
                <?php if (count($tables) > 0): ?>
                    <div class="d-flex flex-wrap gap-2 pt-1">
                        <?php foreach ($tables as $table): ?>
                            <div class="table-badge">
                                <i class="bi bi-table text-primary"></i>
                                <span><?php echo htmlspecialchars($table); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0 py-2">
                        <i class="bi bi-info-circle me-1"></i> ไม่พบตารางในฐานข้อมูล กรุณานำเข้าไฟล์ schema.sql
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ปุ่มกลับสู่หน้าแรก -->
        <div class="pt-2 text-center">
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                <i class="bi bi-arrow-left me-1"></i> กลับสู่หน้าหลัก
            </a>
        </div>
    </div>
</div>

</body>
</html>

