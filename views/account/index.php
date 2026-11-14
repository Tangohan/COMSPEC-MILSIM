<?php
declare(strict_types=1);

$accountUser = is_array($accountUser ?? null) ? $accountUser : [];
$accountProfile = is_array($accountProfile ?? null) ? $accountProfile : [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$onboardingSnapshot = is_array($onboardingSnapshot ?? null) ? $onboardingSnapshot : [];
$accountHasPortrait = !empty($accountHasPortrait);
$accountSecurity = is_array($accountSecurity ?? null) ? $accountSecurity : [];
$accountActivity = is_array($accountActivity ?? null) ? $accountActivity : [];
$accountDeletionScheduledAt = trim((string) ($accountDeletionScheduledAt ?? ''));
$accountMemberSince = trim((string) ($accountMemberSince ?? ''));

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$callsign = trim((string) ($accountUser['callsign'] ?? ''));
$onboardingPercent = max(0, min(100, (int) ($onboardingSnapshot['percent'] ?? 0)));
$onboardingNudge = trim((string) ($onboardingSnapshot['nudge'] ?? ''));
$onboardingTotal = (int) ($onboardingSnapshot['total_count'] ?? 0);
$showOnboarding = $onboardingTotal > 0 && $onboardingPercent < 100;

$secondFactor = $accountSecurity['second_factor'] ?? null;
$secondFactorMandatory = !empty($accountSecurity['second_factor_mandatory']);
$steamLinked = !empty($accountSecurity['steam_linked']);
$devicesActive = (int) ($accountSecurity['devices_active'] ?? 0);
$devicesTotal = (int) ($accountSecurity['devices_total'] ?? 0);
$ovPublicCvUrl = isset($accountPublicCvUrl) && is_string($accountPublicCvUrl) && $accountPublicCvUrl !== '' ? $accountPublicCvUrl : null;

$formatDay = static function (string $raw): ?string {
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return null;
    }
    try {
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $dt = new \DateTimeImmutable($raw);

        return $dt->format('j') . ' ' . $months[(int) $dt->format('n') - 1] . ' ' . $dt->format('Y');
    } catch (\Throwable) {
        return null;
    }
};

/* Protections du compte : chaque ligne dit son état et mène au réglage. */
$checks = [
    [
        'ok' => !empty($accountSnapshot['email_verified']),
        'title' => 'Adresse e-mail confirmée',
        'detail' => (string) ($accountSnapshot['email_masked'] ?? '—'),
        'detail_mono' => true,
        'warn' => 'Confirmez-la : c’est elle qui sert à récupérer le compte.',
        'href' => url('account/mail'),
        'cta' => !empty($accountSnapshot['email_verified']) ? 'Changer' : 'Vérifier',
    ],
    [
        'ok' => $secondFactor !== null,
        'title' => 'Double vérification',
        'detail' => $secondFactor === 'totp'
            ? 'Application d’authentification'
            : ($secondFactor === 'email'
                ? 'Code par e-mail' . ($secondFactorMandatory ? ' (imposé pour votre rôle)' : '')
                : 'Mot de passe seul'),
        'warn' => 'Un second code bloque la connexion même si le mot de passe fuite.',
        'href' => url('account/security'),
        'cta' => $secondFactor !== null ? 'Gérer' : 'Activer',
    ],
    [
        'ok' => $steamLinked,
        'title' => 'Compte Steam lié',
        'detail' => $steamLinked ? 'Reconnu en jeu et par COMSPEC ATAK' : 'Non lié',
        'warn' => 'Nécessaire pour être reconnu sur le serveur et dans ATAK.',
        'href' => $steamLinked ? url('account/preferences') . '#section-liaisons' : url('account/steam/connect'),
        'cta' => $steamLinked ? 'Voir' : 'Lier Steam',
    ],
    [
        'ok' => $accountHasPortrait,
        'title' => 'Portrait opérateur',
        'detail' => $accountHasPortrait ? 'Affiché sur le portail et la fiche' : 'Photo « inconnu » affichée',
        'warn' => 'Votre portrait remplace la silhouette partout sur le site.',
        'href' => url('account/portrait'),
        'cta' => $accountHasPortrait ? 'Changer' : 'Ajouter',
    ],
];
$checksDone = count(array_filter($checks, static fn (array $c): bool => $c['ok']));
$checksTotal = count($checks);
$checksPct = $checksTotal > 0 ? (int) round($checksDone * 100 / $checksTotal) : 0;
$healthTone = $checksDone === $checksTotal ? 'ok' : ($checksDone >= $checksTotal - 1 ? 'mid' : 'low');
$healthLabel = match ($healthTone) {
    'ok' => 'Compte bien protégé',
    'mid' => 'Presque complet',
    default => 'À compléter',
};

