<?php
declare(strict_types=1);
/**
 * Composeur d’échange depuis un espace.
 * @var array<string, mixed> $space
 * @var list<array{id:int,label:string,relation:string}> $postTargets
 * @var array<string, string> $exchangeKinds
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$spaceId = (int) ($space['id'] ?? 0);
$targets = is_array($postTargets ?? null) ? $postTargets : [];
$kinds = is_array($exchangeKinds ?? null) ? $exchangeKinds : [];
$draft = \App\Core\Session::getFlash('jnet_exchange_draft');
$draft = is_array($draft) ? $draft : [];
$draftKind = (string) ($draft['kind'] ?? 'info');
$uid = 'jnc' . $spaceId;
?>
<form class="jn-composer" method="post" action="<?= $h(url('jnet/u/' . $spaceId . '/echanges')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <div class="jn-composer__row">
        <label class="sr-only" for="<?= $uid ?>-kind">Type d’échange</label>
        <select id="<?= $uid ?>-kind" name="kind" class="jn-input jn-input--select">
            <?php foreach ($kinds as $key => $label): ?>
                <option value="<?= $h($key) ?>"<?= $draftKind === $key ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="<?= $uid ?>-title">Titre</label>
        <input id="<?= $uid ?>-title" name="title" class="jn-input jn-composer__title" maxlength="160" required
               placeholder="Titre : ce que les destinataires doivent savoir" value="<?= $h((string) ($draft['title'] ?? '')) ?>">
    </div>
    <label class="sr-only" for="<?= $uid ?>-body">Détail</label>
    <textarea id="<?= $uid ?>-body" name="body" class="jn-input" rows="3" maxlength="4000"
              placeholder="Détail, consignes, position…"><?= $h((string) ($draft['body'] ?? '')) ?></textarea>
    <details class="jn-composer__more">
        <summary>Joindre un lien Athena</summary>
        <label class="sr-only" for="<?= $uid ?>-link">Lien</label>
        <input id="<?= $uid ?>-link" name="link" class="jn-input" placeholder="/documents/42, /atak…" value="<?= $h((string) ($draft['link'] ?? '')) ?>">
    </details>
    <fieldset class="jn-composer__targets">
        <legend>Diffuser à</legend>
        <?php foreach ($targets as $t): ?>
            <label class="jn-check">
                <input type="checkbox" name="targets[]" value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $spaceId ? ' checked' : '' ?>>
                <span><?= $h((string) $t['label']) ?> <small><?= $h((string) $t['relation']) ?></small></span>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <div class="jn-composer__actions">
        <p class="jn-hint">Un ordre demande un accusé de lecture aux destinataires.</p>
        <button type="submit" class="jn-btn jn-btn--primary">Publier</button>
    </div>
</form>
