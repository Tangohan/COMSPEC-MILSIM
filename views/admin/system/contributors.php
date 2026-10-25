<?php
declare(strict_types=1);

/**
 * Contributeurs et traduction communautaire du site (administration du site).
 * Contrôleur : SystemContributorsController.
 *
 * @var list<array<string, mixed>> $contribRows
 * @var list<array{code: string, name: string, status: string, done: int, total: int, pending: int, blank?: int}> $contribLanguages
 * @var list<array<string, mixed>> $contribLeaders
 * @var array<string, string> $contribDiscordRoles
 */
$ready = (bool) ($contribReady ?? false);
$rows = is_array($contribRows ?? null) ? $contribRows : [];
$langs = is_array($contribLanguages ?? null) ? $contribLanguages : [];
$leaders = is_array($contribLeaders ?? null) ? $contribLeaders : [];
$discordRoles = is_array($contribDiscordRoles ?? null) ? $contribDiscordRoles : [];
$base = (string) ($contribBase ?? 'admin/system/contributeurs');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$roleNames = [
    'translator' => 'Traducteur',
    'tester' => 'Testeur',
    'content' => 'Créateur de contenu',
    'developer' => 'Développeur',
    'support' => 'Support',
];
$roleList = implode(', ', array_values($roleNames));
$statusPill = [
    'pending' => ['En attente', 'amber'],
    'active' => ['Actif', 'mint'],
    'rejected' => ['Refusé', 'rose'],
    'revoked' => ['Retiré', 'slate'],
];
$roleLabel = static function (string $role) use ($roleNames): string {
    return $roleNames[$role] ?? 'Contributeur';
};
$statusLabel = static function (string $status) use ($statusPill): array {
    return $statusPill[$status] ?? ['À vérifier', 'slate'];
};

$grouped = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $grouped[(int) ($row['user_id'] ?? 0)][] = $row;
}
$people = [];
foreach ($grouped as $uid => $roleRows) {
    $pseudo = '';
    $call = '';
    $email = '';
    $display = '';
    $discord = '';
    $byRole = [];
    foreach ($roleRows as $row) {
        $byRole[(string) ($row['role'] ?? '')] = $row;
        if ($pseudo === '' && trim((string) ($row['public_name'] ?? '')) !== '') {
            $pseudo = trim((string) $row['public_name']);
        }
        if ($call === '' && trim((string) ($row['callsign'] ?? '')) !== '') {
            $call = trim((string) $row['callsign']);
        }
        if ($email === '' && trim((string) ($row['email'] ?? '')) !== '') {
            $email = trim((string) $row['email']);
        }
        if ($display === '' && trim((string) ($row['display_name'] ?? '')) !== '') {
            $display = trim((string) $row['display_name']);
        }
        if (trim((string) ($row['discord_handle'] ?? '')) !== '') {
            $discord = trim((string) $row['discord_handle']);
        }
    }
    $name = $pseudo !== '' ? $pseudo : ($call !== '' ? $call : ($display !== '' ? $display : ('Membre #' . $uid)));
    $ordered = [];
    foreach (array_keys($roleNames) as $role) {
        if (isset($byRole[$role])) {
            $ordered[] = $byRole[$role];
        }
    }
    $people[] = [
        'user_id' => $uid,
        'name' => $name,
        'pseudo' => $pseudo,
        'call' => $call,
        'email' => $email,
        'discord' => $discord,
        'by_role' => $byRole,
        'roles' => $ordered,
    ];
}
$hasStatus = static function (array $person, string $status): bool {
    foreach ($person['roles'] as $row) {
        if ((string) ($row['status'] ?? '') === $status) {
            return true;
        }
    }

    return false;
};
$pendingPeople = array_values(array_filter($people, static fn (array $p): bool => $hasStatus($p, 'pending')));
$activePeople = array_values(array_filter(
    $people,
    static fn (array $p): bool => !$hasStatus($p, 'pending') && $hasStatus($p, 'active')
));
$closedPeople = array_values(array_filter(
    $people,
    static fn (array $p): bool => !$hasStatus($p, 'pending') && !$hasStatus($p, 'active')
));
usort($pendingPeople, static function (array $a, array $b): int {
    $date = static function (array $person): string {
        $oldest = '9999';
        foreach ($person['roles'] as $row) {
            if ((string) ($row['status'] ?? '') === 'pending') {
                $created = (string) ($row['created_at'] ?? '');
                if ($created !== '' && $created < $oldest) {
                    $oldest = $created;
                }
            }
        }

        return $oldest;
    };

    return strcmp($date($a), $date($b));
});
$byName = static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']);
usort($activePeople, $byName);
usort($closedPeople, $byName);
$pendingCount = 0;
$activeUsers = [];
foreach ($rows as $row) {
    if ((string) ($row['status'] ?? '') === 'pending') {
        $pendingCount++;
    }
    if ((string) ($row['status'] ?? '') === 'active') {
        $activeUsers[(int) ($row['user_id'] ?? 0)] = true;
    }
}
$blankCatalog = (int) ($langs[0]['blank'] ?? 0);
$catalogTotal = (int) ($langs[0]['total'] ?? 0);
$honors = ['Premier traducteur', 'Deuxième', 'Troisième'];
$shareOf = static function (int $validated) use ($catalogTotal): int {
    return $catalogTotal > 0 ? (int) floor($validated * 100 / $catalogTotal) : 0;
};
$discordLegend = [];
foreach ($roleNames as $key => $label) {
    if (($discordRoles[$key] ?? '') !== '') {
        $discordLegend[] = $label . ' → ' . $discordRoles[$key];
    }
}

