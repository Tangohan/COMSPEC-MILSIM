<?php
declare(strict_types=1);
/**
 * Composeur d’échange depuis un espace, replié derrière un bouton « Publier ».
 * @var array<string, mixed> $space
 * @var list<array{id:int,label:string,relation:string}> $postTargets
 * @var array<string, string> $exchangeKinds
 * @var string|null $composeLabel
 * @var string|null $composeKind type présélectionné (ex. compte_rendu)
 * @var int|null $composeTarget destinataire coché d'office (par défaut : l'espace lui-même)
 * @var string|null $composeSuffix suffixe d'identifiant quand la page porte plusieurs composeurs
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$spaceId = (int) ($space['id'] ?? 0);
$targets = is_array($postTargets ?? null) ? $postTargets : [];
$kinds = is_array($exchangeKinds ?? null) ? $exchangeKinds : [];
$draft = \App\Core\Session::getFlash('jnet_exchange_draft');
$draft = is_array($draft) ? $draft : [];
$draftKind = (string) ($draft['kind'] ?? ($composeKind ?? 'info'));
$defaultTarget = (int) ($composeTarget ?? $spaceId);
$uid = 'jnc' . $spaceId . (string) ($composeSuffix ?? '');
$relationHelp = [
    'Cet espace' => 'les membres de l’unité et leur encadrement',
    'Échelon supérieur' => 'remonte à l’unité du dessus',
    'Subordonné' => 'descend vers cette sous-unité',
];
?>
<details class="jn-compose"<?= $draft !== [] && ($composeSuffix ?? '') === '' ? ' open' : '' ?>>
    <summary class="jn-compose__summary">
        <span class="jn-compose__plus" aria-hidden="true">+</span>
        <?= $h((string) ($composeLabel ?? 'Publier un échange')) ?>
    </summary>
    <form class="jn-composer" method="post" action="<?= $h(url('jnet/u/' . $spaceId . '/echanges')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <div class="jn-composer__row">
        <div class="jn-field">
            <label for="<?= $uid ?>-kind">Type</label>
            <select id="<?= $uid ?>-kind" name="kind" class="jn-input jn-input--select">
                <?php foreach ($kinds as $kindKey => $kindName): ?>
                    <option value="<?= $h($kindKey) ?>"<?= $draftKind === $kindKey ? ' selected' : '' ?>><?= $h($kindName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jn-field">
            <label for="<?= $uid ?>-title">Titre</label>
            <input id="<?= $uid ?>-title" name="title" class="jn-input" maxlength="160" required
                   placeholder="Ce que les destinataires doivent retenir" value="<?= $h((string) ($draft['title'] ?? '')) ?>">
        </div>
        </div>
        <div class="jn-field">
            <label for="<?= $uid ?>-body">Détail <small>(facultatif)</small></label>
            <textarea id="<?= $uid ?>-body" name="body" class="jn-input" rows="3" maxlength="4000"
                      placeholder="Consignes, horaires, position…"><?= $h((string) ($draft['body'] ?? '')) ?></textarea>
        </div>
        <div class="jn-cite" data-jn-cite data-jn-cite-target="<?= $uid ?>-body" data-jn-cite-search="<?= $h(url('jnet/liens/recherche')) ?>" data-jn-cite-space="<?= $spaceId ?>">
            <button type="button" class="jn-btn jn-btn--ghost" data-jn-cite-open aria-expanded="false" aria-controls="<?= $uid ?>-cite">Citer un élément SSE</button>
            <p class="jn-hint">Ajoute au détail une citation (référence seulement) : chaque lecteur la voit avec ses propres droits SSE, sinon « Élément restreint ».</p>
            <div class="jn-cite__pop" id="<?= $uid ?>-cite" hidden>
                <label class="jn-cite__q">
                    <span>Référence ou nom</span>
                    <input type="search" class="jn-input" data-jn-cite-q maxlength="80" autocomplete="off" placeholder="IDN-00012, CTRL-3, Karim…">
                </label>
                <ul class="jn-cite__list" data-jn-cite-list role="listbox" aria-label="Résultats"></ul>
                <span hidden data-jn-cite-msg="none">Aucun résultat parmi ce que vous pouvez lire.</span>
                <span hidden data-jn-cite-msg="short">Tapez au moins deux caractères.</span>
            </div>
        </div>
        <div class="jn-field">
            <label for="<?= $uid ?>-link">Lien Athena <small>(facultatif)</small></label>
            <input id="<?= $uid ?>-link" name="link" class="jn-input" placeholder="Collez l’adresse d’une page Athena : document, fiche, carte…" value="<?= $h((string) ($draft['link'] ?? '')) ?>">
        </div>
        <fieldset class="jn-composer__targets">
            <legend>Diffuser à</legend>
            <?php foreach ($targets as $t): ?>
                <?php $rel = (string) $t['relation']; ?>
                <label class="jn-check">
                    <input type="checkbox" name="targets[]" value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $defaultTarget ? ' checked' : '' ?>>
                    <span>
                        <b><?= $h((string) $t['label']) ?></b>
                        <small><?= $h($rel) ?><?= isset($relationHelp[$rel]) ? ' — ' . $h($relationHelp[$rel]) : '' ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="jn-composer__actions">
            <p class="jn-hint">Un <b>ordre</b> demande un accusé de lecture à chaque destinataire.</p>
            <button type="submit" class="jn-btn jn-btn--primary" data-loading="Publication…">Publier</button>
        </div>
    </form>
</details>
<?php if (empty($GLOBALS['jnSseCiteScript'])): $GLOBALS['jnSseCiteScript'] = true; ?>
<script defer src="<?= $h(asset_url('assets/js/jnet-sse-cite.js')) ?>"></script>
<?php endif; ?>
