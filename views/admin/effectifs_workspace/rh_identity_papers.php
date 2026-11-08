<?php
declare(strict_types=1);

/*
 * Effectifs > Documents RH > Documents d'identité (?vue=identite).
 * Papiers FICTIFS de COMSPEC Identity (jeu), regroupés par membre : type, état, lien vers la fiche ou l'atelier.
 * Les couvertures, alias et faux ne sont lus qu'avec l'habilitation Confidentiel (voir RhIdentityPapersOverview) :
 * ce qui est écarté n'apparaît dans aucun compteur. Aucune adresse e-mail, aucun numéro de papier.
 */

require base_path('views/admin/effectifs_workspace/partials/rh_ui_helpers.php');

$data = is_array($rhIdentityPapers ?? null) ? $rhIdentityPapers : ['ready' => false, 'truth' => false, 'members' => [], 'summary' => []];
$rights = is_array($rhIdentityRights ?? null) ? $rhIdentityRights : [];
$members = is_array($data['members'] ?? null) ? $data['members'] : [];
$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
$truth = !empty($data['truth']);
$sse = !empty($rights['sse']);
$q = trim((string) ($rhIdentityQuery ?? ''));
$onlyRenew = !empty($rhIdentityRenew);
$base = effectifs_workspace_url('documents-rh');
$norm = static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8');

$shown = [];
foreach ($members as $m) {
    if ($q !== '' && !str_contains($norm((string) $m['name']), $norm($q))) {
        continue;
    }
    $docs = is_array($m['documents'] ?? null) ? $m['documents'] : [];
    if ($onlyRenew) {
        $docs = array_values(array_filter($docs, static fn (array $d): bool => !empty($d['to_renew'])));
        if ($docs === []) {
            continue;
        }
    }
    $m['documents'] = $docs;
    $shown[] = $m;
}
?>
<?php $rhDocsTab = 'identite'; require base_path('views/admin/effectifs_workspace/partials/rh_documents_tabs.php'); ?>
<div class="rh-idp">
    <header class="rh-idp__head">
        <p class="eff-page-kicker">Dossier RH</p>
        <h2 class="eff-page-title">Documents d’identité</h2>
        <p class="eff-page-lead">Les papiers fictifs de COMSPEC Identity, créés pour le jeu. Ce ne sont pas de vraies pièces d’identité. La liste montre ce que vos droits permettent de voir.</p>
    </header>

<?php if (empty($data['ready'])): ?>
    <div class="rh-idp__empty" role="status">
        <p class="rh-idp__empty-title">Rien à afficher pour le moment</p>
        <p>Le registre des papiers n’est pas encore ouvert pour cette communauté.</p>
    </div>
<?php else: ?>
    <div class="rh-idp__tiles" role="group" aria-label="Synthèse des papiers">
        <div class="rh-idp__tile"><strong><?= (int) ($summary['members'] ?? 0) ?></strong><span>Membres avec des papiers</span></div>
        <div class="rh-idp__tile"><strong><?= (int) ($summary['documents'] ?? 0) ?></strong><span>Papiers au registre</span></div>
        <div class="rh-idp__tile<?= (int) ($summary['to_renew'] ?? 0) > 0 ? ' is-warn' : '' ?>"><strong><?= (int) ($summary['to_renew'] ?? 0) ?></strong><span>À renouveler ou expirés</span></div>
    </div>

    <form class="rh-idp__filters" method="get" action="<?= $h($base) ?>" role="search">
        <input type="hidden" name="vue" value="identite">
        <label class="rh-idp__field">
            <span class="rh-idp__sr">Rechercher un membre</span>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Rechercher un membre…" autocomplete="off" maxlength="80">
        </label>
        <label class="rh-idp__check">
            <input type="checkbox" name="renouveler" value="1"<?= $onlyRenew ? ' checked' : '' ?>>
            <span>Seulement les papiers à renouveler</span>
        </label>
        <button type="submit" class="ath-btn ath-btn--sm ath-btn--solid">Filtrer</button>
        <?php if ($q !== '' || $onlyRenew): ?><a class="ath-btn ath-btn--sm" href="<?= $h($base . '?vue=identite') ?>">Tout afficher</a><?php endif; ?>
    </form>

    <?php if ($shown === []): ?>
        <div class="rh-idp__empty" role="status">
            <p class="rh-idp__empty-title">Aucun papier à afficher</p>
            <p><?= ($q !== '' || $onlyRenew) ? 'Aucun membre ne correspond à ce filtre.' : 'Aucun membre n’a de papier visible avec vos droits.' ?></p>
        </div>
    <?php else: ?>
        <ul class="rh-idp__members">
            <?php foreach ($shown as $m): ?>
                <?php $fiche = effectifs_workspace_url('membres/' . (int) $m['id']) . '#papiers'; ?>
                <li class="rh-idp__member" id="membre-<?= (int) $m['id'] ?>">
                    <div class="rh-idp__who">
                        <h3 class="rh-idp__name"><?= $h((string) $m['name']) ?></h3>
                        <a class="rh-idp__link" href="<?= $h($fiche) ?>">Ouvrir la fiche</a>
                    </div>
                    <ul class="rh-idp__docs">
                        <?php foreach ($m['documents'] as $d): ?>
                            <?php $left = $d['days_left']; ?>
                            <li class="rh-idp__doc<?= !empty($d['to_renew']) ? ' is-warn' : '' ?>">
                                <span class="rh-idp__type"><?= $h((string) $d['type']) ?></span>
                                <span class="rh-idp__state rh-idp__state--<?= $h((string) $d['workflow']) ?>"><?= $h((string) $d['state']) ?></span>
                                <?php if ((string) $d['validity'] !== ''): ?><span class="rh-idp__tag rh-idp__tag--warn"><?= $h((string) $d['validity']) ?></span><?php endif; ?>
                                <?php if ((string) $d['report'] !== ''): ?><span class="rh-idp__tag rh-idp__tag--warn"><?= $h((string) $d['report']) ?></span><?php endif; ?>
                                <?php if ($left !== null && $left < 0): ?>
                                    <span class="rh-idp__tag rh-idp__tag--warn">Échu depuis <?= -$left ?> j</span>
                                <?php elseif ($left !== null && !empty($d['to_renew'])): ?>
                                    <span class="rh-idp__tag rh-idp__tag--warn">Expire dans <?= $left ?> j</span>
                                <?php elseif ((string) $d['expires_on'] !== ''): ?>
                                    <span class="rh-idp__tag">Valable jusqu’au <?= $h($rhWhen((string) $d['expires_on'])) ?></span>
                                <?php endif; ?>
                                <?php if ($truth && !empty($d['secret'])): ?><span class="rh-idp__tag rh-idp__tag--secret">Confidentiel</span><?php endif; ?>
                                <span class="rh-idp__go">
                                    <?php if ($sse && (int) $d['id'] > 0): ?>
                                        <a class="rh-idp__link" href="<?= $h(url('atak/sse/papiers/atelier/' . (int) $d['id'])) ?>">Ouvrir l’atelier</a>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php $rhShortcutCurrent = 'documents'; require base_path('views/admin/effectifs_workspace/partials/rh_shortcuts.php'); ?>
