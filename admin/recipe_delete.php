<?php
/**
 * หน้าลบสูตรอาหารและจัดการความสัมพันธ์ Foreign Key (Delete Recipe)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

// 1. ตรวจสอบสิทธิ์ผู้ดูแลระบบและดึงการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// 2. จัดการคำขอลบข้อมูลผ่าน POST (มาจาก Modal หรือหน้ายืนยัน)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2.1 ตรวจสอบความถูกต้องของ CSRF Token
    if (!admin_verify_csrf()) {
        $_SESSION['flash_error'] = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
        header('Location: recipes.php');
        exit;
    }

    $recipeId = filter_var($_POST['recipe_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$recipeId) {
        $_SESSION['flash_error'] = 'รหัสสูตรอาหารที่ต้องการลบไม่ถูกต้อง';
        header('Location: recipes.php');
        exit;
    }

    try {
        // 2.2 ตรวจสอบว่าสูตรอาหารมีอยู่จริงในฐานข้อมูลก่อนลบ
        $checkStmt = $pdo->prepare("SELECT id, title, image FROM recipes WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $recipeId]);
        $recipe = $checkStmt->fetch();

        if (!$recipe) {
            $_SESSION['flash_error'] = "ไม่พบสูตรอาหารรหัส #$recipeId ในระบบ หรืออาจถูกลบไปแล้ว";
            header('Location: recipes.php');
            exit;
        }

        // 2.3 ตรวจสอบและจัดการความสัมพันธ์กับ recipe_ingredients ก่อนลบ (Database Transaction)
        $pdo->beginTransaction();

        // ตรวจสอบจำนวนวัตถุดิบที่ผูกอยู่
        $countRiStmt = $pdo->prepare("SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = :recipe_id");
        $countRiStmt->execute([':recipe_id' => $recipeId]);
        $ingredientCount = (int) $countRiStmt->fetchColumn();

        // ลบความสัมพันธ์ในตาราง recipe_ingredients
        if ($ingredientCount > 0) {
            $delRiStmt = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = :recipe_id");
            $delRiStmt->execute([':recipe_id' => $recipeId]);
        }

        // ลบข้อมูลสูตรอาหารในตาราง recipes
        $delRecipeStmt = $pdo->prepare("DELETE FROM recipes WHERE id = :id");
        $delRecipeStmt->execute([':id' => $recipeId]);

        // ยืนยันการเปลี่ยนแปลง (Commit Transaction)
        $pdo->commit();

        // 2.4 ตรวจสอบและลบไฟล์รูปภาพที่อัปโหลดไว้ (เฉพาะไฟล์ที่ขึ้นต้นด้วย recipe_ ในโฟลเดอร์ assets/images)
        if (!empty($recipe['image']) && str_starts_with($recipe['image'], 'recipe_')) {
            $imageFile = __DIR__ . '/../assets/images/' . $recipe['image'];
            if (file_exists($imageFile) && is_file($imageFile)) {
                @unlink($imageFile);
            }
        }

        // 2.5 บันทึกข้อความแจ้งผลสำเร็จและส่งกลับไปหน้ารายการสูตรอาหาร
        $msgDetails = ($ingredientCount > 0) ? " และลบรายการวัตถุดิบที่เกี่ยวข้อง ($ingredientCount รายการ)" : "";
        $_SESSION['flash_success'] = "ลบสูตรอาหาร \"" . htmlspecialchars($recipe['title']) . "\" (ID: $recipeId){$msgDetails} ออกจากระบบเรียบร้อยแล้ว";
        header('Location: recipes.php');
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete Recipe Error: " . $e->getMessage());
        $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการลบสูตรอาหาร: " . $e->getMessage();
        header('Location: recipes.php');
        exit;
    }
}

// 3. กรณีเข้าถึงผ่าน GET โดยตรง (แสดงหน้าจอยืนยันการลบ)
$recipeId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$recipeId) {
    $_SESSION['flash_error'] = 'รหัสสูตรอาหารไม่ถูกต้อง';
    header('Location: recipes.php');
    exit;
}

try {
    // ดึงข้อมูลสูตรอาหารพร้อมจำนวนวัตถุดิบที่ผูกอยู่
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.cooking_time, r.servings, c.name AS category_name, COUNT(ri.id) AS ingredient_count
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
        WHERE r.id = :id
        GROUP BY r.id
        LIMIT 1
    ");
    $stmt->execute([':id' => $recipeId]);
    $recipe = $stmt->fetch();

    if (!$recipe) {
        $_SESSION['flash_error'] = "ไม่พบสูตรอาหารรหัส #$recipeId ในระบบ";
        header('Location: recipes.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Get Recipe for Delete Error: " . $e->getMessage());
    $_SESSION['flash_error'] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    header('Location: recipes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันการลบสูตรอาหาร #<?php echo (int)$recipeId; ?> | ระบบจัดการหลังบ้าน</title>
    
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
                    <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
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
                        
                        <div style="width: 75px; height: 75px; border-radius: 50%; background-color: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem;" class="mb-3">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>

                        <h1 class="h4 fw-bold text-dark mb-2">ยืนยันการลบสูตรอาหาร</h1>
                        <p class="text-muted small mb-4">
                            คุณต้องการลบข้อมูลสูตรอาหารนี้ออกจากฐานข้อมูล MySQL หรือไม่?<br>การดำเนินการนี้ไม่สามารถยกเลิกได้
                        </p>

                        <!-- Recipe Info Card -->
                        <div class="card border rounded-3 p-3 text-start bg-light mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-danger rounded-pill">รหัส #<?php echo (int)$recipe['id']; ?></span>
                                <span class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($recipe['category_name'] ?? 'ไม่มีหมวดหมู่'); ?></span>
                            </div>
                            <h2 class="h5 fw-bold text-dark mb-2"><?php echo htmlspecialchars($recipe['title']); ?></h2>
                            <div class="small text-muted d-flex gap-3">
                                <span><i class="bi bi-clock me-1"></i><?php echo (int)$recipe['cooking_time']; ?> นาที</span>
                                <span><i class="bi bi-people me-1"></i><?php echo (int)$recipe['servings']; ?> ที่</span>
                                <span><i class="bi bi-basket2 me-1"></i><?php echo (int)$recipe['ingredient_count']; ?> วัตถุดิบ</span>
                            </div>
                        </div>

                        <!-- Foreign Key Notice -->
                        <?php if ((int)$recipe['ingredient_count'] > 0): ?>
                            <div class="alert alert-warning border-0 small text-start py-2 px-3 rounded-3 mb-4">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                ตรวจพบความสัมพันธ์: ระบบจะลบรายการวัตถุดิบ <strong><?php echo (int)$recipe['ingredient_count']; ?> รายการ</strong> ที่ผูกอยู่ในตาราง <code>recipe_ingredients</code> ออกอย่างสมบูรณ์
                            </div>
                        <?php endif; ?>

                        <!-- Form Submission with CSRF -->
                        <form method="POST" action="recipe_delete.php">
                            <?php echo admin_csrf_input(); ?>
                            <input type="hidden" name="recipe_id" value="<?php echo (int)$recipe['id']; ?>">

                            <div class="d-flex justify-content-center gap-3">
                                <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                    <i class="bi bi-x-circle me-1"></i> ยกเลิก
                                </a>
                                <button type="submit" class="btn btn-danger rounded-pill px-4 py-2">
                                    <i class="bi bi-trash3 me-1"></i> ยืนยันการลบข้อมูล
                                </button>
                            </div>
                        </form>

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

