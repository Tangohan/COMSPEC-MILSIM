<?php
declare(strict_types=1);

/**
 * Contributeurs et traduction communautaire du site (administration du site). Contrôleur :
 * SystemContributorsController. Demandes de rôle, rôles actifs, langues (publication dans le sélecteur de langue).
 *
 * @var list<array<string, mixed>> $contribRows
 * @var list<array{code: string, name: string, status: string, done: int, total: int, pending: int}> $contribLanguages
 * @var array<string, string> $contribDiscordRoles
 */
$ready = (bool) ($contribReady ?? false);
$rows = is_array($contribRows ?? null) ? $contribRows : [];
$langs = is_array($contribLanguages ?? null) ? $contribLanguages : [];
$discordRoles = is_array($contribDiscordRoles ?? null) ? $contribDiscordRoles : [];
$base = (string) ($contribBase ?? 'admin/system/contributeurs');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$roleNames = ['translator' => 'Traducteur', 'tester' => 'Testeur', 'content' => 'Créateur de contenu', 'developer' => 'Développeur', 'support' => 'Support'];
$statusPill = ['pending' => ['En attente', 'amber'], 'active' => ['Actif', 'mint'], 'rejected' => ['Refusé', 'rose'], 'revoked' => ['Retiré', 'slate']];
$pending = array_values(array_filter($rows, static fn (array $r): bool => (string) $r['status'] === 'pending'));
$active = array_values(array_filter($rows, static fn (array $r): bool => (string) $r['status'] === 'active'));
$closed = array_values(array_filter($rows, static fn (array $r): bool => in_array((string) $r['status'], ['rejected', 'revoked'], true)));

