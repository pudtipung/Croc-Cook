<?php
/**
 * หน้าแสดงผลลัพธ์การค้นหาสูตรอาหารจากวัตถุดิบที่เลือก - มีอะไรในครัว? (What's in My Kitchen)
 */
$currentPage = 'search';
$pageTitle = 'ผลการค้นหาสูตรอาหาร | มีอะไรในครัว? (What\'s in My Kitchen)';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

// 2. รับค่า ID ของวัตถุดิบที่ผู้ใช้เลือก (รองรับทั้ง GET และ POST)
$rawIngredients = $_GET['ingredients'] ?? ($_POST['ingredients'] ?? []);
$selectedIds = [];

if (is_array($rawIngredients)) {
    foreach ($rawIngredients as $val) {
        $id = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id) {
            $selectedIds[] = $id;
        }
    }
}
$selectedIds = array_values(array_unique($selectedIds));

$selectedIngredientsInfo = [];
$readyToCook = [];
$needMoreIngredients = [];
$dbError = null;

if (!empty($selectedIds)) {
    try {
        // 3. ดึงรายชื่อวัตถุดิบที่ผู้ใช้เลือก เพื่อนำมาแสดงสรุปที่ด้านบน
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmtSel = $pdo->prepare("
            SELECT id, name, category_group 
            FROM ingredients 
            WHERE id IN ($placeholders) 
            ORDER BY name ASC
        ");
        $stmtSel->execute($selectedIds);
        $selectedIngredientsInfo = $stmtSel->fetchAll();

        // 4. ดึงข้อมูลสูตรอาหารทั้งหมดพร้อมวัตถุดิบที่จำเป็นและทางเลือก (Prepared Statement)
        $sql = "
            SELECT 
                r.id, 
                r.title, 
                r.description,
                r.image,
                r.cooking_time, 
                r.servings, 
                c.name AS category_name, 
                c.icon AS category_icon,
                ri.ingredient_id, 
                ri.quantity, 
                ri.unit, 
                ri.is_essential, 
                i.name AS ingredient_name,
                i.type,
                i.category_group
            FROM recipes r
            LEFT JOIN categories c ON r.category_id = c.id
            JOIN recipe_ingredients ri ON r.id = ri.recipe_id
            JOIN ingredients i ON ri.ingredient_id = i.id
            ORDER BY r.id ASC
        ";
        $allRows = $pdo->query($sql)->fetchAll();

        // 5. ประมวลผลจับคู่ (Matching Algorithm) วัตถุดิบของผู้ใช้กับสูตรอาหาร
        $matchedRecipes = [];
        foreach ($allRows as $row) {
            $rid = $row['id'];
            if (!isset($matchedRecipes[$rid])) {
                $matchedRecipes[$rid] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'image' => $row['image'],
                    'cooking_time' => $row['cooking_time'],
                    'servings' => $row['servings'],
                    'category_name' => $row['category_name'],
                    'category_icon' => $row['category_icon'],
                    'have' => [],
                    'missing' => [],
                    'missing_essential' => 0,
                    // จำแนกวัตถุดิบ 4 กลุ่มชัดเจนตามโจทย์
                    'group_have' => [],              // สิ่งที่มีอยู่ (สีเขียว)
                    'group_missing_essential' => [], // สิ่งที่ขาดจำเป็น (สีแดง)
                    'group_seasonings' => [],        // เครื่องปรุงรส (สีเหลือง)
                    'group_optional' => []           // ของที่ใส่หรือไม่ใส่ก็ได้ (สีขาว)
                ];
            }

            $isHave = in_array((int)$row['ingredient_id'], $selectedIds, true);
            $isEssential = ((int)($row['is_essential'] ?? 1) === 1);
            $ingredientType = $row['type'] ?? 'main';
            $categoryGroup = $row['category_group'] ?? '';
            $isSeasoning = ($ingredientType === 'seasoning' || $categoryGroup === 'เครื่องปรุงรส');

            if ($isHave) {
                $matchedRecipes[$rid]['have'][] = $row;
                // กลุ่ม 1: สิ่งที่มีอยู่ (สีเขียว)
                $matchedRecipes[$rid]['group_have'][] = $row;
            } else {
                $matchedRecipes[$rid]['missing'][] = $row;
                if ($isEssential) {
                    $matchedRecipes[$rid]['missing_essential']++;
                }

                // จำแนกกลุ่มที่ผู้ใช้ยังไม่มี
                if ($isSeasoning) {
                    // กลุ่ม 3: เครื่องปรุง (สีเหลือง)
                    $matchedRecipes[$rid]['group_seasonings'][] = $row;
                } elseif ($isEssential) {
                    // กลุ่ม 2: สิ่งที่ขาด (จำเป็น) (สีแดง)
                    $matchedRecipes[$rid]['group_missing_essential'][] = $row;
                } else {
                    // กลุ่ม 4: ของที่ใส่หรือไม่ใส่ก็ได้ (สีขาว)
                    $matchedRecipes[$rid]['group_optional'][] = $row;
                }
            }
        }

        // 6. จำแนกเป็น 2 กลุ่มอย่างชัดเจน:
        //    กลุ่มที่ 1: เมนูที่พร้อมปรุงทันที (วัตถุดิบจำเป็นครบ 100%)
        //    กลุ่มที่ 2: เมนูที่ยังขาดบางรายการ (มีวัตถุดิบบางส่วน)
        foreach ($matchedRecipes as $r) {
            $haveCount = count($r['have']);
            $missingCount = count($r['missing']);
            $totalCount = $haveCount + $missingCount;

            $r['have_count'] = $haveCount;
            $r['missing_count'] = $missingCount;
            $r['total_count'] = $totalCount;
            $r['match_percent'] = ($totalCount > 0) ? round(($haveCount / $totalCount) * 100) : 0;

            // กรองเฉพาะเมนูที่ตรงกับวัตถุดิบของผู้ใช้อย่างน้อย 1 อย่าง
            if ($haveCount > 0) {
                if ($r['missing_essential'] === 0) {
                    // วัตถุดิบหลักจำเป็นครบถ้วน พร้อมทำทันที
                    $readyToCook[] = $r;
                } else {
                    // ยังขาดวัตถุดิบหลักจำเป็นบางอย่าง
                    $needMoreIngredients[] = $r;
                }
            }
        }

        // เรียงลำดับเมนูตามเปอร์เซ็นต์ความตรงกันมากที่สุดไปหาน้อยที่สุด
        usort($readyToCook, function ($a, $b) {
            return $b['match_percent'] <=> $a['match_percent'];
        });
        usort($needMoreIngredients, function ($a, $b) {
            return $b['have_count'] <=> $a['have_count'];
        });

    } catch (PDOException $e) {
        error_log("Find Recipes Error: " . $e->getMessage());
        $dbError = "ไม่สามารถประมวลผลการค้นหาได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง";
    }
}

