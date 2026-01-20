<?php
// File: video.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

$videoSlug = $_GET['slug'] ?? null;
$video = $videoSlug ? getVideoBySlugFromDB($videoSlug) : null;

// --- 1. JIKA VIDEO TIDAK DITEMUKAN ---
if (!$video) {
    http_response_code(404);
    $full_page_title = "404 Not Found - JAVPORNSUB";
    require_once __DIR__ . '/templates/header.php';
    ?>
    <div class="container-404">
        <img src="<?= BASE_URL; ?>404.png" alt="Page Not Found" class="image-404">
        <h1 class="title-404">Video Not Found</h1>
        <p class="text-404">The video you are looking for might have been deleted or does not exist.</p>
        <a href="<?= BASE_URL; ?>" class="button-404">Back to Home</a>
    </div>
    <?php
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// --- 2. FUNGSI BANTUAN SEO & UTILS ---
if (!function_exists('seo_absolute_url')) {
    function seo_absolute_url($path) {
        if (empty($path)) return BASE_URL . 'assets/img/no-thumb.jpg';
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}
if (!function_exists('seo_duration_iso')) {
    function seo_duration_iso($seconds) {
        $seconds = (int)$seconds;
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return "PT" . ($h > 0 ? $h . "H" : "") . ($m > 0 ? $m . "M" : "") . $s . "S";
    }
}
if (!function_exists('extract_video_code_clean')) {
    function extract_video_code_clean($title) {
        if (preg_match('/([A-Z]{2,5}-[A-Z0-9]{0,3}-?\d{2,6})/i', $title, $matches)) {
            return strtoupper($matches[1]);
        }
        return null; 
    }
}
// [BARU] Fungsi Otomatis Deteksi URL Akhir (Follow Redirect)
if (!function_exists('get_final_redirect_url')) {
    function get_final_redirect_url($url) {
        // Hanya proses jika URL valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) return $url;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);         // Hanya ambil Header (Cepat)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Ikuti Redirect otomatis
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);           // Timeout 3 detik agar loading tidak lama
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_exec($ch);
        
        $target = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        // Kembalikan URL baru jika ditemukan, jika gagal pakai URL lama
        return $target ? $target : $url;
    }
}

// --- 3. KONFIGURASI JUDUL & SEO ---
$raw_title = $video['original_title'];
$detected_code = extract_video_code_clean($raw_title);

// A. JUDUL HALAMAN
$full_page_title = $raw_title . " - JAVPORNSUB";

// B. BREADCRUMB
$breadcrumb_name = $detected_code ? $detected_code : "Watch Video";

// C. META DESCRIPTION
$site_desc = !empty($video['description']) ? strip_tags($video['description']) : $raw_title;

// D. KEYWORDS
$actress_list = !empty($video['actresses']) ? $video['actresses'] : '';
$base_keywords = "jav sub indo, jav english sub, streaming jav, nonton jav";
if ($detected_code) {
    $site_keywords = $detected_code . ", " . $detected_code . " sub indo, " . $detected_code . " english sub, " . str_replace(' ', ', ', $raw_title) . ", " . $actress_list . ", " . $base_keywords;
} else {
    $site_keywords = str_replace(' ', ', ', $raw_title) . ", " . $actress_list . ", " . $base_keywords;
}

$ogImage = seo_absolute_url($video['image_url']);
$canonical_url = BASE_URL . $video['slug'];

// --- 4. LOGIKA SERVER (DENGAN AUTO FIX DOODSTREAM) ---
$sorted_servers = [];
if ($video) {
    $all_urls = [];
    if (!empty($video['embed_url'])) $all_urls[] = $video['embed_url'];
    if (!empty($video['extra_embed_urls'])) {
        $extra = array_map('trim', explode(',', $video['extra_embed_urls']));
        $all_urls = array_merge($all_urls, $extra);
    }
    $all_urls = array_unique(array_filter($all_urls));
    
    // Sorting
    $vh = []; $sw = []; $dd = []; $others = [];
    $vh_dom = ['earnvids', 'dintezuvio', 'dingtezuni'];
    $sw_dom = ['streamhg', 'hglink', 'gradehplus'];
    
    // Daftar semua kemungkinan domain Doodstream (Lama & Baru)
    $dd_dom = ['dood', 'dsvplay', 'vidpply', 'myvidplay', 'd000d', 'ds2play', 'dooood'];

    foreach ($all_urls as $url) {
        $h = strtolower(parse_url($url, PHP_URL_HOST));
        $found = false;

        // 1. Cek Server Vh
        foreach($vh_dom as $d) { 
            if(strpos($h,$d)!==false){ 
                $vh[]=$url; 
                $found=true; 
                break; 
            }
        } 
        if($found) continue;

        // 2. Cek Server Sw
        foreach($sw_dom as $d) { 
            if(strpos($h,$d)!==false){ 
                $sw[]=$url; 
                $found=true; 
                break; 
            }
        } 
        if($found) continue;

        // 3. Cek Server Doodstream (DENGAN AUTO RESOLVE)
        foreach($dd_dom as $d) { 
            if(strpos($h,$d)!==false){ 
                // Panggil fungsi untuk mendapatkan URL domain terbaru secara otomatis
                $dd[] = get_final_redirect_url($url); 
                $found=true; 
                break; 
            }
        } 
        if($found) continue;

        // 4. Server Lainnya
        $others[] = $url;
    }
    $sorted_servers = array_merge($vh, $sw, $dd, $others);
}

// --- LOAD HEADER ---
require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    
    <nav class="breadcrumb-nav" style="margin-bottom: 15px; font-size: 13px; color: #888;">
        <a href="<?= BASE_URL; ?>" style="color: #aaa; text-decoration: none;">Home</a> 
        <span style="margin: 0 5px;">/</span> 
        <span style="color: var(--primary); font-weight:bold;"><?= htmlspecialchars($breadcrumb_name); ?></span>
    </nav>

    <div class="video-hero-block">
        <div class="player-cinematic-frame">
            <iframe id="video-player-iframe" src="<?= htmlspecialchars($sorted_servers[0] ?? ''); ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="<?= htmlspecialchars($video['original_title']); ?>"></iframe>
        </div>
        
        <div class="video-action-bar">
            <div class="video-main-header">
                <h1 class="hero-title"><?= htmlspecialchars($raw_title); ?></h1>
                
                <div class="hero-meta">
                    <span class="hero-badge quality"><?= htmlspecialchars($video['quality'] ?? 'HD'); ?></span>
                    <span class="hero-badge duration"><i class="fas fa-clock"></i> <?= formatDurationToMinutes($video['duration'] ?? 0); ?></span>
                    <span class="hero-badge date"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars(date('d M Y', strtotime($video['cloned_at']))); ?></span>
                    <span class="hero-badge views" style="color:#888;"><i class="fas fa-eye"></i> <?= number_format($video['views']); ?> Views</span>
                </div>
            </div>
        </div>

        <?php if (count($sorted_servers) > 1): ?>
        <div class="server-control-panel">
            <span class="server-label"><i class="fas fa-broadcast-tower"></i> SERVERS:</span>
            <div class="server-scroll-wrapper">
                <?php foreach ($sorted_servers as $index => $url): ?>
                    <button class="modern-server-btn <?= ($index === 0) ? 'active' : ''; ?>" data-embed-url="<?= htmlspecialchars($url); ?>">
                        <span class="status-dot"></span> Server <?= $index + 1; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="content-explorer-wrapper">
        <div class="explorer-tabs">
            <a href="#info" class="explorer-tab active"><i class="fas fa-info-circle"></i> OVERVIEW</a>
            <?php if (!empty($video['trailer_embed_url'])): ?><a href="#trailer" class="explorer-tab"><i class="fas fa-film"></i> TRAILER</a><?php endif; ?>
            <?php if (!empty($video['gallery_image_urls'])): ?><a href="#gallery" class="explorer-tab"><i class="fas fa-images"></i> GALLERY</a><?php endif; ?>
            <?php if (!empty($video['download_links'])): ?><a href="#download" class="explorer-tab"><i class="fas fa-download"></i> DOWNLOAD</a><?php endif; ?>
        </div>

        <div class="explorer-content">
            <div id="info-panel" class="explorer-panel active">
                <div class="info-layout">
                    <div class="info-poster">
                        <img src="<?= htmlspecialchars($ogImage); ?>" alt="Watch <?= htmlspecialchars($raw_title); ?>">
                    </div>
                    <div class="info-details">
                        <div class="meta-tags-group">
                            <?php if (!empty($video['tags'])): 
                                $tags = explode(',', $video['tags']); 
                                foreach ($tags as $tag): $tag=trim($tag); if(!$tag)continue; ?>
                                <a href="<?= BASE_URL . 'genres/tag/' . urlencode($tag); ?>" class="modern-tag"><?= htmlspecialchars($tag); ?></a>
                            <?php endforeach; endif; ?>
                        </div>

                        <div class="specs-grid">
                            <?php if (!empty($video['studios'])): ?>
                                <div class="spec-item"><span class="lbl">Studio</span><span class="val"><?= htmlspecialchars($video['studios']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($video['actresses'])): ?>
                                <div class="spec-item full-width"><span class="lbl">Cast</span><span class="val highlight">
                                    <?php 
                                    $acts = explode(',', $video['actresses']); 
                                    $links = []; 
                                    foreach ($acts as $act) { 
                                        $act=trim($act); if(!$act)continue;
                                        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $act)); 
                                        $links[] = '<a href="' . BASE_URL . 'actress/' . $slug . '">' . htmlspecialchars($act) . '</a>'; 
                                    } 
                                    echo implode(', ', $links); 
                                    ?>
                                </span></div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($video['description'])): ?>
                        <div class="synopsis-box">
                            <h3>Synopsis</h3>
                            <div class="synopsis-text is-collapsible">
                                <p>
                                    <?= nl2br(htmlspecialchars($video['description'])); ?> 
                                </p>
                            </div>
                            <span class="read-more-trigger">Read More</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($video['trailer_embed_url'])): ?>
            <div id="trailer-panel" class="explorer-panel">
                <div class="player-cinematic-frame">
                    <iframe src="<?= htmlspecialchars($video['trailer_embed_url']); ?>" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($video['gallery_image_urls'])): ?>
            <div id="gallery-panel" class="explorer-panel">
                <div class="gallery-grid">
                    <?php 
                    $imgs = array_filter(array_map('trim', explode(',', $video['gallery_image_urls']))); 
                    
                    $code_var = $detected_code ? $detected_code . " " : "";
                    $lsi_keywords = [
                        $code_var . "Full Movie", 
                        $code_var . "Streaming Free", 
                        $code_var . "Uncensored", 
                        "Download " . $code_var . "Sub Indo", 
                        "Watch " . $code_var . "English Sub"
                    ];
                    $lsi_count = count($lsi_keywords);

                    foreach ($imgs as $idx => $img): 
                        $abs_img = seo_absolute_url($img);
                        $keyword = $lsi_keywords[$idx % $lsi_count];
                        $smart_alt = $keyword . " (Scene " . ($idx+1) . ")";
                    ?>
                        <a href="<?= htmlspecialchars($abs_img); ?>" class="gallery-item" data-index="<?= $idx; ?>">
                            <img src="<?= htmlspecialchars($abs_img); ?>" loading="lazy" alt="<?= $smart_alt; ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($video['download_links'])): ?>
            <div id="download-panel" class="explorer-panel">
                <div class="download-grid">
                    <?php $dls = array_filter(array_map('trim', explode(',', $video['download_links']))); foreach ($dls as $i => $link): ?>
                        <a href="<?= htmlspecialchars($link); ?>" target="_blank" class="dl-card" rel="nofollow noopener">
                            <div class="dl-icon"><i class="fas fa-cloud-download-alt"></i></div>
                            <div class="dl-info"><span>Download</span><strong>Server <?= $i+1; ?></strong></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="related-video-wrapper">
        <section class="video-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-film"></i><span>Related Videos</span></h2>
            </div>
            <?php
            // Logic Related Videos
            $relatedVideos = []; $excludeIds = [$video['id']]; $limit = 8;
            if (!empty($video['actresses'])) $relatedVideos = getRelatedVideosByActress($video['actresses'], $video['id'], $limit);
            if (count($relatedVideos) < $limit && !empty($video['tags'])) {
                $needed = $limit - count($relatedVideos); foreach($relatedVideos as $r) $excludeIds[] = $r['id'];
                $relatedVideos = array_merge($relatedVideos, getRelatedVideosByTags($video['tags'], array_unique($excludeIds), $needed));
            }
            if (count($relatedVideos) < $limit) {
                $needed = $limit - count($relatedVideos); foreach($relatedVideos as $r) $excludeIds[] = $r['id'];
                $relatedVideos = array_merge($relatedVideos, getRandomVideosFromDB($needed, array_unique($excludeIds)));
            }
            $relatedVideos = array_slice($relatedVideos, 0, $limit);
            
            if (!empty($relatedVideos)): ?>
                <div class="grid">
                    <?php foreach ($relatedVideos as $rel): 
                        $rel_title = stripslashes($rel['original_title']);
                        
                        // Ekstrak kode untuk tampilan Related
                        $rel_code = extract_video_code_clean($rel_title);
                        $rel_display = $rel_code ? $rel_code : mb_strimwidth($rel_title, 0, 20, "...");

                        $rel_link = BASE_URL . $rel['slug'];
                        $rel_img = seo_absolute_url($rel['image_url']);
                        $rel_cat = !empty($rel['category_name']) ? $rel['category_name'] : 'JAV';
                        
                        // DURASI SESUAI REQUEST (Index Style)
                        $rel_dur = formatDurationToMinutes($rel['duration']);

                        $rel_alt = "Watch " . ($rel_code ? $rel_code : $rel_title);
                    ?>
                    <a href="<?= $rel_link; ?>" class="video-card" title="<?= htmlspecialchars($rel_title); ?>">
                        <div class="thumbnail-container">
                            <img src="<?= htmlspecialchars($rel_img); ?>" alt="<?= $rel_alt; ?>" loading="lazy" width="240" height="360">
                            <span class="category-badge-top"><?= htmlspecialchars($rel_cat); ?></span>
                            <div class="portrait-info-overlay">
                                <div class="portrait-meta">
                                    <span class="meta-quality"><?= htmlspecialchars($rel['quality'] ?? 'HD'); ?></span>
                                    <?php if ($rel_dur): ?>
                                        <span class="meta-duration"><?= $rel_dur; ?></span>
                                    <?php endif; ?>
                                    <div class="portrait-title"><?= htmlspecialchars($rel_display); ?></div>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="message info" style="text-align: center; color:#777;">No related videos found.</p><?php endif; ?>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btns = document.querySelectorAll('.modern-server-btn');
    const iframe = document.getElementById('video-player-iframe');
    btns.forEach(b => b.addEventListener('click', (e) => {
        e.preventDefault(); iframe.src = b.dataset.embedUrl;
        btns.forEach(x => x.classList.remove('active')); b.classList.add('active');
    }));
    
    const readMore = document.querySelector('.read-more-trigger');
    if(readMore) {
        readMore.addEventListener('click', function() {
            this.previousElementSibling.classList.toggle('is-expanded');
            this.textContent = this.textContent === 'Read More' ? 'Show Less' : 'Read More';
        });
    }
    
    const tabs = document.querySelectorAll('.explorer-tab');
    const panels = document.querySelectorAll('.explorer-panel');
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const target = tab.getAttribute('href').substring(1) + '-panel';
            tabs.forEach(t => t.classList.remove('active')); tab.classList.add('active');
            panels.forEach(p => p.classList.remove('active'));
            document.getElementById(target).classList.add('active');
        });
    });

    const lightbox = document.getElementById('gallery-lightbox'); 
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
