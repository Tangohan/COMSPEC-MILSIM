<?php
declare(strict_types=1);

use App\Services\Account\ProfileWelcomeService as W;

/*
 * Accueil du profil joueur (/account/bienvenue) : une étape à la fois, avec l'aperçu de la carte d'opérateur
 * qui se remplit au fil des étapes. Chaque action réutilise la page existante (Discord, Steam, portrait,
 * couverture, double vérification, page publique) ; tout peut être passé et repris plus tard.
 */
$user = is_array($user ?? null) ? $user : [];
$welcomeStep = (string) ($welcomeStep ?? W::END);
$welcomeSteps = is_array($welcomeSteps ?? null) ? $welcomeSteps : [];
$welcomeProgress = is_array($welcomeProgress ?? null) ? $welcomeProgress : ['done' => 0, 'skipped' => 0, 'todo' => 0, 'total' => 0, 'percent' => 0];
$welcomeFacts = is_array($welcomeFacts ?? null) ? $welcomeFacts : [];
$welcomeNext = isset($welcomeNext) && is_string($welcomeNext) ? $welcomeNext : null;
$welcomePersonnel = is_array($welcomePersonnel ?? null) ? $welcomePersonnel : [];
$welcomeDiscordLink = is_array($welcomeDiscordLink ?? null) ? $welcomeDiscordLink : null;
$welcomeIsNewMember = !empty($welcomeIsNewMember);
$publicCvUrl = isset($accountPublicCvUrl) && is_string($accountPublicCvUrl) && $accountPublicCvUrl !== '' ? $accountPublicCvUrl : null;

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrf = \App\Core\Csrf::field();
$isEnd = $welcomeStep === W::END;

$current = null;
$previousKey = null;
foreach ($welcomeSteps as $i => $s) {
    if ($s['key'] === $welcomeStep) {
        $current = $s;
        $previousKey = $i > 0 ? $welcomeSteps[$i - 1]['key'] : null;
        break;
    }
}
if ($isEnd && $welcomeSteps !== []) {
    $previousKey = $welcomeSteps[count($welcomeSteps) - 1]['key'];
}
$total = count($welcomeSteps);
$done = (int) $welcomeProgress['done'];
$stepDone = $current !== null && $current['status'] === 'done';

$callsign = trim((string) ($user['callsign'] ?? ''));
$welcomeName = trim((string) ($user['display_name'] ?? ''));
$portraitUrl = !empty($welcomePersonnel['character_portrait_path']) && function_exists('personnel_operator_portrait_url')
    ? personnel_operator_portrait_url($welcomePersonnel)
    : null;
$portraitLocked = !empty($welcomeFacts['portrait_locked']);
$bannerUrl = isset($welcomeBannerUrl) && is_string($welcomeBannerUrl) && $welcomeBannerUrl !== '' ? $welcomeBannerUrl : null;
$bannerHint = (string) ($welcomeBannerHint ?? 'JPG, PNG ou WebP, format paysage conseillé.');
$discordName = $welcomeDiscordLink !== null
    ? trim((string) ($welcomeDiscordLink['discord_username'] ?? '')) ?: 'votre compte'
    : null;
$discordOk = \App\Core\Session::getFlash('discord_link_success');
$discordErr = \App\Core\Session::getFlash('discord_link_error');

$go = static fn (string $step): string => url('account/bienvenue/aller/' . rawurlencode($step));
$stepUrl = static fn (string $step): string => \App\Services\Account\ProfileWelcomeJourney::stepUrl($step);

$icon = static fn (string $path): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
$check = '<path d="M5 12.5l4.5 4.5L19 7.5"/>';

/* Badges de la carte d'opérateur : ce que les autres verront, ou ce qui protège le compte. */
$badges = [
    ['on' => !empty($welcomeFacts['discord_linked']), 'label' => 'Discord', 'show' => !empty($welcomeFacts['discord_available']) || !empty($welcomeFacts['discord_linked'])],
    ['on' => !empty($welcomeFacts['steam_linked']), 'label' => 'Steam', 'show' => true],
    ['on' => !empty($welcomeFacts['second_factor']), 'label' => 'Double vérification', 'show' => true],
    ['on' => !empty($welcomeFacts['public_page']), 'label' => 'Page publique', 'show' => true],
];

