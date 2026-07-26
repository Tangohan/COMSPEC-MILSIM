<?php
declare(strict_types=1);

$fl = is_array($firstLink ?? null) ? $firstLink : [];
$steps = is_array($steps ?? null) ? $steps : [];

$accountReady = !empty($fl['account_ready']);
$steamLinked = !empty($fl['steam_linked']);
$hasIdentity = !empty($fl['has_identity']);
$hasMod = !empty($fl['has_mod']);
$gameLinkReady = !empty($fl['game_link_ready']);
$canViewOperators = !empty($fl['can_view_operators']);

$displayName = (string) ($fl['display_name'] ?? '');
$callsign = (string) ($fl['callsign'] ?? '');
$identityLabel = $callsign !== '' ? $callsign : ($displayName !== '' ? $displayName : 'Non renseigné');

$accountUrl = (string) ($fl['account_url'] ?? url('account/preferences'));
$modPageUrl = (string) ($fl['mod_page_url'] ?? url('atak/mod'));
$modDownloadUrl = $fl['mod_download_url'] ?? null;
$setupUrl = (string) ($fl['setup_url'] ?? url('atak/setup'));
$tutoUrl = (string) ($fl['tuto_url'] ?? url('atak/tuto'));
$atakUrl = (string) ($fl['atak_url'] ?? url('atak'));
$operateursUrl = (string) ($fl['operateurs_url'] ?? url('back-office/atak/operateurs'));
$nodeUrl = trim((string) ($fl['node_url'] ?? ''));
$gameLinkUrl = (string) ($fl['game_link_url'] ?? url('atak/game-link'));

