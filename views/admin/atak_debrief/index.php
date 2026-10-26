<?php
declare(strict_types=1);

/**
 * Back-office — suivi des CR de fin d’opération (/back-office/atak/suivi-cr).
 * Liste dense d’une demande ou d’une session : une ligne par joueur (état du CR, dernière synchro de l’ATAK, remontées),
 * réponses dépliables, et les formulaires « Imposer un CR » et « Réglages du jeu ».
 *
 * @var bool $dbfReady
 * @var list<array<string, mixed>> $dbfDebriefs
 * @var list<array<string, mixed>> $dbfOperations
 * @var array<string, mixed>|null $dbfSelected
 * @var string $dbfOp
 * @var list<array<string, mixed>> $dbfRows
 * @var list<array<string, mixed>> $dbfAllRows
 * @var array<string, int> $dbfCounts
 * @var string $dbfFilter
 * @var array<string, mixed>|null $dbfSettings
 * @var list<array<string, mixed>> $dbfTemplates
 * @var list<array<string, mixed>> $dbfAars
 */
use App\Controllers\Admin\AdminAtakDebriefController as Ctl;
use App\Repositories\AtakDebriefRepository as Repo;
use App\Support\AtakDebriefSpec as Spec;
use App\Support\AtakMissionKey;
use App\Support\UtcDateTime;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/suivi-cr');
$d = $dbfSelected ?? null;
$op = (string) ($dbfOp ?? '');
$rows = $dbfRows ?? [];
$all = $dbfAllRows ?? [];
$counts = $dbfCounts ?? [];
$filter = $dbfFilter ?? 'tous';
$canManage = !empty($dbfCanManage);
$settings = $dbfSettings ?? ['default_mode' => 'fill', 'template_id' => 0, 'intro' => '', 'zeus_allowed' => true, 'authorized_uids' => []];
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$fmt = static function (mixed $raw, string $format = 'd/m H:i'): string {
    $ts = UtcDateTime::timestamp($raw);

    return $ts !== null ? date($format, $ts) : '—';
};
$ago = static function (?int $sec): string {
    return match (true) {
        $sec === null => 'jamais',
        $sec < 90 => 'à l’instant',
        $sec < 3600 => 'il y a ' . intdiv($sec, 60) . ' min',
        $sec < 86400 => 'il y a ' . intdiv($sec, 3600) . ' h',
        default => 'il y a ' . intdiv($sec, 86400) . ' j',
    };
};
$stateLabel = static fn (string $s): string => $s === 'none' ? 'Pas demandé' : (Repo::STATES[$s] ?? $s);
$tone = static fn (string $s): string => match ($s) {
    'submitted', 'read' => 'done',
    'opened', 'delivered' => 'run',
    'sent' => 'wait',
    'expired', 'cancelled' => 'bad',
    default => 'none',
};
$qs = static function (array $over = []) use ($d, $op, $filter): string {
    $q = array_filter(array_merge(['cr' => $d !== null ? (int) $d['id'] : '', 'op' => $d === null ? $op : '', 'filtre' => $filter !== 'tous' ? $filter : ''], $over), static fn ($v) => $v !== '' && $v !== null);

    return $q === [] ? '' : '?' . http_build_query($q);
};
$total = count($all);
$done = 0;
$wait = 0;
$online = 0;
$lastSync = null;
foreach ($all as $r) {
    $done += in_array($r['state'], ['submitted', 'read'], true) ? 1 : 0;
    $wait += in_array($r['state'], ['sent', 'delivered', 'opened'], true) ? 1 : 0;
    $online += !empty($r['online']) ? 1 : 0;
    if ($r['sync_age'] !== null && ($lastSync === null || $r['sync_age'] < $lastSync)) {
        $lastSync = $r['sync_age'];
    }
}
$asked = count(array_filter($all, static fn (array $r): bool => $r['state'] !== 'none'));
$mode = (string) ($d['mode'] ?? $settings['default_mode']);
$questions = $d !== null && $d['questions'] !== [] ? $d['questions'] : Spec::BUILTIN;
$openPanel = (string) ($dbfOpen ?? '');
?>
<div class="bo-aar bo-dbf" id="bo-dbf">

