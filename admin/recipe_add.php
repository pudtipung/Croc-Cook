<?php
/**
 * หน้าเพิ่มสูตรอาหารใหม่สำหรับผู้ดูแลระบบ (Add Recipe)
 * เว็บไซต์: มีอะไรในครัว? (What's in My Kitchen)
 */

// 1. ตรวจสอบสิทธิ์ผู้ดูแลระบบและดึงการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/auth_check.php';

$adminName = $_SESSION['admin_name'] ?? 'ผู้ดูแลระบบ';
$adminUsername = $_SESSION['admin_username'] ?? 'admin';

// 2. ดึงรายชื่อหมวดหมู่อาหารจริงจากฐานข้อมูล MySQL สำหรับ Dropdown
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name, icon FROM categories ORDER BY id ASC");
    $categories = $catStmt->fetchAll();

    // ดึงรายชื่อวัตถุดิบทั้งหมดสำหรับเลือกในสูตรอาหาร
    $ingStmt = $pdo->query("
        SELECT id, name, type, category_group 
        FROM ingredients 
        ORDER BY FIELD(category_group, 'เนื้อสัตว์', 'ไข่และแป้ง', 'ผักและสมุนไพร', 'เครื่องปรุงรส', 'ทั่วไป'), name ASC
    ");
    $allIngredients = $ingStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Load Recipe Add Data Error: " . $e->getMessage());
}

$errors = [];
$formData = [
    'title' => '',
    'category_id' => '',
    'cooking_time' => 15,
    'servings' => 1,
    'description' => '',
    'instructions' => '',
    'image_url' => '',
    'ingredients' => []
];