$accountNavKey = 'welcome';
$accountTitle = $welcomeIsNewMember && $done === 0 ? 'Bienvenue' : 'Compléter mon profil';
$accountLead = 'Quelques étapes pour qu’on vous reconnaisse en jeu, sur Discord et sur le portail. Passez celles qui ne vous concernent pas : vous pourrez y revenir depuis Mon compte.';
$accountHideDefaultActions = true;
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<div class="ah-wel">

    <nav class="ah-wel__rail" aria-label="Étapes de l’accueil">
        <p class="ah-wel__rail-count">
            <?php if ($isEnd): ?>
            <strong>Récapitulatif</strong>
            <?php else: ?>
            <span>Étape <strong><?= (int) ($current['number'] ?? 1) ?></strong> sur <?= $total ?></span>
            <?php endif; ?>
            <span><?= $done ?> faite<?= $done > 1 ? 's' : '' ?></span>
        </p>
        <span class="ah-wel__meter" role="progressbar" aria-label="Profil complété" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $welcomeProgress['percent'] ?>">
            <span style="width:<?= (int) $welcomeProgress['percent'] ?>%"></span>
        </span>
        <ol class="ah-wel__steps">
            <?php foreach ($welcomeSteps as $s): ?>
            <?php $isCurrent = $s['key'] === $welcomeStep; ?>
            <li class="ah-wel__dot is-<?= $h($s['status']) ?><?= $isCurrent ? ' is-current' : '' ?>">
                <a href="<?= $h($stepUrl($s['key'])) ?>"<?= $isCurrent ? ' aria-current="step"' : '' ?>>
                    <span class="ah-wel__dot-mark" aria-hidden="true">
                        <?php if ($s['status'] === 'done'): ?><?= $icon($check) ?><?php else: ?><?= (int) $s['number'] ?><?php endif; ?>
                    </span>
                    <span class="ah-wel__dot-label"><?= $h($s['short']) ?></span>
                    <span class="account-hub__sr"><?= $s['status'] === 'done' ? ' : fait' : ($s['status'] === 'skipped' ? ' : passé' : ' : à faire') ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="ah-wel__grid">
        <section class="ah-wel__panel" aria-labelledby="welcome-step-title">
        <?php if ($isEnd): ?>
            <h2 id="welcome-step-title" class="ah-wel__title"><?= $done === $total ? 'Votre profil est complet' : 'Presque terminé' ?></h2>
            <p class="ah-wel__lead">
                <?= $done === $total
                    ? 'Tout est en place. Vous pouvez modifier chaque réglage plus tard dans Mon compte.'
                    : 'Les étapes restantes ne bloquent rien. Terminez maintenant : la carte « Complétez votre profil » disparaîtra de Mon compte, et chaque réglage restera accessible depuis le menu du compte.' ?>
            </p>
            <ul class="ah-wel__summary">
                <?php foreach ($welcomeSteps as $s): ?>
                <li class="is-<?= $h($s['status']) ?>">
                    <span class="ah-wel__summary-mark" aria-hidden="true"><?= $s['status'] === 'done' ? $icon($check) : $icon('<path d="M6 12h12"/>') ?></span>
                    <span class="ah-wel__summary-text"><?= $h($s['title']) ?></span>
                    <?php if ($s['status'] !== 'done'): ?>
                    <a href="<?= $h($stepUrl($s['key'])) ?>"><?= $s['status'] === 'skipped' ? 'Reprendre' : 'Faire' ?></a>
                    <?php else: ?>
                    <span class="ah-wel__summary-state">Fait</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="ah-wel__actions">
                <form method="post" action="<?= $h(url('account/bienvenue/terminer')) ?>">
                    <?= $csrf ?>
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Terminer l’accueil</button>
                </form>
                <?php if ($previousKey !== null): ?>
                <a class="ah-wel__back" href="<?= $h($stepUrl($previousKey)) ?>">Étape précédente</a>
                <?php endif; ?>
            </div>
        <?php elseif ($current !== null): ?>
            <p class="ah-wel__status is-<?= $h($current['status']) ?>">
                <?= $current['status'] === 'done' ? 'Déjà fait' : ($current['status'] === 'skipped' ? 'Passée la dernière fois' : 'À faire') ?>
            </p>
            <h2 id="welcome-step-title" class="ah-wel__title"><?= $h($current['title']) ?></h2>
            <p class="ah-wel__lead"><?= $h($current['lead']) ?></p>

            <div class="ah-wel__body">
            <?php switch ($welcomeStep):
                case W::STEP_IDENTITY: ?>
                <form method="post" action="<?= $h(url('account/bienvenue/identite')) ?>" class="ah-wel__form" id="welcome-main-form">
                    <?= $csrf ?>
                    <label class="ah-wel__label" for="welcome-callsign">Indicatif</label>
                    <input class="ah-wel__input" type="text" id="welcome-callsign" name="callsign" maxlength="50" autocomplete="nickname"
                           value="<?= $h($callsign) ?>" placeholder="Par exemple : Faucon, Bravo 2-1" data-welcome-callsign required>
                    <p class="ah-wel__help">Court et facile à dire à la radio. Vous pourrez le changer dans Profil et préférences.</p>
                    <?php if ($welcomeName !== ''): ?>
                    <p class="ah-wel__help">Nom affiché : <strong><?= $h($welcomeName) ?></strong>. <a href="<?= $h(url('account/preferences')) ?>#section-profil">Modifier mon nom</a></p>
                    <?php endif; ?>
                </form>
            <?php break;
                case W::STEP_DISCORD: ?>
                <?php if ($discordOk): ?><p class="ah-wel__flash is-ok" role="status"><?= $h($discordOk) ?></p><?php endif; ?>
                <?php if ($discordErr): ?><p class="ah-wel__flash is-err" role="alert"><?= $h($discordErr) ?></p><?php endif; ?>
                <?php if ($discordName !== null): ?>
                <p class="ah-wel__linked"><?= $icon($check) ?> Relié à <strong><?= $h($discordName) ?></strong></p>
                <?php else: ?>
                <ul class="ah-wel__points">
                    <li>Discord vous demande d’autoriser Athena, puis vous ramène ici.</li>
                    <li>Seuls votre pseudo et votre identifiant Discord sont lus.</li>
                    <li>Vous pouvez délier le compte à tout moment dans Profil et préférences.</li>
                </ul>
                <?php endif; ?>
            <?php break;
                case W::STEP_STEAM: ?>
                <?php if (!empty($welcomeFacts['steam_linked'])): ?>
                <p class="ah-wel__linked"><?= $icon($check) ?> Compte Steam relié : le serveur et COMSPEC ATAK vous reconnaissent.</p>
                <?php else: ?>
                <ul class="ah-wel__points">
                    <li>Connectez-vous sur la page officielle de Steam : votre mot de passe Steam ne passe jamais par Athena.</li>
                    <li>Déjà en jeu ? Vous pouvez aussi <a href="<?= $h(url('lier')) ?>">saisir le code affiché par ATAK</a>.</li>
                </ul>
                <?php endif; ?>
            <?php break;
                case W::STEP_PORTRAIT: ?>
                <?php if ($portraitLocked): ?>
                <p class="ah-wel__linked"><?= $icon('<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>') ?> Votre portrait est choisi par un administrateur de la communauté.</p>
                <?php else: ?>
                <form method="post" action="<?= $h(url('account/portrait')) ?>" enctype="multipart/form-data" class="ah-wel__form" id="welcome-main-form">
                    <?= $csrf ?>
                    <input type="hidden" name="retour" value="bienvenue">
                    <label class="ah-wel__drop" for="welcome-portrait">
                        <input type="file" id="welcome-portrait" name="portrait" accept="image/jpeg,image/png,image/webp" data-welcome-file="portrait" required>
                        <span class="ah-wel__drop-icon" aria-hidden="true"><?= $icon('<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"/>') ?></span>
                        <span class="ah-wel__drop-text"><strong><?= $portraitUrl ? 'Choisir une autre photo' : 'Choisir une photo' ?></strong><span data-welcome-filename>Format carré conseillé. JPG, PNG ou WebP, 2 Mo maximum.</span></span>
                    </label>
                </form>
                <?php endif; ?>
            <?php break;
                case W::STEP_BANNER: ?>
                <form method="post" action="<?= $h(url('account/bienvenue/banniere')) ?>" enctype="multipart/form-data" class="ah-wel__form" id="welcome-main-form">
                    <?= $csrf ?>
                    <label class="ah-wel__drop" for="welcome-banner">
                        <input type="file" id="welcome-banner" name="banner" accept="image/jpeg,image/png,image/webp" data-welcome-file="banner" required>
                        <span class="ah-wel__drop-icon" aria-hidden="true"><?= $icon('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 15l5-4 4 3 3-2 6 4"/>') ?></span>
                        <span class="ah-wel__drop-text"><strong><?= $bannerUrl ? 'Choisir une autre image' : 'Choisir une image' ?></strong><span data-welcome-filename><?= $h($bannerHint) ?></span></span>
                    </label>
                </form>
            <?php break;
                case W::STEP_SECURITY: ?>
                <?php if (!empty($welcomeFacts['second_factor'])): ?>
                <p class="ah-wel__linked"><?= $icon($check) ?> Double vérification active sur ce compte.</p>
                <?php else: ?>
                <ul class="ah-wel__points">
                    <li><strong>Application d’authentification</strong> : un code qui change toutes les 30 secondes sur votre téléphone. Le plus sûr.</li>
                    <li><strong>Code par e-mail</strong> : plus simple, aucun outil à installer.</li>
                    <li>Votre mot de passe vous sera demandé pour confirmer.</li>
                </ul>
                <?php endif; ?>
            <?php break;
                case W::STEP_PUBLIC_PAGE: ?>
                <?php if ($publicCvUrl !== null): ?>
                <p class="ah-wel__linked"><?= $icon($check) ?> Votre page publique est en ligne. <a href="<?= $h($publicCvUrl) ?>" target="_blank" rel="noopener">La voir</a></p>
                <?php else: ?>
                <ul class="ah-wel__points">
                    <li>Elle reste fermée tant que vous ne l’ouvrez pas.</li>
                    <li>Vous choisissez chaque bloc affiché : unités, formations, distinctions.</li>
                </ul>
                <?php endif; ?>
            <?php break;
            endswitch; ?>
            </div>

            <div class="ah-wel__actions">
                <?php
                /* Action principale : continuer si l'étape est faite, sinon l'envoi du formulaire ou la page existante. */
                [$primaryKind, $primaryLabel] = $stepDone && $welcomeStep !== W::STEP_IDENTITY ? ['continue', 'Continuer'] : match ($welcomeStep) {
                    W::STEP_IDENTITY => ['form', 'Enregistrer et continuer'],
                    W::STEP_PORTRAIT => $portraitLocked ? ['continue', 'Continuer'] : ['form', 'Enregistrer le portrait'],
                    W::STEP_BANNER => ['form', 'Enregistrer la bannière'],
                    W::STEP_DISCORD => ['go', 'Relier mon Discord'],
                    W::STEP_STEAM => ['go', 'Se connecter avec Steam'],
                    W::STEP_SECURITY => ['go', 'Activer la double vérification'],
                    W::STEP_PUBLIC_PAGE => ['go', 'Préparer ma page publique'],
                    default => ['continue', 'Continuer'],
                };
                ?>
                <?php if ($primaryKind === 'continue'): ?>
                <form method="post" action="<?= $h(url('account/bienvenue/continuer')) ?>">
                    <?= $csrf ?>
                    <input type="hidden" name="etape" value="<?= $h($welcomeStep) ?>">
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Continuer</button>
                </form>
                <?php elseif ($primaryKind === 'form'): ?>
                <button type="submit" form="welcome-main-form" class="account-hub__btn account-hub__btn--primary"><?= $h($primaryLabel) ?></button>
                <?php else: ?>
                <a href="<?= $h($go($welcomeStep)) ?>" class="account-hub__btn account-hub__btn--primary"><?= $h($primaryLabel) ?></a>
                <?php endif; ?>

                <?php if ($primaryKind !== 'continue' && !$stepDone): ?>
                <?php if ($welcomeStep === W::STEP_PUBLIC_PAGE): ?>
                <form method="post" action="<?= $h(url('account/bienvenue/continuer')) ?>">
                    <?= $csrf ?>
                    <input type="hidden" name="etape" value="<?= $h($welcomeStep) ?>">
                    <button type="submit" class="ah-wel__skip">Continuer sans l’ouvrir</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= $h(url('account/bienvenue/passer')) ?>">
                    <?= $csrf ?>
                    <input type="hidden" name="etape" value="<?= $h($welcomeStep) ?>">
                    <button type="submit" class="ah-wel__skip">Passer cette étape</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>

                <span class="ah-wel__actions-end">
                    <?php if ($previousKey !== null): ?>
                    <a class="ah-wel__back" href="<?= $h($stepUrl($previousKey)) ?>">Étape précédente</a>
                    <?php endif; ?>
                    <a class="ah-wel__back" href="<?= $h(url('account')) ?>">Finir plus tard</a>
                </span>
            </div>
        <?php endif; ?>
        </section>

        <aside class="ah-wel__card" aria-label="Aperçu de votre profil">
            <div class="ah-wel__card-banner<?= $bannerUrl ? '' : ' is-empty' ?>" data-welcome-preview="banner">
                <?php if ($bannerUrl): ?><img src="<?= $h($bannerUrl) ?>" alt=""><?php endif; ?>
            </div>
            <div class="ah-wel__card-body">
                <span class="ah-wel__card-portrait<?= $portraitUrl ? '' : ' is-empty' ?>" data-welcome-preview="portrait">
                    <img src="<?= $h($portraitUrl ?: url('assets/images/inconnu.svg')) ?>" alt="">
                </span>
                <p class="ah-wel__card-callsign<?= $callsign === '' ? ' is-empty' : '' ?>" data-welcome-preview="callsign" data-empty="Votre indicatif"><?= $h($callsign !== '' ? $callsign : 'Votre indicatif') ?></p>
                <?php if ($welcomeName !== ''): ?>
                <p class="ah-wel__card-name"><?= $h($welcomeName) ?></p>
                <?php endif; ?>
                <ul class="ah-wel__card-badges">
                    <?php foreach ($badges as $b): if (!$b['show']) { continue; } ?>
                    <li class="<?= $b['on'] ? 'is-on' : '' ?>"><?= $b['on'] ? $icon($check) : '' ?><?= $h($b['label']) ?><span class="account-hub__sr"><?= $b['on'] ? ' : en place' : ' : pas encore' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p class="ah-wel__card-note">Aperçu de ce que les membres de votre communauté verront.</p>
        </aside>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('.ah-wel');
    if (!root) { return; }
    var callsign = root.querySelector('[data-welcome-callsign]');
    var cardCallsign = root.querySelector('[data-welcome-preview="callsign"]');
    if (callsign && cardCallsign) {
        callsign.addEventListener('input', function () {
            var v = callsign.value.trim();
            cardCallsign.textContent = v || cardCallsign.getAttribute('data-empty');
            cardCallsign.classList.toggle('is-empty', v === '');
        });
    }
    root.querySelectorAll('[data-welcome-file]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var label = input.closest('.ah-wel__drop');
            var name = label ? label.querySelector('[data-welcome-filename]') : null;
            if (!file) { return; }
            if (name) { name.textContent = file.name; }
            if (label) { label.classList.add('is-chosen'); }
            var box = root.querySelector('[data-welcome-preview="' + input.getAttribute('data-welcome-file') + '"]');
            if (!box || !window.URL) { return; }
            var img = box.querySelector('img');
            if (!img) { img = document.createElement('img'); img.alt = ''; box.appendChild(img); }
            img.src = URL.createObjectURL(file);
            box.classList.remove('is-empty');
            box.classList.add('is-fresh');
        });
    });
})();
</script>

<?php require base_path('views/partials/account/shell_close.php'); ?>