<?php if (empty($dbfReady)): ?>
  <section class="ath-panel"><p class="ath-panel__lead">Les tables du suivi des CR ne sont pas encore en place : lancez <code>run-migrations.php</code> (étape <code>atak_debrief</code>).</p></section>
<?php endif; ?>

  <div class="ath-kpi-grid bo-aar-kpis">
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Joueurs</p>
      <p class="ath-kpi__value"><?= $total ?></p>
      <p class="ath-kpi__note"><?= $d !== null ? $asked . ' visé(s) par la demande' : ($op !== '' ? 'de la session' : 'choisissez une opération') ?></p>
    </article>
    <article class="ath-card ath-kpi<?= $d !== null && $done < $total ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label"><?= $mode === 'read' ? 'CR lus' : 'CR remis' ?></p>
      <p class="ath-kpi__value"><?= $done ?> <small>/ <?= $total ?></small></p>
      <p class="ath-kpi__note"><?= $total > 0 ? (int) round(100 * $done / $total) . ' % de l’effectif' : '—' ?></p>
    </article>
    <article class="ath-card ath-kpi<?= $wait > 0 ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label">En attente</p>
      <p class="ath-kpi__value"><?= $wait ?></p>
      <p class="ath-kpi__note"><?= (int) ($counts['non_recu'] ?? 0) ?> pas encore reçu(s)</p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">ATAK en ligne</p>
      <p class="ath-kpi__value"><?= $online ?> <small>/ <?= $total ?></small></p>
      <p class="ath-kpi__note">synchro depuis moins de <?= (int) (Ctl::ONLINE_SECONDS / 60) ?> min</p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Dernière synchro</p>
      <p class="ath-kpi__value bo-dbf-kpi-small"><?= $h($ago($lastSync)) ?></p>
      <p class="ath-kpi__note">le plus récent des ATAK listés</p>
    </article>
  </div>

  <form class="bo-dbf-pick" method="get" action="<?= $h($base) ?>">
    <label class="bo-dbf-pick__label" for="dbf-pick">Opération</label>
    <select id="dbf-pick" class="ath-table-toolbar__filter bo-dbf-pick__select" name="sel" onchange="var v=this.value;location.href='<?= $h($base) ?>'+(v?('?'+v):'');">
      <?php if (($dbfDebriefs ?? []) === [] && ($dbfOperations ?? []) === []): ?><option value="">Aucune opération pour l’instant</option><?php endif; ?>
      <?php if (($dbfDebriefs ?? []) !== []): ?>
        <optgroup label="CR demandés">
          <?php foreach ($dbfDebriefs as $x): ?>
            <option value="cr=<?= (int) $x['id'] ?>"<?= $d !== null && (int) $d['id'] === (int) $x['id'] ? ' selected' : '' ?>><?= $h($fmt($x['created_at'], 'd/m/y')) ?> · <?= $h($x['label']) ?> · <?= (int) ($x['mode'] === 'read' ? $x['n_read'] : $x['n_submitted']) ?>/<?= (int) $x['n_total'] ?><?= !empty($x['closed_at']) ? ' · clôturé' : '' ?><?= $x['source'] === 'game' ? ' · en jeu' : '' ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
      <?php if (($dbfOperations ?? []) !== []): ?>
        <optgroup label="Sessions jouées">
          <?php foreach ($dbfOperations as $o): ?>
            <option value="op=<?= $h(rawurlencode($o['mission_key'])) ?>"<?= $d === null && $op === $o['mission_key'] ? ' selected' : '' ?>><?= $h($fmt($o['day'] . ' 00:00:00', 'd/m/y')) ?> · <?= $h($o['label']) ?> · <?= (int) $o['players'] ?> joueur(s)</option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
    </select>
    <span class="bo-aar-tabs__spacer"></span>
    <a class="ath-btn" href="<?= $h(url('back-office/atak/comptes-rendus')) ?>">Comptes rendus post-op</a>
    <?php if ($canManage): ?>
      <a class="ath-btn" href="#dbf-reglages" data-dbf-open="dbf-reglages">Réglages du jeu</a>
      <a class="ath-btn ath-btn--solid" href="#dbf-imposer" data-dbf-open="dbf-imposer">Imposer un CR</a>
    <?php endif; ?>
  </form>

  <?php if ($d !== null): ?>
  <section class="bo-dbf-head">
    <div class="bo-dbf-head__main">
      <span class="ath-tag ath-tag--<?= $mode === 'read' ? 'info' : 'warn' ?>"><?= $h(Spec::MODES[$mode] ?? $mode) ?></span>
      <h2 class="bo-dbf-head__title"><?= $h($d['label']) ?></h2>
      <?php
        $by = trim((string) ($d['created_by_label'] ?? ''));
        $bits = [($d['source'] === 'game' ? 'Imposé en jeu' : 'Imposé depuis le site') . ($by !== '' ? ' par ' . $by : ''), 'le ' . $fmt($d['created_at'], 'd/m/Y H:i')];
        if ($d['mission_label'] !== '') {
            $bits[] = 'session ' . $d['mission_label'];
        }
        $bits[] = !empty($d['closed_at']) ? 'clôturé le ' . $fmt($d['closed_at'], 'd/m/Y H:i') : (!empty($d['expires_at']) ? 'visible jusqu’au ' . $fmt($d['expires_at'], 'd/m H:i') : '');
        if ($mode === 'fill') {
            $bits[] = $d['questions'] !== [] ? count($d['questions']) . ' question(s) du modèle' : 'questionnaire intégré à l’ATAK';
        }
      ?>
      <p class="bo-dbf-head__sub"><?= $h(implode(' · ', array_filter($bits))) ?></p>
    </div>
    <div class="bo-dbf-head__actions">
      <a class="ath-btn" href="<?= $h($base . '/' . (int) $d['id'] . '/export.csv') ?>">Exporter (Excel)</a>
      <?php if ($canManage && empty($d['closed_at']) && $d['source'] === 'site'): ?>
        <form method="post" action="<?= $h($base . '/' . (int) $d['id'] . '/relancer') ?>"><?= $csrf ?><button class="ath-btn" type="submit">Relancer les retardataires</button></form>
      <?php endif; ?>
      <?php if ($canManage && empty($d['closed_at'])): ?>
        <form method="post" action="<?= $h($base . '/' . (int) $d['id'] . '/cloturer') ?>" onsubmit="return confirm('Clôturer ce CR ? Il ne s’affichera plus sur les téléphones qui ne l’ont pas encore reçu.');"><?= $csrf ?><button class="ath-btn bo-aar-danger" type="submit">Clôturer</button></form>
      <?php endif; ?>
    </div>
  </section>
  <?php elseif ($op !== ''): ?>
  <section class="bo-dbf-head">
    <div class="bo-dbf-head__main">
      <span class="ath-tag ath-tag--neut">Session</span>
      <h2 class="bo-dbf-head__title"><?= $h(AtakMissionKey::label($op)) ?></h2>
      <p class="bo-dbf-head__sub">Aucun CR n’a encore été imposé pour cette session : la liste montre les joueurs qui y ont joué et leur ATAK.</p>
    </div>
  </section>
  <?php endif; ?>

  <div class="bo-aar-seg" role="group" aria-label="Filtre">
    <?php foreach (Ctl::FILTERS as $code => $label): ?>
      <a href="<?= $h($base . $qs(['filtre' => $code === 'tous' ? '' : $code])) ?>" class="bo-aar-seg__btn<?= $filter === $code ? ' is-active' : '' ?>"><?php if ($code !== 'tous'): ?><span class="bo-aar-dot bo-aar-dot--<?= ['attente' => 'warn', 'remis' => 'ok', 'non_recu' => 'info', 'hors_ligne' => 'bad'][$code] ?>"></span><?php endif; ?><?= $h($label) ?> <b><?= (int) ($counts[$code] ?? 0) ?></b></a>
    <?php endforeach; ?>
  </div>

  <section class="bo-dbf-grid" aria-label="Joueurs">
    <header class="bo-dbf-grid__bar">
      <span class="bo-dbf-grid__title">CR de fin d’opération</span>
      <input class="bo-dbf-grid__search" type="search" placeholder="Filtrer par joueur ou rôle…" aria-label="Filtrer" data-dbf-search>
    </header>
    <?php if ($rows === []): ?>
      <div class="bo-aar-empty"><p class="ath-panel__lead"><?= $all === []
          ? ($d === null && $op === '' ? 'Aucune opération à suivre pour l’instant. Imposez un CR aux joueurs dont l’ATAK est en ligne, ou attendez qu’une session remonte ses joueurs.' : 'Aucun joueur pour l’instant.')
          : 'Aucun joueur dans ce filtre. <a href="' . $h($base . $qs(['filtre' => ''])) . '">Tout afficher</a>' ?></p></div>
    <?php else: ?>
    <div class="bo-dbf-grid__wrap">
      <table class="bo-dbf-table">
        <thead><tr>
          <th class="bo-dbf-c-cr" title="CR remis ou lu">CR</th>
          <th>Joueur</th><th>Rôle</th><th>ATAK</th><th>Dernière synchro</th><th>État du CR</th><th>Affiché</th><th><?= $mode === 'read' ? 'Lu le' : 'Remis le' ?></th>
          <th class="bo-dbf-c-num" title="Remontées envoyées pendant la session (hors CR de fin d’opération)">Remontées</th><th class="bo-dbf-c-act"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
            $t = $tone($r['state']);
            $isDone = $t === 'done';
            $hasAnswers = array_filter($r['answers'], static fn ($v): bool => trim((string) $v) !== '') !== [];
            ?>
          <tr class="bo-dbf-row is-<?= $t ?>" data-dbf-text="<?= $h(mb_strtolower($r['name'] . ' ' . $r['role'])) ?>">
            <td class="bo-dbf-c-cr"><span class="bo-dbf-box<?= $isDone ? ' is-on' : '' ?>" role="img" aria-label="<?= $isDone ? 'CR fait' : 'CR pas fait' ?>"><?= $isDone ? '✓' : '' ?></span></td>
            <td class="bo-dbf-name"><?= $h($r['name']) ?><?php if ($r['steam'] !== '' && (int) $r['user_id'] < 1): ?> <span class="bo-dbf-sub">sans compte</span><?php endif; ?></td>
            <td><?= $h($r['role'] !== '' ? $r['role'] : '—') ?></td>
            <td><span class="bo-dbf-net<?= !empty($r['online']) ? ' is-on' : '' ?>"><?= !empty($r['online']) ? 'En ligne' : ($r['sync_at'] !== '' ? 'Hors ligne' : 'Aucun ATAK') ?></span><?php if (($r['mod_version'] ?? '') !== ''): ?> <span class="bo-dbf-sub"><?= $h($r['mod_version']) ?></span><?php endif; ?></td>
            <td class="bo-dbf-mono" title="<?= $h($fmt($r['sync_at'], 'd/m/Y H:i:s')) ?>"><?= $h($ago($r['sync_age'])) ?></td>
            <td class="bo-dbf-state"><?= $h($stateLabel($r['state'])) ?></td>
            <td class="bo-dbf-mono"><?= $h($fmt($r['seen_at'] !== '' ? $r['seen_at'] : null)) ?></td>
            <td class="bo-dbf-mono"><?= $h($fmt($r['done_at'] !== '' ? $r['done_at'] : null)) ?></td>
            <td class="bo-dbf-c-num"><?= (int) $r['reports'] > 0 ? '<a href="' . $h(url('back-office/remontees')) . '">' . (int) $r['reports'] . '</a>' : '0' ?></td>
            <td class="bo-dbf-c-act"><?php if ($hasAnswers): ?><button type="button" class="bo-dbf-more" aria-expanded="false" aria-controls="dbf-ans-<?= $i ?>" title="Voir les réponses">⋮</button><?php endif; ?></td>
          </tr>
          <?php if ($hasAnswers): ?>
          <tr class="bo-dbf-answers" id="dbf-ans-<?= $i ?>" hidden>
            <td></td>
            <td colspan="9">
              <dl class="bo-dbf-qa">
                <?php foreach (Spec::present($d !== null ? $d['questions'] : [], array_map('strval', $r['answers'])) as $qa): if ($qa['value'] === '') { continue; } ?>
                  <div><dt><?= $h($qa['label']) ?></dt><dd><?= nl2br($h($qa['value'])) ?></dd></div>
                <?php endforeach; ?>
              </dl>
              <?php if ((int) $r['report_id'] > 0): ?><p class="bo-dbf-sub">Remonté aussi dans les <a href="<?= $h(url('back-office/remontees')) ?>">remontées</a> (type « Fin d’opération »).</p><?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <footer class="bo-dbf-grid__foot"><span data-dbf-count><?= count($rows) ?></span> / <?= $total ?> joueur(s)</footer>
  </section>

