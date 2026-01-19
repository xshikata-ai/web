<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($full_page_title) ? $full_page_title : 'JAVPORNSUB'; ?></title>
    <meta name="description" content="<?= isset($site_desc) ? $site_desc : ''; ?>">
    <meta name="keywords" content="<?= isset($site_keywords) ? $site_keywords : ''; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= isset($canonical_url) ? $canonical_url : BASE_URL; ?>">
    
    <meta property="og:title" content="<?= isset($full_page_title) ? $full_page_title : 'JAVPORNSUB'; ?>">
    <meta property="og:description" content="<?= isset($site_desc) ? $site_desc : ''; ?>">
    <meta property="og:url" content="<?= isset($canonical_url) ? $canonical_url : BASE_URL; ?>">
    <meta property="og:type" content="website">
    
    <link rel="stylesheet" href="assets/css/tube-style.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="assets/css/video.css?v=<?= time(); ?>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .dropdown-content a i, .sidebar-menu i, .dropdown-container i { margin-right: 10px; width: 20px; text-align: center; color: #888; }
        .nav-link i { margin-right: 8px; color: #aaa; }
        .nav-link:hover i { color: #fff; }
        .nav-arrow { font-size: 10px; margin-left: 8px; opacity: 0.5; transition: transform 0.2s; }
        .desktop-dropdown:hover .nav-arrow { transform: rotate(180deg); opacity: 1; }
        .dropdown-content { margin-top: 0; border-top: 2px solid var(--primary); }
        .sr-only { position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0; }
    </style>

    <script type="application/ld+json">
    <?php
    $graph = [];
    $schema_url = isset($canonical_url) ? $canonical_url : BASE_URL;
    $schema_desc = isset($site_desc) ? $site_desc : '';
    $schema_title = isset($full_page_title) ? $full_page_title : 'JAVPORNSUB';
    
    // 1. WEB SITE (SEARCH ACTION)
    $graph[] = [ 
        "@type" => "WebSite", 
        "@id" => BASE_URL . "/#website", 
        "url" => BASE_URL, 
        "name" => "JAVPORNSUB", 
        "description" => $schema_desc, 
        "potentialAction" => [ 
            "@type" => "SearchAction", 
            "target" => BASE_URL . "/videos?search={search_term_string}", 
            "query-input" => "required name=search_term_string" 
        ]
    ];

    // 2. SITELINKS (SITE NAVIGATION ELEMENT) - INI YANG KEMARIN HILANG
    $sitelinks_data = [
        ["name" => "JAV Uncensored", "url" => BASE_URL . "/videos?category=uncensored"],
        ["name" => "JAV English Subtitle", "url" => BASE_URL . "/videos?category=subtitle-english"],
        ["name" => "JAV Sub Indo", "url" => BASE_URL . "/videos?category=sub-indo"],
        ["name" => "JAV Popular", "url" => BASE_URL . "/videos?sort=likes"]
    ];

    foreach ($sitelinks_data as $link) {
        $graph[] = [
            "@type" => "SiteNavigationElement",
            "name" => $link['name'],
            "url" => $link['url']
        ];
    }

    // 3. WEB PAGE (HALAMAN UTAMA) - INI JUGA PENTING
    $graph[] = [
        "@type" => "WebPage",
        "@id" => $schema_url,
        "url" => $schema_url,
        "name" => $schema_title,
        "headline" => $schema_title,
        "description" => $schema_desc,
        "isPartOf" => [ "@id" => BASE_URL . "/#website" ]
    ];

    // 4. MOVIE SCHEMA (LIST VIDEO)
    // Menggunakan variabel $videos_eng yang dikirim dari index.php
    if (isset($videos_eng) && !empty($videos_eng) && is_array($videos_eng)) {
        foreach ($videos_eng as $v) {
            $v_title = stripslashes($v['original_title']);
            $desc_raw = !empty($v['description']) ? stripslashes($v['description']) : "";
            $v_desc = strip_tags($desc_raw); 
            if (strlen($v_desc) < 5) $v_desc = "Watch " . $v_title . " JAV English Subtitle & Indo Sub Uncensored.";
            $v_desc_final = mb_substr($v_desc, 0, 300);
            $v_studio = !empty($v['studio']) ? stripslashes($v['studio']) : "JAV Admin";
            $v_link = rtrim(BASE_URL, '/') . '/' . $v['slug'];
            $v_thumb = !empty($v['image_url']) ? $v['image_url'] : "https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Big_buck_bunny_poster_big.jpg/1200px-Big_buck_bunny_poster_big.jpg";
            $v_date = date('Y-m-d', strtotime($v['created_at'] ?? 'now'));

            $graph[] = [
                "@type" => "Movie",
                "name" => $v_title,
                "description" => $v_desc_final,
                "image" => $v_thumb,
                "dateCreated" => $v_date,
                "url" => $v_link,
                "director" => [ "@type" => "Person", "name" => $v_studio ],
                "aggregateRating" => [ 
                    "@type" => "AggregateRating", 
                    "ratingValue" => "4.".rand(6,9), 
                    "ratingCount" => rand(100,5000) 
                ]
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
                    <a href="videos?category=subtitle-english"><i class="fas fa-closed-captioning"></i> JAV ENG SUB</a>
                    <a href="videos?category=sub-indo"><i class="fas fa-closed-captioning"></i> JAV SUB INDO</a>
                    <a href="videos?category=uncensored"><i class="fas fa-video-slash"></i> UNCENSORED</a>
                    <?php if(!empty($db_cats)): ?>
                        <div style="border-top:1px solid #222; margin:5px 0;"></div>
                        <?php foreach($db_cats as $cat): 
                            $cName = $cat['name'] ?? 'Cat';
                            $cSlug = $cat['slug'] ?? '';
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
                    <li class="sidebar-item"><a href="videos?category=subtitle-english"><i class="fas fa-closed-captioning"></i> JAV ENG SUB</a></li>
                    <li class="sidebar-item"><a href="videos?category=sub-indo"><i class="fas fa-closed-captioning"></i> JAV SUB INDO</a></li>
                    <li class="sidebar-item"><a href="videos?category=uncensored"><i class="fas fa-video-slash"></i> JAV UNCENSORED</a></li>
                    <?php if(!empty($db_cats)): foreach($db_cats as $cat): 
                        $cName = $cat['name'] ?? 'Cat';
                        $cSlug = $cat['slug'] ?? '';
                        if (in_array(strtolower($cSlug), ['subtitle-english', 'sub-indo', 'uncensored'])) continue;
                    ?>
                        <li class="sidebar-item"><a href="videos?category=<?= $cSlug; ?>"><i class="fas fa-tag"></i> <?= $cName; ?></a></li>
                    <?php endforeach; endif; ?>
                </ul>
            </li>
            <li class="sidebar-item"><a href="actress"><i class="fas fa-star"></i> Actress List</a></li>
        </ul>
    </aside>
