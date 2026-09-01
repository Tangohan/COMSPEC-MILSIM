<?php
declare(strict_types=1);

/** @var array<string,mixed>|null $integration */
/** @var list<array<string,mixed>> $steps */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $referents */
/** @var list<array<string,mixed>> $pendingInvites */
/** @var list<array<string,mixed>> $upcoming */
/** @var list<array<string,mixed>> $groups */
/** @var array<string,mixed> $dossier */
/** @var array<string,string> $statusLabels */
/** @var array<string,string> $rsvpLabels */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$row = is_array($integration ?? null) ? $integration : null;
$score = is_array($dossier['score'] ?? null) ? $dossier['score'] : [];
$crit = is_array($score['sections_critiques'] ?? null) ? $score['sections_critiques'] : [];
$pct = (int) ($row['progress_percent'] ?? 0);
?>
<link href="<?= $h(asset_url('assets/css/member-integration.css')) ?>" rel="stylesheet">
<h1>Mon intégration</h1>
<?php if (!$row): ?>
    <p>Vous n’avez pas de parcours d’intégration en cours. Si vous venez d’arriver, votre encadrement peut l’ouvrir pour vous.</p>
<?php else: ?>
    <p class="mi-muted"><?= $h($statusLabels[(string) ($row['status'] ?? '')] ?? '') ?> · <?= $pct ?> %</p>
    <div class="mi-progress"><span style="width:<?= max(0, min(100, $pct)) ?>%"></span></div>

    <?php if ($crit !== []): ?>
        <section class="mi-panel" style="margin-top:1rem">
            <h2>Votre dossier personnel</h2>
            <p>Quelques informations sont encore attendues. Complétez votre fiche pour avancer.</p>
            <p><a class="mi-btn" href="<?= $h(url('personnel/me')) ?>">Compléter mon dossier</a></p>
        </section>
    <?php endif; ?>

    <section class="mi-panel" style="margin-top:1rem">
        <h2>Votre référent</h2>
        <?php if ($referents === []): ?>
            <p class="mi-muted">Aucun référent n’est encore désigné.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($referents as $ref): ?>
                    <li><?= $h($ref['display_name'] ?? '') ?><?= !empty($ref['is_primary']) ? ' — référent principal' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="mi-panel" style="margin-top:1rem">
        <h2>Étapes</h2>
        <?php foreach ($steps as $st): ?>
            <div class="mi-step">
                <strong><?= $h($st['title'] ?? '') ?></strong>
                <p class="mi-muted"><?= $h($st['description'] ?? '') ?></p>
                <p class="mi-muted"><?= !empty($st['is_required']) ? 'Obligatoire' : 'Facultative' ?> · <?= $h($st['status'] ?? '') ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="mi-panel" style="margin-top:1rem">
        <h2>Invitations</h2>
        <?php foreach (array_merge($pendingInvites ?? [], $upcoming ?? []) as $inv): ?>
            <div class="mi-step">
                <strong><?= $h($inv['title'] ?? $inv['appointment_title'] ?? 'Rendez-vous') ?></strong>
                <p class="mi-muted"><?= $h($inv['starts_at'] ?? '') ?> · <?= $h($inv['location'] ?? '') ?></p>
                <form method="post" action="<?= $h(url('mon-integration/repondre')) ?>" class="mi-actions">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="appointment_id" value="<?= (int) ($inv['appointment_id'] ?? $inv['id'] ?? 0) ?>">
                    <button class="mi-btn" name="reponse" value="oui" type="submit">Oui</button>
                    <button class="mi-btn mi-btn--ghost" name="reponse" value="peut-etre" type="submit">Peut-être</button>
                    <button class="mi-btn mi-btn--warn" name="reponse" value="non" type="submit">Non</button>
                    <a class="mi-btn mi-btn--ghost" href="<?= $h(url('mon-integration/rendez-vous/' . (int) ($inv['appointment_id'] ?? $inv['id'] ?? 0) . '/calendrier')) ?>">Ajouter au calendrier</a>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (($pendingInvites ?? []) === [] && ($upcoming ?? []) === []): ?>
            <p class="mi-muted">Aucune invitation en attente.</p>
        <?php endif; ?>
    </section>

    <?php if (($groups ?? []) !== []): ?>
        <section class="mi-panel" style="margin-top:1rem">
            <h2>Groupes de suivi</h2>
            <ul><?php foreach ($groups as $g): ?><li><?= $h($g['name'] ?? '') ?></li><?php endforeach; ?></ul>
        </section>
    <?php endif; ?>

    <section class="mi-panel" style="margin-top:1rem">
        <h2>Messages</h2>
        <?php foreach ($events as $ev): ?>
            <p class="mi-step"><?= $h($ev['message'] ?? $ev['body'] ?? '') ?> <span class="mi-muted"><?= $h($ev['created_at'] ?? '') ?></span></p>
        <?php endforeach; ?>
        <?php if ($events === []): ?><p class="mi-muted">Aucun message pour le moment.</p><?php endif; ?>
    </section>
<?php endif; ?>
