<?php

declare(strict_types=1);

use App\Repositories\AtakRealismRepository;
use App\Support\AtakDevicePresenter as Dev;

$terminals = is_array($terminals ?? null) ? array_values(array_filter($terminals, 'is_array')) : [];
$gamePhone = is_array($gamePhone ?? null) ? $gamePhone : [];
$linkEvents = is_array($linkEvents ?? null) ? $linkEvents : [];
$success = $success ?? null;
$error = $error ?? null;
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$fmtDate = static function (mixed $raw, string $format = 'd/m/Y à H:i'): string {
    $ts = Dev::utcTimestamp((string) $raw);

    return $ts === null ? '—' : date($format, $ts);
};
$verdicts = [
    'ok' => ['Liaison opérationnelle', 'ok'],
    'warn' => ['Liaison à vérifier', 'warn'],
    'bad' => ['Liaison interrompue', 'bad'],
];
$checkIcons = ['ok' => '✓', 'warn' => '!', 'bad' => '✕'];

$active = array_values(array_filter($terminals, static fn (array $t): bool => strtolower((string) ($t['status'] ?? '')) !== 'revoked'));
$retired = array_values(array_filter($terminals, static fn (array $t): bool => strtolower((string) ($t['status'] ?? '')) === 'revoked'));

// Vue d'ensemble : combien de terminaux répondent, et le certificat qui expire le plus tôt.
$online = 0;
$lastSeen = null;
$minDays = null;
foreach ($active as $t) {
    $ts = Dev::utcTimestamp((string) ($t['last_seen_at'] ?? ''));
    if ($ts !== null) {
        $lastSeen = $lastSeen === null ? $ts : max($lastSeen, $ts);
    }
    $online += Dev::linkState(($t['last_seen_at'] ?? null) !== null ? (string) $t['last_seen_at'] : null)['key'] === 'online' ? 1 : 0;
    $life = Dev::certificateLifetime(Dev::certificateOfTerminal($t));
    if (in_array($life['state'], ['valid', 'expiring'], true) && $life['days_left'] !== null) {
        $minDays = $minDays === null ? (int) $life['days_left'] : min($minDays, (int) $life['days_left']);
    }
}
$lastLink = Dev::linkState($lastSeen !== null ? gmdate('Y-m-d H:i:s', $lastSeen) : null);
?>
<div class="bo-member-situation atk-dev atk-link">
    <?php if ($success): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <div class="bo-member-situation__actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/appareils')) ?>">Mes appareils</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/premiere-liaison')) ?>">Configurer ATAK</a>
        <a class="ath-btn" href="<?= $h(url('atak')) ?>">Ouvrir la carte</a>
    </div>

    <?php if ($active === []): ?>
        <section class="bo-member-situation__card atk-dev__empty">
            <?php $phone = ['callsign' => '', 'link' => 'never', 'subtitle' => 'Pas encore lié', 'size' => 'sm']; require base_path('views/partials/atak_android_phone.php'); ?>
            <div>
                <h2>Aucune liaison active</h2>
                <p>Aucun téléphone ATAK n’est lié à votre compte. Configurez ATAK, puis validez la liaison depuis l’app Liaison du téléphone en jeu : un certificat client vous sera délivré par un responsable ATAK.</p>
                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/premiere-liaison')) ?>">Configurer ATAK</a>
            </div>
        </section>
    <?php else: ?>
        <div class="atk-kpis">
            <div class="atk-kpi"><span>Terminaux liés</span><strong><?= count($active) ?></strong><small><?= count($retired) ?> retiré<?= count($retired) > 1 ? 's' : '' ?></small></div>
            <div class="atk-kpi <?= $online > 0 ? 'atk-kpi--ok' : '' ?>"><span>En liaison</span><strong><?= $online ?></strong><small>Signe de vie depuis moins de 3 min</small></div>
            <div class="atk-kpi <?= $minDays === null ? 'atk-kpi--bad' : ($minDays <= 30 ? 'atk-kpi--warn' : 'atk-kpi--ok') ?>"><span>Certificat</span><strong><?= $minDays === null ? '—' : $minDays . ' j' ?></strong><small><?= $minDays === null ? 'Aucun certificat valide' : 'Avant la première expiration' ?></small></div>
            <div class="atk-kpi"><span>Dernier signe de vie</span><strong style="font-size:1.05rem"><?= $h($lastLink['ago'] !== '' ? $lastLink['ago'] : 'Jamais') ?></strong><small><?= $h($lastLink['label']) ?></small></div>
        </div>
    <?php endif; ?>

    <?php foreach ($active as $index => $t):
        $type = Dev::terminalType($t);
        $uid = trim((string) ($t['terminal_uid'] ?? ''));
        $callsign = trim((string) ($t['operator_callsign'] ?? $t['callsign'] ?? $gamePhone['callsign'] ?? ''));
        $isPhone = $type === 'phone' && $gamePhone !== [] && $index === 0;
        $number = $isPhone ? (trim((string) ($gamePhone['live_number'] ?? '')) ?: trim((string) ($gamePhone['phone_number'] ?? ''))) : '';
        $title = trim((string) ($t['terminal_label'] ?? ''));
        if ($title === '' || preg_match('/^(Terminal|\d{2}-\d{4}-\d+)/', $title) === 1) {
            $model = $isPhone ? trim((string) ($gamePhone['device_model'] ?? '')) : '';
            $title = $type === 'phone' ? ($model !== '' ? $model : Dev::DEFAULT_PHONE_MODEL) : Dev::typeLabel($type);
        }
        $identity = AtakRealismRepository::liaisonIdentity($t);
        $cert = Dev::certificateOfTerminal($t);
        $life = Dev::certificateLifetime($cert);
        $link = Dev::linkState(($t['last_seen_at'] ?? null) !== null ? (string) $t['last_seen_at'] : null);
        $checks = Dev::liaisonChecks($t);
        [$verdictLabel, $verdictTone] = $verdicts[Dev::liaisonVerdict($checks)];
        $authority = trim((string) ($cert['authority_label'] ?? '')) ?: 'Autorité ATAK locale';
        $domain = trim((string) ($t['crypto_domain_label'] ?? '')) ?: 'Réseau par défaut';
        $host = $identity['host'] !== '' ? $identity['host'] : 'Athena';
        $linkTone = ['online' => 'ok', 'idle' => 'warn'][$link['key']] ?? 'bad';
        $events = $linkEvents[$uid] ?? [];
        ?>
        <article class="bo-member-situation__card atk-dev__card">
            <header class="atk-dev__head">
                <div>
                    <p class="bo-member-situation__kicker">Liaison · <?= $h(Dev::typeLabel($type)) ?></p>
                    <h2><?= $h($title) ?></h2>
                </div>
                <div class="atk-dev__chips">
                    <span class="atk-chip atk-chip--<?= $h($verdictTone) ?>"><?= $h($verdictLabel) ?></span>
                    <span class="atk-chip atk-chip--<?= $h($link['key']) ?>"><i></i><?= $h($link['label']) ?><?= $link['ago'] !== '' ? ' · ' . $h($link['ago']) : '' ?></span>
                </div>
            </header>

            <ol class="atk-path" aria-label="Chemin de liaison">
                <li class="atk-path__node">
                    <span><?= $h(Dev::typeLabel($type)) ?></span>
                    <strong><?= $h($callsign !== '' ? $callsign : $title) ?></strong>
                    <small><?= $h($number !== '' ? $number : $identity['ip']) ?></small>
                </li>
                <li class="atk-path__hop atk-path__hop--<?= $h($life['tone']) ?>" aria-label="Certificat client : <?= $h($life['label']) ?>">
                    <i></i><b>Certificat client</b><em><?= $h($life['label']) ?></em>
                </li>
                <li class="atk-path__node">
                    <span>Autorité de confiance</span>
                    <strong><?= $h($authority) ?></strong>
                    <small><?= $h($domain) ?></small>
                </li>
                <li class="atk-path__hop atk-path__hop--<?= $h($linkTone) ?>" aria-label="Signe de vie : <?= $h($link['label']) ?>">
                    <i></i><b>Signe de vie</b><em><?= $h($link['ago'] !== '' ? $link['ago'] : 'Jamais') ?></em>
                </li>
                <li class="atk-path__node">
                    <span>Serveur</span>
                    <strong><?= $h($host) ?></strong>
                    <small><?= $h($identity['signature'] !== '—' ? 'Signature ' . $identity['signature'] : 'Signature non présentée') ?></small>
                </li>
            </ol>

            <div class="atk-link__grid">
                <section class="atk-sheet">
                    <h3>Contrôles de liaison</h3>
                    <ul class="atk-checks">
                        <?php foreach ($checks as $check): ?>
                            <li class="atk-check atk-check--<?= $h($check['state']) ?>">
                                <i aria-hidden="true"><?= $checkIcons[$check['state']] ?? '?' ?></i>
                                <div><strong><?= $h($check['label']) ?></strong><small><?= $h($check['detail']) ?></small></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="atk-sheet">
                    <h3>Session</h3>
                    <dl>
                        <div><dt>Identifiant terminal</dt><dd class="is-mono"><?= $h($uid !== '' ? $uid : '—') ?></dd></div>
                        <div><dt>Indicatif</dt><dd><?= $h($callsign !== '' ? $callsign : '—') ?><?php if (trim((string) ($t['operator_military_id'] ?? '')) !== ''): ?> · <span class="is-mono"><?= $h($t['operator_military_id']) ?></span><?php endif; ?></dd></div>
                        <div><dt>Dernière adresse IP</dt><dd class="is-mono"><?= $h($identity['ip']) ?></dd></div>
                        <div><dt>Dernière liaison</dt><dd><?= $h($fmtDate($t['last_seen_at'] ?? '', 'd/m/Y à H:i:s')) ?></dd></div>
                        <div><dt>Première connexion</dt><dd><?= $h($fmtDate($t['first_seen_at'] ?? '')) ?></dd></div>
                        <div><dt>Associé au compte</dt><dd><?= $h($fmtDate($t['linked_at'] ?? '')) ?></dd></div>
                        <div><dt>Logiciel</dt><dd><?= $h($identity['versions']) ?></dd></div>
                        <div><dt>Plateforme</dt><dd><?= $h((string) ($t['platform_label'] ?? '') ?: '—') ?></dd></div>
                        <div class="is-wide"><dt>Certificat</dt><dd class="is-mono is-wrap"><?= $h($life['state'] === 'none' ? 'Aucun certificat émis' : Dev::subjectDn($cert)) ?></dd></div>
                    </dl>
                    <?php if ($life['from'] !== null || $life['to'] !== null): ?>
                        <div class="atk-validity atk-validity--<?= $h($life['tone']) ?>">
                            <div class="atk-validity__bar"><i style="width: <?= (int) ($life['elapsed_pct'] ?? 0) ?>%"></i></div>
                            <div class="atk-validity__dates">
                                <span>Valide du <?= $h($life['from'] !== null ? date('d/m/Y', $life['from']) : '—') ?></span>
                                <span>au <?= $h($life['to'] !== null ? date('d/m/Y', $life['to']) : '—') ?><?php if ($life['days_left'] !== null && $life['days_left'] >= 0 && $life['state'] !== 'revoked'): ?> · <?= (int) $life['days_left'] ?> j restants<?php endif; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="atk-diag" aria-label="Historique de liaison">
                <h3 class="atk-link__title">Historique de liaison</h3>
                <?php if ($events === []): ?>
                    <p class="atk-sheet__note">Aucun événement de liaison dans le journal de ce terminal sur les 14 derniers jours.</p>
                <?php else: ?>
                    <div class="atk-table-wrap">
                        <table class="atk-table">
                            <thead><tr><th>Quand</th><th>Niveau</th><th>Module</th><th>Événement</th></tr></thead>
                            <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td style="white-space:nowrap"><?= $h($ev['at'] !== null ? date('d/m H:i:s', $ev['at']) : '—') ?></td>
                                    <td><span class="atk-chip atk-chip--<?= $ev['level'] === 'error' ? 'bad' : ($ev['level'] === 'warn' ? 'warn' : 'muted') ?>"><?= $h(\App\Support\AtakDeviceLog::levelLabel($ev['level'])) ?></span></td>
                                    <td><?= $h($ev['module']) ?></td>
                                    <td><?= $h($ev['message']) ?><?php if ($ev['detail'] !== ''): ?><small class="is-mono"><?= $h(mb_strimwidth($ev['detail'], 0, 180, '…')) ?></small><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </article>
    <?php endforeach; ?>

    <?php if ($retired !== []): ?>
        <section class="bo-member-situation__card">
            <h2>Terminaux retirés</h2>
            <div class="atk-table-wrap">
                <table class="atk-table">
                    <thead><tr><th>Appareil</th><th>Identifiant</th><th>Dernière liaison</th><th>Certificat</th></tr></thead>
                    <tbody>
                    <?php foreach ($retired as $t): $rl = Dev::certificateLifetime(Dev::certificateOfTerminal($t)); ?>
                        <tr>
                            <td><strong><?= $h(trim((string) ($t['terminal_label'] ?? '')) ?: Dev::typeLabel(Dev::terminalType($t))) ?></strong></td>
                            <td class="is-mono"><?= $h((string) ($t['terminal_uid'] ?? '') ?: '—') ?></td>
                            <td><?= $h($fmtDate($t['last_seen_at'] ?? '')) ?></td>
                            <td><span class="atk-chip atk-chip--<?= $h($rl['tone']) ?>"><?= $h($rl['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