/**
 * ฟังก์ชันช่วยแสดงผลรายการวัตถุดิบ 4 สถานะ แยกสีตามโจทย์อย่างชัดเจน
 * 1. สิ่งที่มีอยู่ -> สีเขียว (Green)
 * 2. สิ่งที่ขาด (จำเป็น) -> สีแดง (Red)
 * 3. เครื่องปรุง -> สีเหลือง (Yellow)
 * 4. ของที่ใส่หรือไม่ใส่ก็ได้ -> สีขาว (White)
 */
function renderIngredientStatusBadges(array $recipe): void {
    ?>
    <div class="ingredient-status-group mb-3">
        <!-- 1. ของที่มีอยู่ (สีเขียว) -->
        <?php if (!empty($recipe['group_have'])): ?>
            <div class="mb-2">
                <span class="small fw-semibold text-success d-inline-flex align-items-center gap-1 mb-1">
                    <i class="bi bi-check-circle-fill" style="font-size: 0.8rem;"></i>
                    ของที่มีอยู่ (<?php echo count($recipe['group_have']); ?>):
                </span>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($recipe['group_have'] as $h): ?>
                        <span class="badge rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1" 
                              style="background-color: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; font-size: 0.74rem; font-weight: 500;">
                            <i class="bi bi-check-lg text-success"></i>
                            <?php echo htmlspecialchars($h['ingredient_name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. ของที่ขาด (จำเป็น) (สีแดง) -->
        <?php if (!empty($recipe['group_missing_essential'])): ?>
            <div class="mb-2">
                <span class="small fw-semibold text-danger d-inline-flex align-items-center gap-1 mb-1">
                    <i class="bi bi-x-circle-fill" style="font-size: 0.8rem;"></i>
                    ของที่ขาด (จำเป็น <?php echo count($recipe['group_missing_essential']); ?> อย่าง):
                </span>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($recipe['group_missing_essential'] as $m): ?>
                        <span class="badge rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1" 
                              style="background-color: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; font-size: 0.74rem; font-weight: 500;">
                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 0.65rem;"></i>
                            <?php echo htmlspecialchars($m['ingredient_name']); ?>
                            <?php if (!empty($m['quantity'])): ?>
                                <span class="small" style="color: #c62828;">(<?php echo htmlspecialchars(trim($m['quantity'] . ' ' . $m['unit'])); ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. เครื่องปรุง (สีเหลือง) -->
        <?php if (!empty($recipe['group_seasonings'])): ?>
            <div class="mb-2">
                <span class="small fw-semibold d-inline-flex align-items-center gap-1 mb-1" style="color: #92400e;">
                    <i class="bi bi-droplet-fill text-warning" style="font-size: 0.8rem;"></i>
                    เครื่องปรุงรส (<?php echo count($recipe['group_seasonings']); ?> อย่าง):
                </span>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($recipe['group_seasonings'] as $s): ?>
                        <span class="badge rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1" 
                              style="background-color: #fff9c4; color: #78350f; border: 1px solid #ffe082; font-size: 0.74rem; font-weight: 500;">
                            <i class="bi bi-dash" style="color: #b45309;"></i>
                            <?php echo htmlspecialchars($s['ingredient_name']); ?>
                            <?php if (!empty($s['quantity'])): ?>
                                <span class="small" style="color: #92400e;">(<?php echo htmlspecialchars(trim($s['quantity'] . ' ' . $s['unit'])); ?>)</span>
                            <?php endif; ?>
                            <?php if ((int)$s['is_essential'] === 1): ?>
                                <span class="badge bg-danger text-white rounded-pill ms-1" style="font-size: 0.58rem; padding: 1px 4px;">จำเป็น</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4. ของที่ใส่หรือไม่ใส่ก็ได้ (สีขาว) -->
        <?php if (!empty($recipe['group_optional'])): ?>
            <div class="mb-1">
                <span class="small fw-semibold text-secondary d-inline-flex align-items-center gap-1 mb-1">
                    <i class="bi bi-dash-circle text-muted" style="font-size: 0.8rem;"></i>
                    ของที่ใส่หรือไม่ใส่ก็ได้ (ทางเลือก <?php echo count($recipe['group_optional']); ?> อย่าง):
                </span>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($recipe['group_optional'] as $opt): ?>
                        <span class="badge rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1 bg-white text-dark" 
                              style="border: 1px solid #ced4da; box-shadow: 0 1px 2px rgba(0,0,0,0.04); font-size: 0.74rem; font-weight: 500;">
                            <i class="bi bi-circle text-muted" style="font-size: 0.5rem;"></i>
                            <?php echo htmlspecialchars($opt['ingredient_name']); ?>
                            <?php if (!empty($opt['quantity'])): ?>
                                <span class="text-muted small">(<?php echo htmlspecialchars(trim($opt['quantity'] . ' ' . $opt['unit'])); ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
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
                <li class="breadcrumb-item">
                    <a href="ingredients.php" class="text-decoration-none" style="color: var(--primary-color);">
                        เลือกวัตถุดิบ
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page" style="color: var(--text-muted);">
                    ผลการค้นหาสูตรอาหาร
                </li>
            </ol>
        </nav>

        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold text-dark mb-1">
                    เมนูที่คุณทำได้ <span class="title-highlight">Recipe Matches</span>
                </h1>
                <p class="text-muted mb-0">
                    ระบบจับคู่วัตถุดิบที่คุณมีกับสูตรอาหารในครัว พร้อมคำนวณวัตถุดิบที่พร้อมและวัตถุดิบที่ยังขาด
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="ingredients.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> ปรับเปลี่ยนวัตถุดิบ
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Main Results Area -->
<section class="py-5" style="background-color: var(--bg-cream); min-height: 550px;">
    <div class="container">

        <?php if ($dbError): ?>
            <div class="alert alert-warning border-0 rounded-4 shadow-sm py-3 px-4 d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-3"></i>
                <div>
                    <h5 class="alert-heading h6 fw-bold mb-1">เกิดข้อผิดพลาดในการโหลดข้อมูล</h5>
                    <span class="small mb-0"><?php echo htmlspecialchars($dbError); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($selectedIds)): ?>
            <!-- กรณีผู้ใช้ยังไม่ได้เลือกวัตถุดิบใด ๆ -->
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 text-center">
                    <div class="card border-0 shadow-sm rounded-4 p-5 bg-white">
                        <div class="mb-4" style="width: 86px; height: 86px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.6rem; margin: 0 auto;">
                            <i class="bi bi-basket2"></i>
                        </div>
                        <h2 class="h5 fw-bold text-dark mb-2">ยังไม่ได้เลือกวัตถุดิบสำหรับค้นหา</h2>
                        <p class="text-muted mb-4 small" style="line-height: 1.7;">
                            กรุณาเข้าไปติ๊กเลือกวัตถุดิบที่คุณมีอยู่ในครัวก่อน เพื่อให้ระบบสามารถประมวลผลจับคู่เมนูอาหารที่เหมาะสมให้คุณได้
                        </p>
                        <div>
                            <a href="ingredients.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2">
                                <i class="bi bi-check2-square me-1"></i> ไปหน้าเลือกวัตถุดิบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <!-- สรุปวัตถุดิบที่ผู้ใช้เลือก (Selected Ingredients Bar) -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-5" style="border: 1px solid var(--border-subtle) !important;">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <h2 class="h6 fw-bold text-dark mb-0">วัตถุดิบที่คุณมีในครัว</h2>
                        <span class="badge rounded-pill bg-light text-dark border">
                            <?php echo count($selectedIngredientsInfo); ?> อย่าง
                        </span>
                    </div>
                    <a href="ingredients.php" class="small text-decoration-none fw-medium" style="color: var(--primary-color);">
                        <i class="bi bi-pencil-square me-1"></i>แก้ไขวัตถุดิบ
                    </a>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($selectedIngredientsInfo as $item): ?>
                        <span class="badge rounded-pill px-3 py-2 fw-medium d-inline-flex align-items-center gap-1" style="background-color: #fff2ea; color: var(--primary-color); border: 1px solid rgba(224, 93, 38, 0.2); font-size: 0.85rem;">
                            <i class="bi bi-check-lg small"></i>
                            <?php echo htmlspecialchars($item['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- แถบอธิบายสัญลักษณ์สี 4 สถานะวัตถุดิบ (Color Legend) ตามโจทย์ -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-5" style="border: 1px solid var(--border-subtle) !important;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background-color: #fff2ea; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="bi bi-palette-fill"></i>
                        </div>
                        <div>
                            <span class="fw-bold text-dark small d-block">คำอธิบายสัญลักษณ์สีของวัตถุดิบ</span>
                            <span class="text-muted" style="font-size: 0.78rem;">ช่วยให้คุณเช็กความพร้อมในการทำแต่ละเมนูได้อย่างชัดเจนและรวดเร็ว</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" style="background-color: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; font-size: 0.78rem; font-weight: 500;">
                            <i class="bi bi-check-circle-fill text-success"></i> สีเขียว: สิ่งที่มีอยู่
                        </span>
                        <span class="badge rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" style="background-color: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; font-size: 0.78rem; font-weight: 500;">
                            <i class="bi bi-x-circle-fill text-danger"></i> สีแดง: สิ่งที่ขาด (จำเป็น)
                        </span>
                        <span class="badge rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1" style="background-color: #fff9c4; color: #78350f; border: 1px solid #ffe082; font-size: 0.78rem; font-weight: 500;">
                            <i class="bi bi-droplet-fill text-warning"></i> สีเหลือง: เครื่องปรุง
                        </span>
                        <span class="badge rounded-pill px-3 py-2 d-inline-flex align-items-center gap-1 bg-white text-dark" style="border: 1px solid #ced4da; box-shadow: 0 1px 2px rgba(0,0,0,0.04); font-size: 0.78rem; font-weight: 500;">
                            <i class="bi bi-dash-circle text-muted"></i> สีขาว: ของที่ใส่หรือไม่ใส่ก็ได้
                        </span>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- กลุ่มที่ 1: เมนูที่พร้อมปรุงทันที (วัตถุดิบหลักครบถ้วน) -->
            <!-- ============================================================== -->
            <div class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 40px; height: 40px; border-radius: 12px; background-color: #e8f5e9; color: #2e7d32; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                            <i class="bi bi-fire"></i>
                        </div>
                        <div>
                            <h2 class="h5 fw-bold text-dark mb-0">เมนูที่พร้อมปรุงทันที (วัตถุดิบครบถ้วน)</h2>
                            <small class="text-muted">คุณมีวัตถุดิบหลักครบตามสูตร สามารถลงมือทำได้เลย</small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis px-3 py-2 fs-6 mt-2 mt-md-0">
                        พร้อมทำ <?php echo count($readyToCook); ?> เมนู
                    </span>
                </div>

                <?php if (!empty($readyToCook)): ?>
                    <div class="row g-4">
                        <?php foreach ($readyToCook as $recipe): ?>
                            <?php
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
                            <div class="col-lg-6">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white" style="border: 1px solid var(--border-subtle) !important;">
                                    <div class="row g-0 h-100">
                                        <!-- Recipe Thumbnail / Fallback Banner -->
                                        <div class="col-sm-4 position-relative d-flex align-items-center justify-content-center text-center p-3" style="background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%); min-height: 180px;">
                                            <?php if ($hasImage): ?>
                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" class="w-100 h-100 rounded-3" style="object-fit: cover;" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                            <?php else: ?>

                                            <?php endif; ?>
                                            <span class="position-absolute top-0 start-0 m-2 badge bg-success rounded-pill small">
                                                <i class="bi bi-check-circle-fill me-1"></i>พร้อมปรุง
                                            </span>
                                        </div>

                                        <!-- Recipe Details & Match Stats -->
                                        <div class="col-sm-8 p-3 p-md-4 d-flex flex-column justify-content-between">
                                            <div>
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="small text-muted">
                                                        <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1" style="color: var(--primary-color);"></i>
                                                        <?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                                                    </span>
                                                    <span class="badge bg-success-subtle text-success-emphasis rounded-pill">
                                                        ตรงกัน <?php echo $recipe['match_percent']; ?>%
                                                    </span>
                                                </div>

                                                <h3 class="h6 fw-bold text-dark mb-2">
                                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none text-dark hover-primary">
                                                        <?php echo htmlspecialchars($recipe['title']); ?>
                                                    </a>
                                                </h3>

                                                <div class="d-flex gap-3 small text-muted mb-3">
                                                    <span><i class="bi bi-clock text-warning me-1"></i><?php echo (int)$recipe['cooking_time']; ?> นาที</span>
                                                    <span><i class="bi bi-people text-secondary me-1"></i><?php echo (int)$recipe['servings']; ?> ที่</span>
                                                </div>

                                                <!-- วัตถุดิบจำแนก 4 สถานะ แยกสีชัดเจน -->
                                                <?php renderIngredientStatusBadges($recipe); ?>
                                            </div>

                                            <div class="pt-2 border-top mt-2">
                                                <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="btn btn-sm btn-kitchen-primary w-100 rounded-pill py-2">
                                                    <i class="bi bi-book-half me-1"></i> ดูวิธีทำสูตรนี้
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 px-3 bg-white rounded-4 border">
                        <i class="bi bi-info-circle text-muted fs-3 mb-2 d-block"></i>
                        <p class="text-muted mb-0 small">ยังไม่มีเมนูที่วัตถุดิบครบถ้วน 100% ลองดูเมนูที่เกือบพร้อมทำด้านล่าง หรือเพิ่มวัตถุดิบเพิ่มเติม</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============================================================== -->
            <!-- กลุ่มที่ 2: เมนูที่ยังขาดบางรายการ (เกือบพร้อมทำ) -->
            <!-- ============================================================== -->
            <div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 40px; height: 40px; border-radius: 12px; background-color: #fff3e0; color: #e65100; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                            <i class="bi bi-cart-plus"></i>
                        </div>
                        <div>
                            <h2 class="h5 fw-bold text-dark mb-0">เมนูที่ยังขาดวัตถุดิบบางรายการ (เกือบพร้อมทำ)</h2>
                            <small class="text-muted">มีวัตถุดิบแล้วบางส่วน ขาดเพียงบางรายการก็สามารถทำมื้ออร่อยได้</small>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis px-3 py-2 fs-6 mt-2 mt-md-0">
                        พบ <?php echo count($needMoreIngredients); ?> เมนู
                    </span>
                </div>

                <?php if (!empty($needMoreIngredients)): ?>
                    <div class="row g-4">
                        <?php foreach ($needMoreIngredients as $recipe): ?>
                            <?php
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
                            <div class="col-lg-6">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white" style="border: 1px solid var(--border-subtle) !important;">
                                    <div class="row g-0 h-100">
                                        <!-- Thumbnail / Icon -->
                                        <div class="col-sm-4 position-relative d-flex align-items-center justify-content-center text-center p-3" style="background: linear-gradient(135deg, #fff8f5 0%, #faede6 100%); min-height: 180px;">
                                            <?php if ($hasImage): ?>
                                                <img src="<?php echo htmlspecialchars($imagePath); ?>" class="w-100 h-100 rounded-3" style="object-fit: cover;" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                            <?php else: ?>
                                                <div style="width: 70px; height: 70px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; box-shadow: 0 4px 12px rgba(224, 93, 38, 0.15);">
                                                    <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-egg-fried'; ?>"></i>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($recipe['group_missing_essential'])): ?>
                                                <span class="position-absolute top-0 start-0 m-2 badge bg-danger rounded-pill small shadow-sm">
                                                    <i class="bi bi-x-circle-fill me-1"></i>ขาดของจำเป็น <?php echo count($recipe['group_missing_essential']); ?> อย่าง
                                                </span>
                                            <?php else: ?>
                                                <span class="position-absolute top-0 start-0 m-2 badge bg-warning text-dark rounded-pill small shadow-sm">
                                                    <i class="bi bi-droplet-half me-1"></i>ขาดเครื่องปรุง
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Details & Missing list -->
                                        <div class="col-sm-8 p-3 p-md-4 d-flex flex-column justify-content-between">
                                            <div>
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="small text-muted">
                                                        <i class="bi <?php echo !empty($recipe['category_icon']) ? htmlspecialchars($recipe['category_icon']) : 'bi-tag'; ?> me-1" style="color: var(--primary-color);"></i>
                                                        <?php echo htmlspecialchars($recipe['category_name'] ?? 'ทั่วไป'); ?>
                                                    </span>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">
                                                        ตรงกัน <?php echo $recipe['match_percent']; ?>%
                                                    </span>
                                                </div>

                                                <h3 class="h6 fw-bold text-dark mb-2">
                                                    <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="text-decoration-none text-dark hover-primary">
                                                        <?php echo htmlspecialchars($recipe['title']); ?>
                                                    </a>
                                                </h3>

                                                <div class="d-flex gap-3 small text-muted mb-3">
                                                    <span><i class="bi bi-clock text-warning me-1"></i><?php echo (int)$recipe['cooking_time']; ?> นาที</span>
                                                    <span><i class="bi bi-people text-secondary me-1"></i><?php echo (int)$recipe['servings']; ?> ที่</span>
                                                </div>

                                                <!-- วัตถุดิบจำแนก 4 สถานะ แยกสีชัดเจน -->
                                                <?php renderIngredientStatusBadges($recipe); ?>
                                            </div>

                                            <div class="pt-2 border-top mt-2">
                                                <a href="recipe_detail.php?id=<?php echo (int)$recipe['id']; ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill py-2">
                                                    <i class="bi bi-book-half me-1"></i> ดูสูตรและส่วนผสมทั้งหมด
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 px-3 bg-white rounded-4 border">
                        <i class="bi bi-journal-check text-success fs-3 mb-2 d-block"></i>
                        <p class="text-muted mb-0 small">ไม่มีเมนูที่ขาดวัตถุดิบ ทุกเมนูที่คุณมีตรงกันพร้อมทำได้เลย!</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</section>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>

