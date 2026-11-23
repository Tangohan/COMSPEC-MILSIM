<?php
declare(strict_types=1);
use App\Core\Csrf;
use App\Support\SseWantedCatalog as W;
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$value = static function (string $key) use ($old, $notice, $timezoneName): string {
    if (array_key_exists($key, $old)) { return (string) $old[$key]; }
    $v = (string) ($notice[$key] ?? '');
    if ($v !== '' && in_array($key, ['expires_at', 'last_seen_at'], true)) {
        return (new DateTimeImmutable($v, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezoneName))->format('Y-m-d\TH:i');
    }
    return $v;
};
$fields = ['reason' => 'Motifs de recherche', 'instructions' => 'Consignes', 'zone' => 'Zone', 'last_seen_place' => 'Dernier lieu connu', 'contact_label' => 'Service / contact', 'contact_detail' => 'Coordonnées du contact', 'expires_at' => 'Date de fin', 'last_seen_at' => 'Dernière observation'];
?>
<div class="page-heading"><h1>Modifier l’avis <?= $h($notice['ref']) ?></h1><a class="link" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $notice['identity_id'] . '#avis')) ?>">Retour à la fiche</a></div>
<?php if ($errors !== []): ?><div role="alert"><ul><?php foreach ($errors as $error): ?><li><?= $h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($notice['status'] === 'active' && empty($notice['is_expired'])): ?>
<section class="panel"><form method="post" action="<?= $h(url('atak/sse/avis/' . (int) $notice['id'] . '/modifier')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="version" value="<?= $h($old['version'] ?? \App\Services\Sse\SseWantedService::amendToken($notice)) ?>">
    <p>Chaque modification conserve la version précédente, son auteur, sa date et son motif. Les dates sont saisies en <?= $h($timezoneName) ?>.</p>
    <?php foreach (['notice_type' => ['Type d’avis', $wantedTypes], 'threat_level' => ['Menace', W::THREATS], 'armed' => ['Armement', W::ARMED]] as $key => [$label, $choices]): ?>
        <div class="form-group"><label for="amend-<?= $h($key) ?>"><?= $h($label) ?></label><select id="amend-<?= $h($key) ?>" name="<?= $h($key) ?>"><?php foreach ($choices as $code => $labels): ?><option value="<?= $h($code) ?>" <?= $value($key) === $code ? 'selected' : '' ?>><?= $h(is_array($labels) ? $labels[0] : $labels) ?></option><?php endforeach; ?></select></div>
    <?php endforeach; ?>
    <?php foreach ($fields as $key => $label): ?>
        <div class="form-group"><label for="amend-<?= $h($key) ?>"><?= $h($label) ?></label>
        <?php if (in_array($key, ['reason', 'instructions'], true)): ?>
            <textarea id="amend-<?= $h($key) ?>" name="<?= $h($key) ?>" rows="4" maxlength="2000"><?= $h($value($key)) ?></textarea>
        <?php else: ?>
            <input id="amend-<?= $h($key) ?>" name="<?= $h($key) ?>" type="<?= in_array($key, ['expires_at', 'last_seen_at'], true) ? 'datetime-local' : 'text' ?>" value="<?= $h($value($key)) ?>" maxlength="200">
        <?php endif; ?></div>
    <?php endforeach; ?>
    <p class="muted">Laissez la date de fin vide pour un avis sans échéance.</p>
    <label><input type="checkbox" name="refresh_physical" value="1" <?= ($old['refresh_physical'] ?? '') === '1' ? 'checked' : '' ?>> Actualiser le signalement avec les données connues de la fiche sujet</label>
    <div class="form-group"><label for="amend-reason">Motif de la mise à jour</label><textarea id="amend-reason" name="change_reason" required minlength="3" maxlength="500"><?= $h($old['change_reason'] ?? '') ?></textarea></div>
    <button type="submit" class="btn">Enregistrer la mise à jour</button>
</form></section>
<?php else: ?><p>Cet avis est clos ou expiré. Son historique reste consultable ; utilisez le renouvellement pour le réactiver.</p><?php endif; ?>
<section class="panel"><h2>Historique des mises à jour</h2>
<?php if ($history === []): ?><p>Aucune modification enregistrée.</p><?php endif; ?>
<?php foreach ($history as $event): $details = $event['details']; ?>
    <details><summary><?= $h($event['at'] . ' UTC · ' . ($event['actor_label'] ?? '') . ' · ' . ($details['reason'] ?? '')) ?></summary>
        <table><thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead><tbody>
        <?php foreach (['notice_type' => 'Type', 'threat_level' => 'Menace', 'armed' => 'Armement'] + $fields as $field => $label): ?>
            <?php if (($details['before'][$field] ?? null) !== ($details['after'][$field] ?? null)): ?><tr><th><?= $h($label) ?></th><td><?= nl2br($h($details['before'][$field] ?? '—')) ?></td><td><?= nl2br($h($details['after'][$field] ?? '—')) ?></td></tr><?php endif; ?>
        <?php endforeach; ?>
        <?php if (($details['physical_before'] ?? []) !== ($details['physical_after'] ?? [])): ?>
            <tr><th>Signalement</th><td><?php foreach (\App\Support\SseWantedPhysical::rows($details['physical_before'] ?? [], 'fr') as $physicalRow): ?><?= $h($physicalRow['label'] . ' : ' . $physicalRow['value']) ?><br><?php endforeach; ?></td><td><?php foreach (\App\Support\SseWantedPhysical::rows($details['physical_after'] ?? [], 'fr') as $physicalRow): ?><?= $h($physicalRow['label'] . ' : ' . $physicalRow['value']) ?><br><?php endforeach; ?></td></tr>
        <?php endif; ?>
        </tbody></table>
    </details>
<?php endforeach; ?>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php'; ?>
