<?php
/**
 * ตัวประมวลผลการลบวัตถุดิบและตรวจสอบความสัมพันธ์ Foreign Key (Delete Ingredient)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

// 1. ตรวจสอบสิทธิ์ผู้ดูแลระบบและดึงการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// 2. จัดการคำขอลบข้อมูลผ่าน POST (มาจาก Modal หรือหน้าจอยืนยัน)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2.1 ตรวจสอบความถูกต้องของ CSRF Token
    if (!admin_verify_csrf()) {
        $_SESSION['flash_error'] = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        header('Location: ingredients.php');
        exit;
    }

    $ingredientId = filter_var($_POST['ingredient_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$ingredientId) {
        $_SESSION['flash_error'] = 'รหัสวัตถุดิบที่ต้องการลบไม่ถูกต้อง';
        header('Location: ingredients.php');
        exit;
    }

    try {
        // 2.2 ตรวจสอบว่าวัตถุดิบมีอยู่จริงในฐานข้อมูล
        $checkStmt = $pdo->prepare("SELECT id, name FROM ingredients WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $ingredientId]);
        $ingredient = $checkStmt->fetch();

        if (!$ingredient) {
            $_SESSION['flash_error'] = "ไม่พบวัตถุดิบรหัส #$ingredientId ในระบบ หรืออาจถูกลบไปแล้ว";
            header('Location: ingredients.php');
            exit;
        }

        // 2.3 ตรวจสอบความสัมพันธ์ใน recipe_ingredients ว่ามีสูตรอาหารใช้อ้างอิงอยู่หรือไม่
        $refStmt = $pdo->prepare("
            SELECT r.id, r.title 
            FROM recipe_ingredients ri
            JOIN recipes r ON ri.recipe_id = r.id
            WHERE ri.ingredient_id = :id
            ORDER BY r.title ASC
        ");
        $refStmt->execute([':id' => $ingredientId]);
        $usedRecipes = $refStmt->fetchAll();

        // หากมีสูตรอาหารใช้อ้างอิงอยู่ ห้ามลบโดยเด็ดขาดตามข้อกำหนด
        if (!empty($usedRecipes)) {
            $recipeTitles = array_column($usedRecipes, 'title');
            $titlesStr = implode(', ', array_slice($recipeTitles, 0, 5));
            if (count($recipeTitles) > 5) {
                $titlesStr .= ' และอื่นๆ อีก ' . (count($recipeTitles) - 5) . ' เมนู';
            }

            $_SESSION['flash_error'] = "ไม่สามารถลบวัตถุดิบ \"" . htmlspecialchars($ingredient['name']) . "\" ได้! เนื่องจากมีสูตรอาหารใช้อ้างอิงอยู่ " . count($usedRecipes) . " เมนู ($titlesStr) กรุณานำวัตถุดิบนี้ออกจากสูตรอาหารทั้งหมดก่อนทำการลบ เพื่อป้องกันไม่ให้ข้อมูลสูตรอาหารเสียหาย";
            header('Location: ingredients.php');
            exit;
        }

        // 2.4 หากไม่มีสูตรอาหารใดใช้งาน สามารถลบออกจากฐานข้อมูลได้อย่างปลอดภัย
        $delStmt = $pdo->prepare("DELETE FROM ingredients WHERE id = :id");
        $delStmt->execute([':id' => $ingredientId]);

        $_SESSION['flash_success'] = "ลบวัตถุดิบ \"" . htmlspecialchars($ingredient['name']) . "\" (ID: #$ingredientId) ออกจากระบบเรียบร้อยแล้ว";
        header('Location: ingredients.php');
        exit;

    } catch (PDOException $e) {
        error_log("Delete Ingredient Error: " . $e->getMessage());
        $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการลบวัตถุดิบ: " . $e->getMessage();
        header('Location: ingredients.php');
        exit;
    }
}

// 3. กรณีเข้าถึงผ่าน GET โดยตรง (แสดงหน้าจอยืนยันการลบ)
$ingredientId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$ingredientId) {
    $_SESSION['flash_error'] = 'รหัสวัตถุดิบไม่ถูกต้อง';
    header('Location: ingredients.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $ingredientId]);
    $ingredient = $stmt->fetch();

    if (!$ingredient) {
        $_SESSION['flash_error'] = "ไม่พบวัตถุดิบรหัส #$ingredientId ในระบบ";
        header('Location: ingredients.php');
        exit;
    }

    // ตรวจสอบการใช้งานในสูตรอาหาร
    $refStmt = $pdo->prepare("
        SELECT r.id, r.title 
        FROM recipe_ingredients ri
        JOIN recipes r ON ri.recipe_id = r.id
        WHERE ri.ingredient_id = :id
        ORDER BY r.title ASC
    ");
    $refStmt->execute([':id' => $ingredientId]);
    $usedRecipes = $refStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Get Ingredient for Delete Error: " . $e->getMessage());
    $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    header('Location: ingredients.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันการลบวัตถุดิบ #<?php echo (int)$ingredientId; ?> | ระบบจัดการหลังบ้าน</title>
    
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
        padding: 2.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }
    </style>
</head>
<body>

    <!-- Admin Navigation Bar -->
    <nav class="admin-navbar sticky-top">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between">
                <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2">
                    <span class="brand-icon" style="background-color: var(--primary-light); color: var(--primary-color); width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1.3rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </span>
                    <div>
                        <span class="fw-bold fs-5 text-dark">มีอะไรในครัว?</span>
                        <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.7rem;">Admin</span>
                    </div>
                </a>

                <div class="d-flex align-items-center gap-2">
                    <a href="ingredients.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
                        <i class="bi bi-arrow-left me-1"></i>กลับหน้ารายการ
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-5 flex-grow-1 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="content-box text-center">
                        
                        <?php if (!empty($usedRecipes)): ?>
                            <!-- กรณีมีสูตรอาหารใช้อ้างอิงอยู่ (ไม่อนุญาตให้ลบ) -->
                            <div style="width: 75px; height: 75px; border-radius: 50%; background-color: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem;" class="mb-3">
                                <i class="bi bi-shield-x"></i>
                            </div>

                            <h1 class="h4 fw-bold text-dark mb-2">ไม่สามารถลบวัตถุดิบนี้ได้!</h1>
                            <p class="text-muted small mb-3">
                                วัตถุดิบ: <strong class="text-dark">"<?php echo htmlspecialchars($ingredient['name']); ?>"</strong> (ID: #<?php echo (int)$ingredient['id']; ?>)
                            </p>

                            <div class="alert alert-danger border-0 small text-start py-3 px-3 rounded-3 mb-4">
                                <div class="fw-bold mb-1">
                                    <i class="bi bi-exclamation-octagon-fill me-1"></i> มีสูตรอาหารใช้อ้างอิงอยู่ <?php echo count($usedRecipes); ?> เมนู:
                                </div>
                                <ul class="mb-2 ps-3">
                                    <?php foreach ($usedRecipes as $r): ?>
                                        <li><?php echo htmlspecialchars($r['title']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="text-danger-emphasis">
                                    <i class="bi bi-info-circle me-1"></i>
                                    เพื่อป้องกันไม่ให้ข้อมูลสูตรอาหารเสียหาย ระบบไม่อนุญาตให้ลบวัตถุดิบจนกว่าจะนำวัตถุดิบนี้ออกจากสูตรอาหารทั้งหมดก่อน
                                </div>
                            </div>

                            <a href="ingredients.php" class="btn btn-primary rounded-pill px-4 py-2">
                                <i class="bi bi-arrow-left me-1"></i> กลับสู่หน้ารายการวัตถุดิบ
                            </a>

                        <?php else: ?>
                            <!-- กรณีไม่มีสูตรอาหารใช้งาน (สามารถลบได้) -->
                            <div style="width: 75px; height: 75px; border-radius: 50%; background-color: #fef3c7; color: #d97706; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem;" class="mb-3">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>

                            <h1 class="h4 fw-bold text-dark mb-2">ยืนยันการลบวัตถุดิบ</h1>
                            <p class="text-muted small mb-4">
                                คุณต้องการลบวัตถุดิบนี้ออกจากฐานข้อมูล MySQL หรือไม่?<br>การดำเนินการนี้ไม่สามารถยกเลิกได้
                            </p>

                            <div class="card border rounded-3 p-3 text-start bg-light mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-secondary rounded-pill">ID #<?php echo (int)$ingredient['id']; ?></span>
                                    <span class="badge bg-light text-dark border rounded-pill"><?php echo htmlspecialchars($ingredient['category_group'] ?? 'ทั่วไป'); ?></span>
                                </div>
                                <h2 class="h5 fw-bold text-dark mb-0"><?php echo htmlspecialchars($ingredient['name']); ?></h2>
                                <span class="small text-muted">
                                    ประเภท: <?php echo ($ingredient['type'] === 'main') ? 'วัตถุดิบหลัก' : 'เครื่องปรุงรส'; ?>
                                </span>
                            </div>

                            <div class="alert alert-success border-0 small text-start py-2 px-3 rounded-3 mb-4">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                ปลอดภัย: วัตถุดิบนี้ไม่ได้ถูกใช้อ้างอิงในสูตรอาหารใด สามารถลบได้โดยไม่กระทบข้อมูลส่วนอื่น
                            </div>

                            <form method="POST" action="ingredient_delete.php">
                                <?php echo admin_csrf_input(); ?>
                                <input type="hidden" name="ingredient_id" value="<?php echo (int)$ingredient['id']; ?>">

                                <div class="d-flex justify-content-center gap-3">
                                    <a href="ingredients.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                        <i class="bi bi-x-circle me-1"></i> ยกเลิก
                                    </a>
                                    <button type="submit" class="btn btn-danger rounded-pill px-4 py-2">
                                        <i class="bi bi-trash3 me-1"></i> ยืนยันการลบ
                                    </button>
                                </div>
                            </form>
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

</body>
</html>

