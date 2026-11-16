<?php
declare(strict_types=1);

/**
 * Tiroir « fiche du membre » du centre de gestion des accès : habilitation et son origine, entrée au portail, droits
 * effectifs par section (voir, écrire, gérer) avec leur origine, dérogations individuelles (retrait avec motif) et
 * historique des changements. Rendu dans la page (?membre=ID, sans JavaScript) ou seul (&fragment=fiche).
 *
 * @var array<string, mixed> $member SseAccessMemberRights::overview()
 * @var bool $canWrite droits du bloc « Accès SSE » de la fiche effectifs
 * @var int $selfId compte de la session
 */

use App\Controllers\Admin\AdminSseAccessController;
use App\Core\Csrf;
use App\Support\SseAccessCatalog as C;

$hd = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$m = is_array($member ?? null) ? $member : [];
$who = is_array($m['member'] ?? null) ? $m['member'] : ['user_id' => 0, 'label' => '', 'unit' => ''];
$uid = (int) $who['user_id'];
$clr = is_array($m['clearance'] ?? null) ? $m['clearance'] : [];
$isSelf = $uid === (int) ($selfId ?? 0);
$write = !empty($canWrite) && !$isSelf;
$short = ['interne' => 'CUI', 'encadrement' => 'C', 'confidentiel' => 'S', 'tres_restreint' => 'TS'];
$ruleState = ['scheduled' => 'À venir', 'expired' => 'Échue', 'active' => 'En cours'];
$histLabels = ['create' => 'Ajout', 'revoke' => 'Retrait', 'update' => 'Modification'];
$back = url(AdminSseAccessController::BASE) . '?onglet=personnels';
?>
<div class="bo-dialog__head sseh-drawer__head">
    <div class="bo-dialog__titles">
        <h2 class="bo-dialog__title" id="sseh-member-title"><?= $hd($who['label']) ?></h2>
        <p class="bo-dialog__sub"><?= $hd($who['unit'] !== '' ? $who['unit'] : 'Sans unité') ?></p>
    </div>
    <a class="bo-dialog__close" href="<?= $hd($back) ?>" data-bo-dialog-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
