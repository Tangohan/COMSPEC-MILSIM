<?php

declare(strict_types=1);

use App\Support\DataCampaignCalendar;

/**
 * Back-office > Fiabilisation des données : liste des campagnes, création et réglage de l'ouverture automatique.
 *
 * @var bool $schemaReady
 * @var list<array<string, mixed>> $campaigns
 * @var array<string, mixed> $settings
 * @var array<string, mixed>|null $openNowPlan ce que ferait « Ouvrir maintenant » (membres concernés ou raison du refus)
 * @var array<int, array{name: string, parent_id: int}> $units
 * @var array<string, array{label: string, lead: string, fields: list<string>, volatile: bool}> $blockCatalog
 * @var int $defaultYear
 * @var string $defaultDue
 * @var string $csrfToken
 */

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$schemaReady = !empty($schemaReady);
$campaigns = is_array($campaigns ?? null) ? $campaigns : [];
$settings = is_array($settings ?? null) ? $settings : [];
$units = is_array($units ?? null) ? $units : [];
$blockCatalog = is_array($blockCatalog ?? null) ? $blockCatalog : [];
$csrfToken = (string) ($csrfToken ?? '');
$openNowPlan = is_array($openNowPlan ?? null) ? $openNowPlan : null;
$defaultYear = (int) ($defaultYear ?? date('Y'));
$defaultDue = (string) ($defaultDue ?? '');
$day = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y', $ts) : '—';
};
$forcedOn = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y', $ts) : '';
};
$statusText = ['draft' => 'Brouillon', 'open' => 'Ouverte', 'closed' => 'Clôturée'];
$statusClass = ['draft' => 'dc-pill--muted', 'open' => 'dc-pill--ok', 'closed' => 'dc-pill--info'];
$selectedBlocks = is_array($settings['blocks'] ?? null) ? $settings['blocks'] : array_keys($blockCatalog);
$selectedUnits = is_array($settings['unit_ids'] ?? null) ? $settings['unit_ids'] : [];
$openMd = (string) ($settings['open_md'] ?? DataCampaignCalendar::DEFAULT_OPEN_MD);
$closeMd = (string) ($settings['close_md'] ?? DataCampaignCalendar::DEFAULT_CLOSE_MD);
[$openMonth, $openDay] = array_map('intval', explode('-', $openMd) + [1 => 1, 0 => 12]);
[$closeMonth, $closeDay] = array_map('intval', explode('-', $closeMd) + [1 => 31, 0 => 12]);
$reminderText = implode(', ', is_array($settings['reminder_days'] ?? null) ? $settings['reminder_days'] : DataCampaignCalendar::DEFAULT_REMINDER_DAYS);
$unitOptions = static function (array $units, array $selected) use ($h): string {
    $out = '';
    foreach ($units as $id => $u) {
        $out .= '<option value="' . (int) $id . '"' . (in_array((int) $id, $selected, true) ? ' selected' : '') . '>' . $h($u['name']) . '</option>';
    }

    return $out;
};
?>
<div class="dc dc--bo">
<?php if (!$schemaReady): ?>
    <section class="dc-card">
        <h2 class="dc-card__title">Mise à jour de la base à lancer</h2>
        <p class="dc-text">Les tables de la campagne de fiabilisation ne sont pas encore créées. Lancez les mises à jour de la base (étape « Campagne de fiabilisation des données »), puis rechargez cette page.</p>
    </section>
