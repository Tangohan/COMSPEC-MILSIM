<?php
declare(strict_types=1);

/**
 * Zone vivante du centre de diffusion : filtres et liste. Page complète, ou fragment renvoyé après chaque action.
 * Tout texte saisi par un membre est échappé et marqué translate="no".
 *
 * @var array<string, mixed> $form BroadcastCenterController::viewData()
 */

use App\Support\AtakCommunityBrief as Brief;
use App\Support\BroadcastCenter as BC;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bcBase ??= url('back-office/alerts');
$items = is_array($form['items'] ?? null) ? $form['items'] : [];
$filters = is_array($form['filters'] ?? null) ? $form['filters'] : [];
$notesRev = (string) ($form['notesRev'] ?? '');
$allowedChannels = is_array($form['allowedChannels'] ?? null) ? $form['allowedChannels'] : [];
$csrf = \App\Core\Csrf::field();
$keep = '';
foreach (['type', 'canal', 'etat', 'importance', 'tri'] as $k) {
    $keep .= '<input type="hidden" name="f_' . $k . '" value="' . $h($filters[$k] ?? '') . '">';
}
$fmt = static fn (?int $t): string => $t ? date('d/m/Y H:i', $t) : '';
$local = static fn (?int $t): string => $t ? date('Y-m-d\TH:i', $t) : '';
$partChannel = ['site' => 'site', 'note' => 'phone', 'game' => 'game', 'orders' => 'tasks'];
$stateTone = ['active' => 'ok', 'scheduled' => 'info', 'done' => 'neut', 'expired' => 'neut', 'withdrawn' => 'neut'];

/** Dépliant sans JavaScript, fenêtre avec. */
$fold = static function (string $summary, string $kicker, string $title, string $inner, string $class = 'ath-btn ath-btn--sm') use ($h): string {
    return '<details class="bo-brief-fold" data-bc-fold data-kicker="' . $h($kicker) . '" data-title="' . $h($title) . '">'
        . '<summary class="' . $h($class) . '">' . $h($summary) . '</summary><div class="bo-brief-fold__b">' . $inner . '</div></details>';
};
$hidden = static fn (string $ref): string => $csrf . $keep . '<input type="hidden" name="ref" value="' . $h($ref) . '"><input type="hidden" name="rev" value="' . $h($notesRev) . '">';

$editForm = static function (array $item) use ($h, $bcBase, $hidden, $local): string {
    ob_start();
    if (isset($item['parts']['note'])) {
        $n = $item['parts']['note'];
        ?>
        <form method="post" action="<?= $h($bcBase . '/centre/modifier') ?>" class="bo-brief-form" data-bc-form>
          <?= $hidden($item['ref']) ?>
          <label class="bo-brief-f"><span>Genre</span>
            <select name="kind"><?php foreach (Brief::KINDS as $k): ?><option value="<?= $h($k) ?>"<?= $n['kind'] === $k ? ' selected' : '' ?>><?= $h(BC::TYPES[$k]) ?></option><?php endforeach; ?></select>
          </label>
          <label class="bo-brief-f"><span>Titre <small data-count-out><?= Brief::MAX_TITLE ?> caractères au plus</small></span>
            <input type="text" name="title" maxlength="<?= Brief::MAX_TITLE ?>" value="<?= $h($n['title']) ?>" translate="no" autocomplete="off" data-count>
          </label>
          <label class="bo-brief-f"><span>Texte <small data-count-out><?= Brief::MAX_BODY ?> caractères au plus</small></span>
            <textarea name="body" rows="5" maxlength="<?= Brief::MAX_BODY ?>" translate="no" data-count><?= $h($n['body']) ?></textarea>
            <small>Même note que dans Consignes et kit ATAK : la modifier ici la modifie là-bas.</small>
          </label>
          <div class="bo-brief-form__f"><button type="button" class="ath-btn" data-bc-close hidden>Annuler</button><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
        </form>
        <?php
    } else {
        $s = $item['parts']['site'];
        ?>
        <form method="post" action="<?= $h($bcBase . '/centre/modifier') ?>" class="bo-brief-form" data-bc-form>
          <?= $hidden($item['ref']) ?>
          <label class="bo-brief-f"><span>Titre <small data-count-out><?= BC::MAX_TITLE ?> caractères au plus</small></span>
            <input type="text" name="title" maxlength="<?= BC::MAX_TITLE ?>" required value="<?= $h($s['title']) ?>" translate="no" autocomplete="off" data-count>
          </label>
          <label class="bo-brief-f"><span>Texte <small data-count-out><?= BC::MAX_BODY ?> caractères au plus</small></span>
            <textarea name="body" rows="5" maxlength="<?= BC::MAX_BODY ?>" translate="no" data-count><?= $h($s['body']) ?></textarea>
          </label>
          <fieldset class="bo-brief-f"><legend>Importance</legend><div class="bo-bc-choices">
            <?php foreach (BC::IMPORTANCE as $v => $l): ?><label class="bo-bc-choice bo-bc-imp is-<?= $h($v) ?>"><input type="radio" name="importance" value="<?= $h($v) ?>"<?= ($item['importance'] ?? 'info') === $v ? ' checked' : '' ?>><span><?= $h($l) ?></span></label><?php endforeach; ?>
          </div></fieldset>
          <div class="bo-brief-form__row">
            <label class="bo-brief-f bo-brief-f--grow"><span>Début</span><input type="datetime-local" name="starts_at" value="<?= $h($s['starts_at'] !== '' ? $local(strtotime($s['starts_at']) ?: null) : '') ?>"></label>
            <label class="bo-brief-f bo-brief-f--grow"><span>Fin</span><input type="datetime-local" name="ends_at" value="<?= $h($s['ends_at'] !== '' ? $local(strtotime($s['ends_at']) ?: null) : '') ?>"></label>
          </div>
          <?php if (array_diff($item['channels'], ['site', 'discord']) !== []): ?>
            <p class="bo-brief-hint">Seul le bandeau du site change : ce qui est déjà parti vers les joueurs reste tel quel.</p>
          <?php endif; ?>
          <p class="bo-brief-hint"><a href="<?= $h(url('back-office/alerts/' . (int) $s['id'] . '/edit')) ?>">Image, bouton, emplacement : formulaire complet</a></p>
          <div class="bo-brief-form__f"><button type="button" class="ath-btn" data-bc-close hidden>Annuler</button><button type="submit" class="ath-btn ath-btn--solid">Enregistrer</button></div>
        </form>
        <?php
    }

    return (string) ob_get_clean();
};

