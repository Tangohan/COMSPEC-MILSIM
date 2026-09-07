<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $sessionList */
/** @var array<string, mixed> $sessionSettings */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$list = is_array($sessionList ?? null) ? $sessionList : [];
$cfg = is_array($sessionSettings ?? null) ? $sessionSettings : [];
$cats = is_array($cfg['hour_categories'] ?? null) ? $cfg['hour_categories'] : [];
$formAction = (string) ($sessionFormAction ?? url('back-office/roleplay/sessions'));
$kindFr = ['officielle' => 'Officielle', 'entrainement' => 'Entraînement', 'libre' => 'Libre'];
$statusFr = ['discovered' => 'Détectée', 'open' => 'En cours', 'closed' => 'Terminée', 'cancelled' => 'Annulée'];
?>
<div class="bo-imm bo-community-settings">
    <section class="bo-imm__hero ath-rise">
        <div class="bo-imm__hero-copy">
            <span class="bo-imm__eyebrow">Roleplay · Sessions</span>
            <h2>Heures et présences en session Arma</h2>
            <p>
                Une session officielle compte pour le suivi et le taux de présence.
                Un entraînement compte comme des heures de formation.
                Une session libre alimente seulement le temps brut.
            </p>
            <div class="bo-imm__hero-actions">
                <a href="<?= $h(url('back-office/roleplay/immersion')) ?>" class="ath-btn">Cadences</a>
                <a href="<?= $h(url('back-office/roleplay/regles-phases')) ?>" class="ath-btn">Parcours RH</a>
            </div>
        </div>
    </section>

    <form method="post" action="<?= $h($formAction) ?>" class="bo-settings-grid">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="_intent" value="settings">
        <section class="ath-card ath-rise bo-setting-group">
            <p class="bo-setting-group__kicker">Réglages</p>
            <h2 class="bo-setting-group__title">Catégories et présence</h2>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Catégories d’heures (une par ligne)</div>
                    <textarea name="rp_session_hour_categories" rows="5" class="bo-setting-row__field--wide"><?= $h(implode("\n", array_map('strval', $cats))) ?></textarea>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Présence retenue — minimum de minutes</div>
                    <input type="number" min="0" max="240" name="rp_session_min_minutes" value="<?= (int) ($cfg['min_minutes'] ?? 45) ?>" class="bo-setting-row__field--wide">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">… ou part de la session (%)</div>
                    <input type="number" min="0" max="100" name="rp_session_min_percent" value="<?= (int) ($cfg['min_percent'] ?? 50) ?>" class="bo-setting-row__field--wide">
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Tolérance de retard (minutes)</div>
                    <input type="number" min="0" max="120" name="rp_session_late_tolerance" value="<?= (int) ($cfg['late_tolerance_minutes'] ?? 15) ?>" class="bo-setting-row__field--wide">
                </div>
                <label class="bo-setting-row" style="cursor:pointer;">
                    <input type="checkbox" name="rp_session_sync_enabled" value="1" <?= !empty($cfg['sync_enabled']) ? 'checked' : '' ?>>
                    <span class="bo-setting-row__copy">
                        <span class="bo-setting-row__label">Recevoir les sessions détectées depuis Arma</span>
                    </span>
                </label>
            </div>
            <div class="bo-settings-save">
                <button type="submit" class="ath-btn ath-btn--solid" formaction="<?= $h(url('back-office/roleplay/sessions/reglages')) ?>">Enregistrer les réglages</button>
            </div>
        </section>
    </form>

    <form method="post" action="<?= $h(url('back-office/roleplay/sessions/nouvelle')) ?>" class="bo-settings-grid">
        <?= \App\Core\Csrf::field() ?>
        <section class="ath-card ath-rise bo-setting-group">
            <p class="bo-setting-group__kicker">Nouvelle session</p>
            <h2 class="bo-setting-group__title">Créer une session</h2>
            <div class="bo-setting-group__rows" style="margin-top:13px;">
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Nom de la mission</div>
                    <input type="text" name="mission_name" maxlength="191" class="bo-setting-row__field--wide" required>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Type</div>
                    <select name="session_kind" class="bo-setting-row__field--wide">
                        <option value="officielle">Officielle</option>
                        <option value="entrainement">Entraînement</option>
                        <option value="libre">Libre</option>
                    </select>
                </div>
                <div class="bo-setting-row bo-setting-row--stack">
                    <div class="bo-setting-row__label">Catégorie d’heures</div>
                    <select name="hour_category" class="bo-setting-row__field--wide">
                        <?php foreach ($cats as $cat): ?>
                        <option value="<?= $h((string) $cat) ?>"><?= $h((string) $cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="bo-settings-save">
                <button type="submit" class="ath-btn ath-btn--solid">Créer la session</button>
            </div>
        </section>
    </form>

    <section class="ath-card ath-rise bo-setting-group bo-setting-group--wide">
        <p class="bo-setting-group__kicker">Registre</p>
        <h2 class="bo-setting-group__title">Sessions récentes</h2>
        <?php if ($list === []): ?>
        <p class="bo-setting-row__help" style="margin-top:10px;">Aucune session pour l’instant. Créez-en une, ou laissez Arma en détecter une.</p>
        <?php else: ?>
        <ul style="list-style:none;padding:0;margin:12px 0 0;">
            <?php foreach ($list as $row): ?>
            <li style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid #e2e8f0;">
                <span>
                    <a href="<?= $h(url('back-office/roleplay/sessions/' . (int) $row['id'])) ?>"><strong><?= $h((string) ($row['mission_name'] ?? 'Session')) ?></strong></a>
                    <span class="bo-setting-row__help"> · <?= $h($kindFr[(string) ($row['session_kind'] ?? '')] ?? (string) ($row['session_kind'] ?? '')) ?> · <?= $h($statusFr[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? '')) ?></span>
                </span>
                <span class="bo-setting-row__help"><?= $h((string) ($row['started_at'] ?? $row['created_at'] ?? '')) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
