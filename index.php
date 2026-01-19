<?php
// =============================================================
// JAVPORNSUB.NET - INDEX (FIXED SORTING: LATEST BY ID)
// =============================================================

require_once 'include/config.php'; 
require_once 'include/database.php';

// --- 1. INISIALISASI KONEKSI DATABASE ---
$conn = connectDB();

// --- CONFIGURATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// --- 2. SETTING ID KATEGORI ---
$cat_id_eng  = 2; // Jav English Subtitle
$cat_id_indo = 3; // Jav Sub Indo
$cat_id_unc  = 1; // Uncensored

// --- 3. DETEKSI SLUG KATEGORI ---
$slug_eng  = 'subtitle-english'; 
$slug_indo = 'sub-indo';
$slug_unc  = 'uncensored';

if ($conn) {
    $q2 = mysqli_query($conn, "SELECT slug FROM categories WHERE id = $cat_id_eng LIMIT 1");
    if ($q2 && $r2 = mysqli_fetch_assoc($q2)) { $slug_eng = $r2['slug']; }

    $q3 = mysqli_query($conn, "SELECT slug FROM categories WHERE id = $cat_id_indo LIMIT 1");
    if ($q3 && $r3 = mysqli_fetch_assoc($q3)) { $slug_indo = $r3['slug']; }

    $q1 = mysqli_query($conn, "SELECT slug FROM categories WHERE id = $cat_id_unc LIMIT 1");
    if ($q1 && $r1 = mysqli_fetch_assoc($q1)) { $slug_unc = $r1['slug']; }
}

// --- 4. FUNGSI KHUSUS FETCH BY CATEGORY ID ---
function getVideosByCatID($conn, $cat_id, $limit = 8) {
    $result = [];
    $cat_id = (int)$cat_id;
    $limit  = (int)$limit;
    
    if (!$conn) return [];

    // PERBAIKAN DI SINI: Menggunakan ORDER BY v.id DESC
    // Ini menjamin video yang paling terakhir dimasukkan (ID terbesar) tampil paling awal
    $sql = "SELECT v.*, c.name as category_name 
            FROM videos v 
            LEFT JOIN categories c ON v.category_id = c.id 
            WHERE v.category_id = $cat_id 
            ORDER BY v.id DESC 
            LIMIT $limit";
            
    $query = mysqli_query($conn, $sql);
    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $result[] = $row;
        }
    }
    return $result;
}

// --- 5. AMBIL DATA VIDEO ---
$videos_eng  = getVideosByCatID($conn, $cat_id_eng, 8);  // ID 2
$videos_indo = getVideosByCatID($conn, $cat_id_indo, 8); // ID 3
$videos_unc  = getVideosByCatID($conn, $cat_id_unc, 8);  // ID 1

// --- 6. TUTUP KONEKSI ---
if ($conn) {
    $conn->close();
}

// --- HELPER FUNCTION: RENDER VIDEO GRID (OBSIDIAN PORTRAIT STYLE) ---
function renderVideoGrid($video_data) {
    global $base_clean; 
    
    if (empty($video_data)) {
        return '<div style="padding:40px; text-align:center; color:#555; width:100%; grid-column:1/-1; border:1px dashed #222; border-radius:8px;">No videos found matching this section.</div>';
    }
    
    $html = '<div class="grid">';
    
    foreach ($video_data as $video) {
        $full_title = stripslashes($video['original_title']);
        $display_code = function_exists('getThumbnailTitle') ? getThumbnailTitle($full_title) : $full_title;
        $cat_raw = isset($video['category_name']) ? $video['category_name'] : '';
        
        // Tampilkan Nama Kategori Full
        $display_cat = !empty($cat_raw) ? $cat_raw : 'JAV';

        $watch_link = rtrim(BASE_URL, '/') . '/' . $video['slug'];
        $duration_text = formatDurationToMinutes($video['duration']); 
        $image_url = !empty($video['image_url']) ? $video['image_url'] : 'assets/img/no-thumb.jpg';
        $optimized_alt = "Watch " . $full_title;
        $quality = !empty($video['quality']) ? $video['quality'] : 'HD';

        $html .= '
        <a href="'.$watch_link.'" class="video-card" title="'.$optimized_alt.'">
            <div class="thumbnail-container">
                <img src="'.$image_url.'" alt="'.$optimized_alt.'" loading="lazy" width="240" height="360">
                
                <span class="category-badge-top">'.$display_cat.'</span>
                
                <div class="portrait-info-overlay">
                    <div class="portrait-meta">
                        <span class="meta-quality">'.$quality.'</span>
                        '.($duration_text ? '<span class="meta-duration">'.$duration_text.'</span>' : '').'
                        <div class="portrait-title">'.$display_code.'</div>
                    </div>
                </div>
            </div>
        </a>';
    }
    $html .= '</div>';
    return $html;
}

// --- META SEO VARS ---
$full_page_title = "JAVPORNSUB"; 
$site_desc  = "Watch JAV English Subtitle and JAV Sub Indo Uncensored in Full HD. JAVPORNSUB is the best site for streaming JAV Engsub, JAV Subbed, and Asian Porn with Subtitles.";
$site_keywords = "jav english subtitle, jav eng sub, jav subbed, jav sub indo, nonton jav, streaming jav, jav uncensored, asian porn sub, jav hd, download jav";
$base_clean = rtrim(BASE_URL, '/'); 
$canonical_url = $base_clean; 

// --- LOAD HEADER ---
require_once 'templates/header.php';
?>

<main class="container">
    <h1 class="sr-only"><?= $full_page_title; ?></h1>

    <div class="section-header">
        <span class="section-title" style="border-left-color:#ffd700;">JAV English Subtitle</span>
        <a href="videos?category=<?= $slug_eng ?>" style="font-size:12px; color:#aaa; font-weight:600; display:flex; align-items:center;">
            View All <i class="fas fa-chevron-right" style="font-size:10px; margin-left:5px;"></i>
        </a>
    </div>
    <?= renderVideoGrid($videos_eng); ?>

    <div class="section-header" style="margin-top:40px;">
        <span class="section-title" style="border-left-color:#d91881;">JAV Sub Indo</span>
        <a href="videos?category=<?= $slug_indo ?>" style="font-size:12px; color:#aaa; font-weight:600; display:flex; align-items:center;">
            View All <i class="fas fa-chevron-right" style="font-size:10px; margin-left:5px;"></i>
        </a>
    </div>
    <?= renderVideoGrid($videos_indo); ?>

    <div class="section-header" style="margin-top:40px;">
        <span class="section-title" style="border-left-color:#fff;">JAV Uncensored</span>
        <a href="videos?category=<?= $slug_unc ?>" style="font-size:12px; color:#aaa; font-weight:600; display:flex; align-items:center;">
            View All <i class="fas fa-chevron-right" style="font-size:10px; margin-left:5px;"></i>
        </a>
    </div>
    <?= renderVideoGrid($videos_unc); ?>

    <div class="sr-only">
        <h2><?= $full_page_title; ?></h2>
        <p>Welcome to JAVPORNSUB, source for JAV English Subtitle and JAV Sub Indo.</p>
    </div>
</main>

<?php
// --- LOAD FOOTER ---
require_once 'templates/footer.php';
?>