</div>
<div class="sseh-drawer__body">
    <section class="sseh-dsec" aria-labelledby="sseh-d-clr">
        <h3 class="sseh-dsec__title" id="sseh-d-clr">Habilitation</h3>
        <p class="sseh-dclr">
            <span class="sseacc-level sseacc-level--<?= $hd($short[$clr['level'] ?? 'interne'] ?? 'CUI') ?>"><?= $hd($clr['label'] ?? '') ?></span>
            <span class="sseh-dclr__line"><?= $hd($clr['source_label'] ?? '') ?></span>
        </p>
        <?php if (!empty($clr['profile_level'])): ?><p class="sseacc-note"><span>Niveau inscrit sur la fiche :</span> <b><?= $hd($clr['profile_label'] ?? '') ?></b></p><?php endif; ?>
        <?php if (is_array($clr['last'] ?? null)): ?><p class="sseacc-note"><span>Dernière modification :</span> <b><?= $hd($clr['last']['actor_label'] ?? '') ?></b> · <span class="sseacc-num"><?= $hd(substr((string) ($clr['last']['created_at'] ?? ''), 0, 10)) ?></span></p><?php endif; ?>
        <?php if ($isSelf): ?>
        <p class="sseacc-note">Votre propre habilitation et vos propres accès se font régler par un autre gestionnaire.</p>
        <?php elseif ($write): ?>
        <div class="sseh-dacts">
            <?php if (!empty($clr['may_change'])): ?><button type="button" class="sseacc-btn" data-bo-dialog-open="sseh-clearance" data-sseh-member="<?= $uid ?>" data-sseh-name="<?= $hd($who['label']) ?>" aria-haspopup="dialog">Changer l’habilitation</button><?php endif; ?>
            <button type="button" class="sseacc-btn" data-bo-dialog-open="sseh-access" data-sseh-member="<?= $uid ?>" data-sseh-name="<?= $hd($who['label']) ?>" data-sseh-effect="allow" aria-haspopup="dialog">Accorder un accès</button>
            <button type="button" class="sseacc-btn sseacc-btn--danger" data-bo-dialog-open="sseh-access" data-sseh-member="<?= $uid ?>" data-sseh-name="<?= $hd($who['label']) ?>" data-sseh-effect="deny" aria-haspopup="dialog">Restreindre</button>
            <a class="sseacc-btn" href="<?= $hd(effectifs_member_url($uid, 'acces-sse')) ?>">Fiche effectifs</a>
        </div>
        <?php endif; ?>
    </section>

    <section class="sseh-dsec" aria-labelledby="sseh-d-rights">
        <h3 class="sseh-dsec__title" id="sseh-d-rights">Droits par section</h3>
        <?php if (empty($m['entry'])): ?>
        <p class="sseh-dwarn">Pas de droit d’entrer au portail : les droits ci-dessous restent fermés tant que son rôle ou un code ne l’ouvre pas.</p>
        <?php endif; ?>
        <table class="sseh-dtbl">
            <caption class="sseacc-sr">Droits effectifs du membre, par section</caption>
            <thead><tr><th scope="col">Section</th><?php foreach (C::SIMPLE_ACCESS as $def): ?><th scope="col"><?= $hd($def['label']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ((array) ($m['rows'] ?? []) as $row): ?>
            <tr>
                <th scope="row"><?= $hd($row['label']) ?></th>
                <?php foreach (C::SIMPLE_ACCESS as $acc => $def): $cell = $row['cells'][$acc]; ?>
                <td>
                    <?php if (!$cell['applies']): ?><span class="sseh-na">—<span class="sseacc-sr">Sans objet</span></span>
                    <?php else:
                        $why = [];
                        foreach ($cell['origins'] as $o) {
                            $why[] = $o['kind'] === 'legacy' ? 'Droit d’origine' : (($o['effect'] === 'deny' ? 'Refus · ' : '') . $o['type_label'] . ' : ' . $o['label']);
                        }
                        ?>
                    <span class="sseh-yn <?= $cell['allowed'] ? 'is-yes' : 'is-no' ?>" title="<?= $hd(implode(' ; ', $why)) ?>"><?= $cell['allowed'] ? 'Oui' : 'Non' ?></span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="sseh-dsec" aria-labelledby="sseh-d-own">
        <h3 class="sseh-dsec__title" id="sseh-d-own">Dérogations individuelles</h3>
        <?php if (empty($m['own'])): ?>
        <p class="sseacc-note">Aucune : ses droits viennent de son rôle, de ses positions et de ses qualifications.</p>
        <?php else: ?>
        <ul class="sseh-rules">
            <?php foreach ($m['own'] as $r): $deny = $r['effect'] === C::EFFECT_DENY; ?>
            <li class="sseh-rule<?= $deny ? ' is-deny' : '' ?>">
                <p class="sseh-rule__main"><strong><?= $deny ? 'Refus' : 'Accord' ?> · <?= $hd($r['section_label']) ?> · <?= $hd($r['level_label']) ?></strong>
                <span class="sseacc-table__sub"><span><?= $hd($ruleState[$r['state']] ?? '') ?></span> · <?php if ($r['ends_at']): ?><span>jusqu’au</span> <span class="sseacc-num"><?= $hd(substr((string) $r['ends_at'], 0, 10)) ?></span><?php else: ?><span>sans date de fin</span><?php endif; ?><?php if ((string) ($r['reason'] ?? '') !== ''): ?> · <span><?= $hd($r['reason']) ?></span><?php endif; ?></span></p>
                <?php if ($write): ?>
                <form method="post" action="<?= $hd(url(AdminSseAccessController::BASE . '/personnels/' . $uid . '/regles/' . (int) $r['id'] . '/retirer')) ?>" class="sseh-rule__form">
                    <?= Csrf::field() ?>
                    <label class="sseacc-field"><span class="sseacc-sr">Motif du retrait</span><input type="text" name="reason" required minlength="5" maxlength="500" placeholder="Motif du retrait"></label>
                    <button type="submit" class="sseacc-btn sseacc-btn--danger">Retirer</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="sseh-dsec" aria-labelledby="sseh-d-hist">
        <h3 class="sseh-dsec__title" id="sseh-d-hist">Historique</h3>
        <?php if (empty($m['history'])): ?>
        <p class="sseacc-note">Aucun changement de droit enregistré pour ce membre.</p>
        <?php else: ?>
        <ol class="sseh-feed">
            <?php foreach (array_slice($m['history'], 0, 12) as $hrow): ?>
            <li class="sseh-feed__it">
                <span class="sseacc-event<?= $hrow['action'] === 'revoke' ? ' sseacc-event--revoke' : ' sseacc-event--issue' ?>"><?= $hd($histLabels[$hrow['action']] ?? 'Changement') ?></span>
                <span class="sseh-feed__who"><?= $hd(C::sectionLabel((string) $hrow['section'])) ?> · <?= $hd(C::levelLabel((string) $hrow['level'])) ?> <span class="sseacc-table__sub"><?= $hd($hrow['actor_label']) ?><?= $hrow['reason'] !== '' ? ' · ' . $hd($hrow['reason']) : '' ?></span></span>
                <span class="sseh-feed__when sseacc-num"><?= $hd(substr((string) $hrow['created_at'], 0, 16)) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
    </section>
</div>
