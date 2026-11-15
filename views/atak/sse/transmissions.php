<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $events */
/** @var array{q:string,event_type:string,source:string,since:string} $filters */
/** @var array<string,string> $eventTypes */
/** @var array<string,string> $sourceOptions */
/** @var string $sort */
/** @var array{total:int,today:int,week:int,natures:array<string,int>} $counters */
/** @var array{today:string,week:string,month:string} $periods */
/** @var \DateTimeImmutable $now */
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-transmissions.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '')
    . '<script defer src="' . $h(asset_url('assets/js/ui_confirm_modal.js')) . '"></script>'
    . '<script defer src="' . $h(asset_url('assets/js/sse-transmissions.js')) . '"></script>';

$canManage = !empty($canManage);
$discordRelays = is_array($discordRelays ?? null) ? $discordRelays : [];
$useCommunityRelay = !empty($useCommunityRelay);
$communityRelayReady = !empty($communityRelayReady);
$sort = \App\Support\SseTransmissionIndex::normalizeSort((string) ($sort ?? ''));
$now = ($now ?? null) instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable('now');
$periods = is_array($periods ?? null) ? $periods : \App\Support\SseTransmissionIndex::periods($now);
$counters = is_array($counters ?? null) ? $counters : \App\Support\SseTransmissionIndex::counters($events, $now);
$filtered = \App\Support\SseTransmissionIndex::hasActiveFilter($filters);
$base = url('atak/sse/transmissions');
$sourceKey = (string) ($filters['source'] ?? 'TERRAIN');
$relayCount = count($discordRelays) + ($useCommunityRelay && $communityRelayReady ? 1 : 0);
$sortOptions = [
    'recent' => 'Les plus récentes',
    'ancien' => 'Les plus anciennes',
    'nature' => 'Par nature',
    'operateur' => 'Par opérateur',
];
$pdfQuery = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'event_type' => $filters['event_type'] ?? '',
    'source' => $filters['source'] ?? '',
    'since' => $filters['since'] ?? '',
], static fn (string $v): bool => $v !== ''));
$periodLink = static fn (string $since): string => $base . '?' . http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'event_type' => $filters['event_type'] ?? '',
    'source' => $filters['source'] ?? '',
    'since' => $since,
    'tri' => $sort !== 'recent' ? $sort : '',
], static fn (string $v): bool => $v !== ''));
$dots = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="5" cy="12" r="1.9" fill="currentColor"/><circle cx="12" cy="12" r="1.9" fill="currentColor"/><circle cx="19" cy="12" r="1.9" fill="currentColor"/></svg>';
?>
<div class="txp" data-txp>
<div class="page-heading txp-head">
    <div>
        <h1>Transmissions terrain</h1>
        <p>Ce que les opérateurs ont envoyé depuis Arma 3 (terminal SSE, ACE, Zeus) vers le bureau, à consulter, exporter ou relayer vers Discord.</p>
    </div>
    <div class="txp-head__actions">
        <a class="btn btn--ghost" href="<?= $h($base . '/pdf' . ($pdfQuery !== '' ? '?' . $pdfQuery : '')) ?>">Exporter le journal (PDF)</a>
    </div>
</div>

<div class="txp-tabs" role="tablist" aria-label="Sections de la page">
    <button type="button" class="txp-tab is-active" role="tab" id="txp-tab-journal" aria-controls="txp-panel-journal" aria-selected="true" data-txp-tab="journal">
        Journal <span class="txp-tab__n"><?= (int) $counters['total'] ?></span>
    </button>
    <?php if ($canManage): ?>
        <button type="button" class="txp-tab" role="tab" id="txp-tab-relais" aria-controls="txp-panel-relais" aria-selected="false" tabindex="-1" data-txp-tab="relais">
            Relais Discord <span class="txp-tab__n"><?= (int) $relayCount ?></span>
        </button>
    <?php endif; ?>
</div>

