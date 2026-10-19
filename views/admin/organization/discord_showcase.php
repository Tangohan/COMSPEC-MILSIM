<?php
declare(strict_types=1);

use App\Services\Community\DiscordShowcaseService;

/**
 * « Annoncer ma communauté sur Discord » (back-office) : aperçu exact du post, état de la demande et actions.
 * Contrôleur : OrganizationDiscordShowcaseController. Styles : discord-showcase.css.
 *
 * @var array<string, mixed> $showcaseEntry
 * @var array<string, mixed>|null $showcaseCard
 * @var array<string, mixed>|null $showcasePayload
 * @var list<string> $showcaseTags
 * @var array{can:bool, reason:string, next:?string, update:bool} $showcaseState
 * @var array{mode:string, forum:bool, cooldown_days:int, tags:array<string, string>} $showcaseConfig
 * @var string $showcaseBase
 */
$entry = is_array($showcaseEntry ?? null) ? $showcaseEntry : DiscordShowcaseService::normalizeEntry([]);
$card = is_array($showcaseCard ?? null) ? $showcaseCard : null;
$state = is_array($showcaseState ?? null) ? $showcaseState : ['can' => false, 'reason' => '', 'next' => null, 'update' => false];
$config = is_array($showcaseConfig ?? null) ? $showcaseConfig : DiscordShowcaseService::normalizeConfig([]);
$base = (string) ($showcaseBase ?? 'back-office/community/discord');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$status = (string) $entry['status'];
$published = $status === DiscordShowcaseService::STATUS_PUBLISHED || ($status === DiscordShowcaseService::STATUS_PENDING && $entry['published_at'] !== '');
$cooldown = (int) $config['cooldown_days'];

