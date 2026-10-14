<?php
/**
 * หน้ารายการจัดการวัตถุดิบสำหรับผู้ดูแลระบบ (Ingredients Management)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

// 1. ตรวจสอบสิทธิ์ผู้ดูแลระบบและดึงการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// 2. จัดการข้อความแจ้งเตือน (Flash Messages)
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// 3. รับค่าตัวกรองและการค้นหา
$search = trim($_GET['search'] ?? '');
$groupFilter = trim($_GET['group'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');

// 4. ดึงรายชื่อกลุ่มหมวดหมู่ที่มีอยู่จริงในฐานข้อมูลสำหรับ Dropdown
$categoryGroups = [];
try {
    $grpStmt = $pdo->query("SELECT DISTINCT category_group FROM ingredients WHERE category_group IS NOT NULL AND category_group != '' ORDER BY category_group ASC");
    $categoryGroups = $grpStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Load Category Groups Error: " . $e->getMessage());
}

// 5. ดึงข้อมูลวัตถุดิบทั้งหมดพร้อมนับจำนวนสูตรอาหารที่ใช้อ้างอิง (Prepared Statement)
$ingredients = [];
$totalCount = 0;
$mainCount = 0;
$seasoningCount = 0;

try {
    // นับสถิติภาพรวม
    $mainCount = (int) $pdo->query("SELECT COUNT(*) FROM ingredients WHERE type = 'main'")->fetchColumn();
    $seasoningCount = (int) $pdo->query("SELECT COUNT(*) FROM ingredients WHERE type = 'seasoning'")->fetchColumn();

    $whereConditions = [];
    $queryParams = [];

    if ($search !== '') {
        $whereConditions[] = "i.name LIKE :search";
        $queryParams[':search'] = '%' . $search . '%';
    }

    if ($groupFilter !== '') {
        $whereConditions[] = "i.category_group = :group";
        $queryParams[':group'] = $groupFilter;
    }

    if ($typeFilter !== '' && in_array($typeFilter, ['main', 'seasoning'], true)) {
        $whereConditions[] = "i.type = :type";
        $queryParams[':type'] = $typeFilter;
    }

    $whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $sql = "
        SELECT 
            i.id, 
            i.name, 
            i.type, 
            i.category_group,
            COUNT(ri.id) AS used_in_recipes,
            GROUP_CONCAT(DISTINCT r.title ORDER BY r.id SEPARATOR ', ') AS sample_recipes
        FROM ingredients i
        LEFT JOIN recipe_ingredients ri ON i.id = ri.ingredient_id
        LEFT JOIN recipes r ON ri.recipe_id = r.id
        $whereSql
        GROUP BY i.id
        ORDER BY 
            FIELD(i.category_group, 'เนื้อสัตว์', 'ไข่และแป้ง', 'ผักและสมุนไพร', 'เครื่องปรุงรส', 'ทั่วไป'),
            i.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $ingredients = $stmt->fetchAll();
    $totalCount = count($ingredients);

} catch (PDOException $e) {
    error_log("Admin Ingredients List Error: " . $e->getMessage());
    $flashError = "เกิดข้อผิดพลาดในการโหลดรายการวัตถุดิบ: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการวัตถุดิบ | ระบบจัดการหลังบ้าน - มีอะไรในครัว?</title>
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Theme CSS -->
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
    .content-box {
        background-color: #ffffff;
        border: 1.5px solid var(--border-subtle);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    .action-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        padding: 0;
    }
    .nav-pill-admin {
        font-weight: 500;
        color: #555;
        border-radius: 50rem;
        padding: 0.45rem 1rem;
        transition: all 0.2s ease;
    }
    .nav-pill-admin:hover, .nav-pill-admin.active {
        background-color: var(--primary-light);
        color: var(--primary-color);
    }
    .stat-badge {
        font-size: 0.85rem;
        padding: 0.45rem 0.9rem;
        border-radius: 50rem;
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
                    <a href="index.php" class="nav-link nav-pill-admin">
                        <i class="bi bi-speedometer2 me-1"></i> แดชบอร์ด
                    </a>
                    <a href="recipes.php" class="nav-link nav-pill-admin">
                        <i class="bi bi-journal-bookmark me-1"></i> จัดการสูตรอาหาร
                    </a>
                    <a href="ingredients.php" class="nav-link nav-pill-admin active">
                        <i class="bi bi-basket2 me-1"></i> จัดการวัตถุดิบ
                    </a>
                </div>

                <!-- User Info & Action Buttons -->
                <div class="d-flex align-items-center gap-2 gap-md-3">
                    <div class="d-none d-lg-block text-end">
                        <span class="d-block small fw-bold text-dark"><?php echo htmlspecialchars($adminName); ?></span>
                        <small class="text-muted" style="font-size: 0.78rem;">(@<?php echo htmlspecialchars($adminUsername); ?>)</small>
                    </div>

                    <a href="../ingredients.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" title="เปิดดูหน้าเลือกวัตถุดิบบนเว็บจริง">
                        <i class="bi bi-box-arrow-up-right me-1"></i>
                        <span class="d-none d-sm-inline">ดูหน้าเว็บ</span>
                    </a>

                    <a href="logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-4 py-md-5 flex-grow-1">
        <div class="container">

            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด</a></li>
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">จัดการวัตถุดิบ</li>
                </ol>
            </nav>

            <!-- Page Header & Action Buttons -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-basket2 text-primary me-2"></i>ระบบจัดการคลังวัตถุดิบ
                    </h1>
                    <p class="text-muted small mb-0">จัดการรายการวัตถุดิบและเครื่องปรุงรสสำหรับให้ผู้ใช้เลือกค้นหาสูตรอาหาร</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="ingredient_add.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle-fill fs-6"></i>
                        <span class="fw-semibold">เพิ่มวัตถุดิบใหม่</span>
                    </a>
                </div>
            </div>

            <!-- Flash Alert: Success Message -->
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center justify-content-between alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background-color: #2e7d32; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;" class="flex-shrink-0">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <span class="small fw-semibold text-success-emphasis"><?php echo htmlspecialchars($flashSuccess); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Flash Alert: Error Message (เช่น ไม่ให้ลบเนื่องจากมีสูตรใช้งานอยู่) -->
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center justify-content-between alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background-color: #c62828; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;" class="flex-shrink-0">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <span class="small fw-semibold text-danger-emphasis"><?php echo htmlspecialchars($flashError); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Overview Bar -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="stat-badge bg-white border text-dark">
                    <i class="bi bi-collection me-1 text-primary"></i> ทั้งหมด: <strong><?php echo number_format($totalCount); ?></strong> รายการ
                </span>
                <span class="stat-badge bg-success-subtle text-success-emphasis border border-success-subtle">
                    <i class="bi bi-egg-fried me-1"></i> วัตถุดิบหลัก (Main): <strong><?php echo number_format($mainCount); ?></strong>
                </span>
                <span class="stat-badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                    <i class="bi bi-cup-hot me-1"></i> เครื่องปรุงรส (Seasoning): <strong><?php echo number_format($seasoningCount); ?></strong>
                </span>
            </div>

            <!-- Search & Filter Card -->
            <div class="content-box mb-4 p-3 p-md-4">
                <form method="GET" action="ingredients.php" class="row g-3 align-items-center">
                    
                    <!-- Search Input -->
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อวัตถุดิบ เช่น หมูสับ, กะปิ..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Category Group Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <select name="group" class="form-select">
                            <option value="">-- ทุกกลุ่มหมวดหมู่ --</option>
                            <?php foreach ($categoryGroups as $grp): ?>
                                <option value="<?php echo htmlspecialchars($grp); ?>" <?php echo ($groupFilter === $grp) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grp); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <select name="type" class="form-select">
                            <option value="">-- ทุกประเภท --</option>
                            <option value="main" <?php echo ($typeFilter === 'main') ? 'selected' : ''; ?>>วัตถุดิบหลัก</option>
                            <option value="seasoning" <?php echo ($typeFilter === 'seasoning') ? 'selected' : ''; ?>>เครื่องปรุงรส</option>
                        </select>
                    </div>

                    <!-- Submit & Reset Buttons -->
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-secondary rounded-pill px-3 py-2 flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>กรอง
                        </button>
                        <?php if ($search !== '' || $groupFilter !== '' || $typeFilter !== ''): ?>
                            <a href="ingredients.php" class="btn btn-light border rounded-pill px-3 py-2 text-muted" title="ล้างตัวกรอง">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>

            <!-- Ingredients Table Box -->
            <div class="content-box">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0">
                            รายการวัตถุดิบในฐานข้อมูล MySQL
                        </h2>
                        <small class="text-muted">
                            <?php if ($search !== '' || $groupFilter !== '' || $typeFilter !== ''): ?>
                                แสดงผลการกรอง <?php echo number_format($totalCount); ?> รายการ
                            <?php else: ?>
                                รายการวัตถุดิบทั้งหมด <?php echo number_format($totalCount); ?> รายการ
                            <?php endif; ?>
                        </small>
                    </div>

                    <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2 small">
                        <i class="bi bi-database me-1"></i>ตาราง MySQL: ingredients
                    </span>
                </div>

                <?php if (!empty($ingredients)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th style="width: 60px;">#ID</th>
                                    <th>ชื่อวัตถุดิบ</th>
                                    <th>กลุ่มหมวดหมู่</th>
                                    <th>ประเภท</th>
                                    <th>การใช้งานในสูตรอาหาร</th>
                                    <th>สถานะความปลอดภัย</th>
                                    <th class="text-end" style="width: 110px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($ingredients as $ing): ?>
                                    <?php 
                                    $usageCount = (int) $ing['used_in_recipes']; 
                                    $isSafeToDelete = ($usageCount === 0);
                                    ?>
                                    <tr>
                                        <!-- ID -->
                                        <td class="text-muted fw-bold"><?php echo (int) $ing['id']; ?></td>

                                        <!-- Name -->
                                        <td>
                                            <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($ing['name']); ?></span>
                                        </td>

                                        <!-- Category Group -->
                                        <td>
                                            <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                                                <i class="bi bi-tag-fill text-muted me-1"></i>
                                                <?php echo htmlspecialchars($ing['category_group'] ?? 'ทั่วไป'); ?>
                                            </span>
                                        </td>

                                        <!-- Type -->
                                        <td>
                                            <?php if ($ing['type'] === 'main'): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1">
                                                    <i class="bi bi-egg-fried me-1"></i>วัตถุดิบหลัก
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1">
                                                    <i class="bi bi-cup-hot me-1"></i>เครื่องปรุงรส
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Recipes Usage Count -->
                                        <td>
                                            <?php if ($usageCount > 0): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis rounded-pill" title="<?php echo htmlspecialchars($ing['sample_recipes'] ?? ''); ?>">
                                                    <i class="bi bi-journal-check me-1"></i>ใช้ใน <?php echo $usageCount; ?> สูตรอาหาร
                                                </span>
                                                <div class="small text-muted text-truncate mt-1" style="max-width: 250px; font-size: 0.75rem;">
                                                    <?php echo htmlspecialchars($ing['sample_recipes'] ?? ''); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">
                                                    <i class="bi bi-dash me-1"></i>ยังไม่ถูกใช้ในสูตรใด
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Safety Status -->
                                        <td>
                                            <?php if ($isSafeToDelete): ?>
                                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill" style="font-size: 0.72rem;">
                                                    <i class="bi bi-shield-check me-1"></i>พร้อมลบได้
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill" style="font-size: 0.72rem;" title="ห้ามลบเนื่องจากมีสูตรอาหารใช้อ้างอิงอยู่">
                                                    <i class="bi bi-shield-lock me-1"></i>มีสูตรอ้างอิง (ห้ามลบ)
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-end text-nowrap">
                                            <!-- Edit Button -->
                                            <a href="ingredient_edit.php?id=<?php echo (int) $ing['id']; ?>" class="btn btn-sm btn-outline-primary action-btn" title="แก้ไขวัตถุดิบนี้">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <!-- Delete Button (Trigger Modal) -->
                                            <button type="button" class="btn btn-sm btn-outline-danger action-btn ms-1" title="ลบวัตถุดิบนี้"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteIngredientModal"
                                                    data-id="<?php echo (int) $ing['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($ing['name'], ENT_QUOTES); ?>"
                                                    data-usage="<?php echo $usageCount; ?>"
                                                    data-recipes="<?php echo htmlspecialchars($ing['sample_recipes'] ?? '', ENT_QUOTES); ?>">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-5">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem;" class="mb-3">
                            <i class="bi bi-basket"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">ไม่พบข้อมูลวัตถุดิบ</h3>
                        <p class="text-muted small mb-3">
                            <?php if ($search !== '' || $groupFilter !== '' || $typeFilter !== ''): ?>
                                ไม่พบวัตถุดิบที่ตรงกับคำค้นหาหรือตัวกรอง
                            <?php else: ?>
                                ยังไม่มีข้อมูลวัตถุดิบในระบบ
                            <?php endif; ?>
                        </p>
                        <?php if ($search !== '' || $groupFilter !== '' || $typeFilter !== ''): ?>
                            <a href="ingredients.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>ล้างตัวกรองทั้งหมด
                            </a>
                        <?php else: ?>
                            <a href="ingredient_add.php" class="btn btn-sm btn-kitchen-primary rounded-pill px-4 py-2">
                                <i class="bi bi-plus-circle me-1"></i>เพิ่มวัตถุดิบแรก
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </main>

    <!-- Delete Confirmation Modal (ตรวจสอบการใช้งานในสูตรอาหาร) -->
    <div class="modal fade" id="deleteIngredientModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="POST" action="ingredient_delete.php">
                    <?php echo admin_csrf_input(); ?>
                    <input type="hidden" name="ingredient_id" id="modalIngredientId" value="0">

                    <div class="modal-body p-4 text-center">
                        <!-- Icon will be set dynamically by JS based on usage -->
                        <div id="modalIconContainer" style="width: 65px; height: 65px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem;" class="mb-3">
                            <i id="modalIcon" class="bi bi-exclamation-triangle-fill"></i>
                        </div>

                        <h5 class="fw-bold text-dark mb-2" id="deleteModalLabel">ยืนยันการลบวัตถุดิบ</h5>
                        <p class="text-muted small mb-3" style="line-height: 1.6;">
                            วัตถุดิบ: <strong id="modalIngredientName" class="text-dark"></strong>
                        </p>

                        <!-- Content message based on usage -->
                        <div id="modalWarningBox" class="text-start py-2 px-3 rounded-3 mb-0 small">
                            <!-- Filled dynamically by JS -->
                        </div>
                    </div>

                    <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4 justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            ยกเลิก
                        </button>
                        <button type="submit" id="modalConfirmDeleteBtn" class="btn btn-danger rounded-pill px-4 py-2">
                            <i class="bi bi-trash3 me-1"></i>ยืนยันการลบ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Footer -->
    <footer class="py-3 bg-white border-top text-center text-muted small mt-auto">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> <strong>มีอะไรในครัว? (What's in My Kitchen)</strong> — ระบบจัดการข้อมูลหลังบ้าน</span>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal Event Script -->
    <script>
    const deleteModal = document.getElementById('deleteIngredientModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ingId = button.getAttribute('data-id');
            const ingName = button.getAttribute('data-name');
            const usageCount = parseInt(button.getAttribute('data-usage') || '0', 10);
            const sampleRecipes = button.getAttribute('data-recipes') || '';

            document.getElementById('modalIngredientId').value = ingId;
            document.getElementById('modalIngredientName').textContent = `"${ingName}" (ID: #${ingId})`;

            const iconContainer = document.getElementById('modalIconContainer');
            const icon = document.getElementById('modalIcon');
            const warningBox = document.getElementById('modalWarningBox');
            const confirmBtn = document.getElementById('modalConfirmDeleteBtn');

            if (usageCount > 0) {
                // ไม่อนุญาตให้ลบเนื่องจากมีสูตรอาหารใช้งานอยู่
                iconContainer.style.backgroundColor = '#fee2e2';
                iconContainer.style.color = '#dc2626';
                icon.className = 'bi bi-shield-x';

                warningBox.className = 'alert alert-danger border-0 small text-start py-3 px-3 rounded-3 mb-0';
                warningBox.innerHTML = `
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>ไม่สามารถลบวัตถุดิบนี้ได้!</div>
                    <div>เนื่องจากวัตถุดิบนี้ถูกใช้อ้างอิงอยู่ใน <strong>${usageCount} สูตรอาหาร</strong> ได้แก่: <em>${sampleRecipes}</em></div>
                    <div class="mt-2 text-danger-emphasis">
                        <i class="bi bi-info-circle me-1"></i>เพื่อป้องกันไม่ให้ข้อมูลสูตรอาหารเสียหาย ระบบไม่อนุญาตให้ลบวัตถุดิบจนกว่าจะนำวัตถุดิบนี้ออกจากสูตรอาหารทั้งหมดก่อน
                    </div>
                `;

                // ปิดการทำงานของปุ่มลบ
                confirmBtn.disabled = true;
                confirmBtn.classList.add('opacity-50');
                confirmBtn.innerHTML = '<i class="bi bi-slash-circle me-1"></i>ไม่สามารถลบได้';
            } else {
                // ปลอดภัย สามารถลบได้
                iconContainer.style.backgroundColor = '#fef3c7';
                iconContainer.style.color = '#d97706';
                icon.className = 'bi bi-exclamation-triangle-fill';

                warningBox.className = 'alert alert-warning border-0 small text-start py-3 px-3 rounded-3 mb-0';
                warningBox.innerHTML = `
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    วัตถุดิบนี้ไม่ได้ถูกใช้อ้างอิงในสูตรอาหารใด สามารถลบออกจากฐานข้อมูลได้อย่างปลอดภัย
                `;

                confirmBtn.disabled = false;
                confirmBtn.classList.remove('opacity-50');
                confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>ยืนยันการลบ';
            }
        });
    }
    </script>

</body>
</html>

