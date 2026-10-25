<?php
declare(strict_types=1);

/**
 * Onglet « Connexion en jeu » : fenêtre Athena au lancement d'Arma, méthodes de connexion,
 * synchronisation du profil, fonctions autorisées depuis le jeu, version du pack, fréquence des positions.
 *
 * @var array<string, mixed> $ge
 * @var callable $h, $ico, $section, $switch, $howto, $on
 */
$gp = is_array($gameExperiencePreview ?? null) ? $gameExperiencePreview : ['name' => 'ATHENA', 'image' => '', 'message' => ''];
$img = trim((string) ($gp['image'] ?? ''));
$hasImg = trim((string) ($ge['login_image_path'] ?? '')) !== '';
$hasLogo = trim((string) ($ge['logo_path'] ?? '')) !== '';
$interval = (int) ($ge['update_interval'] ?? 0);
?>
<section class="bo-ac-sec" id="overwatch-game-experience" data-ac-panel>
  <?= $section('game', 'Connexion en jeu', 'Ce que voient vos opérateurs au lancement d’Arma (fenêtre Athena) et ce que le mod a le droit de faire. Appliqué à la prochaine connexion de chaque joueur.') ?>
  <form method="post" action="<?= $h($base . '/game-experience') ?>" enctype="multipart/form-data" class="bo-ac-form bo-ac-game" id="bo-ac-game">
    <?= $csrf ?>
    <div class="bo-ac-game__cfg">

      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label">Fenêtre de connexion</p>
        <div class="bo-ac-grid bo-ac-grid--2">
          <label class="bo-ac-field"><span>Nom affiché</span><input name="game_display_name" maxlength="80" value="<?= $h($ge['display_name'] ?? '') ?>" placeholder="<?= $h($gp['name'] ?? 'ATHENA') ?>" data-ac-game="name"><small>Vide : le nom de la communauté.</small></label>
          <label class="bo-ac-field"><span>Message d’accueil</span><input name="game_welcome_message" maxlength="280" value="<?= $h($ge['welcome_message'] ?? '') ?>" placeholder="Ex. Bienvenue au 2e REP — briefing 20 h 45" data-ac-game="message"></label>
        </div>
        <div class="bo-ac-grid bo-ac-grid--2">
          <div class="bo-ac-field"><span>Image de connexion</span>
            <label class="bo-ac-drop"><input type="file" name="game_login_image" accept="image/jpeg,image/png,image/webp" data-ac-file-preview="[data-ac-game-img]"><span><?= $ico('photo') ?> <?= $hasImg ? 'Remplacer l’image' : 'Choisir une image' ?></span><small>JPG, PNG ou WebP · paysage 16:9 conseillé (1280 × 720)</small></label>
            <?php if ($hasImg): ?><label class="bo-ac-check"><input type="checkbox" name="game_remove_login_image" value="1"> Retirer l’image actuelle</label><?php endif; ?>
          </div>
          <div class="bo-ac-field"><span>Logo</span>
            <label class="bo-ac-drop"><input type="file" name="game_logo" accept="image/jpeg,image/png,image/webp"><span><?= $ico('shield') ?> <?= $hasLogo ? 'Remplacer le logo' : 'Choisir un logo' ?></span><small>Carré, fond transparent (PNG 256 × 256)</small></label>
            <?php if ($hasLogo): ?><label class="bo-ac-check"><input type="checkbox" name="game_remove_logo" value="1"> Retirer le logo actuel</label><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label">Méthodes de connexion autorisées <small>au moins une</small></p>
        <div class="bo-ac-grid bo-ac-grid--3">
          <?= $switch('game_auth_password', $on('auth_password'), 'E-mail et mot de passe', 'Les identifiants du portail.', 'mail', ' data-ac-auth="E-mail"') ?>
          <?= $switch('game_auth_otp', $on('auth_otp'), 'Code par e-mail', 'Un code à 6 chiffres reçu par e-mail.', 'code', ' data-ac-auth="Code temporaire"') ?>
          <?= $switch('game_auth_steam', $on('auth_steam'), 'Steam', 'Sans saisie, pour un compte Steam déjà associé.', 'steam', ' data-ac-auth="Steam"') ?>
        </div>
        <?= $switch('game_allow_auto_reconnect', $on('allow_auto_reconnect'), 'Reconnexion automatique', 'Le joueur reste connecté d’une session à l’autre. Désactivé : nouvelle connexion à chaque lancement d’Arma.', 'refresh') ?>
        <p class="bo-ac-muted bo-ac-warnline" data-ac-auth-warn hidden><?= $ico('warn', 14) ?> Gardez au moins une méthode : sinon plus personne ne peut se lier.</p>
      </div>

      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label">Profil transmis au mod</p>
        <p class="bo-ac-muted">Coché : le mod reprend la fiche Athena du joueur. Décoché : l’information n’est pas envoyée au mod (pour l’indicatif, celui réglé dans les options du mod est conservé).</p>
        <div class="bo-ac-grid bo-ac-grid--3">
          <?php foreach ([
              'sync_profile' => ['user', 'Nom et prénom'],
              'sync_grade' => ['shield', 'Grade'],
              'sync_unit' => ['modules', 'Unité'],
              'sync_callsign' => ['radio', 'Indicatif'],
              'sync_avatar' => ['photo', 'Portrait'],
              'sync_clearances' => ['key', 'Rôle et fonction'],
          ] as $k => [$i, $l]): ?>
            <?= $switch('game_' . $k, $on($k), $l, '', $i) ?>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="ath-panel bo-ac-card">
        <p class="bo-ac-label">Fonctions autorisées depuis le jeu</p>
        <p class="bo-ac-muted">Une fonction coupée est refusée quand elle vient du mod ; la Tacmap web n’est pas concernée. Le suivi des positions reste toujours actif : il porte la liaison.</p>
        <div class="bo-ac-grid bo-ac-grid--2">
          <?= $switch('game_chat_enabled', $on('chat_enabled'), 'Messagerie', 'Messages tactiques envoyés depuis la tablette.', 'chat') ?>
          <?= $switch('game_markers_enabled', $on('markers_enabled'), 'Marqueurs', 'Marqueurs posés en jeu et repris sur la carte.', 'marker') ?>
          <?= $switch('game_photos_enabled', $on('photos_enabled'), 'Photos', 'Photos terrain et photos de renseignement.', 'photo') ?>
          <?= $switch('game_intel_enabled', $on('intel_enabled'), 'Renseignement', 'Rapports SIGINT remontés par le mod.', 'intel') ?>
          <?= $switch('game_jtac_enabled', $on('jtac_enabled'), 'Appui feu', '9-line, demandes CAS et désignation laser.', 'fire') ?>
        </div>
      </div>

      <div class="ath-panel bo-ac-card">
        <div class="bo-ac-grid bo-ac-grid--2">
          <label class="bo-ac-field"><span>Version minimale du pack Overwatch</span><input name="game_min_mod_version" value="<?= $h($ge['min_mod_version'] ?? '1.5.0') ?>" pattern="\d+\.\d+(\.\d+)?" placeholder="1.5.0" inputmode="decimal"><small>Un pack plus ancien est refusé à la connexion, avec la version installée et celle demandée. Format : 1.6.0.</small></label>
          <label class="bo-ac-field"><span>Fréquence d’envoi des positions</span>
            <select name="game_update_interval">
              <option value="0"<?= $interval === 0 ? ' selected' : '' ?>>Réglage du pack (3 s)</option>
              <?php foreach ([2, 3, 5, 10, 15, 30, 60] as $s): ?><option value="<?= $s ?>"<?= $interval === $s ? ' selected' : '' ?>>Toutes les <?= $s ?> s<?= $s >= 10 ? ' (économe)' : '' ?></option><?php endforeach; ?>
            </select>
            <small>Plus court = carte plus fluide mais plus de trafic. Imposé aux joueurs à partir du prochain pack Overwatch.</small></label>
        </div>
      </div>
    </div>

    <aside class="bo-ac-game__prev">
      <p class="bo-ac-label">Aperçu de la fenêtre Arma</p>
      <div class="bo-ac-arma">
        <div class="bo-ac-arma__strip"></div>
        <div class="bo-ac-arma__top"><b>ATHENA</b><span>COMSPEC OVERWATCH</span></div>
        <div class="bo-ac-arma__img"><img data-ac-game-img src="<?= $h($img) ?>" alt=""<?= $img === '' ? ' hidden' : '' ?>><span data-ac-game-noimg<?= $img !== '' ? ' hidden' : '' ?>>Image de votre communauté</span></div>
        <p class="bo-ac-arma__title">CONNEXION À ATHENA</p>
        <p class="bo-ac-arma__name" data-ac-game-out="name"><?= $h($gp['name'] ?? '') ?></p>
        <p class="bo-ac-arma__msg" data-ac-game-out="message"><?= $h($gp['message'] ?? '') ?></p>
        <div class="bo-ac-arma__field"></div><div class="bo-ac-arma__field"></div>
        <div class="bo-ac-arma__btn">SE CONNECTER</div>
        <p class="bo-ac-arma__methods" data-ac-game-out="methods"></p>
      </div>
      <?= $howto('Tester sans déranger les joueurs', [
          'Gardez une méthode que vous maîtrisez (e-mail et mot de passe) le temps du test.',
          'Lancez Arma avec le pack Overwatch : la fenêtre Athena reprend ces réglages à l’ouverture.',
          'Un réglage refusé (méthode, version) affiche un message explicite au joueur, jamais un écran vide.',
      ]) ?>
    </aside>
    <div class="bo-ac-save"><span class="bo-ac-muted">S’applique à la prochaine connexion Overwatch de chaque joueur.</span><button type="submit" class="ath-btn ath-btn--solid">Enregistrer la connexion en jeu</button></div>
  </form>
</section>
