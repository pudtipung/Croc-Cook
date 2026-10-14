<?php
/**
 * หน้าเลือกวัตถุดิบที่มีอยู่ในครัว - มีอะไรในครัว? (What's in My Kitchen)
 */
$currentPage = 'search';
$pageTitle = 'เลือกวัตถุดิบในครัว | มีอะไรในครัว? (What\'s in My Kitchen)';

// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลเดิมจาก includes/db.php
require_once __DIR__ . '/includes/db.php';

$groupedIngredients = [];
$totalIngredientsCount = 0;
$dbError = null;

try {
    // 2. ดึงข้อมูลวัตถุดิบทั้งหมดจากตาราง ingredients จัดกลุ่มตาม category_group
    $stmt = $pdo->query("
        SELECT id, name, type, category_group 
        FROM ingredients 
        ORDER BY 
            FIELD(category_group, 'เนื้อสัตว์', 'ไข่และแป้ง', 'ผักและสมุนไพร', 'เครื่องปรุงรส', 'ทั่วไป'),
            name ASC
    ");
    $ingredients = $stmt->fetchAll();
    $totalIngredientsCount = count($ingredients);

    // จัดกลุ่มข้อมูลตาม category_group
    foreach ($ingredients as $ing) {
        $group = !empty($ing['category_group']) ? $ing['category_group'] : 'ทั่วไป';
        $groupedIngredients[$group][] = $ing;
    }
} catch (PDOException $e) {
    error_log("Ingredients Page Error: " . $e->getMessage());
    $dbError = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง";
}

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styling สำหรับ Checkbox Card และ Sticky Footer Bar -->
<style>
.ingredient-card {
    background-color: #ffffff;
    border: 1.5px solid var(--border-subtle);
    border-radius: 14px;
    padding: 1rem;
    transition: all 0.2s ease;
    cursor: pointer;
    user-select: none;
    height: 100%;
    display: flex;
    align-items: center;
}
.ingredient-card:hover {
    border-color: var(--primary-color);
    background-color: #fff9f6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(224, 93, 38, 0.08);
}
.ingredient-card.selected {
    border-color: var(--primary-color);
    background-color: #fff2ea;
    box-shadow: 0 4px 14px rgba(224, 93, 38, 0.12);
}
.ingredient-card .form-check-input {
    width: 1.35rem;
    height: 1.35rem;
    cursor: pointer;
    border-color: #ced4da;
    border-radius: 6px;
}
.ingredient-card .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}
.category-header-badge {
    background-color: #ffffff;
    border: 1px solid var(--border-subtle);
    color: var(--text-dark);
    padding: 0.5rem 1.25rem;
    border-radius: 50rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}
