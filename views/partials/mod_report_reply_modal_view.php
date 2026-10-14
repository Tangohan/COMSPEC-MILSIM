<?php
declare(strict_types=1);

// Affichage de la fenêtre « réponse à votre signalement ».
// Attend : $modReplyItems (ModReportReplyNotice::items, déjà limitées), $modReplyTotal, $modReplyLang,
// $modReplyAction, $modReplyHubUrl, $modReplyCsrf (champ CSRF déjà rendu).

use App\Support\ModReportReplyNotice;

$mrCopy = ModReportReplyNotice::copy($modReplyLang, (int) $modReplyTotal);
$mrMore = max(0, (int) $modReplyTotal - count($modReplyItems));
$mrH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="mrr-overlay" id="mod-report-reply-notice" role="dialog" aria-modal="true" aria-labelledby="mrr-title" translate="no" lang="<?= $mrH($modReplyLang) ?>">
    <form class="mrr-card" method="post" action="<?= $mrH($modReplyAction) ?>" data-mrr-form>
        <?= $modReplyCsrf ?>
        <button type="button" class="mrr-x" data-mrr-close aria-label="<?= $mrH($mrCopy['close']) ?>">×</button>
        <p class="mrr-kicker"><span class="mrr-dot" aria-hidden="true"></span><?= $mrH($mrCopy['kicker']) ?></p>
        <h2 class="mrr-title" id="mrr-title"><?= $mrH($mrCopy['title']) ?></h2>
        <p class="mrr-lead"><?= $mrH($mrCopy['lead']) ?></p>
        <ul class="mrr-list">
            <?php foreach ($modReplyItems as $it): ?>
                <li class="mrr-item" data-mrr-item>
                    <input type="hidden" name="ids[]" value="<?= (int) $it['id'] ?>">
                    <div class="mrr-meta">
                        <?php if ($it['status_label'] !== ''): ?>
                            <span class="mrr-pill mrr-pill--<?= $mrH($it['status_tone']) ?>"><span class="mrr-sr"><?= $mrH($mrCopy['status']) ?> : </span><?= $mrH($it['status_label']) ?></span>
                        <?php endif; ?>
                        <?php if ($it['date'] !== ''): ?>
                            <span class="mrr-date"><?= $mrH($it['date']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="mrr-report">
                        <span class="mrr-label"><?= $mrH($it['auto'] ? $mrCopy['auto_report'] : $mrCopy['report']) ?></span>
                        <?php if ($it['report'] !== ''): ?><span class="mrr-report-text"><?= $mrH($it['report']) ?></span><?php endif; ?>
                    </p>
                    <blockquote class="mrr-message"><?= nl2br($mrH($it['message'])) ?></blockquote>
                    <div class="mrr-item-actions">
                        <button type="submit" name="id" value="<?= (int) $it['id'] ?>" class="mrr-btn mrr-btn--solid" data-mrr-one><?= $mrH($mrCopy['ok']) ?></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($mrMore > 0): ?>
            <p class="mrr-more">+ <?= $mrMore ?> <?= $mrH($mrCopy['more']) ?></p>
        <?php endif; ?>
        <div class="mrr-actions">
            <a class="mrr-btn" href="<?= $mrH($modReplyHubUrl) ?>"><?= $mrH($mrCopy['hub']) ?></a>
            <?php if (count($modReplyItems) > 1): ?>
                <button type="submit" class="mrr-btn mrr-btn--solid" data-mrr-all><?= $mrH($mrCopy['ok_all']) ?></button>
            <?php endif; ?>
        </div>
    </form>
</div>
<style>
.mrr-overlay{--mrr-bg:#fff;--mrr-fg:#0f172a;--mrr-muted:#64748b;--mrr-line:#e2e8f0;--mrr-soft:#f1f5f9;--mrr-accent:#047857;--mrr-accent-fg:#fff;position:fixed;inset:0;z-index:9989;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.6)}
html[data-bo-theme="dark"] .mrr-overlay{--mrr-bg:#111827;--mrr-fg:#e5e7eb;--mrr-muted:#9ca3af;--mrr-line:#1f2937;--mrr-soft:#0b1220;--mrr-accent:#10b981;--mrr-accent-fg:#052e1c}
@media (prefers-color-scheme:dark){html:not([data-bo-theme="light"]) .mrr-overlay{--mrr-bg:#111827;--mrr-fg:#e5e7eb;--mrr-muted:#9ca3af;--mrr-line:#1f2937;--mrr-soft:#0b1220;--mrr-accent:#10b981;--mrr-accent-fg:#052e1c}}
.mrr-card{position:relative;width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:var(--mrr-bg);color:var(--mrr-fg);border-radius:16px;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.35);font-size:15px;line-height:1.55}
.mrr-card p{margin:0 0 10px}
.mrr-x{position:absolute;top:10px;right:10px;width:36px;height:36px;border:0;border-radius:10px;background:transparent;color:var(--mrr-muted);font-size:24px;line-height:1;cursor:pointer}
.mrr-x:hover{background:var(--mrr-soft);color:var(--mrr-fg)}
.mrr-kicker{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--mrr-accent)}
.mrr-dot{width:8px;height:8px;border-radius:50%;background:var(--mrr-accent)}
.mrr-title{margin:0 0 6px;padding-right:32px;font-size:22px;font-weight:800;line-height:1.25}
.mrr-lead{color:var(--mrr-muted)}
.mrr-list{list-style:none;margin:14px 0 0;padding:0;display:flex;flex-direction:column;gap:12px}
.mrr-item{border:1px solid var(--mrr-line);border-radius:12px;padding:14px;transition:opacity .2s,transform .2s}
.mrr-item.is-gone{opacity:0;transform:translateX(12px)}
.mrr-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:8px}
.mrr-pill{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700}
.mrr-pill--ok{background:#d1fae5;color:#065f46}
.mrr-pill--work{background:#fef3c7;color:#92400e}
.mrr-pill--info{background:#dbeafe;color:#1e40af}
.mrr-pill--muted{background:#e2e8f0;color:#334155}
.mrr-date{font-size:13px;color:var(--mrr-muted)}
.mrr-report{display:flex;flex-direction:column;gap:2px}
.mrr-label{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--mrr-muted)}
.mrr-report-text{font-weight:600;overflow-wrap:anywhere}
.mrr-message{margin:0;padding:10px 12px;border-left:3px solid var(--mrr-accent);background:var(--mrr-soft);border-radius:0 8px 8px 0;overflow-wrap:anywhere}
.mrr-item-actions{display:flex;justify-content:flex-end;margin-top:10px}
.mrr-more{margin-top:10px!important;font-size:13px;color:var(--mrr-muted)}
.mrr-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.mrr-btn{flex:1 1 auto;display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:10px;border:1px solid var(--mrr-line);background:var(--mrr-bg);color:var(--mrr-fg);font-weight:700;font-size:15px;text-decoration:none;cursor:pointer}
.mrr-item-actions .mrr-btn{flex:0 0 auto;min-width:120px}
.mrr-btn--solid{background:var(--mrr-accent);border-color:var(--mrr-accent);color:var(--mrr-accent-fg)}
.mrr-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
@media (max-width:480px){.mrr-overlay{align-items:flex-end;padding:0}.mrr-card{max-width:none;border-radius:16px 16px 0 0;max-height:92vh;padding:20px 16px}.mrr-item-actions .mrr-btn{flex:1 1 auto}}
</style>
<script>
(function () {
    var root = document.getElementById('mod-report-reply-notice');
    if (!root || !window.fetch || !window.FormData) { return; }
    var form = root.querySelector('[data-mrr-form]');
    function close() { root.remove(); }
    function send(ids) {
        var fd = new FormData();
        var token = form.querySelector('input[name="_csrf_token"]');
        if (token) { fd.append('_csrf_token', token.value); }
        ids.forEach(function (id) { fd.append('ids[]', id); });
        return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .catch(function () {});
    }
    form.addEventListener('submit', function (e) {
        var btn = e.submitter;
        if (!btn) { return; }
        e.preventDefault();
        if (btn.hasAttribute('data-mrr-one')) {
            var item = btn.closest('[data-mrr-item]');
            send([btn.value]);
            item.classList.add('is-gone');
            setTimeout(function () {
                item.remove();
                if (!root.querySelector('[data-mrr-item]')) { close(); }
            }, 200);
            return;
        }
        var all = Array.prototype.map.call(form.querySelectorAll('input[name="ids[]"]'), function (i) { return i.value; });
        send(all);
        close();
    });
    // Fermer sans « Compris » : la fenêtre revient plus tard (10 min), rien n'est marqué lu.
    root.querySelector('[data-mrr-close]').addEventListener('click', close);
    root.addEventListener('click', function (e) { if (e.target === root) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.contains(root)) { close(); } });
})();
</script>
