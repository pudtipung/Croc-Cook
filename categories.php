<?php
/**
 * หน้าหมวดหมู่อาหาร - มีอะไรในครัว? (What's in My Kitchen)
 * แสดงรายการหมวดหมู่ทั้งหมดจากฐานข้อมูล MySQL พร้อมจำนวนสูตรอาหารในแต่ละหมวด
 */
$currentPage = 'categories';
$pageTitle = 'หมวดหมู่อาหาร | มีอะไรในครัว? (What\'s in My Kitchen)';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

// 2. รับค่าค้นหาหมวดหมู่ผ่าน URL (Prepared Statement & XSS-Safe)
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

$categories = [];
$totalCategories = 0;
$totalRecipesAll = 0;
$dbError = null;

try {
    // 3. ดึงข้อมูลหมวดหมู่ทั้งหมด พร้อมนับจำนวนสูตรอาหารและตัวอย่างเมนูจากตาราง recipes
    if ($searchTerm !== '') {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
                c.icon, 
                COUNT(r.id) AS recipe_count,
                GROUP_CONCAT(r.title ORDER BY r.id ASC SEPARATOR '||') AS sample_recipes
            FROM categories c
            LEFT JOIN recipes r ON c.id = r.category_id
            WHERE c.name LIKE :search1 OR c.description LIKE :search2
            GROUP BY c.id
            ORDER BY c.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':search1' => '%' . $searchTerm . '%',
            ':search2' => '%' . $searchTerm . '%'
        ]);
    } else {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
                c.icon, 
                COUNT(r.id) AS recipe_count,
                GROUP_CONCAT(r.title ORDER BY r.id ASC SEPARATOR '||') AS sample_recipes
            FROM categories c
            LEFT JOIN recipes r ON c.id = r.category_id
            GROUP BY c.id
            ORDER BY c.id ASC
        ";
        $stmt = $pdo->query($sql);
    }

    $categories = $stmt->fetchAll();
    $totalCategories = count($categories);

    // นับจำนวนสูตรอาหารรวมทั้งหมดที่มีในระบบ
    $countAllStmt = $pdo->query("SELECT COUNT(*) FROM recipes");
    $totalRecipesAll = (int) $countAllStmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Categories Page Error: " . $e->getMessage());
    $dbError = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง";
}

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styling สำหรับการ์ดหมวดหมู่อาหาร -->
<style>
.category-box-card {
    background-color: #ffffff;
    border: 1.5px solid var(--border-subtle);
    border-radius: 18px;
    padding: 1.75rem;
    transition: all 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}
