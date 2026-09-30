<?php
declare(strict_types=1);

/**
 * Feuille ORBAT imprimable (style FM ATHENA / mockup).
 * Format adaptatif (A3→A0 / custom) — toutes les sous-unités, sans limite A4.
 *
 * @var array{
 *   root: array<string,mixed>,
 *   groups: list<array<string,mixed>>,
 *   flat_units?: list<array<string,mixed>>,
 *   totals: array{theoretical:int,present:int,rate:int,unit_count?:int},
 *   meta: array<string,mixed>
 * } $document
 * @var bool $printToolbar
 */

$doc = is_array($document ?? null) ? $document : [];
$root = is_array($doc['root'] ?? null) ? $doc['root'] : [];
$groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
$flatUnits = is_array($doc['flat_units'] ?? null) ? $doc['flat_units'] : [];
$totals = is_array($doc['totals'] ?? null) ? $doc['totals'] : ['theoretical' => 0, 'present' => 0, 'rate' => 0, 'unit_count' => 0];
$meta = is_array($doc['meta'] ?? null) ? $doc['meta'] : [];
$printToolbar = !empty($printToolbar);
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$paperCss = trim((string) ($meta['paper_css'] ?? 'A1 landscape'));
$paperName = trim((string) ($meta['paper'] ?? 'A1'));
$paperW = max(420, (int) ($meta['paper_width_mm'] ?? 841));
$sheetMinPx = (int) round($paperW * 3.78); // ~96dpi

$symClass = static function (string $type, bool $small = false): string {
    $c = 'sym' . ($small ? ' small' : '');
    return match ($type) {
        'hq' => $c . ' hq',
        'support' => $c . ' support',
        'air' => $c . ' air',
        'fire' => $c . ' fire',
        'engineer' => $c . ' engineer',
        'log' => $c . ' log',
        'isr' => $c . ' isr',
        default => $c . ' cross',
    };
};
$symInner = static function (string $type): string {
    return match ($type) {
        'hq' => '<span class="hqtxt">HQ</span>',
        'support' => '<span class="circle"></span>',
        'air' => '<span class="plane">✈</span>',
        'fire' => '<span class="glyph">↔</span>',
        'engineer' => '<span class="glyph">△</span>',
        'log' => '<span class="glyph">▰</span>',
        default => '',
    };
};

/**
 * Rendu récursif d’une unité et de toutes ses sous-unités.
 *
 * @param array<string,mixed> $node
 */
$renderUnitBranch = static function (array $node, int $depth) use (&$renderUnitBranch, $h, $symClass, $symInner): string {
    $type = (string) ($node['type'] ?? 'combat');
    $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
    $small = $depth > 0;
    $cls = $depth === 0 ? 'group' : 'child depth-' . min(6, $depth);
    $title = (string) ($node['title'] ?? $node['name'] ?? 'Unité');
    $code = trim((string) ($node['code'] ?? ''));
    $html = '<article class="' . $h($cls) . '">';
    if ($depth === 0) {
        $html .= '<div class="echelon">' . $h($node['echelon'] ?? '') . '</div>';
    } elseif ($code !== '') {
        $html .= '<div class="child-code">' . $h($code) . '</div>';
    }
    $html .= '<div class="' . $h($symClass($type, $small)) . '">' . $symInner($type) . '</div>';
    $html .= '<div class="' . ($depth === 0 ? 'group-title' : 'child-name') . '">' . $h($title) . '</div>';
    $html .= '<div class="' . ($depth === 0 ? 'group-meta' : 'child-meta') . '">';
    $html .= '<div>' . (int) ($node['present'] ?? 0) . ' / ' . (int) ($node['theoretical'] ?? 0) . ' pers.</div>';
    $html .= '<div>' . $h($node['rank'] ?? $node['leader'] ?? '') . '</div>';
    $html .= '</div>';
    if ($depth === 0 && trim((string) ($node['desc'] ?? '')) !== '') {
        $html .= '<div class="group-desc">' . nl2br($h($node['desc'])) . '</div>';
    }
    if ($kids !== []) {
        $html .= '<div class="child-connector"></div>';
        $html .= '<div class="children cols-' . min(8, count($kids)) . '">';
        foreach ($kids as $c) {
            if (!is_array($c)) {
                continue;
            }
            $html .= $renderUnitBranch($c, $depth + 1);
        }
        $html .= '</div>';
    }
    $html .= '</article>';

    return $html;
};

