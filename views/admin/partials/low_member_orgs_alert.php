<?php
declare(strict_types=1);
// Organisations à supprimer (toujours 1 ou 2 membres après les rappels) : lecture légère de la liste posée par la
// tâche planifiée « org_low_member_reminders ». Bloc autonome : n'apparaît que s'il y a quelque chose à traiter.
if (!\App\Core\Gate::getInstance()->allows('admin.system')) {
    return;
}
$lowOrgs = ['count' => 0, 'names' => []];
try {
    $lowOrgsRepo = new \App\Repositories\LowMemberOrgRepository();
    if ($lowOrgsRepo->schemaReady()) {
        $lowOrgs = $lowOrgsRepo->listedSummary(3);
    }
} catch (\Throwable) {
    return;
}
if ($lowOrgs['count'] < 1) {
    return;
}
$hl = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$lowOrgsMore = $lowOrgs['count'] - count($lowOrgs['names']);
?>
<section id="hub-orgs-a-supprimer" class="pa-card scroll-mt-24" style="margin-bottom:1.25rem;" aria-labelledby="low-orgs-heading">
    <div class="pa-card__head">
        <span class="pa-icon pa-icon--rose" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></span>
        <div>
            <p class="pa-card__kicker">À traiter</p>
            <h2 id="low-orgs-heading" class="pa-card__title"><?= (int) $lowOrgs['count'] ?> organisation<?= $lowOrgs['count'] > 1 ? 's' : '' ?> à supprimer</h2>
            <p class="pa-card__help">
                Toujours un ou deux membres après le rappel à 3 mois et la relance à 4 mois :
                <span translate="no"><?= $hl(implode(', ', $lowOrgs['names'])) ?></span><?php if ($lowOrgsMore > 0): ?> <span><?= $hl(sprintf('et %d autre(s)', $lowOrgsMore)) ?></span><?php endif; ?>.
                Rien n’est supprimé automatiquement.
            </p>
        </div>
        <a class="pa-card__link" href="<?= $hl(url('admin/organisations-a-supprimer')) ?>">Voir la liste →</a>
    </div>
</section>
