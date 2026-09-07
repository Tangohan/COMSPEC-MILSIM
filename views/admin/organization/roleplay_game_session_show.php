<?php
declare(strict_types=1);

/** @var array<string, mixed> $gameSession */
/** @var list<array<string, mixed>> $sessionMembers */
/** @var array<string, mixed> $sessionSettings */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$s = is_array($gameSession ?? null) ? $gameSession : [];
$members = is_array($sessionMembers ?? null) ? $sessionMembers : [];
$cfg = is_array($sessionSettings ?? null) ? $sessionSettings : [];
$cats = is_array($cfg['hour_categories'] ?? null) ? $cfg['hour_categories'] : [];
$id = (int) ($s['id'] ?? 0);
$kindFr = ['officielle' => 'Officielle', 'entrainement' => 'Entraînement', 'libre' => 'Libre'];
$statusFr = ['detected' => 'Détectée', 'declared' => 'Pointée', 'confirmed' => 'Confirmée', 'absent' => 'Absente'];
$hours = static function (int $sec): string {
    if ($sec < 60) {
        return $sec . ' s';
    }
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);

    return ($h > 0 ? $h . ' h ' : '') . $m . ' min';
};
?>
<div class="bo-imm bo-community-settings">
    <p><a href="<?= $h(url('back-office/roleplay/sessions')) ?>">← Sessions Arma</a></p>
    <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide">
        <p class="bo-setting-group__kicker">Session</p>
        <h2 class="bo-setting-group__title"><?= $h((string) ($s['mission_name'] ?? 'Session')) ?></h2>
        <form method="post" action="<?= $h(url('back-office/roleplay/sessions/' . $id)) ?>" class="bo-setting-group__rows" style="margin-top:13px;">
            <?= \App\Core\Csrf::field() ?>
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">Nom</div>
                <input type="text" name="mission_name" value="<?= $h((string) ($s['mission_name'] ?? '')) ?>" class="bo-setting-row__field--wide">
            </div>
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">Type</div>
                <select name="session_kind" class="bo-setting-row__field--wide">
                    <?php foreach ($kindFr as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= (($s['session_kind'] ?? '') === $k) ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">Catégorie d’heures</div>
                <select name="hour_category" class="bo-setting-row__field--wide">
                    <?php foreach ($cats as $cat): ?>
                    <option value="<?= $h((string) $cat) ?>" <?= ((string) ($s['hour_category'] ?? '')) === (string) $cat ? 'selected' : '' ?>><?= $h((string) $cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="bo-setting-row" style="cursor:pointer;">
                <input type="checkbox" name="attendance_enabled" value="1" <?= !empty($s['attendance_enabled']) ? 'checked' : '' ?>>
                <span class="bo-setting-row__copy"><span class="bo-setting-row__label">Pointage demandé</span></span>
            </label>
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">État</div>
                <select name="status" class="bo-setting-row__field--wide">
                    <option value="open" <?= (($s['status'] ?? '') === 'open') ? 'selected' : '' ?>>En cours</option>
                    <option value="closed" <?= (($s['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Terminée</option>
                    <option value="cancelled" <?= (($s['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Annulée</option>
                    <option value="discovered" <?= (($s['status'] ?? '') === 'discovered') ? 'selected' : '' ?>>Détectée</option>
                </select>
            </div>
            <div class="bo-settings-save">
                <button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button>
            </div>
        </form>
    </section>

    <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide">
        <p class="bo-setting-group__kicker">Présences</p>
        <h2 class="bo-setting-group__title">Pointage et temps</h2>
        <p class="bo-setting-row__help">Une inscription sans pointage n’est pas une présence. Une détection sans pointage peut être retenue par un responsable.</p>
        <table class="w-full text-sm" style="margin-top:12px;">
            <thead>
                <tr class="text-left text-slate-500">
                    <th>Membre</th>
                    <th>Pointage</th>
                    <th>Détection</th>
                    <th>Temps brut</th>
                    <th>Temps retenu</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr style="border-top:1px solid #e2e8f0;">
                    <td><?= $h(trim((string) ($m['display_name'] ?? '')) ?: (trim((string) ($m['callsign'] ?? '')) ?: ('#' . (int) ($m['user_id'] ?? 0)))) ?></td>
                    <td><?= !empty($m['checked_in_at']) ? 'Oui' : 'Non' ?></td>
                    <td><?= $h($statusFr[(string) ($m['attendance_status'] ?? '')] ?? (string) ($m['attendance_status'] ?? '')) ?></td>
                    <td><?= $h($hours((int) ($m['raw_seconds'] ?? 0))) ?></td>
                    <td><?= !empty($m['rh_excluded']) ? 'Exclu' : $h($hours((int) ($m['validated_seconds'] ?? 0))) ?></td>
                    <td>
                        <form method="post" action="<?= $h(url('back-office/roleplay/sessions/' . $id . '/membres/' . (int) $m['id'])) ?>" style="display:inline;">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" name="member_action" value="validate" class="ath-btn">Retenir</button>
                            <button type="submit" name="member_action" value="exclude" class="ath-btn">Exclure</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="<?= $h(url('back-office/roleplay/sessions/' . $id . '/membres/0')) ?>" class="bo-setting-group__rows" style="margin-top:16px;">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="member_action" value="add">
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">Ajouter un membre (numéro de dossier)</div>
                <input type="number" min="1" name="user_id" class="bo-setting-row__field--wide" required>
            </div>
            <div class="bo-setting-row bo-setting-row--stack">
                <div class="bo-setting-row__label">Temps brut (secondes)</div>
                <input type="number" min="0" name="raw_seconds" value="0" class="bo-setting-row__field--wide">
            </div>
            <div class="bo-settings-save">
                <button type="submit" class="ath-btn">Ajouter</button>
            </div>
        </form>
    </section>
</div>
