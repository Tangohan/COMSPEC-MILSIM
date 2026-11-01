<?php /* Notes de la salle de cours = carnet personnel du stagiaire (jamais visible des instructeurs). */ ?>
<form class="acr-box acr-notes" method="post" action="<?= $h($link('action/notes')) ?>"><?= \App\Core\Csrf::field() ?>
<input type="hidden" name="course" value="<?= $h($courseKey) ?>"><input type="hidden" name="return" value="classroom"><input type="hidden" name="step" value="<?= $viewC ?>.<?= $viewS ?>">
<label for="acr-notes-<?= $viewS ?>"><?= $h($t('my_notes')) ?></label>
<textarea id="acr-notes-<?= $viewS ?>" name="notes" rows="5" maxlength="30000" placeholder="<?= $h($t('notes_placeholder')) ?>"><?= $h($states['__notes']['text'] ?? '') ?></textarea>
<div class="act"><button class="btn"><?= $h($t('save_notes')) ?></button><small><?= $h($t('notes_private')) ?> <a href="<?= $h($link('notebook')) ?>"><?= $h($t('notebook')) ?></a></small></div>
</form>
