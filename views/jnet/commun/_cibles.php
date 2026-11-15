<?php
/**
 * Salon « Cibles » : les dossiers d'intérêt suivis par le bureau SSE (priorité, statut, unité ou affiliation, dernière mise
 * à jour), avec un lien vers la fiche cible JNET existante. Même porte que le renseignement : sans elle, le salon est
 * verrouillé et ne montre rien.
 *
 * @var array<string, mixed> $view
 * @var string $salon
 * @var bool $canPin
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!empty($view['locked'])) {
    require base_path('views/jnet/commun/_locked.php');

    return;
}
$rows = is_array($view['rows'] ?? null) ? $view['rows'] : [];
$prio = (string) ($view['priorite'] ?? '');
$q = (string) ($view['q'] ?? '');
$priorities = ['critical' => 'Critique', 'high' => 'Élevée', 'medium' => 'À surveiller', 'low' => 'Courante'];
?>
<div class="jc-body">
    <form class="jc-filters" method="get" action="<?= $h(url('jnet')) ?>" role="search" aria-label="Chercher une cible">
        <input type="hidden" name="commun" value="1">
        <input type="hidden" name="salon" value="cibles">
        <?php if ($prio !== ''): ?><input type="hidden" name="priorite" value="<?= $h($prio) ?>"><?php endif; ?>
        <label class="jc-search">
            <span class="ja-sr">Chercher une cible</span>
            <?= JnetCommonRules::icon('search') ?>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Chercher une cible, un code, un alias (touche /)" autocomplete="off" data-jc-search data-jc-filter="[data-jc-row]">
        </label>
        <button type="submit" class="jn-btn jn-btn--small">Chercher</button>
        <span class="jc-bar__l" aria-hidden="true">Priorité</span>
        <nav class="jc-chips" aria-label="Priorité">
            <a class="jc-chip<?= $prio === '' ? ' is-on' : '' ?>" href="<?= $h(url(JnetCommonRules::path('cibles', ['q' => $q]))) ?>" data-jc-salon="cibles"<?= $prio === '' ? ' aria-current="true"' : '' ?>>Toutes</a>
            <?php foreach ($priorities as $key => $label): ?>
                <a class="jc-chip<?= $prio === $key ? ' is-on' : '' ?>" href="<?= $h(url(JnetCommonRules::path('cibles', ['priorite' => $key, 'q' => $q]))) ?>" data-jc-salon="cibles"<?= $prio === $key ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </form>

    <?php if ($rows === []): ?>
        <div class="jc-empty">
            <?php if ((int) ($view['total'] ?? 0) > 0): ?>
                <p><strong>Aucune cible ne correspond à ces filtres.</strong></p>
                <p><a href="<?= $h(url(JnetCommonRules::path('cibles'))) ?>" data-jc-salon="cibles">Afficher toutes les cibles</a></p>
            <?php else: ?>
                <p><strong>Aucun dossier de renseignement ouvert.</strong></p>
                <p>Les personnes et objectifs suivis par le bureau SSE apparaissent ici.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="jc-targets" data-jc-tree>
            <?php foreach ($rows as $t): ?>
                <?php
                $key = preg_replace('/[^a-z]/', '', strtolower((string) ($t['priority_key'] ?? 'low'))) ?: 'low';
                $folded = JnetCommonRules::fold(implode(' ', [(string) $t['name'], (string) $t['code'], (string) $t['alias'], (string) $t['org'], (string) $t['lastKnown']]));
                $seen = strtotime((string) ($t['lastSeen'] ?? '')) ?: 0;
                ?>
                <li class="jc-target jc-target--<?= $h($key) ?><?= (int) $t['pinId'] > 0 ? ' is-pinned' : '' ?>" data-jc-row data-jc-q="<?= $h($folded) ?>">
                    <span class="jc-target__mark" aria-hidden="true" translate="no"><?= $h(mb_strtoupper(mb_substr((string) $t['name'], 0, 2))) ?></span>
                    <div class="jc-target__main">
                        <h3 class="jc-target__t"><a href="<?= $h((string) $t['href']) ?>" translate="no"><?= $h((string) $t['name']) ?></a><?php if (trim((string) $t['code']) !== ''): ?> <small translate="no"><?= $h((string) $t['code']) ?></small><?php endif; ?></h3>
                        <p class="jc-target__meta">
                            <span class="jc-prio jc-prio--<?= $h($key) ?>"><?= $h((string) $t['priority']) ?></span>
                            <span><?= $h((string) $t['kind']) ?></span>
                            <?php if (trim((string) $t['status_label']) !== ''): ?><span translate="no"><?= $h((string) $t['status_label']) ?></span><?php endif; ?>
                        </p>
                        <p class="jc-target__sub">
                            <?php if (trim((string) $t['org']) !== ''): ?><span>Unité ou affiliation : <b translate="no"><?= $h((string) $t['org']) ?></b></span><?php endif; ?>
                            <?php if (trim((string) $t['lastKnown']) !== ''): ?><span translate="no"><?= $h((string) $t['lastKnown']) ?></span><?php endif; ?>
                            <?php if ($seen > 0): ?><span>Mise à jour <time datetime="<?= $h((string) $t['lastSeen']) ?>"><?= $h(date('d/m/Y', $seen)) ?></time></span><?php endif; ?>
                        </p>
                    </div>
                    <div class="jc-msg__tools">
                        <?php $pinType = 'cible'; $pinTarget = (string) $t['id']; $pinId = (int) $t['pinId']; require base_path('views/jnet/commun/_pin.php'); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="jc-note">
            <?= (int) ($view['shown'] ?? 0) ?> <?= (int) ($view['shown'] ?? 0) > 1 ? 'cibles' : 'cible' ?> sur <?= (int) ($view['total'] ?? 0) ?>.
            <a href="<?= $h(url('jnet/cibles')) ?>">Ouvrir la liste complète des cibles</a>
        </p>
    <?php endif; ?>
</div>
