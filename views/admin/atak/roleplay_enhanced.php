<?php
declare(strict_types=1);

/**
 * Back-office — Mode roleplay ATAK (contraintes réalistes imposées aux téléphones).
 * Lecture de haut en bas : état des modules, réseau, relais, capteurs, données chiffrées, zones, enregistrement.
 * Styles : public/assets/css/back-office-atak-roleplay.css (préfixe bo-rp, jetons --ath-*, mode nuit compris).
 */

/** @var array $config Configuration roleplay actuelle */
/** @var array $tenant Informations de la communauté */
/** @var list<array{center_x: float, center_y: float, radius: float, effect: string}> $zoneRows */
/** @var array<string, string> $zoneEffectOptions */
/** @var array|null $serverTests Résultats des tests serveur */

$config = $config ?? [];
$tenant = $tenant ?? [];
$zoneRows = is_array($zoneRows ?? null) ? $zoneRows : [];
$zoneEffectOptions = is_array($zoneEffectOptions ?? null) ? $zoneEffectOptions : [];
$serverTests = is_array($serverTests ?? null) ? $serverTests : null;
$csrfToken = (string) ($csrfToken ?? '');
$roleplayFormAction = (string) ($roleplayFormAction ?? url('back-office/atak/roleplay'));
$roleplayResetUrl = (string) ($roleplayResetUrl ?? url('back-office/atak/roleplay/reset'));
$atakHubUrl = (string) ($atakHubUrl ?? url('back-office/atak'));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$on = static fn (string $key): bool => !empty($config[$key]);
$checked = static fn (string $key): string => !empty($config[$key]) ? ' checked' : '';

$networkMode = (string) ($config['network_mode'] ?? 'normal');
$networkModes = [
    'normal' => 'Normal',
    'hostile' => 'Hostile',
    'degraded' => 'Dégradé',
    'equipment' => 'Défaut matériel',
];

$modules = [
    ['key' => 'network_enabled', 'anchor' => 'reseau', 'label' => 'Réseau dégradé'],
    ['key' => 'disconnect_enabled', 'anchor' => 'reseau', 'label' => 'Coupures'],
    ['key' => 'link_via_relays', 'anchor' => 'liaison-relais', 'label' => 'Liaison par relais'],
    ['key' => 'sensor_enabled', 'anchor' => 'capteurs', 'label' => 'Capteurs médicaux'],
    ['key' => 'intel_scramble_enabled', 'anchor' => 'intel-scramble', 'label' => 'Données chiffrées'],
    ['key' => 'zones_enabled', 'anchor' => 'zones', 'label' => 'Zones de brouillage'],
];
$activeCount = count(array_filter($modules, static fn (array $m): bool => !empty($config[$m['key']])));

