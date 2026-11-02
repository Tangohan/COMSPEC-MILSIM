<?php
declare(strict_types=1);

use App\Controllers\Admin\Organization\InvitationAdminController;
use App\Support\OrganizationRoleLabels;

/**
 * Back-office › Invitations : en-tête, jauge membres / plafond de la formule, chiffres, liens tout juste créés,
 * tableau filtrable et triable (renvoyer, révoquer, copier un nouveau lien) et assistant « Inviter des membres »
 * en trois étapes (destinataires, accès et arrivée, message et envoi).
 * Kit : views/admin/effectifs_workspace/partials/effk.php + effectifs-kit.css ; styles propres : invitations-admin.css ;
 * script : assets/js/invitations-admin.js.
 *
 * @var list<array<string, mixed>> $rolesOrganization
 * @var list<array<string, mixed>> $inviteUnits
 * @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions
 * @var list<array<string, mixed>> $inviteGrades
 * @var list<array<string, mixed>> $invitations
 * @var array{pending: int, accepted: int, revoked: int, expired: int, total: int} $inviteStatusCounts
 * @var array{unlimited: bool, limit: int, used: int, remaining: int|null, can_add: bool} $memberCapacity
 */
require_once base_path('views/admin/effectifs_workspace/partials/effk.php');

$rolesOrganization = is_array($rolesOrganization ?? null) ? $rolesOrganization : [];
$inviteUnits = is_array($inviteUnits ?? null) ? $inviteUnits : [];
$inviteJobRoleOptions = is_array($inviteJobRoleOptions ?? null) ? $inviteJobRoleOptions : [];
$inviteGrades = is_array($inviteGrades ?? null) ? $inviteGrades : [];
$invitations = is_array($invitations ?? null) ? $invitations : [];
$organizationRoleLabelMode = (string) ($organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR);
$counts = array_merge(['pending' => 0, 'accepted' => 0, 'revoked' => 0, 'expired' => 0, 'total' => 0], is_array($inviteStatusCounts ?? null) ? $inviteStatusCounts : []);
$cap = array_merge(['unlimited' => true, 'limit' => 0, 'used' => 0, 'remaining' => null, 'can_add' => true], is_array($memberCapacity ?? null) ? $memberCapacity : []);
$planName = (string) ($planName ?? '');
$canAdmin = !empty($canAdmin);
$currentUserId = (int) ($currentUserId ?? 0);
$filter = (string) ($inviteFilterStatus ?? '');
$capFull = !$cap['can_add'];
$canInvite = !$capFull && $rolesOrganization !== [];
$base = 'back-office/invitations';
$upgradeUrl = url('platform/upgrade') . '?from=max_members';
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$justLinks = json_decode((string) (\App\Core\Session::getFlash('invitation_links') ?? ''), true);
$justLinks = is_array($justLinks) ? $justLinks : [];

$statusInfo = [
    'pending' => ['En attente', 'warn'],
    'accepted' => ['Acceptée', 'ok'],
    'revoked' => ['Annulée', ''],
    'expired' => ['Expirée', ''],
];
$fmt = static function (?string $mysql): string {
    $t = $mysql !== null && $mysql !== '' ? strtotime($mysql) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};

