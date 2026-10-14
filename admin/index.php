<?php
/**
 * หน้า Dashboard เบื้องต้นสำหรับผู้ดูแลระบบ (Admin Dashboard)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ป้องกันหน้า Dashboard: ตรวจสอบ Session ทุกครั้ง หากยังไม่ได้เข้าสู่ระบบ ให้ดีดกลับไปหน้า Login
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. เรียกใช้การเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/../includes/db.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';
$loginTime = isset($_SESSION['admin_login_time']) ? date('H:i น.', $_SESSION['admin_login_time']) : date('H:i น.');

// 3. ดึงสถิติจริงจากฐานข้อมูล MySQL เพื่อแสดงผลใน Dashboard
$stats = [
    'recipes' => 0,
    'ingredients' => 0,
    'categories' => 0,
    'admins' => 0,
];

$recentRecipes = [];

try {
    $stats['recipes'] = (int) $pdo->query("SELECT COUNT(*) FROM recipes")->fetchColumn();
    $stats['ingredients'] = (int) $pdo->query("SELECT COUNT(*) FROM ingredients")->fetchColumn();
    $stats['categories'] = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $stats['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();

    // ดึงสูตรอาหารล่าสุดในระบบ 5 รายการ
    $recentStmt = $pdo->query("
        SELECT r.id, r.title, r.cooking_time, r.servings, c.name AS category_name, r.created_at
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        ORDER BY r.id DESC
        LIMIT 5
    ");
    $recentRecipes = $recentStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard Data Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดผู้ดูแลระบบ | มีอะไรในครัว? (What's in My Kitchen)</title>
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
    body {
        background-color: var(--bg-cream);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .admin-navbar {
        background-color: #ffffff;
        border-bottom: 1.5px solid var(--border-subtle);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        padding: 0.85rem 0;
    }
    .admin-stat-card {
        background-color: #ffffff;
        border: 1.5px solid var(--border-subtle);
        border-radius: 18px;
        padding: 1.75rem;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        height: 100%;
    }
    .admin-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(224, 93, 38, 0.08);
        border-color: rgba(224, 93, 38, 0.3);
    }
    .stat-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }
    .content-box {
        background-color: #ffffff;
        border: 1.5px solid var(--border-subtle);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    </style>
</head>
<body>

    <!-- Admin Navigation Bar -->
    <nav class="admin-navbar sticky-top">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                
                <!-- Brand Title -->
                <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2">
                    <span class="brand-icon" style="background-color: var(--primary-light); color: var(--primary-color); width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1.3rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </span>
                    <div>
                        <span class="fw-bold fs-5 text-dark">มีอะไรในครัว?</span>
                        <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.7rem;">Admin</span>
                    </div>
                </a>

                <!-- Admin Center Menu -->
                <div class="d-none d-md-flex align-items-center gap-2">
                    <a href="index.php" class="btn btn-sm btn-light border rounded-pill px-3 py-2 fw-semibold text-primary" style="background-color: var(--primary-light);">
                        <i class="bi bi-speedometer2 me-1"></i> แดชบอร์ด
                    </a>
                    <a href="recipes.php" class="btn btn-sm btn-light rounded-pill px-3 py-2 text-dark">
                        <i class="bi bi-journal-bookmark me-1"></i> จัดการสูตรอาหาร
                    </a>
                    <a href="ingredients.php" class="btn btn-sm btn-light rounded-pill px-3 py-2 text-dark">
                        <i class="bi bi-basket2 me-1"></i> จัดการวัตถุดิบ
                    </a>
                </div>

                <!-- User Info & Action Buttons -->
                <div class="d-flex align-items-center gap-2 gap-md-3">
                    <div class="d-none d-md-block text-end">
                        <span class="d-block small fw-bold text-dark"><?php echo htmlspecialchars($adminName); ?></span>
                        <small class="text-muted" style="font-size: 0.78rem;">(@<?php echo htmlspecialchars($adminUsername); ?>)</small>
                    </div>

                    <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" title="เปิดดูหน้าแรกของเว็บไซต์">
                        <i class="bi bi-box-arrow-up-right me-1"></i>
                        <span class="d-none d-sm-inline">ดูหน้าเว็บ</span>
                    </a>

                    <!-- ปุ่มออกจากระบบ (Logout) -->
                    <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <!-- Main Dashboard Content -->
    <main class="py-4 py-md-5 flex-grow-1">
        <div class="container">

            <!-- Welcome Banner (แจ้งเตือนเข้าสู่ระบบสำเร็จ) -->
            <div class="alert alert-success border-0 rounded-4 shadow-sm p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, #e8f5e9 0%, #d4edda 100%);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background-color: #2e7d32; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem;" class="flex-shrink-0">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div>
                        <h4 class="h6 fw-bold text-success-emphasis mb-1">เข้าสู่ระบบสำเร็จ!</h4>
                        <span class="small text-success-emphasis mb-0">
                            ยินดีต้อนรับคุณ <strong><?php echo htmlspecialchars($adminName); ?></strong> เข้าสู่แดชบอร์ดระบบจัดการหลังบ้าน (เข้าสู่ระบบเมื่อ <?php echo htmlspecialchars($loginTime); ?>)
                        </span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <span class="badge bg-success rounded-pill px-3 py-2 small">
                        <i class="bi bi-shield-check me-1"></i>ระบบปลอดภัย
                    </span>
                </div>
            </div>

            <!-- Stats Overview Grid -->
            <div class="row g-4 mb-5 row-cols-1 row-cols-sm-2 row-cols-lg-4">
                
                <!-- Stat 1: สูตรอาหาร -->
                <div class="col">
                    <a href="recipes.php" class="text-decoration-none">
                        <div class="admin-stat-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small fw-semibold">สูตรอาหารทั้งหมด</span>
                                <div class="stat-icon-wrapper" style="background-color: #fff2ea; color: var(--primary-color);">
                                    <i class="bi bi-journal-bookmark"></i>
                                </div>
                            </div>
                            <h3 class="h2 fw-bold text-dark mb-1"><?php echo number_format($stats['recipes']); ?></h3>
                            <span class="small text-primary fw-medium">จัดการสูตรอาหาร <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>

                <!-- Stat 2: วัตถุดิบ -->
                <div class="col">
                    <a href="ingredients.php" class="text-decoration-none">
                        <div class="admin-stat-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small fw-semibold">วัตถุดิบในคลัง</span>
                                <div class="stat-icon-wrapper" style="background-color: #edf7f1; color: var(--sage-green);">
                                    <i class="bi bi-basket2"></i>
                                </div>
                            </div>
                            <h3 class="h2 fw-bold text-dark mb-1"><?php echo number_format($stats['ingredients']); ?></h3>
                            <span class="small text-success fw-medium">จัดการวัตถุดิบ <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                </div>

                <!-- Stat 3: หมวดหมู่อาหาร -->
                <div class="col">
                    <div class="admin-stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small fw-semibold">หมวดหมู่อาหาร</span>
                            <div class="stat-icon-wrapper" style="background-color: #fef8e7; color: #d89614;">
                                <i class="bi bi-grid"></i>
                            </div>
                        </div>
                        <h3 class="h2 fw-bold text-dark mb-1"><?php echo number_format($stats['categories']); ?></h3>
                        <span class="small text-muted">หมวดหมู่สำหรับจัดกลุ่ม</span>
                    </div>
                </div>

                <!-- Stat 4: ผู้ดูแลระบบ -->
                <div class="col">
                    <div class="admin-stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small fw-semibold">ผู้ดูแลระบบ</span>
                            <div class="stat-icon-wrapper" style="background-color: #f0f4ff; color: #3b82f6;">
                                <i class="bi bi-person-badge"></i>
                            </div>
                        </div>
                        <h3 class="h2 fw-bold text-dark mb-1"><?php echo number_format($stats['admins']); ?></h3>
                        <span class="small text-muted">บัญชีที่มีสิทธิ์เข้าถึง</span>
                    </div>
                </div>

            </div>

            <!-- Dashboard Content: Quick Links & Recent Recipes -->
            <div class="row g-4">
                
                <!-- Left: Recent Recipes in Database -->
                <div class="col-lg-8">
                    <div class="content-box">
                        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                            <div>
                                <h3 class="h6 fw-bold text-dark mb-1">
                                    <i class="bi bi-clock-history me-1 text-primary"></i> รายการสูตรอาหารในระบบ
                                </h3>
                                <small class="text-muted">ข้อมูลสูตรอาหารที่บันทึกอยู่ในฐานข้อมูลจริง</small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="recipe_add.php" class="btn btn-sm btn-kitchen-primary rounded-pill px-3">
                                    <i class="bi bi-plus-circle me-1"></i>เพิ่มสูตร
                                </a>
                                <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                    จัดการทั้งหมด <i class="bi bi-arrow-right small"></i>
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($recentRecipes)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>#</th>
                                            <th>ชื่อเมนูอาหาร</th>
                                            <th>หมวดหมู่</th>
                                            <th>เวลาปรุง</th>
                                            <th>จำนวน</th>
                                            <th class="text-end" style="width: 100px;">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php foreach ($recentRecipes as $idx => $r): ?>
                                            <tr>
                                                <td class="text-muted fw-bold"><?php echo (int) $r['id']; ?></td>
                                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($r['title']); ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border rounded-pill">
                                                        <?php echo htmlspecialchars($r['category_name'] ?? 'ทั่วไป'); ?>
                                                    </span>
                                                </td>
                                                <td><i class="bi bi-clock text-warning me-1"></i><?php echo (int) $r['cooking_time']; ?> นาที</td>
                                                <td><?php echo (int) $r['servings']; ?> ที่</td>
                                                <td class="text-end text-nowrap">
                                                    <a href="../recipe_detail.php?id=<?php echo (int) $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1" title="ดูหน้าเว็บ">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="recipe_edit.php?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 ms-1" title="แก้ไข">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0 text-center py-4">ยังไม่มีข้อมูลสูตรอาหารในระบบ</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Quick Navigation & Admin Profile Card -->
                <div class="col-lg-4">
                    
                    <!-- Admin Profile Card -->
                    <div class="content-box mb-4">
                        <div class="text-center mb-3">
                            <div style="width: 70px; height: 70px; border-radius: 50%; background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; box-shadow: 0 4px 14px rgba(224, 93, 38, 0.15);" class="mb-2">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <h4 class="h6 fw-bold text-dark mb-0"><?php echo htmlspecialchars($adminName); ?></h4>
                            <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill small">ผู้ดูแลระบบ (Admin)</span>
                        </div>

                        <div class="d-flex flex-column gap-2 small text-muted border-top pt-3">
                            <div class="d-flex justify-content-between">
                                <span>ชื่อผู้ใช้:</span>
                                <strong class="text-dark"><?php echo htmlspecialchars($adminUsername); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>สถานะเซสชัน:</span>
                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill">ใช้งานอยู่ (Active)</span>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <a href="logout.php" class="btn btn-outline-danger w-100 rounded-pill py-2 small" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');">
                                <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบผู้ดูแล
                            </a>
                        </div>
                    </div>

                    <!-- Admin Management Quick Links -->
                    <div class="content-box mb-4">
                        <h4 class="h6 fw-bold text-dark mb-3">
                            <i class="bi bi-gear-fill me-1 text-primary"></i> เมนูจัดการระบบหลังบ้าน
                        </h4>
                        <div class="d-flex flex-column gap-2">
                            <a href="recipes.php" class="btn btn-sm btn-outline-primary text-start rounded-pill py-2 px-3 fw-medium">
                                <i class="bi bi-journal-bookmark me-2"></i>จัดการสูตรอาหาร (CRUD)
                            </a>
                            <a href="recipe_add.php" class="btn btn-sm btn-kitchen-primary text-start rounded-pill py-2 px-3 fw-medium">
                                <i class="bi bi-plus-circle me-2"></i>เพิ่มสูตรอาหารใหม่
                            </a>
                            <a href="ingredients.php" class="btn btn-sm btn-outline-success text-start rounded-pill py-2 px-3 fw-medium">
                                <i class="bi bi-basket2 me-2"></i>จัดการคลังวัตถุดิบ (CRUD)
                            </a>
                            <a href="ingredient_add.php" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3 fw-medium">
                                <i class="bi bi-plus-circle me-2"></i>เพิ่มวัตถุดิบใหม่
                            </a>
                        </div>
                    </div>

                    <!-- Front-End Quick Links -->
                    <div class="content-box">
                        <h4 class="h6 fw-bold text-dark mb-3">
                            <i class="bi bi-link-45deg me-1 text-primary"></i> ลิงก์หน้าระบบหน้าบ้าน
                        </h4>
                        <div class="d-flex flex-column gap-2">
                            <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3">
                                <i class="bi bi-house-door me-2"></i>หน้าแรกเว็บไซต์
                            </a>
                            <a href="../ingredients.php" target="_blank" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3">
                                <i class="bi bi-check2-square me-2"></i>หน้าเลือกวัตถุดิบ
                            </a>
                            <a href="../recipes.php" target="_blank" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3">
                                <i class="bi bi-journal-bookmark me-2"></i>หน้าสูตรอาหารทั้งหมด
                            </a>
                            <a href="../categories.php" target="_blank" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3">
                                <i class="bi bi-grid me-2"></i>หน้าหมวดหมู่อาหาร
                            </a>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <!-- Admin Footer -->
    <footer class="py-3 bg-white border-top text-center text-muted small mt-auto">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> <strong>มีอะไรในครัว? (What's in My Kitchen)</strong> — ระบบจัดการข้อมูลหลังบ้าน</span>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

