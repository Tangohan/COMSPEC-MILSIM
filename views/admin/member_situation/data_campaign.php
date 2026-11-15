<?php

declare(strict_types=1);

use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignLoginPrompt;
use App\Support\DataCampaignPage;

/**
 * Ma situation > Fiabilisation des données : le membre relit SA fiche, bloc par bloc.
 * Vue d'ensemble (étape « accueil »), page d'un bloc, récapitulatif et confirmation. Toute la logique d'ordre, de progression
 * et de libellés est dans App\Support\DataCampaignPage ; les états viennent de DataCampaignLoginPrompt (inchangés).
 *
 * @var bool $schemaReady
 * @var array<string, mixed>|null $campaign campagne ouverte qui concerne le membre
 * @var array<string, mixed>|null $member sa ligne de suivi
 * @var array<string, mixed>|null $campaignView parties lues (DataCampaignService::memberView)
 * @var string $step « accueil », une clé de bloc ou « recap »
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
$today = (string) ($today ?? date('Y-m-d'));
$day = static function (string $raw): string {
    $ts = strtotime($raw);

    return $ts !== false && $raw !== '' ? date('d/m/Y', $ts) : '';
};
$fieldLabels = PersonnelCorrectionRequestService::CORRECTABLE_FIELDS;
$badgeClass = ['ok' => 'dcm-badge--ok', 'to_confirm' => 'dcm-badge--neutral', 'incomplete' => 'dcm-badge--warn', 'flagged' => 'dcm-badge--info'];
$badge = static fn (string $status): string => '<span class="dcm-badge ' . ($badgeClass[$status] ?? 'dcm-badge--neutral') . '">' . htmlspecialchars(DataCampaignPage::statusLabel($status), ENT_QUOTES, 'UTF-8') . '</span>';
$svg = static fn (string $path, string $class = 'dcm-svg'): string => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
$icons = [
    'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.2 2"/>',
    'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'shield' => '<path d="M12 3 4.5 6v5.5c0 4.4 3 7.6 7.5 9.5 4.5-1.9 7.5-5.1 7.5-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
    'back' => '<path d="m14 6-6 6 6 6"/>',
    'alert' => '<path d="M12 4 3 19.5h18z"/><path d="M12 10v4.5M12 17.2v.1"/>',
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/>',
];
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
$flagList = static function (array $flags, bool $withBlock) use ($h, $requestLabel): string {
    $out = '<div class="dcm-flags">';
    foreach ($flags as $f) {
        $out .= '<div class="dcm-flag"><p class="dcm-flag__state">' . ($withBlock ? $h(DataCampaignBlocks::label((string) $f['block_key'])) . ' · ' : '') . $h($requestLabel($f)) . '</p><p class="dcm-flag__text">' . $h($f['message']) . '</p>';
        $answer = trim((string) ($f['answer'] ?? '')) !== '' ? (string) $f['answer'] : (string) ($f['request_note'] ?? '');
        if (trim($answer) !== '') {
            $out .= '<p class="dcm-flag__answer">Réponse : ' . $h($answer) . '</p>';
        }
        $out .= '</div>';
    }

    return $out . '</div>';
};
$stepUrl = static fn (string $key): string => jnet_moi_url('fiabilisation', ['etape' => $key]);
?>
<div class="dcm">
<?php if (!$schemaReady || $campaign === null || $view === null): ?>
    <section class="dcm-panel dcm-empty" aria-labelledby="dcm-none-title">
        <span class="dcm-bigic"><?= $svg($icons['check']) ?></span>
        <h2 class="dcm-panel__title" id="dcm-none-title">Aucune campagne ouverte</h2>
        <p class="dcm-text">Une fois par an, la communauté vous demande de relire votre fiche et de confirmer qu’elle est exacte. Il n’y a rien à faire pour le moment : vous serez prévenu quand une campagne sera ouverte.</p>
        <p class="dcm-actions"><a class="dcm-btn dcm-btn--ghost" href="<?= $h(jnet_moi_url('ma-fiche')) ?>">Voir ma fiche</a></p>
    </section>
<?php else:
    $blocks = $view['blocks'];
    $confirmedAt = (string) ($view['confirmed_at'] ?? '');
    $confirmed = $confirmedAt !== '';
    $prog = DataCampaignPage::progress($blocks);
    $due = DataCampaignPage::due((string) ($campaign['due_on'] ?? ''), $today, $confirmed);
    $later = !empty($comingFromLogin) ? jnet_moi_url('fiabilisation/plus-tard') : '';
    $isBlock = isset($blocks[$step]);
    ?>

    <?php if ($step !== DataCampaignLoginPrompt::STEP && ($isBlock || $step === 'recap')): ?>
        <nav class="dcm-steps" aria-label="Blocs de la fiche">
            <a class="dcm-back" href="<?= $h($stepUrl(DataCampaignLoginPrompt::STEP)) ?>"><?= $svg($icons['back']) ?><span>Vue d’ensemble</span></a>
            <ol class="dcm-steps__list">
                <?php $n = 0; foreach ($blocks as $key => $b): $n++; $st = DataCampaignLoginPrompt::blockStatus($b); ?>
                    <li><a class="dcm-step dcm-step--<?= $h($st) ?><?= $step === $key ? ' is-current' : '' ?>" href="<?= $h($stepUrl((string) $key)) ?>"<?= $step === $key ? ' aria-current="step"' : '' ?>><span aria-hidden="true"><?= $st === 'ok' ? $svg($icons['check']) : $n ?></span><span class="dcm-sr"><?= $h($b['label']) ?></span><span class="dcm-sr"><?= $h(DataCampaignPage::statusLabel($st)) ?></span></a></li>
                <?php endforeach; ?>
                <li><a class="dcm-step dcm-step--recap<?= $step === 'recap' ? ' is-current' : '' ?>" href="<?= $h($stepUrl('recap')) ?>"<?= $step === 'recap' ? ' aria-current="step"' : '' ?>><span aria-hidden="true"><?= $svg($icons['shield']) ?></span><span class="dcm-sr">Récapitulatif</span></a></li>
            </ol>
        </nav>
    <?php endif; ?>

    <?php if ($step === DataCampaignLoginPrompt::STEP):
        $groups = DataCampaignPage::groups($blocks);
        $action = DataCampaignPage::primaryAction($blocks, $confirmed);
        $card = static function (array $row) use ($h, $badge, $svg, $stepUrl): string {
            $b = $row['block'];
            $status = $row['status'];
            $id = 'dcm-b-' . $h($row['key']);
            $out = '<li class="dcm-card dcm-card--' . $h($status) . '"><div class="dcm-card__head"><span class="dcm-ic">' . $svg(DataCampaignPage::iconPath($row['key'])) . '</span><h3 class="dcm-card__name" id="' . $id . '">' . $h($b['label']) . '</h3>' . $badge($status) . '</div>';
            $items = DataCampaignPage::summary($row['key'], $b);
            if ($items !== []) {
                $out .= '<dl class="dcm-sum">';
                foreach ($items as $it) {
                    $out .= '<div class="dcm-sum__item' . ($it['missing'] ? ' is-missing' : '') . '"><dt>' . $h($it['label']) . '</dt><dd>' . $h($it['value']) . '</dd></div>';
                }
                $out .= '</dl>';
            }
            if (!empty($b['changed_since_answer'])) {
                $out .= '<p class="dcm-hint">Cette partie a changé depuis votre dernière réponse : relisez-la.</p>';
            }
            $primary = DataCampaignPage::isTodo($status) || $status === 'flagged';

            return $out . '<a class="dcm-btn ' . ($primary ? 'dcm-btn--outline' : 'dcm-btn--ghost') . '" href="' . $h($stepUrl($row['key'])) . '" aria-describedby="' . $id . '">' . $h(DataCampaignPage::actionLabel($status)) . '</a></li>';
        };
        ?>
        <section class="dcm-hero<?= $confirmed ? ' is-done' : '' ?>" aria-labelledby="dcm-hero-title">
            <div class="dcm-hero__row">
                <div class="dcm-hero__main">
                    <?php if ($confirmed): ?>
                        <span class="dcm-bigic dcm-bigic--ok"><?= $svg($icons['check']) ?></span>
                        <h2 class="dcm-hero__title" id="dcm-hero-title">Votre fiche est confirmée</h2>
                        <p class="dcm-hero__sub">Confirmée le <?= $h($day($confirmedAt)) ?>. Merci : vous n’avez plus rien à faire.</p>
                    <?php else: ?>
                        <h2 class="dcm-hero__title" id="dcm-hero-title"><?= $h(DataCampaignPage::progressLabel($prog['confirmed'], $prog['total'])) ?></h2>
                        <p class="dcm-hero__sub"><?= $h(DataCampaignPage::remainingLabel($prog['remaining'], $prog['flagged'])) ?><?php if ($prog['flagged'] > 0): ?> · <?= $h(DataCampaignPage::flaggedLabel($prog['flagged'])) ?><?php endif; ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($due['tone'] !== 'none'): ?>
                    <p class="dcm-due dcm-due--<?= $h($due['tone']) ?>"><?= $svg($icons['clock']) ?><span><?= $h($due['date']) ?></span><span class="dcm-due__delay"><?= $h($due['delay']) ?></span></p>
                <?php endif; ?>
            </div>
            <div class="dcm-bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $prog['total'] ?>" aria-valuenow="<?= (int) $prog['confirmed'] ?>" aria-valuetext="<?= $h(DataCampaignPage::progressLabel($prog['confirmed'], $prog['total'])) ?>">
                <span class="dcm-bar__ok" style="width: <?= (int) $prog['percent'] ?>%"></span><span class="dcm-bar__flag" style="width: <?= (int) $prog['flaggedPercent'] ?>%"></span>
            </div>
            <?php if (!$confirmed && $due['tone'] === 'late'): ?>
                <p class="dcm-alert"><?= $svg($icons['alert']) ?><span>La date limite est dépassée. Prenez 2 minutes maintenant : l’encadrement est prévenu des fiches non confirmées.</span></p>
            <?php endif; ?>
        </section>

        <?php if ($groups['todo'] !== []): ?>
            <section class="dcm-group" aria-labelledby="dcm-todo-title">
                <h2 class="dcm-group__title" id="dcm-todo-title">À traiter <span class="dcm-count"><?= count($groups['todo']) ?></span></h2>
                <ul class="dcm-cards"><?php foreach ($groups['todo'] as $row) { echo $card($row); } ?></ul>
            </section>
        <?php endif; ?>
        <?php if ($groups['done'] !== []): ?>
            <section class="dcm-group" aria-labelledby="dcm-done-title">
                <h2 class="dcm-group__title" id="dcm-done-title"><?= $groups['todo'] === [] ? 'Vos blocs' : 'Déjà traités' ?> <span class="dcm-count"><?= count($groups['done']) ?></span></h2>
                <ul class="dcm-cards"><?php foreach ($groups['done'] as $row) { echo $card($row); } ?></ul>
            </section>
        <?php endif; ?>

        <?php if (!$confirmed): ?>
            <section class="dcm-reassure" aria-label="Ce qu’il faut savoir">
                <p><?= $svg($icons['clock']) ?><span><strong>Environ 2 minutes.</strong> Un coup d’œil par bloc suffit.</span></p>
                <p><?= $svg($icons['lock']) ?><span><strong>Vos réponses restent privées.</strong> Seuls vous et l’encadrement de la campagne les voient.</span></p>
                <p><?= $svg($icons['shield']) ?><span><strong>Rien ne change sans validation.</strong> Une correction demandée passe par la validation habituelle.</span></p>
                <details class="dcm-more">
                    <summary>Et si je ne fais rien ?</summary>
                    <p>Rien n’est supprimé ni bloqué : votre fiche reste telle quelle. Elle ne sera simplement pas considérée comme fiable, vous recevrez des rappels, l’encadrement verra qu’elle n’est pas confirmée et cette page vous sera reproposée à votre prochaine connexion.</p>
                </details>
            </section>
        <?php endif; ?>

        <?php if (!$confirmed || $later !== ''): ?>
        <div class="dcm-cta" role="group" aria-label="Actions">
            <?php if (!$confirmed): ?><p class="dcm-cta__text"><?= $h(DataCampaignPage::remainingLabel($prog['remaining'], $prog['flagged'])) ?></p><?php endif; ?>
            <?php if ($confirmed): ?>
                <?php if ($later !== ''): ?><a class="dcm-btn dcm-btn--primary" href="<?= $h($later) ?>">Continuer</a><?php endif; ?>
            <?php else: ?>
                <?php if ($later !== ''): ?><a class="dcm-btn dcm-btn--quiet" href="<?= $h($later) ?>">Plus tard</a><?php endif; ?>
                <?php if ($action['kind'] === 'confirm_all'): ?>
                    <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/tout-confirmer')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                        <button type="submit" class="dcm-btn dcm-btn--primary">Tout confirmer</button>
                    </form>
                <?php else: ?>
                    <a class="dcm-btn dcm-btn--primary" href="<?= $h($stepUrl($action['step'])) ?>"><?= $h($action['label']) ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php elseif ($isBlock):
        $b = $blocks[$step];
        $status = DataCampaignLoginPrompt::blockStatus($b);
        $fields = is_array($b['fields'] ?? null) ? $b['fields'] : [];
        ?>
        <section class="dcm-panel" aria-labelledby="dcm-block-title">
            <header class="dcm-panel__head">
                <span class="dcm-ic dcm-ic--lg"><?= $svg(DataCampaignPage::iconPath($step)) ?></span>
                <div class="dcm-panel__headtext">
                    <h2 class="dcm-panel__title" id="dcm-block-title"><?= $h($b['label']) ?></h2>
                    <p class="dcm-text dcm-text--muted"><?= $h($b['lead']) ?></p>
                </div>
                <?= $badge($status) ?>
            </header>
            <?php if (!empty($b['changed_since_answer'])): ?>
                <p class="dcm-alert"><?= $svg($icons['alert']) ?><span>Cette partie a changé depuis votre dernière réponse : relisez-la et répondez de nouveau.</span></p>
            <?php endif; ?>

            <?php $toComplete = $status === 'incomplete' ? DataCampaignLoginPrompt::missingLabels($b) : []; ?>
            <?php if ($status === 'incomplete'): ?>
                <p class="dcm-alert"><?= $svg($icons['alert']) ?><span><?= $toComplete !== []
                    ? 'À compléter : ' . $h(implode(', ', $toComplete)) . '. Choisissez « À corriger » ci-dessous et indiquez la bonne valeur : l’encadrement la valide. « Tout confirmer » n’est pas proposé tant qu’il manque une information.'
                    : 'Aucune donnée n’est enregistrée dans cette partie. Choisissez « À corriger » ci-dessous pour le dire à l’encadrement.' ?></span></p>
            <?php endif; ?>
            <div class="dcm-detail">
                <?php if (!empty($b['portrait'])): ?>
                    <img class="dcm-portrait" src="<?= $h($b['portrait']) ?>" alt="Votre portrait enregistré" width="112" height="112" loading="lazy">
                <?php endif; ?>
                <?php if ($b['rows'] !== []): ?>
                    <dl class="dcm-rows">
                        <?php foreach ($b['rows'] as $row): $missing = DataCampaignPage::isMissingValue($row['value'] ?? ''); ?>
                            <div class="dcm-rows__line<?= $missing ? ' is-missing' : '' ?>">
                                <dt><?= $h($row['label']) ?></dt>
                                <dd><?= $h($row['value']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
            <?php if ($b['notice'] !== ''): ?>
                <p class="dcm-hint"><?= $h($b['notice']) ?></p>
            <?php endif; ?>
            <?php if ($b['links'] !== []): ?>
                <p class="dcm-links">
                    <?php foreach ($b['links'] as $link): ?>
                        <a class="dcm-btn dcm-btn--ghost" href="<?= $h($link['href']) ?>"><?= $h($link['label']) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($b['flags'] !== []): ?>
                <?= $flagList($b['flags'], false) ?>
            <?php endif; ?>

            <div class="dcm-actions">
                <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/repondre')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                    <input type="hidden" name="block" value="<?= $h($b['key']) ?>">
                    <input type="hidden" name="verdict" value="ok">
                    <button type="submit" class="dcm-btn dcm-btn--primary">C’est exact</button>
                </form>
                <details class="dcm-fix">
                    <summary class="dcm-btn dcm-btn--ghost"><?= $svg($icons['edit']) ?>À corriger</summary>
                    <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/repondre')) ?>" class="dcm-form">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                        <input type="hidden" name="block" value="<?= $h($b['key']) ?>">
                        <input type="hidden" name="verdict" value="flag">
                        <label class="dcm-field">
                            <span>Qu’est-ce qui n’est pas exact ?</span>
                            <textarea name="message" rows="4" minlength="5" maxlength="1000" required placeholder="Dites ce qui est faux et ce que ça devrait être."></textarea>
                        </label>
                        <?php if ($fields !== []): ?>
                            <p class="dcm-hint">Si vous connaissez la bonne valeur, indiquez-la : elle part en demande de correction, validée par l’encadrement. Laissez vide ce qui ne change pas.</p>
                            <?php foreach ($fields as $field): ?>
                                <label class="dcm-field">
                                    <span><?= $h($fieldLabels[$field] ?? $field) ?></span>
                                    <input type="<?= $field === 'enlistment_date' ? 'date' : 'text' ?>" name="f[<?= $h($field) ?>]" maxlength="200" autocomplete="off">
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($b['key'] === DataCampaignBlocks::IDENTITY): ?>
                            <p class="dcm-hint">Pour changer votre image, utilisez « Envoyer un nouveau portrait » ci-dessus : l’envoi est celui de votre compte.</p>
                        <?php endif; ?>
                        <button type="submit" class="dcm-btn dcm-btn--primary">Envoyer le signalement</button>
                    </form>
                </details>
            </div>
        </section>
    <?php elseif ($step === 'recap'): ?>
        <section class="dcm-panel<?= $confirmed ? ' dcm-panel--done' : '' ?>" aria-labelledby="dcm-recap-title">
            <header class="dcm-panel__head">
                <?php if ($confirmed): ?><span class="dcm-bigic dcm-bigic--ok"><?= $svg($icons['check']) ?></span><?php endif; ?>
                <div class="dcm-panel__headtext">
                    <h2 class="dcm-panel__title" id="dcm-recap-title"><?= $confirmed ? 'Votre fiche est confirmée' : 'Récapitulatif' ?></h2>
                    <p class="dcm-text dcm-text--muted">
                        <?php if ($confirmed): ?>Confirmée le <?= $h($day($confirmedAt)) ?>. Merci : vous n’avez plus rien à faire. Vous pouvez encore signaler une erreur depuis chaque bloc tant que la campagne est ouverte.
                        <?php elseif ($view['complete']): ?>Tous les blocs ont une réponse. Il ne reste qu’à confirmer votre fiche.
                        <?php else: ?>Il reste des blocs à vérifier avant de pouvoir confirmer votre fiche.<?php endif; ?>
                    </p>
                </div>
            </header>
            <ul class="dcm-recap">
                <?php foreach (DataCampaignPage::ordered($blocks) as $row): ?>
                    <li>
                        <span class="dcm-ic"><?= $svg(DataCampaignPage::iconPath($row['key'])) ?></span>
                        <a class="dcm-recap__name" href="<?= $h($stepUrl($row['key'])) ?>"><?= $h($row['block']['label']) ?></a>
                        <?= $badge($row['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($view['flags'] !== []): ?>
                <h3 class="dcm-sub">Suivi de vos signalements</h3>
                <?= $flagList($view['flags'], true) ?>
            <?php endif; ?>

            <?php if (!empty($view['changed'])): ?>
                <p class="dcm-alert"><?= $svg($icons['alert']) ?><span>Depuis votre confirmation, une donnée a été modifiée dans votre fiche (<?= $h(implode(', ', array_map(static fn (string $k): string => DataCampaignBlocks::label($k), $view['changed']))) ?>). La campagne garde ce que vous aviez confirmé.</span></p>
            <?php endif; ?>

            <div class="dcm-actions">
                <?php if ($confirmed): ?>
                    <a class="dcm-btn dcm-btn--primary" href="<?= $h(url('jnet/espaces')) ?>">Retour à mon espace</a>
                <?php elseif ($view['complete']): ?>
                    <form method="post" action="<?= $h(jnet_moi_url('fiabilisation/confirmer')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="campaign" value="<?= (int) $campaign['id'] ?>">
                        <button type="submit" class="dcm-btn dcm-btn--primary">Confirmer ma fiche</button>
                    </form>
                    <p class="dcm-hint">La date de confirmation et ce que vous avez vu sont enregistrés.</p>
                <?php else: ?>
                    <a class="dcm-btn dcm-btn--primary" href="<?= $h($stepUrl(DataCampaignPage::primaryAction($blocks, false)['step'])) ?>">Continuer</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($unitFollow !== null): ?>
        <section class="dcm-panel" aria-labelledby="dcm-unit-title">
            <header class="dcm-panel__head">
                <div class="dcm-panel__headtext">
                    <h2 class="dcm-panel__title" id="dcm-unit-title">Suivi de mon unité</h2>
                    <p class="dcm-text dcm-text--muted">Vous voyez l’état d’avancement des membres de l’unité que vous commandez, pas le contenu de leurs réponses.</p>
                </div>
                <span class="dcm-badge dcm-badge--neutral"><?= (int) $unitFollow['totals']['confirmed'] ?> confirmé<?= (int) $unitFollow['totals']['confirmed'] > 1 ? 's' : '' ?> sur <?= (int) $unitFollow['totals']['total'] ?></span>
            </header>
            <ul class="dcm-recap">
                <?php foreach ($unitFollow['members'] as $m): ?>
                    <li>
                        <span class="dcm-recap__name"><?= $h($m['display_name'] !== '' ? $m['display_name'] : $m['callsign']) ?></span>
                        <span class="dcm-badge <?= $m['bucket'] === 'confirmed' ? 'dcm-badge--ok' : ($m['bucket'] === 'flagged' ? 'dcm-badge--info' : 'dcm-badge--neutral') ?>"><?= $m['bucket'] === 'confirmed' ? 'Confirmé' : ($m['bucket'] === 'flagged' ? 'Avec signalement' : 'Sans réponse') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($history !== []): ?>
    <section class="dcm-panel" aria-labelledby="dcm-history-title">
        <h2 class="dcm-panel__title" id="dcm-history-title">Mes campagnes</h2>
        <ul class="dcm-recap">
            <?php foreach ($history as $row): $ok = trim((string) ($row['confirmed_at'] ?? '')) !== ''; ?>
                <li>
                    <span class="dcm-recap__name"><?= $h($row['title'] ?? '') ?> (<?= (int) ($row['campaign_year'] ?? 0) ?>)</span>
                    <span class="dcm-badge <?= $ok ? 'dcm-badge--ok' : 'dcm-badge--neutral' ?>"><?= $ok ? 'Fiabilisée le ' . $h($day((string) $row['confirmed_at'])) : 'Non confirmée' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
</div>
