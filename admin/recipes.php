<?php
/**
 * หน้ารายการจัดการสูตรอาหารสำหรับผู้ดูแลระบบ (Recipe Management)
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

// ข้อความแจ้งเตือนผ่าน URL query param (ถ้ามี)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $flashSuccess = $flashSuccess ?? 'เพิ่มสูตรอาหารใหม่เรียบร้อยแล้ว';
    } elseif ($_GET['msg'] === 'updated') {
        $flashSuccess = $flashSuccess ?? 'บันทึกการแก้ไขสูตรอาหารเรียบร้อยแล้ว';
    } elseif ($_GET['msg'] === 'deleted') {
        $flashSuccess = $flashSuccess ?? 'ลบสูตรอาหารออกจากระบบเรียบร้อยแล้ว';
    }
}

// 3. รับค่าตัวกรองและการค้นหา
$search = trim($_GET['search'] ?? '');
$categoryFilter = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]) : 0;
if (!$categoryFilter) {
    $categoryFilter = 0;
}

// 4. ดึงรายชื่อหมวดหมู่ทั้งหมดสำหรับตัวกรอง
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name, icon FROM categories ORDER BY id ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Load Categories Error: " . $e->getMessage());
}

// 5. ดึงรายการสูตรอาหารจากฐานข้อมูล MySQL จริง (Prepared Statements)
$recipes = [];
$totalRecipes = 0;

try {
    $whereConditions = [];
    $queryParams = [];

    if ($search !== '') {
        $whereConditions[] = "(r.title LIKE :search1 OR r.description LIKE :search2)";
        $queryParams[':search1'] = '%' . $search . '%';
        $queryParams[':search2'] = '%' . $search . '%';
    }

    if ($categoryFilter > 0) {
        $whereConditions[] = "r.category_id = :cat_id";
        $queryParams[':cat_id'] = $categoryFilter;
    }

    $whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $sql = "
        SELECT 
            r.id, 
            r.title, 
            r.description, 
            r.image, 
            r.cooking_time, 
            r.servings, 
            r.created_at,
            c.id AS category_id,
            c.name AS category_name, 
            c.icon AS category_icon,
            COUNT(ri.id) AS ingredient_count
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
        $whereSql
        GROUP BY r.id
        ORDER BY r.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $recipes = $stmt->fetchAll();
    $totalRecipes = count($recipes);

} catch (PDOException $e) {
    error_log("Admin Recipe List Error: " . $e->getMessage());
    $flashError = "เกิดข้อผิดพลาดในการโหลดรายการสูตรอาหาร: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสูตรอาหาร | ระบบจัดการหลังบ้าน - มีอะไรในครัว?</title>
    
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
    .recipe-thumb {
        width: 58px;
        height: 58px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid var(--border-subtle);
    }
    .recipe-thumb-fallback {
        width: 58px;
        height: 58px;
        border-radius: 12px;
        background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%);
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        border: 1px solid var(--border-subtle);
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
                    <a href="recipes.php" class="nav-link nav-pill-admin active">
                        <i class="bi bi-journal-bookmark me-1"></i> จัดการสูตรอาหาร
                    </a>
                    <a href="ingredients.php" class="nav-link nav-pill-admin">
                        <i class="bi bi-basket2 me-1"></i> จัดการวัตถุดิบ
                    </a>
                </div>

                <!-- User Info & Action Buttons -->
                <div class="d-flex align-items-center gap-2 gap-md-3">
                    <div class="d-none d-lg-block text-end">
                        <span class="d-block small fw-bold text-dark"><?php echo htmlspecialchars($adminName); ?></span>
                        <small class="text-muted" style="font-size: 0.78rem;">(@<?php echo htmlspecialchars($adminUsername); ?>)</small>
                    </div>

                    <a href="../recipes.php" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" title="เปิดดูหน้ารายการสูตรอาหารหน้าบ้าน">
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
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">จัดการสูตรอาหาร</li>
                </ol>
            </nav>

            <!-- Page Header & Action Button -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-journal-bookmark text-primary me-2"></i>ระบบจัดการสูตรอาหาร
                    </h1>
                    <p class="text-muted small mb-0">เพิ่ม แก้ไข และลบข้อมูลเมนูอาหารในฐานข้อมูล MySQL จริง</p>
                </div>
                <div>
                    <a href="recipe_add.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2 shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle-fill fs-6"></i>
                        <span class="fw-semibold">เพิ่มสูตรอาหารใหม่</span>
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

            <!-- Flash Alert: Error Message -->
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center justify-content-between alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background-color: #c62828; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;" class="flex-shrink-0">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <span class="small fw-semibold text-danger-emphasis"><?php echo htmlspecialchars($flashError); ?></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search & Filter Card -->
            <div class="content-box mb-4 p-3 p-md-4">
                <form method="GET" action="recipes.php" class="row g-3 align-items-center">
                    
                    <!-- Search Input -->
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อเมนูอาหาร หรือคำอธิบาย..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="col-12 col-sm-6 col-md-4">
                        <select name="category" class="form-select">
                            <option value="0">-- ทุกหมวดหมู่อาหาร --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id']; ?>" <?php echo ($categoryFilter === (int)$cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Submit & Reset Buttons -->
                    <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-secondary rounded-pill px-3 py-2 flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>กรองข้อมูล
                        </button>
                        <?php if ($search !== '' || $categoryFilter > 0): ?>
                            <a href="recipes.php" class="btn btn-light border rounded-pill px-3 py-2 text-muted" title="ล้างตัวกรอง">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </form>
            </div>

            <!-- Recipes Table Box -->
            <div class="content-box">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0">
                            รายการสูตรอาหารทั้งหมด
                        </h2>
                        <small class="text-muted">
                            <?php if ($search !== '' || $categoryFilter > 0): ?>
                                แสดงผลการกรอง <?php echo number_format($totalRecipes); ?> รายการ
                            <?php else: ?>
                                มีสูตรอาหารในระบบทั้งหมด <?php echo number_format($totalRecipes); ?> รายการ
                            <?php endif; ?>
                        </small>
                    </div>

                    <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2 small">
                        <i class="bi bi-database me-1"></i>ตาราง MySQL: recipes
                    </span>
                </div>

                <?php if (!empty($recipes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th style="width: 50px;">#ID</th>
                                    <th style="width: 80px;">รูปภาพ</th>
                                    <th>ชื่อเมนูอาหาร</th>
                                    <th>หมวดหมู่</th>
                                    <th>เวลาปรุง</th>
                                    <th>จำนวน</th>
                                    <th>วัตถุดิบที่ใช้</th>
                                    <th>วันที่เพิ่ม</th>
                                    <th class="text-end" style="width: 140px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($recipes as $r): ?>
                                    <?php
                                    // ตรวจสอบรูปภาพ
                                    $hasImg = false;
                                    $imgPath = '';
                                    if (!empty($r['image'])) {
                                        if (filter_var($r['image'], FILTER_VALIDATE_URL)) {
                                            $hasImg = true;
                                            $imgPath = $r['image'];
                                        } elseif (file_exists(__DIR__ . '/../assets/images/' . $r['image'])) {
                                            $hasImg = true;
                                            $imgPath = '../assets/images/' . $r['image'];
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <!-- ID -->
                                        <td class="text-muted fw-bold"><?php echo (int) $r['id']; ?></td>

                                        <!-- Image Thumbnail -->
                                        <td>
                                            <?php if ($hasImg): ?>
                                                <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>" class="recipe-thumb">
                                            <?php else: ?>
                                                <div class="recipe-thumb-fallback">
                                                    <i class="bi <?php echo !empty($r['category_icon']) ? htmlspecialchars($r['category_icon']) : 'bi-egg-fried'; ?>"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Title & Excerpt -->
                                        <td>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($r['title']); ?></div>
                                            <?php if (!empty($r['description'])): ?>
                                                <small class="text-muted d-block text-truncate" style="max-width: 260px;">
                                                    <?php echo htmlspecialchars($r['description']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Category -->
                                        <td>
                                            <span class="badge bg-light text-dark border rounded-pill px-2 py-1">
                                                <i class="bi <?php echo !empty($r['category_icon']) ? htmlspecialchars($r['category_icon']) : 'bi-tag'; ?> me-1 text-primary"></i>
                                                <?php echo htmlspecialchars($r['category_name'] ?? 'ไม่มีหมวดหมู่'); ?>
                                            </span>
                                        </td>

                                        <!-- Cooking Time -->
                                        <td>
                                            <span class="text-nowrap text-muted">
                                                <i class="bi bi-clock text-warning me-1"></i><?php echo (int) $r['cooking_time']; ?> นาที
                                            </span>
                                        </td>

                                        <!-- Servings -->
                                        <td>
                                            <span class="text-nowrap text-muted">
                                                <i class="bi bi-people me-1"></i><?php echo (int) $r['servings']; ?> ที่
                                            </span>
                                        </td>

                                        <!-- Ingredients Count -->
                                        <td>
                                            <span class="badge <?php echo ((int)$r['ingredient_count'] > 0) ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?> rounded-pill">
                                                <i class="bi bi-basket2 me-1"></i><?php echo (int) $r['ingredient_count']; ?> รายการ
                                            </span>
                                        </td>

                                        <!-- Created At -->
                                        <td class="text-muted text-nowrap" style="font-size: 0.78rem;">
                                            <?php echo date('d/m/Y', strtotime($r['created_at'])); ?>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="text-end text-nowrap">
                                            <!-- Open Detail Front-End -->
                                            <a href="../recipe_detail.php?id=<?php echo (int) $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary action-btn" title="เปิดดูสูตรอาหารบนหน้าเว็บ">
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <!-- Edit Button -->
                                            <a href="recipe_edit.php?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-primary action-btn ms-1" title="แก้ไขข้อมูลสูตรอาหาร">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <!-- Delete Button (Trigger Modal) -->
                                            <button type="button" class="btn btn-sm btn-outline-danger action-btn ms-1" title="ลบสูตรอาหารนี้" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal" 
                                                    data-id="<?php echo (int) $r['id']; ?>" 
                                                    data-title="<?php echo htmlspecialchars($r['title'], ENT_QUOTES); ?>"
                                                    data-ingredients="<?php echo (int) $r['ingredient_count']; ?>">
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
                            <i class="bi bi-journal-x"></i>
                        </div>
                        <h3 class="h6 fw-bold text-dark mb-1">ไม่พบข้อมูลสูตรอาหาร</h3>
                        <p class="text-muted small mb-3">
                            <?php if ($search !== '' || $categoryFilter > 0): ?>
                                ไม่พบสูตรอาหารที่ตรงกับคำค้นหาหรือตัวกรองที่เลือก
                            <?php else: ?>
                                ยังไม่มีข้อมูลสูตรอาหารในระบบ เริ่มต้นสร้างเมนูอาหารแรกได้เลย
                            <?php endif; ?>
                        </p>
                        <?php if ($search !== '' || $categoryFilter > 0): ?>
                            <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>ล้างตัวกรองทั้งหมด
                            </a>
                        <?php else: ?>
                            <a href="recipe_add.php" class="btn btn-sm btn-kitchen-primary rounded-pill px-4 py-2">
                                <i class="bi bi-plus-circle me-1"></i>เพิ่มสูตรอาหารแรก
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="POST" action="recipe_delete.php">
                    <?php echo admin_csrf_input(); ?>
                    <input type="hidden" name="recipe_id" id="modalRecipeId" value="0">
                    
                    <div class="modal-body p-4 text-center">
                        <div style="width: 65px; height: 65px; border-radius: 50%; background-color: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem;" class="mb-3">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2" id="deleteModalLabel">ยืนยันการลบสูตรอาหาร</h5>
                        <p class="text-muted small mb-3" style="line-height: 1.6;">
                            คุณต้องการลบสูตรอาหาร <strong id="modalRecipeTitle" class="text-danger"></strong> ออกจากฐานข้อมูลใช่หรือไม่?
                        </p>
                        <div class="alert alert-warning border-0 small text-start py-2 px-3 rounded-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <span id="modalIngredientsWarning">รายการวัตถุดิบที่ผูกอยู่กับเมนูนี้ในตาราง <code>recipe_ingredients</code> จะถูกลบออกตามความสัมพันธ์อย่างถูกต้อง</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-light rounded-bottom-4 justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-danger rounded-pill px-4 py-2">
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

    <!-- Modal Event Handler Script -->
    <script>
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const recipeId = button.getAttribute('data-id');
            const recipeTitle = button.getAttribute('data-title');
            const ingCount = button.getAttribute('data-ingredients');

            document.getElementById('modalRecipeId').value = recipeId;
            document.getElementById('modalRecipeTitle').textContent = `"${recipeTitle}" (ID: ${recipeId})`;
            
            const warningEl = document.getElementById('modalIngredientsWarning');
            if (parseInt(ingCount) > 0) {
                warningEl.innerHTML = `ตรวจพบวัตถุดิบที่ผูกอยู่ <strong>${ingCount} รายการ</strong> ในตาราง <code>recipe_ingredients</code> ระบบจะลบความสัมพันธ์และสูตรอาหารนี้อย่างปลอดภัย`;
            } else {
                warningEl.innerHTML = `สูตรอาหารนี้ไม่มีรายการวัตถุดิบผูกอยู่ สามารถลบได้อย่างปลอดภัย`;
            }
        });
    }
    </script>

</body>
</html>

