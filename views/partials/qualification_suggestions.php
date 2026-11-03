<?php
declare(strict_types=1);

/**
 * Qualifications et brevets suggérés à un membre : info-barre compacte (compteurs, « Voir les suggestions ») et
 * fenêtre avec la liste complète (fiche membre du back-office avec actions rapides ; « Ma situation » en lecture).
 * Les actions rapides restent des formulaires classiques ; qualification-suggestions.js les envoie sans quitter la
 * fenêtre et remplace la liste et les compteurs par ceux de la page renvoyée. Sans JavaScript, le lien ouvre la
 * liste (ancre #qsg-md) et les formulaires reviennent à la fiche.
 * Rendu dans une fonction : aucune variable de la page qui l'inclut n'est écrasée.
 *
 * @var array<string, mixed>|null $qualificationSuggestions MemberQualificationSuggestionService::forMember()
 * @var array{import?: bool, award?: bool, enroll?: bool, dismiss?: bool} $qualificationSuggestionRights
 * @var int $qualificationSuggestionUserId
 * @var array<string, mixed> $progressNames
 */
(static function (array $qs, array $can, int $memberId, array $criterionNames): void {
    $items = is_array($qs['items'] ?? null) ? $qs['items'] : [];
    $dismissed = is_array($qs['dismissed'] ?? null) ? $qs['dismissed'] : [];
    $e = static fn (mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $States = \App\Services\Personnel\MemberQualificationSuggester::class;
    $Visual = \App\Support\QualificationCatalog\QualificationVisual::class;
    $lang = function_exists('locale') ? (string) locale() : 'fr';
    $stateLabels = [
        $States::STATE_PENDING => ['En formation ou en évaluation', 'info', 'train'],
        $States::STATE_ACADEMY => ['En formation Academy', 'info', 'train'],
        $States::STATE_READY => ['Prête à obtenir', 'ok', 'ready'],
        $States::STATE_MISSING => ['Il manque des conditions', 'warn', 'train'],
        $States::STATE_NOT_IMPORTED => ['Non importée dans le référentiel', 'muted', 'notimp'],
    ];
    $kindLabels = ['brevet' => 'Brevet', 'qualification' => 'Qualification', 'cursus' => 'Cursus ou sélection', 'licence' => 'Licence'];
    $sourceLabels = ['game' => 'mesuré par le jeu', 'instructor' => 'attesté par un instructeur', 'qualification' => 'qualification préalable', 'grade' => 'grade'];
    $reasonLabels = ['required' => 'Exigée par', 'job' => 'Emploi', 'nature' => 'Unité', 'prereq' => 'Prérequis de'];
    $canDismiss = !empty($can['dismiss']) && !empty($qs['dismiss_ready']);
    $hasActions = array_filter($can) !== [];
    $self = !$hasActions && $memberId === (int) \App\Core\Session::get('user_id');
    $jobs = is_array($qs['jobs'] ?? null) ? $qs['jobs'] : [];
    $natureLabels = is_array($qs['nature_labels'] ?? null) ? $qs['nature_labels'] : [];
    $unitName = trim((string) ($qs['unit_name'] ?? ''));
    $dismissUrl = url('back-office/ressources/effectifs/membres/' . $memberId . '/qualifications-suggerees');
    $counts = ['all' => count($items), 'ready' => 0, 'train' => 0, 'notimp' => 0, 'training' => 0];
    foreach ($items as $it) {
        $counts[$stateLabels[$it['state']][2] ?? 'train']++;
        if (in_array($it['state'], [$States::STATE_PENDING, $States::STATE_ACADEMY], true)) {
            $counts['training']++;
        }
    }
    if ($items === [] && ($dismissed === [] || !$canDismiss)) {
        return;
    }
    $num = static fn (?float $v): string => $v === null ? '' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
    ?>
<div class="qsg-bar" id="qualifs-suggerees" role="status" data-qsg-bar data-qsg-member="<?= $memberId ?>" data-qsg-sig="<?= $e(md5(implode('|', array_column($items, 'key')))) ?>"<?= $items === [] ? ' hidden' : '' ?>>
    <span class="qsg-bar__ic" aria-hidden="true"><?= $Visual::icon('') ?></span>
    <p class="qsg-bar__t">
        <b><span data-qsg-count="all"><?= $counts['all'] ?></span> <?php if ($self): ?><span>qualifications suggérées pour vous</span><?php else: ?><span>qualifications suggérées pour ce membre</span><?php endif; ?></b>
        <?php if ($jobs !== [] || $unitName !== ''): ?>
        <span class="qsg-bar__ctx">(<?php if ($jobs !== []): ?><span>emploi</span> <span translate="no"><?= $e($jobs[0]['label']) ?></span><?php endif; ?><?php if ($jobs !== [] && $unitName !== ''): ?> · <?php endif; ?><?php if ($unitName !== ''): ?><span>unité</span> <span translate="no"><?= $e($unitName) ?></span><?php endif; ?>)</span>
        <?php endif; ?>
        <span class="qsg-bar__n"><span>—</span> <b data-qsg-count="ready"><?= $counts['ready'] ?></b> <span>prêtes à obtenir</span>, <b data-qsg-count="training"><?= $counts['training'] ?></b> <span>en formation</span></span>
    </p>
    <a class="eff-btn eff-btn--sm" href="#qsg-md" data-qsg-open aria-haspopup="dialog" aria-controls="qsg-md">Voir les suggestions</a>
    <button type="button" class="qsg-bar__x" data-qsg-hide aria-label="Masquer pour cette session" title="Masquer pour cette session" hidden>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>
<dialog class="effk-md qsg-md" id="qsg-md" aria-labelledby="qsg-md-t" data-qsg-dialog>
    <div class="effk-md__h">
        <div><span class="effk-k">Qualifications et brevets suggérés</span><h2 id="qsg-md-t"><span data-qsg-count="all"><?= $counts['all'] ?></span> <span>suggestions</span></h2></div>
        <a href="#qualifs-suggerees" class="effk-md__x" data-qsg-close aria-label="Fermer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></a>
    </div>
    <div class="effk-md__b qsg" data-qsg-body>
        <?php if ($jobs !== [] || $natureLabels !== []): ?>
        <p class="qsg-detected">
            <span>Détecté :</span>
            <?php foreach (array_slice($jobs, 0, 3) as $j): ?>
                <span class="qsg-chip"><span>emploi</span> <b translate="no"><?= $e($j['label']) ?></b><?php if (($j['country'] ?? '') !== ''): ?> <?= \App\Support\QualificationCatalog\CatalogCountry::flag((string) $j['country']) ?><?php endif; ?></span>
            <?php endforeach; ?>
            <?php foreach ($natureLabels as $n): ?>
                <span class="qsg-chip"><?= $e($n) ?></span>
            <?php endforeach; ?>
        </p>
        <?php endif; ?>
        <div class="qsg-filters" role="group" aria-label="Filtrer les suggestions" data-qsg-filters hidden>
            <button type="button" class="qsg-f is-on" data-qsg-filter="all" aria-pressed="true"><span>Toutes</span> <small data-qsg-count="all"><?= $counts['all'] ?></small></button>
            <button type="button" class="qsg-f" data-qsg-filter="ready" aria-pressed="false"><span>Prêtes</span> <small data-qsg-count="ready"><?= $counts['ready'] ?></small></button>
            <button type="button" class="qsg-f" data-qsg-filter="train" aria-pressed="false"><span>À former</span> <small data-qsg-count="train"><?= $counts['train'] ?></small></button>
            <button type="button" class="qsg-f" data-qsg-filter="notimp" aria-pressed="false"><span>Non importées</span> <small data-qsg-count="notimp"><?= $counts['notimp'] ?></small></button>
        </div>
        <?php if ($items === []): ?>
            <p class="qsg-empty">Aucune suggestion en cours.</p>
        <?php endif; ?>
        <ol class="qsg-list" data-qsg-list>
            <?php foreach ($items as $it):
                [$stateText, $tone, $group] = $stateLabels[$it['state']] ?? ['', 'muted', 'train'];
                $domain = (string) ($it['domain'] ?? '');
                $kind = $Visual::kind($it['name'] . ' ' . $it['official_name'] . ' ' . $it['category']);
                $checks = is_array($it['checks'] ?? null) ? $it['checks'] : [];
                $country = (string) $it['country'];
                ?>
            <li class="qsg-item is-<?= $e($tone) ?>" data-qsg-item="<?= $e($it['key']) ?>" data-qsg-group="<?= $e($group) ?>">
                <div class="qsg-head">
                    <span class="qsg-ic qsg-ic--<?= $e($domain !== '' ? $domain : 'default') ?>" aria-hidden="true"><?= $Visual::icon($domain) ?></span>
                    <div class="qsg-head__t">
                        <b translate="no"><?= $e($it['name']) ?></b>
                        <?php if ($it['official_name'] !== '' && $it['official_name'] !== $it['name']): ?><small translate="no"><?= $e($it['official_name']) ?></small><?php endif; ?>
                        <p class="qsg-meta">
                            <span class="qsg-kind"><?= $e($kindLabels[$kind]) ?></span>
                            <?php if ($it['category'] !== ''): ?><span><?= $e($it['category']) ?></span><?php endif; ?>
                            <?php if ($country !== ''): ?><span class="qsg-flag"><?= \App\Support\QualificationCatalog\CatalogCountry::flag($country) ?> <span translate="no"><?= $e(\App\Support\QualificationCatalog\QualificationCatalog::countryLabel($country, $lang)) ?></span></span><?php endif; ?>
                            <?php if (($it['issuer'] ?? '') !== ''): ?><span><span>émise par</span> <span translate="no"><?= $e($it['issuer']) ?></span></span><?php endif; ?>
                        </p>
                    </div>
                    <span class="qfs-tag qfs-tag--<?= $tone === 'ok' ? 'ok' : ($tone === 'warn' ? 'soon' : 'info') ?>"><?= $e($stateText) ?></span>
                </div>
                <p class="qsg-reasons">
                    <?php foreach ($it['reasons'] as $r): ?>
                    <span class="qsg-badge qsg-badge--<?= $e($r['kind']) ?>"><span><?= $e($reasonLabels[$r['kind']] ?? $r['text']) ?></span><?php if ($r['subject'] !== ''): ?> <b<?= $r['kind'] === 'nature' ? '' : ' translate="no"' ?>><?= $e($r['subject']) ?></b><?php endif; ?></span>
                    <?php endforeach; ?>
                    <span class="qsg-score" title="Pertinence"><span>pertinence</span> <?= (int) $it['points'] ?></span>
                </p>
                <?php if ($checks === [] && $it['missing'] !== []): ?>
                <ul class="qsg-checks">
                    <?php foreach (array_slice($it['missing'], 0, 5) as $m): ?>
                    <li class="is-no"><span class="qsg-ck" aria-hidden="true">✖</span>
                        <span><?= $e($m['text']) ?></span><?php if (is_array($m['criterion'] ?? null)): ?><span> :</span>
                            <?php $criterion = $m['criterion']; require __DIR__ . '/qualification_criterion.php'; ?>
                        <?php elseif (($m['subject'] ?? '') !== ''): ?><span> :</span> <b translate="no"><?= $e($m['subject']) ?></b><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php elseif ($checks !== []): ?>
                <ul class="qsg-checks" aria-label="Prérequis">
                    <?php foreach ($checks as $c): ?>
                    <li class="<?= $c['ok'] ? 'is-ok' : 'is-no' ?>">
                        <span class="qsg-ck" aria-hidden="true"><?= $c['ok'] ? '✔' : '✖' ?></span>
                        <span class="sr-only"><?= $c['ok'] ? 'Rempli' : 'Manquant' ?></span>
                        <?php if ($c['source'] === 'grade'): ?>
                            <span>Grade minimum</span> <b translate="no"><?= $e($c['subject']) ?></b>
                        <?php elseif ($c['source'] === 'qualification'): ?>
                            <b translate="no"><?= $e($c['subject']) ?></b>
                        <?php else: ?>
                            <?php if ($c['need'] !== null): ?><b class="qsg-have"><?= $e($num($c['have'])) ?></b> <span>/</span><?php endif; ?>
                            <?php $criterion = (array) $c['criterion']; require __DIR__ . '/qualification_criterion.php'; ?>
                        <?php endif; ?>
                        <small class="qsg-src">(<?= $e($sourceLabels[$c['source']] ?? '') ?>)</small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (is_array($it['course'])): ?>
                <p class="qsg-course">
                    <span><?= $it['course']['enrolled'] ? ($it['course']['completed'] ? 'Cours Academy terminé :' : 'Inscrit au cours Academy :') : 'Cours Academy qui y mène :' ?></span>
                    <b translate="no"><?= $e($it['course']['title']) ?></b>
                </p>
                <?php endif; ?>
                <?php $desc = trim((string) ($it['description'] ?? '')); $texts = is_array($it['prerequisite_texts'] ?? null) ? $it['prerequisite_texts'] : []; ?>
                <?php if ($desc !== '' || $texts !== [] || ($it['validity'] ?? '') !== '' || !empty($it['validity_months']) || $it['notes'] !== [] || ($hasActions && $it['catalog_id'] !== '')): ?>
                <details class="qsg-more">
                    <summary>Description, prérequis et validité</summary>
                    <?php if ($desc !== ''): ?><p class="qsg-desc"><?= $e($desc) ?></p><?php endif; ?>
                    <?php if ($texts !== []): ?>
                    <p class="qsg-sub">Prérequis officiels</p>
                    <ul class="qsg-texts"><?php foreach ($texts as $t): ?><li><?= $e($t) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <?php if (($it['validity'] ?? '') !== '' || !empty($it['validity_months'])): ?>
                    <p class="qsg-sub">Validité et renouvellement</p>
                    <p class="qsg-desc"><?php if (!empty($it['validity_months'])): ?><b><?= (int) $it['validity_months'] ?></b> <span>mois, puis renouvellement</span><?php endif; ?><?php if (($it['validity'] ?? '') !== ''): ?> <span><?= $e($it['validity']) ?></span><?php endif; ?></p>
                    <?php endif; ?>
                    <?php foreach ($it['notes'] as $note): ?><p class="qsg-note"><?= $e($note) ?></p><?php endforeach; ?>
                    <?php if ($hasActions && $it['catalog_id'] !== ''): ?>
                    <p><a href="<?= $e(url('back-office/referentiels/qualifications/catalogue/fiche') . '?id=' . rawurlencode((string) $it['catalog_id'])) ?>" data-ath-modal="off" target="_blank" rel="noopener">Fiche complète de la qualification</a></p>
                    <?php endif; ?>
                </details>
                <?php endif; ?>
                <?php if ($hasActions): ?>
                <div class="qsg-acts">
                    <?php if ($it['state'] === $States::STATE_NOT_IMPORTED && !empty($can['import']) && $it['catalog_id'] !== ''): ?>
                    <form method="post" action="<?= $e(url('back-office/referentiels/qualifications/catalogue/importer')) ?>" data-qsg-action>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="one" value="<?= $e($it['catalog_id']) ?>">
                        <input type="hidden" name="pack" value="<?= $e($it['pack']) ?>">
                        <input type="hidden" name="return_member" value="<?= $memberId ?>">
                        <button type="submit" class="eff-btn eff-btn--sm">Importer dans le référentiel</button>
                    </form>
                    <?php endif; ?>
                    <?php if (is_array($it['course']) && !$it['course']['enrolled'] && !empty($can['enroll'])): ?>
                    <form method="post" action="<?= $e(url('academy/action/assign')) ?>" data-qsg-action>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="course" value="<?= $e($it['course']['key']) ?>">
                        <input type="hidden" name="member" value="<?= $memberId ?>">
                        <input type="hidden" name="return" value="membre">
                        <button type="submit" class="eff-btn eff-btn--sm">Inscrire au cours Academy</button>
                    </form>
                    <?php endif; ?>
                    <?php if ((int) $it['definition_id'] > 0 && $it['allow_manual'] && !empty($can['award']) && $it['state'] !== $States::STATE_NOT_IMPORTED): ?>
                        <?php foreach ($it['state'] === $States::STATE_PENDING ? ['ok'] : ['ok', 'train'] as $target): ?>
                    <form method="post" action="<?= $e(url('back-office/referentiels/qualifications/matrice/case')) ?>" data-qsg-action>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="definition_id" value="<?= (int) $it['definition_id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $memberId ?>">
                        <input type="hidden" name="target" value="<?= $e($target) ?>">
                        <input type="hidden" name="return_to" value="member">
                        <button type="submit" class="eff-btn eff-btn--sm<?= $target === 'train' ? ' eff-btn--ghost' : '' ?>"><?= $target === 'ok' ? ($it['state'] === $States::STATE_PENDING ? 'Valider' : 'Délivrer') : 'Mettre en formation' ?></button>
                    </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($canDismiss): ?>
                    <form method="post" action="<?= $e($dismissUrl) ?>" data-qsg-action>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="key" value="<?= $e($it['key']) ?>">
                        <button type="submit" class="eff-btn eff-btn--sm eff-btn--ghost">Ignorer pour ce membre</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <p class="qsg-empty" data-qsg-none hidden>Aucune suggestion pour ce filtre.</p>
        <?php if ((int) ($qs['held'] ?? 0) > 0): ?>
            <p class="qsg-held"><span>Qualifications déjà détenues, non reproposées :</span> <?= (int) $qs['held'] ?></p>
        <?php endif; ?>
        <?php if ($dismissed !== [] && $canDismiss): ?>
        <details class="qsg-dismissed">
            <summary><span>Suggestions ignorées</span> (<?= count($dismissed) ?>)</summary>
            <ul>
                <?php foreach ($dismissed as $it): ?>
                <li>
                    <b translate="no"><?= $e($it['name']) ?></b>
                    <form method="post" action="<?= $e($dismissUrl) ?>" data-qsg-action>
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="key" value="<?= $e($it['key']) ?>">
                        <input type="hidden" name="restore" value="1">
                        <button type="submit" class="eff-btn eff-btn--sm eff-btn--ghost">Reprendre</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
        <details class="qsg-how">
            <summary>Comment sont calculées ces suggestions ?</summary>
            <p>Chaque qualification reçoit des points : exigée par la fonction du membre, recommandée pour son emploi, courante dans son type d’unité, ou prérequis d’une qualification suggérée. Les qualifications d’un autre pays que l’unité, et celles de niveau instructeur, comptent moins.</p>
            <p>L’état compare ensuite les prérequis à la situation du membre : valeurs mesurées par le jeu (sauts, heures de vol…), attestations d’un instructeur, qualifications détenues et grade. Les qualifications déjà détenues ne sont pas reproposées.</p>
        </details>
    </div>
</dialog>
    <?php
})(
    is_array($qualificationSuggestions ?? null) ? $qualificationSuggestions : [],
    is_array($qualificationSuggestionRights ?? null) ? $qualificationSuggestionRights : [],
    (int) ($qualificationSuggestionUserId ?? 0),
    is_array($progressNames ?? null) ? $progressNames : []
);
?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/qualification-suggestions.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
