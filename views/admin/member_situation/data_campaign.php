<?php

declare(strict_types=1);

use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Support\DataCampaignBlocks;

/**
 * Ma situation > Fiabilisation des données : le membre relit SA fiche, partie par partie.
 *
 * @var bool $schemaReady
 * @var array<string, mixed>|null $campaign campagne ouverte qui concerne le membre
 * @var array<string, mixed>|null $member sa ligne de suivi
 * @var array<string, mixed>|null $campaignView parties lues (DataCampaignService::memberView)
 * @var string $step partie affichée (clé de bloc) ou « recap »
 * @var list<array<string, mixed>> $history campagnes passées du membre
 * @var array<string, mixed>|null $unitFollow suivi de l'unité pour qui la commande
 * @var string $csrfToken
 */

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$schemaReady = !empty($schemaReady);
$campaign = is_array($campaign ?? null) ? $campaign : null;
$view = is_array($campaignView ?? null) ? $campaignView : null;
$step = (string) ($step ?? '');
$history = is_array($history ?? null) ? $history : [];
$unitFollow = is_array($unitFollow ?? null) ? $unitFollow : null;
$csrfToken = (string) ($csrfToken ?? '');
$day = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y', $ts) : '';
};
$fieldLabels = PersonnelCorrectionRequestService::CORRECTABLE_FIELDS;
$stateLabel = ['ok' => 'Confirmé exact', 'flag' => 'À corriger', '' => 'À faire'];
$stateClass = ['ok' => 'msd-pill--ok', 'flag' => 'msd-pill--warn', '' => 'msd-pill--muted'];
$requestLabel = static function (array $f): string {
    if ((string) ($f['status'] ?? '') === 'handled') {
        return 'Traité par l’encadrement';
    }
    return match ((string) ($f['request_status'] ?? '')) {
        'pending' => 'Demande de correction en attente de décision',
        'approved' => 'Demande acceptée : la fiche a été corrigée',
        'rejected' => 'Demande refusée',
        'cancelled' => 'Demande annulée',
        default => 'Transmis à l’encadrement',
    };
};
$base = jnet_moi_url('fiabilisation');
?>
<div class="msd dc">
<?php if (!$schemaReady || $campaign === null || $view === null): ?>
    <section class="msd-card" aria-labelledby="dc-none-title">
        <header class="msd-card__head"><h2 class="msd-card__title" id="dc-none-title">Aucune campagne ouverte</h2></header>
        <p class="dc-text">Une fois par an, la communauté vous demande de relire votre fiche et de confirmer qu’elle est exacte. Il n’y a rien à faire pour le moment : vous serez prévenu quand une campagne sera ouverte.</p>
        <p class="dc-text"><a class="dc-link" href="<?= $h(jnet_moi_url('ma-fiche')) ?>">Voir ma fiche</a></p>
    </section>