<?php else: ?>
    <section class="dc-card" aria-labelledby="dc-list-title">
        <h2 class="dc-card__title" id="dc-list-title">Campagnes</h2>
        <?php if ($campaigns === []): ?>
            <p class="dc-text">Aucune campagne pour le moment. Créez-en une ci-dessous, ou activez l’ouverture automatique chaque année.</p>
        <?php else: ?>
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead><tr><th scope="col">Campagne</th><th scope="col">Statut</th><th scope="col">Ouverture</th><th scope="col">Date limite</th><th scope="col"><span class="dc-sr">Suivi</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td><?= $h($c['title']) ?> <span class="dc-muted">(<?= (int) $c['campaign_year'] ?><?= !empty($c['auto_created']) ? (((string) ($c['forced_on'] ?? '')) !== '' ? ', ouverte par Athena (forcée le ' . $forcedOn((string) $c['forced_on']) . ')' : ', ouverte par Athena') : '' ?>)</span></td>
                            <td><span class="dc-pill <?= $statusClass[(string) $c['status']] ?? 'dc-pill--muted' ?>"><?= $h($statusText[(string) $c['status']] ?? $c['status']) ?></span></td>
                            <td><?= $h($day((string) ($c['opens_on'] ?? ''))) ?></td>
                            <td><?= $h($day((string) ($c['due_on'] ?? ''))) ?></td>
                            <td><a class="dc-btn dc-btn--ghost" href="<?= $h(url('back-office/organisation/fiabilisation/' . (int) $c['id'])) ?>">Ouvrir le suivi</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dc-card" aria-labelledby="dc-now-title">
        <h2 class="dc-card__title" id="dc-now-title">Ouvrir maintenant</h2>
        <?php if ($openNowPlan !== null && ($openNowPlan['action'] ?? '') === 'open'): ?>
            <p class="dc-text">Athena ouvre tout de suite la campagne de cette année pour toute la communauté, comme elle le fait chaque année : <b><?= (int) $openNowPlan['members'] ?></b> membre(s) seraient concernés, prévenus par notification et e-mail, par lots.</p>
            <?php if (!empty($openNowPlan['incomplete'])): ?>
                <p class="dc-alert">Beaucoup de fiches sont incomplètes (prénom ou nom manquant) : complétez l’état civil avant ou pendant la campagne.</p>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('back-office/organisation/fiabilisation/ouvrir-maintenant')) ?>" class="dc-form dc-form--row" data-confirm="Ouvrir la campagne maintenant ? <?= (int) $openNowPlan['members'] ?> membre(s) seront notifiés.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label class="dc-field"><span>Date limite</span><input type="date" name="deadline" value="<?= $h($defaultDue) ?>" required></label>
                <button type="submit" class="dc-btn">Ouvrir maintenant</button>
            </form>
        <?php else: ?>
            <p class="dc-text">Pas d’ouverture possible pour le moment : <?= $h($openNowPlan['reason'] ?? 'indisponible.') ?></p>
        <?php endif; ?>
    </section>

    <section class="dc-card" aria-labelledby="dc-new-title">
        <h2 class="dc-card__title" id="dc-new-title">Nouvelle campagne</h2>
        <p class="dc-text">La campagne est créée en brouillon : elle ne concerne personne tant que vous ne l’ouvrez pas.</p>
        <form method="post" action="<?= $h(url('back-office/organisation/fiabilisation/creer')) ?>" class="dc-form dc-grid">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <label class="dc-field"><span>Intitulé</span><input type="text" name="title" maxlength="120" placeholder="Fiabilisation des données <?= $defaultYear ?>"></label>
            <label class="dc-field"><span>Année</span><input type="number" name="year" min="2000" max="2100" value="<?= $defaultYear ?>" required></label>
            <label class="dc-field"><span>Ouverture</span><input type="date" name="opens_on" value="<?= $h(date('Y-m-d')) ?>"></label>
            <label class="dc-field"><span>Date limite</span><input type="date" name="due_on" value="<?= $h($defaultDue) ?>" required></label>
            <label class="dc-field"><span>Rappels (jours avant la date limite)</span><input type="text" name="reminder_days" value="<?= $h(implode(', ', DataCampaignCalendar::DEFAULT_REMINDER_DAYS)) ?>" inputmode="numeric" pattern="[0-9, ]*"></label>
            <fieldset class="dc-field dc-field--wide">
                <legend>Parties de la fiche relues</legend>
                <?php foreach ($blockCatalog as $key => $b): ?>
                    <label class="dc-check"><input type="checkbox" name="blocks[]" value="<?= $h($key) ?>" checked> <?= $h($b['label']) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <label class="dc-field dc-field--wide"><span>Unités concernées (aucune : toute la communauté, sous-unités comprises)</span>
                <select name="unit_ids[]" multiple size="6"><?= $unitOptions($units, []) ?></select>
            </label>
            <div class="dc-field dc-field--wide"><button type="submit" class="dc-btn">Créer la campagne</button></div>
        </form>
    </section>

    <section class="dc-card" id="reglage" aria-labelledby="dc-auto-title">
        <h2 class="dc-card__title" id="dc-auto-title">Ouverture automatique chaque année</h2>
        <p class="dc-text">Activée par défaut : Athena ouvre une campagne à la date choisie, envoie les rappels aux dates prévues (une seule fois chacun), puis la clôture à la date limite et vous remet le rapport d’avancement. Elle ne modifie jamais une fiche. Une campagne créée à la main pour l’année empêche Athena d’en ouvrir une seconde.</p>
        <form method="post" action="<?= $h(url('back-office/organisation/fiabilisation/reglages')) ?>" class="dc-form dc-grid">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <fieldset class="dc-field">
                <legend>Ouverture automatique</legend>
                <label class="dc-check"><input type="radio" name="auto_enabled" value="1"<?= !empty($settings['auto_enabled']) ? ' checked' : '' ?>> Oui</label>
                <label class="dc-check"><input type="radio" name="auto_enabled" value="0"<?= empty($settings['auto_enabled']) ? ' checked' : '' ?>> Non</label>
            </fieldset>
            <fieldset class="dc-field">
                <legend>E-mails d’ouverture et de rappel</legend>
                <label class="dc-check"><input type="radio" name="emails_enabled" value="1"<?= !empty($settings['emails_enabled']) ? ' checked' : '' ?>> Oui</label>
                <label class="dc-check"><input type="radio" name="emails_enabled" value="0"<?= empty($settings['emails_enabled']) ? ' checked' : '' ?>> Non</label>
            </fieldset>
            <div class="dc-field"><span>Ouverture (jour et mois)</span>
                <div class="dc-inline"><input type="number" name="open_day" min="1" max="31" value="<?= $openDay ?>" aria-label="Jour d’ouverture"><input type="number" name="open_month" min="1" max="12" value="<?= $openMonth ?>" aria-label="Mois d’ouverture"></div>
            </div>
            <div class="dc-field"><span>Date limite (jour et mois)</span>
                <div class="dc-inline"><input type="number" name="close_day" min="1" max="31" value="<?= $closeDay ?>" aria-label="Jour de la date limite"><input type="number" name="close_month" min="1" max="12" value="<?= $closeMonth ?>" aria-label="Mois de la date limite"></div>
            </div>
            <label class="dc-field"><span>Rappels (jours avant la date limite)</span><input type="text" name="reminder_days" value="<?= $h($reminderText) ?>" inputmode="numeric" pattern="[0-9, ]*"></label>
            <fieldset class="dc-field dc-field--wide">
                <legend>Parties de la fiche relues</legend>
                <?php foreach ($blockCatalog as $key => $b): ?>
                    <label class="dc-check"><input type="checkbox" name="blocks[]" value="<?= $h($key) ?>"<?= in_array($key, $selectedBlocks, true) ? ' checked' : '' ?>> <?= $h($b['label']) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <label class="dc-field dc-field--wide"><span>Unités concernées (aucune : toute la communauté)</span>
                <select name="unit_ids[]" multiple size="6"><?= $unitOptions($units, $selectedUnits) ?></select>
            </label>
            <div class="dc-field dc-field--wide"><button type="submit" class="dc-btn">Enregistrer le réglage</button></div>
        </form>
    </section>
<?php endif; ?>
</div>