$renderRole = static function (array $r) use ($h, $base, $roleNames, $statusPill, $discordRoles): void {
    [$label, $tone] = $statusPill[(string) $r['status']] ?? ['?', 'slate'];
    $role = (string) $r['role'];
    $name = trim((string) ($r['callsign'] ?? '')) !== '' ? (string) $r['callsign'] : (string) ($r['display_name'] ?? ('Membre #' . $r['user_id']));
    ?>
    <article class="pa-card ctba-item" id="r<?= (int) $r['id'] ?>">
        <p class="pa-name"><?= $h($name) ?> <span class="pa-sub">· <?= $h($r['email'] ?? '') ?></span></p>
        <p class="pa-sub">
            <span class="pa-pill pa-pill--<?= $h($tone) ?>"><?= $h($label) ?></span>
            <strong><?= $h($roleNames[$role] ?? $role) ?></strong>
            <?php if ((string) $r['languages'] !== ''): ?> · langues <?= $h(strtoupper(str_replace(',', ', ', (string) $r['languages']))) ?><?php endif; ?>
            · demandé le <?= $h(substr((string) $r['created_at'], 0, 10)) ?>
            <?php if ((string) $r['discord_handle'] !== ''): ?> · Discord <strong><?= $h($r['discord_handle']) ?></strong> (rôle « <?= $h($discordRoles[$role] ?? '?') ?> »)<?php endif; ?>
            <?php if ((int) $r['public_credit'] !== 1): ?> · ne veut pas être cité<?php endif; ?>
        </p>
        <?php if ((string) $r['motivation'] !== ''): ?><p class="pa-card__help"><?= nl2br($h($r['motivation'])) ?></p><?php endif; ?>
        <?php if ((string) $r['admin_note'] !== ''): ?><p class="pa-card__help">Note : <?= $h($r['admin_note']) ?></p><?php endif; ?>
        <form method="post" action="<?= $h(url($base . '/role')) ?>" class="ctba-form">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <?php if ($role === 'translator'): ?>
            <div class="pa-field"><label>Langues</label><input type="text" name="languages" value="<?= $h($r['languages']) ?>" placeholder="es, de"></div>
            <?php endif; ?>
            <div class="pa-field"><label>Note (vue par le membre en cas de refus ou de retrait)</label><input type="text" name="note" maxlength="500"></div>
            <div class="pa-actions">
                <?php if ((string) $r['status'] !== 'active'): ?><button type="submit" name="action" value="accept" class="pa-btn pa-btn--solid">Accorder</button><?php endif; ?>
                <?php if ((string) $r['status'] === 'pending'): ?><button type="submit" name="action" value="reject" class="pa-btn pa-btn--line">Refuser</button><?php endif; ?>
                <?php if ((string) $r['status'] === 'active'): ?><button type="submit" name="action" value="revoke" class="pa-btn pa-btn--line">Retirer le rôle</button><?php endif; ?>
            </div>
        </form>
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
            <h1 class="pa-hero__title">Contributeurs et traduction du site</h1>
            <p class="pa-hero__lead">
                Les membres demandent un rôle de contributeur sur la page publique « Contribuer ». Un traducteur actif valide
                directement les textes de ses langues et accepte les propositions des autres membres. Une langue publiée apparaît
                dans le sélecteur de langue du site ; un texte qu’elle n’a pas encore traduit s’affiche en anglais.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('contribuer')) ?>" target="_blank" rel="noopener">Page Contribuer</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('traduire-site')) ?>" target="_blank" rel="noopener">Espace de traduction</a>
                <a class="pa-btn pa-btn--ghost" href="#langues">Langues</a>
            </div>
        </header>
        <div class="pa-panel">
            <?php if ($ok): ?><p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p><?php endif; ?>
            <?php if ($err): ?><p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p><?php endif; ?>
            <?php if (!$ready): ?>
            <p class="pa-flash pa-flash--err">Tables absentes : lancez <code>php run-migrations.php</code> sur le serveur (migration 20261025000001_site_contributors_translation.sql).</p>
            <?php else: ?>
            <dl class="pa-stats">
                <div class="pa-stat"><dt>Demandes à traiter</dt><dd><?= count($pending) ?></dd></div>
                <div class="pa-stat"><dt>Contributeurs actifs</dt><dd><?= count(array_unique(array_map(static fn (array $r): int => (int) $r['user_id'], $active))) ?></dd></div>
                <div class="pa-stat"><dt>Langues</dt><dd><?= count($langs) ?></dd></div>
                <div class="pa-stat"><dt>Propositions en attente</dt><dd><?= array_sum(array_map(static fn (array $l): int => $l['pending'], $langs)) ?></dd></div>
            </dl>

            <section class="pa-card" aria-labelledby="ctba-pending">
                <p class="pa-card__kicker">Demandes</p>
                <h2 class="pa-card__title" id="ctba-pending">À traiter</h2>
                <?php if ($pending === []): ?><p class="par-empty">Aucune demande en attente.</p><?php endif; ?>
                <?php foreach ($pending as $r) { $renderRole($r); } ?>
            </section>

            <section class="pa-card" aria-labelledby="ctba-active">
                <p class="pa-card__kicker">Rôles</p>
                <h2 class="pa-card__title" id="ctba-active">Contributeurs actifs</h2>
                <p class="pa-card__help">Le rôle Discord se donne à la main sur le serveur (ou avec le script de rôles du Discord), d’après le pseudo indiqué.</p>
                <?php if ($active === []): ?><p class="par-empty">Aucun contributeur actif.</p><?php endif; ?>
                <?php foreach ($active as $r) { $renderRole($r); } ?>
            </section>

            <?php if ($closed !== []): ?>
            <details class="pa-card">
                <summary class="pa-card__title">Refusés et retirés (<?= count($closed) ?>)</summary>
                <?php foreach ($closed as $r) { $renderRole($r); } ?>
            </details>
            <?php endif; ?>

            <section class="pa-card" id="langues" aria-labelledby="ctba-langs">
                <p class="pa-card__kicker">Traduction du site</p>
                <h2 class="pa-card__title" id="ctba-langs">Langues</h2>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead><tr><th>Code</th><th>Nom (dans la langue)</th><th>Avancement</th><th>Propositions</th><th>Publication</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($langs as $l): ?>
                            <tr>
                                <td><strong><?= $h(strtoupper($l['code'])) ?></strong></td>
                                <?php if ($l['code'] === 'en'): ?>
                                <td>English (corrections de l’anglais)</td>
                                <td><?= (int) $l['done'] ?> corrections</td>
                                <td><?= (int) $l['pending'] ?></td>
                                <td>Toujours actives</td>
                                <td>
                                    <form method="post" action="<?= $h(url($base . '/langue')) ?>"><?= \App\Core\Csrf::field() ?><input type="hidden" name="code" value="en"><button type="submit" name="action" value="recompile" class="pa-btn pa-btn--ghost">Recompiler</button></form>
                                </td>
                                <?php else: ?>
                                <td colspan="5">
                                    <form method="post" action="<?= $h(url($base . '/langue')) ?>" class="ctba-lang">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="code" value="<?= $h($l['code']) ?>">
                                        <input type="text" name="name" value="<?= $h($l['name']) ?>" maxlength="60" aria-label="Nom de la langue">
                                        <span><?= $l['total'] > 0 ? (int) floor($l['done'] * 100 / $l['total']) : 0 ?> % (<?= (int) $l['done'] ?> / <?= (int) $l['total'] ?>)</span>
                                        <span><?= (int) $l['pending'] ?> en attente</span>
                                        <label><input type="checkbox" name="published" value="1"<?= $l['status'] === 'published' ? ' checked' : '' ?>> Publiée</label>
                                        <button type="submit" name="action" value="save" class="pa-btn pa-btn--solid">Enregistrer</button>
                                        <button type="submit" name="action" value="recompile" class="pa-btn pa-btn--ghost">Recompiler</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <form method="post" action="<?= $h(url($base . '/langue')) ?>" class="ctba-lang ctba-lang--new">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="pa-field"><label for="ctba-code">Code</label><input id="ctba-code" type="text" name="code" maxlength="5" placeholder="es" required></div>
                    <div class="pa-field"><label for="ctba-name">Nom dans la langue</label><input id="ctba-name" type="text" name="name" maxlength="60" placeholder="Español"></div>
                    <div class="pa-actions"><button type="submit" class="pa-btn pa-btn--solid">Ajouter la langue</button></div>
                </form>
                <p class="pa-card__help">Publier une langue à faible avancement est possible : tout texte non traduit s’affiche en anglais.</p>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
.ctba-item { margin-top: 0.75rem; }
.ctba-form { display: grid; gap: 0.5rem; margin-top: 0.5rem; }
.ctba-lang { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.9rem; }
.ctba-lang input[type="text"] { min-width: 10rem; }
.ctba-lang--new { margin-top: 1rem; align-items: flex-end; }
</style>
