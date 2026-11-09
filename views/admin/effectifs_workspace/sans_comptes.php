<?php
declare(strict_types=1);

/**
 * Effectifs › Sans compte (EffectifsWorkspaceController::sansComptes).
 *
 * @var list<array<string, mixed>> $ncRows
 * @var array<string, int> $ncCounts
 * @var array<string, string> $ncFilters
 * @var array<string, mixed>|null $ncSelected
 * @var list<array<string, string>> $ncHistory
 * @var list<array{id: int, label: string}> $ncAccounts
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statuses = \App\Services\Effectifs\NoAccountDirectory::STATUSES;
$sources = \App\Services\Effectifs\NoAccountDirectory::SOURCES;
$f = is_array($ncFilters ?? null) ? $ncFilters : [];
$sel = is_array($ncSelected ?? null) ? $ncSelected : null;
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
$base = effectifs_workspace_url('sans-comptes');
$eventLabels = ['created' => 'Fiche créée', 'edited' => 'Fiche corrigée', 'invited' => 'Invitation', 'merged' => 'Rattachée à un compte', 'imported' => 'Importée', 'note' => 'Note'];
$day = static fn (string $d): string => $d !== '' && strtotime($d) !== false ? date('d/m/Y', strtotime($d)) : '—';
?>
<section class="eff-nc" aria-labelledby="eff-nc-title">
    <header class="eff-nc__head">
        <div>
            <h2 id="eff-nc-title">Personnes sans compte</h2>
            <p>Les membres qui n’ont pas (encore) de compte : vous tenez leur fiche, vous pouvez les inviter à créer leur compte ou rattacher la fiche à un compte qui existe déjà.</p>
        </div>
    </header>
    <?php if ($flashErr): ?><p class="eff-nc__flash is-bad" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="eff-nc__flash" role="status"><?= $h($flashOk) ?></p><?php endif; ?>

    <nav class="eff-nc__chips" aria-label="Filtrer par statut">
        <a href="<?= $h($base) ?>" class="<?= $f['status'] === '' ? 'is-on' : '' ?>">Tous <b><?= (int) $ncTotal ?></b></a>
        <?php foreach ($statuses as $key => $label): ?>
            <a href="<?= $h($base . '?statut=' . $key) ?>" class="<?= $f['status'] === $key ? 'is-on' : '' ?>"><?= $h($label) ?> <b><?= (int) ($ncCounts[$key] ?? 0) ?></b></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= $h($base) ?>" class="eff-nc__filters">
        <label>Rechercher
            <input type="search" name="q" value="<?= $h($f['q']) ?>" maxlength="80" placeholder="Nom ou indicatif">
        </label>
        <label>Statut
            <select name="statut">
                <option value="">Tous</option>
                <?php foreach ($statuses as $key => $label): ?><option value="<?= $h($key) ?>"<?= $f['status'] === $key ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Origine
            <select name="origine">
                <option value="">Toutes</option>
                <?php foreach ($sources as $key => $label): ?><option value="<?= $h($key) ?>"<?= $f['source'] === $key ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>Trier par
            <select name="tri">
                <option value="activite"<?= $f['sort'] === 'activite' ? ' selected' : '' ?>>Dernière activité</option>
                <option value="nom"<?= $f['sort'] === 'nom' ? ' selected' : '' ?>>Nom</option>
                <option value="statut"<?= $f['sort'] === 'statut' ? ' selected' : '' ?>>Statut</option>
            </select>
        </label>
        <button type="submit" class="eff-nc__btn">Filtrer</button>
    </form>

    <?php if ($ncRows === []): ?>
        <p class="eff-nc__empty"><?= (int) $ncTotal === 0 ? 'Personne n’est sans compte pour l’instant : tous vos membres ont un compte actif.' : 'Aucune personne ne correspond à ces filtres.' ?></p>
    <?php else: ?>
        <div class="eff-nc__tablewrap">
            <table class="eff-nc__table">
                <thead>
                    <tr><th scope="col">Nom</th><th scope="col">Indicatif</th><th scope="col">Statut</th><th scope="col">Origine</th><th scope="col">Dernière activité</th><th scope="col"><span class="eff-sr">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php foreach ($ncRows as $r): ?>
                    <tr class="<?= $sel !== null && $sel['id'] === $r['id'] ? 'is-selected' : '' ?>">
                        <th scope="row"><a href="<?= $h(effectifs_workspace_url('membres/' . $r['id'])) ?>"><?= $h($r['name']) ?></a></th>
                        <td><?= $h($r['callsign'] !== '' ? $r['callsign'] : '—') ?></td>
                        <td><span class="eff-nc__state eff-nc__state--<?= $h($r['status']) ?>"><?= $h($r['status_label']) ?></span></td>
                        <td><?= $h($r['source_label']) ?></td>
                        <td><?= $h($day((string) $r['last_activity'])) ?></td>
                        <td><a class="eff-nc__link" href="<?= $h($base . '?fiche=' . $r['id']) ?>#fiche">Gérer</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($sel !== null): ?>
    <section class="eff-nc__panel" id="fiche" aria-labelledby="eff-nc-fiche">
        <h3 id="eff-nc-fiche"><?= $h($sel['name']) ?> <small><?= $h($sel['status_label']) ?></small></h3>
        <?php if ($ncCanEdit): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('sans-comptes/' . $sel['id'] . '/modifier')) ?>" class="eff-nc__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label>Nom affiché <input type="text" name="display_name" value="<?= $h($sel['name']) ?>" maxlength="100"></label>
            <label>Indicatif <input type="text" name="callsign" value="<?= $h($sel['callsign']) ?>" maxlength="50"></label>
            <button type="submit" class="eff-nc__btn">Enregistrer la correction</button>
        </form>
        <?php endif; ?>
        <?php if ($ncCanInvite): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('membres/' . $sel['id'] . '/invitations')) ?>" class="eff-nc__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="return_to" value="sans-comptes">
            <input type="hidden" name="single_use" value="1">
            <label>Validité du lien
                <select name="duration_hours"><option value="48">48 heures</option><option value="168">7 jours</option><option value="24">24 heures</option></select>
            </label>
            <button type="submit" class="eff-nc__btn">Inviter à créer son compte</button>
            <span class="eff-nc__hint">Le lien apparaît ensuite dans <a href="<?= $h(effectifs_workspace_url('invitations')) ?>">Invitations</a> pour être copié ou envoyé.</span>
        </form>
        <?php endif; ?>
        <?php if ($ncCanEdit && $sel['status'] !== \App\Services\Effectifs\NoAccountDirectory::STATUS_PENDING): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('sans-comptes/' . $sel['id'] . '/rattacher')) ?>" class="eff-nc__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label>Rattacher à un compte existant
                <input type="text" name="target" list="eff-nc-accounts" placeholder="Commencez à taper un nom" autocomplete="off" required>
            </label>
            <datalist id="eff-nc-accounts">
                <?php foreach ($ncAccounts as $a): ?><option value="<?= $h($a['label']) ?>"></option><?php endforeach; ?>
            </datalist>
            <label class="eff-nc__check"><input type="checkbox" name="confirm" value="1" required> Je confirme : le dossier de cette fiche passe sur ce compte, et cela ne se défait pas.</label>
            <button type="submit" class="eff-nc__btn eff-nc__btn--warn">Rattacher</button>
        </form>
        <?php endif; ?>
        <h4>Historique</h4>
        <?php if ($ncHistory === []): ?>
            <p class="eff-nc__empty">Aucun événement enregistré pour cette fiche.</p>
        <?php else: ?>
            <ol class="eff-nc__history">
                <?php foreach ($ncHistory as $e): ?>
                    <li><time datetime="<?= $h($e['created_at']) ?>"><?= $h(date('d/m/Y H:i', strtotime($e['created_at']) ?: 0)) ?></time>
                        <b><?= $h($eventLabels[$e['event']] ?? $e['event']) ?></b>
                        <?php if ($e['detail'] !== ''): ?> — <?= $h($e['detail']) ?><?php endif; ?>
                        <?php if ($e['actor_name'] !== ''): ?><span class="eff-nc__by">par <?= $h($e['actor_name']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="eff-nc__tools">
        <?php if ($ncCanCreate): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('sans-comptes/creer')) ?>" class="eff-nc__panel eff-nc__form">
            <h3>Nouvelle fiche rapide</h3>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label>Prénom <input type="text" name="first_name" maxlength="60"></label>
            <label>Nom <input type="text" name="last_name" maxlength="60"></label>
            <label>Indicatif <input type="text" name="callsign" maxlength="50"></label>
            <button type="submit" class="eff-nc__btn">Créer la fiche</button>
            <span class="eff-nc__hint">Grade, unité et autres détails se complètent ensuite sur la fiche.</span>
        </form>
        <?php endif; ?>
        <?php if ($ncCanCreate && $ncCanEdit): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('sans-comptes/importer')) ?>" enctype="multipart/form-data" class="eff-nc__panel eff-nc__form">
            <h3>Importer un tableur</h3>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label>Fichier CSV (500 lignes au plus) <input type="file" name="roster_file" accept=".csv,text/csv" required></label>
            <button type="submit" class="eff-nc__btn">Importer</button>
            <span class="eff-nc__hint">Les doublons (même nom ou même indicatif) sont ignorés. <a href="<?= $h(effectifs_workspace_url('modele-tableur')) ?>">Télécharger le modèle</a>.</span>
        </form>
        <?php endif; ?>
    </div>
</section>
