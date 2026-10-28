<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array{devices:int,acquisitions:int,artifacts:int,findings_pending:int,packets_pending?:int} $counts */
/** @var list<array<string,mixed>> $recentDevices */
/** @var list<array<string,mixed>> $pendingFindings */
/** @var list<array<string,mixed>> $pendingPackets */
$counts = is_array($counts ?? null) ? $counts : [];
$recentDevices = is_array($recentDevices ?? null) ? $recentDevices : [];
$pendingFindings = is_array($pendingFindings ?? null) ? $pendingFindings : [];
$pendingPackets = is_array($pendingPackets ?? null) ? $pendingPackets : [];
$canManage = (bool) ($canManage ?? false);

$nDevices = (int) ($counts['devices'] ?? 0);
$nAcq = (int) ($counts['acquisitions'] ?? 0);
$nArt = (int) ($counts['artifacts'] ?? 0);
$nPending = (int) ($counts['findings_pending'] ?? 0);
$nPackets = (int) ($counts['packets_pending'] ?? 0);
$isEmpty = $nDevices < 1;
$nTodo = $nPending + $nPackets;

$labUrl = static fn (string $path = ''): string => url('atak/sse/exploitation-numerique' . ($path !== '' ? '/' . $path : ''));

// Couleur du statut d'un support : où il en est dans la chaîne.
$deviceTone = static fn (string $status): string => match ($status) {
    'acquiring', 'exploiting' => 'active',
    'acquired' => 'info',
    'exploited' => 'ok',
    'returned', 'archived' => 'muted',
    default => 'wait',
};
$confidenceTone = static fn (string $level): string => match ($level) {
    'faible' => 'low',
    'eleve', 'tres_eleve' => 'high',
    default => 'mid',
};
$shortDate = static function (mixed $raw): string {
    $ts = strtotime((string) $raw);

    return $ts ? date('d/m H:i', $ts) : '';
};
$excerpt = static function (mixed $raw, int $max = 150): string {
    $text = trim(preg_replace('/\s+/u', ' ', (string) $raw) ?? '');

    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
};

// Prochaine étape conseillée, une seule à la fois.
if ($isEmpty) {
    $next = ['tone' => 'start', 'title' => 'Enregistrez le premier support', 'text' => 'Créez la fiche du matériel saisi en mission pour ouvrir la chaîne d’exploitation.', 'href' => $canManage ? $labUrl('supports/nouveau') : $labUrl('supports'), 'cta' => $canManage ? 'Enregistrer un support' : 'Voir les supports'];
} elseif ($nPackets > 0) {
    $next = ['tone' => 'todo', 'title' => $nPackets > 1 ? $nPackets . ' paquets de mission à exploiter' : '1 paquet de mission à exploiter', 'text' => 'Des renseignements préparés en mission attendent d’être lus et rattachés.', 'href' => $labUrl('a-exploiter'), 'cta' => 'Ouvrir la file'];
} elseif ($nPending > 0) {
    $next = ['tone' => 'todo', 'title' => $nPending > 1 ? $nPending . ' signaux à examiner' : '1 signal à examiner', 'text' => 'Confirmez ou écartez les propositions automatiques avant tout compte rendu.', 'href' => $labUrl('analyses'), 'cta' => 'Examiner les signaux'];
} elseif ($nAcq < 1) {
    $next = ['tone' => 'todo', 'title' => 'Déclarez une acquisition', 'text' => 'Les supports sont enregistrés : rattachez-leur une copie ou une extraction.', 'href' => $labUrl('acquisitions'), 'cta' => 'Voir les acquisitions'];
} else {
    $next = ['tone' => 'ok', 'title' => 'Rien en attente', 'text' => 'Tous les signaux sont traités. Vous pouvez rédiger le compte rendu.', 'href' => $labUrl('rapports'), 'cta' => 'Ouvrir les rapports'];
}

