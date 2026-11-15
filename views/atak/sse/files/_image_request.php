<?php
declare(strict_types=1);
/**
 * Demande d'accès adressée à la hiérarchie du membre (sous un cadre CONFIDENTIEL ÉLEVÉ ; jamais dans un lien).
 *
 * Dans un autre formulaire (planche de comparaison), $lockShells (tableau passé par référence) reçoit la coque du formulaire à poser
 * hors de celui-ci ; les champs lui sont reliés par l'attribut « form ».
 *
 * @var array<string, mixed>|null $lock voir SseMemberImageGate::lockInfo
 * @var string $csrfToken
 * @var string $lockBack adresse de retour (page des fichiers)
 * @var array<string, string>|null $lockShells
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!is_array($lock ?? null) || empty($lock['can_request'])) {
    return;
}
$lkId = 'fx-req-' . (int) $lock['owner'];
$lkInside = isset($lockShells) && is_array($lockShells);
$lkAttr = $lkInside ? ' form="' . $h($lkId) . '"' : '';
$lkAction = url('atak/sse/fichiers/acces-images/demande');
if ($lkInside && !isset($lockShells[$lkId])) {
    $lockShells[$lkId] = '<form id="' . $h($lkId) . '" method="post" action="' . $h($lkAction) . '"><input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '"><input type="hidden" name="owner" value="' . (int) $lock['owner'] . '"><input type="hidden" name="back" value="' . $h($lockBack ?? 'atak/sse/fichiers/photos') . '"></form>';
}
?>
<?php if (!empty($lock['pending'])): ?>
    <small class="fx-lkq">Demande en attente auprès de la hiérarchie.</small>
<?php else: ?>
    <details class="fx-lkq">
        <summary>Demander l’accès</summary>
        <?php if (!$lkInside): ?><form id="<?= $h($lkId) ?>" method="post" action="<?= $h($lkAction) ?>"><input type="hidden" name="_csrf_token" value="<?= $h($csrfToken ?? '') ?>"><input type="hidden" name="owner" value="<?= (int) $lock['owner'] ?>"><input type="hidden" name="back" value="<?= $h($lockBack ?? 'atak/sse/fichiers/photos') ?>"></form><?php endif; ?>
        <input class="fx-in" name="reason" maxlength="300" minlength="5" required placeholder="Motif de la demande" aria-label="Motif de la demande d’accès"<?= $lkAttr !== '' ? $lkAttr : ' form="' . $h($lkId) . '"' ?>>
        <button class="btn sm" type="submit" form="<?= $h($lkId) ?>">Envoyer à la hiérarchie</button>
    </details>
<?php endif; ?>
