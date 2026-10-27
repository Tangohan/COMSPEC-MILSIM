<?php
/**
 * Compte simple : profil, ATAK et données de jeu de la personne, sur son tableau de bord.
 *
 * @var array<string, mixed>|null $solo_overview {@see \App\Services\Account\SoloAccountOverviewService::forUser}
 */
$soloOverview = is_array($solo_overview ?? null) ? $solo_overview : null;
if ($soloOverview === null) {
    return;
}
$so = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$soN = static fn (string $fr): string => htmlspecialchars(function_exists('i18n_phrase') ? i18n_phrase('nav', $fr) : $fr, ENT_QUOTES, 'UTF-8');
$soDate = static function (?string $v): string {
    $ts = $v !== null && $v !== '' ? strtotime($v) : false;

    return $ts ? date('d/m/Y H:i', $ts) : '—';
};
$soProfile = $soloOverview['profile'];
$soAtak = $soloOverview['atak'];
$soHistory = $soloOverview['history'];
$soState = [
    'ok' => ['dash-solo__pill--ok', 'Liaison OK'],
    'idle' => ['dash-solo__pill--idle', 'Pas de session ouverte'],
    'bad' => ['dash-solo__pill--bad', 'À réparer'],
][$soAtak['state']] ?? ['dash-solo__pill--idle', 'Pas de session ouverte'];
?>
<style>
.dash-solo-grid { display: grid; gap: 1rem; }
@media (min-width: 1024px) { .dash-solo-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.25rem; } }
.dash-solo__dl { margin: 0; display: grid; gap: 0.55rem; font-size: 0.86rem; }
.dash-solo__dl div { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #ececec; padding-bottom: 0.45rem; }
.dash-solo__dl dt { color: #666666; }
.dash-solo__dl dd { margin: 0; font-weight: 700; color: #161616; text-align: right; overflow-wrap: anywhere; }
.dash-solo__pill { display: inline-flex; padding: 0.2rem 0.55rem; font-size: 10px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; border: 1px solid currentColor; }
.dash-solo__pill--ok { color: #047857; }
.dash-solo__pill--idle { color: #666666; }
.dash-solo__pill--bad { color: #b91c1c; }
.dash-solo__problems { margin: 0.75rem 0 0; padding-left: 1.1rem; font-size: 0.82rem; line-height: 1.5; color: #b91c1c; }
.dash-solo__table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.dash-solo__table th, .dash-solo__table td { padding: 0.45rem 0; border-bottom: 1px solid #ececec; text-align: left; vertical-align: top; }
.dash-solo__table th { font-size: 10px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: #666666; }
.dash-solo__table td.num { text-align: right; font-weight: 800; color: #161616; white-space: nowrap; padding-left: 0.75rem; }
.dash-solo__muted { margin: 0; font-size: 0.86rem; line-height: 1.55; color: #4a4a4a; }
.dash-solo .dash-start-card__actions { margin-top: auto; padding-top: 1.25rem; }
</style>
<section class="dash-zone dash-guest-zone dash-start dash-solo dash-reveal" id="dash-solo" data-dash-reveal aria-labelledby="dash-solo-title">
    <div class="dash-guest-zone__inner">
        <header class="dash-zone__head">
            <p class="dash-zone__kicker"><?= $soN('Mon compte') ?></p>
            <h3 id="dash-solo-title" class="dash-zone__title"><?= $soN('Mon profil, mon ATAK et mes données') ?></h3>
            <p class="dash-zone__lead"><?= $soN('Sans organisation, tout ce qui concerne votre compte est réuni ici.') ?></p>
        </header>
        <div class="dash-solo-grid">
            <article class="dash-start-card">
                <p class="dash-start-card__kicker"><?= $soN('Profil') ?></p>
                <h4 class="dash-start-card__title"><?= $so($soProfile['display_name'] !== '' ? $soProfile['display_name'] : $soProfile['email']) ?></h4>
                <dl class="dash-solo__dl">
                    <div><dt><?= $soN('Indicatif') ?></dt><dd><?= $so($soProfile['callsign'] !== '' ? $soProfile['callsign'] : '—') ?></dd></div>
                    <div><dt><?= $soN('Adresse') ?></dt><dd><?= $so($soProfile['email']) ?></dd></div>
                    <div><dt><?= $soN('Steam') ?></dt><dd><?= $soProfile['steam_linked'] ? $soN('Relié') . ' ' . $so($soProfile['steam_hint']) : $soN('Non relié') ?></dd></div>
                    <div><dt><?= $soN('Compte créé le') ?></dt><dd><?= $so($soDate($soProfile['created_at'])) ?></dd></div>
                    <div><dt><?= $soN('Dernière connexion') ?></dt><dd><?= $so($soDate($soProfile['last_login_at'])) ?></dd></div>
                </dl>
                <div class="dash-start-card__actions">
                    <a href="<?= $so(url('personnel/me')) ?>" class="dash-start-btn dash-start-btn--dark"><?= $soN('Mon dossier') ?></a>
                    <a href="<?= $so(url('account')) ?>" class="dash-start-btn dash-start-btn--outline"><?= $soN('Mon compte') ?></a>
                </div>
            </article>

            <article class="dash-start-card dash-start-card--create">
                <p class="dash-start-card__kicker"><?= $soN('ATAK') ?></p>
                <h4 class="dash-start-card__title"><?= $soN('Mon ATAK') ?> <span class="dash-solo__pill <?= $so($soState[0]) ?>"><?= $soN($soState[1]) ?></span></h4>
                <?php if ($soAtak['terminals'] === []): ?>
                    <p class="dash-solo__muted"><?= $soN('Aucun ATAK relié pour l’instant. Liez votre jeu, puis ouvrez l’ATAK en jeu : il apparaîtra ici.') ?></p>
                <?php else: ?>
                    <table class="dash-solo__table">
                        <thead><tr><th><?= $soN('Terminal') ?></th><th><?= $soN('Liaison') ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($soAtak['terminals'] as $term): ?>
                            <tr><td><?= $so($term['label']) ?></td><td><?= $so($term['link'] !== '' ? $term['link'] : $soDate($term['last_seen_at'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <dl class="dash-solo__dl" style="margin-top:.85rem">
                    <div><dt><?= $soN('Sessions de jeu ouvertes') ?></dt><dd><?= (int) $soAtak['sessions_usable'] ?></dd></div>
                    <div><dt><?= $soN('Dernière activité en jeu') ?></dt><dd><?= $so($soDate($soAtak['last_seen_at'])) ?></dd></div>
                </dl>
                <?php if ($soAtak['problems'] !== []): ?>
                    <ul class="dash-solo__problems">
                        <?php foreach (array_slice($soAtak['problems'], 0, 4) as $problem): ?>
                            <li><?= $so($problem) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="dash-start-card__actions">
                    <a href="<?= $so(url('lier')) ?>" class="dash-start-btn dash-start-btn--primary"><?= $soN('Lier mon jeu') ?></a>
                </div>
            </article>

            <article class="dash-start-card">
                <p class="dash-start-card__kicker"><?= $soN('Historique') ?></p>
                <h4 class="dash-start-card__title"><?= $soN('Mes données de jeu') ?></h4>
                <?php if ($soHistory === []): ?>
                    <p class="dash-solo__muted"><?= $soN('Aucune donnée de jeu enregistrée sur votre compte.') ?></p>
                <?php else: ?>
                    <table class="dash-solo__table">
                        <thead><tr><th><?= $soN('Donnée') ?></th><th><?= $soN('Dernière') ?></th><th style="text-align:right"><?= $soN('Lignes') ?></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($soHistory, 0, 10) as $row): ?>
                            <tr>
                                <td><?= $soN($row['label']) ?></td>
                                <td><?= $so($soDate($row['last'])) ?></td>
                                <td class="num"><?= number_format((int) $row['count'], 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div class="dash-start-card__actions">
                    <a href="<?= $so(url('account/donnees')) ?>" class="dash-start-btn dash-start-btn--outline"><?= $soN('Exporter ou supprimer') ?></a>
                </div>
            </article>
        </div>
    </div>
</section>