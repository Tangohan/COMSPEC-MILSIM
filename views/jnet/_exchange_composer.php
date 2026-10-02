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
    <p class="jn-composer__title-line">Nouvel échange</p>
    <div class="jn-composer__grid">
        <label class="jn-field">
            <span class="jn-field__label">Type</span>
            <select name="kind" class="jn-input">
                <?php foreach ($kinds as $key => $kindLabel): ?>
                    <option value="<?= $h($key) ?>"<?= $draftKind === $key ? ' selected' : '' ?>><?= $h($kindLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="jn-field jn-field--wide">
            <span class="jn-field__label">Titre</span>
            <input name="title" class="jn-input" maxlength="160" required
                   placeholder="Ex. Point de regroupement déplacé" value="<?= $h((string) ($draft['title'] ?? '')) ?>">
        </label>
    </div>
    <label class="jn-field">
        <span class="jn-field__label">Détail <small>(facultatif)</small></span>
        <textarea name="body" class="jn-input" rows="3" maxlength="4000"
                  placeholder="Consignes, contexte, position…"><?= $h((string) ($draft['body'] ?? '')) ?></textarea>
    </label>
    <details class="jn-composer__more">
        <summary>Joindre un lien vers une page Athena (facultatif)</summary>
        <label class="sr-only" for="<?= $uid ?>-link">Lien</label>
        <input id="<?= $uid ?>-link" name="link" class="jn-input" placeholder="Collez l’adresse d’un document, d’une fiche, de la carte…" value="<?= $h((string) ($draft['link'] ?? '')) ?>">
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
        <p class="jn-hint">Seuls les destinataires cochés (et leur chaîne de commandement) verront l’échange. Un ordre leur demande un accusé de réception.</p>
        <button type="submit" class="jn-btn jn-btn--primary" data-jn-busy="Publication…">Publier</button>
    </div>
</form>
