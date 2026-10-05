<?php
declare(strict_types=1);

use App\Support\AtakDevicePresenter as Dev;

$terminals = is_array($atakRealismTerminals ?? null) ? $atakRealismTerminals : [];
$certificates = is_array($atakRealismCertificates ?? null) ? $atakRealismCertificates : [];
$domains = is_array($atakCryptoDomains ?? null) ? $atakCryptoDomains : [];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$certificateTypeFr = static function (?string $type): string {
    return match ((string) $type) {
        'server' => 'Serveur',
        'client' => 'Client',
        'device' => 'Appareil',
        'operator' => 'Opérateur',
        'gateway' => 'Passerelle',
        'test' => 'Essai',
        default => ((string) $type !== '' ? (string) $type : 'Appareil'),
    };
};

// Cycle de vie de chaque certificat, puis compteurs et autorités intermédiaires.
$rows = [];
$counts = ['valid' => 0, 'expiring' => 0, 'expired' => 0, 'revoked' => 0];
$authorities = [];
foreach ($certificates as $certificate) {
    if (!is_array($certificate)) {
        continue;
    }
    $life = Dev::certificateLifetime($certificate);
    if (isset($counts[$life['state']])) {
        $counts[$life['state']]++;
    } elseif ($life['state'] === 'pending') {
        $counts['valid']++;
    }
    $authority = trim((string) ($certificate['authority_label'] ?? '')) ?: 'Autorité ATAK locale';
    $authorities[$authority] = ($authorities[$authority] ?? 0) + ($life['state'] === 'revoked' || $life['state'] === 'expired' ? 0 : 1);
    $rows[] = ['cert' => $certificate, 'life' => $life];
}
$revoked = array_values(array_filter($rows, static fn (array $r): bool => $r['life']['state'] === 'revoked'));
$authorityNames = array_keys($authorities);
if ($authorityNames === []) {
    $authorityNames = ['Autorité ATAK locale'];
}
?>
<div class="atk-admin atk-dev">
    <section class="atk-kpis" aria-label="État de la PKI">
        <div class="atk-kpi atk-kpi--ok"><span>Valides</span><strong><?= (int) $counts['valid'] ?></strong><small>Appareils autorisés à se connecter</small></div>
        <div class="atk-kpi atk-kpi--warn"><span>À renouveler</span><strong><?= (int) $counts['expiring'] ?></strong><small>Expirent dans 30 jours ou moins</small></div>
        <div class="atk-kpi atk-kpi--bad"><span>Expirés</span><strong><?= (int) $counts['expired'] ?></strong><small>Refusés à la prochaine connexion</small></div>
        <div class="atk-kpi"><span>Révoqués</span><strong><?= (int) $counts['revoked'] ?></strong><small>Inscrits sur la liste de révocation</small></div>
    </section>

    <section class="atk-panel">
        <div class="atk-panel__head">
            <div>
                <h2>Chaîne de certification</h2>
                <p>Chaque terminal présente son certificat client ; le serveur remonte la chaîne jusqu’à la racine avant d’accepter la liaison.</p>
            </div>
            <a class="atk-btn" href="<?= $h(url('back-office/atak/realisme')) ?>">Parc de terminaux</a>
        </div>
        <div class="atk-panel__body">
            <div class="atk-chain">
                <div class="atk-chain__node">
                    <span>Racine</span>
                    <strong>COMSPEC Root CA</strong>
                    <small>RSA 4096 · SHA-256 · hors ligne</small>
                </div>
                <div class="atk-chain__arrow" aria-hidden="true">→</div>
                <div class="atk-chain__node">
                    <span>Intermédiaire<?= count($authorityNames) > 1 ? 's' : '' ?></span>
                    <?php foreach ($authorityNames as $name): ?>
                        <strong><?= $h($name) ?></strong>
                        <small><?= (int) ($authorities[$name] ?? 0) ?> certificat<?= ($authorities[$name] ?? 0) > 1 ? 's' : '' ?> en cours</small>
                    <?php endforeach; ?>
                </div>
                <div class="atk-chain__arrow" aria-hidden="true">→</div>
                <div class="atk-chain__node">
                    <span>Feuilles</span>
                    <strong><?= count($terminals) ?> <?= count($terminals) > 1 ? 'terminaux' : 'terminal' ?> terrain</strong>
                    <small>ECDSA P-256 · authentification client TLS</small>
                </div>
            </div>
        </div>
    </section>

    <section class="atk-panel">
        <div class="atk-panel__head">
            <div>
                <h2>Certificats émis</h2>
                <p><?= count($rows) ?> certificat<?= count($rows) > 1 ? 's' : '' ?>. Sujet, numéro de série et empreinte sont ceux que le terminal présente au serveur.</p>
            </div>
        </div>
        <div class="atk-table-wrap">
            <table class="atk-table">
                <thead>
                    <tr>
                        <th>Certificat</th>
                        <th>Titulaire</th>
                        <th>Série · empreinte</th>
                        <th>Validité</th>
                        <th>État</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" style="text-align:center;padding:2rem">Aucun certificat émis pour le moment. Ils sont délivrés à la validation de la liaison d’un terminal, ou ci-dessous.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row):
                    $c = $row['cert'];
                    $life = $row['life'];
                    $certId = (int) ($c['id'] ?? 0);
                    $ref = (string) ($c['certificate_ref'] ?? '');
                    $label = $ref !== '' ? $ref : ('#' . $certId);
                    $holder = trim((string) ($c['callsign'] ?? '')) ?: trim((string) ($c['display_name'] ?? ''));
                    $terminal = trim((string) ($c['terminal_label'] ?? '')) ?: trim((string) ($c['terminal_uid'] ?? ''));
                    $confirmRevoke = 'Révoquer le certificat « ' . $label . ' » ? Le terminal sera refusé à sa prochaine connexion tant qu’un nouveau certificat n’est pas émis.';
                    $confirmDelete = 'Supprimer définitivement le certificat « ' . $label . ' » ? Il disparaît aussi de la liste de révocation.';
                    ?>
                    <tr>
                        <td>
                            <strong class="is-mono"><?= $h($label) ?></strong>
                            <small class="is-mono"><?= $h(Dev::subjectDn($c)) ?></small>
                            <small><?= $h($certificateTypeFr($c['certificate_type'] ?? null)) ?> · émis par <?= $h(trim((string) ($c['authority_label'] ?? '')) ?: 'Autorité ATAK locale') ?><?php if (trim((string) ($c['crypto_domain_label'] ?? '')) !== ''): ?> · réseau <?= $h($c['crypto_domain_label']) ?><?php endif; ?></small>
                        </td>
                        <td>
                            <?= $h($holder !== '' ? $holder : '—') ?>
                            <small><?= $h($terminal !== '' ? $terminal : 'Aucun terminal rattaché') ?></small>
                        </td>
                        <td>
                            <span class="is-mono"><?= $h(Dev::colonHex((string) ($c['serial_number'] ?? ''), 8) ?: '—') ?></span>
                            <small class="is-mono" title="<?= $h(Dev::colonHex((string) ($c['fingerprint_sha256'] ?? ''))) ?>">SHA-256 <?= $h(Dev::shortFingerprint((string) ($c['fingerprint_sha256'] ?? '')) ?: '—') ?></small>
                        </td>
                        <td>
                            <div class="atk-validity atk-validity--<?= $h($life['tone']) ?>">
                                <div class="atk-validity__bar"><i style="width: <?= (int) ($life['elapsed_pct'] ?? 0) ?>%"></i></div>
                                <div class="atk-validity__dates">
                                    <span><?= $h($life['from'] !== null ? date('d/m/Y', $life['from']) : '—') ?></span>
                                    <span><?= $h($life['to'] !== null ? date('d/m/Y', $life['to']) : '—') ?></span>
                                </div>
                            </div>
                            <?php if ($life['days_left'] !== null && $life['state'] !== 'revoked'): ?>
                                <small><?= $life['days_left'] >= 0 ? (int) $life['days_left'] . ' j restants' : 'Expiré depuis ' . abs((int) $life['days_left']) . ' j' ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="atk-chip atk-chip--<?= $h($life['tone']) ?>"><?= $h($life['label']) ?></span>
                            <?php if ($life['state'] === 'revoked' && trim((string) ($c['revoked_reason'] ?? '')) !== ''): ?>
                                <small><?= $h(Dev::revocationReasonLabel((string) $c['revoked_reason'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($certId > 0): ?>
                                <div class="atk-actions">
                                    <?php if ($life['state'] !== 'revoked'): ?>
                                        <form method="post" action="<?= $h(url('back-office/atak/certificats/' . $certId . '/revoquer')) ?>" class="atk-revoke" onsubmit="return confirm(<?= $h(json_encode($confirmRevoke, JSON_UNESCAPED_UNICODE)) ?>);">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <select name="revoked_reason" aria-label="Motif de révocation">
                                                <?php foreach (Dev::REVOCATION_REASONS as $code => $reasonLabel): ?>
                                                    <option value="<?= $h($code) ?>"><?= $h($reasonLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="atk-btn atk-btn--warn">Révoquer</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= $h(url('back-office/atak/certificats/' . $certId . '/supprimer')) ?>" onsubmit="return confirm(<?= $h(json_encode($confirmDelete, JSON_UNESCAPED_UNICODE)) ?>);">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <button type="submit" class="atk-btn atk-btn--bad">Supprimer</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="atk-panel">
        <div class="atk-panel__head">
            <div>
                <h2>Liste de révocation (LCR)</h2>
                <p>Publiée aux serveurs de jeu à chaque synchronisation : un terminal inscrit ici est refusé, même avec un certificat encore dans sa période de validité.</p>
            </div>
        </div>
        <div class="atk-panel__body">
            <?php if ($revoked === []): ?>
                <p class="atk-sheet__note">Aucun certificat révoqué.</p>
            <?php else: ?>
                <div class="atk-table-wrap">
                    <table class="atk-table" style="min-width:40rem">
                        <thead><tr><th>Numéro de série</th><th>Certificat</th><th>Révoqué le</th><th>Motif</th></tr></thead>
                        <tbody>
                        <?php foreach ($revoked as $row): $c = $row['cert']; $at = Dev::utcTimestamp((string) ($c['revoked_at'] ?? '')); ?>
                            <tr>
                                <td class="is-mono"><?= $h(Dev::colonHex((string) ($c['serial_number'] ?? ''), 16) ?: '—') ?></td>
                                <td class="is-mono"><?= $h((string) ($c['certificate_ref'] ?? '—')) ?></td>
                                <td><?= $h($at !== null ? date('d/m/Y H:i', $at) : '—') ?></td>
                                <td><?= $h(Dev::revocationReasonLabel((string) ($c['revoked_reason'] ?? '')) ?: 'Non précisé') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="atk-kpis" style="grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));align-items:start">
        <form method="post" action="<?= $h(url('back-office/atak/certificats')) ?>" class="atk-panel">
            <div class="atk-panel__head"><div><h2>Émettre un certificat</h2><p>Numéro de série, empreinte et sujet sont générés si vous les laissez vides.</p></div></div>
            <div class="atk-panel__body atk-form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Terminal
                    <select name="terminal_id">
                        <option value="">Aucun (certificat d’opérateur ou de passerelle)</option>
                        <?php foreach ($terminals as $terminal): ?>
                            <?php $tLabel = trim((string) ($terminal['operator_callsign'] ?? '')) ?: (string) ($terminal['terminal_label'] ?? $terminal['terminal_uid'] ?? 'Terminal'); ?>
                            <option value="<?= (int) ($terminal['id'] ?? 0) ?>"><?= $h($tLabel . ' · ' . Dev::typeLabel(Dev::terminalType($terminal))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="atk-form__row">
                    <label>Usage
                        <select name="certificate_type">
                            <option value="device">Appareil (client TLS)</option>
                            <option value="operator">Opérateur</option>
                            <option value="gateway">Passerelle</option>
                            <option value="test">Essai</option>
                        </select>
                    </label>
                    <label>Durée de validité
                        <select name="duration_days">
                            <option value="30">30 jours</option>
                            <option value="90">90 jours</option>
                            <option value="180">6 mois</option>
                            <option value="365" selected>1 an</option>
                            <option value="730">2 ans</option>
                        </select>
                    </label>
                </div>
                <div class="atk-form__row">
                    <label>Autorité émettrice
                        <input name="authority_label" list="atk-authorities" placeholder="Autorité ATAK locale" autocomplete="off">
                    </label>
                    <label>Réseau de chiffrement
                        <select name="crypto_domain_id">
                            <option value="">Réseau par défaut</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?= (int) ($domain['id'] ?? 0) ?>"><?= $h($domain['label'] ?? 'Réseau') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <datalist id="atk-authorities">
                    <?php foreach ($authorityNames as $name): ?><option value="<?= $h($name) ?>"><?php endforeach; ?>
                </datalist>
                <div class="atk-form__row">
                    <label>Sujet (CN) <input name="common_name" placeholder="Indicatif du terminal" autocomplete="off"></label>
                    <label>Référence <input name="certificate_ref" placeholder="Générée automatiquement" autocomplete="off"></label>
                </div>
                <label>Compte membre à lier <input name="user_id" inputmode="numeric" placeholder="Facultatif" autocomplete="off"></label>
                <div><button class="atk-btn atk-btn--solid" type="submit">Émettre et signer</button></div>
            </div>
        </form>

        <form method="post" action="<?= $h(url('back-office/atak/reseaux-chiffrement')) ?>" class="atk-panel">
            <div class="atk-panel__head"><div><h2>Réseaux de chiffrement</h2><p>Les appareils d’un même réseau lisent le trafic ; hors réseau, les données arrivent illisibles.</p></div></div>
            <div class="atk-panel__body atk-form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <?php if ($domains !== []): ?>
                    <div class="atk-chain atk-chain--stack">
                        <?php foreach ($domains as $domain): $active = ($domain['status'] ?? '') === 'active'; ?>
                            <div class="atk-chain__node">
                                <span><?= $active ? 'Actif' : 'Inactif' ?></span>
                                <strong><?= $h($domain['label'] ?? 'Réseau') ?></strong>
                                <small><?= $h((string) ($domain['domain_ref'] ?? '')) ?> · AES-256-GCM</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <label>Nom du réseau <input name="label" required placeholder="ex. Réseau ami" autocomplete="off"></label>
                <label>Référence courte <input name="domain_ref" placeholder="Facultatif" autocomplete="off"></label>
                <div><button class="atk-btn atk-btn--solid" type="submit">Enregistrer le réseau</button></div>
                <p class="atk-form__hint"><a href="<?= $h(url('back-office/atak/roleplay#intel-scramble')) ?>">Données chiffrées en jeu</a></p>
            </div>
        </form>
    </div>
</div>
