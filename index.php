<?php
// =============================================================
// JAVPORNSUB.NET - FINAL CLEAN (NO NEW RELEASE & NO PAGINATION)
// =============================================================

require_once 'include/config.php'; 
require_once 'include/database.php';

// --- CONFIGURATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// --- 1. DETEKSI SLUG KATEGORI (UNTUK LINK VIEW ALL) ---
$db_cats = [];
$slug_eng  = 'subtitle-english'; // Default fallback
$slug_indo = 'sub-indo';
$slug_unc  = 'uncensored';

if (isset($conn)) {
    $sql_cat = "SELECT * FROM categories ORDER BY id ASC"; 
    $res_cat = @mysqli_query($conn, $sql_cat); 
    if ($res_cat) {
        while ($row = mysqli_fetch_assoc($res_cat)) {
            $db_cats[] = $row;
            $s = strtolower($row['slug']);
            
            // Update slug jika ditemukan yang cocok di database
            if (strpos($s, 'english') !== false) { $slug_eng = $row['slug']; }
            elseif (strpos($s, 'indo') !== false) { $slug_indo = $row['slug']; }
            elseif (strpos($s, 'uncensored') !== false) { $slug_unc = $row['slug']; }
        }
    }
}

// --- 2. AMBIL DATA SECTION (MENGGUNAKAN KEYWORD AGAR PASTI TAMPIL) ---
// Kita ambil 8 video terbaru untuk masing-masing section
$videos_eng  = getVideosFromDB(8, 0, 'English', null, null, 'cloned_at', 'DESC');
$videos_indo = getVideosFromDB(8, 0, 'Indo', null, null, 'cloned_at', 'DESC');
$videos_unc  = getVideosFromDB(8, 0, 'Uncensored', null, null, 'cloned_at', 'DESC');


// --- HELPER FUNCTION: RENDER VIDEO GRID ---
function renderVideoGrid($video_data) {
    global $base_clean; 
    
    if (empty($video_data)) {
        return '<div style="padding:40px; text-align:center; color:#555; width:100%; grid-column:1/-1; border:1px dashed #222; border-radius:8px;">No videos found matching this section.</div>';
    }
    
    $html = '<div class="grid">';
    foreach ($video_data as $video) {
        $full_title = stripslashes($video['original_title']);
        $display_code = getThumbnailTitle($full_title); 
        $cat_raw = isset($video['category_name']) ? $video['category_name'] : '';
        
        // Badge Logic
        if (stripos($cat_raw, 'English') !== false) {
            $display_cat = "ENG SUB"; $cat_style = "#ffd700"; $alt_keyword = "JAV English Subtitle";
        } elseif (stripos($cat_raw, 'Indo') !== false) {
            $display_cat = "INDO SUB"; $cat_style = "#d91881"; $alt_keyword = "JAV Sub Indo";
        } else {
            $display_cat = "ENG SUB"; $cat_style = "#ffd700"; $alt_keyword = "JAV Engsub";
        }

        $watch_link = rtrim(BASE_URL, '/') . '/' . $video['slug'];
        $duration_text = formatDurationToMinutes($video['duration']); 
        $image_url = !empty($video['image_url']) ? $video['image_url'] : 'assets/img/no-thumb.jpg';
        $optimized_alt = "Watch " . $full_title . " - " . $alt_keyword;

        $html .= '
        <a href="'.$watch_link.'" class="card" title="'.$optimized_alt.'">
            <span class="badge-quality">'.(!empty($video['quality']) ? $video['quality'] : 'HD').'</span>
            '.($duration_text ? '<span class="badge-dur">'.$duration_text.'</span>' : '').'
            <img src="'.$image_url.'" alt="'.$optimized_alt.'" class="card-img" loading="lazy" width="240" height="360">
            <div class="card-overlay">
                <div class="badges-bottom">
                    <div class="card-code">'.$display_code.'</div>
                    <div class="card-sub" style="color: '.$cat_style.'; border-color: '.$cat_style.';">'.$display_cat.'</div>
                </div>
            </div>
        </a>';
    }
    $html .= '</div>';
    return $html;
}

// --- META SEO ---
$full_page_title = "JAVPORNSUB"; 
$site_desc  = "Watch JAV English Subtitle and JAV Sub Indo Uncensored in Full HD. JAVPORNSUB is the best site for streaming JAV Engsub, JAV Subbed, and Asian Porn with Subtitles.";
$site_keywords = "jav english subtitle, jav eng sub, jav subbed, jav sub indo, nonton jav, streaming jav, jav uncensored, asian porn sub, jav hd, download jav";

