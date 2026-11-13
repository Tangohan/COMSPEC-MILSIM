<?php
declare(strict_types=1);

/**
 * Administration du site › Messages aux communautés : liste, état, ciblage et suivi (communautés, jamais des personnes).
 *
 * @var list<array<string, mixed>> $pbmRows
 * @var ?string $pbmError
 * @var int $pbmMax
 * @var array<string, string> $pbmPlansNames
 */
$rows = is_array($pbmRows ?? null) ? $pbmRows : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$planNames = is_array($pbmPlansNames ?? null) ? $pbmPlansNames : [];
$types = \App\Controllers\Admin\System\SystemPlatformBoMessagesController::TYPES;
$fmt = static function (mixed $raw): string {
    $t = ($raw === null || $raw === '') ? false : strtotime((string) $raw);

    return $t ? date('d/m/Y H:i', $t) : '—';
};
$levelLabel = ['info' => 'Information', 'important' => 'Important', 'urgent' => 'Urgent'];
$statusLabel = ['live' => 'En ligne', 'scheduled' => 'Programmé', 'ended' => 'Terminé', 'inactive' => 'Désactivé'];
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
?>
<div class="pmad">
    <div class="pmad__bar pa-head">
        <div>
            <h1 class="pmad__h1">Messages aux communautés</h1>
            <p class="pmad__lead">Écrivez aux gestionnaires dans leur back-office : invitation à rejoindre le Discord, consigne, annonce. Le message s’affiche en haut de leur accueil et dans « Messages de la plateforme ».</p>
        </div>
        <a class="pm-btn pm-btn--primary" href="<?= $h(url('admin/system/messages-communautes/create')) ?>">Nouveau message</a>
    </div>

    <?php if ($ok): ?><p class="pm-flash pm-flash--ok" role="status"><?= $h((string) $ok) ?></p><?php endif; ?>
    <?php if ($err): ?><p class="pm-flash pm-flash--err" role="alert"><?= $h((string) $err) ?></p><?php endif; ?>
    <?php if (!empty($pbmError)): ?><p class="pmad-error" role="alert"><?= $h((string) $pbmError) ?></p><?php endif; ?>

    <?php if ($rows === [] && empty($pbmError)): ?>
        <div class="pm-empty">
            <p class="pm-empty__title">Aucun message</p>
            <p class="pm-empty__text">Créez un premier message pour le voir apparaître dans les back-offices ciblés.</p>
        </div>
    <?php endif; ?>

    <ul class="pmad-list">
        <?php foreach ($rows as $r):
            $id = (int) $r['id'];
            $st = (string) $r['_status'];
            $stats = $r['_stats'];
            $title = $r['title_fr'] !== '' ? (string) $r['title_fr'] : (string) $r['title_en'];
            ?>
            <li class="pmad-row">
                <div class="pmad-row__top">
                    <h2 class="pmad-row__title"><?= $h($title) ?></h2>
                    <span class="pmad-tag<?= $st === 'live' ? ' pmad-tag--live' : '' ?>"><?= $h($statusLabel[$st] ?? $st) ?></span>
                    <span class="pmad-tag<?= $r['level'] === 'urgent' ? ' pmad-tag--urgent' : '' ?>"><?= $h($levelLabel[$r['level']] ?? 'Information') ?></span>
                </div>
                <p class="pmad-meta">
                    <?php $vals = \App\Support\PlatformBoMessage::decodeValues($r['target_json'] ?? null); ?>
                    <?php if ($r['target_mode'] === 'tenants'): ?>
                        <span><?= count($vals) ?> communauté(s) choisie(s)</span>
                    <?php elseif ($r['target_mode'] === 'plans'): ?>
                        <span>Formules :</span> <span><?= $h(implode(', ', array_map(static fn ($v): string => $planNames[(string) $v] ?? (string) $v, $vals))) ?></span>
                    <?php elseif ($r['target_mode'] === 'types'): ?>
                        <span>Types :</span> <span><?= $h(implode(', ', array_map(static fn ($v): string => $types[(string) $v] ?? (string) $v, $vals))) ?></span>
                    <?php else: ?>
                        <span>Toutes les communautés</span>
                    <?php endif; ?>
                    · <span><?= (int) $r['_targeted'] ?> communauté(s) concernée(s)</span>
                    · <span>Du <?= $h($fmt($r['starts_at'])) ?> au <?= $h($fmt($r['ends_at'])) ?></span>
                </p>
                <p class="pmad-stats">
                    <span><b><?= (int) $stats['seen'] ?></b> <span>ont vu</span></span>
                    <span><b><?= (int) $stats['clicked'] ?></b> <span>ont cliqué</span></span>
                    <span><b><?= (int) $stats['dismissed'] ?></b> <span>ont fermé</span></span>
                    <?php if ($r['level'] === 'urgent'): ?><span><b><?= (int) $stats['acked'] ?></b> <span>ont acquitté</span></span><?php endif; ?>
                </p>
                <div class="pmad-actions">
                    <a class="pm-btn" href="<?= $h(url('admin/system/messages-communautes/' . $id . '/edit')) ?>">Modifier</a>
                    <form method="post" action="<?= $h(url('admin/system/messages-communautes/' . $id . '/toggle')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="pm-btn"><?= empty($r['is_active']) ? 'Activer' : 'Désactiver' ?></button>
                    </form>
                    <form method="post" action="<?= $h(url('admin/system/messages-communautes/' . $id . '/delete')) ?>" onsubmit="return confirm('Supprimer ce message et son suivi ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="pm-btn pm-btn--danger">Supprimer</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="pmad-help"><?= (int) $pbmMax ?> messages actifs au plus. Les compteurs comptent des communautés, pas des personnes.</p>
</div>
