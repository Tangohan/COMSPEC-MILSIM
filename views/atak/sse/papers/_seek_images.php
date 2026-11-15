<?php
declare(strict_types=1);
/**
 * Images des fiches SEEK II de l'identité : le cliché du visage pris au terminal SEEK II, rangé à la photothèque SSE
 * (App\Services\Sse\SseSeekPhotoService) et servi par la route protégée du portail, jamais depuis un dossier public.
 * Miniature, agrandissement (ancre :target, sans script), légende (fiche REC, verdict), date, lieu, source, mentions.
 *
 * @var list<array<string, mixed>> $seekImages clichés (url, caption, created_at, seek = fiche SEEK II ou null)
 * @var list<array<string, mixed>> $fileMentions mentions actives aux fichiers de l'identité
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$seekImages = is_array($seekImages ?? null) ? array_values($seekImages) : [];
$fileMentions = is_array($fileMentions ?? null) ? array_values($fileMentions) : [];
$seekKinds = ['front' => 'Face', 'profile' => 'Profil', 'field' => 'Terrain'];
?>
<style>
    .skx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:14px;margin:0;padding:0;list-style:none}
    .skx-card{display:flex;flex-direction:column;gap:6px;margin:0}
    .skx-thumb{display:block;aspect-ratio:3/4;overflow:hidden;border:1px solid var(--line,#2a3138);border-radius:6px;background:#0e1114}
    .skx-thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .skx-cap{font-size:12px;line-height:1.45}
    .skx-cap strong{display:block;font-size:13px}
    .skx-lb{display:none;position:fixed;inset:0;z-index:60;align-items:center;justify-content:center;padding:24px}
    .skx-lb:target{display:flex}
    .skx-lb-bg{position:absolute;inset:0;background:rgba(0,0,0,.82)}
    .skx-lb figure{position:relative;margin:0;max-width:min(92vw,720px);max-height:92vh;display:flex;flex-direction:column;gap:10px;align-items:center}
    .skx-lb img{max-width:100%;max-height:74vh;border-radius:6px;background:#0e1114}
    .skx-lb figcaption{font-size:13px;line-height:1.5;text-align:center;color:#fff}
    .skx-mentions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
</style>
<section class="panel" style="margin-top:10px" id="seek-images">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">SEEK</span> Images SEEK II</div>
        <div class="panel-meta"><?= count($seekImages) ?> image(s)</div>
    </div>
    <div class="panel-body">
        <?php if ($seekImages === []): ?>
            <div class="empty-state"><div class="empty-state-inner">
                <strong>Aucune image SEEK II pour cette identité</strong>
                <p>Une image apparaît ici quand un contrôleur photographie le visage de la personne au terminal SEEK II, puis signe et envoie la fiche avec la liaison Athena active. La fiche est rattachée à cette identité par le papier présenté ou par la clé biométrique ; sans l’un ni l’autre, l’image reste dans la photothèque, à rattacher.</p>
                <p><a class="link" href="<?= $h(url('atak/sse/fichiers/seek')) ?>">Fiches SEEK II</a> · <a class="link" href="<?= $h(url('atak/sse/fichiers/photos')) ?>">Photothèque</a></p>
            </div></div>
        <?php else: ?>
            <ul class="skx-grid">
                <?php foreach ($seekImages as $img): ?>
                    <?php
                    $seek = is_array($img['seek'] ?? null) ? $img['seek'] : null;
                    $rec = (string) ($img['source_ref'] ?? ($seek['rec'] ?? ''));
                    $when = trim((string) ($seek['game_time'] ?? ''));
                    $stored = substr((string) ($img['created_at'] ?? ''), 0, 16);
                    $where = trim((string) ($seek['grid'] ?? ''));
                    $who = trim((string) ($seek['controller_callsign'] ?? ''));
                    $unit = trim((string) ($seek['unit'] ?? ''));
                    $verdict = trim((string) ($seek['verdict_label'] ?? ''));
                    $src = (string) ($img['url'] ?? '');
                    $anchor = 'seek-img-' . (int) $img['id'];
                    ?>
                    <li>
                        <figure class="skx-card">
                            <?php if ($src !== ''): ?>
                                <a class="skx-thumb" href="#<?= $h($anchor) ?>" title="Agrandir"><img src="<?= $h($src) ?>" alt="Photo du visage, fiche SEEK II <?= $h($rec) ?>" loading="lazy"></a>
                            <?php else: ?>
                                <span class="skx-thumb muted" style="display:flex;align-items:center;justify-content:center">Image indisponible</span>
                            <?php endif; ?>
                            <figcaption class="skx-cap">
                                <strong><?= $h($rec !== '' ? $rec : 'Fiche SEEK II') ?></strong>
                                <?= $h($seekKinds[(string) ($img['kind'] ?? '')] ?? 'Photo') ?><?= $verdict !== '' ? ' · ' . $h($verdict) : '' ?><br>
                                <?= $h($when !== '' ? $when : $stored) ?><?= $where !== '' ? ' · carroyage ' . $h($where) : '' ?><br>
                                Source : terminal SEEK II<?= $who !== '' ? ' · ' . $h($who) : '' ?><?= $unit !== '' ? ' (' . $h($unit) . ')' : '' ?>
                                <?php $seekSig = trim((string) ($seek['signature_image'] ?? '')); ?>
                                <?php if ($seekSig !== ''): ?><br><img src="<?= $h(url($seekSig)) ?>" alt="Signature tracée au SEEK II, fiche <?= $h($rec) ?>" loading="lazy" style="height:28px;background:#fff;border-radius:3px"><?php endif; ?>
                            </figcaption>
                        </figure>
                        <?php if ($src !== ''): ?>
                            <div class="skx-lb" id="<?= $h($anchor) ?>">
                                <a class="skx-lb-bg" href="#seek-images" aria-label="Fermer"></a>
                                <figure>
                                    <img src="<?= $h($src) ?>" alt="Photo du visage, fiche SEEK II <?= $h($rec) ?>" loading="lazy">
                                    <figcaption>
                                        <?= $h($rec) ?><?= $verdict !== '' ? ' · ' . $h($verdict) : '' ?> · <?= $h($when !== '' ? $when : $stored) ?><?= $where !== '' ? ' · carroyage ' . $h($where) : '' ?><br>
                                        <?= $h((string) ($img['caption'] ?? '')) ?>
                                    </figcaption>
                                    <a class="btn" href="#seek-images">Fermer</a>
                                </figure>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($fileMentions !== []): ?>
            <div class="skx-mentions">
                <?php foreach ($fileMentions as $m): ?>
                    <span class="badge" title="<?= $h((string) ($m['note'] ?? '')) ?>">Mention : <?= $h((string) ($m['mention_label'] ?? '')) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