.category-box-card:hover {
    transform: translateY(-5px);
    border-color: rgba(224, 93, 38, 0.4);
    box-shadow: 0 10px 25px rgba(224, 93, 38, 0.1);
}
.category-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%);
    color: var(--primary-color);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 4px 12px rgba(224, 93, 38, 0.12);
    transition: transform 0.25s ease;
}
.category-box-card:hover .category-icon-wrapper {
    transform: scale(1.08) rotate(3deg);
}
.sample-recipe-badge {
    background-color: #faf8f5;
    color: var(--text-dark);
    border: 1px solid var(--border-subtle);
    font-size: 0.75rem;
    font-weight: 500;
}
</style>

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
                    หมวดหมู่อาหาร
                </li>
            </ol>
        </nav>

        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold text-dark mb-1">
                    หมวดหมู่อาหาร <span class="title-highlight">Recipe Categories</span>
                </h1>
                <p class="text-muted mb-0">
                    เลือกดูสูตรอาหารตามประเภทและสไตล์การปรุงที่คุณชื่นชอบ เช่น เมนูผัด ต้ม แกง ทอด ยำ และน้ำพริก
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge rounded-pill px-3 py-2 fs-6 shadow-xs" style="background-color: var(--primary-light); color: var(--primary-color); border: 1px solid rgba(224,93,38,0.2);">
                    <i class="bi bi-grid me-1"></i>
                    ทั้งหมด <strong><?php echo number_format($totalCategories); ?></strong> หมวดหมู่
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Main Categories Section -->
<section class="py-5" style="background-color: var(--bg-cream); min-height: 550px;">
    <div class="container">

        <!-- Error Notification Banner (ถ้ามีข้อผิดพลาด) -->
        <?php if ($dbError): ?>
            <div class="alert alert-warning border-0 rounded-4 shadow-sm py-3 px-4 d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-3"></i>
                <div>
                    <h5 class="alert-heading h6 fw-bold mb-1">เกิดข้อผิดพลาดในการโหลดข้อมูล</h5>
                    <span class="small mb-0"><?php echo htmlspecialchars($dbError); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Toolbar: ช่องค้นหาหมวดหมู่ & ลิงก์ลัด -->
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-5 bg-white" style="border: 1px solid var(--border-subtle) !important;">
            <div class="row g-3 align-items-center justify-content-between">
                
                <!-- Search Box -->
                <div class="col-lg-5 col-md-6">
                    <form action="categories.php" method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   name="search" 
                                   class="form-control border-start-0 ps-0" 
                                   placeholder="ค้นหาหมวดหมู่ เช่น ผัด, ต้ม, ยำ..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                   aria-label="ค้นหาหมวดหมู่">
                            <?php if ($searchTerm !== ''): ?>
                                <a href="categories.php" class="btn btn-outline-secondary border-start-0" title="ล้างการค้นหา">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-kitchen-primary rounded-pill px-3">
                            ค้นหา
                        </button>
                    </form>
                </div>

                <!-- Quick Stats & Link to All Recipes -->
                <div class="col-lg-7 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                    <span class="text-muted small me-2">
                        รวมสูตรอาหารในระบบทั้งหมด: <strong class="text-dark"><?php echo number_format($totalRecipesAll); ?></strong> เมนู
                    </span>
                    <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
                        <i class="bi bi-journal-bookmark me-1"></i> ดูสูตรอาหารทั้งหมด
                    </a>
                    <a href="ingredients.php" class="btn btn-sm btn-kitchen-primary rounded-pill px-3 py-2">
                        <i class="bi bi-basket2 me-1"></i> ค้นหาจากวัตถุดิบ
                    </a>
                </div>

            </div>
        </div>

        <!-- Categories Grid -->
        <?php if (!empty($categories)): ?>
            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3">
                <?php foreach ($categories as $cat): ?>
                    <?php 
                    $catId = (int) $cat['id'];
                    $recipeCount = (int) $cat['recipe_count'];
                    
                    ?>
                    <div class="col" id="category-<?php echo $catId; ?>">
                        <div class="category-box-card">
                            
                            <!-- Top Part: Icon & Recipe Count Badge -->
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="main-food-icon">
                         <img src="im/logo.png" alt="Logo" style="max-width: 80px; height: auto; border-radius: 50%; object-fit: cover;">
                    </div>
                                    <?php if ($recipeCount > 0): ?>
                                        <span class="badge rounded-pill px-3 py-2" style="background-color: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; font-weight: 500; font-size: 0.8rem;">
                                            <i class="bi bi-journal-check me-1"></i><?php echo $recipeCount; ?> สูตรอาหาร
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill px-3 py-2 bg-light text-muted border" style="font-weight: 500; font-size: 0.8rem;">
                                            <i class="bi bi-hourglass me-1"></i>0 สูตรอาหาร
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Category Name -->
                                <h2 class="h5 fw-bold text-dark mb-2">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </h2>

                                <!-- Category Description -->
                                <p class="text-muted small mb-3" style="line-height: 1.6; min-height: 44px;">
                                    <?php echo htmlspecialchars($cat['description'] ?? 'ไม่มีคำอธิบายหมวดหมู่'); ?>
                                </p>

                                <!-- Sample Recipes (ตัวอย่างเมนูในหมวด) -->
                                <?php if (!empty($cat['sample_recipes'])): ?>
                                    <div class="mb-3 pt-2 border-top">
                                        <span class="small text-muted d-block mb-1" style="font-size: 0.78rem;">
                                            <i class="bi bi-fire text-warning me-1"></i>ตัวอย่างเมนูในหมวดนี้:
                                        </span>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php 
                                            $samples = explode('||', $cat['sample_recipes']);
                                            foreach (array_slice($samples, 0, 3) as $sTitle): ?>
                                                <span class="badge sample-recipe-badge rounded-pill px-2 py-1">
                                                    <?php echo htmlspecialchars($sTitle); ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if (count($samples) > 3): ?>
                                                <span class="badge bg-light text-muted border rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                                                    + อีก <?php echo count($samples) - 3; ?> เมนู
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- ข้อความแจ้งกรณีหมวดหมู่นี้ยังไม่มีสูตรอาหาร -->
                                    <div class="mb-3 pt-2 border-top">
                                        <div class="p-2 rounded-3 text-muted small bg-light border text-center" style="font-size: 0.8rem;">
                                            <i class="bi bi-info-circle me-1 text-warning"></i>ยังไม่มีเมนูในหมวดนี้ กำลังอัปเดตเร็ว ๆ นี้
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Bottom Action Button: ลิงก์ไปยัง recipes.php?category={id} -->
                            <div class="pt-3 border-top mt-auto">
                                <?php if ($recipeCount > 0): ?>
                                    <a href="recipes.php?category=<?php echo $catId; ?>" 
                                       class="btn btn-kitchen-primary w-100 rounded-pill py-2 d-inline-flex align-items-center justify-content-center gap-1">
                                        <span>ดูสูตรอาหารในหมวดนี้</span>
                                        <i class="bi bi-arrow-right small ms-1"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="recipes.php?category=<?php echo $catId; ?>" 
                                       class="btn btn-outline-secondary w-100 rounded-pill py-2 d-inline-flex align-items-center justify-content-center gap-1">
                                        <span>ดูสูตรอาหารในหมวดนี้</span>
                                        <i class="bi bi-arrow-right small ms-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($searchTerm !== ''): ?>
            <!-- กรณีค้นหาแล้วไม่พบหมวดหมู่ที่ตรงกัน -->
            <div class="text-center py-5 px-3 bg-white rounded-4 border shadow-sm my-4">
                <div class="mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: #fff2ea; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto;">
                    <i class="bi bi-search"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">ไม่พบหมวดหมู่อาหารที่ตรงกับ "<?php echo htmlspecialchars($searchTerm); ?>"</h3>
                <p class="text-muted mb-4 small" style="max-width: 480px; margin: 0 auto;">
                    ลองเปลี่ยนคำค้นหา หรือกดปุ่มด้านล่างเพื่อแสดงหมวดหมู่อาหารทั้งหมดที่มีในระบบ
                </p>
                <a href="categories.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> แสดงหมวดหมู่อาหารทั้งหมด
                </a>
            </div>

        <?php else: ?>
            <!-- กรณีไม่มีข้อมูลหมวดหมู่ในฐานข้อมูลเลย -->
            <div class="text-center py-5 px-3 bg-white rounded-4 border shadow-sm my-4">
                <div class="mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: #fff2ea; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto;">
                    <i class="bi bi-folder-x"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">ยังไม่มีข้อมูลหมวดหมู่อาหารในระบบ</h3>
                <p class="text-muted mb-4 small" style="max-width: 480px; margin: 0 auto;">
                    กรุณานำเข้าข้อมูลเริ่มต้นผ่านไฟล์ <code>database/schema.sql</code> ใน phpMyAdmin เพื่อเริ่มใช้งาน
                </p>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                    <i class="bi bi-arrow-left me-1"></i> กลับสู่หน้าแรก
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>
