<?php
/**
 * หน้ารายละเอียดสูตรอาหาร - มีอะไรในครัว? (What's in My Kitchen)
 */
$currentPage = 'recipes';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

// เริ่มเซสชันเพื่อจัดการการนับยอดเข้าชม (ป้องกันนับซ้ำจากการรีเฟรชในเซสชันเดียวกัน)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. รับค่า ID ของสูตรอาหารจาก URL และตรวจสอบความถูกต้อง
$recipeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

$recipe = null;
$essentialIngredients = [];
$optionalIngredients = [];
$relatedRecipes = [];
$dbError = null;

if ($recipeId) {
    try {
        // 3. ค้นหาข้อมูลสูตรอาหารจากตาราง recipes เชื่อมกับ categories (Prepared Statement)
        $recipeStmt = $pdo->prepare("
            SELECT 
                r.id, 
                r.category_id, 
                r.title, 
                r.description, 
                r.image, 
                r.cooking_time, 
                r.servings, 
                r.instructions, 
                r.views,
                r.created_at,
                c.name AS category_name, 
                c.icon AS category_icon,
                c.description AS category_description
            FROM recipes r
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.id = :id
            LIMIT 1
        ");
        $recipeStmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
        $recipeStmt->execute();
        $recipe = $recipeStmt->fetch();

        if ($recipe) {
            // นับยอดเข้าชมเมื่อเปิดดูสูตรอาหารสำเร็จ (ป้องกันนับซ้ำจากการรีเฟรชหรือเปิดซ้ำในเซสชันเดียวกัน)
            if (!isset($_SESSION['viewed_recipes']) || !is_array($_SESSION['viewed_recipes'])) {
                $_SESSION['viewed_recipes'] = [];
            }

            if (!in_array((int)$recipeId, $_SESSION['viewed_recipes'], true)) {
                $viewStmt = $pdo->prepare("UPDATE recipes SET views = views + 1 WHERE id = :id");
                $viewStmt->bindValue(':id', $recipeId, PDO::PARAM_INT);
                $viewStmt->execute();

                $_SESSION['viewed_recipes'][] = (int)$recipeId;
                $recipe['views'] = (int)($recipe['views'] ?? 0) + 1;
            }

            // 4. ดึงรายการวัตถุดิบผ่านความสัมพันธ์ตาราง recipe_ingredients และ ingredients (Prepared Statement)
            $riStmt = $pdo->prepare("
                SELECT 
                    ri.id, 
                    ri.quantity, 
                    ri.unit, 
                    ri.is_essential, 
                    i.name AS ingredient_name, 
                    i.type AS ingredient_type,
                    i.category_group
                FROM recipe_ingredients ri
                JOIN ingredients i ON ri.ingredient_id = i.id
                WHERE ri.recipe_id = :recipe_id
                ORDER BY ri.is_essential DESC, i.category_group ASC, i.name ASC
            ");
            $riStmt->bindValue(':recipe_id', $recipeId, PDO::PARAM_INT);
            $riStmt->execute();
            $ingredientsList = $riStmt->fetchAll();

            // แยกหมวดหมู่วัตถุดิบจำเป็น และวัตถุดิบทางเลือก/เครื่องปรุง
            foreach ($ingredientsList as $ing) {
                if ($ing['is_essential'] == 1) {
                    $essentialIngredients[] = $ing;
                } else {
                    $optionalIngredients[] = $ing;
                }
            }

            // 5. ดึงสูตรอาหารอื่นในหมวดเดียวกัน หรือเมนูแนะนำเพิ่มเติม (Related Recipes)
            $relatedStmt = $pdo->prepare("
                SELECT 
                    r.id, 
                    r.title, 
                    r.image, 
                    r.cooking_time, 
                    r.servings, 
                    c.name AS category_name, 
                    c.icon AS category_icon
                FROM recipes r
                LEFT JOIN categories c ON r.category_id = c.id
                WHERE r.id != :current_id
                ORDER BY (r.category_id = :cat_id) DESC, r.id ASC
                LIMIT 3
            ");
            $relatedStmt->bindValue(':current_id', $recipeId, PDO::PARAM_INT);
            $relatedStmt->bindValue(':cat_id', $recipe['category_id'], PDO::PARAM_INT);
            $relatedStmt->execute();
            $relatedRecipes = $relatedStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Recipe Detail Page Error: " . $e->getMessage());
        $dbError = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง";
    }
}

// กำหนดชื่อหัวเรื่องของหน้าเว็บ (Page Title)
if ($recipe) {
    $pageTitle = htmlspecialchars($recipe['title']) . ' | สูตรอาหาร - มีอะไรในครัว?';
} else {
    $pageTitle = 'ไม่พบสูตรอาหาร | มีอะไรในครัว?';
}

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$recipe): ?>
    <!-- กรณีไม่พบ ID หรือไม่พบข้อมูลสูตรอาหารในฐานข้อมูล (Empty / 404 State) -->
    <section class="py-5" style="background-color: var(--bg-cream); min-height: 550px;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 text-center">
                    <div class="card border-0 shadow-sm rounded-4 p-5 bg-white">
                        <div class="mb-4" style="width: 90px; height: 90px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.8rem; margin: 0 auto; box-shadow: 0 4px 15px rgba(224, 93, 38, 0.15);">
                            <i class="bi bi-journal-x"></i>
                        </div>
                        <h1 class="h4 fw-bold text-dark mb-2">ไม่พบสูตรอาหารที่คุณต้องการ</h1>
                        <p class="text-muted mb-4 small" style="line-height: 1.7;">
                            <?php if ($dbError): ?>
                                <?php echo htmlspecialchars($dbError); ?>
                            <?php else: ?>
                                ไม่พบรหัสสูตรอาหารนี้ในระบบ หรือสูตรอาหารอาจถูกย้ายหรือลบออกไปแล้ว กรุณาตรวจสอบรหัสเมนูอีกครั้ง
                            <?php endif; ?>
                        </p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="recipes.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2">
                                <i class="bi bi-journal-bookmark me-1"></i> ดูสูตรอาหารทั้งหมด
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                                <i class="bi bi-house-door me-1"></i> กลับสู่หน้าแรก
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <?php
    // ตรวจสอบความมีอยู่ของไฟล์รูปภาพจริงในระบบ
    $hasImage = false;
    $imagePath = '';
    if (!empty($recipe['image'])) {
        if (filter_var($recipe['image'], FILTER_VALIDATE_URL)) {
            $hasImage = true;
            $imagePath = $recipe['image'];
        } elseif (file_exists(__DIR__ . '/assets/images/' . $recipe['image'])) {
            $hasImage = true;
            $imagePath = 'assets/images/' . $recipe['image'];
        }
    }
    ?>

    <!-- Breadcrumb & Header Banner Section -->
    <section class="py-4" style="background: radial-gradient(circle at top right, #fff4ee 0%, var(--bg-cream) 70%); border-bottom: 1px solid var(--border-subtle);">
        <div class="container">
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item">
                        <a href="index.php" class="text-decoration-none" style="color: var(--primary-color);">
                            <i class="bi bi-house-door me-1"></i>หน้าแรก
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="recipes.php" class="text-decoration-none" style="color: var(--primary-color);">
                            สูตรอาหาร
                        </a>
                    </li>
                    <?php if (!empty($recipe['category_name'])): ?>
                        <li class="breadcrumb-item">
                            <a href="recipes.php?category=<?php echo (int)$recipe['category_id']; ?>" class="text-decoration-none" style="color: var(--primary-color);">
                                <?php echo htmlspecialchars($recipe['category_name']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page" style="color: var(--text-muted);">
                        <?php echo htmlspecialchars($recipe['title']); ?>
                    </li>
                </ol>
            </nav>

            <div class="row align-items-center">
                <div class="col-lg-8">
                    <!-- Category Badge & Views Badge -->
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <a href="recipes.php?category=<?php echo (int)$recipe['category_id']; ?>" class="badge rounded-pill text-decoration-none px-3 py-2" style="background-color: var(--primary-light); color: var(--primary-color); border: 1px solid rgba(224,93,38,0.2); font-weight: 500;">
                            <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1"></i>
                            หมวดหมู่: <?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                        </a>
                        <span class="badge rounded-pill bg-white text-secondary border px-3 py-2 shadow-xs" style="font-weight: 500;">
                            <i class="bi bi-eye text-primary me-1"></i>เข้าชม <?php echo number_format((int)($recipe['views'] ?? 0)); ?> ครั้ง
                        </span>
                    </div>
                    
                    <!-- Recipe Title -->
                    <h1 class="display-6 fw-bold text-dark mb-2">
                        <?php echo htmlspecialchars($recipe['title']); ?>
                    </h1>

                    <!-- Recipe Description -->
                    <?php if (!empty($recipe['description'])): ?>
                        <p class="text-muted lead fs-6 mb-0" style="max-width: 720px; line-height: 1.7;">
                            <?php echo htmlspecialchars($recipe['description']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                        <i class="bi bi-arrow-left me-1"></i> กลับหน้ารวมสูตรอาหาร
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Detail Section -->
    <section class="py-5" style="background-color: var(--bg-cream);">
        <div class="container">
            <div class="row g-4">
                
                <!-- Left Column: Visual, Instructions, and Cooking Details (col-lg-8) -->
                <div class="col-lg-8">
                    
                    <!-- Visual Presentation: Real Image or Styled Fallback Banner -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="border: 1px solid var(--border-subtle) !important;">
                        <?php if ($hasImage): ?>
                            <div style="max-height: 420px; overflow: hidden;">
                                <img src="<?php echo htmlspecialchars($imagePath); ?>" class="w-100 h-100" style="object-fit: cover;" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                            </div>
                        <?php else: ?>
                            <!-- Fallback Food Illustration Banner -->
                            <div class="p-5 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 260px; background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%);">
                                <div style="width: 100px; height: 100px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 3.2rem; box-shadow: 0 6px 20px rgba(224, 93, 38, 0.18);" class="mb-3">
                                    <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-egg-fried'; ?>"></i>
                                </div>
                                <span class="badge rounded-pill px-3 py-2 bg-white text-dark border shadow-xs" style="font-size: 0.9rem;">
                                    <i class="bi bi-check2-circle text-success me-1"></i>สูตรอาหารพร้อมวิธีปรุงอย่างละเอียด
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Metrics Bar -->
                        <div class="card-body p-3 p-md-4 bg-white border-top">
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="p-2 p-md-3 rounded-3" style="background-color: var(--bg-cream); border: 1px solid var(--border-subtle);">
                                        <i class="bi bi-clock-history text-warning fs-4 mb-1 d-block"></i>
                                        <span class="small text-muted d-block">เวลาทำอาหาร</span>
                                        <strong class="text-dark fs-6"><?php echo (int) $recipe['cooking_time']; ?> นาที</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 p-md-3 rounded-3" style="background-color: var(--bg-cream); border: 1px solid var(--border-subtle);">
                                        <i class="bi bi-people-fill text-secondary fs-4 mb-1 d-block"></i>
                                        <span class="small text-muted d-block">ปริมาณสำหรับ</span>
                                        <strong class="text-dark fs-6"><?php echo (int) $recipe['servings']; ?> ที่/จาน</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 p-md-3 rounded-3" style="background-color: var(--bg-cream); border: 1px solid var(--border-subtle);">
                                        <i class="bi bi-basket2-fill text-primary fs-4 mb-1 d-block"></i>
                                        <span class="small text-muted d-block">วัตถุดิบทั้งหมด</span>
                                        <strong class="text-dark fs-6"><?php echo count($essentialIngredients) + count($optionalIngredients); ?> อย่าง</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cooking Instructions Card (ขั้นตอนและวิธีทำ) -->
                    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4" style="border: 1px solid var(--border-subtle) !important;">
                        <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background-color: #fff2ea; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="bi bi-fire"></i>
                            </div>
                            <div>
                                <h2 class="h5 fw-bold text-dark mb-0">ขั้นตอนและวิธีทำอาหาร</h2>
                                <small class="text-muted">ทำตามขั้นตอนทีละขั้นเพื่อรสชาติที่อร่อยลงตัว</small>
                            </div>
                        </div>

                        <!-- คำแนะนำและวิธีทำแบบเรียงขั้นตอน -->
                        <div class="instructions-content" style="line-height: 1.9; font-size: 1.05rem; color: #3a3431;">
                            <?php
                            $rawInstructions = trim($recipe['instructions']);
                            $steps = preg_split('/\r\n|\r|\n/', $rawInstructions);
                            ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($steps as $stepLine): ?>
                                    <?php $stepLine = trim($stepLine); ?>
                                    <?php if (!empty($stepLine)): ?>
                                        <div class="p-3 rounded-3 d-flex align-items-start gap-3" style="background-color: #faf8f5; border: 1px solid var(--border-subtle);">
                                            <i class="bi bi-check2-circle text-success fs-5 mt-1 flex-shrink-0"></i>
                                            <div class="flex-grow-1">
                                                <?php echo htmlspecialchars($stepLine); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Cooking Tip Box -->
                        <div class="mt-4 p-3 rounded-3 d-flex align-items-center gap-3" style="background-color: #fff9e6; border: 1px dashed #f5d77f;">
                            <i class="bi bi-lightbulb-fill text-warning fs-3 flex-shrink-0"></i>
                            <div class="small text-dark">
                                <strong>เคล็ดลับจากครัว:</strong> เตรียมและตวงวัตถุดิบทั้งหมดให้พร้อมก่อนเปิดเตา จะช่วยให้การปรุงอาหารสะดวก รวดเร็ว และได้ความสุกที่พอดีที่สุด
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Ingredients Checklist & Category Info (col-lg-4) -->
                <div class="col-lg-4">
                    
                    <!-- Ingredients Checklist Card (วัตถุดิบและเครื่องปรุง) -->
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 sticky-lg-top" style="top: 85px; border: 1px solid var(--border-subtle) !important; z-index: 10;">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 38px; height: 38px; border-radius: 10px; background-color: var(--primary-light); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                    <i class="bi bi-basket2"></i>
                                </div>
                                <h3 class="h6 fw-bold text-dark mb-0">วัตถุดิบและเครื่องปรุง</h3>
                            </div>
                            <span class="badge rounded-pill bg-light text-dark border">
                                <?php echo count($essentialIngredients) + count($optionalIngredients); ?> รายการ
                            </span>
                        </div>

                        <!-- กลุ่ม 1: วัตถุดิบจำเป็น (Essential Ingredients) -->
                        <?php if (!empty($essentialIngredients)): ?>
                            <div class="mb-4">
                                <span class="d-inline-flex align-items-center gap-1 small fw-bold text-danger text-uppercase mb-2">
                                    <i class="bi bi-exclamation-circle-fill"></i> วัตถุดิบหลัก (จำเป็น)
                                </span>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($essentialIngredients as $item): ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 rounded-2" style="background-color: #fff5f2; border: 1px solid #ffdcd3;">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-check-circle-fill text-danger small"></i>
                                                <span class="small fw-semibold text-dark">
                                                    <?php echo htmlspecialchars($item['ingredient_name']); ?>
                                                </span>
                                            </div>
                                            <span class="badge bg-white text-dark border border-danger-subtle rounded-pill small fw-normal">
                                                <?php echo htmlspecialchars($item['quantity'] ?? ''); ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- กลุ่ม 2: เครื่องปรุงรสและวัตถุดิบทางเลือก (Optional / Seasonings) -->
                        <?php if (!empty($optionalIngredients)): ?>
                            <div class="mb-3">
                                <span class="d-inline-flex align-items-center gap-1 small fw-bold text-secondary text-uppercase mb-2">
                                    <i class="bi bi-plus-circle-fill"></i> เครื่องปรุงรสและส่วนผสมเสริม
                                </span>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($optionalIngredients as $item): ?>
                                        <div class="d-flex justify-content-between align-items-center p-2 rounded-2" style="background-color: #f8f9fa; border: 1px solid var(--border-subtle);">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-dash-circle text-muted small"></i>
                                                <span class="small text-dark">
                                                    <?php echo htmlspecialchars($item['ingredient_name']); ?>
                                                </span>
                                            </div>
                                            <span class="badge bg-white text-muted border rounded-pill small fw-normal">
                                                <?php echo htmlspecialchars($item['quantity'] ?? ''); ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($essentialIngredients) && empty($optionalIngredients)): ?>
                            <p class="text-muted small mb-0">ไม่มีข้อมูลวัตถุดิบระบุไว้สำหรับเมนูนี้</p>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="mt-4 pt-3 border-top d-flex flex-column gap-2">
                            <a href="search.php" class="btn btn-outline-secondary w-100 rounded-pill py-2 small">
                                <i class="bi bi-search me-1"></i> ค้นหาเมนูจากวัตถุดิบที่คุณมี
                            </a>
                            <a href="recipes.php" class="btn btn-kitchen-primary w-100 rounded-pill py-2 small">
                                <i class="bi bi-grid me-1"></i> ดูสูตรอาหารทั้งหมด
                            </a>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Bottom Section: Related Recipes (สูตรอาหารอื่นที่น่าสนใจ) -->
            <?php if (!empty($relatedRecipes)): ?>
                <div class="mt-5 pt-4 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h3 class="h4 fw-bold text-dark mb-1">เมนูอื่น ๆ ที่น่าสนใจ</h3>
                            <p class="text-muted small mb-0">ลองค้นพบเมนูแสนอร่อยเพิ่มเติมสำหรับมื้อต่อไปของคุณ</p>
                        </div>
                        <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
                            ดูทั้งหมด <i class="bi bi-arrow-right small"></i>
                        </a>
                    </div>

                    <div class="row g-4">
                        <?php foreach ($relatedRecipes as $rel): ?>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white" style="border: 1px solid var(--border-subtle) !important; transition: transform 0.2s ease;">
                                    <div class="p-3 text-center d-flex align-items-center justify-content-center" style="height: 120px; background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%);">
                                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 4px 10px rgba(224, 93, 38, 0.15);">
                                            <i class="bi <?php echo !empty($rel['category_icon']) ? htmlspecialchars($rel['category_icon']) : 'bi-egg-fried'; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="card-body p-3 d-flex flex-column">
                                        <span class="badge rounded-pill bg-light text-muted border mb-2 align-self-start small">
                                            <?php echo htmlspecialchars($rel['category_name'] ?? 'ทั่วไป'); ?>
                                        </span>
                                        <h4 class="h6 fw-bold text-dark mb-2">
                                            <a href="recipe_detail.php?id=<?php echo (int)$rel['id']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($rel['title']); ?>
                                            </a>
                                        </h4>
                                        <div class="d-flex justify-content-between text-muted small mt-auto pt-2 border-top">
                                            <span><i class="bi bi-clock me-1 text-warning"></i><?php echo (int)$rel['cooking_time']; ?> นาที</span>
                                            <span><i class="bi bi-people me-1 text-secondary"></i><?php echo (int)$rel['servings']; ?> ที่</span>
                                        </div>
                                        <a href="recipe_detail.php?id=<?php echo (int)$rel['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill mt-3 w-100" style="color: var(--primary-color); border-color: var(--primary-color);">
                                            ดูสูตรนี้
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
<?php endif; ?>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>

