<?php
declare(strict_types=1);
/**
 * Une correspondance de la recherche (fiche sujet) : photo signalétique, nom, alias, signalement, raisons, mentions,
 * dernier contrôle et score. Partiel réutilisé par le terminal et la photothèque.
 *
 * @var array<string,mixed> $hit
 * @var string $purpose
 */
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseRecordsFormat as RF;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$i = $hit['identity'];
$p = $hit['profile'] ?? [];
$country = (string) ($p['country'] ?? $i['nationality'] ?? ($terminalCountry ?? 'USA'));
$initials = mb_strtoupper(mb_substr((string) $i['first_name'], 0, 1) . mb_substr((string) $i['last_name'], 0, 1));
$age = \App\Services\Sse\SseFileSearchService::age($i['birth_date'] ?? null, gmdate('Y-m-d'));
$tone = static fn (string $m): string => in_array($m, ['armed_dangerous', 'warrant', 'wanted'], true) ? 'r' : ($m === 'caution' ? 'a' : 'b');
$mentionLabel = static fn (string $m): string => \App\Support\SseFileCatalog::label(\App\Support\SseFileCatalog::MENTIONS, $m, $m);
$photoUrl = isset($hit['photo']['path']) ? user_media_public_url((string) $hit['photo']['path']) : (($i['photo_path'] ?? null) ? user_media_public_url((string) $i['photo_path']) : null);
$href = url('atak/sse/fichiers/sujet/' . (int) $i['id']) . (($purpose ?? '') !== '' ? '?motif=' . rawurlencode((string) $purpose) : '');
?>
<a class="fx-hit" href="<?= $h($href) ?>">
    <span class="fx-mug"><?php if ($photoUrl !== null): ?><img src="<?= $h($photoUrl) ?>" alt="" loading="lazy"><?php else: ?><b><?= $h($initials !== '' ? $initials : '?') ?></b><small>SANS PHOTO</small><?php endif; ?></span>
    <span style="min-width:0">
        <span class="fx-hn">
            <b translate="no"><?= $h(mb_strtoupper((string) $i['last_name'])) ?><?= ($i['first_name'] ?? '') !== '' ? ', ' . $h($i['first_name']) : '' ?></b>
            <?php foreach (($hit['mentions'] ?? []) as $m): ?><span class="fx-flg <?= $tone((string) $m) ?>"><?= $h($mentionLabel((string) $m)) ?></span><?php endforeach; ?>
            <?php foreach (($p['caution_codes'] ?? []) as $c): ?><span class="fx-flg a"><?= $h(S::label(S::CAUTIONS, (string) $c)) ?></span><?php endforeach; ?>
        </span>
        <?php if (($hit['aliases'] ?? []) !== []): ?>
            <span class="fx-aka">alias <?php foreach ($hit['aliases'] as $n => $a): ?><?= $n > 0 ? ', ' : '' ?><?php if ($a === ($hit['matched_alias'] ?? null)): ?><i translate="no">« <?= $h($a) ?> »</i><?php else: ?><span translate="no">« <?= $h($a) ?> »</span><?php endif; ?><?php endforeach; ?></span>
        <?php endif; ?>
        <span class="fx-kv">
            <?php if (($i['birth_date'] ?? null) !== null): ?><span>NÉ <b><?= $h(RF::date((string) $i['birth_date'], $country)) ?></b><?= $age !== null ? ' (' . $age . ' ans)' : '' ?></span><?php endif; ?>
            <span><?= $h($i['sex'] ?? 'X') ?><?php if (($p['height_cm'] ?? null) !== null): ?> · <b><?= $h(RF::height((int) $p['height_cm'], $country)) ?></b><?php endif; ?><?php if (($p['weight_kg'] ?? null) !== null): ?> · <?= $h(RF::weight((int) $p['weight_kg'], $country)) ?><?php endif; ?></span>
            <?php if (($p['eyes'] ?? null) !== null): ?><span>YEUX <b><?= $h(S::label(S::EYES, (string) $p['eyes'])) ?></b></span><?php endif; ?>
            <?php if (($p['hair'] ?? null) !== null): ?><span>CHEV. <b><?= $h(S::label(S::HAIR, (string) $p['hair'])) ?></b></span><?php endif; ?>
            <span><?= $h($i['ref']) ?></span>
        </span>
        <?php if (($hit['why'] ?? []) !== []): ?>
            <span class="fx-why"><?php foreach ($hit['why'] as [$label, $strong]): ?><span class="<?= $strong ? 'hl' : '' ?>"><?= $h($label) ?></span><?php endforeach; ?></span>
        <?php endif; ?>
        <?php if (is_array($hit['checks'] ?? null)): ?>
            <span class="fx-seen"><svg class="i"><use href="#fx-clock"></use></svg>Déjà contrôlé · <?= (int) $hit['checks']['count'] ?> contrôle<?= (int) $hit['checks']['count'] > 1 ? 's' : '' ?>, dernier <?= $h(\App\Services\Sse\SseFileRecordService::ago((string) $hit['checks']['at'], gmdate('Y-m-d H:i:s'))) ?></span>
        <?php endif; ?>
    </span>
    <span class="fx-sc"><small><?= $h(S::CONFIDENCE_LABELS[$hit['confidence']] ?? '') ?></small><b><?= (int) $hit['score'] ?></b><span class="fx-bar"><i style="width:<?= (int) $hit['score'] ?>%"></i></span></span>
</a>