// Rôles groupés par famille.
$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
foreach ($rolesOrganization as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    $rolesByLayer[in_array($ly, ['community', 'intra'], true) ? $ly : 'other'][] = $r;
}
$unitsById = [];
foreach ($inviteUnits as $u) {
    $unitsById[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
}
$jobsById = [];
foreach ($inviteJobRoleOptions as $jo) {
    $jobsById[(int) ($jo['id'] ?? 0)] = (string) ($jo['label'] ?? $jo['name'] ?? '');
}
$gradesById = [];
foreach ($inviteGrades as $g) {
    $gradesById[(int) ($g['id'] ?? 0)] = trim((string) ($g['label_long'] ?? '')) !== '' ? (string) $g['label_long'] : (string) ($g['label_short'] ?? '');
}

// Jauge membres.
$capPct = $cap['unlimited'] || (int) $cap['limit'] <= 0 ? 0 : (int) min(100, round((int) $cap['used'] / (int) $cap['limit'] * 100));
$capTone = $capFull ? 'is-bad' : ($capPct >= 80 ? 'is-warn' : '');
$acceptRate = $counts['total'] > 0 ? (int) round($counts['accepted'] / $counts['total'] * 100) : 0;
$statusUrl = static fn (string $s): string => url($base) . ($s !== '' ? '?status=' . rawurlencode($s) : '');

$linksUrl = url('back-office/recruitments/liens-invitation');
$invBtn = $canInvite
    ? '<button type="button" class="iath-b1" data-inv-open="inviter"><span>' . effk_e('Inviter des membres') . '</span>' . effk_icon('arrow') . '</button>'
    : '<button type="button" class="iath-b1" disabled title="' . effk_e($capFull ? 'Plafond de membres atteint' : 'Aucun rôle disponible') . '"><span>' . effk_e('Inviter des membres') . '</span></button>';
$capVal = $cap['unlimited'] ? 'illimité' : (string) (int) $cap['limit'];
?>
<div class="bo-eff-workspace inv-page">
<div class="effk iath" data-inv>
    <header class="iath-hero" aria-labelledby="inv-title">
        <div class="iath-hero__top">
            <div class="iath-hero__copy">
                <p class="iath-kk"><i></i><span>Membres · invitations personnelles</span></p>
                <h1 class="iath-title" id="inv-title">Invitations<span>.</span></h1>
                <p class="iath-lead">Invitez des personnes par e-mail avec leur <b>rôle</b> et leur <b>arrivée prévue</b> dans l’organigramme. Chaque lien est personnel et ne sert qu’une fois.</p>
            </div>
            <div class="iath-hero__acts">
                <?php if ($canAdmin): ?><a class="iath-b2" href="<?= effk_e($linksUrl) ?>">Liens partageables</a><?php endif; ?>
                <?= $invBtn ?>
            </div>
        </div>
        <ul class="iath-facts" role="list" aria-label="Chiffres des invitations">
            <li class="inv-fact-cap">
                <span class="iath-facts__k">Membres<?php if ($planName !== ''): ?> · <span translate="no"><?= effk_e($planName) ?></span><?php endif; ?></span>
                <span class="iath-facts__v<?= $capFull ? ' is-bad' : '' ?>"><?= (int) $cap['used'] ?><small>/ <?= effk_e($capVal) ?></small></span>
                <span class="inv-gauge <?= $capTone ?>"<?= $cap['unlimited'] ? ' data-unlimited' : '' ?> role="meter" aria-label="Membres sur le plafond de la formule" aria-valuemin="0" aria-valuemax="<?= $cap['unlimited'] ? (int) $cap['used'] : (int) $cap['limit'] ?>" aria-valuenow="<?= (int) $cap['used'] ?>"><i style="width:<?= $cap['unlimited'] ? 100 : $capPct ?>%"></i></span>
                <span class="iath-facts__h"><?php if ($cap['unlimited']): ?>Aucun plafond sur votre formule<?php elseif ($capFull): ?>Plafond atteint<?php else: ?><?= (int) $cap['remaining'] ?> place(s) restante(s)<?php endif; ?></span>
            </li>
            <li><a href="<?= effk_e($statusUrl('')) ?>"><span class="iath-facts__k">Envoyées</span><span class="iath-facts__v"><?= (int) $counts['total'] ?></span><span class="iath-facts__h"><?= $counts['revoked'] > 0 ? 'dont ' . (int) $counts['revoked'] . ' annulée(s)' : 'Depuis le début' ?></span></a></li>
            <li><a href="<?= effk_e($statusUrl('accepted')) ?>"><span class="iath-facts__k">Acceptées</span><span class="iath-facts__v<?= $counts['accepted'] > 0 ? ' is-ok' : '' ?>"><?= (int) $counts['accepted'] ?></span><span class="iath-facts__h"><?= $counts['total'] > 0 ? $acceptRate . ' % des envois' : 'Aucune pour l’instant' ?></span></a></li>
            <li><a href="<?= effk_e($statusUrl('pending')) ?>"><span class="iath-facts__k">En attente</span><span class="iath-facts__v<?= $counts['pending'] > 0 ? ' is-warn' : '' ?>"><?php if ($counts['pending'] > 0): ?><span class="iath-dot is-warn"></span><?php endif; ?><?= (int) $counts['pending'] ?></span><span class="iath-facts__h">Sans réponse</span></a></li>
            <li><a href="<?= effk_e($statusUrl('expired')) ?>"><span class="iath-facts__k">Expirées</span><span class="iath-facts__v"><?= (int) $counts['expired'] ?></span><span class="iath-facts__h">Délai dépassé, à renvoyer</span></a></li>
        </ul>
    </header>

    <div class="inv-msgs" data-inv-msg aria-live="polite">
        <?php if ($flashSuccess): ?><p class="effk-note is-info" role="status"><?= effk_e((string) $flashSuccess) ?></p><?php endif; ?>
        <?php if ($flashError): ?><p class="effk-note is-warn" role="alert"><?= effk_e((string) $flashError) ?></p><?php endif; ?>
    </div>

    <?php if ($capFull): ?>
        <p class="effk-note is-warn inv-full" role="alert">
            <?= effk_icon('alert') ?>
            <span><b>Plafond de membres atteint : <?= (int) $cap['used'] ?> / <?= (int) $cap['limit'] ?>.</b> Aucune nouvelle invitation ne peut être envoyée ni acceptée tant qu’une place n’est pas libérée. <a href="<?= effk_e($upgradeUrl) ?>">Passer à une offre supérieure</a></span>
        </p>
    <?php elseif ($rolesOrganization === []): ?>
        <p class="effk-note is-warn">Aucun rôle n’est encore disponible pour votre communauté. Configurez d’abord les rôles dans le back-office, ou contactez une personne administratrice si le problème persiste.</p>
    <?php endif; ?>

    <?php if ($justLinks !== []): ?>
    <section class="iath-ticket inv-just" aria-labelledby="inv-just-title">
        <div class="iath-ticket__h"><div><span class="iath-ticket__k">Liens tout juste envoyés</span><h2 class="iath-ticket__t" id="inv-just-title">À transmettre vous-même si besoin</h2></div></div>
        <div class="iath-perf"></div>
        <ul role="list">
        <?php foreach ($justLinks as $jl): if (!is_array($jl)) { continue; } ?>
            <li>
                <span class="inv-just__mail" translate="no"><?= effk_e((string) ($jl['email'] ?? '')) ?></span>
                <button type="button" class="iath-code" data-inv-copy="<?= effk_e((string) ($jl['url'] ?? '')) ?>" title="Copier le lien"><span translate="no"><?= effk_e((string) ($jl['url'] ?? '')) ?></span></button>
            </li>
        <?php endforeach; ?>
        </ul>
        <p class="iath-ticket__f"><?= effk_icon('alert') ?><span>Visibles <b>une seule fois</b> · chaque lien est personnel, ne le publiez pas.</span></p>
    </section>
    <?php endif; ?>

    <section class="inv-list" aria-label="Invitations envoyées">
    <?php if ($invitations !== []): ?>
        <div class="effk-bar" role="search">
            <label class="effk-search effk-search--wide">
                <?= effk_icon('search') ?>
                <span class="sr-only">Rechercher une invitation</span>
                <input type="search" placeholder="Adresse, rôle, unité, expéditeur…" data-inv-f="q" autocomplete="off">
            </label>
            <label>
                <span class="sr-only">État</span>
                <select data-inv-f="status">
                    <option value=""<?= $filter === '' ? ' selected' : '' ?>>Tous les états (<?= (int) $counts['total'] ?>)</option>
                    <?php foreach ($statusInfo as $sk => $si): ?>
                        <option value="<?= effk_e($sk) ?>"<?= $filter === $sk ? ' selected' : '' ?>><?= effk_e($si[0]) ?> (<?= (int) $counts[$sk] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!$canAdmin): ?>
            <span class="effk-tip">Vous gérez les invitations que vous avez envoyées.</span>
            <?php endif; ?>
            <button type="button" class="effk-btn is-sm" data-inv-reset hidden>Effacer les filtres</button>
        </div>
        <p class="effk-count" aria-live="polite"><b data-inv-count><?= count($invitations) ?></b> <span>invitation(s) affichée(s)</span> <span class="effk-muted">sur <?= count($invitations) ?></span></p>

        <div class="effk-table-wrap">
            <table class="effk-table inv-table">
                <thead>
                    <tr>
                        <th scope="col"><button type="button" class="inv-sort" data-inv-sort="email">Destinataire</button></th>
                        <th scope="col"><button type="button" class="inv-sort" data-inv-sort="status">État</button></th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Arrivée prévue</th>
                        <th scope="col" aria-sort="descending"><button type="button" class="inv-sort" data-inv-sort="created" data-inv-desc>Envoyée le</button></th>
                        <th scope="col"><button type="button" class="inv-sort" data-inv-sort="expires">Expire le</button></th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody data-inv-rows>
                <?php foreach ($invitations as $i):
                    $id = (int) ($i['id'] ?? 0);
                    $st = (string) ($i['status'] ?? '');
                    $si = $statusInfo[$st] ?? ['État indéterminé', ''];
                    $email = (string) ($i['email'] ?? '');
                    $role = OrganizationRoleLabels::displayName(['name' => $i['role_name'] ?? '', 'label_en' => $i['role_label_en'] ?? ''], $organizationRoleLabelMode);
                    $pay = json_decode((string) ($i['invitation_payload'] ?? ''), true);
                    $pay = is_array($pay) ? $pay : [];
                    $arrival = [];
                    $uid = (int) ($pay['unit_id'] ?? 0);
                    if ($uid > 0 && isset($unitsById[$uid])) {
                        $lab = trim((string) ($pay['assignment_label'] ?? ''));
                        $arrival[] = $unitsById[$uid] . ($lab !== '' ? ' — ' . $lab : '');
                    }
                    $jid = (int) ($pay['personnel_job_role_id'] ?? 0);
                    if ($jid > 0 && isset($jobsById[$jid])) {
                        $arrival[] = $jobsById[$jid];
                    }
                    $gid = (int) ($pay['grade_id'] ?? 0);
                    if ($gid > 0 && isset($gradesById[$gid])) {
                        $arrival[] = $gradesById[$gid];
                    }
                    $inviter = (string) ($i['inviter_email'] ?? '');
                    $mine = $canAdmin || (int) ($i['invited_by_user_id'] ?? 0) === $currentUserId;
                    $created = (string) ($i['created_at'] ?? '');
                    $expires = (string) ($i['expires_at'] ?? '');
                    $search = mb_strtolower(implode(' ', array_merge([$email, $role, $inviter, $si[0]], $arrival)), 'UTF-8');
                    $hasMsg = trim((string) ($pay['message'] ?? '')) !== '';
                    ?>
                    <tr data-inv-row data-id="<?= $id ?>" data-email="<?= effk_e($email) ?>" data-status="<?= effk_e($st) ?>" data-created="<?= effk_e($created) ?>" data-expires="<?= effk_e($expires) ?>" data-search="<?= effk_e($search) ?>"<?= $filter !== '' && $filter !== $st ? ' hidden' : '' ?>>
                        <td data-label="Destinataire">
                            <span class="inv-who">
                                <b translate="no"><?= effk_e($email) ?></b>
                                <?php if ($inviter !== ''): ?><small>par <span translate="no"><?= effk_e($inviter) ?></span><?php if ($hasMsg): ?> · avec message<?php endif; ?></small><?php endif; ?>
                            </span>
                        </td>
                        <td data-label="État"><span class="iath-badge is-<?= effk_e($st) ?>"><?= effk_e($si[0]) ?></span></td>
                        <td data-label="Rôle"><?= $role !== '' ? effk_e($role) : '<span class="effk-muted">—</span>' ?></td>
                        <td data-label="Arrivée prévue"><?= $arrival !== [] ? effk_e(implode(' · ', $arrival)) : '<span class="effk-muted">—</span>' ?></td>
                        <td data-label="Envoyée le" class="inv-date"><?= effk_e($fmt($created)) ?></td>
                        <td data-label="Expire le" class="inv-date"><?= $st === 'pending' || $st === 'expired' ? effk_e($fmt($expires)) : '<span class="effk-muted">—</span>' ?></td>
                        <td class="inv-acts">
                            <?php if ($mine && $st === 'pending'): ?>
                                <form method="post" action="<?= effk_e(url($base . '/lien')) ?>" data-inv-link>
                                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_status" value="<?= effk_e($filter) ?>">
                                    <button type="submit" class="iath-code" title="Crée un nouveau lien et le copie : l’ancien lien envoyé par e-mail ne fonctionnera plus.">Copier le lien</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($mine && ($st === 'pending' || $st === 'expired')): ?>
                                <form method="post" action="<?= effk_e(url($base . '/renvoyer')) ?>" data-confirm="Renvoyer cette invitation ? Un nouveau lien remplace l’ancien.">
                                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_status" value="<?= effk_e($filter) ?>">
                                    <button type="submit" class="effk-btn is-sm">Renvoyer</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($mine && $st === 'pending'): ?>
                                <form method="post" action="<?= effk_e(url($base . '/revoke')) ?>" data-confirm="Révoquer cette invitation ? Le lien ne fonctionnera plus.">
                                    <?= \App\Core\Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_status" value="<?= effk_e($filter) ?>">
                                    <button type="submit" class="effk-btn is-sm inv-danger">Révoquer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-inv-none hidden>
            <div class="effk-empty"><b>Aucune invitation ne correspond.</b>Modifiez la recherche ou l’état.<p class="inv-empty-act"><button type="button" class="effk-btn is-sm" data-inv-reset>Effacer les filtres</button></p></div>
        </div>
    <?php else: ?>
        <div class="effk-empty inv-empty">
            <b>Aucune invitation pour l’instant.</b>
            <span>Invitez vos premiers membres par e-mail, ou créez un lien d’invitation partageable à publier sur Discord.</span>
            <p class="inv-empty-act">
                <?php if ($canAdmin): ?><a class="iath-b2 is-light" href="<?= effk_e($linksUrl) ?>">Liens partageables</a><?php endif; ?>
                <?php if ($canInvite): ?><button type="button" class="iath-b1" data-inv-open="inviter"><span>Inviter des membres</span><?= effk_icon('arrow') ?></button><?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    </section>

    <?php if ($canInvite): ?>
    <dialog class="effk-md inv-wiz iath-ticket" id="inviter" aria-labelledby="inv-wiz-title">
        <form method="post" action="<?= effk_e(url($base)) ?>" data-inv-wiz data-max="<?= InvitationAdminController::MAX_BATCH ?>" data-remaining="<?= $cap['unlimited'] ? '' : (int) $cap['remaining'] ?>" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <header class="effk-md__h">
                <div><span class="effk-k">Invitations</span><h2 id="inv-wiz-title">Inviter des membres</h2></div>
                <button type="button" class="effk-md__x" data-inv-close aria-label="Fermer"><?= effk_icon('x') ?></button>
            </header>
            <div class="inv-wiz__steps iath-steps">
                <?php $steps = [['label' => 'Destinataires', 'active' => true], ['label' => 'Accès et arrivée'], ['label' => 'Message et envoi']];
                require base_path('views/partials/ui/stepper.php'); ?>
            </div>
            <div class="effk-md__b inv-wiz__b">
                <section data-inv-step="1" aria-label="Étape 1 : destinataires">
                    <div class="effk-md__fl">
                        <label for="inv-emails">Adresses e-mail</label>
                        <textarea id="inv-emails" name="emails" rows="5" placeholder="prenom.nom@exemple.fr, autre@exemple.fr" autocomplete="off" spellcheck="false" data-inv-emails></textarea>
                        <small>Une ou plusieurs adresses (jusqu’à <?= InvitationAdminController::MAX_BATCH ?>), séparées par une virgule, un espace ou un retour à la ligne. Celles que les personnes utiliseront pour se connecter.</small>
                    </div>
                    <div class="inv-chips" data-inv-chips aria-live="polite"></div>
                    <?php if (!$cap['unlimited']): ?>
                        <p class="effk-note is-info"><span>Votre formule permet encore <b><?= (int) $cap['remaining'] ?></b> membre(s) (<?= (int) $cap['used'] ?> / <?= (int) $cap['limit'] ?>).</span></p>
                    <?php endif; ?>
                    <p class="inv-wiz__err" data-inv-err="1" role="alert" hidden></p>
                </section>

                <section data-inv-step="2" aria-label="Étape 2 : accès et arrivée" hidden>
                    <div class="effk-md__fl">
                        <label for="inv-role">Rôle principal</label>
                        <?php if (count($rolesOrganization) > 6): ?>
                        <label class="effk-search inv-wiz__search">
                            <?= effk_icon('search') ?>
                            <span class="sr-only">Rechercher un rôle</span>
                            <input type="search" placeholder="Rechercher un rôle…" data-inv-role-q autocomplete="off">
                        </label>
                        <?php endif; ?>
                        <select id="inv-role" name="role_id" required class="inv-sel" data-inv-role>
                            <option value="">Choisir un rôle…</option>
                            <?php foreach (['community', 'intra', 'other'] as $ly): if ($rolesByLayer[$ly] === []) { continue; } ?>
                            <optgroup label="<?= effk_e(OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode)) ?>">
                                <?php foreach ($rolesByLayer[$ly] as $r):
                                    $rid = (int) ($r['id'] ?? 0);
                                    if ($rid < 1) {
                                        continue;
                                    }
                                    $disp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                    $disp = $disp !== '' ? $disp : 'Rôle sans intitulé';
                                    $desc = trim((string) ($r['description'] ?? '')); ?>
                                    <option value="<?= $rid ?>" data-hint="<?= effk_e($desc) ?>" data-search="<?= effk_e(mb_strtolower($disp . ' ' . $desc, 'UTF-8')) ?>"><?= effk_e($disp) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <small data-inv-role-hint>Un seul rôle par invitation. Les habilitations réservées à l’équipe plateforme ne sont pas proposées.</small>
                    </div>
                    <div class="inv-grid">
                        <div class="effk-md__fl">
                            <label for="inv-grade">Grade d’entrée</label>
                            <select id="inv-grade" name="grade_id" class="inv-sel">
                                <option value="0">Grade habituel des recrues</option>
                                <?php foreach ($gradesById as $gid => $glabel): if ($gid < 1) { continue; } ?><option value="<?= (int) $gid ?>"><?= effk_e($glabel) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="effk-md__fl">
                            <label for="inv-expiry">Lien valable</label>
                            <select id="inv-expiry" name="expiry_days" class="inv-sel">
                                <?php foreach (InvitationAdminController::EXPIRY_DAYS as $d): ?><option value="<?= $d ?>"<?= $d === 7 ? ' selected' : '' ?>><?= $d ?> jour<?= $d > 1 ? 's' : '' ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="effk-md__fl">
                            <label for="inv-unit">Unité dans l’organigramme</label>
                            <select id="inv-unit" name="unit_id" class="inv-sel">
                                <option value="0">Aucune pour l’instant</option>
                                <?php foreach ($unitsById as $uid => $uname): if ($uid < 1) { continue; } ?><option value="<?= (int) $uid ?>"><?= effk_e($uname) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="effk-md__fl">
                            <label for="inv-assign">Libellé d’affectation</label>
                            <input id="inv-assign" type="text" name="assignment_label" maxlength="120" value="Membre" placeholder="Ex. membre d’équipe, opérateur…" class="inv-input">
                        </div>
                        <?php if ($jobsById !== []): ?>
                        <div class="effk-md__fl inv-grid__full">
                            <label for="inv-job">Fonction sur la fiche personnel</label>
                            <select id="inv-job" name="personnel_job_role_id" class="inv-sel">
                                <option value="0">Aucune pour l’instant</option>
                                <?php foreach ($jobsById as $jid => $jlabel): if ($jid < 1) { continue; } ?><option value="<?= (int) $jid ?>"><?= effk_e($jlabel) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="effk-tip">Unité, fonction et grade sont appliqués automatiquement quand la personne accepte.</p>
                    <p class="inv-wiz__err" data-inv-err="2" role="alert" hidden>Choisissez un rôle.</p>
                </section>

                <section data-inv-step="3" aria-label="Étape 3 : message et envoi" hidden>
                    <div class="effk-md__fl">
                        <label for="inv-message">Message personnel <span class="effk-muted">(facultatif)</span></label>
                        <textarea id="inv-message" name="message" rows="4" maxlength="<?= InvitationAdminController::MESSAGE_MAX ?>" placeholder="Ex. Bienvenue ! Rendez-vous samedi 21 h sur le serveur."></textarea>
                        <small>Ajouté à l’e-mail d’invitation, <?= InvitationAdminController::MESSAGE_MAX ?> caractères au plus.</small>
                    </div>
                    <dl class="inv-sum" data-inv-sum>
                        <div><dt>Destinataires</dt><dd data-sum="emails">—</dd></div>
                        <div><dt>Rôle</dt><dd data-sum="role">—</dd></div>
                        <div><dt>Grade d’entrée</dt><dd data-sum="grade">—</dd></div>
                        <div><dt>Unité</dt><dd data-sum="unit">—</dd></div>
                        <div><dt>Lien valable</dt><dd data-sum="expiry">—</dd></div>
                    </dl>
                </section>
            </div>
            <footer class="effk-md__f inv-wiz__f">
                <span class="inv-wiz__where" data-inv-where>Étape 1 sur 3</span>
                <button type="button" class="iath-b2 is-light" data-inv-prev hidden>Précédent</button>
                <button type="button" class="iath-b1" data-inv-next><span>Suivant</span><?= effk_icon('arrow') ?></button>
                <button type="submit" class="iath-b1" data-inv-submit hidden><span>Envoyer les invitations</span><?= effk_icon('arrow') ?></button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <div class="iath-toast" data-inv-toast role="status" aria-live="polite"></div>

    <template data-inv-texts>
        <span data-t="copied">Lien copié dans le presse-papiers.</span>
        <span data-t="copy-fail">Copie impossible : sélectionnez le lien et copiez-le à la main.</span>
        <span data-t="link-confirm">Créer un nouveau lien et le copier ? Le lien déjà envoyé par e-mail ne fonctionnera plus.</span>
        <span data-t="error">Action impossible pour le moment. Réessayez.</span>
        <span data-t="valid">adresse(s) valide(s)</span>
        <span data-t="invalid">adresse(s) invalide(s) :</span>
        <span data-t="need-email">Indiquez au moins une adresse e-mail valide.</span>
        <span data-t="fix-invalid">Corrigez ou retirez les adresses invalides.</span>
        <span data-t="too-many">Trop d’adresses pour un seul envoi.</span>
        <span data-t="over-cap">Plus d’adresses que de places restantes sur votre formule.</span>
        <span data-t="step">Étape %1 sur 3</span>
        <span data-t="days">jour(s)</span>
        <span data-t="none">Aucune</span>
    </template>
</div>
</div>
<script src="<?= effk_e(asset_url('assets/js/invitations-admin.js')) ?>" defer></script>
