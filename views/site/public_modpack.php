<?php

use App\Core\Csrf;
use App\Services\Modpack\ModpackVersion;

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$english = locale() === 'en';
$number = static fn ($value, int $decimals = 0): string => number_format((float) $value, $decimals, $english ? '.' : ',', $english ? ',' : ' ');
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
$sizeLabel = $published ? ($size >= 1073741824 ? $number($size / 1073741824, 2) . ' ' . __('modpack.gib') : ($size >= 1048576 ? $number($size / 1048576, 1) . ' ' . __('modpack.mib') : $number($size) . ' ' . __('modpack.bytes'))) : '';
$dateLabel = $date ? $date->setTimezone(new DateTimeZone('Europe/Paris'))->format($english ? 'j M Y' : 'd/m/Y') : '';
$shaShort = $published && preg_match('/\A[a-f0-9]{64}\z/', (string) ($pack['sha256'] ?? '')) === 1 ? substr($pack['sha256'], 0, 8) . '…' . substr($pack['sha256'], -8) : '';
// Notes de version : première ligne = titre, le reste dans un cadre défilant.
$noteLines = $published && !empty($pack['release_notes']) ? preg_split('/\R/', trim((string) $pack['release_notes']), 2) : [];
$noteHead = (string) ($noteLines[0] ?? '');
$noteBody = trim((string) ($noteLines[1] ?? ''));
?>
<div class="mp" lang="<?= $e(locale()) ?>" translate="no">
<div id="contenu" class="mp-wrap">
    <section class="mp-hero" aria-labelledby="pack-title">
        <img class="mp-cover" src="<?= $e(asset_url('assets/images/home/hero-2-1920.webp')) ?>" srcset="<?= $e(asset_url('assets/images/home/hero-2-960.webp')) ?> 960w, <?= $e(asset_url('assets/images/home/hero-2-1920.webp')) ?> 1817w" sizes="(max-width: 610px) 100vw, 1200px" alt="" fetchpriority="high">
        <div class="mp-hero-content">
            <p class="mp-kicker hi-kicker"><span></span> <?= $e(__('modpack.kicker')) ?></p>
            <h1 id="pack-title" class="hi-display">Modpack<br><em>COMSPEC</em></h1>
            <p><?= $e(__('modpack.intro')) ?></p>
            <div class="mp-actions">
                <?php if ($published && !$paused): ?>
                    <a class="mp-primary" href="<?= $e(url('modpacks/telecharger?archive=' . $pack['archive_key'])) ?>">
                        <?= $e(__('modpack.download')) ?> <span class="mp-arrow mp-arrow-down" aria-hidden="true"></span>
                    </a>
                <?php else: ?>
                    <span class="mp-disabled"><?= $e(__('modpack.' . (!$ready ? 'unavailable' : ($published ? 'paused' : 'coming')))) ?></span>
                <?php endif; ?>
                <button class="mp-guide mp-workshop-trigger" type="button" data-workshop-notice hidden><?= $e(__('modpack.workshop_link')) ?></button>
                <a class="mp-guide" href="#installation"><?= $e(__('modpack.guide')) ?> <span class="mp-arrow" aria-hidden="true"></span></a>
                <?php if ($admin && $ready): ?><a class="mp-guide" href="#administration"><?= $e(__('modpack.admin')) ?> <span class="mp-arrow" aria-hidden="true"></span></a><?php endif; ?>
            </div>
            <?php if ($published): ?>
            <ul class="mp-meta" aria-label="<?= $e(__('modpack.file_details')) ?>">
                <li>v<?= $e($pack['version']) ?></li>
                <li><?= $e($sizeLabel) ?> · RAR</li>
                <li><?= $e($dateLabel) ?></li>
                <?php if ($shaShort !== ''): ?><li class="mp-hash" title="SHA-256"><span>SHA-256</span> <code><?= $e($shaShort) ?></code></li><?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>
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

    <section class="mp-release" aria-label="<?= $e(__('modpack.statistics')) ?>">
        <div class="mp-release-info">
            <p class="mp-label"><?= $e(__('modpack.version_distributed')) ?></p>
            <p class="mp-version"><?= $published ? 'v' . $e($pack['version']) : '—' ?></p>
            <p class="mp-status<?= $outdated ? ' mp-warning' : ($published && $expected !== null ? ' mp-current' : '') ?>"><span aria-hidden="true"></span><?= $e(__('modpack.' . $status)) ?></p>
        </div>
        <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.views')) ?></p><strong><?= $ready ? $number($activity['views']) : '—' ?></strong><small><?= $e(__('modpack.views_total')) ?></small></div>
        <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.downloads')) ?></p><strong><?= $ready ? $number($activity['downloads']) : '—' ?></strong><small><?= $e(__('modpack.started')) ?></small></div>
        <div class="mp-stat"><p class="mp-label"><?= $e(__('modpack.trend')) ?></p><strong><?= $ready ? $number($recent) : '—' ?></strong><small><?= $e(!$ready || $delta === 0 ? __('modpack.last_week') : __('modpack.previous_week', ['delta' => ($delta > 0 ? '+' : '−') . $number(abs($delta))])) ?></small></div>
    </section>

    <?php if ($outdated): ?><p class="mp-alert"><?= $e(__('modpack.outdated_detail', ['version' => $pack['version'], 'reference' => $expected])) ?></p><?php endif; ?>

    <section id="installation" class="mp-install">
            <h2><?= $e(__('modpack.install')) ?></h2>
            <ol>
                <li><div><strong><?= $e(__('modpack.check')) ?></strong><p><?= $e(__('modpack.check_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.extract')) ?></strong><p><?= $e(__('modpack.extract_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.local_mods')) ?></strong><p><?= $e(__('modpack.local_mods_detail')) ?></p></div></li>
                <li><div><strong><?= $e(__('modpack.activate')) ?></strong><p><?= $e(__('modpack.activate_detail')) ?></p></div></li>
            </ol>
    </section>

    <div class="mp-details">
        <section class="mp-file">
            <h2><?= $e(__('modpack.file_details')) ?></h2>
            <dl>
                <div><dt><?= $e(__('modpack.format')) ?></dt><dd>RAR</dd></div>
                <div><dt><?= $e(__('modpack.size')) ?></dt><dd><?= $published ? $e($size >= 1073741824 ? $number($size / 1073741824, 2) . ' ' . __('modpack.gib') : ($size >= 1048576 ? $number($size / 1048576, 1) . ' ' . __('modpack.mib') : $number($size) . ' ' . __('modpack.bytes'))) : '—' ?></dd></div>
                <div><dt><?= $e(__('modpack.published_on')) ?></dt><dd><?= $date ? $e($date->setTimezone(new DateTimeZone('Europe/Paris'))->format($english ? 'j M Y' : 'd/m/Y')) : '—' ?></dd></div>
                <div><dt><?= $e(__('modpack.reference')) ?></dt><dd><?= $e($expected ?? '—') ?></dd></div>
            </dl>
            <?php if ($published): ?><details><summary><?= $e(__('modpack.checksum')) ?></summary><code><?= $e($pack['sha256']) ?></code></details><?php endif; ?>
            <?php if ($published): ?><details><summary><?= $e(__('modpack.verify')) ?></summary><p><?= $e(__('modpack.verify_detail')) ?></p><code><?= $e(__('modpack.verify_cmd')) ?></code></details><?php endif; ?>
            <?php if ($published): ?><p class="mp-note"><?= $e(__('modpack.av_note')) ?></p><?php endif; ?>
        </section>
        <?php if ($noteLines !== []): ?>
        <section class="mp-notes" aria-labelledby="mp-notes-title">
            <h2 id="mp-notes-title"><?= $e(__('modpack.notes')) ?></h2>
            <p class="mp-notes-head"><?= $e($noteHead) ?></p>
            <?php if ($noteBody !== ''): ?><div class="mp-notes-body" tabindex="0"><?= nl2br($e($noteBody)) ?></div><?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <section id="securite" class="mp-trust" aria-labelledby="mp-trust-title">
        <h2 id="mp-trust-title"><?= $e(__('modpack.trust')) ?></h2>
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
            <h2 id="mp-support-title"><?= $e(__('modpack.donate_title')) ?></h2>
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
<?php if ($admin && $ready): ?>
<script src="<?= $e(asset_url('assets/js/public-modpack-upload.js')) ?>" defer></script>
<?php endif; ?>
