<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * @var list<array<string,mixed>> $notes
 * @var array{total:int,today:int,immediate:int,untriaged:int} $counters
 * @var array<string,string> $filters
 * @var array<string,string> $kindOptions
 * @var array<string,string> $themeOptions
 * @var array<string,string> $urgencyOptions
 * @var array<string,string> $statusOptions
 * @var bool $canWrite
 */
$notes = is_array($notes ?? null) ? $notes : [];
$counters = is_array($counters ?? null) ? $counters : ['total' => 0, 'today' => 0, 'immediate' => 0, 'untriaged' => 0];
$filters = is_array($filters ?? null) ? $filters : [];
$kindOptions = is_array($kindOptions ?? null) ? $kindOptions : [];
$themeOptions = is_array($themeOptions ?? null) ? $themeOptions : [];
$urgencyOptions = is_array($urgencyOptions ?? null) ? $urgencyOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$canWrite = (bool) ($canWrite ?? false);
$tone = static fn (string $code): string => \App\Support\SseFieldNoteCatalog::themeTone($code);
?>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Fiches</div>
        <h1>Fiches de renseignement</h1>
        <p>
            Notes libres remontées du terrain ou rédigées au bureau : ce que l’on a vu,
            où, quand, avec les photos et documents qui vont avec. Une fiche n’engage
            rien : elle est là pour ne rien perdre, et l’analyste décide de la suite.
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Fiches</strong>
        Réf. ATH-SSE-FICHES
    </div>
</div>

<div class="sse-ops-grid" style="margin-top:14px">
    <?php if ($canWrite): ?>
        <a href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">
            <strong>Rédiger une fiche</strong>
            <span>Rédacteur plein écran : texte libre, date, lieu, thèmes et pièces jointes</span>
        </a>
    <?php endif; ?>
    <a href="<?= $h(url('atak/sse/fiches?status=transmise')) ?>">
        <strong><?= (int) $counters['untriaged'] ?> en attente de lecture</strong>
        <span>Fiches transmises que personne n’a encore prises en compte</span>
    </a>
    <a href="<?= $h(url('atak/sse/fiches?urgency=immediate')) ?>">
        <strong><?= (int) $counters['immediate'] ?> à traiter tout de suite</strong>
        <span>Fiches marquées « Immédiat » encore ouvertes</span>
    </a>
    <a href="<?= $h(url('atak/sse/transmissions')) ?>">
        <strong><?= (int) $counters['today'] ?> reçues aujourd’hui</strong>
        <span>Journal complet des transmissions terrain</span>
    </a>
</div>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">F.01</span> Filtrer la file</div>
        <div class="panel-meta"><?= count($notes) ?> fiche<?= count($notes) > 1 ? 's' : '' ?></div>
    </div>
    <div class="panel-body">
        <form method="get" action="<?= $h(url('atak/sse/fiches')) ?>" class="sse-filter-row sse-desk-filters">
            <label class="sr-only" for="fiche-q">Rechercher une fiche</label>
            <input id="fiche-q" type="search" name="q" value="<?= $h($filters['q'] ?? '') ?>"
                   placeholder="Référence, texte, lieu ou auteur…">
            <label class="sr-only" for="fiche-kind">Type de fiche</label>
            <select id="fiche-kind" name="note_kind">
                <option value="">Tous les types</option>
                <?php foreach ($kindOptions as $code => $label): ?>
                    <option value="<?= $h($code) ?>" <?= ($filters['note_kind'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="fiche-theme">Thème</label>
            <select id="fiche-theme" name="theme">
                <option value="">Tous les thèmes</option>
                <?php foreach ($themeOptions as $code => $label): ?>
                    <option value="<?= $h($code) ?>" <?= ($filters['theme'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="fiche-urgency">Degré d’urgence</label>
            <select id="fiche-urgency" name="urgency">
                <option value="">Toutes les urgences</option>
                <?php foreach ($urgencyOptions as $code => $label): ?>
                    <option value="<?= $h($code) ?>" <?= ($filters['urgency'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="fiche-status">État de suivi</label>
            <select id="fiche-status" name="status">
                <option value="">Tous les états</option>
                <?php foreach ($statusOptions as $code => $label): ?>
                    <option value="<?= $h($code) ?>" <?= ($filters['status'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= $h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Filtrer</button>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/fiches')) ?>">Tout afficher</a>
        </form>
    </div>
</section>

<section class="panel" style="margin-top:14px">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">F.02</span> Fiches reçues</div>
        <div class="panel-meta">Les plus récentes d’abord</div>
    </div>
    <?php if ($notes === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucune fiche pour ces critères.
                <?php if ($canWrite): ?>
                    <a href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">Rédigez la première</a>.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Étiquettes</th>
                    <th>Renseignement</th>
                    <th>Lieu</th>
                    <th>Auteur</th>
                    <th>Suivi</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($notes as $note): ?>
                    <tr>
                        <td class="record-id"><?= $h($note['reference_code'] ?? '') ?></td>
                        <td>
                            <?= $h($note['observed_date_label'] ?? '') ?>
                            <span class="muted"><?= $h($note['observed_time_label'] ?? '') ?></span>
                        </td>
                        <td>
                            <div class="sse-note-badges">
                                <?php foreach (($note['themes'] ?? []) as $themeCode): ?>
                                    <span class="sse-note-badge sse-note-badge--<?= $h($tone((string) $themeCode)) ?>">
                                        <?= $h((string) $themeCode) ?>
                                    </span>
                                <?php endforeach; ?>
                                <span class="sse-note-badge sse-note-badge--kind"><?= $h($note['note_kind'] ?? '') ?></span>
                                <?php if (($note['urgency'] ?? '') !== 'routine'): ?>
                                    <span class="sse-note-badge sse-note-badge--warning"><?= $h($note['urgency_label'] ?? '') ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= $h($note['excerpt'] ?? '') ?></td>
                        <td class="muted"><?= $h($note['place_label'] ?? '—') ?></td>
                        <td class="muted">
                            <?= $h($note['author_label'] ?? '—') ?>
                            <br><span class="muted"><?= $h($note['origin_label'] ?? '') ?></span>
                        </td>
                        <td>
                            <?= $h($note['status_label'] ?? '') ?>
                            <?php if ((int) ($note['attachment_count'] ?? 0) > 0): ?>
                                <br><span class="muted"><?= (int) $note['attachment_count'] ?> pièce<?= (int) $note['attachment_count'] > 1 ? 's' : '' ?> jointe<?= (int) $note['attachment_count'] > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/fiches/' . (int) ($note['id'] ?? 0))) ?>">Ouvrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="security-notice" style="margin-top:14px">
    <div class="security-notice-code">FR</div>
    <div>
        <strong>Ce qu’une fiche n’est pas</strong>
        <span>
            Une fiche de renseignement n’identifie personne et ne vaut pas preuve.
            Elle consigne un constat daté et situé ; toute conclusion passe par un
            dossier et une validation humaine.
        </span>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