// Stepper dynamique : étape active = première incomplete (pack/liaison/contrôle = progressions guidées côté UI)
$activeIndex = 0;
if ($accountReady) {
    $activeIndex = 1;
}
$stepsForUi = [
    ['label' => 'Compte', 'done' => $accountReady, 'active' => $activeIndex === 0],
    ['label' => 'Pack', 'done' => false, 'active' => $activeIndex === 1],
    ['label' => 'Liaison', 'done' => false, 'active' => false],
    ['label' => 'Contrôle', 'done' => false, 'active' => false],
];
$steps = $stepsForUi;
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/atak-first-link.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="at-first-link" data-first-link data-game-link-url="<?= htmlspecialchars($gameLinkUrl, ENT_QUOTES, 'UTF-8') ?>">
    <header class="at-first-link__hero">
        <div class="at-first-link__hero-inner">
            <p class="at-first-link__eyebrow">Carte tactique · mise en service</p>
            <h1 class="at-first-link__title">Première liaison</h1>
            <p class="at-first-link__lead">
                Suivez ce parcours une fois, au calme, avant une activité. À la fin, votre compte Athena sera
                relié au jeu et votre présence pourra apparaître correctement sur la carte partagée.
            </p>
            <div class="at-first-link__progress">
                <?php require base_path('views/partials/ui/stepper.php'); ?>
            </div>
        </div>
    </header>

    <div class="at-first-link__body">

        <section class="at-first-link__step<?= $accountReady ? ' is-done' : ' is-active' ?>" id="fl-step-compte" aria-labelledby="fl-compte-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true"><?= $accountReady ? '✓' : '1' ?></span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 1</p>
                    <h2 id="fl-compte-title" class="at-first-link__step-title">Préparer votre compte Athena</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    La carte reconnaît les opérateurs via le compte portail. Sans identifiant Steam et sans
                    nom / indicatif, la liaison en jeu ne pourra pas vous rattacher proprement.
                </p>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark<?= $steamLinked ? ' is-ok' : '' ?>" aria-hidden="true"><?= $steamLinked ? '✓' : '' ?></span>
                        <span>
                            <strong>Identifiant Steam</strong>
                            — <?= $steamLinked ? 'renseigné sur votre compte.' : 'à renseigner dans vos préférences.' ?>
                        </span>
                    </li>
                    <li>
                        <span class="at-first-link__mark<?= $hasIdentity ? ' is-ok' : '' ?>" aria-hidden="true"><?= $hasIdentity ? '✓' : '' ?></span>
                        <span>
                            <strong>Nom ou indicatif</strong>
                            — actuellement : <?= htmlspecialchars($identityLabel, ENT_QUOTES, 'UTF-8') ?>.
                        </span>
                    </li>
                </ul>
                <?php if ($accountReady): ?>
                <p class="at-first-link__callout at-first-link__callout--ok">Compte prêt pour la liaison.</p>
                <?php else: ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Complétez votre fiche avant de continuer. Revenez ensuite sur cette page : l’étape se mettra à jour.
                </p>
                <?php endif; ?>
                <div class="at-first-link__actions">
                    <a class="at-first-link__btn at-first-link__btn--primary" href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">
                        Ouvrir mes préférences
                    </a>
                </div>
            </div>
        </section>

        <section class="at-first-link__step<?= $accountReady ? ' is-active' : '' ?>" id="fl-step-pack" aria-labelledby="fl-pack-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true">2</span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 2</p>
                    <h2 id="fl-pack-title" class="at-first-link__step-title">Installer le pack Overwatch</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    Le pack relie Arma à Athena (positions, marqueurs, outils de situation). Installez-le
                    une fois, puis activez-le à chaque session avec CBA.
                </p>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Avoir <strong>Arma 3</strong> à jour et le module <strong>CBA</strong> activé.</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark<?= $hasMod ? ' is-ok' : '' ?>" aria-hidden="true"><?= $hasMod ? '✓' : '' ?></span>
                        <span>
                            <?php if ($hasMod): ?>
                                Télécharger le pack publié par votre communauté.
                            <?php else: ?>
                                Demander à un administrateur de publier le pack (aucun fichier disponible pour l’instant).
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Extraire l’archive dans vos mods, activer <strong>Overwatch</strong> dans le lanceur, derrière CBA.</span>
                    </li>
                </ul>
                <div class="at-first-link__actions">
                    <?php if ($hasMod && $modDownloadUrl): ?>
                    <a class="at-first-link__btn at-first-link__btn--mint" href="<?= htmlspecialchars((string) $modDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger le pack</a>
                    <?php endif; ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($modPageUrl, ENT_QUOTES, 'UTF-8') ?>">Page du pack</a>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8') ?>">Assistant d’installation</a>
                </div>
                <?php if (!$hasMod): ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Sans pack publié, vous pouvez tout de même préparer le compte et le code de liaison ;
                    l’installation se fera dès que l’équipe aura déposé le fichier.
                </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="at-first-link__step" id="fl-step-liaison" aria-labelledby="fl-liaison-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true">3</span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 3</p>
                    <h2 id="fl-liaison-title" class="at-first-link__step-title">Relier le jeu à votre compte</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    Deux méthodes : Steam déjà renseigné (souvent suffisant), ou un <strong>code de liaison</strong>
                    à usage unique à saisir en jeu. Le code expire après une trentaine de minutes.
                </p>
                <ol class="at-first-link__checklist" style="list-style:decimal;padding-left:1.2rem;">
                    <li style="display:list-item;">Lancez Arma avec Overwatch activé.</li>
                    <li style="display:list-item;">Ouvrez le panneau de liaison (touche <strong>K</strong> → Compte Athena, ou téléphone ATAK Enhanced → Connexion Athena).</li>
                    <li style="display:list-item;">Saisissez le code ci-dessous, puis validez.</li>
                </ol>

                <?php if ($nodeUrl !== ''): ?>
                <p class="at-first-link__copy" style="margin-top:0.85rem;margin-bottom:0;">
                    Si le mod demande une adresse de serveur de liaison, utilisez celle-ci :
                </p>
                <div class="at-first-link__copy-row">
                    <pre id="fl-node-url"><?= htmlspecialchars($nodeUrl, ENT_QUOTES, 'UTF-8') ?></pre>
                    <button type="button" class="at-first-link__btn at-first-link__btn--ghost" data-fl-copy="fl-node-url">Copier</button>
                </div>
                <?php endif; ?>

                <?php if ($gameLinkReady): ?>
                <div class="at-first-link__actions" style="margin-top:1rem;">
                    <button type="button" class="at-first-link__btn at-first-link__btn--mint" id="fl-game-link-btn">
                        Générer un code de liaison
                    </button>
                </div>
                <div class="at-first-link__code-box" id="fl-game-link-result" hidden>
                    <p class="at-first-link__code-label">Votre code</p>
                    <p class="at-first-link__code" id="fl-game-link-code">————</p>
                    <p class="at-first-link__code-meta" id="fl-game-link-meta"></p>
                    <button type="button" class="at-first-link__btn at-first-link__btn--ghost" id="fl-game-link-copy">Copier le code</button>
                </div>
                <p class="at-first-link__error" id="fl-game-link-error" hidden></p>
                <?php else: ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    La génération de code n’est pas encore disponible sur ce serveur. Utilisez l’identifiant Steam
                    de votre compte, ou demandez à un administrateur d’activer la liaison jeu.
                </p>
                <?php endif; ?>

                <?php if (!$accountReady): ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Terminez d’abord l’étape compte : sans Steam, le rattachement risque d’échouer.
                </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="at-first-link__step" id="fl-step-controle" aria-labelledby="fl-controle-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true">4</span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 4</p>
                    <h2 id="fl-controle-title" class="at-first-link__step-title">Contrôler votre présence sur la carte</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    Une fois en mission (ou en éditeur avec le pack), ouvrez la carte dans le navigateur et
                    vérifiez que votre indicatif apparaît. Faites ce contrôle avant une activité réelle.
                </p>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Ouvrir la carte Athena dans un onglet du navigateur.</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Être en jeu avec Overwatch actif et la liaison validée.</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Confirmer que votre marqueur / indicatif est visible (après quelques secondes).</span>
                    </li>
                </ul>
                <div class="at-first-link__actions">
                    <a class="at-first-link__btn at-first-link__btn--primary" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir la carte</a>
                    <?php if ($canViewOperators): ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($operateursUrl, ENT_QUOTES, 'UTF-8') ?>">Voir les opérateurs en liaison</a>
                    <?php endif; ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($tutoUrl, ENT_QUOTES, 'UTF-8') ?>">Tutoriel détaillé du pack</a>
                </div>
                <p class="at-first-link__callout at-first-link__callout--ok">
                    Si vous n’apparaissez pas : vérifiez Steam, le code de liaison, et que le pack est bien activé.
                    Signalez le problème à l’encadrement plutôt que de laisser un marqueur incohérent.
                </p>
            </div>
        </section>

        <p class="at-first-link__footer">
            Besoin d’aller plus loin ?
            <a href="<?= htmlspecialchars($setupUrl, ENT_QUOTES, 'UTF-8') ?>">Assistant d’installation</a>
            ·
            <a href="<?= htmlspecialchars($tutoUrl, ENT_QUOTES, 'UTF-8') ?>">Tutoriel technique</a>
            ·
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Tableau de bord</a>
        </p>
    </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-first-link]');
  if (!root) return;

  var gameLinkUrl = root.getAttribute('data-game-link-url') || '';
  var btn = document.getElementById('fl-game-link-btn');
  var resultEl = document.getElementById('fl-game-link-result');
  var codeEl = document.getElementById('fl-game-link-code');
  var metaEl = document.getElementById('fl-game-link-meta');
  var errEl = document.getElementById('fl-game-link-error');
  var copyBtn = document.getElementById('fl-game-link-copy');
  var busy = false;

  function showError(msg) {
    if (!errEl) return;
    errEl.textContent = msg || '';
    errEl.hidden = !msg;
  }

  function copyText(text, button) {
    if (!text) return;
    var label = button ? button.textContent : '';
    var done = function () {
      if (!button) return;
      button.textContent = 'Copié';
      setTimeout(function () { button.textContent = label; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {});
      return;
    }
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      done();
    } catch (e) {}
  }

  document.querySelectorAll('[data-fl-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var id = el.getAttribute('data-fl-copy');
      var target = id ? document.getElementById(id) : null;
      if (!target) return;
      copyText((target.textContent || '').trim(), el);
    });
  });

  if (copyBtn && codeEl) {
    copyBtn.addEventListener('click', function () {
      copyText((codeEl.textContent || '').trim(), copyBtn);
    });
  }

  if (!btn || !gameLinkUrl) return;

  btn.addEventListener('click', function () {
    if (busy) return;
    busy = true;
    btn.disabled = true;
    btn.textContent = 'Génération…';
    showError('');

    fetch(gameLinkUrl, {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' }
    })
      .then(function (r) {
        return r.text().then(function (raw) {
          var j = null;
          try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
          return { ok: r.ok, status: r.status, body: j };
        });
      })
      .then(function (res) {
        busy = false;
        btn.disabled = false;
        if (!res.ok || !res.body || !res.body.code) {
          btn.textContent = 'Générer un code de liaison';
          showError(
            (res.body && res.body.message)
              ? res.body.message
              : 'Impossible de générer le code pour le moment.'
          );
          return;
        }
        btn.textContent = 'Générer un nouveau code';
        if (codeEl) codeEl.textContent = res.body.code;
        if (metaEl) {
          metaEl.textContent = res.body.hint
            || 'Dans Arma : touche K → Compte Athena, puis saisissez ce code.';
        }
        if (resultEl) resultEl.hidden = false;
      })
      .catch(function () {
        busy = false;
        btn.disabled = false;
        btn.textContent = 'Générer un code de liaison';
        showError('Réseau indisponible. Réessayez dans un instant.');
      });
  });
})();
</script>
