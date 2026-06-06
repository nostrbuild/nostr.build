<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

global $link;

$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close();
    exit;
}

$link->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Video Poster Backfill</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #log { font-family: monospace; font-size: 13px; max-height: 420px; overflow-y: auto; }
        .s-created { color: #198754; }
        .s-skipped { color: #6c757d; }
        .s-failed  { color: #dc3545; }
        .s-info    { color: #0d6efd; }
    </style>
</head>

<body>
    <div class="container py-4" style="max-width: 860px;">
        <h2>Video Poster Backfill</h2>
        <p class="text-muted">
            Generates missing <code>poster.jpg</code> thumbnails for videos on
            <code>v.nostr.build</code>. Each video is HEAD-checked first &mdash; ones that already
            have a poster are skipped. Runs in batches of 10; progress is saved in this browser
            (survives a crash/close), so you can pause and resume.
        </p>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="allMode">
            <label class="form-check-label" for="allMode">
                <strong>Scan ALL paid-subscriber videos</strong> (oldest &rarr; newest, ~50k+)
            </label>
        </div>

        <div class="input-group mb-3">
            <input type="text" class="form-control" id="npub" placeholder="npub1..." autocomplete="off">
            <button class="btn btn-outline-primary" id="scanBtn">Scan</button>
        </div>

        <div id="summary" class="mb-2"></div>

        <div class="progress mb-3" style="height: 24px; display: none;" id="progressWrap">
            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%;">0%</div>
        </div>

        <div class="mb-3">
            <button class="btn btn-success" id="startBtn" disabled>Start</button>
            <button class="btn btn-warning" id="pauseBtn" disabled>Pause</button>
            <button class="btn btn-outline-danger float-end" id="resetBtn">Reset progress</button>
        </div>

        <div class="card">
            <div class="card-body p-2">
                <div id="log"></div>
            </div>
        </div>
    </div>

    <script>
        const BATCH = 10;
        const API     = '/api/v2/admin/media/poster-backfill';
        const API_ALL = '/api/v2/admin/media/poster-backfill-all';
        const ALL_KEY = 'posterBackfill:__ALL__';
        const LOG_CAP = 300;

        const $ = (id) => document.getElementById(id);
        const sleep = (ms) => new Promise(r => setTimeout(r, ms));
        const npubRe = /^npub1[a-z0-9]{20,90}$/;

        let running = false;
        let state = null;
        // npub mode: { mode:'npub', npub, queue:[{id,image}], results:{ [id]:{status,message} } }
        // all  mode: { mode:'all', cursor, total, done, counts:{scanned,created,skipped,failed} }

        const npubKey = (npub) => 'posterBackfill:' + npub;
        const stateKey = (s) => s.mode === 'all' ? ALL_KEY : npubKey(s.npub);
        const save = () => { if (state) localStorage.setItem(stateKey(state), JSON.stringify(state)); };

        function logLine(html, cls) {
            const div = document.createElement('div');
            if (cls) div.className = cls;
            div.innerHTML = html;
            const log = $('log');
            log.appendChild(div);
            while (log.childElementCount > LOG_CAP) log.removeChild(log.firstChild);
            log.scrollTop = log.scrollHeight;
        }

        function counts() {
            if (!state) return { total: 0, done: 0, created: 0, skipped: 0, failed: 0 };
            if (state.mode === 'all') {
                return {
                    total: state.total, done: state.counts.scanned,
                    created: state.counts.created, skipped: state.counts.skipped, failed: state.counts.failed
                };
            }
            const r = Object.values(state.results);
            return {
                total: state.queue.length, done: r.length,
                created: r.filter(x => x.status === 'created').length,
                skipped: r.filter(x => x.status === 'skipped').length,
                failed:  r.filter(x => x.status === 'failed').length,
            };
        }

        function isComplete() {
            if (!state) return false;
            if (state.mode === 'all') return !!state.done;
            return Object.keys(state.results).length >= state.queue.length;
        }

        function render() {
            const c = counts();
            $('summary').innerHTML = state
                ? `<strong>${c.total.toLocaleString()}</strong> videos &middot; ${c.done.toLocaleString()} processed &middot;
                   <span class="s-created">${c.created.toLocaleString()} created</span> &middot;
                   <span class="s-skipped">${c.skipped.toLocaleString()} skipped</span> &middot;
                   <span class="s-failed">${c.failed.toLocaleString()} failed</span>`
                : '';
            const pct = c.total ? Math.round(c.done / c.total * 100) : 0;
            $('progressWrap').style.display = state ? 'flex' : 'none';
            $('progressBar').style.width = pct + '%';
            $('progressBar').textContent = pct + '%';
            $('startBtn').disabled = running || !state || isComplete();
            $('pauseBtn').disabled = !running;
        }

        // ---- single npub mode ----

        async function processNpubBatch(batch) {
            const ids = batch.map(v => v.id);
            for (let attempt = 0; attempt < 2; attempt++) {
                try {
                    const res = await fetch(API, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ npub: state.npub, ids })
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return (await res.json()).results || [];
                } catch (e) {
                    if (attempt === 0) { await sleep(1500); continue; }
                    return ids.map(id => ({ id, status: 'failed', message: e.message }));
                }
            }
        }

        async function runNpub() {
            while (running) {
                const batch = state.queue.filter(v => !state.results[v.id]).slice(0, BATCH);
                if (batch.length === 0) break;
                const results = await processNpubBatch(batch);
                for (const r of results) {
                    state.results[r.id] = { status: r.status, message: r.message };
                    const v = state.queue.find(x => x.id === r.id);
                    logLine(`[${r.status.toUpperCase()}] #${r.id} ${v ? v.image : ''} ${r.message ? '&mdash; ' + r.message : ''}`, 's-' + r.status);
                }
                save(); render();
            }
        }

        // ---- scan-all mode ----

        async function runAll() {
            while (running) {
                let data;
                try {
                    const res = await fetch(API_ALL, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ after_id: state.cursor, limit: BATCH })
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    data = await res.json();
                } catch (e) {
                    logLine(`Batch after #${state.cursor} errored (${e.message}) &mdash; retry in 2s`, 's-failed');
                    await sleep(2000);
                    continue; // retry same cursor; extraction is idempotent
                }

                const b = { created: 0, skipped: 0, failed: 0 };
                for (const r of data.results) {
                    state.counts[r.status]++;
                    b[r.status]++;
                    if (r.status !== 'skipped') {
                        logLine(`[${r.status.toUpperCase()}] #${r.id} ${r.image} ${r.npub ? r.npub.slice(0, 12) + '…' : ''} ${r.message ? '&mdash; ' + r.message : ''}`, 's-' + r.status);
                    }
                }
                state.counts.scanned += data.scanned;
                state.cursor = data.cursor;
                logLine(`&middot; through #${data.cursor}: +${b.created} created, ${b.skipped} skipped, ${b.failed} failed (${state.counts.scanned.toLocaleString()}/${state.total.toLocaleString()})`, 's-info');
                save(); render();

                if (!data.more) { state.done = true; save(); break; }
            }
        }

        async function run() {
            running = true; render();
            if (state.mode === 'all') await runAll(); else await runNpub();
            running = false;
            if (isComplete()) logLine('<strong>Done.</strong>', 's-created');
            render();
        }

        // ---- scan / init ----

        async function scanNpub() {
            const npub = $('npub').value.trim();
            if (!npubRe.test(npub)) { alert('Enter a valid npub1...'); return; }
            $('log').innerHTML = '';
            const raw = localStorage.getItem(npubKey(npub));
            const cached = raw ? JSON.parse(raw) : null;
            if (cached && cached.queue && cached.queue.length) {
                state = cached;
                logLine(`Resumed ${npub} (${Object.keys(state.results).length}/${state.queue.length} done).`, 's-info');
                render();
                return;
            }
            const res = await fetch(`${API}?npub=${encodeURIComponent(npub)}`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!res.ok) { logLine('Scan error: ' + (data.error || res.status), 's-failed'); return; }
            state = { mode: 'npub', npub, queue: data.videos, results: {} };
            save();
            logLine(`Found ${data.count} videos for ${npub}.`, 's-info');
            render();
        }

        async function scanAll() {
            $('log').innerHTML = '';
            const raw = localStorage.getItem(ALL_KEY);
            const cached = raw ? JSON.parse(raw) : null;
            if (cached && cached.mode === 'all') {
                state = cached;
                logLine(`Resumed scan-all at cursor #${state.cursor} (${state.counts.scanned.toLocaleString()}/${state.total.toLocaleString()} scanned).`, 's-info');
                render();
                return;
            }
            const res = await fetch(`${API}?all=1`, { credentials: 'same-origin' });
            const data = await res.json();
            if (!res.ok) { logLine('Scan error: ' + (data.error || res.status), 's-failed'); return; }
            state = { mode: 'all', cursor: 0, total: data.total, done: false, counts: { scanned: 0, created: 0, skipped: 0, failed: 0 } };
            save();
            logLine(`${data.total.toLocaleString()} total videos. Oldest first. Click Start.`, 's-info');
            render();
        }

        // ---- wiring ----

        $('allMode').addEventListener('change', (e) => {
            running = false;
            $('npub').disabled = e.target.checked;
            state = null;
            $('log').innerHTML = '';
            render();
        });

        $('scanBtn').addEventListener('click', async () => {
            $('scanBtn').disabled = true;
            try { await ($('allMode').checked ? scanAll() : scanNpub()); }
            finally { $('scanBtn').disabled = false; }
        });

        $('startBtn').addEventListener('click', () => { if (state && !running) run(); });
        $('pauseBtn').addEventListener('click', () => { running = false; render(); });
        $('resetBtn').addEventListener('click', () => {
            if (state) localStorage.removeItem(stateKey(state));
            else localStorage.removeItem($('allMode').checked ? ALL_KEY : npubKey($('npub').value.trim()));
            running = false; state = null;
            $('log').innerHTML = '';
            render();
        });

        render();
    </script>
</body>

</html>
