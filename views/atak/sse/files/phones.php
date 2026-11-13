<?php
declare(strict_types=1);
/**
 * Téléphones du jeu : extractions reçues (toutes, ou les miennes), mes téléphones synchronisés avec l'accord donné ou
 * retiré, et la marche à suivre en jeu. Données du personnage seulement.
 *
 * @var array{rows: list<array<string,mixed>>, total: int} $list
 * @var list<array<string,mixed>> $devices
 * @var bool $mine
 * @var string $q
 * @var bool $enabled
 */
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_nav.php';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken) . '">';
?>
<div class="fx">
    <div class="fx-th">
        <div><h1>Téléphones du jeu</h1><p>Les téléphones ATAK synchronisés depuis le jeu arrivent ici comme un rapport d’exploitation : contacts, messages, notes, photos, positions et lieux. Seules les données du personnage en jeu sont copiées.</p></div>
    </div>
    <?php if (!$enabled): ?><div class="fx-card" style="margin-bottom:16px"><p class="fx-err" style="margin:16px">La synchronisation des téléphones est coupée pour la communauté (réglage du back-office).</p></div><?php endif; ?>
    <div class="fx-wk">
        <div class="fx-col">
            <section class="fx-card">
                <div class="fx-ch"><h2>Synchroniser un téléphone</h2></div>
                <div class="fx-fs">
                    <ol class="fx-steps">
                        <li>Reliez votre compte Athena en jeu (page <b>Lier</b> du site), comme pour l’ATAK.</li>
                        <li>Dans les réglages du téléphone, cochez <b>Autoriser la synchronisation du téléphone avec Athena</b> (désactivé par défaut).</li>
                        <li>Menu ACE › Téléphone › <b>Synchroniser avec le web</b>, ou <b>Synchroniser le téléphone saisi</b> pour celui d’une IA.</li>
                    </ol>
                    <small class="fx-help">Le MJ peut interdire la synchronisation pendant une mission. Une synchronisation par minute au plus.</small>
                </div>
            </section>
            <section class="fx-card">
                <div class="fx-ch"><h2>Mes téléphones</h2><small><?= count($devices) ?></small></div>
                <?php if ($devices === []): ?><p class="fx-empty">Aucun téléphone synchronisé avec votre compte.</p><?php endif; ?>
                <?php foreach ($devices as $d): ?>
                    <div class="fx-li">
                        <span style="min-width:0"><b class="mono" translate="no"><?= $h($d['device_label'] ?? $d['device_uid']) ?></b><br><small><?= $d['revoked_at'] !== null ? 'accord retiré' : 'dernière synchro ' . $h(\App\Services\Sse\SseFileRecordService::ago((string) ($d['last_sync_at'] ?? $d['created_at']), gmdate('Y-m-d H:i:s'))) ?></small></span>
                        <form method="post" action="<?= $h(url('atak/sse/fichiers/appareils/' . (int) $d['id'] . '/accord')) ?>"><?= $csrf ?><input type="hidden" name="allowed" value="<?= $d['revoked_at'] !== null ? '1' : '0' ?>"><button class="btn sm<?= $d['revoked_at'] !== null ? ' pri' : '' ?>" type="submit"><?= $d['revoked_at'] !== null ? 'Autoriser de nouveau' : 'Retirer mon accord' ?></button></form>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>
        <section class="fx-card fx-out">
            <form class="fx-rh" method="get" action="<?= $h(url('atak/sse/fichiers/telephones')) ?>" data-fx-instant role="search">
                <span class="fx-tag">QD</span><b><?= (int) $list['total'] ?> extraction<?= $list['total'] > 1 ? 's' : '' ?></b><span class="sp"></span>
                <label class="sr-only" for="fx-pq">Chercher une extraction</label>
                <input class="fx-in" id="fx-pq" name="q" value="<?= $h($q) ?>" placeholder="EXT-…, appareil, propriétaire" style="max-width:240px;height:34px" data-fx-focus>
                <input type="hidden" name="miens" value=""><label class="fx-sw"><input type="checkbox" name="miens" value="1"<?= $mine ? ' checked' : '' ?>><i></i>Les miennes</label>
            </form>
            <?php if ($list['rows'] === []): ?>
                <div class="fx-nr"><b>AUCUNE EXTRACTION</b><p>Aucun téléphone n’a encore été synchronisé<?= $q !== '' ? ' pour cette recherche' : '' ?>.</p></div>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $e): ?><?php require __DIR__ . '/_extraction_row.php'; ?><?php endforeach; ?>
        </section>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
