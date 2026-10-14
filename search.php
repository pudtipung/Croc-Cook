<?php
/**
 * หน้าค้นหาจากวัตถุดิบ (Redirect / Query Handler) - มีอะไรในครัว?
 */
require_once __DIR__ . '/includes/db.php';

$query = trim($_GET['ingredient'] ?? '');

if (!empty($query)) {
    try {
        // ค้นหาวัตถุดิบที่มีชื่อตรงกับคำค้นหา
        $stmt = $pdo->prepare("SELECT id FROM ingredients WHERE name LIKE :term LIMIT 10");
        $stmt->execute([':term' => '%' . $query . '%']);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            // ส่งต่อไปยังหน้าผลลัพธ์ find_recipes.php พร้อม ID วัตถุดิบ
            $params = http_build_query(['ingredients' => $ids]);
            header("Location: find_recipes.php?" . $params);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
    }
}

// หากไม่มีคำค้นหาหรือไม่พบ ให้ส่งต่อไปยังหน้าเลือกวัตถุดิบหลัก ingredients.php
header("Location: ingredients.php");
exit;
