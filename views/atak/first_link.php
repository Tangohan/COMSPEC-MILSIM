<?php
declare(strict_types=1);

$fl = is_array($firstLink ?? null) ? $firstLink : [];

$accountReady = !empty($fl['account_ready']);
$steamLinked = !empty($fl['steam_linked']);
$hasIdentity = !empty($fl['has_identity']);
$hasMod = !empty($fl['has_mod']);
$gameLinkReady = !empty($fl['game_link_ready']);
$canViewOperators = !empty($fl['can_view_operators']);
$hasAccessKey = !empty($fl['has_access_key']);

$displayName = (string) ($fl['display_name'] ?? '');
$callsign = (string) ($fl['callsign'] ?? '');
$identityLabel = $callsign !== '' ? $callsign : ($displayName !== '' ? $displayName : 'Non renseigné');

$accountUrl = (string) ($fl['account_url'] ?? url('account/preferences'));
$modPageUrl = (string) ($fl['mod_page_url'] ?? url('atak/mod'));
$modDownloadUrl = $fl['mod_download_url'] ?? null;
$tutoUrl = (string) ($fl['tuto_url'] ?? url('atak/tuto'));
$atakUrl = (string) ($fl['atak_url'] ?? url('atak'));
$operateursUrl = (string) ($fl['operateurs_url'] ?? url('back-office/atak/operateurs'));
$portalUrl = (string) ($fl['portal_url'] ?? rtrim(url(''), '/'));
$gameLinkUrl = (string) ($fl['game_link_url'] ?? url('atak/game-link'));
$guideUrl = (string) ($fl['guide_url'] ?? url('atak/mod/guide'));