$renderPerson = static function (array $person, string $tone) use ($h, $base, $roleNames, $roleLabel, $statusLabel, $discordRoles): void {
    $uid = (int) $person['user_id'];
    $name = (string) $person['name'];
    $pendingLabels = [];
    $activeLabels = [];
    foreach ($person['roles'] as $row) {
        $label = $roleLabel((string) ($row['role'] ?? ''));
        if ((string) ($row['status'] ?? '') === 'pending') {
            $pendingLabels[] = $label;
        } elseif ((string) ($row['status'] ?? '') === 'active') {
            $activeLabels[] = $label;
        }
    }
    $translatorLangs = (string) ($person['by_role']['translator']['languages'] ?? '');
    ?>
    <article class="ctba-person ctba-person--<?= $h($tone) ?>" id="u<?= $uid ?>" data-ctba-person>
        <header class="ctba-person__head">
            <h3 class="ctba-person__name"><?= $h($name) ?></h3>
            <p class="pa-sub">
                <?php if ((string) $person['pseudo'] === ''): ?>Sans pseudo public<?php elseif ((string) $person['call'] !== ''): ?>Indicatif <?= $h($person['call']) ?><?php endif; ?>
                <?php if ((string) $person['email'] !== ''): ?> · <?= $h($person['email']) ?><?php endif; ?>
                <?php if ((string) $person['discord'] !== ''): ?> · Discord <strong><?= $h($person['discord']) ?></strong><?php endif; ?>
            </p>
            <?php if ($pendingLabels !== []): ?>
            <p class="ctba-person__next">À faire : accorder ou refuser <?= $h(implode(', ', $pendingLabels)) ?>.</p>
            <?php elseif ($activeLabels !== []): ?>
            <p class="ctba-person__next">Rôles en place : <?= $h(implode(', ', $activeLabels)) ?>. Vous pouvez en accorder d’autres, ou en retirer un seul.</p>
            <?php else: ?>
            <p class="ctba-person__next">Aucun rôle actif. Cochez ceux à accorder de nouveau.</p>
            <?php endif; ?>
        </header>
        <?php if ($person['roles'] !== []): ?>
        <ul class="ctba-chips">
            <?php foreach ($person['roles'] as $row): ?>
            <?php
            [$stateLabel, $stateTone] = $statusLabel((string) ($row['status'] ?? ''));
            $roleKey = (string) ($row['role'] ?? '');
            $asked = substr((string) ($row['created_at'] ?? ''), 0, 10);
            ?>
            <li class="ctba-chip" id="r<?= (int) ($row['id'] ?? 0) ?>">
                <span class="pa-pill pa-pill--blue"><?= $h($roleLabel($roleKey)) ?></span>
                <span class="pa-pill pa-pill--<?= $h($stateTone) ?>"><?= $h($stateLabel) ?></span>
                <?php if (trim((string) ($row['languages'] ?? '')) !== ''): ?>
                <span class="pa-sub"><?= $h(strtoupper(str_replace(',', ', ', (string) $row['languages']))) ?></span>
                <?php endif; ?>
                <?php if ((int) ($row['public_credit'] ?? 1) !== 1): ?>
                <span class="pa-sub">Ne veut pas être cité</span>
                <?php endif; ?>
                <?php if ($asked !== ''): ?>
                <span class="pa-sub"><?= (string) ($row['status'] ?? '') === 'pending' ? 'Demandé le' : 'Depuis le' ?> <?= $h($asked) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php foreach ($person['roles'] as $row): ?>
            <?php if (trim((string) ($row['motivation'] ?? '')) === '') { continue; } ?>
            <p class="pa-card__help"><strong><?= $h($roleLabel((string) ($row['role'] ?? ''))) ?>.</strong> <?= nl2br($h($row['motivation'])) ?></p>
        <?php endforeach; ?>
        <?php foreach ($person['roles'] as $row): ?>
            <?php if (trim((string) ($row['admin_note'] ?? '')) === '') { continue; } ?>
            <p class="pa-card__help">Note sur <?= $h($roleLabel((string) ($row['role'] ?? ''))) ?> : <?= $h($row['admin_note']) ?></p>
        <?php endforeach; ?>

        <form method="post" action="<?= $h(url($base . '/role')) ?>" class="ctba-grant">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="user_id" value="<?= $uid ?>">
            <input type="hidden" name="action" value="accept">
            <div class="ctba-grant__wide">
                <p class="pa-card__kicker">Accorder</p>
                <p class="pa-card__help">Cochez un ou plusieurs rôles pour <?= $h($name) ?>. Un rôle déjà actif reste en place. Décocher n’enlève rien et ne refuse pas une demande.</p>
            </div>
            <?php foreach ($roleNames as $role => $label): ?>
            <?php
            $held = $person['by_role'][$role] ?? null;
            $heldStatus = is_array($held) ? (string) ($held['status'] ?? '') : '';
            $checked = in_array($heldStatus, ['pending', 'active'], true);
            $hint = match ($heldStatus) {
                'active' => 'Déjà actif. Décocher ne retire pas ce rôle.',
                'pending' => 'Demande en attente.',
                'rejected' => 'Demande refusée. Cocher l’accorde de nouveau.',
                'revoked' => 'Rôle retiré. Cocher l’accorde de nouveau.',
                default => 'Pas encore demandé.',
            };
            if (($discordRoles[$role] ?? '') !== '') {
                $hint .= ' Sur Discord : ' . $discordRoles[$role] . '.';
            }
            ?>
            <label class="pa-choice">
                <input type="checkbox" name="roles[]" value="<?= $h($role) ?>"<?= $checked ? ' checked' : '' ?>>
                <span><strong><?= $h($label) ?></strong><em><?= $h($hint) ?></em></span>
            </label>
            <?php endforeach; ?>
            <div class="pa-field ctba-grant__wide" data-ctba-langs>
                <label for="ctba-langs-<?= $uid ?>">Langues du traducteur</label>
                <p class="pa-hint">Deux lettres séparées par une virgule, par exemple es, de. Sert seulement si Traducteur est coché.</p>
                <input id="ctba-langs-<?= $uid ?>" type="text" name="languages" value="<?= $h($translatorLangs) ?>" placeholder="es, de" maxlength="120" autocomplete="off" spellcheck="false">
            </div>
            <div class="pa-field ctba-grant__wide">
                <label for="ctba-note-<?= $uid ?>">Note pour les rôles accordés</label>
                <p class="pa-hint">Facultative. Elle est enregistrée avec les rôles que vous accordez maintenant.</p>
                <input id="ctba-note-<?= $uid ?>" type="text" name="note" maxlength="500">
            </div>
            <div class="pa-actions ctba-grant__wide">
                <button type="submit" class="pa-btn pa-btn--solid">Accorder les rôles cochés à <?= $h($name) ?></button>
            </div>
        </form>

        <?php
        $decisions = [];
        foreach ($person['roles'] as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'pending' || $status === 'active') {
                $decisions[] = $row;
            }
        }
        ?>
        <?php if ($decisions !== []): ?>
        <div class="ctba-decide">
            <?php foreach ($decisions as $row): ?>
            <?php $roleKey = (string) ($row['role'] ?? ''); $isPending = (string) ($row['status'] ?? '') === 'pending'; ?>
            <form method="post" action="<?= $h(url($base . '/role')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                <div class="pa-field">
                    <label for="ctba-dec-<?= (int) ($row['id'] ?? 0) ?>"><?= $isPending ? 'Refuser' : 'Retirer' ?> <?= $h($roleLabel($roleKey)) ?> — <?= $h($name) ?></label>
                    <input id="ctba-dec-<?= (int) ($row['id'] ?? 0) ?>" type="text" name="note" maxlength="500" placeholder="Note vue par le membre">
                </div>
                <div class="pa-actions">
                    <?php if ($isPending): ?>
                    <button type="submit" name="action" value="reject" class="pa-btn pa-btn--line">Refuser <?= $h($roleLabel($roleKey)) ?></button>
                    <?php else: ?>
                    <button type="submit" name="action" value="revoke" class="pa-btn pa-btn--line">Retirer <?= $h($roleLabel($roleKey)) ?></button>
                    <?php endif; ?>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </article>
    <?php
};
?>
<div class="pa par">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Contributeurs
            </p>
            <h1 class="pa-hero__title">Contributeurs</h1>
            <p class="pa-hero__lead">
                Les demandes arrivent depuis la page Contribuer. Une personne peut cumuler plusieurs rôles :
                accorder un rôle ne retire pas les autres, et refuser ou retirer un rôle laisse le reste en place.
                Le rôle Discord correspondant se donne à la main.
            </p>
            <?php if ($ready): ?>
            <ul class="ctba-counts">
                <li><strong><?= $pendingCount ?></strong> <?= $pendingCount > 1 ? 'demandes à traiter' : 'demande à traiter' ?></li>
                <li><strong><?= count($activeUsers) ?></strong> <?= count($activeUsers) > 1 ? 'contributeurs actifs' : 'contributeur actif' ?></li>
                <li><strong><?= count($langs) ?></strong> <?= count($langs) > 1 ? 'langues' : 'langue' ?></li>
                <li><strong><?= $blankCatalog ?></strong> textes seulement en français</li>
            </ul>
            <?php endif; ?>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="#demandes">Demandes</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('contribuer')) ?>" target="_blank" rel="noopener">Page Contribuer</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('traduire-site')) ?>" target="_blank" rel="noopener">Espace de traduction</a>
                <a class="pa-btn pa-btn--ghost" href="#langues">Langues</a>
            </div>
        </header>
        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p><?php endif; ?>
            <?php if (!$ready): ?>
            <p class="pa-flash pa-flash--err">Les demandes de contributeurs ne sont pas encore disponibles sur ce serveur.</p>
            <?php else: ?>

            <section class="ctba-section" id="demandes" aria-labelledby="ctba-pending">
                <p class="pa-card__kicker">À traiter</p>
                <h2 class="pa-card__title" id="ctba-pending">Demandes en attente</h2>
                <p class="pa-card__help">Chaque carte est une personne. Les rôles demandés sont indiqués à côté de son nom. <?= $discordLegend !== [] ? 'Sur Discord, à donner à la main : ' . $h(implode(' · ', $discordLegend)) . '.' : '' ?></p>
                <?php if ($pendingPeople === []): ?>
                <p class="pa-empty-state">Aucune demande en attente. Les rôles que l’on peut accorder sont <?= $h($roleList) ?>.</p>
                <?php else: ?>
                <div class="ctba-people">
                    <?php foreach ($pendingPeople as $person) { $renderPerson($person, 'pending'); } ?>
                </div>
                <?php endif; ?>
            </section>

            <section class="ctba-section" id="actifs" aria-labelledby="ctba-active">
                <p class="pa-card__kicker">En place</p>
                <h2 class="pa-card__title" id="ctba-active">Contributeurs actifs</h2>
                <p class="pa-card__help">Une personne peut avoir plusieurs rôles en même temps. Retirer l’un d’eux ne retire pas les autres.</p>
                <?php if ($activePeople === []): ?>
                <p class="pa-empty-state">Aucun contributeur actif. Les rôles possibles sont <?= $h($roleList) ?>.</p>
                <?php else: ?>
                <div class="ctba-people">
                    <?php foreach ($activePeople as $person) { $renderPerson($person, 'active'); } ?>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($closedPeople !== []): ?>
            <details class="pa-card ctba-section">
                <summary class="pa-card__title">Refusés et retirés (<?= count($closedPeople) ?>)</summary>
                <p class="pa-card__help">Ces personnes n’ont plus de rôle actif. Vous pouvez leur en accorder de nouveau sans rouvrir les autres.</p>
                <div class="ctba-people">
                    <?php foreach ($closedPeople as $person) { $renderPerson($person, 'closed'); } ?>
                </div>
            </details>
            <?php endif; ?>

            <section class="pa-card ctba-section" id="classement" aria-labelledby="ctba-leaders">
                <p class="pa-card__kicker">Traduction</p>
                <h2 class="pa-card__title" id="ctba-leaders">Meilleurs contributeurs</h2>
                <p class="pa-card__help">Classement des textes validés. Le pourcentage est la part du catalogue couverte par cette personne. Les trois premiers sont mis en avant. Quelqu’un qui ne veut pas être cité reste visible ici, pas sur le site public.</p>
                <?php if ($leaders === []): ?>
                <p class="pa-empty-state">Aucun texte validé pour l’instant. Le classement apparaîtra dès qu’une personne aura validé un texte.</p>
                <?php else: ?>
                <ol class="ctba-podium">
                    <?php foreach (array_slice($leaders, 0, 3) as $i => $leader): ?>
                    <li class="ctba-podium__card is-<?= $i + 1 ?>">
                        <span class="ctba-podium__rank"><?= $i + 1 ?></span>
                        <strong class="ctba-podium__name"><?= $h($leader['name'] ?? '') ?></strong>
                        <span class="pa-pill pa-pill--mint"><?= $h($honors[$i]) ?></span>
                        <?php if (!empty($leader['roles']) && is_array($leader['roles'])): ?>
                        <span class="ctba-podium__roles">
                            <?php foreach ($leader['roles'] as $role) : ?>
                            <span class="pa-pill"><?= $h($roleLabel((string) $role)) ?></span>
                            <?php endforeach; ?>
                        </span>
                        <?php endif; ?>
                        <?php if (empty($leader['public_credit'])): ?><span class="pa-sub">Ne veut pas être cité</span><?php endif; ?>
                        <span class="ctba-podium__meta"><?= (int) ($leader['validated'] ?? 0) ?> textes · <?= $shareOf((int) ($leader['validated'] ?? 0)) ?> % du catalogue</span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($leaders) > 3): ?>
                <ol class="ctba-rest" start="4">
                    <?php foreach (array_slice($leaders, 3) as $i => $leader): ?>
                    <li>
                        <span class="ctba-rest__rank"><?= $i + 4 ?></span>
                        <strong><?= $h($leader['name'] ?? '') ?></strong>
                        <?php if (!empty($leader['roles']) && is_array($leader['roles'])): ?>
                            <?php foreach ($leader['roles'] as $role): ?><span class="pa-pill"><?= $h($roleLabel((string) $role)) ?></span><?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (empty($leader['public_credit'])): ?><span class="pa-sub">Ne veut pas être cité</span><?php endif; ?>
                        <span class="ctba-rest__meta"><?= (int) ($leader['validated'] ?? 0) ?> textes · <?= $shareOf((int) ($leader['validated'] ?? 0)) ?> %</span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="pa-card ctba-section" id="langues" aria-labelledby="ctba-langs">
                <p class="pa-card__kicker">Traduction du site</p>
                <h2 class="pa-card__title" id="ctba-langs">Langues</h2>
                <p class="pa-card__help">L’anglais compte les corrections, et les textes encore seulement en français. Chaque autre langue affiche sa propre part du catalogue. Publier une langue peu avancée est possible : un texte non traduit s’affiche en anglais.</p>
                <div class="ctba-langs">
                    <?php foreach ($langs as $l): ?>
                    <?php
                    $done = (int) ($l['done'] ?? 0);
                    $total = (int) ($l['total'] ?? 0);
                    $pct = $total > 0 ? (int) floor($done * 100 / $total) : 0;
                    $code = (string) ($l['code'] ?? '');
                    $isEnglish = $code === 'en';
                    ?>
                    <article class="ctba-langcard">
                        <div class="ctba-langcard__head">
                            <span class="ctba-langcard__code" translate="no"><?= $h(strtoupper($code)) ?></span>
                            <?php if ($isEnglish): ?>
                            <strong>English</strong>
                            <span class="pa-pill pa-pill--blue">Corrections</span>
                            <span class="pa-pill pa-pill--mint">Toujours dans le sélecteur</span>
                            <?php else: ?>
                            <strong translate="no"><?= $h($l['name'] ?? '') ?></strong>
                            <?php if (($l['status'] ?? '') === 'published'): ?>
                            <span class="pa-pill pa-pill--mint">Dans le sélecteur</span>
                            <?php else: ?>
                            <span class="pa-pill pa-pill--slate">Pas encore dans le sélecteur</span>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ctba-bar" role="img" aria-label="<?= $pct ?> %">
                            <span style="width: <?= $pct ?>%"></span>
                        </div>
                        <?php if ($isEnglish): ?>
                        <p class="pa-card__help"><?= $pct ?> % corrigé (<?= $done ?> / <?= $total ?>). <?= (int) ($l['blank'] ?? 0) ?> textes encore seulement en français.<?php if ((int) ($l['pending'] ?? 0) > 0): ?> <?= (int) $l['pending'] ?> propositions en attente.<?php endif; ?></p>
                        <form method="post" action="<?= $h(url($base . '/langue')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="code" value="en">
                            <button type="submit" name="action" value="recompile" class="pa-btn pa-btn--ghost">Actualiser les textes</button>
                        </form>
                        <?php else: ?>
                        <p class="pa-card__help"><?= $pct ?> % (<?= $done ?> / <?= $total ?>). <?php if ((int) ($l['pending'] ?? 0) > 0): ?><?= (int) $l['pending'] ?> propositions en attente.<?php else: ?>Aucune proposition en attente.<?php endif; ?></p>
                        <form method="post" action="<?= $h(url($base . '/langue')) ?>" class="ctba-langform">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="code" value="<?= $h($code) ?>">
                            <div class="pa-field">
                                <label for="ctba-name-<?= $h($code) ?>">Nom affiché</label>
                                <input id="ctba-name-<?= $h($code) ?>" type="text" name="name" value="<?= $h($l['name'] ?? '') ?>" maxlength="60">
                            </div>
                            <label class="pa-choice">
                                <input type="checkbox" name="published" value="1"<?= ($l['status'] ?? '') === 'published' ? ' checked' : '' ?>>
                                <span><strong>Visible dans le sélecteur de langue</strong><em>Les visiteurs peuvent choisir cette langue. Un texte manquant s’affiche en anglais.</em></span>
                            </label>
                            <div class="pa-actions ctba-langform__wide">
                                <button type="submit" name="action" value="save" class="pa-btn pa-btn--solid">Enregistrer</button>
                                <button type="submit" name="action" value="recompile" class="pa-btn pa-btn--ghost">Actualiser les textes</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= $h(url($base . '/langue')) ?>" class="ctba-new">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="pa-field">
                        <label for="ctba-code">Code de la langue</label>
                        <p class="pa-hint">Deux lettres, par exemple es.</p>
                        <input id="ctba-code" type="text" name="code" maxlength="5" placeholder="es" required autocomplete="off" spellcheck="false">
                    </div>
                    <div class="pa-field">
                        <label for="ctba-new-name">Nom affiché</label>
                        <p class="pa-hint">Le nom dans cette langue, tel qu’il apparaîtra dans le sélecteur. Par exemple Español.</p>
                        <input id="ctba-new-name" type="text" name="name" maxlength="60" placeholder="Español">
                    </div>
                    <div class="pa-actions"><button type="submit" class="pa-btn pa-btn--solid">Ajouter la langue</button></div>
                </form>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.ctba-counts { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 1rem 0 0; padding: 0; list-style: none; }