<section class="txp-panel" id="txp-panel-journal" role="tabpanel" aria-labelledby="txp-tab-journal" data-txp-panel="journal">
    <h2 class="txp-h2">Dernières transmissions</h2>
    <p class="txp-lead">Les envois les plus récents en premier. Ouvrez-en un pour le lire sans quitter la liste.</p>

    <ul class="txp-stats" aria-label="Repères du journal">
        <li><a class="txp-stat<?= !$filtered ? ' is-current' : '' ?>" href="<?= $h($base) ?>"><strong><?= (int) $counters['total'] ?></strong><span><?= $filtered ? 'Résultats (tout effacer)' : 'Dans le journal' ?></span></a></li>
        <li><a class="txp-stat<?= ($filters['since'] ?? '') === $periods['today'] ? ' is-current' : '' ?>" href="<?= $h($periodLink($periods['today'])) ?>"><strong><?= (int) $counters['today'] ?></strong><span>Aujourd’hui</span></a></li>
        <li><a class="txp-stat<?= ($filters['since'] ?? '') === $periods['week'] ? ' is-current' : '' ?>" href="<?= $h($periodLink($periods['week'])) ?>"><strong><?= (int) $counters['week'] ?></strong><span>7 derniers jours</span></a></li>
        <?php if ($canManage): ?>
            <li><a class="txp-stat" href="<?= $h($base . '?onglet=relais') ?>" data-txp-goto="relais"><strong><?= (int) $relayCount ?></strong><span>Relais Discord actifs</span></a></li>
        <?php endif; ?>
    </ul>

    <form class="txp-filters" method="get" action="<?= $h($base) ?>" role="search" aria-label="Filtrer le journal">
        <div class="txp-field txp-field--grow">
            <label for="q">Recherche</label>
            <input id="q" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Résumé, opérateur, unité…" autocomplete="off">
        </div>
        <div class="txp-field">
            <label for="event_type">Nature</label>
            <select id="event_type" name="event_type">
                <option value="">Toutes les natures</option>
                <?php foreach ($eventTypes as $key => $label): ?>
                    <option value="<?= $h($key) ?>" <?= ($filters['event_type'] ?? '') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="txp-field txp-field--wide">
            <label for="source">Origine</label>
            <select id="source" name="source">
                <?php foreach ($sourceOptions as $key => $label): ?>
                    <option value="<?= $h($key) ?>" <?= $sourceKey === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="txp-field">
            <label for="since">Depuis le</label>
            <input id="since" type="date" name="since" value="<?= $h($filters['since'] ?? '') ?>">
        </div>
        <div class="txp-field">
            <label for="tri">Trier</label>
            <select id="tri" name="tri">
                <?php foreach ($sortOptions as $key => $label): ?>
                    <option value="<?= $h($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="txp-filters__go">
            <button class="btn" type="submit">Filtrer</button>
            <?php if ($filtered || $sort !== 'recent'): ?>
                <a class="btn btn--ghost" href="<?= $h($base) ?>">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($events === []): ?>
        <div class="txp-empty">
            <?php if ($filtered): ?>
                <strong>Aucune transmission ne correspond à ces filtres</strong>
                <p>Élargissez la période, choisissez « Toutes les natures » ou retirez la recherche pour revoir le journal.</p>
                <a class="btn" href="<?= $h($base) ?>">Réinitialiser les filtres</a>
            <?php else: ?>
                <strong>Aucune transmission enregistrée pour l’instant</strong>
                <p>Dès qu’un opérateur envoie une fiche ou un relevé depuis Arma 3, elle apparaît ici. Rien à configurer côté bureau pour la recevoir.</p>
                <?php if ($canManage): ?>
                    <a class="btn btn--ghost" href="<?= $h($base . '?onglet=relais') ?>" data-txp-goto="relais">Relayer les prochaines vers Discord</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="txp-list">
            <table data-sse-keep class="txp-table">
                <caption class="sr-only">Transmissions terrain, <?= count($events) ?> entrée(s)</caption>
                <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Nature</th>
                    <th scope="col">Sujet</th>
                    <th scope="col">Opérateur</th>
                    <th scope="col">Cotation</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <?php
                    $eid = (int) ($event['id'] ?? 0);
                    $open = url('atak/sse/transmissions/' . $eid);
                    $pdf = $open . '/pdf';
                    $author = trim((string) ($event['author_label'] ?? ''));
                    $unit = trim((string) ($event['unit_label'] ?? ''));
                    $client = trim((string) ($event['client_label'] ?? ''));
                    $summary = trim((string) ($event['summary'] ?? ''));
                    $tx = [
                        'id' => $eid,
                        'href' => $open,
                        'pdf' => $pdf,
                        'when' => substr((string) ($event['event_time'] ?? ''), 0, 16),
                        'nature' => (string) ($event['event_type_label'] ?? ''),
                        'source' => (string) ($event['source_system_label'] ?? ''),
                        'summary' => $summary,
                        'unit' => $unit,
                        'client' => $client,
                        'author' => $author,
                        'conf' => (string) ($event['confidence_code'] ?? ''),
                    ];
                    ?>
                    <tr data-tx="<?= $h(json_encode($tx, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                        <td class="txp-when" data-label="Date"><time datetime="<?= $h(str_replace(' ', 'T', substr((string) ($event['event_time'] ?? ''), 0, 19))) ?>"><?= $h(\App\Support\SseTransmissionIndex::shortWhen((string) ($event['event_time'] ?? ''), $now)) ?></time></td>
                        <td data-label="Nature"><span class="badge"><?= $h($event['event_type_label'] ?? '') ?></span></td>
                        <td class="txp-subject" data-label="Sujet">
                            <button type="button" class="txp-peek" data-txp-peek aria-haspopup="dialog"><?= $h($summary !== '' ? $summary : 'Sans résumé') ?></button>
                            <?php if ($unit !== ''): ?><span class="txp-sub"><?= $h($unit) ?></span><?php endif; ?>
                            <?php if ($author !== ''): ?><span class="txp-sub txp-sub--author"><?= $h($author) ?></span><?php endif; ?>
                        </td>
                        <td class="txp-author" data-label="Opérateur"><?= $author !== '' ? $h($author) : '<span class="muted">Non renseigné</span>' ?></td>
                        <td class="txp-conf" data-label="Cotation"><?= $h($event['confidence_code'] ?? '') ?></td>
                        <td class="txp-actions"><div class="txp-actions__in">
                            <a class="btn txp-open" href="<?= $h($open) ?>">Ouvrir<span class="sr-only"> la transmission TX-<?= $eid ?></span></a>
                            <div class="txp-menu" data-txp-menu>
                                <button type="button" class="txp-menu__btn" aria-haspopup="menu" aria-expanded="false" aria-label="Plus d’actions pour TX-<?= $eid ?>"><?= $dots ?></button>
                                <div class="txp-menu__list" role="menu" hidden>
                                    <button type="button" role="menuitem" data-txp-peek>Aperçu rapide</button>
                                    <a role="menuitem" href="<?= $h($pdf) ?>">Télécharger en PDF</a>
                                    <?php if ($canManage): ?>
                                        <form method="post" action="<?= $h($open . '/discord') ?>" data-ui-confirm="1" data-ui-confirm-title="Envoyer vers Discord ?" data-ui-confirm-body="Cette transmission sera publiée dans les salons Discord reliés.">
                                            <?= \App\Core\Csrf::field() ?>
                                            <button type="submit" role="menuitem">Envoyer vers Discord</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="txp-foot"><span><?= count($events) ?> transmission(s) affichée(s).</span><?php if (count($events) >= 100): ?> <span>Les 100 plus récentes de la sélection : affinez avec les filtres.</span><?php endif; ?></p>
    <?php endif; ?>
