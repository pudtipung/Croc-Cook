<?php
/**
 * หน้ารายการสูตรอาหารทั้งหมด - มีอะไรในครัว? (What's in My Kitchen)
 */
$currentPage = 'recipes';
$pageTitle = 'สูตรอาหารทั้งหมด | มีอะไรในครัว? (What\'s in My Kitchen)';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

// 2. กำหนดค่าและจัดการพารามิเตอร์สำหรับ Pagination และ Category Filter
$perPage = 6; // แสดงหน้าละ 6 รายการ
$page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]) : 1;
if (!$page) {
    $page = 1;
}

$categoryId = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 0]]) : 0;
if (!$categoryId) {
    $categoryId = 0;
}

$totalRecipes = 0;
$recipes = [];
$categories = [];
$currentCategoryName = 'ทั้งหมด';
$recipeIngredientsMap = [];
$dbError = null;

try {
    // 3. ดึงหมวดหมู่ทั้งหมดพร้อมจำนวนสูตรอาหารสำหรับแถบกรอง (Filter Pills)
    $categoriesStmt = $pdo->query("
        SELECT 
            c.id, 
            c.name, 
            c.icon, 
            COUNT(r.id) AS recipe_count
        FROM categories c
        LEFT JOIN recipes r ON c.id = r.category_id
        GROUP BY c.id
        ORDER BY c.id ASC
    ");
    $categories = $categoriesStmt->fetchAll();

    // 4. สร้างเงื่อนไข SQL สำหรับการกรองตามหมวดหมู่
    $whereClause = "";
    $params = [];

    if ($categoryId > 0) {
        $whereClause = "WHERE r.category_id = :cat_id";
        $params[':cat_id'] = $categoryId;

        // ค้นหาชื่อหมวดหมู่ที่เลือก
        foreach ($categories as $cat) {
            if ($cat['id'] == $categoryId) {
                $currentCategoryName = $cat['name'];
                break;
            }
        }
    }

    // 5. นับจำนวนสูตรอาหารทั้งหมดตามเงื่อนไข (Prepared Statement)
    $countSql = "SELECT COUNT(*) FROM recipes r $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecipes = (int) $countStmt->fetchColumn();

    // คำนวณจำนวนหน้าทั้งหมดสำหรับการแบ่งหน้า (Pagination)
    $totalPages = max(1, (int) ceil($totalRecipes / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    // 6. ดึงข้อมูลสูตรอาหารของหน้าปัจจุบัน (Prepared Statement with Limit & Offset)
    $recipesSql = "
        SELECT 
            r.id, 
            r.title, 
            r.description, 
            r.image, 
            r.cooking_time, 
            r.servings, 
            r.instructions,
            c.id AS category_id, 
            c.name AS category_name, 
            c.icon AS category_icon,
            COUNT(ri.id) AS total_ingredients
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
        $whereClause
        GROUP BY r.id
        ORDER BY r.id ASC
        LIMIT :limit OFFSET :offset
    ";

    $recipesStmt = $pdo->prepare($recipesSql);
    if ($categoryId > 0) {
        $recipesStmt->bindValue(':cat_id', $categoryId, PDO::PARAM_INT);
    }
    $recipesStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $recipesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $recipesStmt->execute();
    $recipes = $recipesStmt->fetchAll();

    // 7. ดึงรายการวัตถุดิบของแต่ละสูตรอาหารในหน้านี้ สำหรับแสดงผลใน Modal รายละเอียด
    if (!empty($recipes)) {
        $recipeIds = array_column($recipes, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($recipeIds), '?'));
        
        $riStmt = $pdo->prepare("
            SELECT 
                ri.recipe_id, 
                ri.quantity, 
                ri.unit, 
                ri.is_essential, 
                i.name AS ingredient_name, 
                i.type AS ingredient_type,
                i.category_group
            FROM recipe_ingredients ri
            JOIN ingredients i ON ri.ingredient_id = i.id
            WHERE ri.recipe_id IN ($inPlaceholders)
            ORDER BY ri.is_essential DESC, i.name ASC
        ");
        $riStmt->execute($recipeIds);
        $allRi = $riStmt->fetchAll();

        foreach ($allRi as $ri) {
            $recipeIngredientsMap[$ri['recipe_id']][] = $ri;
        }
    }

} catch (PDOException $e) {
    error_log("Recipes Page Error: " . $e->getMessage());
    $dbError = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาตรวจสอบสถานะของฐานข้อมูล MySQL";
}

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header / Breadcrumb Section -->
<section class="py-4" style="background: radial-gradient(circle at top right, #fff4ee 0%, var(--bg-cream) 70%); border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item">
                    <a href="index.php" class="text-decoration-none" style="color: var(--primary-color);">
                        <i class="bi bi-house-door me-1"></i>หน้าแรก
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page" style="color: var(--text-muted);">
                    สูตรอาหารทั้งหมด
                </li>
            </ol>
        </nav>

        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold text-dark mb-1">
                    สูตรอาหารทั้งหมด <span class="title-highlight">All Recipes</span>
                </h1>
                <p class="text-muted mb-0">
                    รวมสูตรอาหารไทยรสเด็ด ครบครันทั้งเมนูผัด ต้ม ทอด ยำ และน้ำพริก พร้อมวิธีปรุงอย่างละเอียด
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge rounded-pill px-3 py-2 fs-6 shadow-xs" style="background-color: var(--primary-light); color: var(--primary-color); border: 1px solid rgba(224,93,38,0.2);">
                    <i class="bi bi-journal-check me-1"></i>
                    พบทั้งหมด <strong><?php echo number_format($totalRecipes); ?></strong> เมนู
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Main Content Area -->
<section class="py-5" style="background-color: var(--bg-cream); min-height: 500px;">
    <div class="container">

        <?php if ($dbError): ?>
            <!-- กล่องแจ้งเตือนกรณีเชื่อมต่อฐานข้อมูลขัดข้อง -->
            <div class="alert alert-warning border-0 rounded-4 shadow-sm py-3 px-4 d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-3"></i>
                <div>
                    <h5 class="alert-heading h6 fw-bold mb-1">เกิดข้อผิดพลาดในการโหลดข้อมูล</h5>
                    <span class="small mb-0"><?php echo htmlspecialchars($dbError); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category Filter Pills (แถบตัวกรองหมวดหมู่อาหาร) -->
        <?php if (!empty($categories)): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-4 pb-2">
                <span class="small text-muted me-1 fw-medium">
                    <i class="bi bi-funnel me-1"></i>หมวดหมู่:
                </span>
                
                <!-- ปุ่ม "ทั้งหมด" -->
                <a href="recipes.php" class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($categoryId === 0) ? 'btn-kitchen-primary' : 'btn-outline-secondary bg-white'; ?>">
                    ทั้งหมด
                </a>

                <!-- ปุ่มหมวดหมู่ย่อย -->
                <?php foreach ($categories as $cat): ?>
                    <a href="recipes.php?category=<?php echo (int) $cat['id']; ?>" class="btn btn-sm rounded-pill px-3 py-2 <?php echo ($categoryId === (int)$cat['id']) ? 'btn-kitchen-primary' : 'btn-outline-secondary bg-white'; ?>">
                        <i class="bi <?php echo !empty($cat['icon']) ? htmlspecialchars($cat['icon']) : 'bi-tag'; ?> me-1"></i>
                        <?php echo htmlspecialchars($cat['name']); ?>
                        <span class="badge <?php echo ($categoryId === (int)$cat['id']) ? 'bg-white text-dark' : 'bg-secondary-subtle text-secondary-emphasis'; ?> rounded-pill ms-1">
                            <?php echo (int) $cat['recipe_count']; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Recipe Cards Grid (แสดงรายการสูตรอาหารแบบ Responsive Cards) -->
        <?php if (!empty($recipes)): ?>
            <div class="row g-4">
                <?php foreach ($recipes as $recipe): ?>
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
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden" style="background-color: #ffffff; border: 1px solid var(--border-subtle) !important; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            
                            <!-- ส่วนรูปภาพ หรือ รูปภาพสำรอง (Fallback Banner) - กดเพื่อเข้าสู่หน้ารายละเอียด -->
                            <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none">
                                <?php if ($hasImage): ?>
                                    <div class="position-relative overflow-hidden" style="height: 190px;">
                                        <img src="<?php echo htmlspecialchars($imagePath); ?>" class="card-img-top w-100 h-100" style="object-fit: cover;" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                        <span class="position-absolute top-0 start-0 m-3 badge rounded-pill" style="background-color: rgba(255,255,255,0.95); color: var(--primary-color); border: 1px solid var(--border-subtle); font-weight: 500; font-size: 0.8rem;">
                                            <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1"></i><?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                                        </span>
                                        <span class="position-absolute top-0 end-0 m-3 badge bg-white text-dark rounded-pill border shadow-xs" style="font-size: 0.8rem; font-weight: 500;">
                                            <i class="bi bi-clock text-warning me-1"></i><?php echo (int) $recipe['cooking_time']; ?> นาที
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <!-- รูปภาพสำรอง (Fallback Food Illustration Banner) ตามธีมสีส้ม-ครีม -->
                                    <div class="position-relative p-4 text-center d-flex align-items-center justify-content-center" style="height: 190px; background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%);">
                                        <div style="width: 76px; height: 76px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.3rem; box-shadow: 0 4px 15px rgba(224, 93, 38, 0.15);">
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

                            <!-- เนื้อหาภายในการ์ด (Card Body) -->
                            <div class="card-body p-4 d-flex flex-column">
                                <h2 class="h5 fw-bold mb-2">
                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none text-dark" style="transition: color 0.2s ease;">
                                        <?php echo htmlspecialchars($recipe['title']); ?>
                                    </a>
                                </h2>
                                
                                <p class="text-muted small flex-grow-1 mb-3" style="line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($recipe['description'] ?? ''); ?>
                                </p>

                                <!-- รายละเอียดเวลาและจำนวนวัตถุดิบ -->
                                <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary-subtle small text-muted">
                                    <div>
                                        <i class="bi bi-basket2 text-primary me-1"></i>
                                        <span><strong><?php echo (int) $recipe['total_ingredients']; ?></strong> วัตถุดิบ</span>
                                    </div>
                                    <div>
                                        <i class="bi bi-people text-secondary me-1"></i>
                                        <span><strong><?php echo (int) $recipe['servings']; ?></strong> ที่/จาน</span>
                                    </div>
                                </div>

                                <!-- ปุ่มเปิดดูรายละเอียดสูตรอาหาร (ไปยัง recipe_detail.php) -->
                                <div class="mt-3">
                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="btn w-100 rounded-pill py-2" style="background-color: #ffffff; color: var(--primary-color); border: 1.5px solid var(--primary-color); font-weight: 500; font-size: 0.95rem; transition: all 0.2s ease;">
                                        <i class="bi bi-book-half me-1"></i> ดูวิธีทำและวัตถุดิบ
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>

            <!-- Pagination Navigation (การแบ่งหน้า) -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <!-- ปุ่มย้อนกลับ (Previous) -->
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-pill me-1" href="recipes.php?page=<?php echo $page - 1; ?><?php echo ($categoryId > 0) ? '&category=' . $categoryId : ''; ?>" aria-label="ก่อนหน้า">
                                <i class="bi bi-chevron-left"></i> ก่อนหน้า
                            </a>
                        </li>

                        <!-- ลิงก์หมายเลขหน้า -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link rounded-pill mx-1" href="recipes.php?page=<?php echo $i; ?><?php echo ($categoryId > 0) ? '&category=' . $categoryId : ''; ?>" style="<?php echo ($i === $page) ? 'background-color: var(--primary-color); border-color: var(--primary-color);' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- ปุ่มถัดไป (Next) -->
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link rounded-pill ms-1" href="recipes.php?page=<?php echo $page + 1; ?><?php echo ($categoryId > 0) ? '&category=' . $categoryId : ''; ?>" aria-label="ถัดไป">
                                ถัดไป <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php else: ?>
                <!-- แสดงข้อความสรุปเมื่อมีหน้าเดียว -->
                <div class="text-center text-muted small mt-4">
                    แสดงทั้งหมด <?php echo count($recipes); ?> รายการ
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- กล่องแจ้งเตือนเมื่อไม่พบข้อมูลสูตรอาหาร (Empty State) -->
            <div class="text-center py-5 px-3 bg-white rounded-4 border shadow-sm my-4">
                <div class="mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary-light); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto;">
                    <i class="bi bi-journal-x"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">ไม่พบสูตรอาหารในหมวดหมู่นี้</h3>
                <p class="text-muted mb-4 small" style="max-width: 480px; margin: 0 auto;">
                    <?php if ($categoryId > 0): ?>
                        ยังไม่มีสูตรอาหารในหมวด "<strong><?php echo htmlspecialchars($currentCategoryName); ?></strong>" ในขณะนี้
                    <?php else: ?>
                        ยังไม่มีข้อมูลสูตรอาหารในระบบ กรุณานำเข้าข้อมูลเริ่มต้นผ่าน phpMyAdmin
                    <?php endif; ?>
                </p>
                <a href="recipes.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2">
                    <i class="bi bi-grid me-1"></i> ดูสูตรอาหารทั้งหมด
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>
