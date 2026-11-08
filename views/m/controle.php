<?php

declare(strict_types=1);
/**
 * Contrôle ID (COMSPEC IDENTITY) sur le vrai téléphone : contrôles d'identité faits par le joueur dans la partie,
 * le plus récent d'abord. Chaque contrôle se déplie : papier présenté, détail par niveau (N0 visuel, N1 bande MRZ,
 * N2 puce, N3 fichiers), photos (pièces jointes de la fiche FRM rattachée) et « Joindre à une FRM » (brouillon de
 * fiche à valider par le bureau SSE).
 *
 * @var list<array<string, mixed>> $checks contrôles enrichis (SseIdCheckInsightService::enrich)
 * @var bool $noSteam
 * @var int $openId
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$docTypes = \App\Support\SseIdentityCatalog::DOC_TYPES;
require __DIR__ . '/_head.php';
?>
    <section class="mm-card">
      <h1 class="mm-h1">Contrôle ID</h1>
      <p class="mm-muted">Vos contrôles d’identité de la partie, sur la carte en cours. Touchez un contrôle pour son détail.</p>
      <p class="mm-idc-sim"><strong>SIMULATION</strong> Papiers fictifs pour le jeu.</p>
    </section>
<?php if (!empty($noSteam)): ?>
    <section class="mm-card mm-card--warn">
      <p class="mm-muted">Votre compte Athena n’est pas relié à Steam : vos contrôles ne peuvent pas être retrouvés. Reliez Steam dans votre profil Athena.</p>
    </section>
<?php elseif ($checks === []): ?>
    <section class="mm-card">
      <p class="mm-muted">Aucun contrôle pour l’instant. Dans le jeu, contrôlez des papiers (menu ACE « Contrôle d’identité ») puis transmettez-les à Athena : ils apparaissent ici.</p>
    </section>
<?php else: ?>
    <div class="mm-idc-list">
    <?php foreach ($checks as $c): ?>
      <?php
        $who = trim(mb_strtoupper((string) ($c['presented_last'] ?? '')) . ' ' . (string) ($c['presented_first'] ?? ''));
        $type = $docTypes[(string) ($c['doc_type'] ?? '')] ?? 'Papier';
        $note = is_array($c['note'] ?? null) ? $c['note'] : null;
        $time = trim((string) ($c['game_time'] ?? ''));
      ?>
      <details class="mm-idc mm-idc--<?= $h($c['verdict_tone']) ?>" id="ctrl-<?= (int) $c['id'] ?>"<?= (int) $openId === (int) $c['id'] ? ' open' : '' ?>>
        <summary class="mm-idc__head">
          <span class="mm-idc__who"><strong translate="no"><?= $h($who !== '' ? $who : 'Identité non lue') ?></strong>
            <small><?= $h($type) ?> <span translate="no"><?= $h(trim(($c['country_code'] ?? '') . ' ' . ($c['doc_number'] ?? ''))) ?></span></small></span>
          <span class="mm-idc__verdict"><?= $h($c['verdict_label']) ?><?php if (!empty($c['forgery'])): ?> · Faux document<?php endif; ?></span>
          <small class="mm-idc__meta mm-mono"><?= $h($c['ref']) ?> · <?= $h($time !== '' ? $time : (string) $c['created_at']) ?><?php if (!empty($c['grid'])): ?> · <?= $h($c['grid']) ?><?php endif; ?></small>
        </summary>
        <div class="mm-idc__body">
          <?php if (!empty($c['presented_dob'])): ?><p class="mm-muted">Né(e) le <span translate="no"><?= $h($c['presented_dob']) ?></span></p><?php endif; ?>
          <h2 class="mm-idc__h">Niveaux de contrôle</h2>
          <?php if ($c['level_rows'] === []): ?>
            <p class="mm-muted">Aucun niveau détaillé pour ce contrôle.</p>
          <?php else: ?>
            <ol class="mm-idc__levels">
              <?php foreach ($c['level_rows'] as $l): ?>
                <li class="mm-idc__lvl mm-idc__lvl--<?= $h($l['tone']) ?>">
                  <strong><?= $h($l['label']) ?></strong>
                  <span><?= $h($l['state_label']) ?><?= $l['verdict_label'] !== '' ? ' · ' . $h($l['verdict_label']) : '' ?></span>
                  <?php if ($l['codes'] !== []): ?><small><?= $h(implode(' · ', $l['codes'])) ?></small><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
          <?php if ($c['photos'] !== []): ?>
            <h2 class="mm-idc__h">Photos</h2>
            <div class="mm-idc__photos">
              <?php foreach ($c['photos'] as $p): ?>
                <a href="<?= $h($p['url']) ?>" target="_blank" rel="noopener"><img src="<?= $h($p['url']) ?>" alt="<?= $h($p['caption'] !== '' ? $p['caption'] : 'Photo du contrôle') ?>" loading="lazy"></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <p class="mm-muted">Contrôleur : <span translate="no"><?= $h($c['controller_callsign'] ?? '—') ?></span><?php if (!empty($c['case_ref'])): ?> · dossier <span translate="no"><?= $h($c['case_ref']) ?></span><?php endif; ?></p>
          <?php if ($note !== null): ?>
            <p class="mm-idc__frm">Fiche <span translate="no" class="mm-mono"><?= $h($note['ref']) ?></span> · <?= $h($note['status_label']) ?></p>
          <?php else: ?>
            <form class="mm-idc__form" data-idc-frm="<?= (int) $c['id'] ?>">
              <label class="mm-idc__label" for="remark-<?= (int) $c['id'] ?>">Remarque pour le bureau SSE (facultatif)</label>
              <textarea id="remark-<?= (int) $c['id'] ?>" name="remark" rows="2" maxlength="400" placeholder="Comportement, lieu, ce que la personne a dit…"></textarea>
              <button type="submit" class="mm-btn mm-btn--send">Joindre à une FRM</button>
              <p class="mm-state" data-idc-state></p>
            </form>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
