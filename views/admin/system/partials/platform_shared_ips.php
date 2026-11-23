<?php
declare(strict_types=1);

$networkEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$networkBase = url('admin/users/ip-partagees');
$networkUrl = static fn (string $ip, int $page = 1): string => $networkBase . '?' . http_build_query(
    array_filter(['ip' => $ip, 'page' => $page > 1 ? $page : null])
);
?>
<div class="pa-network-results" data-network-results>
    <p class="pu-muted">Connexions réussies des <?= (int) $networkDays ?> derniers jours. Une même IP peut correspondre à un foyer, un réseau collectif ou un VPN.</p>
    <?php if ($networkIp !== ''): ?>
        <a class="pu-link" href="<?= $networkEscape($networkBase) ?>" data-network-view>Toutes les IP partagées</a>
    <?php endif; ?>
    <form action="<?= $networkEscape($networkBase) ?>" method="get" class="pa-network-search" data-network-search>
        <label for="network-ip-search">Rechercher une adresse IP</label>
        <div>
            <input type="search" name="ip" id="network-ip-search" maxlength="45" value="<?= $networkEscape($networkIp) ?>" placeholder="IPv4 ou IPv6" spellcheck="false" required>
            <button type="submit" class="pa-btn pa-btn--line pa-btn--sm">Rechercher</button>
        </div>
    </form>
    <?php if ($networkError !== null): ?>
        <p class="pa-flash pa-flash--err" role="alert"><?= $networkEscape($networkError) ?></p>
    <?php elseif ($networkRows === []): ?>
        <p class="pa-empty-state"><?= $networkIp === '' ? 'Aucune adresse partagée sur cette période.' : 'Aucun compte associé à cette adresse sur cette période.' ?></p>
    <?php else: ?>
        <ul class="pa-network-list">
            <?php foreach ($networkRows as $networkRow): ?>
                <?php $networkStamp = \App\Support\UtcDateTime::timestamp((string) $networkRow['last_seen']); ?>
                <li>
                    <?php if ($networkIp === ''): ?>
                        <a class="pu-link pu-mono" href="<?= $networkEscape($networkUrl((string) $networkRow['ip'])) ?>" data-network-view><?= $networkEscape((string) $networkRow['ip']) ?></a>
                        <strong><?= (int) $networkRow['people_count'] ?> comptes distincts</strong>
                    <?php else: ?>
                        <a class="pu-link" href="<?= $networkEscape(url('admin/users/person') . '?email=' . rawurlencode((string) $networkRow['email']) . '#reseau') ?>"><?= $networkEscape((string) $networkRow['label']) ?></a>
                        <?php if ($networkRow['label'] !== $networkRow['email']): ?><span><?= $networkEscape((string) $networkRow['email']) ?></span><?php endif; ?>
                        <strong><?= (int) $networkRow['logins'] ?> connexion(s)</strong>
                    <?php endif; ?>
                    <?php if ($networkStamp !== null): ?>
                        <span class="pu-muted">Dernière connexion · <?= $networkEscape((new DateTimeImmutable('@' . $networkStamp))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($networkPage > 1 || $networkHasNext): ?>
        <nav class="pa-network-pages" aria-label="Pages des résultats IP">
            <?php if ($networkPage > 1): ?><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $networkEscape($networkUrl($networkIp, $networkPage - 1)) ?>" data-network-view>Précédente</a><?php endif; ?>
            <span>Page <?= (int) $networkPage ?></span>
            <?php if ($networkHasNext): ?><a class="pa-btn pa-btn--line pa-btn--sm" href="<?= $networkEscape($networkUrl($networkIp, $networkPage + 1)) ?>" data-network-view>Suivante</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