$zoneRowHtml = static function (array $row, string $title) use ($h, $zoneEffectOptions): string {
    ob_start(); ?>
    <div class="bo-rp-zone" data-zone-row>
        <div class="bo-rp-zone__head">
            <p class="bo-rp-zone__title" data-zone-title><?= $h($title) ?></p>
            <button type="button" class="bo-rp-link bo-rp-link--danger" data-remove-zone>Retirer</button>
        </div>
        <div class="bo-rp-grid bo-rp-grid--4">
            <label class="bo-rp-field">
                <span class="bo-rp-field__label">Est (m)</span>
                <input class="bo-rp-input" type="number" name="zone_center_x[]" step="1" value="<?= $h($row['center_x'] ?? 0) ?>">
            </label>
            <label class="bo-rp-field">
                <span class="bo-rp-field__label">Nord (m)</span>
                <input class="bo-rp-input" type="number" name="zone_center_y[]" step="1" value="<?= $h($row['center_y'] ?? 0) ?>">
            </label>
            <label class="bo-rp-field">
                <span class="bo-rp-field__label">Rayon (m)</span>
                <input class="bo-rp-input" type="number" name="zone_radius[]" min="5" step="1" value="<?= $h($row['radius'] ?? 500) ?>">
            </label>
            <label class="bo-rp-field">
                <span class="bo-rp-field__label">Effet</span>
                <select class="bo-rp-input" name="zone_effect[]">
                    <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                        <option value="<?= $h($effectValue) ?>"<?= ($row['effect'] ?? '') === $effectValue ? ' selected' : '' ?>><?= $h($effectLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="bo-rp">

    <!-- État des modules -->
    <section class="bo-rp-status" aria-labelledby="bo-rp-status-title">
        <div class="bo-rp-status__summary">
            <p class="bo-rp-status__count"><strong><?= $activeCount ?></strong> / <?= count($modules) ?></p>
            <h2 id="bo-rp-status-title" class="bo-rp-status__title">Contraintes actives</h2>
            <p class="bo-rp-muted">Chaque module s’applique à tous les téléphones de la communauté, dès l’enregistrement.</p>
        </div>
        <ul class="bo-rp-status__list">
            <?php foreach ($modules as $m): $isOn = !empty($config[$m['key']]); ?>
                <li>
                    <a class="bo-rp-chip<?= $isOn ? ' is-on' : '' ?>" href="#<?= $h($m['anchor']) ?>">
                        <span class="bo-rp-chip__dot" aria-hidden="true"></span>
                        <span class="bo-rp-chip__label"><?= $h($m['label']) ?></span>
                        <span class="bo-rp-chip__state"><?= $isOn ? 'Actif' : 'Inactif' ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="bo-rp-layout">
        <form method="POST" action="<?= $h($roleplayFormAction) ?>" class="bo-rp-main" id="roleplay-form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

            <?php if ($on('disconnect_enabled')): ?>
                <div class="bo-rp-alert" id="double-penalty-warning" role="alert">
                    <p class="bo-rp-alert__title">Coupures réseau actives</p>
                    <p>Si les joueurs ont aussi activé la simulation réseau dans les réglages CBA du mod, ils subissent les coupures deux fois. Gardez l’une ou l’autre.</p>
                </div>
            <?php endif; ?>

            <!-- Réseau -->
            <section class="bo-rp-card" id="reseau">
                <header class="bo-rp-card__head">
                    <div>
                        <h2 class="bo-rp-card__title">Réseau dégradé</h2>
                        <p class="bo-rp-muted">Retard et perte des messages entre les téléphones et Athena.</p>
                    </div>
                    <label class="bo-rp-switch">
                        <input type="checkbox" name="network_enabled"<?= $checked('network_enabled') ?>>
                        <span class="bo-rp-switch__track" aria-hidden="true"></span>
                        <span class="bo-rp-sr">Activer le réseau dégradé</span>
                    </label>
                </header>
                <div class="bo-rp-card__body">
                    <div class="bo-rp-grid bo-rp-grid--4">
                        <label class="bo-rp-field">
                            <span class="bo-rp-field__label">Mode</span>
                            <select class="bo-rp-input" name="network_mode">
                                <?php foreach ($networkModes as $value => $label): ?>
                                    <option value="<?= $h($value) ?>"<?= $networkMode === $value ? ' selected' : '' ?>><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="bo-rp-field">
                            <span class="bo-rp-field__label">Messages perdus (%)</span>
                            <input class="bo-rp-input" type="number" name="packet_loss_percent" value="<?= $h($config['packet_loss_percent'] ?? 0) ?>" min="0" max="100" step="0.1">
                        </label>
                        <label class="bo-rp-field">
                            <span class="bo-rp-field__label">Retard minimum (ms)</span>
                            <input class="bo-rp-input" type="number" name="latency_min_ms" value="<?= $h($config['latency_min_ms'] ?? 0) ?>" min="0" max="10000">
                        </label>
                        <label class="bo-rp-field">
                            <span class="bo-rp-field__label">Retard maximum (ms)</span>
                            <input class="bo-rp-input" type="number" name="latency_max_ms" value="<?= $h($config['latency_max_ms'] ?? 0) ?>" min="0" max="10000">
                        </label>
                    </div>

                    <div class="bo-rp-sub">
                        <div class="bo-rp-sub__head">
                            <div>
                                <h3 class="bo-rp-sub__title">Coupures temporaires</h3>
                                <p class="bo-rp-muted">Le téléphone perd la liaison quelques secondes, à intervalle régulier.</p>
                            </div>
                            <label class="bo-rp-switch">
                                <input type="checkbox" name="disconnect_enabled"<?= $checked('disconnect_enabled') ?>>
                                <span class="bo-rp-switch__track" aria-hidden="true"></span>
                                <span class="bo-rp-sr">Activer les coupures temporaires</span>
                            </label>
                        </div>
                        <div class="bo-rp-grid bo-rp-grid--3">
                            <label class="bo-rp-field">
                                <span class="bo-rp-field__label">Durée minimum (s)</span>
                                <input class="bo-rp-input" type="number" name="disconnect_min_sec" value="<?= $h($config['disconnect_min_sec'] ?? 5) ?>" min="1" max="300">
                            </label>
                            <label class="bo-rp-field">
                                <span class="bo-rp-field__label">Durée maximum (s)</span>
                                <input class="bo-rp-input" type="number" name="disconnect_max_sec" value="<?= $h($config['disconnect_max_sec'] ?? 30) ?>" min="1" max="300">
                            </label>
                            <label class="bo-rp-field">
                                <span class="bo-rp-field__label">Une coupure toutes les (s)</span>
                                <input class="bo-rp-input" type="number" name="disconnect_interval_sec" value="<?= $h($config['disconnect_interval_sec'] ?? 600) ?>" min="60" max="3600">
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Relais, capteurs, données chiffrées -->
            <div class="bo-rp-trio">
                <section class="bo-rp-card" id="liaison-relais">
                    <header class="bo-rp-card__head">
                        <h2 class="bo-rp-card__title">Liaison par relais</h2>
                        <label class="bo-rp-switch">
                            <input type="checkbox" name="link_via_relays"<?= $checked('link_via_relays') ?>>
                            <span class="bo-rp-switch__track" aria-hidden="true"></span>
                            <span class="bo-rp-sr">Activer la liaison par relais</span>
                        </label>
                    </header>
                    <div class="bo-rp-card__body">
                        <p class="bo-rp-muted">Un téléphone ne transmet que s’il est à portée d’un relais posé en jeu.</p>
                    </div>
                </section>

                <section class="bo-rp-card" id="capteurs">
                    <header class="bo-rp-card__head">
                        <h2 class="bo-rp-card__title">Capteurs médicaux</h2>
                        <label class="bo-rp-switch">
                            <input type="checkbox" name="sensor_enabled"<?= $checked('sensor_enabled') ?>>
                            <span class="bo-rp-switch__track" aria-hidden="true"></span>
                            <span class="bo-rp-sr">Activer les défauts des capteurs médicaux</span>
                        </label>
                    </header>
                    <div class="bo-rp-card__body bo-rp-stack">
                        <label class="bo-rp-field bo-rp-field--row">
                            <span class="bo-rp-field__label">En panne (%)</span>
                            <input class="bo-rp-input bo-rp-input--short" type="number" name="sensor_failure_percent" value="<?= $h($config['sensor_failure_percent'] ?? 0) ?>" min="0" max="100" step="0.1">
                        </label>
                        <label class="bo-rp-field bo-rp-field--row">
                            <span class="bo-rp-field__label">Valeur fausse (%)</span>
                            <input class="bo-rp-input bo-rp-input--short" type="number" name="sensor_error_percent" value="<?= $h($config['sensor_error_percent'] ?? 0) ?>" min="0" max="100" step="0.1">
                        </label>
                        <label class="bo-rp-field bo-rp-field--row">
                            <span class="bo-rp-field__label">Pas de mesure (%)</span>
                            <input class="bo-rp-input bo-rp-input--short" type="number" name="sensor_missing_percent" value="<?= $h($config['sensor_missing_percent'] ?? 0) ?>" min="0" max="100" step="0.1">
                        </label>
                    </div>
                </section>

                <section class="bo-rp-card" id="intel-scramble">
                    <header class="bo-rp-card__head">
                        <h2 class="bo-rp-card__title">Données chiffrées</h2>
                        <label class="bo-rp-switch">
                            <input type="checkbox" name="intel_scramble_enabled"<?= $checked('intel_scramble_enabled') ?>>
                            <span class="bo-rp-switch__track" aria-hidden="true"></span>
                            <span class="bo-rp-sr">Activer les données chiffrées</span>
                        </label>
                    </header>
                    <div class="bo-rp-card__body">
                        <p class="bo-rp-muted">Sans certificat valide, ou si le téléphone est capturé, les données affichées sont illisibles.</p>
                    </div>
                </section>
            </div>

            <!-- Zones -->
            <section class="bo-rp-card" id="zones">
                <header class="bo-rp-card__head">
                    <div>
                        <h2 class="bo-rp-card__title">Zones de brouillage</h2>
                        <p class="bo-rp-muted">Cercles sur la carte où la liaison est dégradée ou coupée. Positions en mètres depuis le coin sud-ouest de la carte.</p>
                    </div>
                    <label class="bo-rp-switch">
                        <input type="checkbox" name="zones_enabled"<?= $checked('zones_enabled') ?>>
                        <span class="bo-rp-switch__track" aria-hidden="true"></span>
                        <span class="bo-rp-sr">Activer les zones de brouillage</span>
                    </label>
                </header>
                <div class="bo-rp-card__body bo-rp-zones">
                    <div class="bo-rp-zones__list">
                        <div id="roleplay-zones-list" class="bo-rp-stack">
                            <p id="roleplay-zones-empty" class="bo-rp-empty<?= $zoneRows === [] ? '' : ' is-hidden' ?>">Aucune zone pour l’instant.</p>
                            <?php foreach ($zoneRows as $idx => $row): ?>
                                <?= $zoneRowHtml($row, 'Zone ' . ((int) $idx + 1)) ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="roleplay-add-zone" class="bo-rp-btn bo-rp-btn--ghost">Ajouter une zone</button>
                        <template id="roleplay-zone-template">
                            <?= $zoneRowHtml(['center_x' => 0, 'center_y' => 0, 'radius' => 500, 'effect' => 'degraded'], 'Nouvelle zone') ?>
                        </template>
                    </div>
                    <figure class="bo-rp-map">
                        <canvas id="zones-canvas" class="bo-rp-map__canvas" aria-label="Aperçu des zones"></canvas>
                        <figcaption class="bo-rp-legend">
                            <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                                <span class="bo-rp-legend__item"><span class="bo-rp-legend__dot bo-rp-fx--<?= $h($effectValue) ?>" aria-hidden="true"></span><?= $h($effectLabel) ?></span>
                            <?php endforeach; ?>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <!-- Enregistrement -->
            <div class="bo-rp-actions">
                <a class="bo-rp-link" href="<?= $h($atakHubUrl) ?>">Retour au poste ATAK</a>
                <div class="bo-rp-actions__right">
                    <button type="button" class="bo-rp-btn bo-rp-btn--ghost" data-reset-url="<?= $h($roleplayResetUrl) ?>" id="roleplay-reset">Tout désactiver</button>
                    <button type="submit" class="bo-rp-btn bo-rp-btn--primary">Enregistrer</button>
                </div>
            </div>
        </form>

        <aside class="bo-rp-side">
            <section class="bo-rp-card">
                <header class="bo-rp-card__head">
                    <div>
                        <h2 class="bo-rp-card__title">Vérifier la configuration</h2>
                        <p class="bo-rp-muted">Contrôle la cohérence de ce qui est enregistré.</p>
                    </div>
                </header>
                <div class="bo-rp-card__body">
                    <button type="button" class="bo-rp-btn bo-rp-btn--ghost bo-rp-btn--block" onclick="runServerTests()">Lancer la vérification</button>
                    <div id="server-tests-results" class="bo-rp-tests" aria-live="polite">
                        <?php if ($serverTests !== null): ?>
                            <?php foreach ($serverTests as $test): ?>
                                <div class="bo-rp-test<?= !empty($test['passed']) ? ' is-ok' : ' is-ko' ?>">
                                    <p class="bo-rp-test__name"><?= $h($test['name']) ?></p>
                                    <?php if (!empty($test['message'])): ?>
                                        <p class="bo-rp-test__msg"><?= $h($test['message']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <nav class="bo-rp-card" aria-label="Pages liées">
                <header class="bo-rp-card__head">
                    <h2 class="bo-rp-card__title">Pages liées</h2>
                </header>
                <ul class="bo-rp-card__body bo-rp-links">
                    <li><a class="bo-rp-link" href="<?= $h(url('back-office/atak/controle-serveur')) ?>">Contrôle de mission</a></li>
                    <li><a class="bo-rp-link" href="<?= $h(url('back-office/atak/relays-network')) ?>">Réseau de relais</a></li>
                    <li><a class="bo-rp-link" href="<?= $h(url('back-office/ressources/atak-realism/config')) ?>">Configuration réalisme</a></li>
                    <li><a class="bo-rp-link" href="<?= $h(url('atak')) ?>">Carte tactique</a></li>
                </ul>
            </nav>
        </aside>
    </div>
</div>

<script>
(function () {
    var list = document.getElementById('roleplay-zones-list');
    var addBtn = document.getElementById('roleplay-add-zone');
    var tpl = document.getElementById('roleplay-zone-template');
    var resetBtn = document.getElementById('roleplay-reset');

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (confirm('Désactiver tous les modules et effacer les zones ?')) {
                window.location.href = resetBtn.getAttribute('data-reset-url');
            }
        });
    }
    if (!list || !addBtn || !tpl) return;

    function renumber() {
        var rows = list.querySelectorAll('[data-zone-row]');
        var empty = document.getElementById('roleplay-zones-empty');
        if (empty) empty.classList.toggle('is-hidden', rows.length > 0);
        rows.forEach(function (row, i) {
            var title = row.querySelector('[data-zone-title]');
            if (title) title.textContent = 'Zone ' + (i + 1);
        });
        drawZonesMap();
    }

    function bindRow(row) {
        var btn = row.querySelector('[data-remove-zone]');
        if (btn) {
            btn.addEventListener('click', function () {
                row.remove();
                renumber();
            });
        }
        row.querySelectorAll('input, select').forEach(function (input) {
            input.addEventListener('input', drawZonesMap);
        });
    }

    list.querySelectorAll('[data-zone-row]').forEach(bindRow);

    addBtn.addEventListener('click', function () {
        var node = tpl.content.cloneNode(true);
        var row = node.querySelector('[data-zone-row]');
        list.appendChild(node);
        if (row) bindRow(row);
        renumber();
    });

    drawZonesMap();
    window.addEventListener('resize', drawZonesMap);
    var themeObserver = new MutationObserver(drawZonesMap);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bo-theme'] });
})();