<?php else:
    $blocks = $view['blocks'];
    $confirmedAt = (string) ($view['confirmed_at'] ?? '');
    $due = $day((string) ($campaign['due_on'] ?? ''));
    ?>
    <section class="msd-card" aria-labelledby="dc-intro-title">
        <header class="msd-card__head">
            <h2 class="msd-card__title" id="dc-intro-title"><?= $h($campaign['title'] ?? 'Fiabilisation des données') ?></h2>
            <span class="msd-card__count"><?= (int) $view['answered'] ?> partie<?= (int) $view['answered'] > 1 ? 's' : '' ?> sur <?= (int) $view['total'] ?></span>
        </header>
        <p class="dc-text">
            <?php if ($confirmedAt !== ''): ?>
                Vous avez confirmé votre fiche le <b><?= $h($day($confirmedAt)) ?></b>. Merci.
            <?php else: ?>
                Relisez chaque partie de votre fiche : dites si c’est exact ou ce qui est à corriger, puis confirmez votre fiche.
            <?php endif; ?>
        </p>
        <?php if ($confirmedAt === '' && $due !== ''): ?>
            <p class="dc-text">Date limite : <b><?= $h($due) ?></b></p>
        <?php endif; ?>
        <p class="dc-note">Ces informations sont lues dans votre fiche telle qu’elle est enregistrée. Votre réponse n’est visible que de vous et de l’encadrement chargé de la campagne. Rien n’est modifié automatiquement : une correction passe par la validation habituelle.</p>

        <nav class="dc-steps" aria-label="Parties de la fiche">
            <ol>
                <?php foreach ($blocks as $key => $b): ?>
                    <li class="<?= $step === $key ? 'is-current' : '' ?>">
                        <a href="<?= $h(jnet_moi_url('fiabilisation', ['etape' => $key])) ?>"<?= $step === $key ? ' aria-current="step"' : '' ?>>
                            <span class="dc-steps__name"><?= $h($b['label']) ?></span>
                            <span class="msd-pill <?= $stateClass[(string) $b['state']] ?? 'msd-pill--muted' ?>"><?= $h($stateLabel[(string) $b['state']] ?? 'À faire') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="<?= $step === 'recap' ? 'is-current' : '' ?>">
                    <a href="<?= $h(jnet_moi_url('fiabilisation', ['etape' => 'recap'])) ?>"<?= $step === 'recap' ? ' aria-current="step"' : '' ?>>
                        <span class="dc-steps__name">Récapitulatif</span>
                        <span class="msd-pill <?= $confirmedAt !== '' ? 'msd-pill--ok' : 'msd-pill--muted' ?>"><?= $confirmedAt !== '' ? 'Confirmée' : 'À faire' ?></span>
                    </a>
                </li>
            </ol>
        </nav>
    </section>

    <?php if (isset($blocks[$step])):
        $b = $blocks[$step];
        $fields = is_array($b['fields'] ?? null) ? $b['fields'] : [];
        ?>
        <section class="msd-card" aria-labelledby="dc-block-title">
            <header class="msd-card__head">
                <h2 class="msd-card__title" id="dc-block-title"><?= $h($b['label']) ?></h2>
                <span class="msd-pill <?= $stateClass[(string) $b['state']] ?? 'msd-pill--muted' ?>"><?= $h($stateLabel[(string) $b['state']] ?? 'À faire') ?></span>
            </header>
            <p class="dc-text"><?= $h($b['lead']) ?></p>
            <?php if (!empty($b['changed_since_answer'])): ?>
                <p class="dc-alert">Cette partie a changé depuis votre dernière réponse : relisez-la et répondez de nouveau.</p>
            <?php endif; ?>

            <?php if (!empty($b['portrait'])): ?>
                <p class="dc-portrait"><img src="<?= $h($b['portrait']) ?>" alt="Votre portrait enregistré" width="120" height="120" loading="lazy"></p>
            <?php endif; ?>

            <?php if ($b['rows'] !== []): ?>
                <dl class="dc-rows">
                    <?php foreach ($b['rows'] as $row): ?>
                        <div class="dc-rows__line">
                            <dt><?= $h($row['label']) ?></dt>
                            <dd><?= $h($row['value']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <?php if ($b['notice'] !== ''): ?>
                <p class="dc-note"><?= $h($b['notice']) ?></p>
            <?php endif; ?>
            <?php if ($b['links'] !== []): ?>
                <p class="dc-links">
                    <?php foreach ($b['links'] as $link): ?>
                        <a class="dc-link" href="<?= $h($link['href']) ?>"><?= $h($link['label']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($b['flags'] !== []): ?>
                <div class="dc-flags" aria-label="Suivi de vos signalements">
                    <?php foreach ($b['flags'] as $f): ?>
                        <div class="dc-flag">
                            <p class="dc-flag__state"><?= $h($requestLabel($f)) ?></p>
                            <p class="dc-flag__text"><?= $h($f['message']) ?></p>
                            <?php if (trim((string) ($f['answer'] ?? '')) !== ''): ?>
                                <p class="dc-flag__answer">Réponse : <?= $h($f['answer']) ?></p>
                            <?php elseif (trim((string) ($f['request_note'] ?? '')) !== ''): ?>
                                <p class="dc-flag__answer">Réponse : <?= $h($f['request_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="dc-actions">
                <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/repondre')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                    <input type="hidden" name="block" value="<?= $h($b['key']) ?>">
                    <input type="hidden" name="verdict" value="ok">
                    <button type="submit" class="dc-btn">C’est exact</button>
                </form>
                <details class="dc-fix">
                    <summary class="dc-btn dc-btn--ghost">À corriger</summary>
                    <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/repondre')) ?>" class="dc-form">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                        <input type="hidden" name="block" value="<?= $h($b['key']) ?>">
                        <input type="hidden" name="verdict" value="flag">
                        <label class="dc-field">
                            <span>Qu’est-ce qui n’est pas exact ?</span>
                            <textarea name="message" rows="4" minlength="5" maxlength="1000" required placeholder="Dites ce qui est faux et ce que ça devrait être."></textarea>
                        </label>
                        <?php if ($fields !== []): ?>
                            <p class="dc-note">Si vous connaissez la bonne valeur, indiquez-la : elle part en demande de correction, validée par l’encadrement. Laissez vide ce qui ne change pas.</p>
                            <?php foreach ($fields as $field): ?>
                                <label class="dc-field">
                                    <span><?= $h($fieldLabels[$field] ?? $field) ?></span>
                                    <input type="<?= $field === 'enlistment_date' ? 'date' : 'text' ?>" name="f[<?= $h($field) ?>]" maxlength="200" autocomplete="off">
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($b['key'] === DataCampaignBlocks::IDENTITY): ?>
                            <p class="dc-note">Pour changer votre image, utilisez « Envoyer un nouveau portrait » ci-dessus : l’envoi est celui de votre compte.</p>
                        <?php endif; ?>
                        <button type="submit" class="dc-btn">Envoyer le signalement</button>
                    </form>
                </details>
            </div>
        </section>
    <?php elseif ($step === 'recap'): ?>
        <section class="msd-card" aria-labelledby="dc-recap-title">
            <header class="msd-card__head"><h2 class="msd-card__title" id="dc-recap-title">Récapitulatif</h2></header>
            <ul class="dc-recap">
                <?php foreach ($blocks as $key => $b): ?>
                    <li>
                        <a href="<?= $h(jnet_moi_url('fiabilisation', ['etape' => $key])) ?>"><?= $h($b['label']) ?></a>
                        <span class="msd-pill <?= $stateClass[(string) $b['state']] ?? 'msd-pill--muted' ?>"><?= $h($stateLabel[(string) $b['state']] ?? 'À faire') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($view['flags'] !== []): ?>
                <h3 class="dc-sub">Suivi de vos signalements</h3>
                <div class="dc-flags">
                    <?php foreach ($view['flags'] as $f): ?>
                        <div class="dc-flag">
                            <p class="dc-flag__state"><?= $h(DataCampaignBlocks::label((string) $f['block_key'])) ?> · <?= $h($requestLabel($f)) ?></p>
                            <p class="dc-flag__text"><?= $h($f['message']) ?></p>
                            <?php if (trim((string) ($f['answer'] ?? '')) !== ''): ?>
                                <p class="dc-flag__answer">Réponse : <?= $h($f['answer']) ?></p>
                            <?php elseif (trim((string) ($f['request_note'] ?? '')) !== ''): ?>
                                <p class="dc-flag__answer">Réponse : <?= $h($f['request_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($view['changed'])): ?>
                <p class="dc-alert">Depuis votre confirmation, une donnée a été modifiée dans votre fiche (<?= $h(implode(', ', array_map(static fn (string $k): string => DataCampaignBlocks::label($k), $view['changed']))) ?>). La campagne garde ce que vous aviez confirmé.</p>
            <?php endif; ?>

            <?php if ($confirmedAt !== ''): ?>
                <p class="dc-text">Fiche confirmée le <b><?= $h($day($confirmedAt)) ?></b>. Vous pouvez encore signaler une erreur depuis chaque partie tant que la campagne est ouverte.</p>
            <?php elseif ($view['complete']): ?>
                <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/confirmer')) ?>" class="dc-actions">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                    <button type="submit" class="dc-btn">Confirmer ma fiche</button>
                </form>
                <p class="dc-note">La date de confirmation et ce que vous avez vu sont enregistrés.</p>
            <?php else: ?>
                <p class="dc-text">Il reste des parties à vérifier avant de pouvoir confirmer votre fiche.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($unitFollow !== null): ?>
        <section class="msd-card" aria-labelledby="dc-unit-title">
            <header class="msd-card__head">
                <h2 class="msd-card__title" id="dc-unit-title">Suivi de mon unité</h2>
                <span class="msd-card__count"><?= (int) $unitFollow['totals']['confirmed'] ?> confirmé<?= (int) $unitFollow['totals']['confirmed'] > 1 ? 's' : '' ?> sur <?= (int) $unitFollow['totals']['total'] ?></span>
            </header>
            <p class="dc-note">Vous voyez l’état d’avancement des membres de l’unité que vous commandez, pas le contenu de leurs réponses.</p>
            <ul class="dc-recap">
                <?php foreach ($unitFollow['members'] as $m): ?>
                    <li>
                        <span><?= $h($m['display_name'] !== '' ? $m['display_name'] : $m['callsign']) ?></span>
                        <span class="msd-pill <?= $m['bucket'] === 'confirmed' ? 'msd-pill--ok' : ($m['bucket'] === 'flagged' ? 'msd-pill--warn' : 'msd-pill--muted') ?>"><?= $m['bucket'] === 'confirmed' ? 'Confirmé' : ($m['bucket'] === 'flagged' ? 'Avec signalement' : 'Sans réponse') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($history !== []): ?>
    <section class="msd-card" aria-labelledby="dc-history-title">
        <header class="msd-card__head"><h2 class="msd-card__title" id="dc-history-title">Mes campagnes</h2></header>
        <ul class="dc-recap">
            <?php foreach ($history as $row): ?>
                <li>
                    <span><?= $h($row['title'] ?? '') ?> (<?= (int) ($row['campaign_year'] ?? 0) ?>)</span>
                    <span class="msd-pill <?= trim((string) ($row['confirmed_at'] ?? '')) !== '' ? 'msd-pill--ok' : 'msd-pill--muted' ?>"><?= trim((string) ($row['confirmed_at'] ?? '')) !== '' ? 'Fiabilisée le ' . $h($day((string) $row['confirmed_at'])) : 'Non confirmée' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
</div>
