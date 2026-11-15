<?php
/**
 * Salon « Voie hiérarchique & contacts » : « Ma voie » (de l'unité du lecteur jusqu'en haut, chef par échelon, un clic pour
 * écrire), puis l'organigramme lisible (indentation, échelon, chef et indicatif, effectif disponible / total) et, pour chaque
 * unité, une carte de contact (chef, adjoint, messagerie JNET, fiche du personnel). Même arbre, filtré par confidentialité,
 * que partout dans JNET : une identité masquée n'a pas de lien. Sans JavaScript, chaque unité se déplie avec son élément
 * natif ; la recherche filtre au fil de la frappe.
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
$mine = is_array($view['mine'] ?? null) ? $view['mine'] : [];
$branch = !empty($view['branch']);
$viewerId = (int) ($view['viewerId'] ?? 0);
$mineId = (int) ($mine[0]['id'] ?? 0);
$pinByRef = [];
foreach ($pins as $p) {
    $pinByRef[(string) $p['ref']] = (int) $p['id'];
}
// Met en évidence le terme cherché (sans tenir compte de la casse).
$hl = static function (string $text) use ($q, $h): string {
    $at = $q !== '' ? mb_stripos($text, $q) : false;
    if ($at === false) {
        return $h($text);
    }

    return $h(mb_substr($text, 0, $at)) . '<mark>' . $h(mb_substr($text, $at, mb_strlen($q))) . '</mark>' . $h(mb_substr($text, $at + mb_strlen($q)));
};
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
$branchUrl = static fn (bool $on): string => url(JnetCommonRules::path('voie', ['q' => $q, 'branche' => $on ? '1' : '']));
?>
<div class="jc-body">
    <?php if ($mine !== []): ?>
        <section class="jc-mine" aria-labelledby="jc-mine-t">
            <h3 id="jc-mine-t" class="jc-h3">Ma voie</h3>
            <ol class="jc-chain">
                <?php foreach ($mine as $i => $step): ?>
                    <?php $lead = $step['leader']; ?>
                    <li class="jc-ch<?= $i === 0 ? ' is-me' : '' ?>"<?= (string) $step['accent'] !== '' ? ' style="--u:' . $h((string) $step['accent']) . '"' : '' ?>>
                        <span class="jc-ch__u">
                            <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $step['label'], 0, 2)), (string) $step['badge'], 'jc-logo') ?>
                            <b translate="no"><?= $h((string) $step['label']) ?></b>
                        </span>
                        <span class="jc-ch__who">
                            <?php if (trim((string) $lead['name']) !== ''): ?>
                                <span class="jc-person__face" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr(trim((string) $lead['name']), 0, 2))) ?></span>
                                <span class="jc-ch__nm"><?= (string) $lead['href'] !== '' ? '<a href="' . $h((string) $lead['href']) . '" translate="no">' . $h((string) $lead['name']) . '</a>' : '<span translate="no">' . $h((string) $lead['name']) . '</span>' ?><?= (string) $lead['callsign'] !== '' ? ' <small class="jc-mono" translate="no">' . $h((string) $lead['callsign']) . '</small>' : '' ?></span>
                                <?php if ((int) $lead['id'] > 0 && (int) $lead['id'] !== $viewerId): ?>
                                    <a class="jc-ac" href="<?= $h(url('jnet/courrier/nouveau?membre=' . (int) $lead['id'])) ?>"><?= JnetCommonRules::icon('msg') ?><span>Écrire</span></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="jc-nil">Poste non pourvu</span>
                            <?php endif; ?>
                        </span>
                        <span class="jc-ch__r"><?= $i === 0 ? 'Mon unité' : ($i === 1 ? 'Chef direct' : $h(JnetCommonRules::fmt('Échelon %1', $i + 1))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <form class="jc-filters" method="get" action="<?= $h(url('jnet')) ?>" role="search" aria-label="Chercher dans la voie hiérarchique">
        <input type="hidden" name="commun" value="1">
        <input type="hidden" name="salon" value="voie">
        <?php if ($branch): ?><input type="hidden" name="branche" value="1"><?php endif; ?>
        <label class="jc-search">
            <span class="ja-sr">Chercher une unité ou une personne</span>
            <?= JnetCommonRules::icon('search') ?>
            <input type="search" name="q" value="<?= $h($q) ?>" placeholder="Chercher une unité, un chef, un indicatif" autocomplete="off" data-jc-search data-jc-filter="[data-jc-row]">
        </label>
        <button type="submit" class="jn-btn jn-btn--small">Chercher</button>
        <?php if (!empty($view['hasBranch'])): ?>
            <nav class="jc-chips" aria-label="Étendue de l’arbre">
                <a class="jc-chip<?= !$branch ? ' is-on' : '' ?>" href="<?= $h($branchUrl(false)) ?>" data-jc-salon="voie"<?= !$branch ? ' aria-current="true"' : '' ?>>Tout l’arbre</a>
                <a class="jc-chip<?= $branch ? ' is-on' : '' ?>" href="<?= $h($branchUrl(true)) ?>" data-jc-salon="voie"<?= $branch ? ' aria-current="true"' : '' ?>>Ma branche</a>
            </nav>
        <?php endif; ?>
        <?php if ($q !== ''): ?><a class="jc-reset" href="<?= $h(url(JnetCommonRules::path('voie', ['branche' => $branch ? '1' : '']))) ?>" data-jc-salon="voie">Réinitialiser</a><?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <div class="jc-empty">
            <?php if (($q !== '' || $branch) && (int) ($view['total'] ?? 0) > 0): ?>
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
                $lead = $r['leader'];
                $unitStyle = (string) $r['accent'] !== '' ? ';--u:' . $h((string) $r['accent']) : '';
                $folded = JnetCommonRules::fold($r['label'] . ' ' . $r['code'] . ' ' . $r['echelonLabel'] . ' ' . $r['leader']['name'] . ' ' . $r['leader']['callsign'] . ' ' . $r['deputy']['name'] . ' ' . $r['deputy']['callsign']);
                $chainLabels = array_map(static fn (string $label): string => '<span translate="no">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>', array_filter((array) $r['chain'], static fn (string $label): bool => $label !== ''));
                ?>
                <details class="jc-node<?= $id === $mineId ? ' is-mine' : '' ?><?= $q !== '' && !empty($r['match']) ? ' is-match' : '' ?>" id="v-<?= $id ?>" data-jc-row data-jc-q="<?= $h($folded) ?>" style="--d:<?= (int) $r['depth'] ?><?= $unitStyle ?>"<?= $q !== '' && !empty($r['match']) ? ' open' : '' ?>>
                    <summary class="jc-node__sum">
                        <span class="jc-node__caret"><?= JnetCommonRules::icon('caret') ?></span>
                        <?= JnetUi::crest(mb_strtoupper(mb_substr((string) $r['label'], 0, 2)), (string) $r['badge'], 'jc-logo') ?>
                        <span class="jc-node__id">
                            <strong translate="no"><?= $hl((string) $r['label']) ?></strong>
                            <span class="jc-node__sub"><?= $h((string) $r['echelonLabel']) ?><?= $id === $mineId ? ((string) $r['echelonLabel'] !== '' ? ' · ' : '') . 'vous êtes ici' : '' ?></span>
                        </span>
                        <span class="jc-node__who">
                            <?php if (trim((string) $lead['name']) !== ''): ?>
                                <span class="jc-person__face" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr(trim((string) $lead['name']), 0, 2))) ?></span>
                                <span translate="no"><?= $hl((string) $lead['name']) ?><?= (string) $lead['callsign'] !== '' ? ' <small class="jc-mono">' . $hl((string) $lead['callsign']) . '</small>' : '' ?></span>
                            <?php else: ?>
                                <span class="jc-nil">Non désigné</span>
                            <?php endif; ?>
                        </span>
                        <span class="jc-node__str" title="Effectif disponible sur effectif total"><b><?= (int) $r['strength']['available'] ?></b> / <?= (int) $r['strength']['total'] ?></span>
                        <span class="jc-node__ops">
                            <?php if ((int) $lead['id'] > 0 && (int) $lead['id'] !== $viewerId): ?>
                                <a class="jc-ac jc-ac--ic" href="<?= $h(url('jnet/courrier/nouveau?membre=' . (int) $lead['id'])) ?>" title="Écrire au chef"><?= JnetCommonRules::icon('msg') ?><span class="ja-sr">Écrire au chef</span></a>
                            <?php endif; ?>
                            <?php if ((string) $lead['callsign'] !== ''): ?>
                                <button type="button" class="jc-ac jc-ac--ic jc-jsonly" data-jc-copy="<?= $h((string) $lead['callsign']) ?>" title="Copier l’indicatif"><?= JnetCommonRules::icon('copy') ?><span class="ja-sr">Copier l’indicatif</span></button>
                            <?php endif; ?>
                        </span>
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