.sticky-bottom-bar {
    position: sticky;
    bottom: 0;
    z-index: 1020;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--border-subtle);
    box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.08);
    padding: 1rem 0;
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
                    เลือกวัตถุดิบในครัว
                </li>
            </ol>
        </nav>

        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold text-dark mb-1">
                    มีอะไรในครัวของคุณบ้าง? <span class="title-highlight">Select Ingredients</span>
                </h1>
                <p class="text-muted mb-0">
                    สำรวจของในตู้เย็นแล้วติ๊กเลือกวัตถุดิบที่คุณมี ระบบจะช่วยค้นหาสูตรอาหารที่ปรุงได้ทันที
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge rounded-pill px-3 py-2 fs-6 shadow-xs" style="background-color: var(--primary-light); color: var(--primary-color); border: 1px solid rgba(224,93,38,0.2);">
                    <i class="bi bi-basket2 me-1"></i>
                    คลังวัตถุดิบทั้งหมด <strong><?php echo number_format($totalIngredientsCount); ?></strong> รายการ
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Main Ingredient Selector Section -->
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

        <!-- Form ส่งข้อมูลวัตถุดิบที่เลือกไปยังหน้าผลลัพธ์ find_recipes.php -->
        <form id="ingredientForm" action="find_recipes.php" method="GET">
            
            <!-- Filter & Action Control Toolbar -->
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white" style="border: 1px solid var(--border-subtle) !important;">
                <div class="row g-3 align-items-center">
                    
                    <!-- Search Input Box (ค้นหาด่วนตามชื่อวัตถุดิบ) -->
                    <div class="col-lg-5 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="quickSearchInput" class="form-control border-start-0 ps-0" placeholder="พิมพ์ชื่อวัตถุดิบ เช่น ไข่, หมู, กะเพรา..." aria-label="ค้นหาวัตถุดิบ">
                            <button class="btn btn-outline-secondary border-start-0" type="button" id="clearSearchBtn" style="display: none;">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons: เลือกทั้งหมด & ล้างรายการ -->
                    <div class="col-lg-7 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                        <button type="button" id="selectAllBtn" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
                            <i class="bi bi-check-all me-1 text-success"></i> เลือกทั้งหมด
                        </button>
                        <button type="button" id="clearAllBtn" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2">
                            <i class="bi bi-x-circle me-1"></i> ล้างรายการที่เลือก
                        </button>
                        <div class="vr d-none d-sm-block mx-1"></div>
                        <span id="selectedCountBadge" class="badge rounded-pill px-3 py-2 text-dark border bg-light fs-6">
                            เลือกแล้ว <strong id="selectedCount" class="text-primary">0</strong> รายการ
                        </span>
                    </div>

                </div>
            </div>

            <!-- Grouped Ingredients Grid -->
            <?php if (!empty($groupedIngredients)): ?>
                <?php
                // กำหนดไอคอนสำหรับแต่ละกลุ่มวัตถุดิบ
                $groupIcons = [
                    'เนื้อสัตว์' => 'bi-egg-fried text-danger',
                    'ไข่และแป้ง' => 'bi-egg text-warning',
                    'ผักและสมุนไพร' => 'bi-flower1 text-success',
                    'เครื่องปรุงรส' => 'bi-cup-hot text-primary',
                    'ทั่วไป' => 'bi-basket2 text-secondary',
                ];
                ?>

                <div id="ingredientGroupsContainer">
                    <?php foreach ($groupedIngredients as $groupName => $items): ?>
                        <div class="ingredient-group-section mb-5" data-group="<?php echo htmlspecialchars($groupName); ?>">
                            
                            <!-- Group Title Header -->
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="category-header-badge shadow-xs">
                                    <i class="bi <?php echo $groupIcons[$groupName] ?? 'bi-basket2 text-secondary'; ?> fs-5"></i>
                                    <span><?php echo htmlspecialchars($groupName); ?></span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill ms-1 group-count">
                                        <?php echo count($items); ?>
                                    </span>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none group-select-btn" data-group-name="<?php echo htmlspecialchars($groupName); ?>" style="color: var(--primary-color);">
                                    <i class="bi bi-check2-circle me-1"></i>เลือกกลุ่มนี้ทั้งหมด
                                </button>
                            </div>

                            <!-- Group Checkbox Items Grid (Responsive) -->
                            <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-lg-4">
                                <?php foreach ($items as $item): ?>
                                    <div class="col ingredient-item" data-name="<?php echo htmlspecialchars(mb_strtolower($item['name'])); ?>">
                                        <label class="ingredient-card" for="ing_<?php echo (int) $item['id']; ?>">
                                            <input class="form-check-input ingredient-checkbox me-3 mt-0" 
                                                   type="checkbox" 
                                                   name="ingredients[]" 
                                                   value="<?php echo (int) $item['id']; ?>" 
                                                   id="ing_<?php echo (int) $item['id']; ?>"
                                                   data-group="<?php echo htmlspecialchars($groupName); ?>">
                                            <div class="flex-grow-1 overflow-hidden">
                                                <span class="fw-semibold text-dark d-block text-truncate ingredient-label">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </span>
                                                <span class="badge <?php echo ($item['type'] === 'main') ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?> rounded-pill" style="font-size: 0.7rem;">
                                                    <?php echo ($item['type'] === 'main') ? 'วัตถุดิบหลัก' : 'เครื่องปรุง'; ?>
                                                </span>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Empty Search Result Notice (ซ่อนเริ่มต้น แสดงเมื่อค้นหาไม่พบ) -->
                <div id="noMatchNotice" class="text-center py-5 px-3 bg-white rounded-4 border shadow-sm my-4" style="display: none;">
                    <i class="bi bi-search fs-1 text-muted mb-2 d-block"></i>
                    <h5 class="fw-bold text-dark mb-1">ไม่พบวัตถุดิบที่ตรงกับคำค้นหา</h5>
                    <p class="text-muted small mb-0">ลองเปลี่ยนคำค้นหา หรือกดล้างการค้นหาเพื่อดูวัตถุดิบทั้งหมด</p>
                </div>

            <?php else: ?>
                <div class="text-center py-5 px-3 bg-white rounded-4 border shadow-sm my-4">
                    <i class="bi bi-basket2 fs-1 text-muted mb-2 d-block"></i>
                    <h5 class="fw-bold text-dark mb-1">ยังไม่มีข้อมูลวัตถุดิบในระบบ</h5>
                    <p class="text-muted small mb-0">กรุณานำเข้าข้อมูลเริ่มต้นผ่านไฟล์ schema.sql ใน phpMyAdmin</p>
                </div>
            <?php endif; ?>

            <!-- Sticky Bottom Submission Bar (แถบปุ่มลอยติดขอบล่างหน้าจอ) -->
            <div class="sticky-bottom-bar">
                <div class="container">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-basket2-fill fs-4 text-warning"></i>
                            <div>
                                <span class="d-block small text-muted">วัตถุดิบที่คุณมีในครัว</span>
                                <strong class="fs-6 text-dark">
                                    เลือกแล้ว <span id="bottomSelectedCount" class="text-primary">0</span> จาก <?php echo $totalIngredientsCount; ?> รายการ
                                </strong>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="submit" id="submitSearchBtn" class="btn btn-kitchen-primary px-4 py-3 rounded-pill d-inline-flex align-items-center gap-2 shadow-sm" disabled>
                                <i class="bi bi-search"></i>
                                <span>ค้นหาเมนูจากวัตถุดิบที่มี</span>
                                <span id="submitBadgeCount" class="badge bg-white text-dark rounded-pill ms-1" style="display: none;">0</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </div>
