<?php

declare(strict_types=1);

$awards = is_array($awards ?? null) ? $awards : [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$statusFr = static function (?string $s): string {
    return match (strtolower(trim((string) $s))) {
        'active', 'valid', 'granted' => 'Valide',
        'expired' => 'Expirée',
        'revoked' => 'Retirée',
        'pending' => 'En attente',
        'suspended' => 'Suspendue',
        default => $s !== null && trim($s) !== '' ? trim($s) : '—',
    };
};
?>
<div class="bo-member-situation">
    <div class="bo-member-situation__actions">
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir dans ma fiche</a>
    </div>

    <?php if ($awards === []): ?>
        <section class="bo-member-situation__card">
            <h2>Aucune qualification</h2>
            <p>Aucune qualification n’est encore enregistrée sur votre dossier. Elles apparaissent ici dès qu’un responsable les attribue.</p>
        </section>
    <?php else: ?>
        <div class="bo-member-situation__grid">
            <?php foreach ($awards as $award): ?>
                <?php
                if (!is_array($award)) {
                    continue;
                }
                $id = (int) ($award['id'] ?? 0);
                $name = trim((string) ($award['definition_name'] ?? $award['qualification_name'] ?? ''));
                if ($name === '') {
                    $name = 'Qualification';
                }
                $level = trim((string) ($award['level_name'] ?? $award['level_short_name'] ?? ''));
                $category = trim((string) ($award['category_name'] ?? ''));
                $issuer = trim((string) ($award['issuer_name'] ?? ''));
                $status = $statusFr((string) ($award['status'] ?? ''));
                $obtained = trim((string) ($award['obtained_at'] ?? ''));
                $expires = trim((string) ($award['expires_at'] ?? ''));
                $hasCert = trim((string) ($award['certificate_document_path'] ?? '')) !== '';
                $obtainedTs = $obtained !== '' ? strtotime($obtained) : false;
                $expiresTs = $expires !== '' ? strtotime($expires) : false;
                ?>
                <article class="bo-member-situation__card">
                    <?php if ($category !== ''): ?>
                        <p class="bo-member-situation__kicker"><?= $h($category) ?></p>
                    <?php endif; ?>
                    <h2><?= $h($name) ?></h2>
                    <dl class="bo-member-situation__dl">
                        <?php if ($level !== ''): ?>
                        <div>
                            <dt>Niveau</dt>
                            <dd><?= $h($level) ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt>État</dt>
                            <dd><?= $h($status) ?></dd>
                        </div>
                        <?php if ($issuer !== ''): ?>
                        <div>
                            <dt>Délivré par</dt>
                            <dd><?= $h($issuer) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($obtainedTs !== false): ?>
                        <div>
                            <dt>Obtenue le</dt>
                            <dd><?= $h(date('d/m/Y', $obtainedTs)) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($expiresTs !== false): ?>
                        <div>
                            <dt>Valable jusqu’au</dt>
                            <dd><?= $h(date('d/m/Y', $expiresTs)) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php if ($hasCert && $id > 0): ?>
                        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/brevet')) ?>">Télécharger le brevet</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
