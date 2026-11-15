<?php
declare(strict_types=1);
use App\Controllers\Web\SseFileController;
use App\Support\SseFileCatalog as F;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $records */
/** @var array<string,string> $filters */
/** @var array<string,list<array<string,mixed>>> $photos clichés du visage par fiche (REC) */
$photos = is_array($photos ?? null) ? $photos : [];
$filesTab = 'seek';
require __DIR__ . '/_nav.php';
$mods = static function (array $m): string {
    $out = [];
    foreach (['fingers' => 'Doigts', 'iris' => 'Iris', 'face' => 'Visage'] as $k => $label) {
        if (!empty($m[$k])) {
            $out[] = $label;
        }
    }

    return $out !== [] ? implode(', ', $out) : '—';
};
$tone = ['concordant' => 'g', 'discordant' => 'r', 'signale' => 'a', 'inconnu' => ''];
?>
<div class="fx">
    <div class="fx-th">
        <div>
            <h1>Fiches SEEK II</h1>
            <p>Enregistrements biométriques (doigts, iris, visage) faits en jeu avec le SEEK II, rattachés au registre quand la clé biométrique ou le papier présenté correspond. Chaque fiche ajoute un contrôle N4 aux contrôles subis de la personne.</p>
        </div>
    </div>
    <section class="fx-card">
        <form class="fx-fs" method="get" action="<?= $h(url('atak/sse/fichiers/seek')) ?>" data-fx-instant role="search" aria-label="Filtres des fiches SEEK II">
            <div class="fx-g2">
                <label class="fx-f"><span>Verdict</span><select class="fx-in" name="verdict"><option value="">Tous les verdicts</option><?php foreach (F::SEEK_VERDICTS as $k => $label): ?><option value="<?= $h($k) ?>"<?= ($filters['verdict'] ?? '') === $k ? ' selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select></label>
                <label class="fx-f"><span>Recherche <kbd>/</kbd></span><input class="fx-in" name="q" type="search" value="<?= $h($filters['q'] ?? '') ?>" placeholder="REC, clé, nom, numéro, contrôleur…" data-fx-focus></label>
            </div>
            <div class="fx-row"><small class="fx-help">Ex. « Discordant » pour retrouver les personnes dont la biométrie ne colle pas au papier présenté.</small><a href="<?= $h(url('atak/sse/fichiers/seek')) ?>">Réinitialiser les filtres</a></div>
        </form>
        <div class="fx-rh"><b><?= count($records) ?> fiche<?= count($records) > 1 ? 's' : '' ?></b></div>
        <?php if ($records === []): ?>
            <div class="fx-nr"><b>AUCUNE FICHE SEEK II</b><p>Les fiches apparaissent ici quand un joueur les transmet depuis le SEEK II en jeu.</p></div>
        <?php else: ?>
            <div class="fx-tbw">
                <table class="fx-tb resp">
                    <thead><tr><th>Enregistrement</th><th>Reçu le</th><th>Nom déclaré</th><th>Papier présenté</th><th>Verdict</th><th>Relevés</th><th>Image</th><th>Lieu, contrôleur</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $s): ?>
                        <tr class="<?= $s['verdict'] === 'discordant' ? 'fl' : '' ?>">
                            <td class="m" translate="no"><?= $h($s['rec']) ?><?php if ($s['enrolled']): ?> <span class="fx-flg g">Enrôlé</span><?php endif; ?></td>
                            <td class="m"><?= $h(substr((string) $s['created_at'], 0, 16)) ?><?php if (($s['game_time'] ?? '') !== ''): ?><br><span class="fx-help">jeu <?= $h($s['game_time']) ?></span><?php endif; ?></td>
                            <td translate="no"><?= $s['subject_unknown'] ? 'Inconnu' : $h($s['display_name'] !== '' ? $s['display_name'] : '—') ?></td>
                            <td class="m" translate="no"><?= $h(trim(($s['doc_country'] ?? '') . ' ' . ($s['doc_number'] ?? '')) ?: '—') ?></td>
                            <td><span class="fx-flg <?= $tone[$s['verdict']] ?? '' ?>"><?= $h($s['verdict_label']) ?></span></td>
                            <td><?= $h($mods($s['modalities'])) ?>
                                <?php foreach ((is_array($s['capture'] ?? null) ? $s['capture'] : []) as $capMod => $cap): ?>
                                    <?php foreach (($cap['notes'] ?? []) as $capNote): ?><br><span class="fx-help" translate="no"><?= $h(\App\Support\SseCaptureNotes::label((string) $capMod) . ' : ' . $capNote) ?></span><?php endforeach; ?>
                                <?php endforeach; ?></td>
                            <?php $imgs = $photos[(string) $s['rec']] ?? []; ?>
                            <td class="m">
                                <?php if ($imgs !== [] && (string) ($imgs[0]['url'] ?? '') !== ''): ?>
                                    <a href="<?= $h((string) $imgs[0]['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h((string) $imgs[0]['url']) ?>" alt="Photo du visage, fiche <?= $h($s['rec']) ?>" loading="lazy" style="width:48px;height:64px;object-fit:cover;border-radius:4px;display:block"></a>
                                    <?php if (count($imgs) > 1): ?><span class="fx-help">+<?= count($imgs) - 1 ?></span><?php endif; ?>
                                <?php elseif (!empty($s['modalities']['face'])): ?><span class="fx-help">Visage relevé, image non reçue</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td translate="no"><?= $h($s['grid'] ?? '—') ?><?= ($s['controller_callsign'] ?? '') !== '' ? ' · ' . $h($s['controller_callsign']) : '' ?>
                                <?php if (!empty($s['signature_image'])): ?><br><img src="<?= $h(url((string) $s['signature_image'])) ?>" alt="Signature tracée au SEEK II, fiche <?= $h($s['rec']) ?>" loading="lazy" style="height:28px;background:#fff;border-radius:3px"><?php endif; ?></td>
                            <td>
                                <?php if ($s['identity_id'] !== null): ?><a href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $s['identity_id'])) ?>">Fiche</a> · <?php endif; ?>
                                <?php if ($s['check_id'] !== null): ?><a href="<?= $h(url('atak/sse/papiers/controles/' . (int) $s['check_id'])) ?>">Contrôle N4</a> · <?php endif; ?>
                                <a href="<?= $h(SseFileController::replayUrl('QB', ['number' => $s['rec']])) ?>">Passer aux fichiers</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