// 3. จัดการเมื่อมีการ Submit ฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3.1 ตรวจสอบความถูกต้องของ CSRF Token
    if (!admin_verify_csrf()) {
        $errors[] = 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        
        // 3.2 รับค่าและตัดช่องว่าง
        $formData['title'] = trim($_POST['title'] ?? '');
        $formData['category_id'] = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $formData['cooking_time'] = filter_var($_POST['cooking_time'] ?? 15, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 15]]);
        $formData['servings'] = filter_var($_POST['servings'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
        $formData['description'] = trim($_POST['description'] ?? '');
        $formData['instructions'] = trim($_POST['instructions'] ?? '');
        $formData['image_url'] = trim($_POST['image_url'] ?? '');

        // 3.3 ตรวจสอบข้อมูลฟิลด์บังคับ (Validation)
        if ($formData['title'] === '') {
            $errors[] = 'กรุณากรอกชื่อเมนูอาหาร';
        } elseif (mb_strlen($formData['title']) > 200) {
            $errors[] = 'ชื่อเมนูอาหารต้องมีความยาวไม่เกิน 200 ตัวอักษร';
        }

        if (empty($formData['category_id'])) {
            $errors[] = 'กรุณาเลือกหมวดหมู่อาหาร';
        } else {
            // ตรวจสอบว่าหมวดหมู่นี้มีอยู่ในฐานข้อมูลจริง
            $categoryExists = false;
            foreach ($categories as $cat) {
                if ((int)$cat['id'] === (int)$formData['category_id']) {
                    $categoryExists = true;
                    break;
                }
            }
            if (!$categoryExists) {
                $errors[] = 'หมวดหมู่อาหารที่เลือกไม่ถูกต้อง';
            }
        }

        if (empty($formData['instructions'])) {
            $errors[] = 'กรุณากรอกขั้นตอนและวิธีปรุงอาหาร';
        }

        if ($formData['cooking_time'] < 1) {
            $errors[] = 'เวลาในการปรุงอาหารต้องไม่ต่ำกว่า 1 นาที';
        }

        if ($formData['servings'] < 1) {
            $errors[] = 'จำนวนที่เสิร์ฟต้องไม่ต่ำกว่า 1 ที่';
        }

        // 3.4 รับค่าวัตถุดิบที่ต้องใช้ (Recipe Ingredients)
        $submittedIngredients = [];
        $rawIngredients = $_POST['recipe_ingredients'] ?? [];
        if (is_array($rawIngredients)) {
            foreach ($rawIngredients as $row) {
                $ingId = filter_var($row['ingredient_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($ingId) {
                    $submittedIngredients[] = [
                        'ingredient_id' => $ingId,
                        'quantity' => trim($row['quantity'] ?? ''),
                        'unit' => trim($row['unit'] ?? ''),
                        'is_essential' => (isset($row['is_essential']) && (int)$row['is_essential'] === 0) ? 0 : 1
                    ];
                }
            }
        }
        $formData['ingredients'] = $submittedIngredients;

        // 3.5 จัดการรูปภาพ (อัปโหลดไฟล์ หรือ URL)
        $savedImageName = null;
        $imageError = null;
        
        if (empty($errors)) {
            $uploadResult = handle_recipe_image_upload($_FILES['image_file'] ?? [], $formData['image_url'], $imageError);
            if ($imageError !== null) {
                $errors[] = $imageError;
            } else {
                $savedImageName = $uploadResult;
            }
        }

        // 3.6 บันทึกข้อมูลลงฐานข้อมูล MySQL หากไม่มีข้อผิดพลาด (Database Transaction)
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1) เพิ่มข้อมูลลงในตาราง recipes
                $insertSql = "
                    INSERT INTO recipes (category_id, title, description, image, cooking_time, servings, instructions)
                    VALUES (:category_id, :title, :description, :image, :cooking_time, :servings, :instructions)
                ";
                
                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute([
                    ':category_id' => $formData['category_id'],
                    ':title' => $formData['title'],
                    ':description' => !empty($formData['description']) ? $formData['description'] : null,
                    ':image' => !empty($savedImageName) ? $savedImageName : null,
                    ':cooking_time' => $formData['cooking_time'],
                    ':servings' => $formData['servings'],
                    ':instructions' => $formData['instructions']
                ]);

                $newRecipeId = (int)$pdo->lastInsertId();

                // 2) บันทึกความสัมพันธ์วัตถุดิบลงในตาราง recipe_ingredients
                if (!empty($submittedIngredients)) {
                    $riSql = "
                        INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, is_essential)
                        VALUES (:recipe_id, :ingredient_id, :quantity, :unit, :is_essential)
                    ";
                    $riStmt = $pdo->prepare($riSql);
                    $seenIngIds = [];

                    foreach ($submittedIngredients as $ing) {
                        $ingId = (int)$ing['ingredient_id'];
                        // ป้องกันวัตถุดิบซ้ำในสูตรเดียวกัน
                        if (!isset($seenIngIds[$ingId])) {
                            $seenIngIds[$ingId] = true;
                            $riStmt->execute([
                                ':recipe_id' => $newRecipeId,
                                ':ingredient_id' => $ingId,
                                ':quantity' => ($ing['quantity'] !== '') ? $ing['quantity'] : null,
                                ':unit' => ($ing['unit'] !== '') ? $ing['unit'] : null,
                                ':is_essential' => (int)$ing['is_essential']
                            ]);
                        }
                    }
                }

                // ยืนยันการบันทึกทั้งหมด
                $pdo->commit();

                // บันทึก Flash Message แจ้งผลสำเร็จและ redirect ไปหน้ารายการ
                $ingCount = count($submittedIngredients);
                $ingMsg = ($ingCount > 0) ? " พร้อมกำหนดวัตถุดิบ $ingCount รายการ" : "";
                $_SESSION['flash_success'] = "เพิ่มสูตรอาหาร \"" . htmlspecialchars($formData['title']) . "\" (ID: $newRecipeId){$ingMsg} เข้าสู่ระบบเรียบร้อยแล้ว";
                header('Location: recipes.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Insert Recipe Error: " . $e->getMessage());
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
    <title>เพิ่มสูตรอาหารใหม่ | ระบบจัดการหลังบ้าน - มีอะไรในครัว?</title>
    
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
    .image-preview-box {
        width: 100%;
        height: 200px;
        border: 2px dashed var(--border-subtle);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: #fafafa;
    }
    .image-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
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

                    <a href="recipes.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2">
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
                    <li class="breadcrumb-item"><a href="recipes.php" class="text-decoration-none text-muted">จัดการสูตรอาหาร</a></li>
                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">เพิ่มสูตรอาหารใหม่</li>
                </ol>
            </nav>

            <!-- Page Title -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-plus-circle text-primary me-2"></i>เพิ่มสูตรอาหารใหม่
                    </h1>
                    <p class="text-muted small mb-0">กรอกข้อมูลเมนูอาหารเพื่อบันทึกลงในระบบและแสดงบนเว็บไซต์</p>
                </div>
                <div>
                    <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-3 py-2 small">
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

            <!-- Recipe Form -->
            <form method="POST" action="recipe_add.php" enctype="multipart/form-data">
                <?php echo admin_csrf_input(); ?>

                <div class="row g-4">
                    
                    <!-- Left Column: Primary Details (col-lg-8) -->
                    <div class="col-lg-8">
                        <div class="content-box">
                            <h2 class="h6 fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="bi bi-card-text text-primary me-2"></i>ข้อมูลหลักของเมนูอาหาร
                            </h2>

                            <!-- Menu Title -->
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    ชื่อเมนูอาหาร <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control rounded-3" id="title" name="title" 
                                       placeholder="เช่น แกงเขียวหวานไก่, ข้าวผัดกุ้งสด..." 
                                       value="<?php echo htmlspecialchars($formData['title']); ?>" required maxlength="200">
                                <div class="form-text small">ความยาวไม่เกิน 200 ตัวอักษร</div>
                            </div>

                            <!-- Category & Cooking Time & Servings -->
                            <div class="row g-3 mb-3">
                                <!-- Category -->
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label">
                                        หมวดหมู่อาหาร <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select rounded-3" id="category_id" name="category_id" required>
                                        <option value="">-- เลือกหมวดหมู่อาหาร --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo (int) $cat['id']; ?>" <?php echo ((int)$formData['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Cooking Time -->
                                <div class="col-sm-6 col-md-3">
                                    <label for="cooking_time" class="form-label">เวลาปรุง (นาที)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control rounded-start-3" id="cooking_time" name="cooking_time" 
                                               min="1" max="1440" value="<?php echo (int) $formData['cooking_time']; ?>">
                                        <span class="input-group-text bg-light rounded-end-3 small text-muted">นาที</span>
                                    </div>
                                </div>

                                <!-- Servings -->
                                <div class="col-sm-6 col-md-3">
                                    <label for="servings" class="form-label">จำนวนที่เสิร์ฟ</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control rounded-start-3" id="servings" name="servings" 
                                               min="1" max="100" value="<?php echo (int) $formData['servings']; ?>">
                                        <span class="input-group-text bg-light rounded-end-3 small text-muted">ที่/จาน</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Short Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">คำอธิบายเมนูอาหารโดยย่อ</label>
                                <textarea class="form-control rounded-3" id="description" name="description" rows="2" 
                                          placeholder="สรุปจุดเด่น รสชาติ หรือเอกลักษณ์ของเมนูนี้..."><?php echo htmlspecialchars($formData['description']); ?></textarea>
                            </div>

                            <!-- Cooking Instructions -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="instructions" class="form-label mb-0">
                                        ขั้นตอนและวิธีทำอาหาร <span class="text-danger">*</span>
                                    </label>
                                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>เขียน 1 บรรทัดต่อ 1 ขั้นตอน</small>
                                </div>
                                <textarea class="form-control rounded-3" id="instructions" name="instructions" rows="7" 
                                          placeholder="ตัวอย่าง:&#10;1. ตั้งกระทะใส่น้ำมันให้ร้อน เจียวกระเทียมจนหอม&#10;2. ใส่เนื้อหมูสับลงไปผัดจนสุกเกือบ 80%&#10;3. ปรุงรสด้วยน้ำปลา ซอสหอยนางรม และน้ำตาลทราย&#10;4. ใส่ใบกะเพราและพริกแดง ผัดเร็วๆ แล้วปิดเตา"><?php echo htmlspecialchars($formData['instructions']); ?></textarea>
                                <div class="form-text small">
                                    ระบบหน้าเว็บจะแยกข้อความแต่ละบรรทัดเพื่อแสดงเป็นขั้นตอนที่มีเครื่องหมายเช็คถูกให้อัตโนมัติ
                                </div>
                            </div>

                        </div>

                        <!-- Card 2: วัตถุดิบและเครื่องปรุงที่ต้องใช้ (Recipe Ingredients) -->
                        <div class="content-box mt-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom gap-2">
                                <div>
                                    <h2 class="h6 fw-bold text-dark mb-1">
                                        <i class="bi bi-basket2 text-primary me-2"></i>วัตถุดิบและเครื่องปรุงที่ต้องใช้
                                    </h2>
                                    <small class="text-muted">กำหนดวัตถุดิบ ปริมาณ และความจำเป็น เพื่อให้ระบบค้นหาและจับคู่เมนูทำงานได้อย่างสมบูรณ์</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-kitchen-primary rounded-pill px-3" onclick="addIngredientRow()">
                                    <i class="bi bi-plus-lg me-1"></i>เพิ่มวัตถุดิบ
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead class="table-light small">
                                        <tr>
                                            <th style="min-width: 220px;">วัตถุดิบ <span class="text-danger">*</span></th>
                                            <th style="width: 120px;">ปริมาณ</th>
                                            <th style="width: 120px;">หน่วยวัด</th>
                                            <th style="width: 180px;">ประเภทความจำเป็น</th>
                                            <th style="width: 50px;" class="text-center">ลบ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ingredientsList">
                                        <!-- รายการวัตถุดิบจะถูกสร้างขึ้นด้วย JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <span class="small text-muted" id="ingredientCountText">กำหนดแล้ว 0 รายการ</span>
                                <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-primary fw-medium" onclick="addIngredientRow()">
                                    <i class="bi bi-plus-circle me-1"></i>เพิ่มรายการวัตถุดิบอีก
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Image & Actions (col-lg-4) -->
                    <div class="col-lg-4">
                        
                        <!-- Image Upload Box -->
                        <div class="content-box mb-4">
                            <h2 class="h6 fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="bi bi-image text-primary me-2"></i>รูปภาพเมนูอาหาร
                            </h2>

                            <!-- Preview Container -->
                            <div class="image-preview-box mb-3" id="imagePreviewContainer">
                                <div class="text-center text-muted p-3" id="previewPlaceholder">
                                    <i class="bi bi-image fs-1 d-block mb-1 text-secondary"></i>
                                    <span class="small">ยังไม่ได้เลือกรูปภาพ</span>
                                </div>
                                <img src="" alt="ตัวอย่างภาพ" id="imagePreview" style="display: none;">
                            </div>

                            <!-- File Upload Input -->
                            <div class="mb-3">
                                <label for="image_file" class="form-label small fw-bold">อัปโหลดไฟล์รูปภาพจากเครื่อง</label>
                                <input type="file" class="form-control form-control-sm rounded-3" id="image_file" name="image_file" 
                                       accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text small" style="font-size: 0.75rem;">
                                    รองรับ JPG, PNG, WEBP, GIF (ขนาดสูงสุด 2 MB)
                                </div>
                            </div>

                            <div class="text-center text-muted small my-2">-- หรือ --</div>

                            <!-- Direct URL / Filename Input -->
                            <div class="mb-2">
                                <label for="image_url" class="form-label small fw-bold">กรอก URL รูปภาพ หรือชื่อไฟล์</label>
                                <input type="text" class="form-control form-control-sm rounded-3" id="image_url" name="image_url" 
                                       placeholder="https://... หรือ kaprao_moo.jpg" 
                                       value="<?php echo htmlspecialchars($formData['image_url']); ?>">
                                <div class="form-text small" style="font-size: 0.75rem;">
                                    ใส่ลิงก์รูปภาพจากอินเทอร์เน็ต เช่น Unsplash
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions Box -->
                        <div class="content-box">
                            <h2 class="h6 fw-bold text-dark mb-3 pb-2 border-bottom">
                                <i class="bi bi-check2-circle text-primary me-2"></i>ดำเนินการ
                            </h2>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-kitchen-primary rounded-pill py-2 shadow-sm fw-semibold">
                                    <i class="bi bi-save me-1"></i> บันทึกสูตรอาหาร
                                </button>
                                <a href="recipes.php" class="btn btn-outline-secondary rounded-pill py-2">
                                    ยกเลิกและย้อนกลับ
                                </a>
                            </div>

                            <hr class="my-3">
                            <small class="text-muted d-block text-center" style="font-size: 0.75rem;">
                                <i class="bi bi-shield-check me-1 text-success"></i> ข้อมูลจะถูกบันทึกลงในตาราง <code>recipes</code> จริง
                            </small>
                        </div>

                    </div>

                </div>
            </form>

        </div>
    </main>

    <!-- Datalist for standard cooking units -->
    <datalist id="unitsList">
        <option value="กรัม">
        <option value="กิโลกรัม">
        <option value="ช้อนโต๊ะ">
        <option value="ช้อนชา">
        <option value="ถ้วยตวง">
        <option value="มิลลิลิตร">
        <option value="ฟอง">
        <option value="ชิ้น">
        <option value="ชิ้นพอดีคำ">
        <option value="หัว">
        <option value="ต้น">
        <option value="ฝัก">
        <option value="แผ่น">
        <option value="เม็ด">
        <option value="กลีบ">
        <option value="หยิบมือ">
        <option value="ตามชอบ">
    </datalist>

    <!-- Admin Footer -->
    <footer class="py-3 bg-white border-top text-center text-muted small mt-auto">
        <div class="container">
            <span>&copy; <?php echo date('Y'); ?> <strong>มีอะไรในครัว? (What's in My Kitchen)</strong> — ระบบจัดการข้อมูลหลังบ้าน</span>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Dynamic Recipe Ingredients Management & Image Live Preview Script -->
    <script>
    // ==========================================
    // Dynamic Recipe Ingredients Management
    // ==========================================
    <?php
    $groupedIngredients = [];
    foreach ($allIngredients as $ing) {
        $grp = !empty($ing['category_group']) ? $ing['category_group'] : 'ทั่วไป';
        $groupedIngredients[$grp][] = $ing;
    }
    $optionsHtml = '<option value="">-- เลือกวัตถุดิบ --</option>';
    foreach ($groupedIngredients as $grpName => $items) {
        $optionsHtml .= '<optgroup label="' . htmlspecialchars($grpName, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($items as $item) {
            $optionsHtml .= '<option value="' . (int)$item['id'] . '">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $optionsHtml .= '</optgroup>';
    }
    ?>
    const ingredientOptionsHtml = <?php echo json_encode($optionsHtml); ?>;
    const initialIngredients = <?php echo json_encode($formData['ingredients'] ?? []); ?>;

    let ingredientRowIndex = 0;

    function addIngredientRow(data = null) {
        const tbody = document.getElementById('ingredientsList');
        const tr = document.createElement('tr');
        const index = ingredientRowIndex++;
        tr.id = `ing-row-${index}`;

        const ingId = data ? data.ingredient_id : '';
        const qty = data ? (data.quantity || '') : '';
        const unit = data ? (data.unit || '') : '';
        const isEss = (data && (data.is_essential === 0 || data.is_essential === '0')) ? 0 : 1;

        tr.innerHTML = `
            <td>
                <select name="recipe_ingredients[${index}][ingredient_id]" class="form-select form-select-sm rounded-3 ingredient-select" required>
                    ${ingredientOptionsHtml}
                </select>
            </td>
            <td>
                <input type="text" name="recipe_ingredients[${index}][quantity]" class="form-control form-control-sm rounded-3" 
                       placeholder="เช่น 200, 1/2" value="${escapeHtml(qty)}">
            </td>
            <td>
                <input type="text" name="recipe_ingredients[${index}][unit]" list="unitsList" class="form-control form-control-sm rounded-3" 
                       placeholder="เช่น กรัม" value="${escapeHtml(unit)}">
            </td>
            <td>
                <select name="recipe_ingredients[${index}][is_essential]" class="form-select form-select-sm rounded-3">
                    <option value="1" ${isEss === 1 ? 'selected' : ''}>จำเป็น (ขาดไม่ได้)</option>
                    <option value="0" ${isEss === 0 ? 'selected' : ''}>ทางเลือก / เสริม</option>
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle" 
                        title="ลบรายการนี้" onclick="removeIngredientRow(${index})">
                    <i class="bi bi-trash3"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        // Pre-select ingredient_id if provided
        if (ingId) {
            const selectEl = tr.querySelector('.ingredient-select');
            if (selectEl) {
                selectEl.value = ingId;
            }
        }

        updateIngredientCount();
    }

    function removeIngredientRow(index) {
        const row = document.getElementById(`ing-row-${index}`);
        if (row) {
            row.remove();
            updateIngredientCount();
        }
    }

    function updateIngredientCount() {
        const count = document.querySelectorAll('#ingredientsList tr').length;
        const countText = document.getElementById('ingredientCountText');
        if (countText) {
            countText.textContent = `กำหนดแล้ว ${count} รายการ`;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize rows
    document.addEventListener('DOMContentLoaded', function () {
        if (initialIngredients && initialIngredients.length > 0) {
            initialIngredients.forEach(item => addIngredientRow(item));
        } else {
            // Default 3 rows for convenience
            addIngredientRow();
            addIngredientRow();
            addIngredientRow();
        }
    });

    // ==========================================
    // Image Live Preview Script
    // ==========================================
    const imageFileInput = document.getElementById('image_file');
    const imageUrlInput = document.getElementById('image_url');
    const imagePreview = document.getElementById('imagePreview');
    const previewPlaceholder = document.getElementById('previewPlaceholder');

    function updatePreview(src) {
        if (src) {
            imagePreview.src = src;
            imagePreview.style.display = 'block';
            previewPlaceholder.style.display = 'none';
        } else {
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            previewPlaceholder.style.display = 'block';
        }
    }

    imageFileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                updatePreview(e.target.result);
            };
            reader.readAsDataURL(file);
        } else {
            if (imageUrlInput.value.trim() !== '') {
                updatePreview(imageUrlInput.value.trim());
            } else {
                updatePreview(null);
            }
        }
    });

    imageUrlInput.addEventListener('input', function () {
        if (!imageFileInput.files || imageFileInput.files.length === 0) {
            const val = this.value.trim();
            if (val.startsWith('http://') || val.startsWith('https://')) {
                updatePreview(val);
            } else if (val === '') {
                updatePreview(null);
            }
        }
    });
    </script>

</body>
</html>

