<?php
declare(strict_types=1);
$title = (string) ($title ?? 'Briefing tactique');
$atakTenantName = trim((string) ($atakTenantName ?? 'Communauté'));
$atakSlides = is_array($atakSlides ?? null) ? $atakSlides : [];
$atakPairingToken = trim((string) ($atakPairingToken ?? ''));
$atakPresenceUrl = (string) ($atakPresenceUrl ?? url('api/atak/briefing-presence'));
$atakCommentsBaseUrl = (string) ($atakCommentsBaseUrl ?? url('api/atak/briefing-slides'));

$slidesPayload = [];
foreach ($atakSlides as $slide) {
    $imagePath = trim((string) ($slide['image_path'] ?? ''));
    if ($imagePath === '') {
        continue;
    }
    $slidesPayload[] = [
        'id' => (int) ($slide['id'] ?? 0),
        'title' => trim((string) ($slide['title'] ?? '')),
        'detail' => trim((string) ($slide['detail_text'] ?? '')),
        'image_url' => url($imagePath),
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --ink: #0f172a;
            --panel: #111827;
            --line: rgba(255,255,255,.1);
            --muted: #94a3b8;
            --accent: #34d399;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 10% -10%, #1e293b 0%, #0b1220 55%, #020617 100%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            color: #e2e8f0;
        }
        header {
            position: sticky; top: 0; z-index: 8;
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .85rem 1rem;
            background: rgba(15, 23, 42, .92);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(8px);
        }
        header .eyebrow { margin: 0 0 .1rem; font-size: .58rem; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; color: var(--accent); }
        header h1 { margin: 0; font-size: .95rem; font-weight: 900; }
        .presence-chip {
            flex-shrink: 0;
            border: 1px solid rgba(52,211,153,.35);
            background: rgba(52,211,153,.08);
            color: #a7f3d0;
            border-radius: 999px;
            padding: .35rem .7rem;
            font-size: .7rem;
            font-weight: 700;
        }
        .deck {
            position: relative;
            min-height: calc(100vh - 9.5rem);
            padding: .85rem 1rem 7.5rem;
            overflow: hidden;
        }
        .slide {
            position: absolute; inset: .85rem 1rem auto;
            opacity: 0;
            transform: translateX(28px) scale(.985);
            pointer-events: none;
            transition: opacity .42s ease, transform .42s ease;
            will-change: opacity, transform;
        }
        .slide.is-active {
            position: relative;
            inset: auto;
            opacity: 1;
            transform: none;
            pointer-events: auto;
        }
        .slide.is-exit {
            position: absolute;
            inset: .85rem 1rem auto;
            opacity: 0;
            transform: translateX(-24px) scale(.99);
        }
        @media (prefers-reduced-motion: reduce) {
            .slide, .slide.is-exit { transition: none; transform: none; }
        }
        .frame {
            background: #fff;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 18px 40px -18px rgba(0,0,0,.65);
        }
        .frame img { width: 100%; display: block; max-height: 58vh; object-fit: contain; background: #0b1220; }
        .cap {
            padding: .75rem .95rem .85rem;
            color: var(--ink);
        }
        .cap h2 { margin: 0; font-size: .95rem; font-weight: 800; line-height: 1.3; }
        .cap .detail {
            margin: .55rem 0 0;
            font-size: .8rem;
            line-height: 1.45;
            color: #334155;
            white-space: pre-wrap;
        }
        .empty {
            text-align: center;
            padding: 4rem 1rem;
            color: var(--muted);
            font-size: .875rem;
        }
        .toolbar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 9;
            display: grid; grid-template-columns: 1fr 1.2fr 1fr;
            gap: .5rem;
            padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom));
            background: rgba(2, 6, 23, .94);
            border-top: 1px solid var(--line);
        }
        .toolbar button {
            appearance: none; border: 0; border-radius: .85rem;
            padding: .85rem .6rem; font-weight: 800; font-size: .85rem;
            background: #1e293b; color: #f8fafc;
        }
        .toolbar button:disabled { opacity: .4; }
        .toolbar .next { background: #059669; }
        .toolbar .idx { text-align: center; align-self: center; font-size: .75rem; color: var(--muted); font-weight: 700; }
        .panels {
            display: grid;
            gap: .75rem;
            margin-top: .85rem;
        }
        .panel {
            border: 1px solid var(--line);
            border-radius: 1rem;
            background: rgba(15, 23, 42, .72);
            padding: .85rem .9rem;
        }
        .panel h3 {
            margin: 0 0 .55rem;
            font-size: .68rem;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--accent);
        }
        .presence-list, .comments-list {
            list-style: none; margin: 0; padding: 0;
            display: flex; flex-direction: column; gap: .45rem;
        }
        .presence-list li, .comments-list li {
            font-size: .8rem;
            color: #cbd5e1;
            line-height: 1.35;
        }
        .comments-list li strong { color: #f8fafc; }
        .comments-list li time { color: var(--muted); font-size: .68rem; margin-left: .35rem; }
        .comment-form { display: grid; gap: .5rem; margin-top: .65rem; }
        .comment-form input, .comment-form textarea {
            width: 100%;
            border: 1px solid rgba(148,163,184,.35);
            border-radius: .7rem;
            background: #0b1220;
            color: #e2e8f0;
            padding: .65rem .75rem;
            font: inherit;
        }
        .comment-form textarea { min-height: 4.5rem; resize: vertical; }
        .comment-form button {
            appearance: none; border: 0; border-radius: .75rem;
            background: #059669; color: #fff; font-weight: 800;
            padding: .7rem .9rem;
        }
        .flash { margin-top: .45rem; font-size: .75rem; color: var(--accent); }
        .flash.is-err { color: var(--danger); }
        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }
    </style>
