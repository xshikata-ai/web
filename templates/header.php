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
    $base_url_clean = rtrim(BASE_URL, '/');
    $schema_url = isset($canonical_url) ? $canonical_url : $base_url_clean;
    $schema_desc = isset($site_desc) ? $site_desc : '';
    $schema_title = isset($full_page_title) ? $full_page_title : 'JAVPORNSUB';
    
    // 1. WEB SITE
    $graph[] = [ 
        "@type" => "WebSite", 
        "@id" => $base_url_clean . "/#website", 
        "url" => $base_url_clean, 
        "name" => "JAVPORNSUB", 
        "description" => "Watch JAV English Subtitle & JAV Sub Indo Free", 
        "potentialAction" => [ 
            "@type" => "SearchAction", 
            "target" => $base_url_clean . "/videos?search={search_term_string}", 
            "query-input" => "required name=search_term_string" 
        ]
    ];

    // 2. SITELINKS
    $sitelinks_data = [
        ["name" => "JAV Uncensored", "url" => $base_url_clean . "/videos?category=uncensored"],
        ["name" => "JAV English Subtitle", "url" => $base_url_clean . "/videos?category=subtitle-english"],
        ["name" => "JAV Sub Indo", "url" => $base_url_clean . "/videos?category=sub-indo"],
        ["name" => "JAV Popular", "url" => $base_url_clean . "/videos?sort=likes"]
    ];

    foreach ($sitelinks_data as $link) {
        $graph[] = [
            "@type" => "SiteNavigationElement",
            "name" => $link['name'],
            "url" => $link['url']
        ];
    }

    // 3. WEB PAGE
    $web_page_schema = [
        "@type" => "WebPage",
        "@id" => $schema_url,
        "url" => $schema_url,
        "name" => $schema_title,
        "headline" => $schema_title,
        "description" => $schema_desc,
        "isPartOf" => [ "@id" => $base_url_clean . "/#website" ]
    ];

    // 4. VIDEO OBJECT & BREADCRUMB
    if (isset($video) && is_array($video)) {
        
        // Helper Durasi untuk Schema (ISO 8601)
        $iso_dur = 'PT0M0S';
        if (function_exists('seo_duration_iso')) {
            $iso_dur = seo_duration_iso($video['duration'] ?? 0);
        } else {
             $sec = (int)($video['duration'] ?? 0);
             $h = floor($sec / 3600); $m = floor(($sec % 3600) / 60); $s = $sec % 60;
             $iso_dur = "PT" . ($h > 0 ? $h . "H" : "") . ($m > 0 ? $m . "M" : "") . $s . "S";
        }

        $final_embed = '';
        if (isset($sorted_servers) && !empty($sorted_servers)) {
             $final_embed = $sorted_servers[0];
        } elseif (!empty($video['embed_url'])) {
             $final_embed = $video['embed_url'];
        }
        
        $final_thumb = isset($ogImage) ? $ogImage : ($video['image_url'] ?? '');

        // Video Object Nested
        $video_object_nested = [
            "@type" => "VideoObject",
            "name" => $video['original_title'],
            "description" => $schema_desc,
            "thumbnailUrl" => [$final_thumb], 
            "uploadDate" => date('c', strtotime($video['created_at'] ?? $video['cloned_at'] ?? 'now')),
            "duration" => $iso_dur,
            "contentUrl" => $schema_url, 
            "embedUrl" => $final_embed,
            "publisher" => [
                "@type" => "Organization",
                "name" => "JAVPORNSUB",
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => $base_url_clean . "/assets/uploads/68658059121ad-logo-1748094252.png"
                ]
            ],
            "interactionStatistic" => [
                "@type" => "InteractionCounter",
                "interactionType" => ["@type" => "WatchAction"],
                "userInteractionCount" => (int)($video['views'] ?? 0)
            ]
        ];

        // Masukkan VideoObject ke dalam mainEntity WebPage
        $web_page_schema['mainEntity'] = $video_object_nested;

        // BREADCRUMB (Sinkron dengan Video.php)
        // Jika variabel $breadcrumb_name dikirim dari video.php, gunakan itu.
        $bc_label = isset($breadcrumb_name) ? $breadcrumb_name : "Video";

        $graph[] = [
            "@type" => "BreadcrumbList",
            "itemListElement" => [
                [
                    "@type" => "ListItem",
                    "position" => 1,
                    "name" => "Home",
                    "item" => $base_url_clean
                ],
                [
                    "@type" => "ListItem",
                    "position" => 2,
                    "name" => $bc_label, // <-- Nama Kode Video di Breadcrumb Schema
                    "item" => $schema_url
                ]
            ]
        ];
    }

    $graph[] = $web_page_schema;

    // 5. MOVIE SCHEMA (Halaman Index)
    if (!isset($video) && isset($videos_eng) && !empty($videos_eng)) {
        foreach ($videos_eng as $v) {
            $v_title = stripslashes($v['original_title']);
            $v_desc = mb_substr(strip_tags($v['description'] ?? "Watch " . $v_title), 0, 300);
            $v_link = $base_url_clean . '/' . $v['slug'];
            $v_thumb = !empty($v['image_url']) ? $v['image_url'] : "";
            $v_date = date('Y-m-d', strtotime($v['created_at'] ?? 'now'));

            $graph[] = [
                "@type" => "Movie",
                "name" => $v_title,
                "description" => $v_desc,
                "image" => $v_thumb,
                "dateCreated" => $v_date,
                "url" => $v_link,
                "director" => [ "@type" => "Person", "name" => "JAV Admin" ],
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
