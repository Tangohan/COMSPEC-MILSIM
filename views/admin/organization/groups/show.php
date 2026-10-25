<?php
declare(strict_types=1);

/**
 * Fiche d’un groupe : en-tête aux couleurs du groupe, chiffres clés, membres et aperçu de la carte publique.
 *
 * @var array<string, mixed>|null $group
 * @var list<array<string, mixed>> $members
 * @var array<string, mixed>|null $commander
 * @var array<string, mixed>|null $parent
 */

$group = $group ?? null;
$members = array_values(is_array($members ?? null) ? $members : []);
$commander = $commander ?? null;
$parent = is_array($parent ?? null) ? $parent : null;
if (!$group) {
    echo '<p>Groupe introuvable.</p>';
    return;
}
$gid = (int) $group['id'];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$nameOf = static function (array $u): string {
    $cs = trim((string) ($u['callsign'] ?? ''));
    $dn = trim((string) ($u['display_name'] ?? ''));

    return $cs !== '' ? $cs : ($dn !== '' ? $dn : (string) ($u['email'] ?? '—'));
};
$accent = trim((string) ($group['public_accent_color'] ?? ''));
$accent = preg_match('/^#[0-9a-f]{6}$/i', $accent) ? $accent : '#1e4f80';
$code = trim((string) ($group['code'] ?? ''));
$badge = $code !== '' ? mb_strtoupper(mb_substr($code, 0, 4)) : user_display_initials((string) $group['name'], 2);
$isPublic = !array_key_exists('show_on_public_page', $group) || (int) ($group['show_on_public_page'] ?? 0) === 1;
$hasBlurb = trim((string) ($group['public_blurb'] ?? '')) !== '';
$stateKey = !$isPublic ? 'off' : ($hasBlurb ? 'ok' : 'todo');
$stateLabel = ['ok' => 'Visible sur la page publique', 'todo' => 'Visible, présentation à écrire', 'off' => 'Masqué de la page publique'][$stateKey];
$capacity = (int) ($group['public_capacity'] ?? 0);
$count = count($members);
$commanderId = $commander ? (int) ($commander['id'] ?? 0) : 0;
usort($members, static function (array $a, array $b) use ($commanderId, $nameOf): int {
    $la = (int) ($a['id'] ?? 0) === $commanderId ? 0 : 1;
    $lb = (int) ($b['id'] ?? 0) === $commanderId ? 0 : 1;

    return $la <=> $lb ?: strcasecmp($nameOf($a), $nameOf($b));
});