<?php if ($canManage): ?>
  <details class="ath-panel bo-dbf-panel" id="dbf-imposer"<?= $openPanel === 'imposer' ? ' open' : '' ?>>
    <summary class="bo-dbf-panel__sum">Imposer un CR de fin d’opération</summary>
    <form method="post" action="<?= $h($base . '/imposer') ?>" class="bo-dbf-form">
      <?= $csrf ?>
      <p class="ath-panel__lead">Le téléphone ATAK de chaque joueur s’ouvre sur le CR à sa prochaine synchro (30 s environ en jeu). Il reste affiché jusqu’à ce que le joueur le remette ou le lise. Un Zeus ou un UID autorisé peut aussi l’imposer depuis le jeu (app « CR fin d’op » du téléphone).</p>
      <div class="bo-dbf-form__grid">
        <label>Mode
          <select name="mode" data-dbf-mode>
            <option value="fill"<?= $settings['default_mode'] === 'fill' ? ' selected' : '' ?>>À remplir par le joueur</option>
            <option value="read"<?= $settings['default_mode'] === 'read' ? ' selected' : '' ?>>À lire seulement</option>
          </select>
        </label>
        <label>Titre
          <input type="text" name="titre" maxlength="200" placeholder="<?= $h($op !== '' ? AtakMissionKey::label($op) : 'Fin d’opération') ?>">
        </label>
        <label>Joueurs visés
          <select name="cible" data-dbf-target>
            <?php if ($op !== ''): ?><option value="operation" selected>Joueurs de la session (<?= $h(AtakMissionKey::label($op)) ?>)</option><?php endif; ?>
            <option value="en_ligne"<?= $op === '' ? ' selected' : '' ?>>ATAK synchronisés récemment</option>
            <option value="liste">Liste d’UID Steam</option>
          </select>
        </label>
        <label data-dbf-show="en_ligne">Synchronisés depuis
          <select name="minutes"><?php foreach ([15 => '15 min', 30 => '30 min', 60 => '1 h', 180 => '3 h', 720 => '12 h'] as $m => $l): ?><option value="<?= $m ?>"<?= $m === 30 ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
        </label>
        <label>Affiché pendant
          <select name="validite_heures"><?php foreach ([6 => '6 h', 24 => '24 h', 48 => '48 h', 168 => '7 jours'] as $hh => $l): ?><option value="<?= $hh ?>"<?= $hh === 48 ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
        </label>
        <label data-dbf-show-mode="fill">Questionnaire
          <select name="template_id">
            <option value="0">Questionnaire intégré à l’ATAK (6 questions)</option>
            <?php foreach ($dbfTemplates ?? [] as $tpl): ?><option value="<?= (int) $tpl['id'] ?>"<?= (int) $settings['template_id'] === (int) $tpl['id'] ? ' selected' : '' ?>><?= $h($tpl['title']) ?> (<?= (int) $tpl['field_count'] ?> question(s))</option><?php endforeach; ?>
          </select>
        </label>
        <label data-dbf-show-mode="read">Compte rendu à lire
          <select name="aar_report_id">
            <option value="0">Texte ci-dessous</option>
            <?php foreach ($dbfAars ?? [] as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $h(($a['reference_code'] ?? '') . ' · ' . (trim((string) ($a['operation_label'] ?? '')) ?: $a['title'])) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <input type="hidden" name="mission_key" value="<?= $h($op) ?>">
      <label class="bo-dbf-form__wide" data-dbf-show="liste">UID Steam (un par ligne ou séparés par des virgules)
        <textarea name="joueurs" rows="3" placeholder="7656119…"></textarea>
      </label>
      <label class="bo-dbf-form__wide">Message d’introduction (facultatif)
        <input type="text" name="intro" maxlength="600" value="<?= $h($settings['intro']) ?>" placeholder="Merci de remplir votre compte rendu avant de quitter le serveur.">
      </label>
      <label class="bo-dbf-form__wide" data-dbf-show-mode="read">Texte à lire
        <textarea name="texte" rows="4" maxlength="6000" placeholder="Synthèse de l’opération, points forts, axes de progrès, consignes pour la prochaine session…"></textarea>
      </label>
      <p><button class="ath-btn ath-btn--solid" type="submit">Envoyer aux téléphones</button></p>
    </form>
  </details>

  <details class="ath-panel bo-dbf-panel" id="dbf-reglages"<?= $openPanel === 'reglages' ? ' open' : '' ?>>
    <summary class="bo-dbf-panel__sum">Réglages du jeu</summary>
    <form method="post" action="<?= $h($base . '/reglages') ?>" class="bo-dbf-form">
      <?= $csrf ?>
      <p class="ath-panel__lead">Ce que le téléphone propose quand un Zeus, un administrateur de la plateforme, un gestionnaire de la communauté ou un UID autorisé impose le CR depuis le jeu. Les téléphones reçoivent ces réglages à leur synchro avec Athena ; hors liaison, l’ATAK garde son questionnaire intégré.</p>
      <div class="bo-dbf-form__grid">
        <label>Mode par défaut
          <select name="mode_defaut">
            <option value="fill"<?= $settings['default_mode'] === 'fill' ? ' selected' : '' ?>>À remplir</option>
            <option value="read"<?= $settings['default_mode'] === 'read' ? ' selected' : '' ?>>À lire</option>
          </select>
        </label>
        <label>Questionnaire en jeu
          <select name="template_id">
            <option value="0">Intégré à l’ATAK</option>
            <?php foreach ($dbfTemplates ?? [] as $tpl): ?><option value="<?= (int) $tpl['id'] ?>"<?= (int) $settings['template_id'] === (int) $tpl['id'] ? ' selected' : '' ?>><?= $h($tpl['title']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="bo-aar-check bo-dbf-form__check"><input type="checkbox" name="zeus" value="1"<?= !empty($settings['zeus_allowed']) ? ' checked' : '' ?>> Les Zeus peuvent imposer le CR</label>
      </div>
      <label class="bo-dbf-form__wide">Introduction par défaut
        <input type="text" name="intro" maxlength="600" value="<?= $h($settings['intro']) ?>">
      </label>
      <label class="bo-dbf-form__wide">UID Steam autorisés en plus (chefs de groupe, instructeurs…)
        <textarea name="uids" rows="3" placeholder="7656119…"><?= $h(implode("\n", $settings['authorized_uids'])) ?></textarea>
      </label>
      <p class="bo-aar-muted">Administrateurs de la plateforme et gestionnaires de la communauté ont toujours le droit. L’hôte et les administrateurs du serveur Arma aussi.</p>
      <p><button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button></p>
    </form>
  </details>
<?php endif; ?>
</div>
<script>
(function () {
  var root = document.getElementById('bo-dbf');
  if (!root) { return; }
  root.querySelectorAll('.bo-dbf-more').forEach(function (b) {
    b.addEventListener('click', function () {
      var row = document.getElementById(b.getAttribute('aria-controls'));
      if (!row) { return; }
      row.hidden = !row.hidden;
      b.setAttribute('aria-expanded', row.hidden ? 'false' : 'true');
    });
  });
  var search = root.querySelector('[data-dbf-search]');
  var count = root.querySelector('[data-dbf-count]');
  if (search) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase(), n = 0;
      root.querySelectorAll('.bo-dbf-row').forEach(function (tr) {
        var ok = q === '' || (tr.getAttribute('data-dbf-text') || '').indexOf(q) >= 0;
        tr.hidden = !ok;
        n += ok ? 1 : 0;
        var next = tr.nextElementSibling;
        if (!ok && next && next.classList.contains('bo-dbf-answers')) { next.hidden = true; }
      });
      if (count) { count.textContent = n; }
    });
  }
  root.querySelectorAll('[data-dbf-open]').forEach(function (a) {
    a.addEventListener('click', function () { var p = document.getElementById(a.getAttribute('data-dbf-open')); if (p) { p.open = true; } });
  });
  var form = root.querySelector('#dbf-imposer form');
  if (form) {
    var sync = function () {
      var mode = form.querySelector('[data-dbf-mode]').value, target = form.querySelector('[data-dbf-target]').value;
      form.querySelectorAll('[data-dbf-show-mode]').forEach(function (el) { el.hidden = el.getAttribute('data-dbf-show-mode') !== mode; });
      form.querySelectorAll('[data-dbf-show]').forEach(function (el) { el.hidden = el.getAttribute('data-dbf-show') !== target; });
    };
    form.addEventListener('change', sync);
    sync();
  }
})();
</script>
