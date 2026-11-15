<?php
/**
 * Salon « Voie hiérarchique & contacts » : l'organigramme lisible (indentation, échelon, chef et adjoint, effectif
 * disponible / total, unité supérieure) et, pour chaque unité, une carte de contact (chef, adjoint, indicatifs, messagerie
 * JNET, fiche du personnel). Même arbre, filtré par confidentialité, que partout dans JNET : une identité masquée n'a pas
 * de lien. Sans JavaScript, chaque unité se déplie avec son élément natif ; la recherche filtre au fil de la frappe.
 *
 * @var array<string, mixed> $view
 * @var list<array<string, mixed>> $pins
 * @var bool $canPin
 * @var string $salon
 */

use App\Services\Jnet\JnetCommonRules;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rows = is_array($view['rows'] ?? null) ? $view['rows'] : [];
$q = (string) ($view['q'] ?? '');
$pinByRef = [];
foreach ($pins as $p) {
    $pinByRef[(string) $p['ref']] = (int) $p['id'];
}
$people = static function (array $p, string $role) use ($h): string {
    if (trim((string) $p['name']) === '') {
        return '<div class="jc-person jc-person--empty"><span class="jc-person__role">' . $h($role) . '</span><span class="jc-nil">Poste non pourvu</span></div>';
    }
    $initials = mb_strtoupper(mb_substr(trim((string) $p['name']), 0, 2));
    $name = '<strong translate="no">' . $h((string) $p['name']) . '</strong>';
    $detail = trim(implode(' · ', array_filter([(string) $p['grade'], (string) $p['function']])));

    return '<div class="jc-person"><span class="jc-person__face" aria-hidden="true">' . $h($initials) . '</span><div class="jc-person__id"><span class="jc-person__role">' . $h($role) . '</span>'
        . ((string) $p['href'] !== '' ? '<a href="' . $h((string) $p['href']) . '">' . $name . '</a>' : $name)
        . ((string) $p['callsign'] !== '' ? '<span class="jc-person__call" translate="no">' . $h((string) $p['callsign']) . '</span>' : '')
        . ($detail !== '' ? '<span class="jc-person__det" translate="no">' . $h($detail) . '</span>' : '')
        . '</div></div>';
};
?>
<div class="jc-body">
    <form class="jc-filters" method="get" action="<?= $h(url('jnet')) ?>" role="search" aria-label="Chercher dans la voie hiérarchique">
        <input type="hidden" name="commun" value="1">
        <input type="hidden" name="salon" value="voie">
        <label class="jc-search">
            <span class="ja-sr">Chercher une unité ou une personne</span>
            <?= JnetCommonRules::icon('search') ?>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Chercher une unité, un chef, un indicatif (touche /)" autocomplete="off" data-jc-search data-jc-filter="[data-jc-row]">
        </label>
        <button type="submit" class="jn-btn jn-btn--small">Chercher</button>
        <?php if ($q !== ''): ?><a class="jc-reset" href="<?= $h(url(JnetCommonRules::path('voie'))) ?>" data-jc-salon="voie">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <div class="jc-empty">
            <?php if ($q !== '' && (int) ($view['total'] ?? 0) > 0): ?>
                <p><strong>Aucune unité ni personne ne correspond à cette recherche.</strong></p>
                <p><a href="<?= $h(url(JnetCommonRules::path('voie'))) ?>" data-jc-salon="voie">Afficher toute la voie hiérarchique</a></p>
            <?php else: ?>
                <p><strong>L’organigramme n’est pas encore renseigné.</strong></p>
                <p>Chaque unité de la chaîne de commandement apparaîtra ici avec son chef et son adjoint.</p>
                <p><a class="jn-btn" href="<?= $h(effectifs_workspace_url('chaine')) ?>">Ouvrir la chaîne de commandement</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="jc-tree" data-jc-tree>
            <?php foreach ($rows as $r): ?>
                <?php
                $id = (int) $r['id'];
                $unitStyle = (string) $r['accent'] !== '' ? ';--u:' . $h((string) $r['accent']) : '';
                $folded = JnetCommonRules::fold($r['label'] . ' ' . $r['code'] . ' ' . $r['echelonLabel'] . ' ' . $r['leader']['name'] . ' ' . $r['leader']['callsign'] . ' ' . $r['deputy']['name'] . ' ' . $r['deputy']['callsign']);
                $chainLabels = array_map(static fn (string $label): string => '<span translate="no">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>', array_filter((array) $r['chain'], static fn (string $label): bool => $label !== ''));
                ?>
                <details class="jc-node<?= $q !== '' && !empty($r['match']) ? ' is-match' : '' ?>" id="v-<?= $id ?>" data-jc-row data-jc-q="<?= $h($folded) ?>" style="--d:<?= (int) $r['depth'] ?><?= $unitStyle ?>"<?= $q !== '' && !empty($r['match']) ? ' open' : '' ?>>
                    <summary class="jc-node__sum">
                        <span class="jc-node__caret"><?= JnetCommonRules::icon('caret') ?></span>
                        <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo') ?>
                        <span class="jc-node__id">
                            <strong translate="no"><?= $h((string) $r['label']) ?></strong>
                            <span class="jc-node__sub"><?= $h((string) $r['echelonLabel']) ?><?= (string) $r['echelonLabel'] !== '' && (string) $r['leader']['name'] !== '' ? ' · ' : '' ?><?php if ((string) $r['leader']['name'] !== ''): ?><span translate="no"><?= $h((string) $r['leader']['name']) ?></span><?php endif; ?></span>
                        </span>
                        <span class="jc-node__str" title="Effectif disponible sur effectif total"><b><?= (int) $r['strength']['available'] ?></b> / <?= (int) $r['strength']['total'] ?></span>
                    </summary>
                    <div class="jc-node__body">
                        <p class="jc-node__chain" aria-label="Voie hiérarchique"><?php foreach ($chainLabels as $ci => $c): ?><?php if ($ci > 0): ?><span class="jc-sep" aria-hidden="true">›</span><?php endif; ?><?= $c ?><?php endforeach; ?></p>
                        <div class="jc-contact">
                            <?= $people($r['leader'], 'Chef') ?>
                            <?= $people($r['deputy'], 'Adjoint') ?>
                        </div>
                        <ul class="jc-facts">
                            <li><span>Effectif</span><b><?= (int) $r['strength']['available'] ?> disponibles sur <?= (int) $r['strength']['total'] ?></b></li>
                            <?php if ((string) $r['parentLabel'] !== ''): ?><li><span>Unité supérieure</span><a href="#v-<?= (int) $r['parentId'] ?>" translate="no"><?= $h((string) $r['parentLabel']) ?></a></li><?php endif; ?>
                            <?php if ((int) $r['children'] > 0): ?><li><span>Sous-unités</span><b><?= (int) $r['children'] ?></b></li><?php endif; ?>
                        </ul>
                        <div class="jc-actions">
                            <?php if (!empty($r['openable'])): ?><a class="jn-btn jn-btn--small" href="<?= $h((string) $r['href']) ?>">Ouvrir l’espace de l’unité</a><?php endif; ?>
                            <?php if ((int) $r['leader']['id'] > 0): ?><a class="jc-link" href="<?= $h(url('jnet/courrier/nouveau?membre=' . (int) $r['leader']['id'])) ?>"><?= JnetCommonRules::icon('mail') ?><span>Écrire au chef</span></a><?php endif; ?>
                            <?php if ((int) $r['deputy']['id'] > 0): ?><a class="jc-link" href="<?= $h(url('jnet/courrier/nouveau?membre=' . (int) $r['deputy']['id'])) ?>"><?= JnetCommonRules::icon('mail') ?><span>Écrire à l’adjoint</span></a><?php endif; ?>
                            <?php $pinType = 'unite'; $pinTarget = (string) $id; $pinId = (int) ($pinByRef['unite:' . $id] ?? 0); require base_path('views/jnet/commun/_pin.php'); ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