.ctba-counts li { padding: 0.28rem 0.7rem; border-radius: 999px; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.14); font-size: 0.82rem; }
.ctba-counts strong { font-variant-numeric: tabular-nums; }
.ctba-section { margin-top: 2rem; }
.ctba-people { display: grid; gap: 1rem; margin-top: 1rem; }
.ctba-person { margin: 0; padding: 1.15rem 1.2rem 1.2rem; border: 1px solid var(--pa-line); border-radius: 14px; background: var(--pa-surface); box-shadow: var(--pa-shadow); }
.ctba-person--pending { border-left: 4px solid var(--pa-tone-amber-fg); }
.ctba-person--active { border-left: 4px solid var(--pa-mint); }
.ctba-person--closed { border-left: 4px solid var(--pa-tone-slate-fg); }
.ctba-person__name { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; }
.ctba-person__next { margin: 0.4rem 0 0; font-weight: 700; }
.ctba-chips { display: flex; flex-wrap: wrap; gap: 0.45rem; margin: 0.85rem 0 0; padding: 0; list-style: none; }
.ctba-chip { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.5rem; padding: 0.35rem 0.6rem; border: 1px solid var(--pa-line); border-radius: 999px; background: var(--pa-surface-2); }
.ctba-grant { display: grid; gap: 0.55rem; margin-top: 1.1rem; }
.ctba-grant .pa-choice { margin: 0; }
.ctba-grant .pa-field { margin-top: 0; }
@media (min-width: 860px) {
  .ctba-grant { grid-template-columns: 1fr 1fr; }
  .ctba-grant__wide { grid-column: 1 / -1; }
}
.ctba-decide { display: grid; gap: 0.6rem; margin-top: 0.9rem; }
@media (min-width: 860px) { .ctba-decide { grid-template-columns: 1fr 1fr; } }
.ctba-decide form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.5rem; padding: 0.75rem; border: 1px dashed var(--pa-line); border-radius: 12px; }
.ctba-decide .pa-field { margin: 0; flex: 1 1 14rem; }
.ctba-decide .pa-actions { margin: 0; }
.ctba-podium { display: grid; gap: 0.75rem; margin: 1rem 0 0; padding: 0; list-style: none; }
@media (min-width: 860px) { .ctba-podium { grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: stretch; } }
.ctba-podium__card { display: flex; flex-direction: column; align-items: flex-start; gap: 0.4rem; padding: 1rem 1.05rem; border: 1px solid var(--pa-line); border-radius: 14px; background: var(--pa-surface-2); }
.ctba-podium__card.is-1 { background: var(--pa-mint-soft); border-color: var(--pa-mint); }
.ctba-podium__card.is-2 { border-color: var(--pa-tone-blue-fg); }
.ctba-podium__card.is-3 { border-color: var(--pa-tone-amber-fg); }
.ctba-podium__rank { font-size: 1.6rem; font-weight: 900; letter-spacing: -0.04em; line-height: 1; }
.ctba-podium__name { font-size: 1.15rem; }
.ctba-podium__roles { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.ctba-podium__meta { margin-top: auto; color: var(--pa-muted); font-size: 0.85rem; font-variant-numeric: tabular-nums; }
.ctba-rest { margin: 0.8rem 0 0; padding: 0; list-style: none; display: grid; gap: 0.4rem; }
.ctba-rest li { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem 0.7rem; padding: 0.55rem 0.75rem; border: 1px solid var(--pa-line); border-radius: 0.7rem; }
.ctba-rest__rank { width: 1.4rem; font-weight: 800; }
.ctba-rest__meta { margin-left: auto; color: var(--pa-muted); font-size: 0.85rem; }
.ctba-langs { display: grid; gap: 0.8rem; margin-top: 1rem; }
.ctba-langcard { padding: 1rem 1.05rem 1.1rem; border: 1px solid var(--pa-line); border-radius: 14px; background: var(--pa-surface-2); }
.ctba-langcard__head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem 0.6rem; }
.ctba-langcard__code { font-size: 1.2rem; font-weight: 900; letter-spacing: 0.04em; }
.ctba-bar { height: 0.45rem; margin: 0.7rem 0 0.35rem; border-radius: 999px; background: var(--pa-line); overflow: hidden; }
.ctba-bar > span { display: block; height: 100%; background: var(--pa-mint); }
.ctba-langform { display: grid; gap: 0.75rem; margin-top: 0.35rem; }
.ctba-langform .pa-choice { margin: 0; }
.ctba-langform .pa-field { margin-top: 0; }
@media (min-width: 860px) {
  .ctba-langform { grid-template-columns: minmax(0, 16rem) minmax(0, 1fr); align-items: end; }
  .ctba-langform__wide { grid-column: 1 / -1; }
}
.ctba-new { display: grid; gap: 0.75rem; margin-top: 1.15rem; padding-top: 1rem; border-top: 1px solid var(--pa-line); }
.ctba-new .pa-field { margin-top: 0; }
@media (min-width: 860px) { .ctba-new { grid-template-columns: 14rem minmax(0, 1fr) auto; align-items: end; } }
.ctba-person .pa-card__help { margin-top: 0.65rem; }
</style>
<script>
(function () {
    document.querySelectorAll('[data-ctba-person]').forEach(function (card) {
        var box = card.querySelector('input[name="roles[]"][value="translator"]');
        var langs = card.querySelector('[data-ctba-langs]');
        if (!box || !langs) { return; }
        var sync = function () { langs.hidden = !box.checked; };
        box.addEventListener('change', sync);
        sync();
    });
})();
</script>