$withdrawForm = static function (array $item) use ($h, $bcBase, $hidden): string {
    $parts = [];
    if (isset($item['parts']['site'])) {
        $parts[] = 'le bandeau quitte le portail (il reste dans la liste, retiré)';
    }
    if (isset($item['parts']['note'])) {
        $parts[] = 'la note disparaît du téléphone de tous les joueurs';
    }
    if (isset($item['parts']['game'])) {
        $parts[] = 'les messages en jeu pas encore validés sont annulés';
    }
    if (isset($item['parts']['orders'])) {
        $parts[] = 'l’ordre est annulé dans l’app Tâches';
    }
    $text = 'Retirer « ' . $item['title'] . ' » : ' . implode(' ; ', $parts) . '.';
    if (in_array('discord', $item['channels'], true)) {
        $text .= ' Le message Discord, lui, reste dans le salon.';
    }

    return '<form method="post" action="' . $h($bcBase . '/centre/retirer') . '" class="bo-brief-form" data-bc-form>' . $hidden($item['ref'])
        . '<p class="effk-md__txt">' . $h($text) . '</p>'
        . '<div class="bo-brief-form__f"><button type="button" class="ath-btn" data-bc-close hidden>Annuler</button>'
        . '<button type="submit" class="ath-btn ath-btn--danger">Retirer</button></div></form>';
};
$select = static function (string $name, string $label, array $choices, string $current) use ($h): string {
    $out = '<label class="bo-brief-f"><span>' . $h($label) . '</span><select name="' . $h($name) . '"><option value="">Tous</option>';
    foreach ($choices as $v => $l) {
        $out .= '<option value="' . $h($v) . '"' . ($current === (string) $v ? ' selected' : '') . '>' . $h($l) . '</option>';
    }

    return $out . '</select></label>';
};
$hasFilter = array_filter($filters, static fn (string $v, string $k): bool => $v !== '' && $k !== 'tri', ARRAY_FILTER_USE_BOTH) !== [];
?>
<section class="ath-panel bo-brief-sec" aria-labelledby="bo-bc-list-t">
  <header class="bo-brief-sec__h">
    <div>
      <h2 id="bo-bc-list-t">Tout ce qui est diffusé</h2>
      <p class="bo-brief-hint">Bandeaux du site, notes de l’app Communauté, messages en jeu et ordres web des <?= \App\Services\Broadcast\BroadcastCenterService::HISTORY_DAYS ?> derniers jours. Une diffusion envoyée sur plusieurs canaux forme une seule ligne.</p>
    </div>
  </header>

  <form method="get" action="<?= $h($bcBase) ?>#diffusions" class="bo-bc-filters" data-bc-filters>
    <?= $select('type', 'Type', BC::TYPES, (string) ($filters['type'] ?? '')) ?>
    <?= $select('canal', 'Canal', BC::CHANNELS, (string) ($filters['canal'] ?? '')) ?>
    <?= $select('etat', 'État', BC::STATES, (string) ($filters['etat'] ?? '')) ?>
    <?= $select('importance', 'Importance', BC::IMPORTANCE, (string) ($filters['importance'] ?? '')) ?>
    <label class="bo-brief-f"><span>Tri</span><select name="tri">
      <option value="">Plus récents d’abord</option>
      <option value="importance"<?= ($filters['tri'] ?? '') === 'importance' ? ' selected' : '' ?>>Importance (urgent d’abord)</option>
    </select></label>
    <div class="bo-bc-filters__a">
      <button type="submit" class="ath-btn ath-btn--sm" data-bc-nojs>Filtrer</button>
      <?php if ($hasFilter): ?><a class="ath-btn ath-btn--sm" href="<?= $h($bcBase) ?>#diffusions" data-bc-reset>Tout afficher</a><?php endif; ?>
    </div>
  </form>

  <p class="bo-brief-hint" data-bc-count><?= count($items) ?> diffusion(s)<?= $hasFilter ? ' pour ce filtre' : '' ?>.</p>

  <?php if ($items === []): ?>
    <p class="bo-brief-empty"><?= $hasFilter ? 'Rien pour ce filtre.' : 'Rien n’est diffusé pour le moment. Écrivez le premier message avec « Nouveau message ».' ?></p>
  <?php endif; ?>

  <ol class="bo-brief-list bo-bc-list">
    <?php foreach ($items as $item):
        $imp = $item['importance'] ?? null;
        $canEdit = (isset($item['parts']['site']) && in_array('site', $allowedChannels, true))
            || (isset($item['parts']['note']) && in_array('phone', $allowedChannels, true));
        $canWithdraw = $item['state'] !== 'withdrawn' || isset($item['parts']['note']);
        foreach (array_keys($item['parts']) as $part) {
            if (isset($partChannel[$part]) && !in_array($partChannel[$part], $allowedChannels, true)) {
                $canWithdraw = false;
            }
        }
        $canWithdraw = $canWithdraw && in_array($item['state'], ['active', 'scheduled'], true);
        $dupChannels = array_values(array_intersect($item['channels'], BC::TYPE_CHANNELS[$item['type']] ?? []));
        $dup = ['type' => $item['type'], 'title' => $item['title'], 'body' => $item['body'], 'importance' => $imp ?? 'info', 'channels' => $dupChannels];
        $period = [];
        if (!empty($item['starts'])) {
            $period[] = 'dès le ' . $fmt($item['starts']);
        }
        if (!empty($item['ends'])) {
            $period[] = ($item['ends'] < time() ? 'fini le ' : 'jusqu’au ') . $fmt($item['ends']);
        }
        ?>
      <li class="bo-brief-row bo-bc-row<?= $imp !== null ? ' is-' . $h($imp) : '' ?>" id="bo-bc-<?= $h(str_replace(':', '-', $item['ref'])) ?>" tabindex="-1">
        <div class="bo-brief-row__t">
          <div class="bo-bc-row__tags">
            <span class="bo-brief-kind is-<?= $h($item['type']) ?>"><?= $h(BC::TYPES[$item['type']] ?? $item['type']) ?></span>
            <?php if ($imp !== null): ?><span class="bo-bc-imp-tag is-<?= $h($imp) ?>"><?= $h(BC::IMPORTANCE[$imp]) ?></span><?php endif; ?>
            <span class="ath-tag ath-tag--<?= $h($stateTone[$item['state']] ?? 'neut') ?>"><?= $h(BC::STATES[$item['state']] ?? $item['state']) ?></span>
            <?php foreach ($item['channels'] as $c): ?><span class="bo-bc-chip"><?= $h(BC::CHANNELS[$c] ?? $c) ?></span><?php endforeach; ?>
          </div>
          <b translate="no"><?= $h($item['title']) ?></b>
          <?php if ($item['body'] !== ''): ?><p translate="no"><?= $h(mb_strlen($item['body']) > 220 ? mb_substr($item['body'], 0, 220) . '…' : $item['body']) ?></p><?php endif; ?>
          <p class="bo-bc-meta">
            <span>Pour : <span translate="no"><?= $h($item['target']) ?></span></span>
            <?php if (!empty($item['ts'])): ?><span><?= isset($item['parts']['note']) && count($item['parts']) === 1 ? 'Notes modifiées le ' : 'Créé le ' ?><?= $h($fmt($item['ts'])) ?></span><?php endif; ?>
            <?php if ($period !== []): ?><span><?= $h(implode(', ', $period)) ?></span><?php endif; ?>
            <?php if (($item['author'] ?? '') !== ''): ?><span>Par <span translate="no"><?= $h($item['author']) ?></span></span><?php endif; ?>
          </p>
          <?php if ($item['receipts'] !== [] || $item['tracking'] !== []): ?>
            <p class="bo-bc-receipts"><?= $h(implode(' · ', array_merge($item['receipts'], $item['tracking']))) ?></p>
          <?php endif; ?>
        </div>
        <div class="bo-brief-row__a">
          <?php if ($canEdit): ?><?= $fold('Modifier', 'Modifier', $item['title'], $editForm($item)) ?><?php endif; ?>
          <?php if ($allowedChannels !== []): ?>
            <a class="ath-btn ath-btn--sm" href="<?= $h($bcBase . '?dupliquer=' . rawurlencode($item['ref']) . '#nouveau') ?>" data-bc-duplicate="<?= $h(json_encode($dup, JSON_UNESCAPED_UNICODE)) ?>">Dupliquer</a>
          <?php endif; ?>
          <?php if ($canWithdraw): ?><?= $fold('Retirer', 'Retirer la diffusion', $item['title'], $withdrawForm($item)) ?><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