</head>
<body
    data-briefing-deck
    data-token="<?= htmlspecialchars($atakPairingToken, ENT_QUOTES, 'UTF-8') ?>"
    data-presence-url="<?= htmlspecialchars($atakPresenceUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-comments-base="<?= htmlspecialchars($atakCommentsBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
>
    <header>
        <div>
            <p class="eyebrow">Briefing tactique</p>
            <h1><?= htmlspecialchars($atakTenantName, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="presence-chip" id="presence-chip" aria-live="polite">ATAK : —</div>
    </header>

    <?php if ($slidesPayload === []): ?>
    <div class="empty">Aucune diapositive de briefing active pour l’instant.</div>
    <?php else: ?>
    <div class="deck" id="briefing-deck" aria-live="polite">
        <?php foreach ($slidesPayload as $i => $slide): ?>
        <article
            class="slide<?= $i === 0 ? ' is-active' : '' ?>"
            data-slide-index="<?= $i ?>"
            data-slide-id="<?= (int) $slide['id'] ?>"
            aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
        >
            <div class="frame">
                <img
                    src="<?= htmlspecialchars($slide['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($slide['title'] !== '' ? $slide['title'] : 'Diapositive de briefing', ENT_QUOTES, 'UTF-8') ?>"
                    <?= $i === 0 ? '' : 'loading="lazy"' ?>
                >
                <div class="cap">
                    <h2><?= htmlspecialchars($slide['title'] !== '' ? $slide['title'] : 'Diapositive ' . ($i + 1), ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($slide['detail'] !== ''): ?>
                    <p class="detail"><?= nl2br(htmlspecialchars($slide['detail'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>

        <div class="panels">
            <section class="panel" aria-labelledby="presence-title">
                <h3 id="presence-title">ATAK connectés</h3>
                <ul class="presence-list" id="presence-list">
                    <li>Recherche des appareils connectés…</li>
                </ul>
            </section>
            <section class="panel" aria-labelledby="comments-title">
                <h3 id="comments-title">Commentaires</h3>
                <ul class="comments-list" id="comments-list">
                    <li>Aucun commentaire pour cette diapositive.</li>
                </ul>
                <form class="comment-form" id="comment-form">
                    <label class="sr-only" for="comment-author">Votre indicatif ou prénom</label>
                    <input id="comment-author" name="author_label" maxlength="80" placeholder="Votre indicatif (facultatif)" autocomplete="nickname">
                    <label class="sr-only" for="comment-body">Commentaire</label>
                    <textarea id="comment-body" name="body" maxlength="2000" placeholder="Ajouter un commentaire ou une précision…" required></textarea>
                    <button type="submit">Envoyer le commentaire</button>
                    <p class="flash" id="comment-flash" hidden></p>
                </form>
            </section>
        </div>
    </div>

    <div class="toolbar">
        <button type="button" id="btn-prev" disabled>Précédent</button>
        <div class="idx" id="slide-idx">1 / <?= count($slidesPayload) ?></div>
        <button type="button" class="next" id="btn-next"<?= count($slidesPayload) < 2 ? ' disabled' : '' ?>>Suivant</button>
    </div>
    <?php endif; ?>

<script>
(function () {
  var root = document.querySelector('[data-briefing-deck]');
  if (!root) return;
  var slides = Array.prototype.slice.call(document.querySelectorAll('.slide'));
  if (!slides.length) return;

  var token = root.getAttribute('data-token') || '';
  var presenceUrl = root.getAttribute('data-presence-url') || '';
  var commentsBase = root.getAttribute('data-comments-base') || '';
  var btnPrev = document.getElementById('btn-prev');
  var btnNext = document.getElementById('btn-next');
  var idxEl = document.getElementById('slide-idx');
  var presenceChip = document.getElementById('presence-chip');
  var presenceList = document.getElementById('presence-list');
  var commentsList = document.getElementById('comments-list');
  var commentForm = document.getElementById('comment-form');
  var commentFlash = document.getElementById('comment-flash');
  var authorInput = document.getElementById('comment-author');
  var bodyInput = document.getElementById('comment-body');

  var reduceMotion = false;
  try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

  var index = 0;
  var transitioning = false;
  var clientKey = '';
  try {
    clientKey = localStorage.getItem('comspec_briefing_client') || '';
    if (!clientKey) {
      clientKey = 'ph-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
      localStorage.setItem('comspec_briefing_client', clientKey);
    }
    var savedAuthor = localStorage.getItem('comspec_briefing_author') || '';
    if (savedAuthor && authorInput) authorInput.value = savedAuthor;
  } catch (e) {
    clientKey = 'ph-' + String(Date.now());
  }

  function clamp(i) {
    if (i < 0) return 0;
    if (i > slides.length - 1) return slides.length - 1;
    return i;
  }

  function currentSlideId() {
    var el = slides[index];
    return el ? parseInt(el.getAttribute('data-slide-id') || '0', 10) : 0;
  }

  function syncControls() {
    if (btnPrev) {
      btnPrev.disabled = index <= 0;
    }
    if (btnNext) {
      btnNext.disabled = index >= slides.length - 1;
      btnNext.textContent = index >= slides.length - 1 ? 'Fin' : 'Suivant';
    }
    if (idxEl) {
      idxEl.textContent = (index + 1) + ' / ' + slides.length;
    }
  }

  function goTo(target) {
    var next = clamp(target);
    if (next === index || transitioning) return;
    var prev = index;
    var prevEl = slides[prev];
    var nextEl = slides[next];
    if (!nextEl) return;
    transitioning = !reduceMotion;
    index = next;
    slides.forEach(function (el, i) {
      var active = i === next;
      el.classList.toggle('is-active', active);
      el.classList.toggle('is-exit', !active && i === prev && !reduceMotion);
      el.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    syncControls();
    loadComments();
    if (reduceMotion) {
      transitioning = false;
      if (prevEl) prevEl.classList.remove('is-exit');
      return;
    }
    window.setTimeout(function () {
      transitioning = false;
      slides.forEach(function (el) { el.classList.remove('is-exit'); });
    }, 420);
  }

  if (btnPrev) btnPrev.addEventListener('click', function () { goTo(index - 1); });
  if (btnNext) btnNext.addEventListener('click', function () { goTo(index + 1); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowRight' || ev.key === 'PageDown') { ev.preventDefault(); goTo(index + 1); }
    if (ev.key === 'ArrowLeft' || ev.key === 'PageUp') { ev.preventDefault(); goTo(index - 1); }
  });

  var touchX = null;
  document.addEventListener('touchstart', function (ev) {
    if (!ev.changedTouches || !ev.changedTouches[0]) return;
    touchX = ev.changedTouches[0].clientX;
  }, { passive: true });
  document.addEventListener('touchend', function (ev) {
    if (touchX === null || !ev.changedTouches || !ev.changedTouches[0]) return;
    var dx = ev.changedTouches[0].clientX - touchX;
    touchX = null;
    if (Math.abs(dx) < 48) return;
    if (dx < 0) goTo(index + 1);
    else goTo(index - 1);
  }, { passive: true });

  function withToken(url) {
    if (!token) return url;
    return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'token=' + encodeURIComponent(token);
  }

  function renderPresence(data) {
    var viewers = (data && data.viewers) || [];
    var count = typeof data.count === 'number' ? data.count : viewers.length;
    if (presenceChip) presenceChip.textContent = 'ATAK : ' + count;
    if (!presenceList) return;
    presenceList.innerHTML = '';
    if (!viewers.length) {
      var empty = document.createElement('li');
      empty.textContent = 'Aucun autre appareil connecté pour le moment.';
      presenceList.appendChild(empty);
      return;
    }
    viewers.forEach(function (v) {
      var li = document.createElement('li');
      var label = (v && v.label) ? String(v.label) : 'Opérateur';
      var source = (v && v.source) ? String(v.source) : 'phone';
      var srcLabel = source === 'arma' ? 'tableau en jeu' : (source === 'admin' ? 'poste de commandement' : 'téléphone');
      li.textContent = label + ' · ' + srcLabel;
      presenceList.appendChild(li);
    });
  }

  function heartbeatPresence() {
    if (!presenceUrl || !token) return;
    var label = (authorInput && authorInput.value.trim()) || 'Téléphone';
    fetch(withToken(presenceUrl), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        token: token,
        client_key: clientKey,
        label: label,
        source: 'phone'
      })
    }).then(function (r) { return r.json(); }).then(renderPresence).catch(function () {});
  }

  function loadComments() {
    var slideId = currentSlideId();
    if (!commentsList || !commentsBase || !token || !slideId) return;
    fetch(withToken(commentsBase + '/' + slideId + '/comments'), {
      headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (data) {
      var comments = (data && data.comments) || [];
      commentsList.innerHTML = '';
      if (!comments.length) {
        var empty = document.createElement('li');
        empty.textContent = 'Aucun commentaire pour cette diapositive.';
        commentsList.appendChild(empty);
        return;
      }
      comments.forEach(function (c) {
        var li = document.createElement('li');
        var strong = document.createElement('strong');
        strong.textContent = (c && c.author) ? String(c.author) : 'Opérateur';
        li.appendChild(strong);
        if (c && c.created_at) {
          var time = document.createElement('time');
          time.textContent = String(c.created_at);
          li.appendChild(time);
        }
        li.appendChild(document.createTextNode(' — ' + ((c && c.body) ? String(c.body) : '')));
        commentsList.appendChild(li);
      });
    }).catch(function () {});
  }

  function showFlash(msg, isErr) {
    if (!commentFlash) return;
    commentFlash.hidden = false;
    commentFlash.textContent = msg;
    commentFlash.classList.toggle('is-err', !!isErr);
  }

  if (commentForm) {
    commentForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var slideId = currentSlideId();
      var body = bodyInput ? bodyInput.value.trim() : '';
      var author = authorInput ? authorInput.value.trim() : '';
      if (!body || !slideId || !token) return;
      try { if (author) localStorage.setItem('comspec_briefing_author', author); } catch (e) {}
      fetch(withToken(commentsBase + '/' + slideId + '/comments'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          token: token,
          body: body,
          author_label: author || 'Opérateur',
          source: 'phone'
        })
      }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
          if (!res.ok) {
            showFlash((res.data && res.data.message) || 'Envoi impossible pour le moment.', true);
            return;
          }
          if (bodyInput) bodyInput.value = '';
          showFlash('Commentaire publié.', false);
          loadComments();
          heartbeatPresence();
        })
        .catch(function () { showFlash('Réseau indisponible. Réessayez.', true); });
    });
  }

  syncControls();
  heartbeatPresence();
  loadComments();
  window.setInterval(heartbeatPresence, 20000);
})();
</script>
</body>
</html>
