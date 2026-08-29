<?php
declare(strict_types=1);

/**
 * Parcours guidé post-acceptation — intégration du nouveau membre.
 *
 * @var array<string,mixed> $enlistment
 * @var array<string,mixed> $onboardingPrefill
 * @var list<array<string,mixed>> $orgRoles
 * @var list<array<string,mixed>> $units
 * @var list<array{id:int,label:string}>|list<array<string,mixed>> $jobRoleOptions
 * @var array<string,string> $clearanceLevels
 * @var array<string,mixed>|null $linkedRecruitmentOpening
 * @var array<string,mixed>|null $linkedUser
 * @var bool $needsAcceptanceOnboarding
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$e = is_array($enlistment ?? null) ? $enlistment : [];
$id = (int) ($e['id'] ?? 0);
$prefill = is_array($onboardingPrefill ?? null) ? $onboardingPrefill : [];
$orgRoles = is_array($orgRoles ?? null) ? $orgRoles : [];
$units = is_array($units ?? null) ? $units : [];
$jobRoleOptions = is_array($jobRoleOptions ?? null) ? $jobRoleOptions : [];
$clearanceLevels = is_array($clearanceLevels ?? null) ? $clearanceLevels : [];
$linkedOpening = is_array($linkedRecruitmentOpening ?? null) ? $linkedRecruitmentOpening : null;
$linkedUser = is_array($linkedUser ?? null) ? $linkedUser : null;
$needsOnboarding = !empty($needsAcceptanceOnboarding);

$selectedRoleIds = [];
foreach ((array) ($prefill['role_ids'] ?? []) as $rid) {
    $rid = (int) $rid;
    if ($rid > 0) {
        $selectedRoleIds[$rid] = $rid;
    }
}

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');
$flashInfo = \App\Core\Session::getFlash('info');

$candidateLabel = trim(trim((string) ($prefill['first_name'] ?? '')) . ' ' . trim((string) ($prefill['last_name'] ?? '')));
if ($candidateLabel === '') {
    $candidateLabel = 'Candidat #' . $id;
}

$dossierUrl = url('back-office/recruitments/' . $id . '?dossier=1');
$formAction = url('back-office/recruitments/' . $id . '/onboarding');
?>
<div class="rec-onb" data-rec-onb>
    <header class="rec-onb__hero">
        <p class="rec-onb__kicker">Après acceptation</p>
        <h1 class="rec-onb__title">Intégrer <?= $h($candidateLabel) ?></h1>
        <p class="rec-onb__lead">
            Quatre étapes simples : identité du personnage, lien Steam (pour remonter les données Arma),
            droits d’accès, puis affectation. Tout est expliqué — vous pouvez revenir en arrière avant de valider.
        </p>
        <?php if ($linkedOpening): ?>
        <p class="rec-onb__offer">
            Offre liée :
            <strong><?= $h((string) ($linkedOpening['title'] ?? 'Avis de vacance')) ?></strong>
            <?php if (trim((string) ($linkedOpening['reference_public'] ?? '')) !== ''): ?>
                · réf. <?= $h((string) $linkedOpening['reference_public']) ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>
        <?php if (!$needsOnboarding): ?>
        <p class="rec-onb__note" role="status">
            L’intégration a déjà été finalisée. Vous pouvez encore corriger les informations ci-dessous puis réenregistrer.
        </p>
        <?php endif; ?>
    </header>

    <?php if ($flashError): ?>
    <p class="rec-onb__flash rec-onb__flash--err" role="alert"><?= $h((string) $flashError) ?></p>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <p class="rec-onb__flash rec-onb__flash--ok" role="status"><?= $h((string) $flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
    <p class="rec-onb__flash rec-onb__flash--info" role="status"><?= $h((string) $flashInfo) ?></p>
    <?php endif; ?>

    <nav class="rec-onb__steps" aria-label="Étapes d’intégration">
        <?php
        $steps = [
            1 => 'Identité',
            2 => 'Steam & Arma',
            3 => 'Droits d’accès',
            4 => 'Affectation',
        ];
        foreach ($steps as $n => $label):
        ?>
        <button type="button" class="rec-onb__step<?= $n === 1 ? ' is-active' : '' ?>" data-rec-onb-tab="<?= (int) $n ?>">
            <span class="rec-onb__step-num"><?= (int) $n ?></span>
            <span class="rec-onb__step-label"><?= $h($label) ?></span>
        </button>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="<?= $h($formAction) ?>" class="rec-onb__form" id="rec-onb-form" novalidate>
        <?= \App\Core\Csrf::field() ?>

        <section class="rec-onb__panel is-active" data-rec-onb-panel="1">
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 1 sur 4</p>
                <h2 class="rec-onb__panel-title">Identité du personnage</h2>
                <p class="rec-onb__panel-text">
                    Un seul couple prénom + nom : c’est le nom du personnage. Il sert partout (dossier, annuaire, ATAK).
                </p>
            </div>
            <div class="rec-onb__grid">
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Prénom <abbr title="obligatoire">*</abbr></span>
                    <input type="text" name="first_name" required maxlength="100" value="<?= $h((string) ($prefill['first_name'] ?? '')) ?>" placeholder="Jean" autocomplete="off">
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Nom <abbr title="obligatoire">*</abbr></span>
                    <input type="text" name="last_name" required maxlength="100" value="<?= $h((string) ($prefill['last_name'] ?? '')) ?>" placeholder="Dupont" autocomplete="off">
                </label>
                <label class="rec-onb__field rec-onb__field--full">
                    <span class="rec-onb__label">Indicatif radio <span class="rec-onb__opt">(optionnel)</span></span>
                    <input type="text" name="callsign" maxlength="64" value="<?= $h((string) ($prefill['callsign'] ?? '')) ?>" placeholder="Ex. Ghost-1" autocomplete="off">
                    <span class="rec-onb__hint">Utilisé en radio / carte. Laissez vide si inconnu.</span>
                </label>
            </div>
            <div class="rec-onb__nav">
                <a class="rec-onb__btn rec-onb__btn--ghost" href="<?= $h($dossierUrl) ?>">Retour au dossier</a>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="2" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 2 sur 4</p>
                <h2 class="rec-onb__panel-title">Lien Steam & données Arma</h2>
                <p class="rec-onb__panel-text">
                    Collez l’URL du profil Steam du candidat. Une fois lié, les sessions Arma / ATAK peuvent
                    remonter automatiquement (temps de jeu, présence en mission) dès que le joueur se connecte en jeu.
                </p>
            </div>
            <div class="rec-onb__callout">
                <p><strong>Comment trouver l’URL ?</strong></p>
                <ol>
                    <li>Ouvrir le profil Steam du candidat (navigateur ou client Steam).</li>
                    <li>Copier l’adresse : <code>steamcommunity.com/profiles/…</code> ou <code>/id/pseudo</code>.</li>
                    <li>La coller ci-dessous. Le SteamID64 est aussi accepté.</li>
                </ol>
            </div>
            <label class="rec-onb__field rec-onb__field--full">
                <span class="rec-onb__label">URL du profil Steam <span class="rec-onb__opt">(fortement recommandé)</span></span>
                <input type="url" name="steam_profile" maxlength="255" value="<?= $h((string) ($prefill['steam_profile'] ?? '')) ?>" placeholder="https://steamcommunity.com/profiles/76561198…" inputmode="url" autocomplete="off">
                <span class="rec-onb__hint">Sans Steam, le membre ne pourra pas être reconnu automatiquement dans Arma / ATAK.</span>
            </label>
            <?php if (is_array($linkedUser) && trim((string) ($linkedUser['steam_id'] ?? '')) !== ''): ?>
            <p class="rec-onb__note">Steam déjà enregistré sur le compte : <?= $h((string) $linkedUser['steam_id']) ?></p>
            <?php endif; ?>
            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="3" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 3 sur 4</p>
                <h2 class="rec-onb__panel-title">Rôles et droits d’accès</h2>
                <p class="rec-onb__panel-text">
                    Les rôles définissent ce que le membre peut voir et faire dans la communauté
                    (documents, recrutement, administration…). Cochez tous ceux qui s’appliquent.
                    « Membre » suffit pour un engagement classique.
                </p>
            </div>
            <?php if ($orgRoles === []): ?>
            <p class="rec-onb__note">Aucun rôle organisationnel n’est encore configuré. Le rôle membre sera appliqué par défaut.</p>
            <?php else: ?>
            <fieldset class="rec-onb__roles">
                <legend class="sr-only">Rôles d’organisation</legend>
                <?php foreach ($orgRoles as $role): ?>
                    <?php
                    $rid = (int) ($role['id'] ?? 0);
                    if ($rid < 1) {
                        continue;
                    }
                    $rname = trim((string) ($role['name'] ?? '')) ?: ('Rôle #' . $rid);
                    $rslug = trim((string) ($role['slug'] ?? ''));
                    $rdesc = trim((string) ($role['description'] ?? ''));
                    $checked = isset($selectedRoleIds[$rid]);
                    ?>
                <label class="rec-onb__role">
                    <input type="checkbox" name="role_ids[]" value="<?= (int) $rid ?>"<?= $checked ? ' checked' : '' ?>>
                    <span class="rec-onb__role-body">
                        <span class="rec-onb__role-name"><?= $h($rname) ?></span>
                        <?php if ($rslug !== ''): ?>
                        <span class="rec-onb__role-slug"><?= $h($rslug) ?></span>
                        <?php endif; ?>
                        <?php if ($rdesc !== ''): ?>
                        <span class="rec-onb__role-desc"><?= $h($rdesc) ?></span>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <label class="rec-onb__field rec-onb__field--full" style="margin-top:1.25rem">
                <span class="rec-onb__label">Niveau d’habilitation documents <span class="rec-onb__opt">(optionnel)</span></span>
                <select name="clearance_level">
                    <option value="">— Ne pas modifier —</option>
                    <?php foreach ($clearanceLevels as $ck => $clabel): ?>
                    <option value="<?= $h((string) $ck) ?>"<?= ((string) ($prefill['clearance_level'] ?? '')) === (string) $ck ? ' selected' : '' ?>><?= $h((string) $clabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="rec-onb__hint">Contrôle l’accès aux documents classifiés. Laissez vide pour démarrer au niveau par défaut.</span>
            </label>

            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="button" class="rec-onb__btn rec-onb__btn--solid" data-rec-onb-next>Continuer</button>
            </div>
        </section>

        <section class="rec-onb__panel" data-rec-onb-panel="4" hidden>
            <div class="rec-onb__panel-head">
                <p class="rec-onb__eyebrow">Étape 4 sur 4</p>
                <h2 class="rec-onb__panel-title">Affectation (unité & fonction)</h2>
                <p class="rec-onb__panel-text">
                    Placez le membre dans l’organigramme. Les valeurs de l’offre de recrutement sont pré-remplies quand elles existent.
                </p>
            </div>
            <div class="rec-onb__grid">
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Unité</span>
                    <select name="unit_id">
                        <option value="0">— Choisir plus tard —</option>
                        <?php foreach ($units as $u): ?>
                            <?php $uid = (int) ($u['id'] ?? 0); if ($uid < 1) continue; ?>
                        <option value="<?= $uid ?>"<?= (int) ($prefill['unit_id'] ?? 0) === $uid ? ' selected' : '' ?>>
                            <?= $h(trim((string) ($u['name'] ?? '')) ?: ('Unité #' . $uid)) ?>
                            <?php if (trim((string) ($u['code'] ?? '')) !== ''): ?> (<?= $h((string) $u['code']) ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rec-onb__field">
                    <span class="rec-onb__label">Fonction RH</span>
                    <select name="personnel_job_role_id">
                        <option value="0">— Aucune —</option>
                        <?php foreach ($jobRoleOptions as $opt): ?>
                            <?php
                            $jid = (int) ($opt['id'] ?? 0);
                            if ($jid < 1) {
                                continue;
                            }
                            $jlabel = (string) ($opt['label'] ?? $opt['name'] ?? ('Fonction #' . $jid));
                            ?>
                        <option value="<?= $jid ?>"<?= (int) ($prefill['personnel_job_role_id'] ?? 0) === $jid ? ' selected' : '' ?>><?= $h($jlabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="rec-onb__field rec-onb__field--full">
                    <span class="rec-onb__label">Libellé d’affectation <span class="rec-onb__opt">(optionnel)</span></span>
                    <input type="text" name="assignment_label" maxlength="120" value="" placeholder="Ex. Tireur, Chef d’équipe…">
                    <span class="rec-onb__hint">Si vide, le nom de la fonction RH (ou « Membre ») est utilisé.</span>
                </label>
            </div>

            <div class="rec-onb__summary">
                <h3 class="rec-onb__summary-title">Prêt à finaliser</h3>
                <p class="rec-onb__summary-text">
                    En validant, Athena crée ou rattache le compte membre, applique les rôles et l’affectation,
                    lie le Steam, puis envoie les e-mails d’accueil si nécessaire.
                </p>
            </div>

            <div class="rec-onb__nav">
                <button type="button" class="rec-onb__btn rec-onb__btn--ghost" data-rec-onb-prev>Retour</button>
                <button type="submit" class="rec-onb__btn rec-onb__btn--accent">Finaliser l’intégration</button>
            </div>
        </section>
    </form>
</div>
<script>
(function () {
  var root = document.querySelector('[data-rec-onb]');
  if (!root) return;
  var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-rec-onb-tab]'));
  var panels = Array.prototype.slice.call(root.querySelectorAll('[data-rec-onb-panel]'));
  var current = 1;

  function show(step) {
    current = Math.max(1, Math.min(4, step));
    tabs.forEach(function (t) {
      var n = parseInt(t.getAttribute('data-rec-onb-tab'), 10);
      t.classList.toggle('is-active', n === current);
      t.classList.toggle('is-done', n < current);
    });
    panels.forEach(function (p) {
      var n = parseInt(p.getAttribute('data-rec-onb-panel'), 10);
      var on = n === current;
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
  }

  function validateStep(step) {
    if (step !== 1) return true;
    var form = root.querySelector('#rec-onb-form');
    if (!form) return true;
    var first = form.querySelector('[name="first_name"]');
    var last = form.querySelector('[name="last_name"]');
    if (first && !String(first.value || '').trim()) {
      first.focus();
      first.reportValidity && first.reportValidity();
      return false;
    }
    if (last && !String(last.value || '').trim()) {
      last.focus();
      last.reportValidity && last.reportValidity();
      return false;
    }
    return true;
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      var n = parseInt(t.getAttribute('data-rec-onb-tab'), 10);
      if (n > current && !validateStep(current)) return;
      show(n);
    });
  });
  root.querySelectorAll('[data-rec-onb-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!validateStep(current)) return;
      show(current + 1);
    });
  });
  root.querySelectorAll('[data-rec-onb-prev]').forEach(function (btn) {
    btn.addEventListener('click', function () { show(current - 1); });
  });
  show(1);
})();
</script>
