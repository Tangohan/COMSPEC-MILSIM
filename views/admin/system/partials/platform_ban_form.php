<?php
declare(strict_types=1);

/**
 * Champs du bannissement du site (sans la balise form, ajoutée par la page qui l'inclut).
 *
 * Variables attendues dans $banForm :
 *  - id (string)           préfixe des identifiants HTML
 *  - label (string)        nom affiché de la personne ('' = rempli par le script, fenêtre partagée du tableau)
 *  - email (string)        adresse ('' = remplie par le script)
 *  - fiches (int)          nombre de fiches qui passeront « inactif »
 *  - prior (int)           nombre de sanctions déjà prises contre la personne
 *  - history_url (string)  lien vers l'historique ('' = aucun)
 *  - supports_end (bool)   la base sait porter une date de fin
 *  - steam_id (string)     Steam ID lié ('' = case masquée)
 *  - known_ips (list)      adresses connues [ip, logins, last_seen, blocked]
 *  - shared (bool)         fenêtre partagée : l'adresse et le nom sont remplis au clic
 */
$banForm = is_array($banForm ?? null) ? $banForm : [];
$bh = static fn (string|int|float|null $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$bid = (string) ($banForm['id'] ?? 'ban');
$bLabel = (string) ($banForm['label'] ?? '');
$bEmail = (string) ($banForm['email'] ?? '');
$bFiches = (int) ($banForm['fiches'] ?? 0);
$bPrior = (int) ($banForm['prior'] ?? 0);
$bHistory = (string) ($banForm['history_url'] ?? '');
$bSupportsEnd = !empty($banForm['supports_end']);
$bSteam = (string) ($banForm['steam_id'] ?? '');
$bIps = is_array($banForm['known_ips'] ?? null) ? $banForm['known_ips'] : [];
$bShared = !empty($banForm['shared']);
$bTomorrow = gmdate('Y-m-d', time() + 86400);
$durations = [
    'definitif' => 'Sans fin',
    '1j' => '1 jour',
    '7j' => '7 jours',
    '30j' => '30 jours',
    '90j' => '90 jours',
    'date' => 'Jusqu’à une date',
];
?>
<div class="pu-ban" data-pu-ban>
    <div class="pu-warn" role="note">
        <p class="pu-warn__title">Avant de bannir <strong data-ban-label><?= $bh($bLabel) ?></strong></p>
        <ul class="pu-warn__list">
            <li>Ses sessions ouvertes sont fermées et toutes ses fiches passent « inactif », dans toutes ses communautés.</li>
            <li>L’adresse <strong data-ban-email><?= $bh($bEmail) ?></strong> ne peut plus se connecter ni créer de compte.</li>
            <li>Rien n’est effacé. Lever le bannissement remet tout comme avant, et une sanction avec date de fin se lève toute seule.</li>
            <?php if ($bPrior > 0 || $bShared): ?>
                <li class="pu-warn__alert" data-ban-prior-wrap<?= $bPrior > 0 ? '' : ' hidden' ?>>
                    Cette personne a déjà été sanctionnée <strong data-ban-prior><?= $bPrior > 0 ? $bh($bPrior) : '' ?></strong> fois.
                    <?php if ($bHistory !== ''): ?>
                        <a href="<?= $bh($bHistory) ?>">Voir l’historique</a>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <fieldset class="pu-dialog__field pu-duration">
        <legend>Durée de la sanction</legend>
        <div class="pu-duration__choices">
            <?php foreach ($durations as $value => $text): $value = (string) $value; ?>
                <?php $disabled = !$bSupportsEnd && $value !== 'definitif'; ?>
                <label class="pu-check<?= $disabled ? ' is-disabled' : '' ?>">
                    <input type="radio" name="duree" value="<?= $bh($value) ?>"<?= $value === 'definitif' ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
                    <?= $bh($text) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <label class="pu-dialog__sub">
            <span>Date de fin (la sanction court jusqu’à la fin de ce jour, heure de Paris)</span>
            <input type="date" name="fin" min="<?= $bh($bTomorrow) ?>" data-ban-date<?= $bSupportsEnd ? '' : ' disabled' ?>>
        </label>
        <?php if (!$bSupportsEnd): ?>
            <p class="pu-muted">Les sanctions avec date de fin arrivent avec la prochaine mise à jour de la base : pour l’instant, seul le bannissement sans fin est possible.</p>
        <?php endif; ?>
    </fieldset>

    <label class="pu-dialog__field">
        <span>Motif (obligatoire, gardé dans l’historique et le journal)</span>
        <textarea name="reason" rows="2" required minlength="5" maxlength="500" placeholder="Ex. insultes répétées, triche"></textarea>
    </label>

    <fieldset class="pu-dialog__field">
        <legend>Bloquer aussi</legend>
        <?php if ($bSteam !== '' || $bShared): ?>
            <label class="pu-check"><input type="checkbox" name="block_steam" value="1" checked> Son Steam ID dans le mod (s’il est lié)<?= $bSteam !== '' ? ' : ' . '<span class="pu-mono">' . $bh($bSteam) . '</span>' : '' ?></label>
        <?php endif; ?>
        <?php foreach ($bIps as $ipRow): ?>
            <?php if (!empty($ipRow['blocked'])) { continue; } ?>
            <label class="pu-check">
                <input type="checkbox" name="block_ips[]" value="<?= $bh($ipRow['ip'] ?? '') ?>">
                Adresse IP <span class="pu-mono"><?= $bh($ipRow['ip'] ?? '') ?></span>
                <span class="pu-muted">· <?= $bh($ipRow['logins'] ?? 0) ?> connexion(s), dernière le <?= $bh(substr((string) ($ipRow['last_seen'] ?? ''), 0, 10)) ?></span>
            </label>
        <?php endforeach; ?>
        <?php if ($bShared): ?>
            <p class="pu-muted">Pour bloquer aussi une adresse IP connue de cette personne, ouvrez son dossier complet (rubrique Bannissement).</p>
        <?php elseif ($bIps === []): ?>
            <p class="pu-muted">Aucune adresse IP de connexion connue sur les 6 derniers mois.</p>
        <?php endif; ?>
        <label class="pu-dialog__sub">
            <span>Autre adresse IP (facultatif)</span>
            <input type="text" name="extra_ips" placeholder="Ex. 203.0.113.10" autocomplete="off" inputmode="decimal">
        </label>
        <p class="pu-muted">Une adresse IP peut être partagée (box familiale, partage de connexion) : ne la bloquez que si vous êtes sûr.</p>
    </fieldset>

    <label class="pu-check pu-confirm-check">
        <input type="checkbox" name="confirm_ban" value="1" required id="<?= $bh($bid) ?>-confirm">
        J’ai lu l’avertissement : je confirme vouloir bannir cette personne.
    </label>
</div>