// Chaîne d'exploitation : chaque étape porte son compteur et son état.
$stages = [
    ['n' => 1, 'label' => 'Supports', 'hint' => 'Matériels saisis', 'value' => $nDevices, 'href' => $labUrl('supports'), 'state' => $nDevices > 0 ? 'done' : 'current'],
    ['n' => 2, 'label' => 'Acquisitions', 'hint' => 'Copies et extractions', 'value' => $nAcq, 'href' => $labUrl('acquisitions'), 'state' => $nAcq > 0 ? 'done' : ($nDevices > 0 ? 'current' : 'idle')],
    ['n' => 3, 'label' => 'Artefacts', 'hint' => 'Données indexées', 'value' => $nArt, 'href' => $labUrl('artefacts'), 'state' => $nArt > 0 ? 'done' : ($nAcq > 0 ? 'current' : 'idle')],
    ['n' => 4, 'label' => 'Signaux', 'hint' => $nPending > 0 ? 'À examiner' : 'Aucun en attente', 'value' => $nPending, 'href' => $labUrl('analyses'), 'state' => $nPending > 0 ? 'attention' : ($nArt > 0 ? 'done' : 'idle')],
    ['n' => 5, 'label' => 'Compte rendu', 'hint' => 'Après relecture humaine', 'value' => null, 'href' => $labUrl('rapports'), 'state' => ($nArt > 0 && $nPending === 0) ? 'current' : 'idle'],
];

$icon = static function (string $name): string {
    $a = ' width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    return match ($name) {
        'plus' => '<svg' . $a . '><path d="M12 5v14M5 12h14"/></svg>',
        'arrow' => '<svg' . $a . '><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
        'check' => '<svg' . $a . '><path d="m5 12.5 4.2 4.2L19 7"/></svg>',
        'signal' => '<svg' . $a . '><path d="M12 4 21 19H3z"/><path d="M12 10v4M12 16.5h.01"/></svg>',
        'packet' => '<svg' . $a . '><path d="M4 8 12 4l8 4v8l-8 4-8-4z"/><path d="m4 8 8 4 8-4M12 12v8"/></svg>',
        'phone' => '<svg' . $a . '><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 17.5h2"/></svg>',
        'laptop' => '<svg' . $a . '><rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2.5 19h19"/></svg>',
        'drive' => '<svg' . $a . '><rect x="4" y="6" width="16" height="12" rx="2"/><path d="M8 14h.01M12 14h4"/></svg>',
        'usb' => '<svg' . $a . '><path d="M9 3h6v6H9zM7 9h10v8a4 4 0 0 1-4 4h-2a4 4 0 0 1-4-4z"/></svg>',
        'card' => '<svg' . $a . '><path d="M7 3h7l4 4v14H7z"/><path d="M10 7v3M13 7v3"/></svg>',
        'radio' => '<svg' . $a . '><rect x="6" y="8" width="12" height="13" rx="2"/><path d="M9 8 15 3M9 13h6M9 17h6"/></svg>',
        'book' => '<svg' . $a . '><path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19"/></svg>',
        default => '<svg' . $a . '><circle cx="12" cy="12" r="7"/></svg>',
    };
};
$deviceIcon = static fn (string $type): string => match ($type) {
    'telephone', 'tablette' => 'phone',
    'ordinateur' => 'laptop',
    'disque_dur', 'ssd' => 'drive',
    'cle_usb' => 'usb',
    'carte_memoire', 'appareil_photo' => 'card',
    'radio_numerique', 'gps' => 'radio',
    default => 'drive',
};

