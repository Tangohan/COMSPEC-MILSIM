<?php

declare(strict_types=1);

/**
 * Envoi d'un compte rendu de fin d'opération aux joueurs dont l'ATAK est relié à Athena (à remplir, ou à lire).
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_admin_head.php';
?>
    <form class="mm-card mm-form" data-admin-form="cr_send" data-reset data-confirm="Envoyer ce compte rendu à tous les joueurs connectés ?">
      <h1 class="mm-h1">Envoyer un compte rendu de fin d’opération</h1>
      <div class="mm-field"><label class="mm-label" for="r-title">Titre</label><input class="mm-input" id="r-title" name="titre" maxlength="200" placeholder="Fin d’opération"></div>
      <div class="mm-field"><label class="mm-label">Les joueurs doivent</label>
        <div class="mm-choices mm-choices--seg">
          <button type="button" class="mm-choice is-on" data-choice="fill">Le remplir</button>
          <button type="button" class="mm-choice" data-choice="read">Le lire</button>
          <input type="hidden" name="mode" value="fill">
        </div>
      </div>
      <div class="mm-field"><label class="mm-label" for="r-text">Texte à lire (pour « Le lire »)</label><textarea class="mm-input" id="r-text" name="texte" rows="4" maxlength="6000"></textarea></div>
      <div class="mm-field"><label class="mm-label" for="r-min">Joueurs vus depuis (minutes)</label><input class="mm-input" id="r-min" name="minutes" inputmode="numeric" pattern="[0-9]*" value="120" maxlength="4"></div>
      <button class="mm-btn mm-btn--send" type="submit">ENVOYER</button>
      <p class="mm-muted" data-admin-result></p>
    </form>
<?php require __DIR__ . '/_admin_foot.php'; ?>
