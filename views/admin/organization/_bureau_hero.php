<?php
declare(strict_types=1);

/**
 * Présentation du mode bureau des effectifs (tableur) sur le tableau de bord.
 * Affiché seulement si TenantTypeConfig::rosterBureauMode() est vrai (voir OrganizationDashboardController).
 * Masquage mémorisé par personne dans le navigateur (localStorage, sans migration).
 *
 * @var int $bureauHeroViewerId
 */
$bhUid = (int) ($bureauHeroViewerId ?? 0);
$bhKey = 'bureau-hero-' . $bhUid;
$bhRows = [
    ['1', 'Martin', 'Sgt', 'Alpha', 'sans', 'Sans compte'],
    ['2', 'Durand', 'Cpl', 'Alpha', 'inv', 'Invitation envoyée'],
    ['3', 'Lefèvre', 'Lt', 'Bravo', 'act', 'Compte actif'],
    ['4', 'Moreau', '1re cl.', 'Bravo', 'sans', 'Sans compte'],
];
?>
<section class="bhero" data-pil-dismissable="<?= htmlspecialchars($bhKey, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="bhero-title">
    <div class="bhero__copy">
        <h2 class="bhero__title" id="bhero-title">Nouveau : le Bureau des effectifs</h2>
        <p class="bhero__lead">Gérez vos effectifs comme dans un tableur, que vos membres aient un compte ou non.</p>
        <ul class="bhero__list">
            <li><b>Tableur des effectifs</b> : colonnes A, B, C, numéros de ligne, filtres et édition à plat.</li>
            <li><b>Membres sans compte</b> : une fiche suffit, sans échéance ni blocage, e-mail rattaché plus tard.</li>
            <li><b>Invitation et compte actif</b> : invitez depuis la ligne, la fiche bascule en compte actif à l’inscription.</li>
            <li><b>Import et export CSV</b> : travaillez dans Excel, réimportez avec un rapport ligne par ligne.</li>
        </ul>
        <div class="bhero__actions">
            <a class="bhero__btn bhero__btn--main" href="<?= htmlspecialchars(effectifs_desk_url(), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le Bureau des effectifs <span aria-hidden="true">→</span></a>
            <button type="button" class="bhero__btn" data-pil-dismiss>Masquer</button>
        </div>
    </div>
    <div class="bhero__sheet" aria-hidden="true">
        <div class="bhero__bar"><span>Effectifs</span><span>4 fiches</span></div>
        <table class="bhero__grid">
            <thead>
                <tr><th class="bhero__rn"></th><th>A</th><th>B</th><th>C</th><th>D</th></tr>
                <tr class="bhero__names"><th class="bhero__rn"></th><th>Nom</th><th>Grade</th><th>Unité</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bhRows as $bhR): ?>
                <tr<?= $bhR[0] === '3' ? ' class="is-sel"' : '' ?>>
                    <td class="bhero__rn"><?= $bhR[0] ?></td>
                    <td><?= htmlspecialchars($bhR[1], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="bhero__mono"><?= htmlspecialchars($bhR[2], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($bhR[3], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="bhero__tag bhero__tag--<?= $bhR[4] ?>"><?= htmlspecialchars($bhR[5], ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