/* Réglages qu’on vient chercher le plus souvent : chacun ouvre directement le bon endroit. */
$shortcuts = [
    ['href' => url('account/preferences') . '#section-interface', 'title' => 'Thème clair ou sombre', 'desc' => 'Mode nuit du compte', 'icon' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>'],
    ['href' => url('account/preferences') . '#language', 'title' => 'Langue et fuseau', 'desc' => 'Pages, e-mails, heures', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/>'],
    ['href' => url('account/preferences') . '#notifications-email', 'title' => 'Notifications par e-mail', 'desc' => 'Ce que vous recevez', 'icon' => '<path d="M6 9a6 6 0 0112 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9z"/><path d="M10 20a2 2 0 004 0"/>'],
    ['href' => url('account/password'), 'title' => 'Mot de passe', 'desc' => 'Changer le secret d’accès', 'icon' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>'],
    ['href' => url('account/security'), 'title' => 'Double vérification', 'desc' => 'Code par e-mail ou application', 'icon' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>'],
    ['href' => url('account/preferences') . '#section-profil', 'title' => 'Nom et indicatif', 'desc' => 'Identité sur le portail', 'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>'],
];
$svg = static fn (string $path): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';

$lastLogin = preg_replace('/\s*\([^)]*\)$/', '', (string) ($accountSnapshot['last_login_label'] ?? '')) ?: 'Non enregistrée';

$accountNavKey = 'overview';
$accountTitle = 'Mon compte';
$accountLead = 'Connexion, sécurité et préférences. L’unité et le grade restent sur votre fiche.';
require base_path('views/partials/account/shell_open.php');
?>

<div class="ah-ov">

<?php
$acChar = is_array($accountCharacter ?? null) ? $accountCharacter : ['name' => '', 'callsign' => '', 'portrait_url' => null];
$acHasChar = trim((string) ($acChar['name'] ?? '')) !== '';
$acDiscord = !empty($accountDiscordLinked);
?>
<?php if ($accountDeletionScheduledAt !== ''): ?>
<div class="account-hub__ov-alert account-hub__ov-alert--danger" role="alert">
    <div>
        <strong>Suppression du compte programmée<?= ($d = $formatDay($accountDeletionScheduledAt)) !== null ? ' le ' . $h($d) : '' ?>.</strong>
        <span>Vous pouvez encore l’annuler.</span>
    </div>
    <a href="<?= $h(url('account/donnees')) ?>" class="account-hub__btn account-hub__btn--ink">Annuler la suppression</a>
</div>
<?php endif; ?>

<?php
/*
 * Une seule liste « à faire » : tout ce qui attend une action, du plus important au moins important. Avant, la même
 * information revenait dans quatre blocs (cartes rapides, arrivée, profil, état du compte) : la page ne disait pas par où
 * commencer. Ce qui est déjà en place tient sur une ligne, sous la liste.
 */
$pw = is_array($profileWelcome ?? null) ? $profileWelcome : null;
$pwPending = $pw !== null && empty($pw['finished']) && ($pw['steps'] ?? []) !== [];
$todo = [];
if ($showOnboarding) {
    $todo[] = [
        'title' => function_exists('__') ? __('common.integration_account_heading') : 'Votre arrivée n’est pas terminée',
        'why' => $onboardingNudge !== '' && $onboardingNudge !== 'RAS'
            ? $onboardingNudge
            : (function_exists('__') ? __('common.integration_account_nudge') : 'Les étapes restantes se trouvent dans Mon intégration, pas ici.'),
        'href' => url('mon-integration'),
        'cta' => function_exists('__') ? __('common.integration_open') : 'Ouvrir Mon intégration',
        'pct' => $onboardingPercent,
    ];
}
if ($pwPending) {
    $pwDone = (int) ($pw['progress']['done'] ?? 0);
    $pwTotal = (int) ($pw['progress']['total'] ?? 0);
    $pwLeft = array_values(array_filter($pw['steps'], static fn (array $st): bool => $st['status'] !== 'done'));
    $todo[] = [
        'title' => 'Complétez votre profil',
        'why' => $pwDone . ' étape' . ($pwDone > 1 ? 's' : '') . ' sur ' . $pwTotal . '. Reste : '
            . implode(', ', array_map(static fn (array $st): string => mb_strtolower((string) $st['short']), array_slice($pwLeft, 0, 3))) . (count($pwLeft) > 3 ? '…' : '.'),
        'post' => url('account/bienvenue/reprendre'),
        'cta' => !empty($pw['state']['started_at']) ? 'Reprendre' : 'Commencer',
        'pct' => $pwTotal > 0 ? (int) round($pwDone * 100 / $pwTotal) : 0,
    ];
}
if (!$acHasChar) {
    $todo[] = ['title' => 'Créez votre personnage', 'why' => 'Prénom, nom et indicatif suffisent : une minute.', 'href' => url('account/personnage'), 'cta' => 'Créer mon personnage'];
}
foreach ($checks as $check) {
    if (!$check['ok']) {
        $todo[] = ['title' => $check['title'], 'why' => $check['warn'], 'href' => $check['href'], 'cta' => $check['cta']];
    }
}
$todoCount = count($todo);
?>
<section id="etat-du-compte" class="ah-todo is-<?= $todoCount === 0 ? 'ok' : 'todo' ?>" aria-labelledby="account-health-heading" data-locate-box>
    <div class="ah-todo__head">
        <h2 id="account-health-heading" class="ah-todo__title">État du compte</h2>
        <p class="ah-todo__state"><?= $todoCount === 0 ? 'Tout est en place, rien à faire.' : $todoCount . ' chose' . ($todoCount > 1 ? 's' : '') . ' à faire' ?></p>
    </div>
    <?php if ($todoCount > 0): ?>
    <ol class="ah-todo__list">
        <?php foreach ($todo as $i => $item): ?>
        <li class="ah-todo__item">
            <span class="ah-todo__n" aria-hidden="true"><?= $i + 1 ?></span>
            <div class="ah-todo__text">
                <p class="ah-todo__what"><?= $h($item['title']) ?></p>
                <p class="ah-todo__why"><?= $h($item['why']) ?></p>
                <?php if (isset($item['pct'])): ?>
                <span class="account-hub__ov-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $item['pct'] ?>"><span style="width:<?= (int) $item['pct'] ?>%"></span></span>
                <?php endif; ?>
            </div>
            <?php if (isset($item['post'])): ?>
            <form method="post" action="<?= $h($item['post']) ?>" class="ah-todo__act">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink"><?= $h($item['cta']) ?></button>
            </form>
            <?php else: ?>
            <a href="<?= $h($item['href']) ?>" class="account-hub__btn account-hub__btn--ink ah-todo__act"><?= $h($item['cta']) ?></a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php $checksOk = array_values(array_filter($checks, static fn (array $c): bool => $c['ok'])); ?>
    <?php if ($checksOk !== []): ?>
    <ul class="ah-todo__done" aria-label="Déjà en place">
        <?php foreach ($checksOk as $check): ?>
        <li>
            <a href="<?= $h($check['href']) ?>" title="<?= $h($check['detail'] . ' · ' . $check['cta']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                <?= $h($check['title']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<div class="ah-spaces">
    <a class="ah-space" href="<?= $h(url('account/personnage')) ?>">
        <?php if ($acHasChar && !empty($acChar['portrait_url'])): ?><img src="<?= $h($acChar['portrait_url']) ?>" alt="" class="ah-space__img" width="44" height="44"><?php endif; ?>
        <span class="ah-space__body">
            <span class="ah-space__title">Mon personnage</span>
            <span class="ah-space__state"><?= $acHasChar ? $h($acChar['name']) . (($acChar['callsign'] ?? '') !== '' ? ' · ' . $h($acChar['callsign']) : '') : 'Pas encore créé' ?></span>
        </span>
        <span class="ah-space__go"><?= $acHasChar ? 'Modifier' : 'Créer' ?></span>
    </a>
    <a class="ah-space" href="<?= $h(url('account/page-publique')) ?>">
        <span class="ah-space__body">
            <span class="ah-space__title">Ma page publique</span>
            <span class="ah-space__state"><?= $ovPublicCvUrl !== null ? 'Ouverte à tous' : 'Fermée' ?></span>
        </span>
        <span class="ah-space__go"><?= $ovPublicCvUrl !== null ? 'Régler' : 'Ouvrir' ?></span>
    </a>
    <a class="ah-space" href="<?= $h(url('discord/roles-lies')) ?>">
        <span class="ah-space__body">
            <span class="ah-space__title">Discord</span>
            <span class="ah-space__state"><?= $acDiscord ? 'Lié à votre compte' : 'Pas encore lié' ?></span>
        </span>
        <span class="ah-space__go"><?= $acDiscord ? 'Gérer' : 'Lier' ?></span>
    </a>
</div>
<?php if ($ovPublicCvUrl !== null): ?>
<p class="ah-spaces__link">Adresse de votre page : <a href="<?= $h($ovPublicCvUrl) ?>"><?= $h($ovPublicCvUrl) ?></a></p>
<?php endif; ?>

<div class="ah-ov-layout">
    <div class="ah-ov-layout__main">
        <?php $yr = is_array($accountYearReview ?? null) ? $accountYearReview : null; ?>
        <?php if ($yr !== null): ?>
        <link href="<?= $h(asset_url('assets/css/year-review.css')) ?>" rel="stylesheet">
        <section class="yr-account<?= $yr['published'] ? '' : ' is-soon' ?>" aria-labelledby="account-year-review-heading">
            <div class="yr-account__main">
                <p class="yr-account__kicker">Rétrospective</p>
                <p class="yr-account__year"><?= (int) $yr['year'] ?></p>
                <div>
                    <h2 id="account-year-review-heading" class="yr-account__title">
                        <?= $yr['published'] ? ($yr['has_data'] ? 'Ton année sur le terrain' : 'Pas encore assez de terrain cette année') : 'Ta rétrospective arrive bientôt' ?>
                    </h2>
                    <p class="yr-account__desc">
                        <?php if (!$yr['published']): ?>
                            Heures de jeu, kilomètres, blessures, SSE, comptes rendus : publiée le <?= $h(date('d/m/Y', (int) $yr['publish_at'])) ?>.
                        <?php elseif ($yr['has_data']): ?>
                            Revois ton année en quelques écrans et partage tes cartes sur Discord.
                        <?php else: ?>
                            Elle se remplit à chaque partie jouée avec l’ATAK et le lien du jeu.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ($yr['published']): ?>
            <div class="yr-account__actions">
                <a class="yr-btn yr-btn--solid" href="<?= $h(url('retrospective/' . (int) $yr['year'])) ?>"><?= $yr['has_data'] ? 'Voir ma rétrospective' : 'Ouvrir' ?></a>
                <?php if ($yr['collective']): ?>
                <a class="yr-btn" href="<?= $h(url('retrospective/' . (int) $yr['year'] . '/unite')) ?>">Bilan de l’unité</a>
                <?php endif; ?>
                <?php foreach (array_slice($yr['years'], 1) as $past): ?>
                <a class="yr-btn" href="<?= $h(url('retrospective/' . (int) $past)) ?>"><?= (int) $past ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="ah-card" aria-labelledby="account-shortcuts-heading">
            <div class="ah-card__head">
                <h2 id="account-shortcuts-heading" class="ah-card__title">Réglages fréquents</h2>
                <p class="ah-card__desc">Tout le reste se trouve avec la recherche en haut de la page<span class="ah-kbd-hint">, touche <kbd>/</kbd></span>.</p>
            </div>
            <ul class="ah-ov-shortcuts">
                <?php foreach ($shortcuts as $sc): ?>
                <li>
                    <a class="ah-ov-shortcut" href="<?= $h($sc['href']) ?>">
                        <span class="ah-ov-shortcut__icon"><?= $svg($sc['icon']) ?></span>
                        <span class="ah-ov-shortcut__text">
                            <span class="ah-ov-shortcut__title"><?= $h($sc['title']) ?></span>
                            <span class="ah-ov-shortcut__desc"><?= $h($sc['desc']) ?></span>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section id="activite" class="ah-card" aria-labelledby="account-activity-heading" data-locate-box>
            <div class="ah-card__head">
                <h2 id="account-activity-heading" class="ah-card__title">Activité récente</h2>
                <p class="ah-card__desc">Connexions et changements de sécurité de ce compte. Adresse IP volontairement tronquée.</p>
            </div>
            <?php if ($accountActivity === []): ?>
            <p class="ah-ov-empty">Aucune activité enregistrée pour l’instant.</p>
            <?php else: ?>
            <details class="ah-fold">
            <summary>Afficher les <?= count($accountActivity) ?> dernières lignes</summary>
            <ol class="ah-ov-feed">
                <?php foreach ($accountActivity as $event): ?>
                <li class="ah-ov-event is-<?= $h($event['tone'] ?? 'muted') ?>">
                    <span class="ah-ov-event__dot" aria-hidden="true"></span>
                    <span class="ah-ov-event__main">
                        <span class="ah-ov-event__title"><?= $h($event['label'] ?? '') ?></span>
                        <span class="ah-ov-event__meta"><?= $h($event['device'] ?? '') ?> · IP <?= $h($event['ip'] ?? '—') ?></span>
                    </span>
                    <time class="ah-ov-event__when"><?= $h($event['when'] ?? '') ?></time>
                </li>
                <?php endforeach; ?>
            </ol>
            </details>
            <?php endif; ?>
        </section>
    </div>

    <aside class="ah-ov-layout__side" aria-label="Votre compte en bref">
        <section class="ah-card" aria-labelledby="account-facts-heading">
            <h2 id="account-facts-heading" class="ah-card__title">En bref</h2>
            <dl class="ah-ov-facts">
                <div>
                    <dt>Dernière connexion</dt>
                    <dd><?= $h($lastLogin) ?></dd>
                </div>
                <div>
                    <dt>Indicatif</dt>
                    <dd><?= $h($callsign !== '' ? $callsign : 'Non renseigné') ?> <a href="<?= $h(url('account/preferences')) ?>#callsign">Modifier</a></dd>
                </div>
                <div>
                    <dt>Appareils ATAK</dt>
                    <dd>
                        <?= $devicesTotal === 0
                            ? 'Aucun téléphone lié'
                            : $h($devicesActive . ' actif' . ($devicesActive > 1 ? 's' : '') . ($devicesActive === $devicesTotal ? '' : ' sur ' . $devicesTotal)) ?>
                        <a href="<?= $h(url('account/security/devices')) ?>">Gérer</a>
                    </dd>
                </div>
                    <dt>Membre depuis</dt>
                    <dd><?= $h($formatDay($accountMemberSince) ?? '—') ?></dd>
                </div>
            </dl>
        </section>

        <section id="sessions" class="ah-card" aria-labelledby="account-sessions-heading" data-locate-box>
            <h2 id="account-sessions-heading" class="ah-card__title">Connecté ailleurs ?</h2>
            <p class="ah-card__desc">Un ordinateur partagé, un téléphone perdu ou une connexion que vous ne reconnaissez pas : fermez toutes les autres sessions. Celle-ci reste ouverte.</p>
            <form method="post" action="<?= $h(url('account/sessions/fermer-les-autres')) ?>" class="ah-ov-sessions" onsubmit="return confirm('Fermer toutes vos autres sessions ? Les autres navigateurs devront se reconnecter.');">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Déconnecter mes autres sessions</button>
            </form>
            <p class="ah-card__note">Changer de mot de passe ferme aussi les autres sessions. Les téléphones ATAK se gèrent dans <a href="<?= $h(url('account/security/devices')) ?>">Appareils ATAK</a>.</p>
        </section>
    </aside>
</div>

</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
