<?php
$currentPage = 'home';
$pageTitle = 'หน้าแรก | มีอะไรในครัว? (What\'s in My Kitchen)';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

// 2. ดึงข้อมูลจริงจากฐานข้อมูล MySQL (ใช้ PDO พร้อมการจัดการข้อผิดพลาด)
$totalRecipes = 0;
$totalIngredients = 0;
$totalCategories = 0;
$featuredRecipes = [];
$popularIngredients = [];
$categoriesList = [];
$dbError = null;

try {
    // 2.1 ดึงจำนวนสูตรอาหารทั้งหมด
    $stmtRecipesCount = $pdo->query("SELECT COUNT(*) FROM recipes");
    $totalRecipes = (int) $stmtRecipesCount->fetchColumn();

    // 2.2 ดึงจำนวนวัตถุดิบทั้งหมดในคลัง
    $stmtIngredientsCount = $pdo->query("SELECT COUNT(*) FROM ingredients");
    $totalIngredients = (int) $stmtIngredientsCount->fetchColumn();

    // 2.3 ดึงจำนวนหมวดหมู่อาหารทั้งหมด
    $stmtCategoriesCount = $pdo->query("SELECT COUNT(*) FROM categories");
    $totalCategories = (int) $stmtCategoriesCount->fetchColumn();

    // 2.4 ดึงสูตรอาหารยอดนิยมแนะนำ (Featured / Popular Recipes) สูงสุด 6 เมนู เรียงตามยอดเข้าชมจากมากไปน้อย
    $stmtFeatured = $pdo->query("
        SELECT 
            r.id, 
            r.title, 
            r.description, 
            r.image, 
            r.cooking_time, 
            r.servings, 
            r.views,
            r.created_at,
            c.name AS category_name, 
            c.icon AS category_icon,
            COUNT(ri.id) AS total_ingredients
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
        GROUP BY r.id
        ORDER BY r.views DESC, r.created_at DESC, r.id DESC
        LIMIT 6
    ");
    $featuredRecipes = $stmtFeatured->fetchAll();

    // 2.5 ดึงตัวอย่างวัตถุดิบยอดนิยมสำหรับแท็กค้นหา (ประเภทวัตถุดิบหลัก)
    $stmtPopular = $pdo->query("
        SELECT name 
        FROM ingredients 
        WHERE type = 'main' 
        ORDER BY id ASC 
        LIMIT 6
    ");
    $popularIngredients = $stmtPopular->fetchAll(PDO::FETCH_COLUMN);

    // 2.6 ดึงหมวดหมู่อาหาร พร้อมนับจำนวนสูตรอาหารในแต่ละหมวดหมู่
    $stmtCategories = $pdo->query("
        SELECT 
            c.id, 
            c.name, 
            c.description, 
            c.icon, 
            COUNT(r.id) AS recipe_count
        FROM categories c
        LEFT JOIN recipes r ON c.id = r.category_id
        GROUP BY c.id
        ORDER BY c.id ASC
    ");
    $categoriesList = $stmtCategories->fetchAll();

} catch (PDOException $e) {
    error_log("Database Query Error in index.php: " . $e->getMessage());
    $dbError = "ไม่สามารถโหลดข้อมูลจากฐานข้อมูลได้ในขณะนี้ กรุณาตรวจสอบสถานะของฐานข้อมูล MySQL";
}

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($dbError): ?>
    <!-- แจ้งเตือนกรณีเกิดข้อผิดพลาดในการดึงข้อมูล -->
    <div class="container mt-3">
        <div class="alert alert-warning border-0 rounded-4 shadow-sm py-2 px-3 d-flex align-items-center mb-0">
            <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
            <span class="small"><?php echo htmlspecialchars($dbError); ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Hero / Welcome Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <!-- Left Column: Welcome Content -->
            <div class="col-lg-7">
                <div class="hero-badge">
                    <i class="bi bi-stars"></i>
                    <span>เปลี่ยนของในตู้เย็นให้เป็นมื้ออร่อย</span>
                </div>
                
                <h1 class="hero-title">
                    Croc Cook <br>
                    <span class="title-highlight">What's in My Kitchen</span>
                </h1>

                <p class="hero-desc">
                    หมดปัญหา “วันนี้จะกินอะไรดี?” เพียงสำรวจวัตถุดิบที่คุณมีอยู่ในครัว แล้วค้นพบสูตรอาหารแสนอร่อยที่สามารถทำตามได้ทันที ช่วยลดขยะอาหาร และสนุกกับการเข้าครัวได้ทุกวัน
                </p>

                <!-- Call to Action Buttons -->
                <div class="d-flex flex-wrap gap-3">
                    <a href="ingredients.php" class="btn btn-kitchen-primary">
                        <i class="bi bi-check2-square"></i>
                        เลือกวัตถุดิบในครัว
                    </a>
                    <a href="#featured-recipes" class="btn btn-outline-secondary rounded-pill px-4 py-3 fw-medium">
                        <i class="bi bi-fire me-1"></i>
                        ดูสูตรอาหารแนะนำ
                    </a>
                </div>
            </div>

            <!-- Right Column: Visual Presentation & Live Database Stats -->
            <div class="col-lg-5">
                <div class="hero-illustration">
                   <div class="main-food-icon">
                        <img src="im/logo.png" alt="Logo" style="max-width: 80px; height: auto;">
                    </div>
                    <h3 class="h4 fw-bold text-dark mb-2">เข้าครัวง่าย ๆ ได้ทุกวัน</h3>
                    <p class="text-muted small mb-4">
                        คลังสูตรอาหารและวัตถุดิบสดใหม่ พร้อมช่วยคุณเนรมิตมื้ออร่อยได้ทุกมื้อ
                    </p>

                    <!-- ข้อมูลสถิติสดจากฐานข้อมูล MySQL -->
                    <div class="row g-2 text-start">
                        <div class="col-12">
                            <div class="feature-pill d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-journal-bookmark-fill text-warning fs-5"></i>
                                    <span class="small fw-medium">สูตรอาหารพร้อมปรุง</span>
                                </div>
                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1 fw-bold fs-6">
                                    <?php echo number_format($totalRecipes); ?> เมนู
                                </span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="feature-pill d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-basket2-fill text-success fs-5"></i>
                                    <span class="small fw-medium">วัตถุดิบในคลังข้อมูล</span>
                                </div>
                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-3 py-1 fw-bold fs-6">
                                    <?php echo number_format($totalIngredients); ?> ชนิด
                                </span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="feature-pill d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-grid-fill text-primary fs-5"></i>
                                    <span class="small fw-medium">หมวดหมู่อาหาร</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-1 fw-bold fs-6">
                                    <?php echo number_format($totalCategories); ?> หมวด
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 1: Search by Ingredients (ค้นหาเมนูจากวัตถุดิบที่มี) -->
<section id="ingredient-search" class="search-section">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold text-dark mb-2">ค้นหาเมนูจากวัตถุดิบที่มี</h2>
            <p class="text-muted">ใส่วัตถุดิบที่คุณมีในตู้เย็น แล้วเราจะช่วยค้นหาสูตรอาหารที่ทำได้ทันที</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <!-- Search Form (Submits to search.php) -->
                <form action="search.php" method="GET" class="search-box-card">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8 col-md-7">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-basket2"></i>
                                </span>
                                <input type="text" name="ingredient" class="form-control border-start-0 ps-0" placeholder="พิมพ์ชื่อวัตถุดิบ เช่น ไข่ไก่, หมูสับ, กะเพรา..." aria-label="พิมพ์ชื่อวัตถุดิบ">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-5">
                            <button type="submit" class="btn btn-kitchen-primary w-100 py-3">
                                <i class="bi bi-search me-1"></i> ค้นหาสูตรอาหาร
                            </button>
                        </div>
                    </div>

                    <!-- วัตถุดิบยอดนิยม ดึงสดจากฐานข้อมูล MySQL จริง -->
                    <?php if (!empty($popularIngredients)): ?>
                        <div class="mt-3 d-flex flex-wrap align-items-center gap-2">
                            <span class="small text-muted me-1">
                                <i class="bi bi-lightbulb text-warning me-1"></i>ตัวอย่างวัตถุดิบยอดนิยม:
                            </span>
                            <?php foreach ($popularIngredients as $ingredientName): ?>
                                <a href="search.php?ingredient=<?php echo urlencode($ingredientName); ?>" class="badge-tag">
                                    <?php echo htmlspecialchars($ingredientName); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ลิงก์ตรงไปยังหน้าเลือกวัตถุดิบทั้งหมด (ingredients.php) -->
                    <div class="mt-4 pt-3 border-top text-center">
                        <a href="ingredients.php" class="btn btn-outline-primary rounded-pill px-4 py-2" style="color: var(--primary-color); border-color: var(--primary-color);">
                            <i class="bi bi-check2-square me-1"></i> หรือคลิกเลือกวัตถุดิบที่มีจากตู้เย็นของคุณ (เปิดหน้าเลือกวัตถุดิบ)
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Section 2: Featured Recipes (สูตรอาหารแนะนำ - ดึงจากฐานข้อมูลจริง) -->
<section id="featured-recipes" class="py-5" style="background-color: #ffffff; border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background-color: var(--primary-light); color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                    <i class="bi bi-fire"></i> เมนูยอดนิยมแนะนำ
                </div>
                <h2 class="h3 fw-bold text-dark mb-1">สูตรอาหารแนะนำสำหรับคุณ</h2>
                <p class="text-muted mb-0">คัดสรรเมนูยอดนิยมที่มีผู้เข้าชมมากที่สุด พร้อมขั้นตอนการปรุงอย่างละเอียด</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                    ดูสูตรอาหารทั้งหมด (<?php echo number_format($totalRecipes); ?>) <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <?php if (!empty($featuredRecipes)): ?>
            <!-- การ์ดแสดงรายการสูตรอาหารจากฐานข้อมูล เรียงตามยอดเข้าชมยอดนิยม -->
            <div class="row g-4">
                <?php foreach ($featuredRecipes as $recipe): ?>
                    <?php
                    // ตรวจสอบว่ามีรูปภาพในระบบหรือไม่
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
                    <div class="col-lg-4 col-md-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden" style="transition: transform 0.3s ease, box-shadow 0.3s ease; background-color: var(--bg-cream); border: 1px solid var(--border-subtle) !important;">
                            
                            <!-- ส่วนรูปภาพ หรือ รูปภาพสำรอง (Fallback Banner) - กดเพื่อเข้าสู่หน้ารายละเอียด -->
                            <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none">
                                <?php if ($hasImage): ?>
                                    <div class="position-relative overflow-hidden" style="height: 180px;">
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>" class="card-img-top w-100 h-100" style="object-fit: cover;" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                        <span class="position-absolute top-0 start-0 m-3 badge rounded-pill" style="background-color: rgba(255,255,255,0.95); color: var(--primary-color); border: 1px solid var(--border-subtle); font-weight: 500; font-size: 0.8rem;">
                                            <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1"></i><?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                                        </span>
                                        <span class="position-absolute top-0 end-0 m-3 badge bg-white text-dark rounded-pill border shadow-xs" style="font-size: 0.8rem; font-weight: 500;">
                                            <i class="bi bi-clock text-warning me-1"></i><?php echo (int) $recipe['cooking_time']; ?> นาที
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="position-relative p-4 text-center" style="background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%); min-height: 140px; display: flex; align-items: center; justify-content: center;">
                                        <div style="width: 68px; height: 68px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 4px 12px rgba(224, 93, 38, 0.15);">
                                            <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-egg-fried'; ?>"></i>
                                        </div>
                                        <span class="position-absolute top-0 start-0 m-3 badge rounded-pill" style="background-color: rgba(255,255,255,0.95); color: var(--primary-color); border: 1px solid var(--border-subtle); font-weight: 500; font-size: 0.8rem;">
                                            <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1"></i><?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                                        </span>
                                        <span class="position-absolute top-0 end-0 m-3 badge bg-white text-dark rounded-pill border shadow-xs" style="font-size: 0.8rem; font-weight: 500;">
                                            <i class="bi bi-clock text-warning me-1"></i><?php echo (int) $recipe['cooking_time']; ?> นาที
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <!-- รายละเอียดสูตรอาหาร -->
                            <div class="card-body p-4 d-flex flex-column">
                                <h3 class="h5 fw-bold text-dark mb-2">
                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($recipe['title']); ?>
                                    </a>
                                </h3>
                                <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($recipe['description'] ?? ''); ?>
                                </p>

                                <!-- ข้อมูลเสริม: จำนวนวัตถุดิบ, จำนวนที่เสิร์ฟ และยอดเข้าชม -->
                                <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary-subtle small text-muted">
                                    <div>
                                        <i class="bi bi-basket2 text-primary me-1"></i>
                                        <span><strong><?php echo (int) $recipe['total_ingredients']; ?></strong> วัตถุดิบ</span>
                                    </div>
                                    <div>
                                        <i class="bi bi-people text-secondary me-1"></i>
                                        <span><strong><?php echo (int) $recipe['servings']; ?></strong> ที่</span>
                                    </div>
                                    <div>
                                        <i class="bi bi-eye text-warning me-1"></i>
                                        <span><strong><?php echo number_format((int) ($recipe['views'] ?? 0)); ?></strong> ครั้ง</span>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="btn w-100 rounded-pill py-2" style="background-color: #ffffff; color: var(--primary-color); border: 1px solid var(--primary-color); font-weight: 500; font-size: 0.95rem;">
                                        <i class="bi bi-book-half me-1"></i> ดูวิธีปรุงอาหาร
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- กล่องแจ้งเตือนเมื่อยังไม่มีข้อมูลสูตรอาหารในระบบ -->
            <div class="text-center py-5 px-3 bg-light rounded-4 border">
                <div class="mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: #ffffff; color: #adb5bd; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                    <i class="bi bi-journal-x"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">ยังไม่มีข้อมูลสูตรอาหารในระบบ</h3>
                <p class="text-muted mb-0 small" style="max-width: 480px; margin: 0 auto;">
                    ขณะนี้ยังไม่มีรายการสูตรอาหารในฐานข้อมูล กรุณานำเข้าข้อมูลเริ่มต้นผ่านไฟล์ schema.sql ใน phpMyAdmin
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Section 3: How It Works (ขั้นตอนการใช้งาน 3 ขั้นตอน) -->
<section class="steps-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h3 fw-bold text-dark mb-2">เริ่มต้นเข้าครัวง่าย ๆ ใน 3 ขั้นตอน</h2>
            <p class="text-muted">เปลี่ยนวัตถุดิบในตู้เย็นให้เป็นมื้ออร่อยของคุณได้อย่างสะดวกและรวดเร็ว</p>
        </div>

        <div class="row g-4">
            <!-- Step 1: เลือกวัตถุดิบ -->
            <div class="col-md-4">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 01</span>
                    <div class="step-icon-box step-icon-1 mx-auto">
                        <i class="bi bi-basket2"></i>
                    </div>
                    <h3 class="step-card-title">1. เลือกวัตถุดิบ</h3>
                    <p class="step-card-desc">
                        สำรวจสิ่งของในตู้เย็นและตู้กับข้าว แล้วระบุหรือเลือกวัตถุดิบที่คุณมีอยู่ติดบ้านเพื่อเริ่มต้น
                    </p>
                </div>
            </div>

            <!-- Step 2: ค้นหาสูตรอาหาร -->
            <div class="col-md-4">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 02</span>
                    <div class="step-icon-box step-icon-2 mx-auto">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3 class="step-card-title">2. ค้นหาสูตรอาหาร</h3>
                    <p class="step-card-desc">
                        ระบบจะนำวัตถุดิบของคุณไปประมวลผลจับคู่กับสูตรอาหารที่เข้ากัน เพื่อค้นหาเมนูที่ทำได้จริง
                    </p>
                </div>
            </div>

            <!-- Step 3: ลงมือทำอาหาร -->
            <div class="col-md-4">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 03</span>
                    <div class="step-icon-box step-icon-3 mx-auto">
                        <i class="bi bi-fire"></i>
                    </div>
                    <h3 class="step-card-title">3. ลงมือทำอาหาร</h3>
                    <p class="step-card-desc">
                        ดูวิธีปรุง ปริมาณเครื่องปรุง และเคล็ดลับทีละขั้นตอน แล้วรังสรรค์มื้ออร่อยพร้อมเสิร์ฟได้ทันที
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 4: Food Categories (หมวดหมู่อาหาร - ดึงข้อมูลจริงจากฐานข้อมูล) -->
<section class="categories-section">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="h3 fw-bold text-dark mb-2">หมวดหมู่อาหารยอดนิยม</h2>
            <p class="text-muted">เลือกดูไอเดียและประเภทอาหารที่เหมาะกับมื้อโปรดของคุณ</p>
        </div>

        <?php if (!empty($categoriesList)): ?>
            <!-- แสดงหมวดหมู่จริงจากฐานข้อมูล -->
            <div class="row g-4 mt-1">
                <?php foreach ($categoriesList as $cat): ?>
                    <div class="col-lg-4 col-md-6">
                        <a href="categories.php" class="category-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="main-food-icon">
                                 <img src="im/logo.png" alt="Logo" style="max-width: 80px; height: auto; border-radius: 50%; object-fit: cover;">
                               </div>
                                <span class="badge bg-white text-secondary-emphasis border rounded-pill px-3 py-1 small">
                                    <?php echo (int) $cat['recipe_count']; ?> สูตรอาหาร
                                </span>
                            </div>
                            <h3 class="category-title"><?php echo htmlspecialchars($cat['name']); ?></h3>
                            <p class="category-desc">
                                <?php echo htmlspecialchars($cat['description'] ?? ''); ?>
                            </p>
                            <span class="category-link-text">
                                ดูหมวดหมู่นี้ <i class="bi bi-arrow-right small"></i>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- กล่องแจ้งเตือนเมื่อยังไม่มีข้อมูลหมวดหมู่ในระบบ -->
            <div class="text-center py-5 px-3 bg-light rounded-4 border mt-3">
                <i class="bi bi-folder-x fs-1 text-muted mb-2 d-block"></i>
                <p class="text-muted mb-0">ยังไม่มีข้อมูลหมวดหมู่อาหารในระบบ</p>
            </div>
        <?php endif; ?>

        <!-- Section CTA Link -->
        <div class="text-center mt-5">
            <a href="categories.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                <i class="bi bi-grid me-1"></i> ดูหมวดหมู่อาหารทั้งหมด
            </a>
        </div>
    </div>
</section>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>