$base_clean = rtrim(BASE_URL, '/'); 
$canonical_url = $base_clean; // Homepage canonical selalu bersih
$img_fallback = "https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Big_buck_bunny_poster_big.jpg/1200px-Big_buck_bunny_poster_big.jpg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $full_page_title; ?></title>
    <meta name="description" content="<?= $site_desc; ?>">
    <meta name="keywords" content="<?= $site_keywords; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= $canonical_url; ?>">
    
    <meta property="og:title" content="<?= $full_page_title; ?>">
    <meta property="og:description" content="<?= $site_desc; ?>">
    <meta property="og:url" content="<?= $canonical_url; ?>">
    <meta property="og:type" content="website">
    
    <link rel="stylesheet" href="assets/css/tube-style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        
        /* Ikon & Dropdown Style */
        .dropdown-content a i, .sidebar-menu i, .dropdown-container i {
            margin-right: 10px; width: 20px; text-align: center; color: #888; transition: color 0.2s;
        }
        .nav-link i { margin-right: 8px; color: #aaa; }
        .nav-link:hover i { color: #fff; }
        .dropdown-content a:hover i, .sidebar-item a:hover i { color: #fff; }
        
        /* Nav Arrow Desktop */
        .nav-arrow { font-size: 10px; margin-left: 8px; opacity: 0.5; transition: transform 0.2s; }
        .desktop-dropdown:hover .nav-arrow { transform: rotate(180deg); opacity: 1; }
        .dropdown-content { margin-top: 0; border-top: 2px solid var(--primary); }
    </style>

    <script type="application/ld+json">
    <?php
    $graph = [];
    
    // 1. WEB SITE
    $graph[] = [
        "@type" => "WebSite",
        "@id" => $base_clean . "/#website",
        "url" => $base_clean,
        "name" => "JAVPORNSUB",
        "description" => $site_desc, 
        "potentialAction" => [
            "@type" => "SearchAction",
            "target" => $base_clean . "/videos?search={search_term_string}",
            "query-input" => "required name=search_term_string"
        ]
    ];

    // 2. SITELINKS
    $sitelinks_data = [
        ["name" => "JAV Uncensored", "url" => $base_clean . "/videos?category=uncensored"],
        ["name" => "JAV English Subtitle", "url" => $base_clean . "/videos?category=subtitle-english"],
        ["name" => "JAV Sub Indo", "url" => $base_clean . "/videos?category=sub-indo"],
        ["name" => "JAV Popular", "url" => $base_clean . "/videos?sort=likes"]
    ];

    foreach ($sitelinks_data as $link) {
        $graph[] = [
            "@type" => "SiteNavigationElement",
            "name" => $link['name'],
            "url" => $link['url']
        ];
    }

    // 3. WEB PAGE
    $graph[] = [
        "@type" => "WebPage",
        "@id" => $canonical_url,
        "url" => $canonical_url,
        "name" => $full_page_title,
        "headline" => $full_page_title,
        "description" => $site_desc,
        "isPartOf" => [ "@id" => $base_clean . "/#website" ]
    ];

    // 4. MOVIES (Ambil sample dari section English untuk Schema)
    if (!empty($videos_eng)) {
        foreach ($videos_eng as $v) {
            $v_title = stripslashes($v['original_title']);
            $desc_raw = !empty($v['description']) ? stripslashes($v['description']) : "";
            $v_desc = strip_tags($desc_raw); 
            if (strlen($v_desc) < 5) $v_desc = "Watch " . $v_title . " JAV English Subtitle & Indo Sub Uncensored streaming online free at JAVPORNSUB.";
            $v_desc_final = mb_substr($v_desc, 0, 300);
            $v_studio = !empty($v['studio']) ? stripslashes($v['studio']) : "JAV Admin";
            $v_link = $base_clean . '/' . $v['slug'];
            $v_thumb = !empty($v['image_url']) ? $v['image_url'] : $img_fallback;
            $v_date = date('Y-m-d', strtotime($v['created_at'] ?? 'now'));

            $graph[] = [
                "@type" => "Movie",
                "name" => $v_title,
                "description" => $v_desc_final,
                "image" => $v_thumb,
                "dateCreated" => $v_date,
                "url" => $v_link,
                "director" => [ "@type" => "Person", "name" => $v_studio ],
                "aggregateRating" => [ "@type" => "AggregateRating", "ratingValue" => "4.".rand(6,9), "ratingCount" => rand(100,5000) ]
            ];
        }
    }
    echo json_encode(["@context"=>"https://schema.org","@graph"=>$graph], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    ?>
    </script>
</head>
<body>

    <div class="backdrop" id="backdrop"></div>

    <div class="search-bar-container" id="search-bar">
        <form action="videos" method="GET" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="Search JAV English Subtitle...">
            <button type="submit" class="search-btn-submit">
                <i class="fas fa-search" style="color:#aaa;"></i>
            </button>
        </form>
    </div>

    <header>
        <a href="<?= BASE_URL; ?>">
            <img src="assets/uploads/68658059121ad-logo-1748094252.png" alt="JAVPORNSUB" class="logo-img">
        </a>

        <nav class="nav-menu-desktop">
            <a href="<?= BASE_URL; ?>" class="nav-link"><i class="fas fa-home"></i> Home</a>
            
            <div class="desktop-dropdown">
                <a href="#" class="nav-link"><i class="fas fa-video"></i> Videos <i class="fas fa-chevron-down nav-arrow"></i></a>
                <div class="dropdown-content">
                    <a href="videos?sort=new"><i class="fas fa-clock"></i> New Releases</a>
                    <a href="videos?sort=views"><i class="fas fa-fire"></i> Most Viewed</a>
                    <a href="videos?sort=likes"><i class="fas fa-thumbs-up"></i> Most Liked</a>
                </div>
            </div>
            
            <div class="desktop-dropdown">
                <a href="#" class="nav-link"><i class="fas fa-list"></i> Categories <i class="fas fa-chevron-down nav-arrow"></i></a>
                <div class="dropdown-content">
                    <a href="videos?category=subtitle-english"><i class="fas fa-closed-captioning"></i> JAV ENGLISH SUBTITLE</a>
                    <a href="videos?category=sub-indo"><i class="fas fa-closed-captioning"></i> JAV SUB INDO</a>
                    <a href="videos?category=uncensored"><i class="fas fa-video-slash"></i> JAV UNCENSORED</a>
                    <?php if(!empty($db_cats)): ?>
                        <div style="border-top:1px solid #222; margin:5px 0;"></div>
                        <?php foreach($db_cats as $cat): 
                            $cName = $cat['name'] ?? $cat['category_name'] ?? 'Unknown';
                            $cSlug = $cat['slug'] ?? strtolower(str_replace(' ', '-', $cName));
                            if (in_array(strtolower($cSlug), ['subtitle-english', 'sub-indo', 'uncensored'])) continue;
                        ?>
                            <a href="videos?category=<?= $cSlug; ?>"><i class="fas fa-tag"></i> <?= $cName; ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <a href="actress" class="nav-link"><i class="fas fa-star"></i> Actress</a>
        </nav>

        <div class="header-actions">
            <div class="search-trigger" id="search-trigger">
                <i class="fas fa-search search-icon-svg" style="font-size:18px;"></i>
            </div>
            <div class="hamburger" id="hamburger-btn">
                <span class="bar"></span><span class="bar"></span><span class="bar"></span>
            </div>
        </div>
    </header>

    <aside class="mobile-sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="assets/uploads/68658059121ad-logo-1748094252.png" alt="JAVPORNSUB" class="logo-img" style="max-height: 28px;">
            <div class="close-sidebar" id="close-sidebar">&times;</div>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item"><a href="<?= BASE_URL; ?>" class="active"><i class="fas fa-home"></i> Home Page</a></li>
            <li class="sidebar-item">
                <a href="#" class="dropdown-btn" onclick="toggleDropdown('mb-vid', this); return false;">
                    <span><i class="fas fa-video"></i> All Videos</span>
                    <span class="arrow"><i class="fas fa-chevron-down"></i></span>
                </a>
                <ul id="mb-vid" class="dropdown-container">
                    <li class="sidebar-item"><a href="videos?sort=new"><i class="fas fa-clock"></i> New Releases</a></li>
                    <li class="sidebar-item"><a href="videos?sort=views"><i class="fas fa-fire"></i> Most Viewed</a></li>
                    <li class="sidebar-item"><a href="videos?sort=likes"><i class="fas fa-thumbs-up"></i> Most Liked</a></li>
                </ul>
            </li>
            <li class="sidebar-item">
                <a href="#" class="dropdown-btn" onclick="toggleDropdown('mb-cat', this); return false;">
                    <span><i class="fas fa-list"></i> Categories</span>
                    <span class="arrow"><i class="fas fa-chevron-down"></i></span>
                </a>
                <ul id="mb-cat" class="dropdown-container">
                    <li class="sidebar-item"><a href="videos?category=subtitle-english"><i class="fas fa-closed-captioning"></i> JAV ENGLISH SUBTITLE</a></li>
                    <li class="sidebar-item"><a href="videos?category=sub-indo"><i class="fas fa-closed-captioning"></i> JAV SUB INDO</a></li>
                    <li class="sidebar-item"><a href="videos?category=uncensored"><i class="fas fa-video-slash"></i> JAV UNCENSORED</a></li>
                    <?php if(!empty($db_cats)): foreach($db_cats as $cat): 
                        $cName = $cat['name'] ?? $cat['category_name'] ?? 'Unknown';
                        $cSlug = $cat['slug'] ?? strtolower(str_replace(' ', '-', $cName));
                        if (in_array(strtolower($cSlug), ['subtitle-english', 'sub-indo', 'uncensored'])) continue;
                    ?>
                        <li class="sidebar-item"><a href="videos?category=<?= $cSlug; ?>"><i class="fas fa-tag"></i> <?= $cName; ?></a></li>
                    <?php endforeach; endif; ?>
                </ul>
            </li>
            <li class="sidebar-item"><a href="actress"><i class="fas fa-star"></i> Actress List</a></li>
        </ul>
    </aside>

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
            <p>
                Welcome to <strong>JAVPORNSUB</strong>, your ultimate destination for streaming <strong>JAV English Subtitle</strong> and <strong>JAV Sub Indo</strong> videos. 
                We provide a vast collection of Uncensored JAV (Japanese Adult Video) in Full HD 1080p quality. 
                Unlike other sites, we update our database daily with the latest releases from top studios like S1, Moodyz, IPZ, and SSNI.
            </p>
            <p>
                Find your favorite actresses and watch their best works with clear English and Indonesian subtitles. 
                Whether you are looking for <em>Jav Uncensored</em>, <em>Jav Engsub</em>, or <em>Nonton Bokep Jepang Sub Indo</em>, 
                we have it all categorized for your convenience. Enjoy fast streaming servers compatible with mobile and desktop devices.
            </p>
        </div>
    </main>

    <footer>
        <div class="footer-wrapper">
            <div class="footer-col">
                <div class="footer-logo-text">JAVPORNSUB</div>
                <p class="footer-text">
                    JAVPORNSUB is the #1 source for <strong>JAV English Subtitle</strong> and <strong>JAV Sub Indo</strong>. 
                    We offer free streaming of Japanese Porn with high-quality subtitles.
                </p>
            </div>
            <div class="footer-col">
                <div class="footer-heading"><i class="fas fa-exclamation-circle" style="color:var(--primary); margin-right:8px;"></i> Disclaimer</div>
                <p class="footer-text">This site does not store any files on its server. All contents are provided by non-affiliated third parties.</p>
            </div>
        </div>
        <div class="footer-copyright">&copy; <?= date('Y'); ?> JAVPORNSUB.NET - All Rights Reserved.</div>
    </footer>

    <script>
        const hamburger=document.getElementById('hamburger-btn'),sidebar=document.getElementById('sidebar'),backdrop=document.getElementById('backdrop'),closeBtn=document.getElementById('close-sidebar'),searchTrigger=document.getElementById('search-trigger'),searchBar=document.getElementById('search-bar'),body=document.body;
        function toggleMenu(){sidebar.classList.toggle('active');backdrop.classList.toggle('active');if(sidebar.classList.contains('active')){searchBar.classList.remove('active');body.style.overflow='hidden'}else{body.style.overflow='auto'}}
        function toggleDropdown(id, el) {const menu=document.getElementById(id);if(menu){menu.classList.toggle('show');el.classList.toggle('active');}}
        searchTrigger.addEventListener('click',()=>{searchBar.classList.toggle('active');if(searchBar.classList.contains('active')){document.querySelector('.search-input').focus()}});
        hamburger.addEventListener('click',toggleMenu);backdrop.addEventListener('click',toggleMenu);closeBtn.addEventListener('click',toggleMenu);
    </script>
</body>
</html>
