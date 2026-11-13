<?php
declare(strict_types=1);
/**
 * Lore des sessions (MJ) : une session par mission (code d'opération), avec ses personnes, sa phase, son verrou et ses
 * dates de synchronisation avec le jeu ; création d'une session.
 *
 * @var list<array<string,mixed>> $sessions
 * @var list<string> $countries
 * @var string $defaultCountry
 */
use App\Support\SseRecordsCountryPacks as Packs;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
$at = static fn (?string $d): string => $d ? \App\Services\Sse\SseFileRecordService::ago($d, gmdate('Y-m-d H:i:s')) : 'jamais';
?>
<div class="fx">
    <div class="fx-th">
        <div><h1>Lore des sessions</h1><p>Préparez les personnes d’une mission et décidez, donnée par donnée, ce que les fichiers, le SEEK II, l’ATAK et les joueurs en sauront. Les IA se synchronisent depuis Eden ou Zeus ; ce que vous écrivez ici redescend en jeu à la récupération suivante.</p></div>
    </div>
    <div class="fx-wk">
        <form class="fx-card" method="post" action="<?= $h(url('atak/sse/fichiers/lore')) ?>"><?= $csrf ?>
            <div class="fx-ch"><h2>Nouvelle session</h2></div>
            <div class="fx-fs">
                <label class="fx-f"><span>Titre</span><input class="fx-in" name="title" maxlength="160" required placeholder="ex. Opération Silver Lake"></label>
                <label class="fx-f"><span>Code d’opération</span><input class="fx-in m" name="op_key" maxlength="64" required pattern="[A-Za-z0-9_.:\-]{2,64}" placeholder="ex. OP-2611-07"><small>Le même code que dans le module SSE de la mission : il relie le jeu à cette session. Lettres, chiffres, « - », « _ », « . ».</small></label>
                <label class="fx-f"><span>Pays par défaut des personnes</span><select class="fx-in" name="country"><?php foreach ($countries as $code): ?><option value="<?= $h($code) ?>"<?= $code === $defaultCountry ? ' selected' : '' ?>><?= $h(Packs::label($code)) ?></option><?php endforeach; ?></select><small>Formats des papiers, plaques, téléphones et adresses des personnes générées. Ex. Allemagne : dates 14.03.1991, plaques « B-AB 1234 ».</small></label>
                <label class="fx-f"><span>Résumé (MJ)</span><textarea class="fx-in" name="summary" maxlength="2000" placeholder="ex. Réseau logistique d’armes entre le port et la frontière nord."></textarea></label>
            </div>
            <div class="fx-ff"><button class="btn pri" type="submit">Créer la session</button></div>
        </form>
        <section class="fx-card fx-out">
            <div class="fx-ch"><h2>Sessions</h2><small><?= count($sessions) ?></small></div>
            <?php if ($sessions === []): ?>
                <div class="fx-idle"><div><h3>Aucune session</h3><p>Créez une session ici, ou lancez « Synchroniser les IA avec Athena » depuis le module SSE d’Eden ou de Zeus : la session se crée toute seule avec le code d’opération de la mission.</p></div></div>
            <?php endif; ?>
            <?php foreach ($sessions as $s): ?>
                <a class="fx-hit" href="<?= $h(url('atak/sse/fichiers/lore/' . (int) $s['id'])) ?>" style="grid-template-columns:minmax(0,1fr) auto">
                    <span style="min-width:0">
                        <span class="fx-hn"><b translate="no"><?= $h($s['title']) ?></b><span class="fx-flg"><?= $h($s['op_key']) ?></span><span class="fx-flg g">Phase <?= (int) $s['phase'] ?></span><?php if ($s['locked']): ?><span class="fx-flg a">Verrouillée</span><?php endif; ?></span>
                        <span class="fx-kv"><span><b><?= (int) $s['members_count'] ?></b> personne<?= $s['members_count'] > 1 ? 's' : '' ?></span><span>remontée du jeu <b><?= $h($at($s['last_push_at'])) ?></b></span><span>récupération par le jeu <b><?= $h($at($s['last_pull_at'])) ?></b></span></span>
                    </span>
                    <span class="fx-sc"><small><?= $h(($s['country'] ?? null) ? Packs::label((string) $s['country']) : '') ?></small></span>
                </a>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