$success = \App\Core\Session::get('success');
$error = \App\Core\Session::get('error');
\App\Core\Session::forget('success');
\App\Core\Session::forget('error');
?>
<?php if ($error): ?><p class="ath-flash ath-flash--err" role="alert"><?= $h((string) $error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="ath-flash ath-flash--ok" role="status"><?= $h((string) $success) ?></p><?php endif; ?>

<div class="bo-grp">
    <section class="bo-grp-hero" style="--grp-accent: <?= $h($accent) ?>">
        <div class="bo-grp-hero__band" aria-hidden="true"></div>
        <div class="bo-grp-hero__main">
            <span class="bo-grp-hero__badge" aria-hidden="true"><?= $h($badge) ?></span>
            <div class="bo-grp-hero__text">
                <h1 class="bo-grp-hero__title"><?= $h((string) $group['name']) ?></h1>
                <p class="bo-grp-hero__meta">
                    <span class="bo-grp-state bo-grp-state--<?= $stateKey ?>"><?= $stateLabel ?></span>
                    <?php if ($code !== ''): ?><span>Code <?= $h($code) ?></span><?php endif; ?>
                    <?php if ($parent): ?><span>Rattaché à <?= $h((string) ($parent['name'] ?? '')) ?></span><?php endif; ?>
                </p>
            </div>
            <div class="bo-grp-hero__actions">
                <a href="<?= $h(url('back-office/groups')) ?>" class="ath-btn">Tous les groupes</a>
                <a href="<?= $h(url('back-office/groups/' . $gid . '/edit')) ?>" class="ath-btn ath-btn--solid">Modifier</a>
                <form method="post" action="<?= $h(url('back-office/groups/' . $gid . '/delete')) ?>" onsubmit="return confirm('Supprimer ce groupe ? Les membres restent dans la communauté, seule leur affectation à ce groupe disparaît.');">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="bo-grp-btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </section>

    <div class="bo-grp-stats">
        <div class="bo-grp-stat">
            <div class="bo-grp-stat__label">Membres</div>
            <div class="bo-grp-stat__value"><?= $count ?><?= $capacity > 0 ? ' / ' . $capacity : '' ?></div>
            <div class="bo-grp-stat__note"><?= $capacity > 0 ? ($count >= $capacity ? 'Effectif complet' : ($capacity - $count) . ' place(s) libre(s)') : 'Pas d’effectif maximum' ?></div>
        </div>
        <div class="bo-grp-stat">
            <div class="bo-grp-stat__label">Responsable</div>
            <div class="bo-grp-stat__value"><?= $commander ? $h($nameOf($commander)) : '—' ?></div>
            <div class="bo-grp-stat__note"><?= $commander ? 'Chef du groupe' : 'À désigner dans « Modifier »' ?></div>
        </div>
        <div class="bo-grp-stat">
            <div class="bo-grp-stat__label">Adresse courte</div>
            <div class="bo-grp-stat__value" style="font-size:16px;"><?= $h((string) (($group['slug'] ?? '') !== '' ? $group['slug'] : '—')) ?></div>
            <div class="bo-grp-stat__note">Utilisée dans le lien public</div>
        </div>
    </div>

    <div class="bo-grp-split">
        <section class="bo-grp-panel" aria-labelledby="grp-members-title">
            <div class="bo-grp-panel__head">
                <h2 class="bo-grp-panel__title" id="grp-members-title">Membres (<?= $count ?>)</h2>
                <a href="<?= $h(url('back-office/ressources/effectifs')) ?>" class="ath-btn">Affecter depuis les effectifs</a>
            </div>
            <div class="bo-grp-panel__body">
                <?php if ($members === []): ?>
                <p class="bo-grp-note" style="margin:0;">Personne n’est encore affecté à ce groupe. L’affectation se fait dans le tableur des effectifs (colonne Affectation).</p>
                <?php else: ?>
                <ul class="bo-grp-members">
                    <?php foreach ($members as $m):
                        $mid = (int) ($m['id'] ?? 0);
                        $mName = $nameOf($m);
                        $sub = trim((string) ($m['role_name'] ?? ''));
                    ?>
                    <li class="bo-grp-member">
                        <a href="<?= $h(url('back-office/users/' . $mid)) ?>">
                            <span class="bo-grp-avatar" aria-hidden="true"><?= $h(user_display_initials($mName, 2)) ?></span>
                            <span style="min-width:0;">
                                <span class="bo-grp-member__name"><?= $h($mName) ?><?php if ($mid === $commanderId): ?><span class="bo-grp-member__lead">Chef</span><?php endif; ?></span><br>
                                <span class="bo-grp-member__sub"><?= $h($sub !== '' ? $sub : 'Membre') ?></span>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>

        <aside class="bo-grp-panel" aria-labelledby="grp-public-title">
            <div class="bo-grp-panel__head">
                <h2 class="bo-grp-panel__title" id="grp-public-title">Carte sur la page publique</h2>
            </div>
            <div class="bo-grp-panel__body">
                <?php $vitrineGroup = $group; require __DIR__ . '/_vitrine_preview.php'; ?>
                <p class="bo-grp-note">C’est ce que voient les visiteurs sur la vitrine de la communauté.</p>
            </div>
        </aside>
    </div>
</div>
