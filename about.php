<?php
/**
 * หน้าเกี่ยวกับเรา - มีอะไรในครัว? (What's in My Kitchen)
 * แนะนำที่มา แนวคิด วัตถุประสงค์ วิธีใช้งาน และประโยชน์ของเว็บไซต์
 */
$currentPage = 'about';
$pageTitle = 'เกี่ยวกับเรา | มีอะไรในครัว? (What\'s in My Kitchen)';

// โหลด Header ของเว็บไซต์
require_once __DIR__ . '/includes/header.php';
?>

<!-- Custom Styling สำหรับหน้าเกี่ยวกับเรา -->
<style>
.about-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background-color: var(--primary-light);
    color: var(--primary-color);
    border: 1px solid rgba(224, 93, 38, 0.2);
    padding: 0.4rem 1.15rem;
    border-radius: 50rem;
    font-size: 0.9rem;
    font-weight: 500;
}
.about-card {
    background-color: #ffffff;
    border: 1.5px solid var(--border-subtle);
    border-radius: 20px;
    padding: 2.25rem 1.75rem;
    height: 100%;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}
.about-card:hover {
    transform: translateY(-5px);
    border-color: rgba(224, 93, 38, 0.4);
    box-shadow: 0 10px 25px rgba(224, 93, 38, 0.09);
}
.about-icon-box {
    width: 62px;
    height: 62px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.85rem;
    margin-bottom: 1.25rem;
}
.benefit-card {
    background-color: #ffffff;
    border: 1.5px solid var(--border-subtle);
    border-radius: 18px;
    padding: 1.75rem 1.5rem;
    height: 100%;
    transition: all 0.25s ease;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.02);
}
.benefit-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(224, 93, 38, 0.08);
}
.benefit-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}
.about-cta-box {
    background: linear-gradient(135deg, #fff4ee 0%, #ffe9dc 100%);
    border: 1.5px solid rgba(224, 93, 38, 0.25);
    border-radius: 24px;
    padding: 3.5rem 2rem;
    box-shadow: 0 10px 30px rgba(224, 93, 38, 0.08);
}
.feature-highlight-box {
    background: linear-gradient(135deg, #ffffff 0%, #fff8f5 100%);
    border: 1.5px solid var(--border-subtle);
    border-radius: 20px;
    padding: 2.25rem;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
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
                    เกี่ยวกับเรา
                </li>
            </ol>
        </nav>

        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="about-hero-badge mb-2">
                    <i class="bi bi-egg-fried"></i> มีอะไรในครัว? (What's in My Kitchen)
                </div>
                <h1 class="h2 fw-bold text-dark mb-1">
                    เกี่ยวกับเรา <span class="title-highlight">About Us</span>
                </h1>
                <p class="text-muted mb-0">
                    ทำความรู้จักกับแนวคิดและเป้าหมายของเว็บไซต์ ผู้ช่วยค้นหาสูตรอาหารจากสิ่งที่คุณมีในบ้าน
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="ingredients.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2 shadow-xs">
                    <i class="bi bi-check2-square me-1"></i> เริ่มค้นหาเมนูเลย
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Section 1: ที่มาและแนวคิดของเว็บไซต์ (Origin & Concept) -->
<section class="py-5" style="background-color: #ffffff; border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        <div class="row align-items-center g-5">
            
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background-color: var(--primary-light); color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                    <i class="bi bi-lightbulb"></i> ที่มาและแนวคิด
                </div>
                <h2 class="h3 fw-bold text-dark mb-3" style="line-height: 1.4;">
                    เปลี่ยนคำถาม “วันนี้กินอะไรดี?” ให้กลายเป็นมื้ออร่อยจากสิ่งที่มีอยู่ในครัว
                </h2>
                <p class="text-muted mb-3" style="line-height: 1.8; font-size: 1.05rem;">
                    หลายครั้งที่เราเปิดตู้เย็นแล้วพบว่ามีวัตถุดิบค้างอยู่หลายรายการ ไม่ว่าจะเป็นเนื้อสัตว์ที่เหลือจากการทำอาหารมื้อก่อน ผักในช่องแช่ หรือไข่ไก่ แต่กลับนึกไม่ออกว่าจะนำมาปรุงเป็นเมนูอะไรดี สุดท้ายจึงจบลงด้วยการสั่งอาหารจากนอกบ้าน หรือปล่อยให้วัตถุดิบเหล่านั้นหมดอายุและต้องทิ้งไปอย่างน่าเสียดาย
                </p>
                <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.05rem;">
                    เว็บไซต์ <strong>“มีอะไรในครัว? (What’s in My Kitchen)”</strong> จึงถูกพัฒนาขึ้นมาเพื่อเป็นผู้ช่วยคิดเมนูอาหารประจำบ้าน เพียงแค่คุณเลือกติ๊กวัตถุดิบที่มีอยู่ในตู้เย็น ระบบจะช่วยประมวลผลจับคู่กับสูตรอาหารไทยรสเด็ด เพื่อบอกคุณได้ทันทีว่า <em>“คุณสามารถทำเมนูอะไรทานได้บ้างในมื้อนี้”</em> โดยไม่ต้องออกไปซื้อของเพิ่มให้ยุ่งยาก
                </p>

                <div class="d-flex flex-wrap gap-3 pt-2">
                    <a href="ingredients.php" class="btn btn-kitchen-primary rounded-pill px-4 py-2">
                        <i class="bi bi-basket2 me-1"></i> เลือกวัตถุดิบเลย
                    </a>
                    <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                        <i class="bi bi-journal-bookmark me-1"></i> ดูสูตรอาหารทั้งหมด
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="feature-highlight-box">
                    <div class="text-center mb-4">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #fff2ea 0%, #ffe8db 100%); color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; box-shadow: 0 6px 18px rgba(224, 93, 38, 0.15);" class="mb-3">
                            <i class="bi bi-basket2-fill"></i>
                        </div>
                        <h3 class="h5 fw-bold text-dark mb-1">หัวใจสำคัญของเว็บไซต์</h3>
                        <span class="text-muted small">Smart Recipe Matching from Your Pantry</span>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background-color: #faf8f5; border: 1px solid var(--border-subtle);">
                            <i class="bi bi-check-circle-fill text-success fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong class="d-block text-dark small fw-bold">ใช้วัตถุดิบจริงที่คุณมี</strong>
                                <span class="text-muted small">เลือกจากของสดและเครื่องปรุงที่มีในบ้านได้ตามสะดวก</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background-color: #faf8f5; border: 1px solid var(--border-subtle);">
                            <i class="bi bi-pie-chart-fill text-warning fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong class="d-block text-dark small fw-bold">คำนวณความพร้อมของเมนู</strong>
                                <span class="text-muted small">แยกเมนูที่พร้อมปรุงทันที กับเมนูที่ยังขาดของบางอย่างชัดเจน</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background-color: #faf8f5; border: 1px solid var(--border-subtle);">
                            <i class="bi bi-book-half text-primary fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong class="d-block text-dark small fw-bold">วิธีทำละเอียด ปรุงตามได้ทันที</strong>
                                <span class="text-muted small">บอกปริมาณ สัดส่วนเครื่องปรุง และขั้นตอนแบบเข้าใจง่าย</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Section 2: วัตถุประสงค์ของเว็บไซต์ (Objectives) -->
<section class="py-5" style="background-color: var(--bg-cream); border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        
        <div class="text-center mb-5">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background-color: var(--primary-light); color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                <i class="bi bi-bullseye"></i> เป้าหมายของเรา
            </div>
            <h2 class="h3 fw-bold text-dark mb-2">วัตถุประสงค์ของเว็บไซต์</h2>
            <p class="text-muted mb-0" style="max-width: 620px; margin: 0 auto;">
                เราตั้งใจสร้างเครื่องมือที่ใช้งานง่ายและเกิดประโยชน์จริงในชีวิตประจำวันของผู้ใช้งานทุกคน
            </p>
        </div>

        <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-4">
            
            <!-- Obj 1 -->
            <div class="col">
                <div class="about-card text-center">
                    <div class="about-icon-box mx-auto" style="background-color: #fff2ea; color: var(--primary-color);">
                        <i class="bi bi-chat-heart"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">ตอบคำถามเรื่องเมนูอาหาร</h3>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        ช่วยให้คุณไม่ต้องเสียเวลาคิดว่าจะทำอะไรทานในแต่ละวัน เพียงติ๊กเลือกของที่มีในครัว ระบบจะช่วยแนะนำเมนูที่เหมาะสมให้ทันที
                    </p>
                </div>
            </div>

            <!-- Obj 2 -->
            <div class="col">
                <div class="about-card text-center">
                    <div class="about-icon-box mx-auto" style="background-color: #edf7f1; color: var(--sage-green);">
                        <i class="bi bi-recycle"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">ลดขยะอาหาร (Food Waste)</h3>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        ส่งเสริมการใช้วัตถุดิบค้างตู้เย็นให้เกิดประโยชน์สูงสุด นำของที่เหลือมาหมุนเวียนปรุงเป็นอาหารจานใหม่ก่อนที่จะเน่าเสีย
                    </p>
                </div>
            </div>

            <!-- Obj 3 -->
            <div class="col">
                <div class="about-card text-center">
                    <div class="about-icon-box mx-auto" style="background-color: #fef8e7; color: #d89614;">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">ประหยัดค่าใช้จ่าย</h3>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        ลดการซื้อวัตถุดิบซ้ำซ้อนโดยไม่จำเป็น และช่วยประหยัดเงินจากการสั่งอาหารนอกบ้านด้วยการทำอาหารรับประทานเอง
                    </p>
                </div>
            </div>

            <!-- Obj 4 -->
            <div class="col">
                <div class="about-card text-center">
                    <div class="about-icon-box mx-auto" style="background-color: #f0f4ff; color: #3b82f6;">
                        <i class="bi bi-egg"></i>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">ส่งเสริมการทำอาหารทานเอง</h3>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        รวบรวมสูตรอาหารไทยรสชาติดี ทำง่าย พร้อมวิธีทำและสัดส่วนที่ชัดเจน ให้ทุกคนสามารถเข้าครัวทำอาหารได้อย่างมั่นใจ
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- Section 3: วิธีใช้งาน 3 ขั้นตอนง่ายๆ (How It Works) -->
<section class="py-5" style="background-color: #ffffff; border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        
        <div class="text-center mb-5">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background-color: var(--primary-light); color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                <i class="bi bi-compass"></i> วิธีใช้งาน
            </div>
            <h2 class="h3 fw-bold text-dark mb-2">วิธีใช้งานง่าย ๆ เพียง 3 ขั้นตอน</h2>
            <p class="text-muted mb-0" style="max-width: 600px; margin: 0 auto;">
                เริ่มต้นเปลี่ยนของในตู้เย็นให้เป็นมื้อพิเศษได้สะดวกรวดเร็วในไม่กี่คลิก
            </p>
        </div>

        <div class="row g-4 justify-content-center">
            
            <!-- Step 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 01</span>
                    <div class="step-icon-box step-icon-1 mx-auto">
                        <i class="bi bi-check2-square"></i>
                    </div>
                    <h3 class="step-card-title">1. ติ๊กเลือกวัตถุดิบที่มี</h3>
                    <p class="step-card-desc">
                        สำรวจของในตู้เย็น แล้วเข้าไปที่หน้า <strong>"เลือกวัตถุดิบ"</strong> ติ๊กเลือกเนื้อสัตว์ ผัก ไข่ หรือเครื่องปรุงรสที่คุณมีอยู่ในครัว
                    </p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 02</span>
                    <div class="step-icon-box step-icon-2 mx-auto">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3 class="step-card-title">2. ค้นหาเมนูอาหาร</h3>
                    <p class="step-card-desc">
                        ระบบจะประมวลผลจับคู่ พร้อมแยกเป็น <strong>“เมนูพร้อมปรุงทันที”</strong> และ <strong>“เมนูที่ยังขาดบางอย่าง”</strong> พร้อมบอกวัตถุดิบที่ขาดอย่างชัดเจน
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="col-lg-4 col-md-6">
                <div class="step-card text-center">
                    <span class="step-number-tag">STEP 03</span>
                    <div class="step-icon-box step-icon-3 mx-auto">
                        <i class="bi bi-fire"></i>
                    </div>
                    <h3 class="step-card-title">3. ดูวิธีทำแล้วลงมือปรุง</h3>
                    <p class="step-card-desc">
                        กดดูสูตรอาหารเพื่ออ่านขั้นตอนการปรุง สัดส่วนเครื่องปรุง และเคล็ดลับการทำอาหารทีละขั้นตอน แล้วรังสรรค์มื้ออร่อยพร้อมเสิร์ฟ
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- Section 4: ประโยชน์ที่ผู้ใช้จะได้รับ (Key Benefits) -->
<section class="py-5" style="background-color: var(--bg-cream); border-bottom: 1px solid var(--border-subtle);">
    <div class="container">
        
        <div class="text-center mb-5">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background-color: var(--primary-light); color: var(--primary-color); font-size: 0.875rem; font-weight: 600;">
                <i class="bi bi-stars"></i> คุณค่าที่มอบให้
            </div>
            <h2 class="h3 fw-bold text-dark mb-2">ประโยชน์ที่คุณจะได้รับจากเว็บไซต์</h2>
            <p class="text-muted mb-0" style="max-width: 600px; margin: 0 auto;">
                เพราะการทำอาหารที่บ้านไม่ใช่แค่เรื่องอิ่มท้อง แต่ยังช่วยให้ชีวิตประจำวันง่ายและคุ้มค่ายิ่งขึ้น
            </p>
        </div>

        <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-4">
            
            <!-- Benefit 1 -->
            <div class="col">
                <div class="benefit-card">
                    <div class="benefit-icon-box" style="background-color: #fff2ea; color: var(--primary-color);">
                        <i class="bi bi-cart-x"></i>
                    </div>
                    <h4 class="h6 fw-bold text-dark mb-2">ลดการซื้อของเกินจำเป็น</h4>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        หมดปัญหาการซื้อวัตถุดิบซ้ำกับที่มีอยู่แล้วในบ้าน ทำให้คุณใช้วัตถุดิบที่มีอยู่อย่างคุ้มค่าและครบถ้วน
                    </p>
                </div>
            </div>

            <!-- Benefit 2 -->
            <div class="col">
                <div class="benefit-card">
                    <div class="benefit-icon-box" style="background-color: #edf7f1; color: var(--sage-green);">
                        <i class="bi bi-trash3"></i>
                    </div>
                    <h4 class="h6 fw-bold text-dark mb-2">ช่วยลดขยะอาหาร</h4>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        นำของค้างตู้เย็นมาปรุงก่อนจะเน่าเสีย ช่วยลดปริมาณขยะอาหาร (Food Waste) และเป็นมิตรต่อสิ่งแวดล้อม
                    </p>
                </div>
            </div>

            <!-- Benefit 3 -->
            <div class="col">
                <div class="benefit-card">
                    <div class="benefit-icon-box" style="background-color: #fef8e7; color: #d89614;">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <h4 class="h6 fw-bold text-dark mb-2">ประหยัดเงินในกระเป๋า</h4>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        ประหยัดค่าอาหารในแต่ละเดือน จากการลดการสั่งเดลิเวอรี่และใช้ของที่ซื้อไว้แล้วให้หมดก่อนซื้อใหม่
                    </p>
                </div>
            </div>

            <!-- Benefit 4 -->
            <div class="col">
                <div class="benefit-card">
                    <div class="benefit-icon-box" style="background-color: #f0f4ff; color: #3b82f6;">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <h4 class="h6 fw-bold text-dark mb-2">อาหารสะอาดถูกหลักอนามัย</h4>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        การปรุงอาหารทานเองช่วยให้คุณควบคุมความสะอาด สดใหม่ ปริมาณโซเดียม และรสชาติได้ตามความต้องการของร่างกาย
                    </p>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- Section 5: Call to Action (พร้อมทำอาหารหรือยัง?) -->
<section class="py-5" style="background-color: #ffffff;">
    <div class="container">
        <div class="about-cta-box text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div style="width: 70px; height: 70px; border-radius: 50%; background: #ffffff; color: var(--primary-color); display: inline-flex; align-items: center; justify-content: center; font-size: 2.2rem; box-shadow: 0 4px 15px rgba(224, 93, 38, 0.15);" class="mb-3">
                        <i class="bi bi-fire"></i>
                    </div>
                    <h2 class="h3 fw-bold text-dark mb-2">
                        พร้อมจะเปลี่ยนของในตู้เย็น ให้เป็นมื้ออร่อยแล้วหรือยัง?
                    </h2>
                    <p class="text-muted mb-4" style="line-height: 1.7;">
                        ไม่ต้องรอให้ถึงมื้อถัดไป ลองเปิดตู้เย็นของคุณตอนนี้ ติ๊กเลือกวัตถุดิบที่มี แล้วมาค้นหาเมนูที่คุณสามารถลงมือทำได้ทันทีกันเลย!
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="ingredients.php" class="btn btn-kitchen-primary rounded-pill px-4 py-3 shadow-sm">
                            <i class="bi bi-check2-square me-1"></i> เลือกวัตถุดิบเลย
                        </a>
                        <a href="recipes.php" class="btn btn-outline-secondary rounded-pill px-4 py-3 bg-white shadow-xs">
                            <i class="bi bi-journal-bookmark me-1"></i> ดูสูตรอาหารทั้งหมด
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// โหลด Footer ของเว็บไซต์
require_once __DIR__ . '/includes/footer.php';
?>
