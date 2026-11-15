<?php
declare(strict_types=1);
/**
 * Onglet « Détections » de la fiche identité : le système a repéré l'identité sous avis de recherche actif dans le jeu ou sur
 * le site. Lecture : accès au portail ; prise en compte : gestion des fichiers SSE. Inclus par identity_show.php.
 *
 * @var list<array<string,mixed>> $detections
 * @var int $iid
 */
use App\Core\Csrf;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseWantedCatalog as W;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dlang = (string) ($dlang ?? 'fr') === 'en' ? 'en' : 'fr';
$detections = is_array($detections ?? null) ? $detections : [];
$canHandle = ($wantedRights ?? null) instanceof SseWantedAccess && $wantedRights->canManage();
$fmt = static function (?string $v) use ($dlang): string {
    $v = trim((string) $v);
    $ts = $v !== '' ? strtotime($v . ' UTC') : false;

    return $ts === false ? '—' : gmdate($dlang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
?>
<p class="muted">Quand cette identité réapparaît pendant qu’un avis de recherche est actif (contrôle d’identité, scan SEEK II, consultation des fichiers, photo, téléphone ou plaque rattachés), le système crée une détection. Une détection ne modifie jamais l’identité. <a class="link" href="<?= $h(url('atak/sse/avis/detections')) ?>">Toutes les détections</a></p>
<?php if (isset($wantedReadable) && !$wantedReadable): ?>
    <div class="dos-note dos-note--info">Les détections relèvent de la section « Fichiers et sujets » : votre accès actuel ne permet pas de les lire.</div>
<?php elseif ($detections === []): ?>
    <div class="dos-card"><div class="dos-empty"><strong>Aucune détection</strong><?= !empty($wantedReady) ? 'Aucune apparition de cette identité n’a été repérée sous avis de recherche.' : 'Les détections seront disponibles une fois l’étape de base de données des avis de recherche passée.' ?></div></div>
<?php else: ?>
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Type</th><th>Source</th><th>Lieu</th><th>Opérateur</th><th>Réf. de l’avis</th><th>État</th><?php if ($canHandle): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($detections as $d): ?>
            <tr>
                <td data-label="Date"><?= $h($fmt($d['created_at'])) ?></td>
                <td data-label="Type"><?= $h(W::label(W::DETECTION_TYPES, (string) $d['type'], $dlang)) ?><?php if ((int) $d['repeat_count'] > 1): ?> <span class="muted">× <?= (int) $d['repeat_count'] ?></span><?php endif; ?></td>
                <td data-label="Source"><?= $h(W::label(W::DETECTION_SOURCES, (string) $d['source'], $dlang)) ?></td>
                <td data-label="Lieu" translate="no"><?= $h($d['place'] ?? '—') ?></td>
                <td data-label="Opérateur" translate="no"><?= $h($d['operator_label'] ?? '—') ?></td>
                <td data-label="Avis" translate="no"><?= $h($d['notice_ref']) ?></td>
                <td data-label="État"><span class="dos-status"><?= $h(W::label(W::DETECTION_STATUSES, (string) $d['status'], $dlang)) ?></span><?php if (($d['comment'] ?? null) !== null): ?><br><small class="muted" translate="no"><?= $h($d['comment']) ?></small><?php endif; ?></td>
                <?php if ($canHandle): ?>
                    <td>
                        <?php if ($d['status'] === 'new'): ?>
                            <form method="post" action="<?= $h(url('atak/sse/avis/detections/' . (int) $d['id'] . '/statut')) ?>" class="idp-actions">
                                <?= Csrf::field() ?>
                                <input type="text" name="comment" maxlength="500" placeholder="Commentaire (facultatif)" aria-label="Commentaire sur la détection">
                                <button class="btn" type="submit" name="status" value="acknowledged">Prendre en compte</button>
                                <button class="btn btn--ghost" type="submit" name="status" value="dismissed">Écarter</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
