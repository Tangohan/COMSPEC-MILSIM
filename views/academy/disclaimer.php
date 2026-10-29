<dialog class="academy-disclaimer" data-academy-disclaimer data-disclaimer-version="2026-10-29-v2" aria-labelledby="academy-disclaimer-title" aria-describedby="academy-disclaimer-description">
<form method="dialog" data-disclaimer-form>
<p class="eyebrow"><?= $h($t('prerelease')) ?></p><h2 id="academy-disclaimer-title"><?= $h($t('disclaimer_title')) ?></h2>
<p id="academy-disclaimer-description"><?= $h($t('disclaimer')) ?></p><p><?= $h($t('disclaimer_data')) ?></p>
<label class="academy-disclaimer__check"><input type="checkbox" required autofocus><span><?= $h($t('disclaimer_understood')) ?></span></label>
<button class="button" type="submit"><?= $h($t('disclaimer_accept')) ?> →</button>
<p class="academy-disclaimer__remember"><?= $h($t('disclaimer_remember')) ?></p>
</form></dialog>
