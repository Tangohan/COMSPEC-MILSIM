<?php
declare(strict_types=1);

use App\Services\Moderation\NameFilter;
use App\Services\Moderation\NameModerationService;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ready = !empty($nfReady);
$tab = ($nfTab ?? 'alertes') === 'mots' ? 'mots' : 'alertes';
$showAll = !empty($nfShowAll);
$words = is_array($nfWords ?? null) ? $nfWords : [];
$alerts = is_array($nfAlerts ?? null) ? $nfAlerts : [];
$openCount = (int) ($nfOpenCount ?? 0);
$indexUrl = url('admin/system/noms-inappropries');
$csrf = \App\Core\Csrf::token();
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$langLabels = ['fr' => 'Français', 'en' => 'Anglais', 'all' => 'Les deux'];
$modeLabels = [NameFilter::MODE_WORD => 'Mot entier', NameFilter::MODE_PARTIAL => 'Dans un mot'];
$statusLabels = ['open' => 'À traiter', 'handled' => 'Traitée', 'allowed' => 'Nom autorisé', 'renamed' => 'Nom changé'];
$wordsByLang = ['fr' => 0, 'en' => 0, 'all' => 0];
foreach ($words as $w) {
    $wordsByLang[$w['lang']] = ($wordsByLang[$w['lang']] ?? 0) + 1;
}
?>
<style>
.pa .nf-rename, .pa .nf-edit { display: inline-block; vertical-align: top; }
.pa .nf-rename > summary, .pa .nf-edit > summary { list-style: none; cursor: pointer; }
.pa .nf-rename > summary::-webkit-details-marker, .pa .nf-edit > summary::-webkit-details-marker { display: none; }
.pa .nf-rename[open], .pa .nf-edit[open] { display: block; margin: .5rem 0; }
.pa .nf-rename form { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-top: .5rem; }
.pa .nf-rename label { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; }
.pa .nf-edit form { margin-top: .5rem; }
</style>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Noms inappropriés
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Modération &amp; sécurité</p>
                    <h1 class="pa-hero__title">Noms inappropriés</h1>
                    <p class="pa-hero__lead">
                        Quand une communauté ou une unité prend un nom qui contient un mot de la liste, le mot est flouté
                        sur les pages publiques, la personne qui l’a saisi est avertie et une alerte arrive ici.
                    </p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn <?= $tab === 'alertes' ? 'pa-btn--solid' : 'pa-btn--ghost' ?>" href="<?= $h($indexUrl) ?>">Alertes<?= $openCount > 0 ? ' (' . $openCount . ')' : '' ?></a>
                        <a class="pa-btn <?= $tab === 'mots' ? 'pa-btn--solid' : 'pa-btn--ghost' ?>" href="<?= $h($indexUrl . '?vue=mots') ?>">Liste de mots</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Aperçu</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Alertes à traiter</dt>
                            <dd><?= $openCount ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Mots surveillés</dt>
                            <dd><?= count($words) ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Français · anglais · les deux</dt>
                            <dd><?= (int) $wordsByLang['fr'] ?> · <?= (int) $wordsByLang['en'] ?> · <?= (int) $wordsByLang['all'] ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if (is_string($flashOk) && trim($flashOk) !== ''): ?>
            <p class="pa-flash pa-flash--ok"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if (is_string($flashErr) && trim($flashErr) !== ''): ?>
            <p class="pa-flash pa-flash--err"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <?php if (!$ready): ?>
            <section class="pa-card">
                <h2 class="pa-card__title">Base de données indisponible</h2>
                <p>Les tables du filtre n’ont pas pu être créées. Vérifiez la connexion à la base, puis rechargez la page.</p>
            </section>
        <?php elseif ($tab === 'alertes'): ?>
            <section class="pa-card">
                <h2 class="pa-card__title"><?= $showAll ? 'Toutes les alertes' : 'Alertes à traiter' ?></h2>
                <p>
                    Une alerte se ferme d’elle-même quand le nom est changé. « Traitée » garde le floutage ;
                    « Autoriser ce nom » retire le floutage pour ce nom précis (faux positif).
                </p>
                <div class="pa-hero__actions">
                    <form method="post" action="<?= $h($indexUrl . '/analyser') ?>" style="display:inline">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                        <button type="submit" class="pa-btn pa-btn--solid">Analyser les noms existants</button>
                    </form>
                    <a class="pa-btn pa-btn--ghost" href="<?= $h($indexUrl . ($showAll ? '' : '?tout=1')) ?>"><?= $showAll ? 'Voir seulement les alertes à traiter' : 'Voir l’historique complet' ?></a>
                </div>
                <?php if ($alerts === []): ?>
                    <p><?= $showAll ? 'Aucune alerte enregistrée.' : 'Aucun nom à traiter.' ?></p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Communauté</th>
                                    <th>Mots trouvés</th>
                                    <th>Saisi par</th>
                                    <th>Date</th>
                                    <th>État</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($alerts as $row): ?>
                                <?php
                                $id = (int) ($row['id'] ?? 0);
                                $st = (string) ($row['status'] ?? 'open');
                                $isTenant = ($row['subject_type'] ?? '') === NameModerationService::SUBJECT_TENANT;
                                $tenantId = (int) ($row['tenant_id'] ?? 0);
                                $slug = trim((string) ($row['tenant_slug'] ?? ''));
                                $who = trim((string) ($row['user_display_name'] ?? ''));
                                $call = trim((string) ($row['user_callsign'] ?? ''));
                                $who = $who !== '' && $call !== '' ? $who . ' (' . $call . ')' : ($who !== '' ? $who : ($call !== '' ? $call : '—'));
                                $ts = strtotime((string) ($row['created_at'] ?? ''));
                                $authorEmail = trim((string) ($row['user_email'] ?? ''));
                                $canBan = $authorEmail !== '' && !str_ends_with(strtolower($authorEmail), '@deleted.invalid');
                                $currentName = $isTenant
                                    ? trim((string) ($row['tenant_name'] ?? ''))
                                    : trim((string) ($row['unit_current_name'] ?? ''));
                                if ($currentName === '') {
                                    $currentName = (string) ($row['name'] ?? '');
                                }
                                ?>
                                <tr>
                                    <td translate="no"><strong><?= $h((string) ($row['name'] ?? '')) ?></strong></td>
                                    <td><?= $isTenant ? 'Communauté' : 'Unité' ?></td>
                                    <td translate="no">
                                        <?php if ($tenantId > 0): ?>
                                            <a href="<?= $h(url('admin/tenants/' . $tenantId . '/edit')) ?>"><?= $h(trim((string) ($row['tenant_name'] ?? '')) ?: ('#' . $tenantId)) ?></a>
                                            <?php if ($slug !== ''): ?>
                                                · <a href="<?= $h(url('c/' . rawurlencode($slug))) ?>" target="_blank" rel="noopener">fiche publique</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td translate="no"><?= $h((string) ($row['matched_words'] ?? '')) ?></td>
                                    <td translate="no">
                                        <?= $h($who) ?>
                                        <?php if ($canBan): ?>
                                            <br><a href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode($authorEmail) . '#bannissement') ?>">Bannir l’auteur…</a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $ts ? $h(date('d/m/Y H:i', $ts)) : '—' ?></td>
                                    <td><?= $h($statusLabels[$st] ?? $st) ?></td>
                                    <td>
                                        <?php if ($st === 'open' && $id > 0): ?>
                                            <form method="post" action="<?= $h($indexUrl . '/alertes/' . $id . '/traiter') ?>" style="display:inline">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                <input type="hidden" name="decision" value="handled">
                                                <button type="submit" class="pa-btn pa-btn--solid">Traitée</button>
                                            </form>
                                            <form method="post" action="<?= $h($indexUrl . '/alertes/' . $id . '/traiter') ?>" style="display:inline">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                <input type="hidden" name="decision" value="allow">
                                                <button type="submit" class="pa-btn pa-btn--ghost" data-confirm="Retirer le floutage pour ce nom ?">Autoriser ce nom</button>
                                            </form>
                                            <details class="nf-rename">
                                                <summary class="pa-btn pa-btn--ghost">Renommer…</summary>
                                                <form method="post" action="<?= $h($indexUrl . '/alertes/' . $id . '/renommer') ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                    <label>Nouveau nom de <?= $isTenant ? 'la communauté' : 'l’unité' ?>
                                                        <input type="text" name="name" required minlength="2" maxlength="120" value="<?= $h($currentName) ?>" autocomplete="off">
                                                    </label>
                                                    <button type="submit" class="pa-btn pa-btn--solid">Enregistrer le nom</button>
                                                </form>
                                            </details>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p>« Renommer… » change le nom à la place de l’auteur et ferme l’alerte si le nouveau nom est propre.
                        « Bannir l’auteur… » ouvre son dossier à la rubrique Bannissement (motif, Steam ID et adresses IP au choix).</p>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="pa-card">
                <h2 class="pa-card__title">Ajouter un mot</h2>
                <p>
                    Les accents, majuscules et chiffres de remplacement (p3n1s, f.u.c.k) sont pris en compte.
                    « Mot entier » ne touche que le mot seul ou au pluriel ; « Dans un mot » le trouve aussi à l’intérieur d’un autre mot.
                </p>
                <form class="pa-filters" method="post" action="<?= $h($indexUrl . '/mots') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <label>Mot ou expression
                        <input type="text" name="word" required minlength="2" maxlength="120" autocomplete="off">
                    </label>
                    <label>Langue
                        <select name="lang">
                            <option value="fr">Français</option>
                            <option value="en">Anglais</option>
                            <option value="all" selected>Les deux</option>
                        </select>
                    </label>
                    <label>Correspondance
                        <select name="mode">
                            <option value="<?= $h(NameFilter::MODE_WORD) ?>" selected>Mot entier</option>
                            <option value="<?= $h(NameFilter::MODE_PARTIAL) ?>">Dans un mot</option>
                        </select>
                    </label>
                    <button type="submit" class="pa-btn pa-btn--solid">Ajouter</button>
                </form>
            </section>

            <section class="pa-card">
                <h2 class="pa-card__title">Liste de mots (<?= count($words) ?>)</h2>
                <form method="post" action="<?= $h($indexUrl . '/mots/defaut') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <button type="submit" class="pa-btn pa-btn--ghost">Remettre les mots d’origine</button>
                </form>
                <?php if ($words === []): ?>
                    <p>La liste est vide : aucun nom n’est flouté.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>Mot</th>
                                    <th>Langue</th>
                                    <th>Correspondance</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($words as $w): ?>
                                <tr>
                                    <td translate="no"><?= $h($w['word']) ?></td>
                                    <td><?= $h($langLabels[$w['lang']] ?? $w['lang']) ?></td>
                                    <td><?= $h($modeLabels[$w['mode']] ?? $w['mode']) ?></td>
                                    <td>
                                        <details class="nf-edit">
                                            <summary class="pa-btn pa-btn--ghost">Modifier</summary>
                                            <form class="pa-filters" method="post" action="<?= $h($indexUrl . '/mots/' . (int) $w['id'] . '/modifier') ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                                <label>Mot ou expression
                                                    <input type="text" name="word" required minlength="2" maxlength="120" value="<?= $h($w['word']) ?>" autocomplete="off">
                                                </label>
                                                <label>Langue
                                                    <select name="lang">
                                                        <?php foreach ($langLabels as $lv => $ll): ?>
                                                            <option value="<?= $h($lv) ?>"<?= $w['lang'] === $lv ? ' selected' : '' ?>><?= $h($ll) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label>Correspondance
                                                    <select name="mode">
                                                        <?php foreach ($modeLabels as $mv => $ml): ?>
                                                            <option value="<?= $h($mv) ?>"<?= $w['mode'] === $mv ? ' selected' : '' ?>><?= $h($ml) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <button type="submit" class="pa-btn pa-btn--solid">Enregistrer</button>
                                            </form>
                                        </details>
                                        <form method="post" action="<?= $h($indexUrl . '/mots/' . (int) $w['id'] . '/supprimer') ?>" style="display:inline">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                                            <button type="submit" class="pa-btn pa-btn--ghost" data-confirm="Retirer ce mot de la liste ?">Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<script>
document.querySelectorAll('.pa button[data-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!window.confirm(btn.getAttribute('data-confirm') || '')) {
            e.preventDefault();
        }
    });
});
</script>