require __DIR__ . '/_subnav.php';
?>
<div class="dl-hub">
    <header class="dl-head">
        <div class="dl-head__copy">
            <p class="dl-crumb">Athena / SSE / <strong>Exploitation numérique</strong></p>
            <h1>Exploitation numérique</h1>
            <p class="dl-head__lead">
                Les matériels saisis en mission (téléphone, ordinateur, clé USB, carte mémoire…) passent ici :
                on enregistre le support, on y rattache une acquisition, puis on valide les signaux avant le compte rendu.
            </p>
        </div>
        <?php if ($canManage): ?>
            <div class="dl-head__actions">
                <a class="dl-btn dl-btn--primary" href="<?= $h($labUrl('supports/nouveau')) ?>"><?= $icon('plus') ?><span>Enregistrer un support</span></a>
            </div>
        <?php endif; ?>
    </header>

    <a class="dl-next is-<?= $h($next['tone']) ?>" href="<?= $h($next['href']) ?>">
        <span class="dl-next__icon"><?= $icon($next['tone'] === 'ok' ? 'check' : ($next['tone'] === 'start' ? 'plus' : 'arrow')) ?></span>
        <span class="dl-next__copy">
            <span class="dl-next__kicker">Prochaine étape</span>
            <strong><?= $h($next['title']) ?></strong>
            <span><?= $h($next['text']) ?></span>
        </span>
        <span class="dl-next__cta"><?= $h($next['cta']) ?> <?= $icon('arrow') ?></span>
    </a>

    <section class="dl-section" aria-labelledby="dl-chain-title">
        <div class="dl-section__head">
            <h2 id="dl-chain-title">Chaîne d’exploitation</h2>
            <span class="dl-section__meta">Cliquez une étape pour l’ouvrir</span>
        </div>
        <ol class="dl-chain">
            <?php foreach ($stages as $s): ?>
                <li class="dl-stage is-<?= $h($s['state']) ?>">
                    <a href="<?= $h($s['href']) ?>">
                        <span class="dl-stage__top">
                            <span class="dl-stage__num"><?= $s['state'] === 'done' ? $icon('check') : (int) $s['n'] ?></span>
                            <span class="dl-stage__label"><?= $h($s['label']) ?></span>
                        </span>
                        <?php if ($s['value'] !== null): ?>
                            <span class="dl-stage__value"><?= (int) $s['value'] ?></span>
                        <?php else: ?>
                            <span class="dl-stage__value dl-stage__value--text">Rédiger</span>
                        <?php endif; ?>
                        <span class="dl-stage__hint"><?= $h($s['hint']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <div class="dl-grid">
        <section class="dl-section dl-card" aria-labelledby="dl-todo-title">
            <div class="dl-section__head">
                <h2 id="dl-todo-title">À traiter <?php if ($nTodo > 0): ?><span class="dl-count is-attention"><?= $nTodo ?></span><?php endif; ?></h2>
                <span class="dl-section__links">
                    <a href="<?= $h($labUrl('a-exploiter')) ?>">Paquets (<?= $nPackets ?>)</a>
                    <a href="<?= $h($labUrl('analyses')) ?>">Signaux (<?= $nPending ?>)</a>
                </span>
            </div>
            <?php if ($pendingPackets === [] && $pendingFindings === []): ?>
                <div class="dl-empty">
                    <span class="dl-empty__icon"><?= $icon('check') ?></span>
                    <strong>Rien en attente</strong>
                    <p><?= $isEmpty
                        ? 'Les signaux et paquets apparaîtront après l’enregistrement d’un support et son acquisition.'
                        : 'Aucun paquet ni signal automatique n’attend votre validation.' ?></p>
                </div>
            <?php else: ?>
                <ul class="dl-list">
                    <?php foreach (array_slice($pendingPackets, 0, 5) as $p): ?>
                        <li>
                            <a class="dl-item" href="<?= $h($labUrl('a-exploiter/' . (int) ($p['id'] ?? 0))) ?>">
                                <span class="dl-item__icon is-packet"><?= $icon('packet') ?></span>
                                <span class="dl-item__body">
                                    <span class="dl-item__title">
                                        <span class="dl-tag is-packet">Paquet</span>
                                        <strong><?= $h($p['packet_type_label'] ?? 'Paquet') ?></strong>
                                        <span class="dl-item__ref" translate="no"><?= $h($p['support_label'] ?? '') ?></span>
                                    </span>
                                    <?php if (trim((string) ($p['body_text'] ?? '')) !== ''): ?>
                                        <span class="dl-item__text" translate="no"><?= $h($excerpt($p['body_text'] ?? '')) ?></span>
                                    <?php endif; ?>
                                    <span class="dl-item__meta"><?= $h($p['quality_label'] ?? '') ?><?php if (($p['confidence_label'] ?? '') !== ''): ?> · <?= $h($p['confidence_label']) ?><?php endif; ?></span>
                                </span>
                                <span class="dl-item__go" aria-hidden="true"><?= $icon('arrow') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($pendingFindings, 0, 6) as $f): ?>
                        <?php $tone = $confidenceTone((string) ($f['confidence_level'] ?? '')); ?>
                        <li>
                            <a class="dl-item" href="<?= $h($labUrl('analyses')) ?>">
                                <span class="dl-item__icon is-signal"><?= $icon('signal') ?></span>
                                <span class="dl-item__body">
                                    <span class="dl-item__title">
                                        <span class="dl-tag is-signal">Signal</span>
                                        <strong translate="no"><?= $h($f['title'] ?? '') ?></strong>
                                    </span>
                                    <?php if (trim((string) ($f['detail'] ?? '')) !== ''): ?>
                                        <span class="dl-item__text" translate="no"><?= $h($excerpt($f['detail'] ?? '')) ?></span>
                                    <?php endif; ?>
                                    <span class="dl-item__meta">
                                        <span class="dl-conf is-<?= $h($tone) ?>"><i></i><i></i><i></i> Confiance <?= $h(mb_strtolower((string) ($f['confidence_label'] ?? ''))) ?></span>
                                        <span>Validation humaine requise</span>
                                    </span>
                                </span>
                                <span class="dl-item__go" aria-hidden="true"><?= $icon('arrow') ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($pendingFindings) > 6): ?>
                    <a class="dl-more" href="<?= $h($labUrl('analyses')) ?>">Voir les <?= $nPending ?> signaux</a>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="dl-section dl-card" aria-labelledby="dl-devices-title">
            <div class="dl-section__head">
                <h2 id="dl-devices-title">Supports récents</h2>
                <span class="dl-section__links"><a href="<?= $h($labUrl('supports')) ?>">Tous les supports</a></span>
            </div>
            <?php if ($recentDevices === []): ?>
                <div class="dl-empty">
                    <span class="dl-empty__icon"><?= $icon('phone') ?></span>
                    <strong>Aucun support enregistré</strong>
                    <p>Commencez par créer la fiche du matériel saisi sur le terrain.</p>
                    <?php if ($canManage): ?>
                        <a class="dl-btn dl-btn--ghost" href="<?= $h($labUrl('supports/nouveau')) ?>"><?= $icon('plus') ?><span>Enregistrer un support</span></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="dl-list">
                    <?php foreach ($recentDevices as $d): ?>
                        <?php
                        $model = trim((string) ($d['manufacturer'] ?? '') . ' ' . (string) ($d['model'] ?? ''));
                        $mission = trim((string) ($d['mission_label'] ?? ''));
                        $when = $shortDate($d['updated_at'] ?? ($d['created_at'] ?? ''));
                        ?>
                        <li>
                            <a class="dl-item dl-device" href="<?= $h($labUrl('supports/' . (int) ($d['id'] ?? 0))) ?>">
                                <span class="dl-item__icon"><?= $icon($deviceIcon((string) ($d['device_type'] ?? ''))) ?></span>
                                <span class="dl-item__body">
                                    <span class="dl-item__title">
                                        <strong class="dl-mono" translate="no"><?= $h($d['reference_code'] ?? '') ?></strong>
                                        <span class="dl-status is-<?= $h($deviceTone((string) ($d['status'] ?? ''))) ?>"><?= $h($d['status_label'] ?? '') ?></span>
                                    </span>
                                    <span class="dl-item__meta">
                                        <span><?= $h(trim((string) ($d['device_type_label'] ?? '')) ?: 'Type inconnu') ?></span>
                                        <?php if ($model !== ''): ?><span translate="no"><?= $h($model) ?></span><?php endif; ?>
                                        <?php if ($mission !== ''): ?><span translate="no"><?= $h($mission) ?></span><?php endif; ?>
                                    </span>
                                </span>
                                <?php if ($when !== ''): ?><span class="dl-item__when"><?= $h($when) ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <details class="dl-help"<?= $isEmpty ? ' open' : '' ?>>
        <summary><?= $icon('book') ?><span>Comment ça marche et lexique</span></summary>
        <div class="dl-help__body">
            <p class="dl-help__note">
                Athena n’ouvre pas un appareil réel : le module ingère des données déjà acquises
                (simulation Arma ou import documentaire). Un signal automatique n’est jamais une conclusion,
                seule une validation humaine consolide le renseignement.
            </p>
            <dl class="dl-lexicon">
                <div><dt>Support</dt><dd>L’objet saisi (téléphone, PC, clé…).</dd></div>
                <div><dt>Acquisition</dt><dd>La copie ou l’extraction rattachée à ce support.</dd></div>
                <div><dt>Artefact</dt><dd>Une donnée utile extraite (message, fichier, contact…).</dd></div>
                <div><dt>Paquet</dt><dd>Un renseignement scénarisé (message, document, point…) préparé en mission, à traiter ici.</dd></div>
                <div><dt>Signal</dt><dd>Une proposition automatique à confirmer ou écarter.</dd></div>
            </dl>
        </div>
    </details>
</div>
<?php
$sseContent = ob_get_clean();
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-digital-hub.css')) . '">';
require dirname(__DIR__) . '/_layout.php';
