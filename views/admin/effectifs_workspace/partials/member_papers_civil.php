<?php
declare(strict_types=1);

use App\Services\Sse\SseMemberCivilIdentityService as Civil;

/**
 * Fiche papiers › Identité principale › « Identité officielle et état civil » : l'identité officielle reprend-elle l'état civil
 * du membre ? Aperçu avant / après, « Aligner sur l'état civil » (avec confirmation) et « nom volontairement différent ».
 * Inclus par member_papers.php (fiche membre et bureau SSE).
 *
 * @var array<string, mixed> $pp section papiers du membre (clé « civil » : SseMemberCivilIdentityService::inspect)
 * @var string $base URL de base des actions papiers du membre
 * @var string $csrfToken
 * @var bool $manage
 */
$civil = is_array($pp['civil'] ?? null) ? $pp['civil'] : null;
if ($civil === null || $civil['state'] === Civil::ANONYMIZED || $civil['state'] === Civil::UNKNOWN) {
    return;
}
$cvh = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cvIdentity = is_array($civil['identity'] ?? null) ? $civil['identity'] : null;
$cvName = $cvIdentity !== null ? trim(mb_strtoupper((string) $cvIdentity['last_name']) . ' ' . (string) $cvIdentity['first_name']) : '';
$cvTarget = is_array($civil['target'] ?? null) ? $civil['target'] : [];
$cvTargetName = trim(mb_strtoupper((string) ($cvTarget['last_name'] ?? '')) . ' ' . (string) ($cvTarget['first_name'] ?? ''));
$cvDate = static fn (?string $d): string => $d !== null && $d !== '' ? \App\Support\SseIdentityCatalog::birthDateLabel($d) : '—';
$cvAlign = static function (string $suffix, string $button) use ($pp, $base, $csrfToken, $civil, $cvName, $cvTargetName): array {
    return [
        'dom' => 'civil-align-' . (int) ($civil['user_id'] ?? 0) . '-' . $suffix,
        'post' => $base . '/etat-civil',
        'csrf' => $csrfToken,
        'identity_id' => (int) ($civil['identity']['id'] ?? 0),
        'name' => $cvName,
        'member' => $cvTargetName,
        'changes' => $civil['changes'],
        'documents' => $civil['documents'],
        'button' => $button,
        'class' => 'eff-btn eff-btn--primary',
    ];
};
?>
<div class="civil-card" id="papiers-etat-civil">
    <?php if ($civil['state'] === Civil::ALIGNED): ?>
        <p class="civil-card__state is-ok">L’identité officielle reprend l’état civil du membre.</p>
    <?php elseif ($civil['state'] === Civil::MISALIGNED): ?>
        <p class="civil-card__state is-warn">L’identité officielle ne reprend pas l’état civil du membre.</p>
        <dl class="civil-card__cols">
            <div>
                <dt>Identité officielle (registre)</dt>
                <dd translate="no"><b><?= $cvh($cvName) ?></b><br><?= $cvh($cvDate($cvIdentity['birth_date'] ?? null)) ?> · <?= $cvh(($cvIdentity['birth_place'] ?? '') !== '' ? $cvIdentity['birth_place'] : '—') ?></dd>
            </div>
            <div>
                <dt>État civil du membre</dt>
                <dd translate="no"><b><?= $cvh($cvTargetName) ?></b><br><?= $cvh($cvDate($cvTarget['birth_date'] ?? null)) ?> · <?= $cvh(($cvTarget['birth_place'] ?? '') !== '' ? $cvTarget['birth_place'] : '—') ?></dd>
            </div>
        </dl>
        <?php if (($civil['sources']['birth_date'] ?? '') === 'generated'): ?>
            <p class="civil-card__hint">La fiche du personnage n’a pas de date de naissance : une date cohérente est tirée une fois pour ce membre, puis gardée sur sa fiche.</p>
        <?php endif; ?>
        <?php if ($manage): ?>
            <div class="civil-card__actions">
                <?php $cal = $cvAlign('fiche', 'Aligner sur l’état civil'); require dirname(__DIR__, 3) . '/partials/sse_civil_align.php'; ?>
                <?php if (!empty($civil['can_override'])): ?>
                    <form method="post" action="<?= $cvh($base . '/etat-civil/volontaire') ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $cvh($csrfToken) ?>">
                        <input type="hidden" name="identity_id" value="<?= (int) $cvIdentity['id'] ?>">
                        <input type="hidden" name="on" value="1">
                        <button type="submit" class="eff-fm-btn is-sm">Nom volontairement différent</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if (empty($civil['can_override'])): ?>
                <p class="civil-card__hint">Le réglage « nom volontairement différent » sera proposé après la mise à jour de la base de données.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php elseif ($civil['state'] === Civil::OVERRIDE): ?>
        <p class="civil-card__state">Nom volontairement différent de l’état civil : l’identité officielle n’est jamais réécrite d’office.</p>
        <?php if ($manage): ?>
            <form method="post" action="<?= $cvh($base . '/etat-civil/volontaire') ?>" class="civil-card__actions">
                <input type="hidden" name="_csrf_token" value="<?= $cvh($csrfToken) ?>">
                <input type="hidden" name="identity_id" value="<?= (int) $cvIdentity['id'] ?>">
                <input type="hidden" name="on" value="0">
                <button type="submit" class="eff-fm-btn is-sm">Suivre de nouveau l’état civil</button>
            </form>
        <?php endif; ?>
    <?php elseif ($civil['state'] === Civil::MISSING): ?>
        <p class="civil-card__state is-warn">Pas d’identité officielle pour ce membre.</p>
        <?php if ($manage): ?>
            <div class="civil-card__actions">
                <?php $cal = $cvAlign('creer', 'Créer depuis l’état civil'); require dirname(__DIR__, 3) . '/partials/sse_civil_align.php'; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($civil['state'] === Civil::NO_CIVIL_STATE): ?>
        <p class="civil-card__state is-warn">Le membre n’a ni prénom ni nom d’état civil : renseignez-les pour aligner son identité officielle.</p>
    <?php endif; ?>
    <?php if (($civil['duplicates'] ?? []) !== []): ?>
        <p class="civil-card__hint">Plusieurs identités civiles existent pour ce membre : la plus ancienne est son identité officielle, les autres sont à fusionner à la main.</p>
        <p class="civil-card__hint">Identités n° <b translate="no"><?= $cvh(implode(', ', array_merge([(int) ($cvIdentity['id'] ?? 0)], $civil['duplicates']))) ?></b></p>
    <?php endif; ?>
</div>
