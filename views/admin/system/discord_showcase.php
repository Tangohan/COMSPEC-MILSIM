<?php
declare(strict_types=1);

use App\Services\Community\DiscordShowcaseService;

/**
 * Vitrine Discord des communautés (administration du site) : demandes à valider avec l'aperçu exact du post,
 * communautés publiées, bloquées, et réglages du salon. Contrôleur : SystemDiscordShowcaseController.
 *
 * @var list<array{entry:array<string, mixed>, name:string, slug:string, exists:bool, listed:bool, payload:?array<string, mixed>, tags:list<string>}> $showcaseRows
 * @var array{mode:string, forum:bool, cooldown_days:int, tags:array<string, string>} $showcaseConfig
 * @var string $showcaseWebhookMasked
 * @var string $showcaseBase
 */
$rows = is_array($showcaseRows ?? null) ? $showcaseRows : [];
$config = is_array($showcaseConfig ?? null) ? $showcaseConfig : DiscordShowcaseService::normalizeConfig([]);
$masked = (string) ($showcaseWebhookMasked ?? '');
$base = (string) ($showcaseBase ?? 'admin/system/annuaire/discord');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$live = $config['mode'] === DiscordShowcaseService::MODE_LIVE && $masked !== '';

$pending = array_values(array_filter($rows, static fn (array $r): bool => $r['entry']['status'] === DiscordShowcaseService::STATUS_PENDING));
$others = array_values(array_filter($rows, static fn (array $r): bool => $r['entry']['status'] !== DiscordShowcaseService::STATUS_PENDING));
$countPublished = count(array_filter($rows, static fn (array $r): bool => $r['entry']['published_at'] !== ''));
$countBlocked = count(array_filter($rows, static fn (array $r): bool => !empty($r['entry']['blocked'])));

$statusPill = static function (array $entry): array {
    if (!empty($entry['blocked'])) {
        return ['Bloquée', 'rose'];
    }

    return match ((string) $entry['status']) {
        DiscordShowcaseService::STATUS_PENDING => [$entry['published_at'] !== '' ? 'Mise à jour demandée' : 'Nouvelle demande', 'amber'],
        DiscordShowcaseService::STATUS_PUBLISHED => [!empty($entry['simulated']) ? 'Validée (simulation)' : 'En ligne', !empty($entry['simulated']) ? 'slate' : 'mint'],
        DiscordShowcaseService::STATUS_REJECTED => ['Refusée', 'rose'],
        DiscordShowcaseService::STATUS_WITHDRAWN => ['Retirée', 'slate'],
        default => ['Aucune demande', 'slate'],
    };
};

$hidden = static fn (int $tid): string => \App\Core\Csrf::field() . '<input type="hidden" name="tenant_id" value="' . $tid . '">';