</section>

<?php if ($canManage): ?>
<section class="txp-panel" id="txp-panel-relais" role="tabpanel" aria-labelledby="txp-tab-relais" data-txp-panel="relais" hidden>
    <div class="txp-bar">
        <div>
            <h2 class="txp-h2">Relais Discord</h2>
            <p class="txp-lead">Chaque nouvelle transmission terrain est publiée dans les salons Discord listés ici, pour que l’équipe la voie sans ouvrir le portail.</p>
        </div>
        <button type="button" class="btn" data-txp-open="txp-relay-add" aria-haspopup="dialog">Ajouter un relais</button>
    </div>

    <form class="txp-community" method="post" action="<?= $h(url('atak/sse/transmissions/relais/communaute')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="use_community_relay" value="0">
        <label class="txp-check">
            <input type="checkbox" name="use_community_relay" value="1" <?= $useCommunityRelay ? 'checked' : '' ?> <?= $communityRelayReady ? '' : 'disabled' ?>>
            <span>
                <strong>Relayer aussi vers le salon Discord de la communauté</strong>
                <?php if ($communityRelayReady): ?>
                    <small>Le salon est celui configuré dans les intégrations de la communauté.</small>
                <?php else: ?>
                    <small>Aucun salon n’est configuré : renseignez-le d’abord dans les <a href="<?= $h(url('back-office/integrations')) ?>">intégrations</a>.</small>
                <?php endif; ?>
            </span>
        </label>
        <button class="btn btn--ghost" type="submit" <?= $communityRelayReady ? '' : 'disabled' ?>>Enregistrer</button>
    </form>

    <?php if ($discordRelays === []): ?>
        <div class="txp-empty">
            <strong>Aucun salon relié</strong>
            <p>Ajoutez un relais pour publier automatiquement les transmissions dans un salon Discord, par exemple celui du renseignement ou du TOC.</p>
            <button type="button" class="btn" data-txp-open="txp-relay-add" aria-haspopup="dialog">Ajouter un relais</button>
        </div>
    <?php else: ?>
        <ul class="txp-relays">
            <?php foreach ($discordRelays as $relay): ?>
                <?php $rid = rawurlencode((string) ($relay['id'] ?? '')); ?>
                <li class="txp-relay">
                    <div class="txp-relay__id">
                        <strong><?= $h($relay['label'] ?? '') ?></strong>
                        <span><?= $h($relay['masked'] ?? '') ?></span>
                    </div>
                    <div class="txp-relay__act">
                        <form method="post" action="<?= $h(url('atak/sse/transmissions/relais/' . $rid . '/essai')) ?>">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn--ghost" type="submit">Envoyer un essai</button>
                        </form>
                        <form method="post" action="<?= $h(url('atak/sse/transmissions/relais/' . $rid . '/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Retirer ce relais ?" data-ui-confirm-body="Les nouvelles transmissions ne seront plus publiées dans ce salon Discord.">
                            <?= \App\Core\Csrf::field() ?>
                            <button class="btn btn--danger" type="submit">Retirer<span class="sr-only"> le relais <?= $h($relay['label'] ?? '') ?></span></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<dialog class="txp-dialog" id="txp-relay-add" aria-labelledby="txp-relay-add-title" data-txp-dialog>
    <form class="txp-dialog__card" method="post" action="<?= $h(url('atak/sse/transmissions/relais')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <header class="txp-dialog__head">
            <div>
                <h2 id="txp-relay-add-title">Ajouter un relais Discord</h2>
                <p>Un relais publie chaque nouvelle transmission dans un salon de votre serveur Discord.</p>
            </div>
            <button type="button" class="txp-close" data-txp-close aria-label="Fermer">×</button>
        </header>
        <div class="txp-dialog__body">
            <div class="txp-field">
                <label for="discord_label">Intitulé du salon</label>
                <input id="discord_label" name="label" autofocus maxlength="80" placeholder="Renseignement, TOC…" autocomplete="off">
            </div>
            <div class="txp-field">
                <label for="discord_url">Lien du relais Discord</label>
                <input id="discord_url" name="discord_url" type="url" required maxlength="500" placeholder="https://discord.com/api/webhooks/…" autocomplete="off" aria-describedby="discord_url_help">
                <small id="discord_url_help">Le lien est enregistré mais jamais réaffiché en entier.</small>
            </div>
            <details class="txp-howto">
                <summary>Où trouver ce lien ?</summary>
                <ol>
                    <li>Dans Discord, ouvrez les paramètres du salon, puis « Intégrations ».</li>
                    <li>Créez un relais (webhook) et copiez son lien.</li>
                    <li>Collez-le ci-dessus, puis utilisez « Envoyer un essai » pour vérifier.</li>
                </ol>
            </details>
        </div>
        <footer class="txp-dialog__foot">
            <button type="button" class="btn btn--ghost" data-txp-close>Annuler</button>
            <button type="submit" class="btn">Ajouter le relais</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<dialog class="txp-drawer" id="txp-drawer" aria-labelledby="txp-drawer-title" data-txp-drawer>
    <div class="txp-drawer__card">
        <header class="txp-dialog__head">
            <div>
                <h2 id="txp-drawer-title" data-f="nature">Transmission</h2>
                <p data-f="when"></p>
            </div>
            <button type="button" class="txp-close" data-txp-close aria-label="Fermer l’aperçu">×</button>
        </header>
        <div class="txp-drawer__body">
            <p class="txp-drawer__summary" data-f="summary"></p>
            <dl class="txp-kv">
                <div><dt>Origine</dt><dd data-f="source"></dd></div>
                <div><dt>Opérateur</dt><dd data-f="author"></dd></div>
                <div><dt>Unité</dt><dd data-f="unit"></dd></div>
                <div><dt>Cotation</dt><dd data-f="conf"></dd></div>
                <div><dt>Logiciel</dt><dd data-f="client"></dd></div>
            </dl>
            <p class="txp-drawer__hint">La fiche complète montre le contenu transmis, les identités liées et les images.</p>
        </div>
        <footer class="txp-dialog__foot">
            <a class="btn btn--ghost" data-f-href="pdf" href="#">Télécharger en PDF</a>
            <a class="btn" data-f-href="href" href="#">Ouvrir la fiche complète</a>
        </footer>
    </div>
</dialog>

<dialog class="txp-dialog txp-dialog--small" id="portal-ui-confirm" aria-labelledby="portal-ui-confirm-title" aria-describedby="portal-ui-confirm-body">
    <form method="dialog" class="txp-dialog__card">
        <header class="txp-dialog__head">
            <div>
                <h2 id="portal-ui-confirm-title">Confirmer</h2>
                <p id="portal-ui-confirm-body"></p>
            </div>
        </header>
        <footer class="txp-dialog__foot">
            <button type="submit" value="cancel" class="btn btn--ghost">Annuler</button>
            <button type="submit" value="confirm" class="btn">Confirmer</button>
        </footer>
    </form>
</dialog>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