if ($entry['blocked']) {
    [$tone, $badge, $line] = ['danger', 'Bloquée', 'L’administration du site a bloqué les annonces Discord de votre communauté.'];
} elseif ($status === DiscordShowcaseService::STATUS_PENDING) {
    [$tone, $badge, $line] = ['warn', 'En attente de validation', $entry['published_at'] !== ''
        ? 'Votre mise à jour attend la validation de l’administration du site. Le post actuel reste en ligne.'
        : 'Votre demande attend la validation de l’administration du site. Rien n’est publié avant.'];
} elseif ($status === DiscordShowcaseService::STATUS_PUBLISHED && $entry['simulated']) {
    [$tone, $badge, $line] = ['info', 'Validée', 'Votre carte est validée. Le salon Discord des communautés ouvre bientôt : elle y sera publiée à son ouverture.'];
} elseif ($status === DiscordShowcaseService::STATUS_PUBLISHED) {
    [$tone, $badge, $line] = ['ok', 'En ligne sur Discord', 'Votre communauté a son post dans le salon des communautés du Discord Athena.'];
} elseif ($status === DiscordShowcaseService::STATUS_REJECTED) {
    [$tone, $badge, $line] = ['danger', 'Refusée', 'L’administration du site a refusé votre demande.'];
} else {
    [$tone, $badge, $line] = ['off', 'Pas encore annoncée', 'Votre communauté n’a pas encore de post sur le Discord Athena.'];
}
?>
<div class="dsc">
    <?php if ($ok): ?>
        <p class="dsc-flash dsc-flash--ok" role="status"><?= $h((string) $ok) ?></p>
    <?php endif; ?>
    <?php if ($err): ?>
        <p class="dsc-flash dsc-flash--err" role="alert"><?= $h((string) $err) ?></p>
    <?php endif; ?>

    <div class="dsc__layout">
        <section class="dsc-panel" aria-labelledby="dsc-status-title">
            <p class="dsc-panel__kicker">Discord Athena</p>
            <h2 class="dsc-panel__title" id="dsc-status-title">Faites connaître votre communauté</h2>
            <p class="dsc-panel__text">
                Le serveur Discord d’Athena a un salon où chaque communauté qui utilise Athena a son post : logo, bannière, pays, langue, jeu, recrutement,
                lien vers votre page publique et vers votre Discord. Un post par communauté, mis à jour au lieu d’être republié.
            </p>

            <div class="dsc-status dsc-status--<?= $h($tone) ?>">
                <span class="dsc-badge dsc-badge--<?= $h($tone) ?>"><?= $h($badge) ?></span>
                <p><?= $h($line) ?></p>
                <?php if ($entry['blocked'] && $entry['block_reason'] !== ''): ?>
                    <p class="dsc-status__reason">Motif : <?= $h((string) $entry['block_reason']) ?></p>
                <?php elseif ($entry['reason'] !== '' && $status !== DiscordShowcaseService::STATUS_PENDING): ?>
                    <p class="dsc-status__reason">Message de l’administration : <?= $h((string) $entry['reason']) ?></p>
                <?php endif; ?>
                <?php if ($entry['published_at'] !== ''): ?>
                    <p class="dsc-status__meta">Dernière publication : <?= $h(DiscordShowcaseService::frDate((string) $entry['published_at'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($card === null): ?>
                <div class="dsc-status dsc-status--warn">
                    <p><strong>Votre communauté n’apparaît pas dans l’annuaire public.</strong> Seules les communautés de l’annuaire peuvent être annoncées sur Discord.</p>
                    <a class="dsc-btn dsc-btn--line" href="<?= $h(url('back-office/community/presentation')) ?>">Régler la visibilité</a>
                </div>
            <?php elseif (!$entry['blocked']): ?>
                <?php if ($state['can']): ?>
                    <form method="post" action="<?= $h(url($base . '/demander')) ?>" class="dsc-form">
                        <?= \App\Core\Csrf::field() ?>
                        <label class="dsc-form__label" for="dsc-pitch">Votre message <span class="dsc-optional">(facultatif)</span></label>
                        <textarea id="dsc-pitch" name="pitch" rows="4" maxlength="<?= DiscordShowcaseService::PITCH_MAX ?>" placeholder="Ex. Unité francophone, opérations le samedi soir, recrutement ouvert aux débutants." data-dsc-pitch><?= $h((string) $entry['pitch']) ?></textarea>
                        <p class="dsc-form__hint"><span data-dsc-count><?= mb_strlen((string) $entry['pitch']) ?></span> / <?= DiscordShowcaseService::PITCH_MAX ?> caractères. Sans message, la présentation de votre carte de l’annuaire est reprise. Liens et mentions sont retirés : les liens de votre page publique et de votre Discord sont ajoutés par Athena.</p>
                        <button type="submit" class="dsc-btn dsc-btn--solid"><?= $published ? 'Demander la mise à jour du post' : 'Demander la publication' ?></button>
                    </form>
                <?php elseif ($state['reason'] === 'cooldown'): ?>
                    <p class="dsc-note">Prochaine demande possible le <strong><?= $h(DiscordShowcaseService::frDate((string) $state['next'])) ?></strong> (une mise à jour au plus tous les <?= $cooldown ?> jours).</p>
                <?php endif; ?>

                <?php if ($status === DiscordShowcaseService::STATUS_PENDING): ?>
                    <form method="post" action="<?= $h(url($base . '/annuler')) ?>" class="dsc-inline">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="dsc-btn dsc-btn--line">Annuler la demande</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($entry['published_at'] !== ''): ?>
                <form method="post" action="<?= $h(url($base . '/retirer')) ?>" class="dsc-inline" data-confirm="Retirer votre post du Discord Athena ?">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="dsc-btn dsc-btn--danger">Retirer mon annonce de Discord</button>
                </form>
            <?php endif; ?>

            <ul class="dsc-rules">
                <li>Chaque demande est validée par l’administration du site avant publication.</li>
                <li>Un seul post par communauté : une mise à jour modifie le post existant, au plus tous les <?= $cooldown ?> jours.</li>
                <li>Aucune mention @everyone ni @here, aucun lien dans le texte libre.</li>
                <li>Seules les informations déjà publiques sur votre carte de l’annuaire sont publiées.</li>
            </ul>
        </section>

        <section class="dsc-panel dsc-panel--preview" aria-labelledby="dsc-preview-title">
            <p class="dsc-panel__kicker">Aperçu</p>
            <h2 class="dsc-panel__title" id="dsc-preview-title">Votre post sur Discord</h2>
            <?php if (is_array($showcasePayload ?? null)): ?>
                <?php
                $dsPayload = $showcasePayload;
                $dsTagLabels = is_array($showcaseTags ?? null) ? $showcaseTags : [];
                require base_path('views/partials/discord_showcase_preview.php');
                ?>
                <p class="dsc-form__hint">Les libellés sont en anglais, comme le serveur Discord. Logo, bannière et couleur viennent de votre page publique : <a href="<?= $h(url('back-office/community/presentation')) ?>">les modifier</a>.</p>
            <?php else: ?>
                <p class="dsc-note">L’aperçu s’affiche dès que votre communauté apparaît dans l’annuaire public.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
<script>
(function () {
  var t = document.querySelector('[data-dsc-pitch]');
  var c = document.querySelector('[data-dsc-count]');
  if (t && c) { t.addEventListener('input', function () { c.textContent = String(t.value.length); }); }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); } });
  });
})();
</script>
