<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/IpAccessControl.class.php');

$perm = new Permission();
if (!$perm->isAdmin()) {
  header("location: /login");
  exit;
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Admin · IP Access Control</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 0 10px;
    }

    .main-content {
      margin-top: 20px;
    }

    .table td,
    .table th {
      vertical-align: middle;
      font-size: 14px;
    }

    .text-truncate-cell {
      max-width: 220px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .btn-xsm {
      font-size: 11px;
      padding: 0.15rem 0.45rem;
      line-height: 1.4;
    }

    code {
      word-break: break-all;
    }
  </style>
</head>

<body>
  <main class="container-fluid main-content">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h1 class="mb-0">IP Access Control</h1>
      <a href="/account/admin/admin_csam_cases.php" class="btn btn-outline-dark btn-sm">&laquo; Back to CSAM Cases</a>
    </div>

    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-blocklist" type="button" role="tab">IP Blocklist (CIDR)</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-whitelist" type="button" role="tab">Whitelist</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-legacy" type="button" role="tab">Legacy Blacklist (npub/ip)</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tools" type="button" role="tab">Tools</button></li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white">

      <!-- ============ BLOCKLIST ============ -->
      <div class="tab-pane fade show active" id="tab-blocklist" role="tabpanel">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Add new block</h5>
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label small mb-1">CIDR</label>
                <input type="text" class="form-control form-control-sm" id="blockCidr" placeholder="1.2.3.4/24">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Source</label>
                <input type="text" class="form-control form-control-sm" id="blockSource" value="manual">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Reason</label>
                <input type="text" class="form-control form-control-sm" id="blockReason" placeholder="(optional)">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Expires (optional)</label>
                <input type="datetime-local" class="form-control form-control-sm" id="blockExpires">
              </div>
            </div>
            <div class="mt-2 d-flex align-items-center gap-2">
              <button class="btn btn-danger btn-sm" id="blockAddBtn">Add Block</button>
              <span id="blockAddStatus" class="small text-muted"></span>
            </div>
            <div class="form-text">Min prefixes: /<?= IpAccessControl::MIN_IPV4_PREFIX ?> v4, /<?= IpAccessControl::MIN_IPV6_PREFIX ?> v6. Bare IPs become /32 or /128.</div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-3">
                <label class="form-label small mb-1">Filter by source</label>
                <input type="text" class="form-control form-control-sm" id="blockFilterSource" placeholder="(any)">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1 d-block">&nbsp;</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="blockFilterActive" checked>
                  <label class="form-check-label small" for="blockFilterActive">Active only</label>
                </div>
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Page size</label>
                <select id="blockLimit" class="form-select form-select-sm">
                  <option>50</option>
                  <option selected>100</option>
                  <option>250</option>
                  <option>500</option>
                </select>
              </div>
              <div class="col-md-4 text-end">
                <button class="btn btn-secondary btn-sm" id="blockReloadBtn">Reload</button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="small text-muted" id="blockTotalLabel"></span>
          <div>
            <button class="btn btn-outline-secondary btn-sm" id="blockPrevBtn" disabled>&laquo; Prev</button>
            <button class="btn btn-outline-secondary btn-sm" id="blockNextBtn" disabled>Next &raquo;</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>CIDR</th>
                <th>Range</th>
                <th>Source</th>
                <th>Reason</th>
                <th>Banned</th>
                <th>Expires</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="blockTbody">
              <tr><td colspan="8" class="text-muted small">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ============ WHITELIST ============ -->
      <div class="tab-pane fade" id="tab-whitelist" role="tabpanel">
        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Add to whitelist</h5>
            <p class="small text-muted mb-2">Whitelisted user IDs override the IP blocklist for any matching authenticated request.</p>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small mb-1">User ID (npub or numeric)</label>
                <input type="text" class="form-control form-control-sm" id="wlUserId" placeholder="npub1... or 12345">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Reason</label>
                <input type="text" class="form-control form-control-sm" id="wlReason" placeholder="(optional)">
              </div>
              <div class="col-md-4">
                <label class="form-label small mb-1">Expires (optional)</label>
                <input type="datetime-local" class="form-control form-control-sm" id="wlExpires">
              </div>
            </div>
            <div class="mt-2 d-flex align-items-center gap-2">
              <button class="btn btn-success btn-sm" id="wlAddBtn">Add / Update</button>
              <span id="wlAddStatus" class="small text-muted"></span>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="wlFilterActive" checked>
              <label class="form-check-label small" for="wlFilterActive">Active only</label>
            </div>
            <div>
              <label class="form-label small mb-1">Page size</label>
              <select id="wlLimit" class="form-select form-select-sm">
                <option>50</option>
                <option selected>100</option>
                <option>250</option>
                <option>500</option>
              </select>
            </div>
            <div class="ms-auto">
              <button class="btn btn-secondary btn-sm" id="wlReloadBtn">Reload</button>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="small text-muted" id="wlTotalLabel"></span>
          <div>
            <button class="btn btn-outline-secondary btn-sm" id="wlPrevBtn" disabled>&laquo; Prev</button>
            <button class="btn btn-outline-secondary btn-sm" id="wlNextBtn" disabled>Next &raquo;</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>User ID</th>
                <th>Reason</th>
                <th>Added</th>
                <th>Expires</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="wlTbody">
              <tr><td colspan="5" class="text-muted small">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ============ LEGACY BLACKLIST ============ -->
      <div class="tab-pane fade" id="tab-legacy" role="tabpanel">
        <div class="alert alert-warning py-2 small mb-3">
          Stop-gap CRUD over the existing <code>blacklist</code> table (npub / ip / user_agent).
          The schema is unchanged and the existing upload-time check still uses it. CIDR-style IP blocking lives on the <em>IP Blocklist</em> tab.
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <h5 class="card-title">Add entry</h5>
            <p class="small text-muted mb-2">At least one of <strong>npub</strong> or <strong>ip</strong> is required.</p>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small mb-1">npub</label>
                <input type="text" class="form-control form-control-sm" id="lbNpub" placeholder="npub1...">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">IP</label>
                <input type="text" class="form-control form-control-sm" id="lbIp" placeholder="exact IP, no CIDR">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">User-Agent</label>
                <input type="text" class="form-control form-control-sm" id="lbUa" placeholder="(optional)">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-1">Reason</label>
                <input type="text" class="form-control form-control-sm" id="lbReason" placeholder="(optional)">
              </div>
            </div>
            <div class="mt-2 d-flex align-items-center gap-2">
              <button class="btn btn-danger btn-sm" id="lbAddBtn">Add</button>
              <span id="lbAddStatus" class="small text-muted"></span>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-body">
            <div class="row g-2 align-items-end">
              <div class="col-md-6">
                <label class="form-label small mb-1">Search (npub or ip substring)</label>
                <input type="text" class="form-control form-control-sm" id="lbSearch" placeholder="(any)">
              </div>
              <div class="col-md-2">
                <label class="form-label small mb-1">Page size</label>
                <select id="lbLimit" class="form-select form-select-sm">
                  <option>50</option>
                  <option selected>100</option>
                  <option>250</option>
                  <option>500</option>
                </select>
              </div>
              <div class="col-md-4 text-end">
                <button class="btn btn-secondary btn-sm" id="lbReloadBtn">Search / Reload</button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="small text-muted" id="lbTotalLabel"></span>
          <div>
            <button class="btn btn-outline-secondary btn-sm" id="lbPrevBtn" disabled>&laquo; Prev</button>
            <button class="btn btn-outline-secondary btn-sm" id="lbNextBtn" disabled>Next &raquo;</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>npub</th>
                <th>IP</th>
                <th>User-Agent</th>
                <th>Reason</th>
                <th>Added</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="lbTbody">
              <tr><td colspan="7" class="text-muted small">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ============ TOOLS ============ -->
      <div class="tab-pane fade" id="tab-tools" role="tabpanel">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title">WHOIS lookup (Team Cymru)</h5>
                <div class="input-group mb-2">
                  <input type="text" class="form-control form-control-sm" id="toolWhoisIp" placeholder="IP address">
                  <button class="btn btn-primary btn-sm" id="toolWhoisBtn">Lookup</button>
                </div>
                <div id="toolWhoisBanner" class="mb-2" style="display:none;"></div>
                <table class="table table-sm table-bordered">
                  <tbody id="toolWhoisBody">
                    <tr><td colspan="2" class="text-muted small">Enter an IP and click Lookup.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title">Block check</h5>
                <p class="small text-muted">Test whether an IP would currently be blocked. Optional user ID applies the whitelist override.</p>
                <div class="row g-2 mb-2">
                  <div class="col-md-7">
                    <input type="text" class="form-control form-control-sm" id="toolCheckIp" placeholder="IP address">
                  </div>
                  <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm" id="toolCheckUser" placeholder="user_id (optional)">
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <button class="btn btn-primary btn-sm" id="toolCheckBtn">Check</button>
                  <span id="toolCheckStatus" class="small"></span>
                </div>
                <pre id="toolCheckResult" class="bg-light p-2 small mb-0" style="max-height:240px;overflow:auto;"></pre>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Edit Block Modal -->
  <div class="modal fade" id="editBlockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit block <span id="editBlockId" class="text-muted small"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label small mb-1">Reason</label>
            <input type="text" class="form-control form-control-sm" id="editBlockReason">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Source</label>
            <input type="text" class="form-control form-control-sm" id="editBlockSource">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Expires (blank to clear)</label>
            <input type="datetime-local" class="form-control form-control-sm" id="editBlockExpires">
          </div>
          <div id="editBlockStatus" class="small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm" id="editBlockSaveBtn">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Whitelist Modal -->
  <div class="modal fade" id="editWlModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit whitelist <span id="editWlUserLabel" class="text-muted small"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label small mb-1">Reason</label>
            <input type="text" class="form-control form-control-sm" id="editWlReason">
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Expires (blank to clear)</label>
            <input type="datetime-local" class="form-control form-control-sm" id="editWlExpires">
          </div>
          <div id="editWlStatus" class="small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm" id="editWlSaveBtn">Save</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const API = '/api/v2/admin/security';

    // ASNs that should NEVER be IP-blocked. Blocking them either breaks our
    // own infra (CDNs/clouds) or punishes huge swaths of legitimate users.
    const RISKY_ASN = {
      13335:  { name: 'Cloudflare',         kind: 'CDN / WARP / Tor / Workers' },
      16509:  { name: 'Amazon AWS',         kind: 'cloud' },
      14618:  { name: 'Amazon AES',         kind: 'cloud' },
      15169:  { name: 'Google',             kind: 'cloud / Search / WARP' },
      396982: { name: 'Google Cloud',       kind: 'cloud' },
      8075:   { name: 'Microsoft Azure',    kind: 'cloud' },
      8068:   { name: 'Microsoft',          kind: 'cloud / Office' },
      20940:  { name: 'Akamai',             kind: 'CDN' },
      16276:  { name: 'OVH',                kind: 'shared VPS hosting' },
      24940:  { name: 'Hetzner',            kind: 'shared VPS hosting' },
      14061:  { name: 'DigitalOcean',       kind: 'shared VPS hosting' },
      63949:  { name: 'Linode (Akamai)',    kind: 'shared VPS hosting' },
      9009:   { name: 'M247',               kind: 'shared VPS / consumer VPN exit' },
      46606:  { name: 'Unified Layer',      kind: 'shared hosting' },
    };
    function riskyAsn(asn) { return RISKY_ASN[Number(asn)] || null; }
    function riskyBannerHtml(risky) {
      return '<div class="fw-bold">🚫 DO NOT IP-BLOCK — ' + escapeHtml(risky.name) + ' (' + escapeHtml(risky.kind) + ')</div>'
        + '<div class="small mt-1">This IP belongs to ' + escapeHtml(risky.name) + ' infrastructure. Blocking it will cut off many legitimate users (and possibly our own services). Ban the npub instead.</div>';
    }

    function escapeHtml(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[c]));
    }

    function setStatus(el, msg, kind) {
      el.textContent = msg || '';
      el.className = 'small ' + (kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
    }

    function dtLocalToMysql(v) {
      return v ? v.replace('T', ' ') + ':00' : null;
    }

    function mysqlToDtLocal(v) {
      if (!v) return '';
      // "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM"
      return v.replace(' ', 'T').slice(0, 16);
    }

    // ===== Blocklist =====
    const blockTbody = document.getElementById('blockTbody');
    const blockTotalLabel = document.getElementById('blockTotalLabel');
    const blockPrevBtn = document.getElementById('blockPrevBtn');
    const blockNextBtn = document.getElementById('blockNextBtn');
    let blockOffset = 0;
    let blockTotal = 0;

    async function loadBlocks() {
      const limit = parseInt(document.getElementById('blockLimit').value, 10) || 100;
      const params = new URLSearchParams({ limit: String(limit), offset: String(blockOffset) });
      const src = document.getElementById('blockFilterSource').value.trim();
      if (src) params.set('source', src);
      if (document.getElementById('blockFilterActive').checked) params.set('active_only', '1');

      blockTbody.innerHTML = '<tr><td colspan="8" class="text-muted small">Loading...</td></tr>';
      try {
        const resp = await fetch(API + '/blocklist?' + params, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!resp.ok) {
          blockTbody.innerHTML = '<tr><td colspan="8" class="text-danger small">' + escapeHtml(data.error || 'Error') + '</td></tr>';
          return;
        }
        blockTotal = data.total || 0;
        blockTotalLabel.textContent = `Showing ${data.rows.length} of ${blockTotal}` + (src ? ` (source: ${src})` : '');
        blockPrevBtn.disabled = blockOffset === 0;
        blockNextBtn.disabled = (blockOffset + data.rows.length) >= blockTotal;

        if (data.rows.length === 0) {
          blockTbody.innerHTML = '<tr><td colspan="8" class="text-muted small">No entries.</td></tr>';
          return;
        }
        blockTbody.innerHTML = data.rows.map(r => `
          <tr data-id="${r.id}">
            <td>${escapeHtml(r.id)}</td>
            <td><code>${escapeHtml(r.cidr)}</code></td>
            <td class="small text-muted">${escapeHtml(r.start_ip)} – ${escapeHtml(r.end_ip)}</td>
            <td>${escapeHtml(r.source)}</td>
            <td class="text-truncate-cell" title="${escapeHtml(r.reason || '')}">${escapeHtml(r.reason || '')}</td>
            <td class="small">${escapeHtml(r.banned_at || '')}</td>
            <td class="small">${escapeHtml(r.expires_at || '')}</td>
            <td>
              <button class="btn btn-xsm btn-outline-primary block-edit-btn">Edit</button>
              <button class="btn btn-xsm btn-outline-danger block-del-btn">Delete</button>
            </td>
          </tr>`).join('');

        blockTbody.querySelectorAll('.block-edit-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            const id = tr.getAttribute('data-id');
            const r = data.rows.find(x => String(x.id) === id);
            openEditBlock(r);
          });
        });
        blockTbody.querySelectorAll('.block-del-btn').forEach(btn => {
          btn.addEventListener('click', () => deleteBlock(btn.closest('tr').getAttribute('data-id')));
        });
      } catch (err) {
        blockTbody.innerHTML = '<tr><td colspan="8" class="text-danger small">Network error: ' + escapeHtml(err.message) + '</td></tr>';
      }
    }

    document.getElementById('blockReloadBtn').addEventListener('click', () => { blockOffset = 0; loadBlocks(); });
    document.getElementById('blockPrevBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('blockLimit').value, 10) || 100;
      blockOffset = Math.max(0, blockOffset - limit);
      loadBlocks();
    });
    document.getElementById('blockNextBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('blockLimit').value, 10) || 100;
      blockOffset += limit;
      loadBlocks();
    });

    document.getElementById('blockAddBtn').addEventListener('click', async () => {
      const status = document.getElementById('blockAddStatus');
      const cidr = document.getElementById('blockCidr').value.trim();
      if (!cidr) { setStatus(status, 'CIDR required', 'error'); return; }
      const body = {
        cidr,
        source: document.getElementById('blockSource').value.trim() || 'manual',
        reason: document.getElementById('blockReason').value.trim(),
      };
      const exp = dtLocalToMysql(document.getElementById('blockExpires').value);
      if (exp) body.expires_at = exp;

      setStatus(status, 'Adding...');
      try {
        const resp = await fetch(API + '/blocklist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        });
        const data = await resp.json();
        if (resp.ok && data.success) {
          setStatus(status, `Added ${data.cidr} (id ${data.id})`, 'ok');
          document.getElementById('blockCidr').value = '';
          document.getElementById('blockReason').value = '';
          document.getElementById('blockExpires').value = '';
          loadBlocks();
        } else {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
        }
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    async function deleteBlock(id) {
      if (!confirm('Delete block #' + id + '?')) return;
      try {
        const resp = await fetch(API + '/blocklist/' + id, { method: 'DELETE', credentials: 'same-origin' });
        const data = await resp.json();
        if (resp.ok && data.success) {
          loadBlocks();
        } else {
          alert('Error: ' + (data.error || resp.status));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }

    // Edit block modal
    const editBlockModalEl = document.getElementById('editBlockModal');
    const editBlockModal = new bootstrap.Modal(editBlockModalEl);
    let editingBlockId = null;

    function openEditBlock(r) {
      editingBlockId = r.id;
      document.getElementById('editBlockId').textContent = '#' + r.id + ' (' + r.cidr + ')';
      document.getElementById('editBlockReason').value = r.reason || '';
      document.getElementById('editBlockSource').value = r.source || '';
      document.getElementById('editBlockExpires').value = mysqlToDtLocal(r.expires_at);
      setStatus(document.getElementById('editBlockStatus'), '');
      editBlockModal.show();
    }

    document.getElementById('editBlockSaveBtn').addEventListener('click', async () => {
      if (!editingBlockId) return;
      const status = document.getElementById('editBlockStatus');
      const body = {
        reason: document.getElementById('editBlockReason').value.trim(),
        source: document.getElementById('editBlockSource').value.trim(),
        expires_at: dtLocalToMysql(document.getElementById('editBlockExpires').value),
      };
      setStatus(status, 'Saving...');
      try {
        const resp = await fetch(API + '/blocklist/' + editingBlockId, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        });
        const data = await resp.json();
        if (resp.ok && data.success) {
          setStatus(status, 'Saved.', 'ok');
          editBlockModal.hide();
          loadBlocks();
        } else {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
        }
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    // ===== Whitelist =====
    const wlTbody = document.getElementById('wlTbody');
    const wlTotalLabel = document.getElementById('wlTotalLabel');
    const wlPrevBtn = document.getElementById('wlPrevBtn');
    const wlNextBtn = document.getElementById('wlNextBtn');
    let wlOffset = 0;

    async function loadWhitelist() {
      const limit = parseInt(document.getElementById('wlLimit').value, 10) || 100;
      const params = new URLSearchParams({ limit: String(limit), offset: String(wlOffset) });
      if (document.getElementById('wlFilterActive').checked) params.set('active_only', '1');

      wlTbody.innerHTML = '<tr><td colspan="5" class="text-muted small">Loading...</td></tr>';
      try {
        const resp = await fetch(API + '/whitelist?' + params, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!resp.ok) {
          wlTbody.innerHTML = '<tr><td colspan="5" class="text-danger small">' + escapeHtml(data.error || 'Error') + '</td></tr>';
          return;
        }
        wlTotalLabel.textContent = `Showing ${data.rows.length}`;
        wlPrevBtn.disabled = wlOffset === 0;
        wlNextBtn.disabled = data.rows.length < limit;

        if (data.rows.length === 0) {
          wlTbody.innerHTML = '<tr><td colspan="5" class="text-muted small">No entries.</td></tr>';
          return;
        }
        wlTbody.innerHTML = data.rows.map(r => `
          <tr data-user="${escapeHtml(r.user_id)}">
            <td class="text-truncate-cell"><code>${escapeHtml(r.user_id)}</code></td>
            <td class="text-truncate-cell" title="${escapeHtml(r.reason || '')}">${escapeHtml(r.reason || '')}</td>
            <td class="small">${escapeHtml(r.added_at || '')}</td>
            <td class="small">${escapeHtml(r.expires_at || '')}</td>
            <td>
              <button class="btn btn-xsm btn-outline-primary wl-edit-btn">Edit</button>
              <button class="btn btn-xsm btn-outline-danger wl-del-btn">Delete</button>
            </td>
          </tr>`).join('');

        wlTbody.querySelectorAll('.wl-edit-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const tr = btn.closest('tr');
            const user = tr.getAttribute('data-user');
            const r = data.rows.find(x => x.user_id === user);
            openEditWl(r);
          });
        });
        wlTbody.querySelectorAll('.wl-del-btn').forEach(btn => {
          btn.addEventListener('click', () => deleteWhitelist(btn.closest('tr').getAttribute('data-user')));
        });
      } catch (err) {
        wlTbody.innerHTML = '<tr><td colspan="5" class="text-danger small">Network error: ' + escapeHtml(err.message) + '</td></tr>';
      }
    }

    document.getElementById('wlReloadBtn').addEventListener('click', () => { wlOffset = 0; loadWhitelist(); });
    document.getElementById('wlPrevBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('wlLimit').value, 10) || 100;
      wlOffset = Math.max(0, wlOffset - limit);
      loadWhitelist();
    });
    document.getElementById('wlNextBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('wlLimit').value, 10) || 100;
      wlOffset += limit;
      loadWhitelist();
    });

    document.getElementById('wlAddBtn').addEventListener('click', async () => {
      const status = document.getElementById('wlAddStatus');
      const userId = document.getElementById('wlUserId').value.trim();
      if (!userId) { setStatus(status, 'user_id required', 'error'); return; }
      const body = {
        user_id: userId,
        reason: document.getElementById('wlReason').value.trim(),
      };
      const exp = dtLocalToMysql(document.getElementById('wlExpires').value);
      if (exp) body.expires_at = exp;

      setStatus(status, 'Saving...');
      try {
        const resp = await fetch(API + '/whitelist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        });
        const data = await resp.json();
        if (resp.ok && data.success) {
          setStatus(status, 'Saved.', 'ok');
          document.getElementById('wlUserId').value = '';
          document.getElementById('wlReason').value = '';
          document.getElementById('wlExpires').value = '';
          loadWhitelist();
        } else {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
        }
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    async function deleteWhitelist(userId) {
      if (!confirm('Remove ' + userId + ' from whitelist?')) return;
      try {
        const resp = await fetch(API + '/whitelist/' + encodeURIComponent(userId), { method: 'DELETE', credentials: 'same-origin' });
        const data = await resp.json();
        if (resp.ok && data.success) {
          loadWhitelist();
        } else {
          alert('Error: ' + (data.error || resp.status));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }

    const editWlModalEl = document.getElementById('editWlModal');
    const editWlModal = new bootstrap.Modal(editWlModalEl);
    let editingWlUser = null;

    function openEditWl(r) {
      editingWlUser = r.user_id;
      document.getElementById('editWlUserLabel').textContent = '(' + r.user_id + ')';
      document.getElementById('editWlReason').value = r.reason || '';
      document.getElementById('editWlExpires').value = mysqlToDtLocal(r.expires_at);
      setStatus(document.getElementById('editWlStatus'), '');
      editWlModal.show();
    }

    document.getElementById('editWlSaveBtn').addEventListener('click', async () => {
      if (!editingWlUser) return;
      const status = document.getElementById('editWlStatus');
      const body = {
        reason: document.getElementById('editWlReason').value.trim(),
        expires_at: dtLocalToMysql(document.getElementById('editWlExpires').value),
      };
      setStatus(status, 'Saving...');
      try {
        const resp = await fetch(API + '/whitelist/' + encodeURIComponent(editingWlUser), {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(body),
        });
        const data = await resp.json();
        if (resp.ok && data.success) {
          setStatus(status, 'Saved.', 'ok');
          editWlModal.hide();
          loadWhitelist();
        } else {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
        }
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    // ===== Tools =====
    document.getElementById('toolWhoisBtn').addEventListener('click', async () => {
      const ip = document.getElementById('toolWhoisIp').value.trim();
      const body = document.getElementById('toolWhoisBody');
      const banner = document.getElementById('toolWhoisBanner');
      banner.style.display = 'none';
      banner.innerHTML = '';
      if (!ip) { body.innerHTML = '<tr><td colspan="2" class="text-danger small">Enter an IP.</td></tr>'; return; }
      body.innerHTML = '<tr><td colspan="2" class="text-muted small">Looking up...</td></tr>';
      try {
        const resp = await fetch(API + '/whois?ip=' + encodeURIComponent(ip), { credentials: 'same-origin' });
        const data = await resp.json();
        if (!resp.ok) {
          body.innerHTML = '<tr><td colspan="2" class="text-danger small">' + escapeHtml(data.error || 'Error') + '</td></tr>';
          return;
        }
        if (data.found === false) {
          body.innerHTML = '<tr><td colspan="2" class="text-warning small">No record (private/reserved or not in routing table).</td></tr>';
          return;
        }
        const rows = [
          ['IP', data.ip],
          ['ASN', data.asn ? ('AS' + data.asn) : ''],
          ['AS name', data.as_name || ''],
          ['Announced prefix', data.prefix || ''],
          ['Country', data.country || ''],
          ['Registry', data.registry || ''],
          ['Allocated', data.allocated || ''],
          ['All ASNs', (data.asns || []).join(', ')],
        ];
        body.innerHTML = rows.map(([k, v]) =>
          '<tr><th class="w-25">' + escapeHtml(k) + '</th><td>' + escapeHtml(v) + '</td></tr>'
        ).join('');

        const risky = riskyAsn(data.asn);
        if (risky) {
          banner.className = 'alert alert-danger border border-danger border-2 py-2 mb-2';
          banner.innerHTML = riskyBannerHtml(risky);
          banner.style.display = 'block';
        }
      } catch (err) {
        body.innerHTML = '<tr><td colspan="2" class="text-danger small">Network error: ' + escapeHtml(err.message) + '</td></tr>';
      }
    });

    document.getElementById('toolCheckBtn').addEventListener('click', async () => {
      const ip = document.getElementById('toolCheckIp').value.trim();
      const userId = document.getElementById('toolCheckUser').value.trim();
      const result = document.getElementById('toolCheckResult');
      const status = document.getElementById('toolCheckStatus');
      if (!ip) { setStatus(status, 'Enter an IP.', 'error'); return; }
      setStatus(status, 'Checking...');
      result.textContent = '';
      try {
        const resp = await fetch(API + '/blocklist/check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ ip, userId }),
        });
        const data = await resp.json();
        if (!resp.ok) {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
          return;
        }
        setStatus(status, data.blocked ? 'BLOCKED' : 'allowed', data.blocked ? 'error' : 'ok');
        result.textContent = JSON.stringify(data, null, 2);
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    // ===== Legacy blacklist =====
    const lbTbody = document.getElementById('lbTbody');
    const lbTotalLabel = document.getElementById('lbTotalLabel');
    const lbPrevBtn = document.getElementById('lbPrevBtn');
    const lbNextBtn = document.getElementById('lbNextBtn');
    let lbOffset = 0;

    async function loadLegacy() {
      const limit = parseInt(document.getElementById('lbLimit').value, 10) || 100;
      const params = new URLSearchParams({ limit: String(limit), offset: String(lbOffset) });
      const q = document.getElementById('lbSearch').value.trim();
      if (q) params.set('q', q);

      lbTbody.innerHTML = '<tr><td colspan="7" class="text-muted small">Loading...</td></tr>';
      try {
        const resp = await fetch(API + '/legacy-blacklist?' + params, { credentials: 'same-origin' });
        const data = await resp.json();
        if (!resp.ok) {
          lbTbody.innerHTML = '<tr><td colspan="7" class="text-danger small">' + escapeHtml(data.error || 'Error') + '</td></tr>';
          return;
        }
        lbTotalLabel.textContent = `Showing ${data.rows.length} of ${data.total}` + (q ? ` (matching: ${q})` : '');
        lbPrevBtn.disabled = lbOffset === 0;
        lbNextBtn.disabled = (lbOffset + data.rows.length) >= data.total;

        if (data.rows.length === 0) {
          lbTbody.innerHTML = '<tr><td colspan="7" class="text-muted small">No entries.</td></tr>';
          return;
        }
        lbTbody.innerHTML = data.rows.map(r => `
          <tr data-id="${r.id}">
            <td>${escapeHtml(r.id)}</td>
            <td class="text-truncate-cell" title="${escapeHtml(r.npub || '')}"><code>${escapeHtml(r.npub || '')}</code></td>
            <td><code>${escapeHtml(r.ip || '')}</code></td>
            <td class="text-truncate-cell" title="${escapeHtml(r.user_agent || '')}">${escapeHtml(r.user_agent || '')}</td>
            <td class="text-truncate-cell" title="${escapeHtml(r.reason || '')}">${escapeHtml(r.reason || '')}</td>
            <td class="small">${escapeHtml(r.timestamp || '')}</td>
            <td>
              <button class="btn btn-xsm btn-outline-danger lb-del-btn">Delete</button>
            </td>
          </tr>`).join('');

        lbTbody.querySelectorAll('.lb-del-btn').forEach(btn => {
          btn.addEventListener('click', () => deleteLegacy(btn.closest('tr').getAttribute('data-id')));
        });
      } catch (err) {
        lbTbody.innerHTML = '<tr><td colspan="7" class="text-danger small">Network error: ' + escapeHtml(err.message) + '</td></tr>';
      }
    }

    document.getElementById('lbReloadBtn').addEventListener('click', () => { lbOffset = 0; loadLegacy(); });
    document.getElementById('lbSearch').addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); lbOffset = 0; loadLegacy(); }
    });
    document.getElementById('lbPrevBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('lbLimit').value, 10) || 100;
      lbOffset = Math.max(0, lbOffset - limit);
      loadLegacy();
    });
    document.getElementById('lbNextBtn').addEventListener('click', () => {
      const limit = parseInt(document.getElementById('lbLimit').value, 10) || 100;
      lbOffset += limit;
      loadLegacy();
    });

    document.getElementById('lbAddBtn').addEventListener('click', async () => {
      const status = document.getElementById('lbAddStatus');
      const npub = document.getElementById('lbNpub').value.trim();
      const ip = document.getElementById('lbIp').value.trim();
      const ua = document.getElementById('lbUa').value.trim();
      const reason = document.getElementById('lbReason').value.trim();
      if (!npub && !ip) { setStatus(status, 'npub or ip required', 'error'); return; }

      setStatus(status, 'Adding...');
      try {
        const resp = await fetch(API + '/legacy-blacklist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ npub, ip, user_agent: ua, reason }),
        });
        const data = await resp.json();
        if (resp.ok && data.success) {
          setStatus(status, `Added (id ${data.id})`, 'ok');
          document.getElementById('lbNpub').value = '';
          document.getElementById('lbIp').value = '';
          document.getElementById('lbUa').value = '';
          document.getElementById('lbReason').value = '';
          loadLegacy();
        } else {
          setStatus(status, data.error || ('HTTP ' + resp.status), 'error');
        }
      } catch (err) {
        setStatus(status, 'Network error: ' + err.message, 'error');
      }
    });

    async function deleteLegacy(id) {
      if (!confirm('Delete entry #' + id + '?')) return;
      try {
        const resp = await fetch(API + '/legacy-blacklist/' + id, { method: 'DELETE', credentials: 'same-origin' });
        const data = await resp.json();
        if (resp.ok && data.success) {
          loadLegacy();
        } else {
          alert('Error: ' + (data.error || resp.status));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }

    // Initial load
    loadBlocks();
    document.querySelector('button[data-bs-target="#tab-whitelist"]').addEventListener('shown.bs.tab', () => {
      if (wlTbody.children.length === 1 && wlTbody.firstElementChild.children.length === 1) loadWhitelist();
    });
    document.querySelector('button[data-bs-target="#tab-legacy"]').addEventListener('shown.bs.tab', () => {
      if (lbTbody.children.length === 1 && lbTbody.firstElementChild.children.length === 1) loadLegacy();
    });
    // Always load whitelist + legacy once at boot too so counts are ready when user clicks the tab.
    loadWhitelist();
    loadLegacy();
  </script>
</body>

</html>
