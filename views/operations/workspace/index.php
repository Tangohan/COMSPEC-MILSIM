<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $operations */
$operations = $operations ?? [];
$canPlan = !empty($canPlan);
$statusOptions = $statusOptions ?? [];
$classificationOptions = $classificationOptions ?? [];
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$opCount = count($operations);
$statusChip = static function (string $status): string {
    return match ($status) {
        'planned' => 'planned',
        'active' => 'active',
        'paused' => 'paused',
        'closed' => 'closed',
        default => 'draft',
    };
};
$classChip = static function (string $class): string {
    return match ($class) {
        'secret' => 'secret',
        'confidential' => 'confidential',
        'restricted' => 'restricted',
        default => 'draft',
    };
};
?>
<div class="ops-ws ops-ws--index">
    <div class="ops-ws__shell">
        <header class="ops-ws__head">
            <p class="ops-ws__kicker">Athena · Opérations</p>
            <h1>Espaces opérationnels</h1>
            <p class="ops-ws__lead">Chaque opération rassemble le plan, le renseignement, les ordres et la vue terrain. La carte ATAK affiche ce qui a été publié, pas le travail d’état-major encore en cours.</p>
        </header>

        <?php if ($flashOk !== ''): ?>
        <p class="ops-ws__flash ops-ws__flash--ok"><?= $h($flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr !== ''): ?>
        <p class="ops-ws__flash ops-ws__flash--err"><?= $h($flashErr) ?></p>
        <?php endif; ?>

        <div class="ops-ws__deck">
            <div class="ops-ws__main">
                <?php if ($operations === []): ?>
                <div class="ops-ws__empty-card" role="status">
                    <div class="ops-ws__empty-mark" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5h7.5A1.75 1.75 0 0 1 17.5 6.25v13.1L12 16.5l-5.5 2.85V6.25A1.75 1.75 0 0 1 8.25 4.5Z"/>
                            <path stroke-linecap="round" d="M9.5 8.5h5M9.5 11.25h5"/>
                        </svg>
                    </div>
                    <strong>Aucune opération n’est ouverte pour le moment</strong>
                    <?php if ($canPlan): ?>
                    <p>Ouvrez un espace pour y poser l’intention, le plan et les ordres. Tant que rien n’est publié, les opérateurs ne voient pas ce travail sur la carte.</p>
                    <?php else: ?>
                    <p>Lorsque le commandement ouvrira une opération, elle apparaîtra ici, avec son indicatif, son statut et la phase en cours.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="ops-ws__list-head">
                    <h2>Opérations ouvertes</h2>
                    <p class="ops-ws__count"><?= $opCount ?> opération<?= $opCount > 1 ? 's' : '' ?></p>
                </div>
                <ul class="ops-ws__list">
                    <?php foreach ($operations as $op):
                        $st = (string) ($op['status'] ?? 'draft');
                        $cl = (string) ($op['classification'] ?? 'restricted');
                    ?>
                    <li>
                        <a class="ops-ws__op" href="<?= $h(url('operations/' . $op['uuid'])) ?>">
                            <span class="ops-ws__op-code"><?= $h($op['code'] ?? '') ?></span>
                            <span class="ops-ws__op-name"><?= $h($op['name'] ?? '') ?></span>
                            <span class="ops-ws__op-badges">
                                <span class="ops-ws__chip ops-ws__chip--<?= $h($statusChip($st)) ?>"><?= $h($op['status_label'] ?? '') ?></span>
                                <span class="ops-ws__chip ops-ws__chip--<?= $h($classChip($cl)) ?>"><?= $h($op['classification_label'] ?? '') ?></span>
                                <span class="ops-ws__chip"><?= $h($op['phase_label'] ?? '') ?></span>
                            </span>
                            <span class="ops-ws__op-go">Ouvrir l’espace</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <?php if ($canPlan): ?>
            <aside class="ops-ws__aside">
                <form class="ops-ws__create" method="post" action="<?= $h(url('operations')) ?>" autocomplete="off">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <h2>Ouvrir une opération</h2>
                    <p class="ops-ws__create-lead">Donnez un nom lisible et un indicatif court, du type AEGIS. L’indicatif désigne l’opération, pas la communauté.</p>
                    <div class="ops-ws__grid">
                        <label class="ops-ws__field" for="ops-op-name">Nom
                            <input id="ops-op-name" type="text" name="name" required maxlength="191" placeholder="Opération Aegis" autocomplete="off">
                        </label>
                        <label class="ops-ws__field" for="ops-op-indicatif">Indicatif
                            <input id="ops-op-indicatif" type="text" name="indicatif" maxlength="12" placeholder="AEGIS" autocomplete="off" spellcheck="false" autocapitalize="characters" inputmode="text" aria-describedby="ops-op-indicatif-hint">
                            <span id="ops-op-indicatif-hint" class="ops-ws__field-hint">Trois à douze lettres, par exemple AEGIS. Pas le nom de la communauté.</span>
                        </label>
                        <label class="ops-ws__field" for="ops-op-class">Classification
                            <select id="ops-op-class" name="classification" class="bo-select">
                                <?php foreach ($classificationOptions as $opt): ?>
                                <option value="<?= $h($opt['value']) ?>"><?= $h($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="ops-ws__field" for="ops-op-status">Statut
                            <select id="ops-op-status" name="status" class="bo-select">
                                <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?= $h($opt['value']) ?>"><?= $h($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label class="ops-ws__field" for="ops-op-intent">Intention
                        <textarea id="ops-op-intent" name="description" rows="3" placeholder="Objet, théâtre, contrainte principale."></textarea>
                    </label>
                    <button type="submit" class="ops-ws__btn">Créer l’espace</button>
                </form>
            </aside>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function () {
  var name = document.getElementById('ops-op-name');
  var code = document.getElementById('ops-op-indicatif');
  if (!name || !code) return;
  var touched = false;
  function suggest(raw) {
    var s = String(raw || '').toUpperCase();
    try { s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (e) {}
    s = s.replace(/^(OPERATION|MISSION|OP|OPS)\b[\s\-:]*/i, '');
    var parts = s.split(/[^A-Z0-9]+/).filter(Boolean);
    var pick = parts.length ? parts[parts.length - 1] : '';
    if (pick.length > 12) pick = pick.slice(0, 12);
    return pick;
  }
  code.addEventListener('input', function () {
    touched = code.value.trim() !== '';
  });
  name.addEventListener('input', function () {
    if (touched) return;
    code.value = suggest(name.value);
  });
})();
</script>