$activeIndex = 0;
if ($accountReady) {
    $activeIndex = 1;
}
$steps = [
    ['label' => 'Compte', 'done' => $accountReady, 'active' => $activeIndex === 0],
    ['label' => 'Pack', 'done' => false, 'active' => $activeIndex === 1],
    ['label' => 'Appairer', 'done' => false, 'active' => false],
    ['label' => 'Contrôle', 'done' => false, 'active' => false],
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/atak-first-link.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="at-first-link" data-first-link data-game-link-url="<?= htmlspecialchars($gameLinkUrl, ENT_QUOTES, 'UTF-8') ?>" data-me-link-url="<?= htmlspecialchars(url('api/atak/me-link-status'), ENT_QUOTES, 'UTF-8') ?>">
    <header class="at-first-link__hero">
        <div class="at-first-link__hero-inner">
            <p class="at-first-link__eyebrow">Carte tactique · mise en service</p>
            <h1 class="at-first-link__title">Première liaison</h1>
            <p class="at-first-link__lead">
                Quatre étapes, une seule fois, avant une activité. À la fin, votre compte est relié à Arma
                et votre présence peut apparaître sur la carte partagée.
            </p>
            <div class="at-first-link__progress">
                <?php require base_path('views/partials/ui/stepper.php'); ?>
            </div>
        </div>
    </header>

    <div class="at-first-link__body">

        <aside class="at-first-link__callout at-first-link__callout--ok" style="margin-bottom:1.25rem;">
            <strong>Méthode recommandée :</strong> compte Athena + pack Overwatch + code <strong>Appairer</strong>.
            Vous n’avez en général <em>pas</em> besoin de coller une clé technique dans le jeu :
            le code Appairer configure la liaison pour vous.
        </aside>

        <section class="at-first-link__step<?= $accountReady ? ' is-done' : ' is-active' ?>" id="fl-step-compte" aria-labelledby="fl-compte-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true"><?= $accountReady ? '✓' : '1' ?></span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 1</p>
                    <h2 id="fl-compte-title" class="at-first-link__step-title">Préparer votre compte</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    La carte reconnaît les opérateurs via le compte portail. Sans Steam et sans nom / indicatif,
                    le jeu ne pourra pas vous rattacher correctement.
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
                <p class="at-first-link__callout at-first-link__callout--ok">Compte prêt pour Appairer.</p>
                <?php else: ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Complétez votre fiche, puis revenez ici : l’étape se mettra à jour.
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
                    Le pack relie Arma au poste (positions, marqueurs, téléphone Athena). Installez-le une fois,
                    puis activez-le à chaque session <strong>après CBA</strong>.
                </p>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Arma 3 à jour et module <strong>CBA</strong> activé.</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark<?= $hasMod ? ' is-ok' : '' ?>" aria-hidden="true"><?= $hasMod ? '✓' : '' ?></span>
                        <span>
                            <?php if ($hasMod): ?>
                                Télécharger le pack publié par votre communauté.
                            <?php else: ?>
                                Demander à un administrateur de publier le pack (aucun fichier pour l’instant).
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Extraire l’archive, activer <strong>Overwatch</strong> dans le lanceur, derrière CBA. Quitter Arma complètement après chaque mise à jour.</span>
                    </li>
                </ul>
                <div class="at-first-link__actions">
                    <?php if ($hasMod && $modDownloadUrl): ?>
                    <a class="at-first-link__btn at-first-link__btn--mint" href="<?= htmlspecialchars((string) $modDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger le pack</a>
                    <?php endif; ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($modPageUrl, ENT_QUOTES, 'UTF-8') ?>">Page du pack</a>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>">Guide d’installation</a>
                </div>
                <?php if (!$hasMod): ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Sans pack publié, préparez le compte et le code Appairer ; l’installation suivra dès que l’équipe aura déposé le fichier.
                </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="at-first-link__step" id="fl-step-liaison" aria-labelledby="fl-liaison-title">
            <div class="at-first-link__step-head">
                <span class="at-first-link__badge" aria-hidden="true">3</span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 3</p>
                    <h2 id="fl-liaison-title" class="at-first-link__step-title">Appairer le jeu (code)</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    Générez un code ici (ou sur la carte via <strong>Appairer</strong>). Dans Arma, ouvrez le téléphone,
                    application <strong>Athena</strong>, puis collez uniquement ce code. Ne collez pas l’adresse du site dans le champ code.
                </p>
                <ol class="at-first-link__checklist" style="list-style:decimal;padding-left:1.2rem;">
                    <li style="display:list-item;">Lancez Arma avec Overwatch activé.</li>
                    <li style="display:list-item;">Ouvrez le téléphone ATAK → application <strong>Athena</strong>.</li>
                    <li style="display:list-item;">Collez le code généré ci-dessous, puis validez (Lier).</li>
                    <li style="display:list-item;">Si le compte est déjà reconnu : bouton <strong>Entrer</strong> pour rouvrir le canal poste.</li>
                </ol>

                <p class="at-first-link__copy" style="margin-top:0.9rem;margin-bottom:0.35rem;">
                    Autres possibilités (si votre communauté les autorise) :
                </p>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span><strong>Steam</strong> — si votre Steam est déjà sur la fiche, essayez le bouton Steam dans Athena.</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span><strong>E-mail / mot de passe</strong> — connexion Athena dans le même panneau du téléphone.</span>
                    </li>
                </ul>

                <?php if ($portalUrl !== ''): ?>
                <p class="at-first-link__copy" style="margin-top:0.85rem;margin-bottom:0;">
                    Adresse du portail (réglage avancé, rarement nécessaire si Appairer a réussi) :
                </p>
                <div class="at-first-link__copy-row">
                    <pre id="fl-portal-url"><?= htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8') ?></pre>
                    <button type="button" class="at-first-link__btn at-first-link__btn--ghost" data-fl-copy="fl-portal-url">Copier</button>
                </div>
                <p class="at-first-link__copy" style="margin-top:0.4rem;font-size:0.92em;opacity:0.9;">
                    Si besoin : téléphone → <strong>Paramètres</strong> → rubrique <strong>Liaison au poste</strong> → coller l’adresse, puis Enregistrer la liaison.
                </p>
                <?php endif; ?>

                <?php if (!$hasAccessKey): ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    Votre communauté n’a pas encore de clé d’accès. Un administrateur doit en générer une dans
                    Configuration ATAK pour que la liaison jeu fonctionne pleinement.
                </p>
                <?php endif; ?>

                <?php if ($gameLinkReady): ?>
                <div class="at-first-link__actions" style="margin-top:1rem;">
                    <button type="button" class="at-first-link__btn at-first-link__btn--mint" id="fl-game-link-btn">
                        Générer un code Appairer
                    </button>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir Appairer sur la carte</a>
                </div>
                <div class="at-first-link__code-box" id="fl-game-link-result" hidden>
                    <p class="at-first-link__code-label">Code à coller dans Athena (Arma)</p>
                    <p class="at-first-link__code" id="fl-game-link-code">————</p>
                    <p class="at-first-link__code-meta" id="fl-game-link-meta"></p>
                    <button type="button" class="at-first-link__btn at-first-link__btn--ghost" id="fl-game-link-copy">Copier le code</button>
                </div>
                <p class="at-first-link__error" id="fl-game-link-error" hidden></p>
                <?php else: ?>
                <p class="at-first-link__callout at-first-link__callout--warn">
                    La génération de code n’est pas encore disponible sur ce serveur. Demandez à un administrateur
                    d’activer la liaison jeu, ou utilisez Steam / e-mail dans Athena.
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
                <span class="at-first-link__badge" aria-hidden="true" id="fl-controle-badge">4</span>
                <div>
                    <p class="at-first-link__step-kicker">Étape 4</p>
                    <h2 id="fl-controle-title" class="at-first-link__step-title">Contrôler votre présence</h2>
                </div>
            </div>
            <div class="at-first-link__step-body">
                <p class="at-first-link__copy">
                    Une fois en mission (ou en éditeur avec le pack), vérifiez ici si le poste vous voit.
                    Bougez un peu en jeu, puis actualisez.
                </p>
                <div class="at-first-link__callout" id="fl-presence-status" role="status" aria-live="polite">
                    Cliquez sur « Vérifier ma présence » après Appairer et quelques secondes en jeu.
                </div>
                <ul class="at-first-link__checklist">
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Être en jeu avec Overwatch actif et le canal poste ouvert (Athena prêt / Entrer).</span>
                    </li>
                    <li>
                        <span class="at-first-link__mark" aria-hidden="true"></span>
                        <span>Attendre jusqu’à une minute et se déplacer un peu.</span>
                    </li>
                </ul>
                <div class="at-first-link__actions">
                    <button type="button" class="at-first-link__btn at-first-link__btn--mint" id="fl-presence-btn">Vérifier ma présence</button>
                    <a class="at-first-link__btn at-first-link__btn--primary" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir la carte</a>
                    <?php if ($canViewOperators): ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($operateursUrl, ENT_QUOTES, 'UTF-8') ?>">Voir les opérateurs en liaison</a>
                    <?php endif; ?>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars(url('equipment'), ENT_QUOTES, 'UTF-8') ?>">Mes tenues</a>
                    <a class="at-first-link__btn at-first-link__btn--ghost" href="<?= htmlspecialchars($tutoUrl, ENT_QUOTES, 'UTF-8') ?>">Guide détaillé</a>
                </div>
                <p class="at-first-link__callout at-first-link__callout--ok">
                    Si vous n’apparaissez pas : vérifiez Steam, générez un <strong>nouveau</strong> code Appairer,
                    quittez Arma complètement, puis réessayez.
                </p>
            </div>
        </section>

        <p class="at-first-link__footer">
            Aller plus loin :
            <a href="<?= htmlspecialchars($tutoUrl, ENT_QUOTES, 'UTF-8') ?>">Guide connexion &amp; clé</a>
            ·
            <a href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>">Guide du pack</a>
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

  if (btn && gameLinkUrl) {
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
          btn.textContent = 'Générer un code Appairer';
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
            || 'Dans Arma : téléphone → Athena → coller ce code (pas l’adresse du site). Valable environ 30 minutes.';
        }
        if (resultEl) resultEl.hidden = false;
      })
      .catch(function () {
        busy = false;
        btn.disabled = false;
        btn.textContent = 'Générer un code Appairer';
        showError('Réseau indisponible. Réessayez dans un instant.');
      });
  });
  }
  var meLinkUrl = root.getAttribute('data-me-link-url') || '';
  var presenceBtn = document.getElementById('fl-presence-btn');
  var presenceEl = document.getElementById('fl-presence-status');
  var presenceBadge = document.getElementById('fl-controle-badge');
  var presenceBusy = false;

  function setPresenceUi(visible, message) {
    if (!presenceEl) return;
    presenceEl.textContent = message || '';
    presenceEl.classList.remove('at-first-link__callout--ok', 'at-first-link__callout--warn');
    presenceEl.classList.add(visible ? 'at-first-link__callout--ok' : 'at-first-link__callout--warn');
    if (presenceBadge) {
      presenceBadge.textContent = visible ? '✓' : '4';
    }
    var step = document.getElementById('fl-step-controle');
    if (step) {
      if (visible) step.classList.add('is-done');
      else step.classList.remove('is-done');
    }
  }

  function checkPresence() {
    if (!meLinkUrl || presenceBusy) return;
    presenceBusy = true;
    if (presenceBtn) {
      presenceBtn.disabled = true;
      presenceBtn.textContent = 'Vérification…';
    }
    fetch(meLinkUrl, { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.text().then(function (raw) {
          var j = null;
          try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
          return { ok: r.ok, body: j };
        });
      })
      .then(function (res) {
        presenceBusy = false;
        if (presenceBtn) {
          presenceBtn.disabled = false;
          presenceBtn.textContent = 'Vérifier ma présence';
        }
        if (!res.body) {
          setPresenceUi(false, 'Impossible de vérifier pour le moment.');
          return;
        }
        setPresenceUi(!!res.body.visible, res.body.message || (res.body.visible ? 'Visible.' : 'Pas encore visible.'));
      })
      .catch(function () {
        presenceBusy = false;
        if (presenceBtn) {
          presenceBtn.disabled = false;
          presenceBtn.textContent = 'Vérifier ma présence';
        }
        setPresenceUi(false, 'Réseau indisponible. Réessayez dans un instant.');
      });
  }

  if (presenceBtn) {
    presenceBtn.addEventListener('click', checkPresence);
  }
})();
</script>
