<?php
/**
 * หน้าแก้ไขข้อมูลวัตถุดิบสำหรับผู้ดูแลระบบ (Edit Ingredient)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

// 1. ตรวจสอบสิทธิ์ผู้ดูแลระบบและดึงการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// 2. รับค่า ID ของวัตถุดิบและตรวจสอบความถูกต้อง
$ingredientId = filter_var($_GET['id'] ?? ($_POST['id'] ?? 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$ingredientId) {
    $_SESSION['flash_error'] = 'รหัสวัตถุดิบไม่ถูกต้องหรือไม่ระบุ';
    header('Location: ingredients.php');
    exit;
}

// 3. ดึงข้อมูลวัตถุดิบเดิมจากฐานข้อมูล MySQL (Prepared Statement)
try {
    $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $ingredientId]);
    $existingIngredient = $stmt->fetch();

    if (!$existingIngredient) {
        $_SESSION['flash_error'] = "ไม่พบวัตถุดิบรหัส #$ingredientId ในระบบ หรืออาจถูกลบไปแล้ว";
        header('Location: ingredients.php');
        exit;
    }

    // ดึงสูตรอาหารที่ใช้วัตถุดิบนี้ (ถ้ามี) เพื่อแสดงเป็นข้อมูลอ้างอิง
    $recipeStmt = $pdo->prepare("
        SELECT r.id, r.title, ri.quantity, ri.unit, ri.is_essential
        FROM recipe_ingredients ri
        JOIN recipes r ON ri.recipe_id = r.id
        WHERE ri.ingredient_id = :id
        ORDER BY r.title ASC
    ");
    $recipeStmt->execute([':id' => $ingredientId]);
    $usedRecipes = $recipeStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Load Ingredient Error: " . $e->getMessage());
    $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
    header('Location: ingredients.php');
    exit;
}

// 4. ดึงกลุ่มหมวดหมู่ที่มีอยู่จริงในระบบ
$existingGroups = ['เนื้อสัตว์', 'ไข่และแป้ง', 'ผักและสมุนไพร', 'เครื่องปรุงรส', 'ทั่วไป'];
try {
    $grpStmt = $pdo->query("SELECT DISTINCT category_group FROM ingredients WHERE category_group IS NOT NULL AND category_group != ''");
    $dbGroups = $grpStmt->fetchAll(PDO::FETCH_COLUMN);
    $existingGroups = array_unique(array_merge($existingGroups, $dbGroups));
    sort($existingGroups);
} catch (PDOException $e) {
    error_log("Load Category Groups Error: " . $e->getMessage());
}

$errors = [];
$formData = [
    'name' => $existingIngredient['name'],
    'type' => $existingIngredient['type'],
    'category_group' => $existingIngredient['category_group'] ?? 'ทั่วไป',
    'custom_group' => ''
];

// 5. จัดการเมื่อมีการ Submit ฟอร์มแก้ไข (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 5.1 ตรวจสอบ CSRF Token
    if (!admin_verify_csrf()) {
        $errors[] = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        
        $formData['name'] = trim($_POST['name'] ?? '');
        $formData['type'] = trim($_POST['type'] ?? 'main');
        $formData['category_group'] = trim($_POST['category_group'] ?? 'ทั่วไป');
        $formData['custom_group'] = trim($_POST['custom_group'] ?? '');

        // หากผู้ใช้เลือก "กำหนดเอง" ให้ใช้ค่าจาก custom_group
        if ($formData['category_group'] === '__custom__') {
            $finalGroup = $formData['custom_group'];
        } else {
            $finalGroup = $formData['category_group'];
        }
        if ($finalGroup === '') {
            $finalGroup = 'ทั่วไป';
        }

        // 5.2 ตรวจสอบข้อมูลฟิลด์บังคับ (Validation)
        if ($formData['name'] === '') {
            $errors[] = 'กรุณากรอกชื่อวัตถุดิบ';
        } elseif (mb_strlen($formData['name']) > 100) {
            $errors[] = 'ชื่อวัตถุดิบต้องมีความยาวไม่เกิน 100 ตัวอักษร';
        } else {
            // ตรวจสอบชื่อซ้ำ (ยกเว้น ID ปัจจุบัน)
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM ingredients WHERE name = :name AND id != :id LIMIT 1");
                $checkStmt->execute([':name' => $formData['name'], ':id' => $ingredientId]);
                if ($checkStmt->fetch()) {
                    $errors[] = 'ชื่อวัตถุดิบ "' . htmlspecialchars($formData['name']) . '" มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น';
                }
            } catch (PDOException $e) {
                error_log("Check Duplicate Ingredient Error: " . $e->getMessage());
            }
        }

        if (!in_array($formData['type'], ['main', 'seasoning'], true)) {
            $errors[] = 'ประเภทวัตถุดิบไม่ถูกต้อง (ต้องเป็นวัตถุดิบหลัก หรือเครื่องปรุงรส)';
        }

        if (mb_strlen($finalGroup) > 50) {
            $errors[] = 'ชื่อกลุ่มหมวดหมู่ต้องมีความยาวไม่เกิน 50 ตัวอักษร';
        }

        // 5.3 อัปเดตข้อมูลลงฐานข้อมูล MySQL (Prepared Statement)
        if (empty($errors)) {
            try {
                $updateSql = "
                    UPDATE ingredients 
                    SET name = :name, 
                        type = :type, 
                        category_group = :category_group
                    WHERE id = :id
                ";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':name' => $formData['name'],
                    ':type' => $formData['type'],
                    ':category_group' => $finalGroup,
                    ':id' => $ingredientId
                ]);

                $_SESSION['flash_success'] = "บันทึกการแก้ไขวัตถุดิบ \"" . htmlspecialchars($formData['name']) . "\" (ID: #$ingredientId) สำเร็จแล้ว";
                header('Location: ingredients.php');
                exit;

            } catch (PDOException $e) {
                error_log("Update Ingredient Error: " . $e->getMessage());
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
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
    <title>แก้ไขวัตถุดิบ #<?php echo (int)$ingredientId; ?> | ระบบจัดการหลังบ้าน - มีอะไรในครัว?</title>
    
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
        padding: 2.2rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    .form-label {
        font-weight: 500;
        color: #333;
        margin-bottom: 0.4rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(224, 93, 38, 0.15);
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

                    <a href="ingredients.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
                        <i class="bi bi-arrow-left me-1"></i>
                        <span>กลับรายการ</span>
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
                    <li class="breadcrumb-item"><a href="ingredients.php" class="text-decoration-none text-muted">จัดการวัตถุดิบ</a></li>
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">แก้ไขวัตถุดิบ #<?php echo (int)$ingredientId; ?></li>
                </ol>
            </nav>

            <!-- Page Title -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลวัตถุดิบ
                    </h1>
                    <p class="text-muted small mb-0">กำลังแก้ไข: <strong class="text-dark"><?php echo htmlspecialchars($existingIngredient['name']); ?></strong> (รหัส #<?php echo (int)$ingredientId; ?>)</p>
                </div>
                <div>
                    <a href="ingredients.php" class="btn btn-outline-secondary rounded-pill px-3 py-2 small">
                        <i class="bi bi-x-circle me-1"></i>ยกเลิก
                    </a>
                </div>
            </div>

            <!-- Error Notification -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 rounded-4 shadow-sm p-3 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2 text-danger-emphasis fw-bold small">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <span>พบข้อผิดพลาด กรุณาตรวจสอบข้อมูลด้านล่าง:</span>
                    </div>
                    <ul class="mb-0 small ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Edit Ingredient Form & Recipes Info -->
            <div class="row g-4 justify-content-center">
                
                <!-- Left: Form Box (col-lg-7) -->
                <div class="col-lg-7">
                    <div class="content-box">
                        
                        <form method="POST" action="ingredient_edit.php?id=<?php echo (int)$ingredientId; ?>">
                            <?php echo admin_csrf_input(); ?>
                            <input type="hidden" name="id" value="<?php echo (int)$ingredientId; ?>">

                            <h2 class="h6 fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="bi bi-card-checklist text-primary me-2"></i>ข้อมูลวัตถุดิบ
                            </h2>

                            <!-- Ingredient Name -->
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    ชื่อวัตถุดิบ <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control rounded-3" id="name" name="name" 
                                       placeholder="เช่น อกไก่, เต้าหู้ไข่, น้ำตาลมะพร้าว..." 
                                       value="<?php echo htmlspecialchars($formData['name']); ?>" required maxlength="100">
                                <div class="form-text small">ชื่อวัตถุดิบต้องไม่ซ้ำกับที่มีอยู่ในระบบ</div>
                            </div>

                            <!-- Ingredient Type -->
                            <div class="mb-3">
                                <label class="form-label d-block">
                                    ประเภทวัตถุดิบ <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <div class="border rounded-3 p-3 bg-light">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="type" id="type_main" value="main" <?php echo ($formData['type'] === 'main') ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold text-success-emphasis" for="type_main">
                                                    <i class="bi bi-egg-fried me-1 text-success"></i> วัตถุดิบหลัก (Main)
                                                </label>
                                                <small class="d-block text-muted mt-1" style="font-size: 0.78rem;">
                                                    ของสด เนื้อสัตว์ ผัก แป้ง
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="border rounded-3 p-3 bg-light">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="type" id="type_seasoning" value="seasoning" <?php echo ($formData['type'] === 'seasoning') ? 'checked' : ''; ?>>
                                                <label class="form-check-label fw-bold text-warning-emphasis" for="type_seasoning">
                                                    <i class="bi bi-cup-hot me-1 text-warning"></i> เครื่องปรุงรส (Seasoning)
                                                </label>
                                                <small class="d-block text-muted mt-1" style="font-size: 0.78rem;">
                                                    น้ำปลา ซอส เครื่องปรุง
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Category Group -->
                            <div class="mb-4">
                                <label for="category_group" class="form-label">
                                    กลุ่มหมวดหมู่วัตถุดิบ <span class="text-danger">*</span>
                                </label>
                                <select class="form-select rounded-3 mb-2" id="category_group" name="category_group" required onchange="toggleCustomGroup(this.value)">
                                    <?php foreach ($existingGroups as $grp): ?>
                                        <option value="<?php echo htmlspecialchars($grp); ?>" <?php echo ($formData['category_group'] === $grp) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grp); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__custom__" <?php echo ($formData['category_group'] === '__custom__') ? 'selected' : ''; ?>>
                                        + กำหนดกลุ่มหมวดหมู่ใหม่...
                                    </option>
                                </select>

                                <!-- Custom Group Input -->
                                <div id="customGroupContainer" style="display: <?php echo ($formData['category_group'] === '__custom__') ? 'block' : 'none'; ?>;">
                                    <input type="text" class="form-control rounded-3" id="custom_group" name="custom_group" 
                                           placeholder="พิมพ์ชื่อกลุ่มหมวดหมู่ใหม่..." 
                                           value="<?php echo htmlspecialchars($formData['custom_group']); ?>" maxlength="50">
                                </div>
                                <div class="form-text small">ใช้สำหรับจัดกลุ่มวัตถุดิบในหน้าเลือกวัตถุดิบ <code>ingredients.php</code></div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                <a href="ingredients.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                    ยกเลิก
                                </a>
                                <button type="submit" class="btn btn-kitchen-primary rounded-pill px-4 py-2 shadow-sm fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i> บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>

                        </form>

                    </div>
                </div>

                <!-- Right: Recipes Reference Info (col-lg-5) -->
                <div class="col-lg-5">
                    <div class="content-box">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h2 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-journal-bookmark text-primary me-2"></i>การใช้งานในสูตรอาหาร
                            </h2>
                            <span class="badge rounded-pill <?php echo !empty($usedRecipes) ? 'bg-info-subtle text-info-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?>">
                                <?php echo count($usedRecipes); ?> เมนู
                            </span>
                        </div>

                        <?php if (!empty($usedRecipes)): ?>
                            <p class="small text-muted mb-3">
                                วัตถุดิบนี้ถูกนำไปใช้ในสูตรอาหารต่อไปนี้ การแก้ไขชื่อวัตถุดิบจะส่งผลต่อการค้นหาของเมนูเหล่านี้ด้วย:
                            </p>
                            <div class="list-group list-group-flush border rounded-3 mb-3">
                                <?php foreach ($usedRecipes as $r): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center p-2 px-3 small">
                                        <div>
                                            <a href="../recipe_detail.php?id=<?php echo (int) $r['id']; ?>" target="_blank" class="text-decoration-none fw-semibold text-dark">
                                                <i class="bi bi-arrow-up-right-square small text-primary me-1"></i>
                                                <?php echo htmlspecialchars($r['title']); ?>
                                            </a>
                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                ปริมาณ: <?php echo htmlspecialchars($r['quantity'] ?? '-'); ?> <?php echo htmlspecialchars($r['unit'] ?? ''); ?>
                                                (<?php echo ((int)$r['is_essential'] === 1) ? '<span class="text-danger">จำเป็น</span>' : '<span class="text-secondary">เสริม</span>'; ?>)
                                            </div>
                                        </div>
                                        <a href="recipe_edit.php?id=<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.75rem;" title="แก้ไขสูตรอาหารนี้">
                                            แก้ไขสูตร
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="alert alert-warning border-0 small py-2 px-3 rounded-3 mb-0">
                                <i class="bi bi-shield-lock me-1"></i>
                                <strong>หมายเหตุความปลอดภัย:</strong> หากมีสูตรอาหารใช้งานอยู่ ระบบจะไม่อนุญาตให้ลบวัตถุดิบนี้ เพื่อป้องกันไม่ให้ข้อมูลสูตรอาหารเสียหาย
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-info-circle fs-3 text-secondary d-block mb-2"></i>
                                ยังไม่มีสูตรอาหารใดนำวัตถุดิบนี้ไปใช้งาน<br>
                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill mt-2">
                                    <i class="bi bi-shield-check me-1"></i>สามารถลบได้อย่างปลอดภัย
                                </span>
                            </div>
                        <?php endif; ?>

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

    <script>
    function toggleCustomGroup(val) {
        const container = document.getElementById('customGroupContainer');
        const customInput = document.getElementById('custom_group');
        if (val === '__custom__') {
            container.style.display = 'block';
            customInput.focus();
        } else {
            container.style.display = 'none';
        }
    }
    </script>

</body>
</html>

