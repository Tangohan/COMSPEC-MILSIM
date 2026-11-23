<?php

declare(strict_types=1);

$escapeSearch = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div data-dashboard-search-results>
    <?php if ($searchError !== null): ?>
        <p><?= $escapeSearch($searchError) ?></p>
    <?php elseif ($searchTooShort): ?>
        <p>Saisissez au moins deux caractères, ou un numéro.</p>
    <?php elseif ($searchAccounts === [] && $searchCommunities === []): ?>
        <p>Aucun compte ni communauté trouvé.</p>
    <?php else: ?>
        <div class="ph-search-results">
            <?php if ($searchAccounts !== []): ?>
                <section aria-label="Comptes trouvés">
                    <h3>Comptes</h3>
                    <div class="ph-dialog__links">
                        <?php foreach ($searchAccounts as $account): ?>
                            <?php $email = (string) $account['email']; ?>
                            <a href="<?= $escapeSearch($email !== '' ? url('admin/users/person') . '?email=' . rawurlencode($email) : url('admin/users/' . (int) $account['id'] . '/edit')) ?>">
                                <strong><?= $escapeSearch((string) ($account['display_name'] ?: ($account['callsign'] ?: $email ?: 'Compte n° ' . (int) $account['id']))) ?></strong>
                                <span><?= $escapeSearch(implode(' · ', array_filter([$email, (string) $account['community'], (string) $account['state']]))) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($searchCommunities !== []): ?>
                <section aria-label="Communautés trouvées">
                    <h3>Communautés</h3>
                    <div class="ph-dialog__links">
                        <?php foreach ($searchCommunities as $community): ?>
                            <a href="<?= $escapeSearch(url('admin/tenants/' . (int) $community['id'] . '/edit')) ?>">
                                <strong><?= $escapeSearch((string) $community['name']) ?></strong>
                                <span><?= $escapeSearch((string) ($community['community_code'] ?: $community['slug'])) ?> · <?= $community['archived'] ? 'Archivée' : 'Active' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