$pdfUrl = url('orbat/pdf');
$qs = [];
if (!empty($meta['include_mission'])) {
    $qs['mission'] = '1';
}
if (!empty($meta['include_notes'])) {
    $qs['notes'] = '1';
}
if (!empty($meta['theater']) && $meta['theater'] !== '—') {
    $qs['theater'] = (string) $meta['theater'];
}
if ($qs !== []) {
    $pdfUrl .= '?' . http_build_query($qs);
}
$unitCount = (int) ($totals['unit_count'] ?? count($flatUnits));
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>ATHENA MILSIM — ORBAT — <?= $h($meta['unit_label'] ?? '') ?></title>
<style>
  :root{
    --ink:#101820; --muted:#5b6268; --line:#21282e; --soft:#eef1f3; --paper:#ffffff;
    --header:#1e2932; --blue:#bcd8f0; --blue2:#dcecf8; --green:#cbe8a3;
    --yellow:#f8e979; --orange:#f4ba79; --gray:#d7dadd;
  }
  *{box-sizing:border-box}
  html,body{margin:0;background:#dfe3e6;color:var(--ink);font-family:"Arial Narrow","Liberation Sans Narrow",Arial,sans-serif}
  body{padding:18px}
  .toolbar{
    max-width:100%;margin:0 auto 12px;display:flex;gap:8px;flex-wrap:wrap;
    align-items:center;background:#fff;border:1px solid #b9c0c5;padding:9px 10px;
    box-shadow:0 1px 4px rgba(0,0,0,.09)
  }
  .toolbar a,.toolbar button{
    border:1px solid #9ca6ad;background:#f7f8f9;color:#182128;padding:7px 10px;
    font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center
  }
  .toolbar a:hover,.toolbar button:hover{background:#edf1f3}
  .toolbar .primary{background:#1e2932;color:#fff;border-color:#1e2932}
  .toolbar .spacer{flex:1}
  .toolbar .hint{font-size:12px;font-weight:700;color:#5b6268}
  .sheet{
    width:max-content; min-width:min(100%, <?= (int) $sheetMinPx ?>px); min-height:720px;
    margin:0 auto;background:var(--paper);
    border:1px solid #111; box-shadow:0 6px 24px rgba(0,0,0,.16); padding:0; position:relative;
  }
  .topline{display:grid;grid-template-columns:1fr auto 1fr;align-items:start;border-bottom:2px solid var(--line);min-height:94px}
  .brand{padding:16px 20px 10px}
  .brand h1{font-size:25px;line-height:1;margin:0 0 3px;font-weight:900}
  .brand h2{font-size:19px;line-height:1.05;margin:0;font-weight:900}
  .brand h3{font-size:14px;line-height:1.2;margin:3px 0 0;font-weight:800}
  .classif{margin-top:0;padding:9px 23px;background:#141c24;color:white;font-size:16px;font-weight:900;letter-spacing:.8px;white-space:nowrap}
  .docref{padding:14px 18px;text-align:right;font-size:16px;font-weight:800;line-height:1.35}
  .meta-row{display:grid;grid-template-columns:1.2fr 1fr .78fr;gap:26px;padding:18px 22px 10px;align-items:start}
  .meta-table{font-size:16px;line-height:1.55}
  .meta-table b{display:inline-block;min-width:118px}
  .summary{border:1px solid var(--line);align-self:start}
  .box-title{background:var(--header);color:#fff;font-weight:900;padding:7px 10px;font-size:16px;letter-spacing:.35px}
  .summary table,.strength table{border-collapse:collapse;width:100%;font-size:14px}
  .summary td,.summary th,.strength td,.strength th{border:1px solid #858c91;padding:5px 7px}
  .summary th,.strength th{background:#f2f3f4;font-weight:800}
  .root-wrap{text-align:center;padding-top:0}
  .root-node{display:inline-flex;align-items:center;gap:16px;padding:0 12px 10px}
  .root-text{text-align:left}
  .root-text .name{font-weight:900;font-size:18px}
  .root-text .sub{font-size:14px}
  .root-label{font-size:20px;font-weight:900;margin-bottom:3px}
  .root-rank{font-weight:900;font-size:14px;margin-top:4px}
  .sym{width:86px;height:52px;border:2px solid #111;background:var(--blue);display:grid;place-items:center;position:relative}
  .sym.small{width:52px;height:34px}
  .sym:before,.sym:after{content:"";position:absolute;width:98%;height:2px;background:#111;left:1%;top:50%;transform-origin:center}
  .sym.cross:before{transform:rotate(33deg)}.sym.cross:after{transform:rotate(-33deg)}
  .sym.hq:before,.sym.hq:after{display:none}
  .sym .hqtxt{font-size:24px;font-weight:900}
  .sym.small .hqtxt{font-size:14px}
  .sym.support{background:var(--green)}.sym.support:before,.sym.support:after{display:none}
  .sym.support .circle{width:29px;height:29px;border:2px solid #111;border-radius:50%}
  .sym.small.support .circle{width:16px;height:16px}
  .sym.air{background:var(--blue)}.sym.air:before,.sym.air:after{display:none}
  .sym.air .plane{font-size:31px;line-height:1}
  .sym.small.air .plane{font-size:18px}
  .sym.fire{background:var(--yellow)}.sym.fire:before,.sym.fire:after{display:none}
  .sym.engineer{background:var(--orange)}.sym.engineer:before,.sym.engineer:after{display:none}
  .sym.log{background:var(--gray)}.sym.log:before,.sym.log:after{display:none}
  .sym.isr{background:var(--blue)}.sym.isr:before{transform:rotate(45deg)}.sym.isr:after{transform:rotate(-45deg)}
  .sym .glyph{font-size:22px;font-weight:900}
  .connector-root{height:42px;position:relative;margin:0 48px}
  .connector-root:before{content:"";position:absolute;left:50%;top:0;height:18px;border-left:2px solid #222}
  .connector-root:after{content:"";position:absolute;left:5%;right:5%;top:18px;border-top:2px solid #222}
  .groups{
    display:flex;flex-wrap:wrap;justify-content:center;align-items:flex-start;
    gap:22px 28px;padding:0 28px 16px;
  }
  /* min-content : la branche s’élargit pour ses frères horizontaux (pas de pile verticale forcée) */
  .group{position:relative;text-align:center;padding:0 8px;flex:0 0 auto;min-width:max-content}
  .group:before{content:"";position:absolute;left:50%;top:-24px;height:24px;border-left:2px solid #222}
  .echelon{font-weight:900;font-size:17px;letter-spacing:2px;height:20px}
  .group-title{font-weight:900;font-size:15px;margin-top:5px}
  .group-meta{font-size:12px;line-height:1.35;margin-top:3px}
  .group .sym{margin:0 auto}
  .group-desc{font-size:12px;line-height:1.35;min-height:24px;margin-top:6px;color:var(--muted)}
  .child-connector{height:24px;position:relative;margin:8px 8px 0}
  .child-connector:before{content:"";position:absolute;left:50%;height:11px;border-left:1.6px solid #222}
  .child-connector:after{content:"";position:absolute;left:6%;right:6%;top:11px;border-top:1.6px solid #222}
  /* Grille en colonnes : frères toujours côte à côte (évite l’empilement flex imbriqué) */
  .children{
    display:grid;grid-auto-flow:column;grid-auto-columns:minmax(100px,max-content);
    justify-content:center;align-items:start;gap:10px 14px;width:max-content;margin:0 auto;
  }
  .children.cols-5,.children.cols-6,.children.cols-7,.children.cols-8{
    grid-auto-flow:row;grid-template-columns:repeat(4,minmax(100px,max-content));
  }
  .child{position:relative;text-align:center;font-size:11px;min-width:100px}
  .child:before{content:"";position:absolute;left:50%;top:-13px;height:13px;border-left:1.6px solid #222}
  .child .sym{margin:0 auto 4px}
  .child-code{font-weight:900;font-size:11px;margin-bottom:2px}
  .child-name{font-weight:900;font-size:12px;line-height:1.05;max-width:140px;margin:0 auto}
  .child-meta{font-size:11px;line-height:1.25;margin-top:3px}
  .child.depth-2 .sym,.child.depth-3 .sym,.child.depth-4 .sym{width:44px;height:28px}
  .child.depth-2,.child.depth-3,.child.depth-4{font-size:10px;min-width:96px}
  .bottom-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;padding:12px 26px 14px}
  .strength{border:1px solid var(--line)}
  .strength td:first-child{font-weight:800}
  .strength tfoot td{font-weight:900;background:#f3f4f5}
  .strength .indent-1{padding-left:14px}
  .strength .indent-2{padding-left:28px}
  .strength .indent-3{padding-left:42px}
  .strength .indent-4{padding-left:56px}
  .strength .indent-5{padding-left:70px}
  .optional{border:1px solid var(--line);margin:0 26px 12px}
  .optional .content{padding:9px 11px;font-size:13px;line-height:1.4}
  .legend-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;font-size:12px}
  .legend-item{display:flex;align-items:center;gap:7px}
  .mini-sym{width:34px;height:21px}
  .footer{border-top:2px solid var(--line);display:grid;grid-template-columns:1fr 1fr 1fr;padding:8px 20px;font-size:13px;font-weight:800}
  .footer div:nth-child(2){text-align:center}
  .footer div:nth-child(3){text-align:right}
  .empty-groups{padding:40px;text-align:center;color:var(--muted);font-weight:700}
  @media print{
    @page{size:<?= $h($paperCss) ?>;margin:6mm}
    html,body{background:white;padding:0}
    .toolbar{display:none!important}
    .sheet{box-shadow:none;border:1px solid #111;margin:0;width:100%;min-width:0;min-height:auto}
    .group{break-inside:avoid;page-break-inside:avoid}
    .child{break-inside:avoid;page-break-inside:avoid}
  }
</style>
</head>
<body>
<?php if ($printToolbar): ?>
<div class="toolbar">
  <button type="button" class="primary" onclick="window.print()">Imprimer / PDF navigateur</button>
  <a class="primary" href="<?= $h($pdfUrl) ?>">Télécharger PDF</a>
  <a href="<?= $h(url('orbat')) ?>">Retour ORBAT</a>
  <span class="spacer"></span>
  <span class="hint">Format <?= $h($paperName) ?> paysage · <?= (int) $unitCount ?> unités · <?= $h($meta['reference'] ?? '') ?></span>
</div>
<?php endif; ?>

<main class="sheet" id="sheet">
  <header class="topline">
    <div class="brand">
      <h1>ATHENA MILSIM</h1>
      <h2>ORGANIZATION CHART (ORBAT)</h2>
      <h3>TABLEAU D’ORGANISATION ET D’ÉQUIPEMENT</h3>
    </div>
    <div class="classif"><?= $h($meta['classification'] ?? 'UNCLASSIFIED // FOR OFFICIAL USE ONLY') ?></div>
    <div class="docref">
      <div><?= $h($meta['doc_code'] ?? 'FM ATHENA RH-01') ?></div>
      <div>ANNEXE B</div>
      <div>FIGURE B-1 · <?= $h($paperName) ?></div>
    </div>
  </header>

  <section class="meta-row">
    <div class="meta-table">
      <div><b>UNITÉ :</b> <?= $h($meta['unit_label'] ?? '') ?></div>
      <div><b>THÉÂTRE :</b> <?= $h($meta['theater'] ?? '—') ?></div>
      <div><b>DATE D’EFFET :</b> <?= $h($meta['effect_date'] ?? '') ?></div>
      <div><b>RÉFÉRENCE :</b> <?= $h($meta['reference'] ?? '') ?></div>
      <div><b>VERSION :</b> <?= $h($meta['version'] ?? '1.0') ?></div>
      <div><b>UNITÉS :</b> <?= (int) $unitCount ?></div>
    </div>

    <div class="root-wrap">
      <div class="root-node">
        <div>
          <div class="root-label"><?= $h($root['code'] ?? 'TF') ?></div>
          <div class="<?= $h($symClass((string) ($root['type'] ?? 'combat'))) ?>"><?= $symInner((string) ($root['type'] ?? 'combat')) ?></div>
          <div class="root-rank"><?= $h($root['leader'] ?? '—') ?></div>
        </div>
        <div class="root-text">
          <div class="name"><?= $h($root['name'] ?? '') ?></div>
          <div class="sub">(± <?= (int) ($totals['theoretical'] ?? 0) ?> pers.)</div>
        </div>
      </div>
    </div>

    <div class="summary">
      <div class="box-title">COMPOSITION GÉNÉRALE</div>
      <table>
        <tbody>
          <tr><td>Effectif total (théorique)</td><td><?= (int) ($totals['theoretical'] ?? 0) ?></td></tr>
          <tr><td>Effectif présent (T/O)</td><td><?= (int) ($totals['present'] ?? 0) ?></td></tr>
          <tr><td>Taux de disponibilité</td><td><?= (int) ($totals['rate'] ?? 0) ?> %</td></tr>
          <tr><td>Unités affichées</td><td><?= (int) $unitCount ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($groups !== []): ?>
  <div class="connector-root"></div>
  <section class="groups">
    <?php foreach ($groups as $g): ?>
      <?php if (!is_array($g)) { continue; } ?>
      <?= $renderUnitBranch($g, 0) ?>
    <?php endforeach; ?>
  </section>
  <?php else: ?>
  <p class="empty-groups">Aucune sous-unité à afficher sur cette feuille.</p>
  <?php endif; ?>

  <section class="bottom-grid">
    <div class="strength">
      <div class="box-title">SYNTHÈSE EFFECTIFS — TOUTES UNITÉS</div>
      <table>
        <thead>
          <tr>
            <th>ÉLÉMENT</th><th>THÉORIQUE</th><th>PRÉSENT</th><th>DISPONIBILITÉ</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $rows = $flatUnits !== [] ? $flatUnits : array_map(static function (array $g): array {
                return [
                    'title' => $g['title'] ?? '',
                    'theoretical' => $g['theoretical'] ?? 0,
                    'present' => $g['present'] ?? 0,
                    'depth' => 0,
                ];
            }, array_values(array_filter($groups, 'is_array')));
          ?>
          <?php foreach ($rows as $u): ?>
            <?php if (!is_array($u)) { continue; } ?>
            <?php
              $t = (int) ($u['theoretical'] ?? 0);
              $p = (int) ($u['present'] ?? 0);
              $pct = $t > 0 ? (int) round(($p / $t) * 100) : 0;
              $d = min(5, max(0, (int) ($u['depth'] ?? 0)));
            ?>
            <tr>
              <td class="indent-<?= $d ?>"><?= $h($u['title'] ?? '') ?></td>
              <td><?= $t ?></td>
              <td><?= $p ?></td>
              <td><?= $pct ?> %</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>TOTAL <?= $h($meta['unit_label'] ?? '') ?></td>
            <td><?= (int) ($totals['theoretical'] ?? 0) ?></td>
            <td><?= (int) ($totals['present'] ?? 0) ?></td>
            <td><?= (int) ($totals['rate'] ?? 0) ?> %</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if (!empty($meta['include_mission'])): ?>
    <div class="optional mission">
      <div class="box-title">MISSION</div>
      <div class="content">
        <?= nl2br($h(($root['mission'] ?? '') !== '' ? (string) $root['mission'] : 'Conduire les opérations conformément aux directives du commandement.')) ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <?php if (!empty($meta['include_notes'])): ?>
  <section class="optional notes">
    <div class="box-title">NOTES</div>
    <div class="content">
      1. Effectifs selon tableau d’organisation en vigueur.<br>
      2. Les indisponibilités sont suivies par le bureau RH / S-1.<br>
      3. Les rattachements temporaires sont indiqués sur ordre.<br>
      4. Format d’impression adaptatif (<?= $h($paperName) ?>) — non limité au A4.
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($meta['include_legend'])): ?>
  <section class="optional legend">
    <div class="box-title">LÉGENDE — SYMBOLES OPÉRATIONNELS</div>
    <div class="content legend-grid">
      <div class="legend-item"><span class="sym mini-sym cross"></span><span>Combat / manœuvre</span></div>
      <div class="legend-item"><span class="sym mini-sym hq"><span class="hqtxt" style="font-size:12px">HQ</span></span><span>Commandement</span></div>
      <div class="legend-item"><span class="sym mini-sym support"><span class="circle" style="width:12px;height:12px"></span></span><span>Soutien</span></div>
      <div class="legend-item"><span class="sym mini-sym air"><span class="plane" style="font-size:15px">✈</span></span><span>Aviation</span></div>
    </div>
  </section>
  <?php endif; ?>

  <footer class="footer">
    <div><?= $h($meta['doc_code'] ?? 'FM ATHENA RH-01') ?></div>
    <div><?= $h($meta['classification'] ?? 'UNCLASSIFIED // FOR OFFICIAL USE ONLY') ?></div>
    <div>ANNEXE B-1 · <?= $h($paperName) ?> · <?= (int) $unitCount ?> unités</div>
  </footer>
</main>
</body>
</html>