function runServerTests() {
    var resultsDiv = document.getElementById('server-tests-results');
    if (!resultsDiv) return;

    resultsDiv.innerHTML = '<p class="bo-rp-muted">Vérification en cours…</p>';

    fetch('<?= url("api/atak/roleplay/server-tests") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= $h($csrfToken) ?>'
        }
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (!data.ok || !data.tests) {
            resultsDiv.innerHTML = '<p class="bo-rp-test is-ko">La vérification a échoué.</p>';
            return;
        }
        var html = '';
        data.tests.forEach(function (test) {
            html += '<div class="bo-rp-test ' + (test.passed ? 'is-ok' : 'is-ko') + '">';
            html += '<p class="bo-rp-test__name">' + escapeHtml(test.name) + '</p>';
            if (test.message) {
                html += '<p class="bo-rp-test__msg">' + escapeHtml(test.message) + '</p>';
            }
            html += '</div>';
        });
        resultsDiv.innerHTML = html;
    })
    .catch(function () {
        resultsDiv.innerHTML = '<p class="bo-rp-test is-ko">Athena ne répond pas. Réessayez.</p>';
    });
}

function escapeHtml(text) {
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
}

// Aperçu des zones : couleurs lues dans les jetons CSS (mode nuit compris).
function drawZonesMap() {
    var canvas = document.getElementById('zones-canvas');
    if (!canvas) return;

    var css = getComputedStyle(canvas);
    var tok = function (name, fallback) { return (css.getPropertyValue(name) || '').trim() || fallback; };
    var ctx = canvas.getContext('2d');
    var rect = canvas.getBoundingClientRect();
    if (rect.width < 1 || rect.height < 1) return;
    var dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    ctx.fillStyle = tok('--rp-map-bg', '#f1f5f9');
    ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.strokeStyle = tok('--rp-map-grid', '#cbd5e1');
    ctx.lineWidth = 0.5;
    for (var i = 0; i <= 10; i++) {
        ctx.beginPath();
        ctx.moveTo(i * rect.width / 10, 0);
        ctx.lineTo(i * rect.width / 10, rect.height);
        ctx.moveTo(0, i * rect.height / 10);
        ctx.lineTo(rect.width, i * rect.height / 10);
        ctx.stroke();
    }

    var zones = [];
    var minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    document.querySelectorAll('#roleplay-zones-list [data-zone-row]').forEach(function (row) {
        var x = parseFloat(row.querySelector('input[name="zone_center_x[]"]').value || 0);
        var y = parseFloat(row.querySelector('input[name="zone_center_y[]"]').value || 0);
        var radius = parseFloat(row.querySelector('input[name="zone_radius[]"]').value || 500);
        var effect = row.querySelector('select[name="zone_effect[]"]').value || 'degraded';
        zones.push({ x: x, y: y, radius: radius, effect: effect });
        minX = Math.min(minX, x - radius);
        maxX = Math.max(maxX, x + radius);
        minY = Math.min(minY, y - radius);
        maxY = Math.max(maxY, y + radius);
    });

    if (zones.length === 0) {
        ctx.fillStyle = tok('--rp-map-text', '#64748b');
        ctx.font = '13px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Aucune zone', rect.width / 2, rect.height / 2);
        return;
    }

    var rangeX = maxX - minX || 1000;
    var rangeY = maxY - minY || 1000;
    var scale = Math.min((rect.width - 32) / rangeX, (rect.height - 32) / rangeY);
    var offsetX = rect.width / 2 - (minX + maxX) / 2 * scale;
    // Nord en haut : l’axe des y du canvas descend.
    var offsetY = rect.height / 2 + (minY + maxY) / 2 * scale;

    zones.forEach(function (zone) {
        var color = tok('--rp-fx-' + zone.effect, '#64748b');
        var cx = offsetX + zone.x * scale;
        var cy = offsetY - zone.y * scale;
        ctx.beginPath();
        ctx.arc(cx, cy, Math.max(zone.radius * scale, 2), 0, 2 * Math.PI);
        ctx.globalAlpha = 0.35;
        ctx.fillStyle = color;
        ctx.fill();
        ctx.globalAlpha = 1;
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, 3.5, 0, 2 * Math.PI);
        ctx.fillStyle = color;
        ctx.fill();
    });
}
</script>