/** Carte d'une communauté : aperçu + actions selon l'état. */
$renderRow = static function (array $r) use ($h, $base, $statusPill, $hidden, $live): void {
    $entry = $r['entry'];
    $tid = (int) $entry['tenant_id'];
    [$label, $tone] = $statusPill($entry);
    $isPending = $entry['status'] === DiscordShowcaseService::STATUS_PENDING;
    ?>
    <article class="dsa-item<?= $isPending ? ' dsa-item--pending' : '' ?>" id="c<?= $tid ?>">
        <header class="dsa-item__head">
            <div>
                <p class="pa-name"><?= $h($r['name']) ?></p>
                <p class="pa-sub">
                    <span class="pa-pill pa-pill--<?= $h($tone) ?>"><?= $h($label) ?></span>
                    <?php if ($entry['requested_at'] !== ''): ?> · demandée le <?= $h(DiscordShowcaseService::frDate((string) $entry['requested_at'])) ?><?php endif; ?>
                    <?php if ($entry['published_at'] !== ''): ?> · publiée le <?= $h(DiscordShowcaseService::frDate((string) $entry['published_at'])) ?><?php endif; ?>
                    <?php if ($r['slug'] !== ''): ?> · <a href="<?= $h(url('c/' . rawurlencode($r['slug']))) ?>" target="_blank" rel="noopener">page publique</a><?php endif; ?>
                </p>
                <?php if (!$r['listed']): ?>
                    <p class="dsa-warn">Plus dans l’annuaire public : rien ne peut être publié.</p>
                <?php endif; ?>
                <?php if (!empty($entry['blocked']) && $entry['block_reason'] !== ''): ?>
                    <p class="dsa-note">Motif du blocage : <?= $h((string) $entry['block_reason']) ?></p>
                <?php elseif ($entry['reason'] !== ''): ?>
                    <p class="dsa-note">Dernier motif : <?= $h((string) $entry['reason']) ?></p>
                <?php endif; ?>
            </div>
        </header>
        <?php if (is_array($r['payload']) && ($isPending || $entry['published_at'] !== '')): ?>
            <details class="dsa-preview"<?= $isPending ? ' open' : '' ?>>
                <summary>Aperçu du post</summary>
                <?php
                $dsPayload = $r['payload'];
                $dsTagLabels = $r['tags'];
                require base_path('views/partials/discord_showcase_preview.php');
                ?>
            </details>
        <?php endif; ?>
        <div class="dsa-actions">
            <?php if ($isPending && $r['listed'] && empty($entry['blocked'])): ?>
                <form method="post" action="<?= $h(url($base . '/valider')) ?>">
                    <?= $hidden($tid) ?>
                    <button type="submit" class="pa-btn pa-btn--solid"><?= $live ? 'Valider et publier' : 'Valider (simulation)' ?></button>
                </form>
                <form method="post" action="<?= $h(url($base . '/refuser')) ?>" class="dsa-reason">
                    <?= $hidden($tid) ?>
                    <input type="text" name="reason" maxlength="<?= DiscordShowcaseService::REASON_MAX ?>" placeholder="Motif montré à la communauté" aria-label="Motif du refus">
                    <button type="submit" class="pa-btn pa-btn--line">Refuser</button>
                </form>
            <?php elseif ($live && $entry['status'] === DiscordShowcaseService::STATUS_PUBLISHED && !empty($entry['simulated']) && $r['listed'] && empty($entry['blocked'])): ?>
                <form method="post" action="<?= $h(url($base . '/valider')) ?>">
                    <?= $hidden($tid) ?>
                    <button type="submit" class="pa-btn pa-btn--solid">Publier sur Discord</button>
                </form>
            <?php endif; ?>
            <?php if ($entry['published_at'] !== ''): ?>
                <form method="post" action="<?= $h(url($base . '/retirer')) ?>">
                    <?= $hidden($tid) ?>
                    <button type="submit" class="pa-btn pa-btn--line">Retirer le post</button>
                </form>
            <?php endif; ?>
            <?php if (empty($entry['blocked'])): ?>
                <details class="dsa-block">
                    <summary class="pa-btn pa-btn--ghost">Bloquer</summary>
                    <form method="post" action="<?= $h(url($base . '/bloquer')) ?>" class="dsa-reason">
                        <?= $hidden($tid) ?>
                        <input type="text" name="reason" maxlength="<?= DiscordShowcaseService::REASON_MAX ?>" placeholder="Motif montré à la communauté" aria-label="Motif du blocage">
                        <button type="submit" class="pa-btn pa-btn--line">Bloquer les annonces</button>
                    </form>
                </details>
            <?php else: ?>
                <form method="post" action="<?= $h(url($base . '/debloquer')) ?>">
                    <?= $hidden($tid) ?>
                    <button type="submit" class="pa-btn pa-btn--line">Débloquer</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<div class="pa par dsa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/system/annuaire')) ?>">Annuaire public</a>
                <span aria-hidden="true"> / </span>
                Vitrine Discord
            </p>
            <h1 class="pa-hero__title">Vitrine Discord des communautés</h1>
            <p class="pa-hero__lead">
                Les communautés de l’annuaire demandent à être annoncées dans le salon des communautés du Discord Athena.
                Rien n’est publié sans votre validation : un post par communauté, mis à jour au plus tous les <?= (int) $config['cooldown_days'] ?> jours, sans mention ni lien libre.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/annuaire')) ?>">Épinglage de l’annuaire</a>
                <a class="pa-btn pa-btn--ghost" href="#reglages">Réglages du salon</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <p class="dsa-mode dsa-mode--<?= $live ? 'live' : 'sim' ?>">
                <?php if ($live): ?>
                    <strong>Publication réelle.</strong> Une validation poste ou modifie le post de la communauté sur Discord.
                <?php else: ?>
                    <strong>Mode simulation.</strong> Les validations sont enregistrées mais rien n’est posté sur Discord. Passez en publication réelle dans les réglages quand le salon est prêt.
                <?php endif; ?>
            </p>

            <dl class="pa-stats">
                <div class="pa-stat"><dt>À valider</dt><dd><?= count($pending) ?></dd></div>
                <div class="pa-stat"><dt>Publiées</dt><dd><?= $countPublished ?></dd></div>
                <div class="pa-stat"><dt>Bloquées</dt><dd><?= $countBlocked ?></dd></div>
                <div class="pa-stat"><dt>Délai entre deux mises à jour</dt><dd><?= (int) $config['cooldown_days'] ?> jours</dd></div>
            </dl>

            <section class="pa-card" aria-labelledby="dsa-pending-title">
                <p class="pa-card__kicker">Modération</p>
                <h2 class="pa-card__title" id="dsa-pending-title">Demandes à valider</h2>
                <?php if ($pending === []): ?>
                    <p class="par-empty">Aucune demande en attente.</p>
                <?php else: ?>
                    <div class="dsa-list">
                        <?php foreach ($pending as $r) { $renderRow($r); } ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="pa-card" aria-labelledby="dsa-all-title">
                <p class="pa-card__kicker">Communautés</p>
                <h2 class="pa-card__title" id="dsa-all-title">Publiées, refusées, bloquées</h2>
                <?php if ($others === []): ?>
                    <p class="par-empty">Aucune communauté n’a encore été annoncée.</p>
                <?php else: ?>
                    <div class="dsa-list">
                        <?php foreach ($others as $r) { $renderRow($r); } ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="pa-card" id="reglages" aria-labelledby="dsa-settings-title">
                <p class="pa-card__kicker">Réglages</p>
                <h2 class="pa-card__title" id="dsa-settings-title">Salon des communautés</h2>
                <p class="pa-card__help">Réglage de la plateforme, enregistré en base (jamais dans le .env). Le lien webhook n’est jamais réaffiché : collez-en un nouveau pour le remplacer.</p>
                <form method="post" action="<?= $h(url($base . '/reglages')) ?>" class="dsa-settings">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="pa-field">
                        <label for="dsa-hook">Lien webhook du salon</label>
                        <input id="dsa-hook" type="password" name="webhook" autocomplete="off" spellcheck="false" placeholder="<?= $h($masked !== '' ? $masked . ' (enregistré)' : 'https://discord.com/api/webhooks/…') ?>">
                        <?php if ($masked !== ''): ?>
                            <label class="dsa-check"><input type="checkbox" name="clear_webhook" value="1"> Effacer le lien enregistré</label>
                        <?php endif; ?>
                    </div>
                    <div class="dsa-settings__grid">
                        <div class="pa-field">
                            <label for="dsa-mode">Mode</label>
                            <select id="dsa-mode" name="mode">
                                <option value="simulation"<?= $config['mode'] === DiscordShowcaseService::MODE_SIMULATION ? ' selected' : '' ?>>Simulation (rien n’est posté)</option>
                                <option value="live"<?= $config['mode'] === DiscordShowcaseService::MODE_LIVE ? ' selected' : '' ?>>Publication réelle</option>
                            </select>
                        </div>
                        <div class="pa-field">
                            <label for="dsa-cooldown">Jours entre deux mises à jour</label>
                            <input id="dsa-cooldown" type="number" name="cooldown_days" min="<?= DiscordShowcaseService::MIN_COOLDOWN_DAYS ?>" max="<?= DiscordShowcaseService::MAX_COOLDOWN_DAYS ?>" value="<?= (int) $config['cooldown_days'] ?>">
                        </div>
                        <div class="pa-field">
                            <span class="dsa-label">Type de salon</span>
                            <label class="dsa-check"><input type="checkbox" name="forum" value="1"<?= $config['forum'] ? ' checked' : '' ?>> Salon forum : un post par communauté</label>
                        </div>
                    </div>
                    <fieldset class="dsa-tags">
                        <legend>Tags du forum posés automatiquement <span class="par-optional">(identifiants donnés par le script de création du salon, facultatif)</span></legend>
                        <?php foreach (['athena' => 'Athena', 'recruiting' => 'Recrute', 'fr' => 'FR', 'en' => 'EN'] as $key => $label): ?>
                            <div class="pa-field">
                                <label for="dsa-tag-<?= $h($key) ?>"><?= $h($label) ?></label>
                                <input id="dsa-tag-<?= $h($key) ?>" type="text" inputmode="numeric" name="tags[<?= $h($key) ?>]" value="<?= $h((string) ($config['tags'][$key] ?? '')) ?>" placeholder="1290000000000000001">
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="pa-actions">
                        <button type="submit" class="pa-btn pa-btn--solid">Enregistrer</button>
                    </div>
                </form>
                <?php if ($masked !== ''): ?>
                    <form method="post" action="<?= $h(url($base . '/verifier')) ?>" class="pa-actions">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="pa-btn pa-btn--line">Vérifier le lien (sans rien poster)</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
