<?php
declare(strict_types=1);

/**
 * Ma situation › Mes papiers : lecture seule (MemberIdentityPapersService::memberView).
 *
 * @var array{identities: list<array<string, mixed>>, documents: list<array<string, mixed>>, kits: list<array<string, mixed>>, checks: list<array<string, mixed>>, steam_linked: bool} $papers
 * @var list<array{id: int, label: string}> $maps
 * @var list<array<string, mixed>> $briefs fiches de couverture (SseIdOperationRepository::briefsForUser)
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$docById = [];
foreach ($papers['documents'] as $d) {
    $docById[(int) $d['id']] = $d;
}
$mapLabel = static function (?int $ctx) use ($maps): string {
    foreach ($maps as $m) {
        if ($m['id'] === $ctx) {
            return $m['label'];
        }
    }

    return 'Toutes les cartes';
};
$scopeLabels = ['default' => 'Par défaut', 'map' => 'Pour une carte', 'operation' => 'Pour une opération'];
?>
<div class="bo-member-situation">
    <p class="idm-sim"><strong>SIMULATION</strong> Papiers fictifs pour le jeu, émis par des pays imaginaires. Aucune valeur réelle.</p>

    <?php if ($papers['identities'] === []): ?>
        <p class="bo-member-situation__lead">Vous n’avez pas encore de papiers de jeu. Ils sont créés par les responsables de votre communauté, à partir de la fiche de votre personnage.</p>
    <?php endif; ?>

    <?php if ($papers['documents'] !== []): ?>
        <h2>Mes papiers</h2>
        <div class="idm-docs">
            <?php foreach ($papers['documents'] as $d): ?>
                <div class="idm-doc">
                    <div class="idm-doc__head">
                        <b><?= $h($d['doc_type_label']) ?> · <?= $h($d['country_name']) ?></b>
                        <span class="idm-doc__num" translate="no"><?= $h($d['doc_number']) ?></span>
                        <span><?= $h($d['workflow'] === 'issued' ? 'En jeu' : $d['workflow_label']) ?></span>
                        <?php if ($d['expired']): ?><span>Expiré</span><?php endif; ?>
                        <?php if ($d['st_report'] !== 'none'): ?><span><?= $h(\App\Support\SseIdentityCatalog::label(\App\Support\SseIdentityCatalog::REPORTS, $d['st_report'])) ?></span><?php endif; ?>
                    </div>
                    <div class="idp-card" style="margin-top:8px">
                        <div class="idp-card__head"><span><?= $h($d['country_name']) ?> · <?= $h($d['doc_type_label']) ?></span><span>SIMULATION</span></div>
                        <div class="idp-card__name"><?= $h($d['holder_name']) ?></div>
                        <div class="idp-grid" style="margin-top:8px">
                            <div><span class="muted">Né(e) le</span><br><strong><?= $h($d['identity']['birth_date'] ?? '—') ?></strong></div>
                            <div><span class="muted">Lieu de naissance</span><br><strong><?= $h($d['identity']['birth_place'] ?? '—') ?></strong></div>
                            <div><span class="muted">Délivré le</span><br><strong><?= $h($d['issued_on'] ?? '—') ?></strong></div>
                            <div><span class="muted">Expire le</span><br><strong><?= $h($d['expires_on'] ?? '—') ?></strong></div>
                            <div><span class="muted">Autorité</span><br><strong><?= $h($d['authority'] ?? '—') ?></strong></div>
                        </div>
                        <div class="idp-mrz" translate="no" aria-label="Bande de lecture automatique"><?= $h(implode("\n", $d['mrz'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($briefs)): ?>
        <h2>Mes briefs de couverture</h2>
        <?php foreach ($briefs as $b): ?>
            <div class="idm-doc">
                <div class="idm-doc__head">
                    <b><?= $h($b['title'] !== '' ? $b['title'] : 'Fiche de couverture') ?></b>
                    <span><?= $h($scopeLabels[$b['scope']] ?? '') ?> — <?= $h($b['op_key'] !== null ? 'opération ' . $b['op_key'] : $mapLabel($b['context_id'])) ?></span>
                </div>
                <dl class="idm-brief">
                    <?php foreach (['biography' => 'Biographie', 'legend' => 'Légende', 'contacts' => 'Contacts', 'instructions' => 'Consignes', 'phone' => 'Téléphone (simulé)', 'address' => 'Adresse'] as $k => $label): ?>
                        <?php if (($b[$k] ?? null) !== null && $b[$k] !== ''): ?>
                            <dt><?= $h($label) ?></dt>
                            <dd style="white-space:pre-line;margin:0 0 6px"><?= $h($b[$k]) ?></dd>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Mon kit de mission</h2>
    <?php if (!$papers['steam_linked']): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err">Votre compte n’est pas lié à Steam : le serveur de jeu ne peut pas vous remettre vos papiers. <a href="<?= $h(url('account/steam/connect')) ?>">Lier mon compte Steam</a></p>
    <?php endif; ?>
    <?php if ($papers['kits'] === []): ?>
        <p>Aucun kit : vous arrivez en mission sans papiers.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($papers['kits'] as $kit): ?>
                <li><b><?= $h($scopeLabels[$kit['scope']] ?? '') ?></b> — <?= $h($kit['op_key'] !== null ? 'opération ' . $kit['op_key'] : $mapLabel($kit['context_id'])) ?> :
                    <?= $h(implode(' · ', array_map(static fn (int $i): string => isset($docById[$i]) ? $docById[$i]['doc_type_label'] . ' ' . $docById[$i]['doc_number'] : '', $kit['document_ids']))) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Contrôles subis</h2>
    <?php if ($papers['checks'] === []): ?>
        <p>Aucun contrôle d’identité enregistré.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($papers['checks'] as $c): ?>
                <li><?= $h(substr((string) $c['created_at'], 0, 16)) ?> — <?= $h($c['verdict_label']) ?><?= $c['controller_callsign'] !== null ? ' · par ' . $h($c['controller_callsign']) : '' ?><?= $c['grid'] !== null ? ' · carroyage ' . $h($c['grid']) : '' ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
