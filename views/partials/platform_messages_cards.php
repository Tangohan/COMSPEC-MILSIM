<?php
declare(strict_types=1);

/**
 * Messages de la plateforme, en cartes épinglées (accueil du back-office et page « Messages de la plateforme »).
 * Les messages ne bloquent jamais la page : cartes ordinaires, fermables sauf niveau urgent.
 *
 * @var list<array<string, mixed>> $platformMessages Messages mis en forme par PlatformBoMessageService::forTenant()
 * @var bool $canAckPlatformMessages un gestionnaire peut acquitter un message urgent
 * @var bool $pmPreview aperçu de l'administration : aucun bouton d'action
 */
$pmItems = is_array($platformMessages ?? null) ? $platformMessages : [];
if ($pmItems === []) {
    return;
}
$pmCanAck = !empty($canAckPlatformMessages);
$pmPreview = !empty($pmPreview);
$pmH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pmLevelLabel = ['info' => 'Information', 'important' => 'Important', 'urgent' => 'Urgent'];
?>
<section class="pm-stack" aria-label="Messages de la plateforme" data-pm-stack>
    <?php foreach ($pmItems as $pm):
        $pmId = (int) $pm['id'];
        $pmLevel = (string) $pm['level'];
        $pmLink = $pmPreview ? (string) ($pm['cta_url'] ?? '') : url('back-office/messages-plateforme/' . $pmId . '/lien');
        ?>
        <article class="pm-card pm-card--<?= $pmH($pmLevel) ?><?= !empty($pm['dismissed']) ? ' pm-card--muted' : '' ?>" data-pm-card="<?= $pmId ?>" role="<?= $pmLevel === 'urgent' ? 'alert' : 'status' ?>">
            <div class="pm-card__head">
                <span class="pm-card__level"><?= $pmH($pmLevelLabel[$pmLevel] ?? 'Information') ?></span>
                <?php if (!empty($pm['is_new'])): ?><span class="pm-card__new">Nouveau</span><?php endif; ?>
                <?php if (!empty($pm['dismissed'])): ?><span class="pm-card__hidden">Masqué pour vous</span><?php endif; ?>
            </div>
            <h2 class="pm-card__title"><?= $pmH((string) $pm['title']) ?></h2>
            <?php if ((string) ($pm['body_html'] ?? '') !== ''): ?>
                <p class="pm-card__body"><?= (string) $pm['body_html'] /* échappé, liens http/https/mailto validés : PlatformBoMessage::renderBody() */ ?></p>
            <?php endif; ?>
            <div class="pm-card__actions">
                <?php if (!empty($pm['cta_url'])): ?>
                    <a class="pm-btn pm-btn--primary" href="<?= $pmH($pmLink) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= $pmH((string) $pm['cta_label']) ?></a>
                <?php endif; ?>
                <?php if (!$pmPreview && empty($pm['dismissed'])): ?>
                    <?php if (!empty($pm['dismissible'])): ?>
                        <form method="post" action="<?= $pmH(url('back-office/messages-plateforme/' . $pmId . '/fermer')) ?>" class="pm-form" data-pm-form>
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="pm-btn">Fermer</button>
                        </form>
                    <?php elseif ($pmCanAck): ?>
                        <form method="post" action="<?= $pmH(url('back-office/messages-plateforme/' . $pmId . '/acquitter')) ?>" class="pm-form" data-pm-form>
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="pm-btn">J’ai pris connaissance</button>
                        </form>
                    <?php else: ?>
                        <span class="pm-card__hint">Un gestionnaire de la communauté peut l’acquitter.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php if (!$pmPreview): ?>
<script>
(function () {
  document.querySelectorAll('[data-pm-form]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.fetch) { return; }
      ev.preventDefault();
      fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var card = form.closest('[data-pm-card]');
          if (j && j.ok && card) { card.remove(); } else { form.submit(); }
        })
        .catch(function () { form.submit(); });
    });
  });
})();
</script>
<?php endif; ?>
