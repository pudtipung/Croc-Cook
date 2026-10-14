<?php
/**
 * Header Template - มีอะไรในครัว? (What's in My Kitchen)
 */
if (!isset($currentPage)) {
    $currentPage = 'home';
}
if (!isset($pageTitle)) {
    $pageTitle = 'มีอะไรในครัว? - ค้นหาสูตรอาหารจากวัตถุดิบที่คุณมี';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Google Fonts (Prompt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS (แยกไฟล์ตามข้อกำหนด) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Header / Navbar -->
    <header>
        <nav class="navbar navbar-expand-lg kitchen-navbar sticky-top">
            <div class="container">
                <!-- Brand Logo & Name -->
                <a class="navbar-brand" href="index.php">
                    <div class="main-food-icon">
                         <img src="im/logo.png" alt="Logo" style="max-width: 80px; height: auto; border-radius: 50%; object-fit: cover;">
                    </div>
                    <span class="brand-text">Croc Cook</span>
                </a>

                <!-- Mobile Hamburger Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kitchenNavbar" aria-controls="kitchenNavbar" aria-expanded="false" aria-label="สลับแถบเมนู">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Navigation Links -->
                <div class="collapse navbar-collapse" id="kitchenNavbar">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-1">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage === 'home') ? 'active' : ''; ?>" href="index.php">
                                <i class="bi bi-house-door me-1"></i>หน้าแรก
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage === 'search') ? 'active' : ''; ?>" href="ingredients.php">
                                <i class="bi bi-search me-1"></i>ค้นหาจากวัตถุดิบ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage === 'recipes') ? 'active' : ''; ?>" href="recipes.php">
                                <i class="bi bi-journal-bookmark me-1"></i>สูตรอาหาร
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage === 'categories') ? 'active' : ''; ?>" href="categories.php">
                                <i class="bi bi-grid me-1"></i>หมวดหมู่
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage === 'about') ? 'active' : ''; ?>" href="about.php">
                                <i class="bi bi-info-circle me-1"></i>เกี่ยวกับเรา
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content Container -->
    <main>

