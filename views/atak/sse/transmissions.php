<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $events */
/** @var array{q:string,event_type:string,source:string,since:string} $filters */
/** @var array<string,string> $eventTypes */
/** @var array<string,string> $sourceOptions */
?>
<div class="breadcrumb">Athena / SSE / <strong>Transmissions terrain</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Collecte Arma</div>
        <h1>Transmissions terrain</h1>
        <p>Journal de tout ce qui a été envoyé depuis Arma 3 (terminal SSE, ACE, Zeus, etc.) vers le bureau Athena.</p>
    </div>
    <div class="page-reference"><strong>Vue // Journal terrain</strong>Réf. ATH-SSE-TX</div>
</div>
<div class="security-notice">
    <div class="security-notice-code">TERRAIN</div>
    <div>
        <strong>Flux unidirectionnel</strong>
        <span>Ces entrées reflètent les envois opérateurs. Elles ne remplacent pas la qualification en dossier d’intérêt ou dossier validé.</span>
    </div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field">
        <label for="q">Recherche</label>
        <input id="q" name="q" value="<?= $h($filters['q'] ?? '') ?>" placeholder="Résumé, opérateur, unité…">
    </div>
    <div class="toolbar-field">
        <label for="event_type">Nature</label>
        <select id="event_type" name="event_type">
            <option value="">Toutes les natures</option>
            <?php foreach ($eventTypes as $key => $label): ?>
                <option value="<?= $h($key) ?>" <?= ($filters['event_type'] ?? '') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="source">Origine</label>
        <select id="source" name="source">
            <?php foreach ($sourceOptions as $key => $label): ?>
                <option value="<?= $h($key) ?>" <?= ($filters['source'] ?? 'TERRAIN') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field">
        <label for="since">Depuis le</label>
        <input id="since" type="date" name="since" value="<?= $h($filters['since'] ?? '') ?>">
    </div>
    <div class="toolbar-actions">
        <button class="btn btn--ghost" type="submit">Filtrer</button>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/transmissions')) ?>">Réinitialiser</a>
        <?php
        $pdfQuery = http_build_query(array_filter([
            'q' => $filters['q'] ?? '',
            'event_type' => $filters['event_type'] ?? '',
            'source' => $filters['source'] ?? '',
            'since' => $filters['since'] ?? '',
        ], static fn (string $v): bool => $v !== ''));
        ?>
        <a class="btn" href="<?= $h(url('atak/sse/transmissions/pdf') . ($pdfQuery !== '' ? '?' . $pdfQuery : '')) ?>">Télécharger le journal (PDF)</a>
    </div>
</form>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.05</span> Journal des transmissions</div>
        <div class="panel-meta"><?= count($events) ?> entrée<?= count($events) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($events === []): ?>
        <div class="empty-state">
            <div class="empty-state-inner">
                <div class="empty-symbol">TX</div>
                <strong>Aucune transmission enregistrée</strong>
                <p>Dès qu’un opérateur envoie une fiche ou un relevé depuis Arma, elle apparaît ici pour exploitation bureau.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Horodatage</th>
                    <th>Nature</th>
                    <th>Origine</th>
                    <th>Résumé</th>
                    <th>Logiciel</th>
                    <th>Opérateur</th>
                    <th>Cotation</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="record-id"><?= $h(substr((string) ($event['event_time'] ?? ''), 0, 16)) ?></td>
                        <td><span class="badge"><?= $h($event['event_type_label'] ?? '') ?></span></td>
                        <td><?= $h($event['source_system_label'] ?? '') ?></td>
                        <td>
                            <span class="record-name"><?= $h($event['summary'] ?? '') ?></span>
                            <?php if (!empty($event['unit_label'])): ?>
                                <span class="record-sub"><?= $h($event['unit_label']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($event['client_label'])): ?>
                                <span class="record-id"><?= $h($event['client_label']) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $h(($event['author_label'] ?? '') !== '' ? $event['author_label'] : '—') ?></td>
                        <td class="record-id"><?= $h($event['confidence_code'] ?? '') ?></td>
                        <td class="sse-tx-actions">
                            <a class="btn-open" href="<?= $h(url('atak/sse/transmissions/' . (int) ($event['id'] ?? 0))) ?>">Ouvrir</a>
                            <a class="btn-open" href="<?= $h(url('atak/sse/transmissions/' . (int) ($event['id'] ?? 0) . '/pdf')) ?>">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
<?php
/** @var list<array{id:string,label:string,masked:string}> $discordRelays */
$discordRelays = is_array($discordRelays ?? null) ? $discordRelays : [];
$useCommunityRelay = !empty($useCommunityRelay);
$communityRelayReady = !empty($communityRelayReady);
?>
<section class="panel sse-tx-discord">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">P.06</span> Relais Discord</div>
        <div class="panel-meta"><?= count($discordRelays) ?> salon<?= count($discordRelays) > 1 ? 's' : '' ?></div>
    </div>
    <div class="panel-body">
        <p class="muted">Publiez automatiquement les nouvelles transmissions terrain dans un ou plusieurs salons Discord. Dans Discord : paramètres du salon → Intégrations → créer un relais, puis collez le lien ici.</p>

        <form class="toolbar sse-tx-discord__community" method="post" action="<?= $h(url('atak/sse/transmissions/relais/communaute')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label class="sse-tx-discord__check">
                <input type="hidden" name="use_community_relay" value="0">
                <input type="checkbox" name="use_community_relay" value="1" <?= $useCommunityRelay ? 'checked' : '' ?> <?= $communityRelayReady ? '' : 'disabled' ?>>
                <span>
                    Relayer aussi vers le salon Discord de la communauté
                    <?php if (!$communityRelayReady): ?>
                        <small> — aucun salon n’est encore configuré dans les <a href="<?= $h(url('back-office/integrations')) ?>">intégrations</a>.</small>
                    <?php endif; ?>
                </span>
            </label>
            <button class="btn btn--ghost" type="submit" <?= $communityRelayReady ? '' : 'disabled' ?>>Enregistrer</button>
        </form>

        <?php if ($discordRelays !== []): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Intitulé</th>
                        <th>Lien</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($discordRelays as $relay): ?>
                        <tr>
                            <td><span class="record-name"><?= $h($relay['label'] ?? '') ?></span></td>
                            <td class="muted"><?= $h($relay['masked'] ?? '') ?></td>
                            <td class="sse-tx-actions">
                                <form method="post" action="<?= $h(url('atak/sse/transmissions/relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/essai')) ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button class="btn-open" type="submit">Essai</button>
                                </form>
                                <form method="post" action="<?= $h(url('atak/sse/transmissions/relais/' . rawurlencode((string) ($relay['id'] ?? '')) . '/supprimer')) ?>" onsubmit="return confirm('Retirer ce relais Discord ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button class="btn-open" type="submit">Retirer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form class="toolbar" method="post" action="<?= $h(url('atak/sse/transmissions/relais')) ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="toolbar-field">
                <label for="discord_label">Intitulé du salon</label>
                <input id="discord_label" name="label" maxlength="80" placeholder="Renseignement, TOC…">
            </div>
            <div class="toolbar-field toolbar-field--wide">
                <label for="discord_url">Lien du relais Discord</label>
                <input id="discord_url" name="discord_url" type="url" required maxlength="500" placeholder="https://discord.com/api/webhooks/…">
            </div>
            <div class="toolbar-actions">
                <button class="btn" type="submit">Ajouter le relais</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
