<?php
declare(strict_types=1);
/**
 * Contenu du tiroir Cerbère (fragment HTML chargé à l'ouverture). Une section ou un onglet par sujet, UNE ligne de description
 * sous chacun ; les décisions passent par les formulaires des propositions existantes (droit et jeton CSRF rejugés côté serveur).
 * Les textes saisis (titres, noms) sont posés en translate="no" : ils ne se traduisent jamais.
 *
 * @var array<string, mixed>|null $w contenu (SseCerbereWidget::panel) ou null
 * @var string $message
 * @var string $csrf
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$w = $w ?? null;
$csrf = (string) ($csrf ?? '');
if ($w === null): ?>
<div class="cw-content" data-cw-count="0" data-cw-tone="neutral">
    <p class="cw-empty"><?= $h($message !== '' ? $message : 'Cerbère n’a rien à montrer ici.') ?></p>
</div>
<?php return; endif;
$tabs = $w['tabs'];
$tone = static fn (string $t): string => in_array($t, ['warn', 'bad'], true) ? $t : 'neutral';
$ctx = $w['ctx'];
$row = static function (array $r, array $w, string $csrf) use ($h, $tone): void { ?>
    <li class="cw-row cw-row--<?= $h($tone((string) $r['tone'])) ?>">
        <div class="cw-row__main">
            <?php if ($r['href'] !== ''): ?><a class="cw-row__title" href="<?= $h($r['href']) ?>" translate="no"><?= $h($r['title']) ?></a><?php else: ?><span class="cw-row__title" translate="no"><?= $h($r['title']) ?></span><?php endif; ?>
            <p class="cw-row__meta"><?= $h($r['what'] !== '' ? $r['what'] : $r['meta']) ?><?php if ($r['when'] !== ''): ?> · <?= $h($r['when']) ?><?php endif; ?></p>
            <?php if ($r['reason'] !== ''): ?><p class="cw-row__why" translate="no"><?= $h($r['reason']) ?></p><?php endif; ?>
        </div>
        <div class="cw-row__acts">
            <?php if ((int) $r['suggestion'] > 0 && !empty($w['canDecide'])): ?>
                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $r['suggestion'] . '/valider')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <?php if ((int) $r['case_id'] > 0): ?><input type="hidden" name="case_id" value="<?= (int) $r['case_id'] ?>"><?php endif; ?>
                    <button type="submit" class="cw-btn cw-btn--ok">Accepter</button>
                </form>
                <form method="post" action="<?= $h(url('atak/sse/rapprochements/' . (int) $r['suggestion'] . '/rejeter')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                    <?php if ((int) $r['case_id'] > 0): ?><input type="hidden" name="case_id" value="<?= (int) $r['case_id'] ?>"><?php endif; ?>
                    <button type="submit" class="cw-btn">Écarter</button>
                </form>
            <?php endif; ?>
            <?php if ($r['href'] !== ''): ?><a class="cw-btn" href="<?= $h($r['href']) ?>">Voir</a><?php endif; ?>
        </div>
    </li>
<?php };
$linkRow = static function (array $r) use ($h): void { ?>
    <li class="cw-row">
        <div class="cw-row__main">
            <p class="cw-row__line"><?= $h($r['relation']) ?> <span class="cw-dim"><?= $h($r['type_label']) ?></span>
                <?php if ($r['href'] !== ''): ?><a class="cw-row__title" href="<?= $h($r['href']) ?>" translate="no"><?= $h($r['title']) ?></a><?php else: ?><b class="cw-row__title" translate="no"><?= $h($r['title']) ?></b><?php endif; ?></p>
            <?php if ($r['summary'] !== ''): ?><p class="cw-row__why" translate="no"><?= $h($r['summary']) ?></p><?php endif; ?>
            <?php if ($r['reason'] !== ''): ?><p class="cw-row__why" translate="no"><?= $h($r['reason']) ?></p><?php endif; ?>
            <p class="cw-row__meta"><?php if ($r['rule_label'] !== ''): ?><span class="cw-chip" title="<?= $h($r['rule']) ?>"><?= $h($r['rule_label']) ?></span> <?php endif; ?><?= $h($r['origin']) ?><?php if ($r['actor'] !== '' && $r['rule'] === ''): ?> · <span translate="no"><?= $h($r['actor']) ?></span><?php endif; ?><?php if ($r['when'] !== ''): ?> · <?= $h($r['when']) ?><?php endif; ?></p>
        </div>
    </li>
<?php };
?>
<div class="cw-content" data-cw-count="<?= (int) $w['badge']['count'] ?>" data-cw-tone="<?= $h($w['badge']['tone']) ?>" data-cw-label="<?= $h($w['badge']['label']) ?>">
    <?php if (!empty($w['degraded'])): ?>
        <p class="cw-note">Une partie de la boîte « À trier » est indisponible : les nombres peuvent être incomplets.</p>
    <?php endif; ?>
    <?php if ($w['subject'] !== null): ?>
        <p class="cw-subject"><b translate="no"><?= $h($w['subject']['title']) ?></b> <span class="cw-dim">(<?= $h($w['subject']['type_label']) ?>)</span></p>
    <?php endif; ?>
    <?php if (count($tabs) > 1): ?>
        <div class="cw-tabs" role="tablist" aria-label="Sections de Cerbère">
            <?php foreach ($tabs as $i => $t): ?>
                <button type="button" class="cw-tab" role="tab" id="cw-tab-<?= $h($t['id']) ?>" aria-controls="cw-pane-<?= $h($t['id']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>" data-cw-tab="<?= $h($t['id']) ?>">
                    <?= $h($t['label']) ?><?php if ((int) $t['count'] > 0): ?> <span class="cw-count"><?= (int) $t['count'] ?></span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php foreach ($tabs as $i => $t): ?>
        <section class="cw-pane" role="<?= count($tabs) > 1 ? 'tabpanel' : 'region' ?>" id="cw-pane-<?= $h($t['id']) ?>" aria-labelledby="cw-tab-<?= $h($t['id']) ?>" data-cw-pane="<?= $h($t['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
            <?php if (count($tabs) === 1): ?><h3 class="cw-h3" id="cw-tab-<?= $h($t['id']) ?>"><?= $h($t['label']) ?></h3><?php endif; ?>
            <p class="cw-desc"><?= $h($t['desc']) ?></p>

            <?php if ($t['id'] === 'done'): ?>
                <?php if ($t['groups'] === []): ?>
                    <p class="cw-empty">Cerbère n’a encore rien rattaché, validé ni détecté sur cet élément.</p>
                <?php endif; ?>
                <?php foreach ($t['groups'] as $g): ?>
                    <h3 class="cw-h3"><?= $h($g['treatment']) ?> <span class="cw-count"><?= count($g['rows']) ?></span></h3>
                    <ul class="cw-list"><?php foreach ($g['rows'] as $r) { $linkRow($r); } ?></ul>
                <?php endforeach; ?>

            <?php elseif ($t['id'] === 'origin'): ?>
                <?php foreach ($t['chains'] as $c): ?><p class="cw-chain" translate="no"><?= $h($c) ?></p><?php endforeach; ?>
                <?php if ($t['rows'] === []): ?><p class="cw-empty">Aucun lien n’est encore posé sur cet élément.</p><?php endif; ?>
                <ul class="cw-list"><?php foreach ($t['rows'] as $r) { $linkRow($r); } ?></ul>
                <?php if (!empty($t['truncated'])): ?><p class="cw-note">Le parcours est limité : la page de l’élément montre la suite.</p><?php endif; ?>

            <?php elseif ($t['id'] === 'auto'): ?>
                <?php if ($t['treatments'] === []): ?><p class="cw-empty">L’état des Cerbères n’est pas lisible pour le moment.</p><?php endif; ?>
                <ul class="cw-list">
                <?php foreach ($t['treatments'] as $tr): ?>
                    <li class="cw-row">
                        <div class="cw-row__main">
                            <p class="cw-row__line"><b><?= $h($tr['label']) ?></b> <span class="cw-chip cw-chip--<?= $tr['active'] ? 'ok' : 'off' ?>"><?= $tr['active'] ? 'Actif' : 'À activer' ?></span></p>
                            <p class="cw-row__why"><?= $h($tr['does']) ?></p>
                            <?php if (!$tr['active'] && $tr['why'] !== ''): ?><p class="cw-row__why"><?= $h($tr['why']) ?></p><?php endif; ?>
                            <p class="cw-row__meta"><b><?= (int) $tr['total'] ?></b> <span><?= (int) $tr['total'] > 1 ? 'liens posés' : 'lien posé' ?></span><?php if ($tr['last'] !== ''): ?> <span>·</span> <span>dernier le</span> <b><?= $h($tr['last']) ?></b><?php endif; ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
                <p class="cw-more"><a class="cw-btn" href="<?= $h($t['link']) ?>">Ouvrir la page Cerbères</a></p>

            <?php else: /* todo, overview */ ?>
                <?php if (!empty($t['sources'])): ?>
                    <ul class="cw-sources">
                        <?php foreach ($t['sources'] as $s): ?>
                            <li><a class="cw-source cw-source--<?= $h($tone((string) $s['tone'])) ?>" href="<?= $h($s['href']) ?>"><span><?= $h($s['label']) ?></span><b class="cw-count"><?= (int) $s['count'] ?></b></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($t['rows'] === []): ?>
                    <p class="cw-empty"><?= $h($t['id'] === 'todo' ? 'Rien n’attend de décision ici : Cerbère n’a rien à signaler.' : ((int) ($t['total'] ?? 0) > 0 ? 'Cerbère signale des éléments dans le bureau : ouvrez « À trier ».' : 'Rien n’attend de décision dans le bureau.')) ?></p>
                <?php else: ?>
                    <ul class="cw-list"><?php foreach ($t['rows'] as $r) { $row($r, $w, $csrf); } ?></ul>
                <?php endif; ?>
                <?php if (!empty($t['shortcuts'])): ?>
                    <h3 class="cw-h3">Pages utiles</h3>
                    <ul class="cw-list">
                        <?php foreach ($t['shortcuts'] as $s): ?>
                            <li class="cw-row"><div class="cw-row__main"><a class="cw-row__title" href="<?= $h($s['href']) ?>"><?= $h($s['label']) ?></a><p class="cw-row__why"><?= $h($s['hint']) ?></p></div></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (($ctx['kind'] ?? '') !== 'triage'): ?><p class="cw-more"><a class="cw-btn cw-btn--ok" href="<?= $h($t['all']) ?>">Ouvrir « À trier »<?= $t['id'] === 'todo' && ($ctx['kind'] ?? '') === 'list' ? ' sur ce contexte' : '' ?></a></p><?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
