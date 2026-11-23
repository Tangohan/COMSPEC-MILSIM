<?php

use App\Core\Csrf;
use App\Services\Modpack\ModpackItems;
use App\Services\Modpack\ModpackVersion;

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$english = locale() === 'en';
$number = static fn ($value, int $decimals = 0): string => number_format((float) $value, $decimals, $english ? '.' : ',', $english ? ',' : ' ');
$formatSize = static fn (int $bytes): string => $bytes >= 1073741824 ? $number($bytes / 1073741824, 2) . ' ' . __('modpack.gib') : ($bytes >= 1048576 ? $number($bytes / 1048576, 1) . ' ' . __('modpack.mib') : $number($bytes) . ' ' . __('modpack.bytes'));
$hasArchive = !empty($pack['archive_key']);
$published = $hasArchive && !empty($pack['published']);
$paused = (int) ($pack['max_downloads'] ?? 0) === 0;
$expected = $pack['expected_version'] ?? null;
$outdated = $published && $expected !== null && ModpackVersion::isOlder($pack['version'], $expected);
$status = !$published ? 'unpublished' : ($outdated ? 'outdated' : ($expected === null ? 'unconfirmed' : 'current'));
$recent = (int) ($activity['recent'] ?? 0);
$previous = (int) ($activity['previous'] ?? 0);
$delta = $recent - $previous;
$size = (int) ($pack['bytes'] ?? 0);
$date = $published ? new DateTimeImmutable($pack['published_at'], new DateTimeZone('UTC')) : null;
$sizeLabel = $published ? $formatSize($size) : '';
$dateLabel = $date ? $date->setTimezone(new DateTimeZone('Europe/Paris'))->format($english ? 'j M Y' : 'd/m/Y') : '';
$validSha = $published && preg_match('/\A[a-f0-9]{64}\z/', (string) ($pack['sha256'] ?? '')) === 1;
$shaShort = $validSha ? substr($pack['sha256'], 0, 8) . '…' . substr($pack['sha256'], -8) : '';
// Notes de version : première ligne = titre, le reste dans un cadre défilant.
$noteLines = $published && !empty($pack['release_notes']) ? preg_split('/\R/', trim((string) $pack['release_notes']), 2) : [];
$noteHead = (string) ($noteLines[0] ?? '');
$noteBody = trim((string) ($noteLines[1] ?? ''));
$items = $items ?? [];
$itemsReady = $itemsReady ?? [];
$canDownload = $ready && !$paused;
$gallery = [
    ['hero-1-960.webp', __('modpack.cap1')],
    ['hero-2-960.webp', __('modpack.cap2')],
    ['hero-3-960.webp', __('modpack.cap3')],
];
?>
<div class="mp" lang="<?= $e(locale()) ?>" translate="no">
<div id="contenu" class="mp-wrap">
    <section class="mp-hero" aria-labelledby="pack-title">
        <div class="mp-gallery" data-gallery>
            <figure class="mp-shot">
                <img src="<?= $e(asset_url('assets/images/home/' . $gallery[0][0])) ?>" alt="" width="960" height="540" fetchpriority="high" data-gallery-image>
                <figcaption><span data-gallery-caption><?= $e($gallery[0][1]) ?></span></figcaption>
            </figure>
            <ul class="mp-thumbs">
                <?php foreach ($gallery as $index => [$file, $caption]): ?>
                <li><button type="button" class="mp-thumb<?= $index === 0 ? ' is-on' : '' ?>" data-src="<?= $e(asset_url('assets/images/home/' . $file)) ?>" data-caption="<?= $e($caption) ?>" aria-label="<?= $e(__('modpack.gallery_view', ['n' => $index + 1])) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"><img src="<?= $e(asset_url('assets/images/home/' . $file)) ?>" alt="" loading="lazy" width="320" height="180"></button></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <aside class="mp-buy" aria-label="<?= $e(__('modpack.download')) ?>">
            <p class="mp-context"><span aria-hidden="true"></span> <?= $e(__('modpack.kicker')) ?></p>
            <h1 id="pack-title" class="mp-display">Modpack<br><em>COMSPEC</em></h1>
            <p class="mp-intro"><?= $e(__('modpack.intro')) ?></p>
            <?php if ($published): ?>
            <ul class="mp-meta" aria-label="<?= $e(__('modpack.file_details')) ?>">
                <li class="mp-badge-status<?= $outdated ? ' mp-warning' : '' ?>"><span aria-hidden="true"></span><?= $e(__('modpack.' . $status)) ?></li>
                <li class="mp-mono">v<?= $e($pack['version']) ?></li>
                <li><?= $e($sizeLabel) ?> · RAR</li>
                <li><?= $e($dateLabel) ?></li>
            </ul>
            <?php endif; ?>

            <div class="mp-box" id="acheter">
                <div class="mp-box-top">
                    <div><p class="mp-label"><?= $e(__('modpack.price')) ?></p><p class="mp-price"><?= $e(__('modpack.free')) ?></p></div>
                    <div class="mp-box-count"><strong class="mp-mono"><?= $ready ? $number($activity['downloads']) : '—' ?></strong><span><?= $e(__('modpack.downloads')) ?></span></div>
                </div>
                <?php if ($published && !$paused): ?>
                    <a class="mp-primary" href="<?= $e(url('modpacks/telecharger?archive=' . $pack['archive_key'])) ?>">
                        <?= $e(__('modpack.download')) ?> <span class="mp-arrow mp-arrow-down" aria-hidden="true"></span>
                    </a>
                <?php else: ?>
                    <span class="mp-disabled"><?= $e(__('modpack.' . (!$ready ? 'unavailable' : ($published ? 'paused' : 'coming')))) ?></span>
                <?php endif; ?>
                <div class="mp-box-links">
                    <a class="mp-ghost" href="#installation"><?= $e(__('modpack.guide')) ?></a>
                    <button class="mp-ghost mp-workshop-trigger" type="button" data-workshop-notice hidden><?= $e(__('modpack.workshop_link')) ?></button>
                    <?php if ($admin && $ready): ?><a class="mp-ghost" href="#administration"><?= $e(__('modpack.admin')) ?></a><?php endif; ?>
                </div>
                <?php if ($shaShort !== ''): ?>
                <p class="mp-hash" title="SHA-256"><span>SHA-256</span> <code><?= $e($shaShort) ?></code></p>
                <?php endif; ?>
            </div>

            <ul class="mp-perks">
                <?php foreach (['perk1', 'perk2', 'perk3', 'perk4'] as $perk): ?><li><span aria-hidden="true">✓</span><?= $e(__('modpack.' . $perk)) ?></li><?php endforeach; ?>
            </ul>
        </aside>
    </section>

    <dialog class="mp-workshop" id="mp-workshop" aria-labelledby="mp-workshop-title">
        <form method="dialog">
            <button class="mp-workshop-close" aria-label="<?= $e(__('modpack.workshop_close')) ?>">×</button>
            <h2 id="mp-workshop-title"><?= $e(__('modpack.workshop_title')) ?></h2>
            <p><?= $e(__('modpack.workshop_intro')) ?></p>
            <p><?= $e(__('modpack.workshop_cause')) ?></p>
            <p><?= $e(__('modpack.workshop_updates')) ?></p>
            <button class="mp-primary"><?= $e(__('modpack.workshop_continue')) ?></button>
        </form>
    </dialog>

    <p class="mp-banner"><strong><?= $e(__('modpack.notice_label')) ?></strong> <span><?= $e(__('modpack.workshop_intro')) ?></span></p>

    <?php if ($outdated): ?><p class="mp-alert"><?= $e(__('modpack.outdated_detail', ['version' => $pack['version'], 'reference' => $expected])) ?></p><?php endif; ?>

    <?php if ($items !== []): ?>
    <section id="mods" class="mp-mods" aria-labelledby="mp-mods-title">
        <div class="mp-section-head">
            <h2 id="mp-mods-title" class="mp-display"><?= $e(__('modpack.mods_title')) ?></h2>
            <p><?= $e(__('modpack.mods_lead')) ?></p>
        </div>
        <form method="get" action="<?= $e(url('modpacks/telecharger-selection')) ?>" class="mp-select" data-select
              data-count-label="<?= $e(__('modpack.selected_some', ['count' => '{count}', 'size' => '{size}'])) ?>"
              data-empty-label="<?= $e(__('modpack.selection_empty')) ?>"
              data-mib="<?= $e(__('modpack.mib')) ?>" data-gib="<?= $e(__('modpack.gib')) ?>" data-decimal="<?= $english ? '.' : ',' ?>">
            <div class="mp-grid">
                <?php foreach ($items as $id => $item): ?>
                <?php
                $direct = $canDownload && ($itemsReady[$id] ?? false) === true;
                $itemBytes = ModpackItems::bytes($item);
                ?>
                <article class="mp-item" id="mod-<?= $e($id) ?>">
                    <header>
                        <span class="mp-ico" aria-hidden="true"><?= $e($item['ico']) ?></span>
                        <div>
                            <h3><?= $e($item['name']) ?></h3>
                            <p class="mp-item-kind"><?= $e(__('modpack.item_' . $id . '_kind')) ?><?= $item['version'] !== null ? ' · v' . $e($item['version']) : '' ?></p>
                        </div>
                        <?php if ($direct): ?>
                        <label class="mp-check-box"><input type="checkbox" name="mods[]" value="<?= $e($id) ?>" data-bytes="<?= $itemBytes ?>" aria-label="<?= $e(__('modpack.select_item', ['name' => $item['name']])) ?>"></label>
                        <?php endif; ?>
                    </header>
                    <p><?= $e(__('modpack.item_' . $id . '_short')) ?></p>
                    <p class="mp-item-long"><?= $e(__('modpack.item_' . $id . '_long')) ?></p>
                    <?php if ($item['requires'] !== []): ?><p class="mp-tags"><span class="mp-tag"><?= $e(__('modpack.requires', ['names' => implode(', ', $item['requires'])])) ?></span></p><?php endif; ?>
                    <?php if ($item['files'] !== []): ?>
                    <details class="mp-files">
                        <summary><?= $e(__('modpack.item_files')) ?> (<?= count($item['files']) ?>)</summary>
                        <ul>
                            <?php foreach ($item['files'] as $file): ?><li><span><?= $e($file['path']) ?></span> <code><?= $e(substr($file['sha256'], 0, 12)) ?>…</code></li><?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endif; ?>
                    <div class="mp-item-action">
                        <?php if ($item['files'] !== [] && ($itemsReady[$id] ?? false) === true): ?>
                            <?php if ($direct): ?>
                            <a class="mp-primary" href="<?= $e(url('modpacks/telecharger-mod') . '?mod=' . rawurlencode($id)) ?>"><?= $e(__('modpack.download_one')) ?> <span class="mp-item-size"><?= $e($formatSize($itemBytes)) ?></span></a>
                            <?php else: ?>
                            <span class="mp-disabled"><?= $e(__('modpack.' . (!$ready ? 'unavailable' : 'paused'))) ?></span>
                            <?php endif; ?>
                        <?php elseif ($item['in_pack']): ?>
                            <a class="mp-ghost" href="#acheter"><?= $e(__('modpack.in_pack')) ?></a>
                        <?php elseif ($item['workshop_url'] !== null): ?>
                            <a class="mp-ghost" href="<?= $e($item['workshop_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $e(__('modpack.workshop_item')) ?> ↗</a>
                        <?php else: ?>
                            <span class="mp-disabled"><?= $e(__('modpack.soon_item')) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
                <aside class="mp-req" aria-labelledby="mp-req-title">
                    <h3 id="mp-req-title"><?= $e(__('modpack.req_title')) ?></h3>
                    <ul>
                        <?php foreach (['req_1', 'req_2', 'req_3', 'req_4'] as $req): ?><li><?= $e(__('modpack.' . $req)) ?></li><?php endforeach; ?>
                    </ul>
                </aside>
            </div>
            <div class="mp-selectbar" role="group" aria-label="<?= $e(__('modpack.download_selection')) ?>">
                <button type="button" class="mp-ghost" data-select-all hidden><?= $e(__('modpack.select_all')) ?></button>
                <button type="button" class="mp-ghost" data-select-none hidden><?= $e(__('modpack.select_none')) ?></button>
                <p class="mp-selectbar-count" data-select-status role="status"><?= $e(__('modpack.selection_hint')) ?></p>
                <button type="submit" class="mp-primary" data-select-submit <?= $canDownload ? '' : 'disabled' ?>><?= $e(__('modpack.download_selection')) ?> <span class="mp-arrow mp-arrow-down" aria-hidden="true"></span></button>
            </div>
            <p class="mp-note"><?= $e(__('modpack.item_zip_note')) ?></p>
        </form>
    </section>
    <?php endif; ?>

    <section class="mp-release" aria-label="<?= $e(__('modpack.statistics')) ?>">
        <div class="mp-stats">
            <div><p class="mp-label"><?= $e(__('modpack.version_distributed')) ?></p><strong><?= $published ? 'v' . $e($pack['version']) : '—' ?></strong><small class="mp-status<?= $outdated ? ' mp-warning' : ($published && $expected !== null ? ' mp-current' : '') ?>"><?= $e(__('modpack.' . $status)) ?></small></div>
            <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.views')) ?></p><strong><?= $ready ? $number($activity['views']) : '—' ?></strong><small><?= $e(__('modpack.views_total')) ?></small></div>
            <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.downloads')) ?></p><strong><?= $ready ? $number($activity['downloads']) : '—' ?></strong><small><?= $e(__('modpack.started')) ?></small></div>
            <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.trend')) ?></p><strong><?= $ready ? $number($recent) : '—' ?></strong><small><?= $e(!$ready || $delta === 0 ? __('modpack.last_week') : __('modpack.previous_week', ['delta' => ($delta > 0 ? '+' : '−') . $number(abs($delta))])) ?></small></div>
        </div>
        <?php if ($noteLines !== []): ?>
        <div class="mp-notes" aria-labelledby="mp-notes-title">
            <h2 id="mp-notes-title"><?= $e(__('modpack.notes')) ?></h2>
            <p class="mp-notes-head"><?= $e($noteHead) ?></p>
            <?php if ($noteBody !== ''): ?><div class="mp-notes-body" tabindex="0"><?= nl2br($e($noteBody)) ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <section id="installation" class="mp-install">
            <h2 class="mp-display"><?= $e(__('modpack.install')) ?></h2>
            <ol>
                <li><div><strong><?= $e(__('modpack.check')) ?></strong><p><?= $e(__('modpack.check_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.extract')) ?></strong><p><?= $e(__('modpack.extract_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.local_mods')) ?></strong><p><?= $e(__('modpack.local_mods_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.activate')) ?></strong><p><?= $e(__('modpack.activate_detail')) ?></p></div></li>
            </ol>
            <p class="mp-manual"><?= $e(__('modpack.manual_updates')) ?></p>
    </section>

    <section class="mp-file" aria-labelledby="mp-file-title">
        <h2 id="mp-file-title"><?= $e(__('modpack.file_details')) ?></h2>
        <dl>
            <div><dt><?= $e(__('modpack.format')) ?></dt><dd>RAR</dd></div>
            <div><dt><?= $e(__('modpack.size')) ?></dt><dd><?= $published ? $e($sizeLabel) : '—' ?></dd></div>
            <div><dt><?= $e(__('modpack.published_on')) ?></dt><dd><?= $date ? $e($dateLabel) : '—' ?></dd></div>
            <div><dt><?= $e(__('modpack.reference')) ?></dt><dd><?= $e($expected ?? '—') ?></dd></div>
        </dl>
        <?php if ($published): ?><details><summary><?= $e(__('modpack.checksum')) ?></summary><code><?= $e($pack['sha256']) ?></code></details><?php endif; ?>
        <?php if ($published): ?><details><summary><?= $e(__('modpack.verify')) ?></summary><p><?= $e(__('modpack.verify_detail')) ?></p><code><?= $e(__('modpack.verify_cmd')) ?></code></details><?php endif; ?>
        <?php if ($published): ?><p class="mp-note"><?= $e(__('modpack.av_note')) ?></p><?php endif; ?>
    </section>

    <section id="securite" class="mp-trust" aria-labelledby="mp-trust-title">
        <h2 id="mp-trust-title" class="mp-display"><?= $e(__('modpack.trust')) ?></h2>
        <p class="mp-lead"><?= $e(__('modpack.trust_lead')) ?></p>
        <div class="mp-trust-grid">
            <article>
                <h3><?= $e(__('modpack.flag_q')) ?></h3>
                <p><?= $e(__('modpack.flag_a1')) ?></p>
                <p><?= $e(__('modpack.flag_a2')) ?></p>
                <p><?= $e(__('modpack.flag_a3')) ?></p>
            </article>
            <article>
                <h3><?= $e(__('modpack.vt_title')) ?></h3>
                <p><?= $e(__('modpack.vt_lead')) ?></p>
                <p class="mp-links">
                    <?php if ($published && preg_match('/\A[a-f0-9]{64}\z/', (string) ($pack['sha256'] ?? '')) === 1): ?>
                    <a class="mp-guide" href="https://www.virustotal.com/gui/file/<?= $e($pack['sha256']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $e(__('modpack.vt_hash')) ?> <span class="mp-arrow" aria-hidden="true"></span></a>
                    <?php endif; ?>
                    <a class="mp-guide" href="https://www.virustotal.com/gui/home/upload" target="_blank" rel="noopener noreferrer nofollow"><?= $e(__('modpack.vt_upload')) ?> <span class="mp-arrow" aria-hidden="true"></span></a>
                </p>
                <p class="mp-note"><?= $e(__('modpack.vt_read')) ?></p>
            </article>
        </div>
    </section>

    <section class="mp-support" aria-labelledby="mp-support-title">
        <div>
            <h2 id="mp-support-title" class="mp-display"><?= $e(__('modpack.donate_title')) ?></h2>
            <p><?= $e(__('modpack.donate_text')) ?></p>
        </div>
        <a class="mp-primary" href="<?= $e(url('soutenir-atak')) ?>"><?= $e(__('modpack.donate_cta')) ?> <span class="mp-arrow" aria-hidden="true"></span></a>
    </section>

    <?php if ($admin && $ready): ?>
    <section id="administration" class="mp-admin">
        <h2><?= $e(__('modpack.admin')) ?></h2>
        <?php if (is_array($notice)): ?><p class="mp-alert" role="<?= $notice['error'] ? 'alert' : 'status' ?>"><?= $e($notice['text']) ?></p><?php endif; ?>
        <div class="mp-admin-grid">
            <form method="post" action="<?= $e(url('modpacks/publier')) ?>" data-modpack-upload
                  data-upload-messages="<?= $e(json_encode([
                      'network' => __('modpack.js_network'),
                      'session' => __('modpack.js_session'),
                      'server_limit' => __('modpack.js_server_limit'),
                      'chunk_failed' => __('modpack.js_chunk_failed'),
                      'invalid_file' => __('modpack.js_invalid_file'),
                      'sending' => __('modpack.js_sending'),
                      'finalizing' => __('modpack.js_finalizing'),
                      'failed' => __('modpack.js_failed'),
                  ], JSON_THROW_ON_ERROR)) ?>"
                  data-block-url="<?= $e(url('modpacks/bloc')) ?>"
                  data-block-size="<?= \App\Services\Modpack\ModpackUpload::MAX_BLOCK_BYTES ?>"
                  data-max-size="<?= \App\Services\Modpack\ModpackUpload::MAX_ARCHIVE_BYTES ?>">
                <input type="hidden" name="upload_token">
                <input type="hidden" name="archive_size">
                <?= Csrf::field() ?>
                <h3><?= $e(__('modpack.publish_version')) ?></h3>
                <label><?= $e(__('modpack.version')) ?><input name="version" placeholder="<?= $e(ModpackVersion::EXAMPLE) ?>" pattern="<?= $e(ModpackVersion::PATTERN) ?>"
                    maxlength="<?= ModpackVersion::MAX_LENGTH ?>" title="<?= $e(__('modpack.example', ['version' => ModpackVersion::EXAMPLE])) ?>" required></label>
                <label><?= $e(__('modpack.rar')) ?><input type="file" data-archive accept=".rar,application/vnd.rar" required></label>
                <label><?= $e(__('modpack.notes')) ?><textarea name="notes" rows="4" maxlength="3000"></textarea></label>
                <?php if ($hasArchive): ?><p><?= $e(__('modpack.replacement')) ?></p><?php endif; ?>
                <progress class="mp-progress" max="100" value="0" aria-label="<?= $e(__('modpack.progress')) ?>" hidden></progress>
                <p data-upload-status role="status"></p>
                <noscript><p><?= $e(__('modpack.js_required')) ?></p></noscript>
                <button class="mp-primary" type="submit" disabled><?= $e(__('modpack.publish')) ?> <span class="mp-arrow" aria-hidden="true"></span></button>
            </form>
            <form method="post" action="<?= $e(url('modpacks/reglages')) ?>">
                <?= Csrf::field() ?>
                <h3><?= $e(__('modpack.settings')) ?></h3>
                <label><?= $e(__('modpack.reference')) ?><input name="expected_version" value="<?= $e($expected ?? '') ?>" placeholder="<?= $e(ModpackVersion::EXAMPLE) ?>" pattern="<?= $e(ModpackVersion::PATTERN) ?>"
                    maxlength="<?= ModpackVersion::MAX_LENGTH ?>" title="<?= $e(__('modpack.example', ['version' => ModpackVersion::EXAMPLE])) ?>"></label>
                <p><?= $e(__('modpack.reference_detail')) ?></p>
                <label><?= $e(__('modpack.concurrent')) ?><input type="number" name="max_downloads" min="0" max="16" value="<?= $e($pack['max_downloads']) ?>" required></label>
                <label><?= $e(__('modpack.threshold')) ?><input type="number" name="throttle_at" min="1" max="16" value="<?= $e($pack['throttle_at']) ?>" required></label>
                <label><?= $e(__('modpack.speed')) ?><input type="number" name="throttle_mib" min="1" max="64" value="<?= $e($pack['throttle_mib']) ?>" required></label>
                <label class="mp-check"><input type="checkbox" name="published" value="1" <?= $published ? 'checked' : '' ?> <?= $hasArchive ? '' : 'disabled' ?>> <?= $e(__('modpack.visibility')) ?></label>
                <button class="mp-primary" type="submit"><?= $e(__('modpack.save')) ?></button>
            </form>
        </div>
    </section>
    <?php endif; ?>
</div>
</div>
<script src="<?= $e(asset_url('assets/js/public-modpack-workshop.js')) ?>" defer></script>
<script src="<?= $e(asset_url('assets/js/public-modpack-select.js')) ?>" defer></script>
<?php if ($admin && $ready): ?>
<script src="<?= $e(asset_url('assets/js/public-modpack-upload.js')) ?>" defer></script>
<?php endif; ?>
