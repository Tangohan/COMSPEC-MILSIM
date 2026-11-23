<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $rows */
$h = static fn (string|int|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$statusLabels = [
    'received' => 'Reçue', 'in_review' => 'En cours d’examen', 'info_requested' => 'Réponse demandée',
    'accepted' => 'Acceptée', 'upheld' => 'Maintenue', 'closed' => 'Clôturée',
];
$pill = ['received' => 'amber', 'in_review' => 'blue', 'info_requested' => 'rose', 'accepted' => 'mint', 'upheld' => 'slate', 'closed' => 'slate'];
$tenantNames = [];
foreach ($tenants as $tenantRow) {
    $tenantNames[(int) $tenantRow['id']] = (string) $tenantRow['name'];
}
$fmt = static fn (?string $utc): string => $utc ? \App\Services\Account\PlatformBanNotice::dateLabel($utc) : '';
?>
<div class="pa pu ps ac">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Contestations de bannissement</h1>
            <p class="pa-hero__lead"><?= $platform ? 'Tous les dossiers de la plateforme, avec leur référence et leur fil d’échange.' : 'Les dossiers des sanctions posées pour votre communauté. Vous échangez avec la personne ; la décision finale revient à l’administration de la plateforme.' ?></p>
            <div class="pa-hero__actions">
                <span class="pa-pill pa-pill--amber"><?= (int) $openCount ?> ouvert<?= $openCount > 1 ? 's' : '' ?></span>
                <span class="pa-pill pa-pill--rose"><?= (int) $unreadCount ?> non lu<?= $unreadCount > 1 ? 's' : '' ?></span>
                <?php if ($platform): ?>
                    <a class="pa-btn pa-btn--ghost" href="<?= $h(url($base . '/export') . '?' . http_build_query(['statut' => $filters['status'], 'assigne' => $filters['assigned'], 'anciennete' => $filters['age_days'], 'communaute' => $filters['tenant']])) ?>">Exporter en CSV</a>
                    <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/sanctions')) ?>">Bannissements</a>
                <?php endif; ?>
            </div>
        </header>
        <?php if ($success): ?><p class="pa-flash pa-flash--ok" role="status"><?= $h($success) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="pa-flash pa-flash--err" role="alert"><?= $h($error) ?></p><?php endif; ?>
        <?php if (!$ready): ?>
            <p class="pa-flash pa-flash--warn">Les dossiers de contestation demandent la migration « Contestations de bannissement : dossiers ». Les contestations restent traitables depuis la page Bannissements.</p>
        <?php else: ?>
        <section class="pa-card" aria-labelledby="ac-filters-title">
            <h2 class="pa-card__title" id="ac-filters-title">Filtres</h2>
            <form method="get" action="<?= $h(url($base)) ?>" class="ac-filters">
                <label class="pu-dialog__field"><span>Statut</span>
                    <select name="statut">
                        <?php foreach (['open' => 'Ouverts', 'unread' => 'Ouverts non lus', 'all' => 'Tous'] + $statusLabels as $value => $label): ?>
                            <option value="<?= $h($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="pu-dialog__field"><span>Ancienneté</span>
                    <select name="anciennete">
                        <?php foreach ([0 => 'Toutes', 3 => 'Plus de 3 jours', 7 => 'Plus de 7 jours', 30 => 'Plus de 30 jours'] as $value => $label): ?>
                            <option value="<?= $value ?>"<?= (int) $filters['age_days'] === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="pu-dialog__field"><span>Assigné</span>
                    <select name="assigne">
                        <?php foreach (['' => 'Tous', 'me' => 'À moi', 'none' => 'Non assignés'] as $value => $label): ?>
                            <option value="<?= $h($value) ?>"<?= $filters['assigned'] === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($platform): ?>
                <label class="pu-dialog__field"><span>Communauté</span>
                    <select name="communaute">
                        <option value="0">Toutes</option>
                        <?php foreach ($tenantNames as $tenantId => $tenantName): ?>
                            <option value="<?= (int) $tenantId ?>"<?= (int) $filters['tenant'] === $tenantId ? ' selected' : '' ?>><?= $h($tenantName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <button type="submit" class="pa-btn pa-btn--solid">Filtrer</button>
            </form>
        </section>

        <section class="pa-card" aria-labelledby="ac-list-title">
            <h2 class="pa-card__title" id="ac-list-title">Dossiers</h2>
            <?php if ($rows === []): ?>
                <p class="pa-empty-state">Aucun dossier ne correspond à ces filtres.</p>
            <?php else: ?>
            <div class="pa-table-wrap">
                <table class="pa-table ac-table">
                    <thead><tr><th scope="col">Référence</th><th scope="col">Statut</th><th scope="col">Déposé le</th><th scope="col">Dernière activité</th><?php if ($platform): ?><th scope="col">Communauté</th><?php endif; ?><th scope="col">Assigné</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr<?= (int) $row['unread_staff'] === 1 ? ' class="ac-unread"' : '' ?>>
                            <th scope="row"><a class="ac-ref" href="<?= $h(url($base . '/' . (int) $row['ban_id'])) ?>"><?= $h($row['reference']) ?></a><?= (int) $row['unread_staff'] === 1 ? ' <span class="pa-pill pa-pill--rose">Non lu</span>' : '' ?></th>
                            <td><span class="pa-pill pa-pill--<?= $h($pill[$row['status']] ?? 'slate') ?>"><?= $h($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                            <td><?= $h($fmt($row['created_at'])) ?></td>
                            <td><?= $h($fmt($row['updated_at'])) ?></td>
                            <?php if ($platform): ?><td><?= $h($tenantNames[(int) ($row['source_tenant_id'] ?? 0)] ?? '—') ?></td><?php endif; ?>
                            <td><?= (int) ($row['assigned_to'] ?? 0) > 0 ? 'Oui' : 'Non' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</div>