</section>

<!-- JavaScript จัดการ Checkbox, ค้นหาด่วน, เลือกทั้งหมด และนับจำนวน -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.ingredient-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    const bottomSelectedCountSpan = document.getElementById('bottomSelectedCount');
    const submitBtn = document.getElementById('submitSearchBtn');
    const submitBadgeCount = document.getElementById('submitBadgeCount');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const ingredientItems = document.querySelectorAll('.ingredient-item');
    const groupSections = document.querySelectorAll('.ingredient-group-section');
    const noMatchNotice = document.getElementById('noMatchNotice');
    const groupSelectBtns = document.querySelectorAll('.group-select-btn');

    // ฟังก์ชันอัปเดตสถานะและจำนวน Checkbox ที่เลือก
    function updateSelectedCount() {
        let count = 0;
        checkboxes.forEach(cb => {
            const card = cb.closest('.ingredient-card');
            if (cb.checked) {
                count++;
                if (card) card.classList.add('selected');
            } else {
                if (card) card.classList.remove('selected');
            }
        });

        selectedCountSpan.textContent = count;
        bottomSelectedCountSpan.textContent = count;

        if (count > 0) {
            submitBtn.removeAttribute('disabled');
            submitBadgeCount.textContent = count;
            submitBadgeCount.style.display = 'inline-block';
        } else {
            submitBtn.setAttribute('disabled', 'disabled');
            submitBadgeCount.style.display = 'none';
        }
    }

    // Event Listener เมื่อติ๊ก Checkbox แต่ละตัว
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    // ปุ่มเลือกทั้งหมด (Select All)
    selectAllBtn.addEventListener('click', function () {
        checkboxes.forEach(cb => {
            // เลือกเฉพาะตัวที่กำลังแสดงผล (ไม่ถูกกรองออก)
            const item = cb.closest('.ingredient-item');
            if (!item || item.style.display !== 'none') {
                cb.checked = true;
            }
        });
        updateSelectedCount();
    });

    // ปุ่มล้างรายการที่เลือก (Clear All)
    clearAllBtn.addEventListener('click', function () {
        checkboxes.forEach(cb => {
            cb.checked = false;
        });
        updateSelectedCount();
    });

    // ปุ่มเลือกเฉพาะกลุ่มนี้ทั้งหมด
    groupSelectBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const groupName = this.getAttribute('data-group-name');
            const groupCheckboxes = document.querySelectorAll(`.ingredient-checkbox[data-group="${groupName}"]`);
            
            // ตรวจสอบว่าในกลุ่มนี้เลือกครบหรือยัง
            const allCheckedInGroup = Array.from(groupCheckboxes).every(cb => cb.checked);
            groupCheckboxes.forEach(cb => {
                cb.checked = !allCheckedInGroup;
            });
            updateSelectedCount();
        });
    });

    // ค้นหาด่วนแบบเรียลไทม์ (Live Quick Search)
    quickSearchInput.addEventListener('input', function () {
        const query = this.value.trim().toLowerCase();
        let anyVisibleTotal = false;

        if (query.length > 0) {
            clearSearchBtn.style.display = 'inline-block';
        } else {
            clearSearchBtn.style.display = 'none';
        }

        groupSections.forEach(section => {
            let visibleInGroup = 0;
            const items = section.querySelectorAll('.ingredient-item');
            
            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (name.includes(query)) {
                    item.style.display = '';
                    visibleInGroup++;
                    anyVisibleTotal = true;
                } else {
                    item.style.display = 'none';
                }
            });

            // ซ่อนหรือแสดงทั้ง Section ตามจำนวนวัตถุดิบที่ค้นพบ
            if (visibleInGroup > 0) {
                section.style.display = '';
            } else {
                section.style.display = 'none';
            }
        });

        // แสดงแจ้งเตือนเมื่อค้นหาไม่พบรายการใดเลย
        if (!anyVisibleTotal && query.length > 0) {
            noMatchNotice.style.display = 'block';
        } else {
            noMatchNotice.style.display = 'none';
        }
    });

    // ปุ่มล้างข้อความค้นหา
    clearSearchBtn.addEventListener('click', function () {
        quickSearchInput.value = '';
        quickSearchInput.dispatchEvent(new Event('input'));
        quickSearchInput.focus();
    });

    // เรียกฟังก์ชันครั้งแรกตอนโหลดหน้า
    updateSelectedCount();
});
</script>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>

