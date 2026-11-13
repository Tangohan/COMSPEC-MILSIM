<?php
declare(strict_types=1);
use App\Support\SseFileCatalog as F;
use App\Support\SseIdentityCatalog as C;

/** Réponse formatée façon fichier : en-tête, HIT / NO HIT, mentions, papiers, personne, SEEK II, contrôles subis. */
/** @var array<string,mixed> $result */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = $result['report'];
$answer = $result['answer'];
$canManage = !empty($filesCanManage);
$back = 'atak/sse/fichiers?' . (string) ($_SERVER['QUERY_STRING'] ?? '');
$mentionCodes = array_map(static fn (array $m): string => (string) $m['mention'], $r['mentions']);
$armed = in_array('armed_dangerous', $mentionCodes, true);
$tone = !$r['hit'] ? 'nohit' : (in_array($r['verdict'], ['hvt', 'recherche', 'vole', 'revoque'], true) ? 'alert' : (in_array($r['verdict'], ['signale', 'expire'], true) ? 'signale' : 'ok'));
$criteriaLabels = ['last' => 'Nom', 'first' => 'Prénom', 'dob' => 'Naissance', 'number' => 'Numéro', 'country' => 'Pays', 'doc_type' => 'Type'];
$criteriaText = [];
foreach ($criteriaLabels as $k => $label) {
    if (($r['criteria'][$k] ?? '') !== '') {
        $criteriaText[] = [$label, $r['criteria'][$k]];
    }
}
$mentionForm = static function (string $type, int $id) use ($h, $back, $csrfToken, $mentionLabels): string {
    $opts = '';
    foreach ($mentionLabels as $k => $label) {
        $opts .= '<option value="' . $h($k) . '">' . $h($label) . '</option>';
    }

    return '<form method="post" action="' . $h(url('atak/sse/fichiers/mentions')) . '">'
        . '<input type="hidden" name="_csrf_token" value="' . $h((string) $csrfToken) . '">'
        . '<input type="hidden" name="back" value="' . $h($back) . '">'
        . '<input type="hidden" name="target_type" value="' . $h($type) . '">'
        . '<input type="hidden" name="target_id" value="' . $id . '">'
        . '<select name="mention" aria-label="Mention">' . $opts . '</select>'
        . '<input type="text" name="note" maxlength="1000" placeholder="Note" aria-label="Note">'
        . '<input type="text" name="reference" maxlength="64" placeholder="Référence (mandat, avis…)" aria-label="Référence">'
        . '<button class="btn btn--ghost" type="submit">Poser la mention</button></form>';
};
?>
<article class="fq-terminal" aria-label="Réponse des fichiers">
    <div class="fq-terminal__head">
        <div>Requête <b translate="no"><?= $h($r['qrn'] !== '' ? $r['qrn'] : '—') ?></b></div>
        <div>Heure <b><?= $h($r['at']) ?> UTC</b></div>
        <div>Type <b><?= $h($r['type_label']) ?></b> <span class="fq-code"><?= $h($r['type']) ?></span></div>
        <div>Source <b><?= $h($r['source_label']) ?></b></div>
        <div>Critères
            <?php foreach ($criteriaText as [$label, $value]): ?>
                <span><?= $h($label) ?> <b translate="no"><?= $h($value) ?></b></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($armed): ?>
        <p class="fq-armed" role="alert">ARMÉ ET DANGEREUX</p>
    <?php endif; ?>
    <div class="fq-verdict fq-verdict--<?= $h($tone) ?>">
        <span class="fq-verdict__big"><?= $r['hit'] ? 'HIT' : 'NO HIT' ?></span>
        <span class="fq-verdict__text"><?= $h($r['verdict_label']) ?> <span class="fq-code"><?= $h($r['verdict']) ?></span>
            <?php if ($r['level'] !== ''): ?> · <?= $h(C::label(C::WATCH_LEVELS, $r['level'], $r['level'])) ?> <span class="fq-code"><?= $h($r['level']) ?></span><?php endif; ?>
        </span>
        <?php if (is_array($r['holder'] ?? null)): ?>
            <span>Titulaire inscrit <b translate="no"><?= $h(mb_strtoupper((string) $r['holder']['last_name']) . ' ' . $r['holder']['first_name']) ?></b></span>
        <?php endif; ?>
    </div>

    <section class="fq-block">
        <h2>Mentions <span class="fq-source">Source : mentions aux fichiers, avis de recherche (BOLO)</span></h2>
        <?php if ($r['mentions'] === [] && $r['watch'] === []): ?>
            <p class="fq-empty">Aucune mention active.</p>
        <?php else: ?>
            <ul class="fq-mentions">
                <?php foreach ($r['mentions'] as $m): ?>
                    <li class="fq-mention<?= in_array($m['mention'], F::MENTIONS_WANTED, true) ? ' fq-mention--grave' : '' ?>">
                        <span><span class="fq-mention__label"><?= $h($m['mention_label']) ?></span> <span class="fq-code"><?= $h($m['mention']) ?></span></span>
                        <span class="fq-mention__meta">
                            <?php if (($m['reference'] ?? '') !== ''): ?>Réf. <span translate="no"><?= $h($m['reference']) ?></span> · <?php endif; ?>
                            Posée le <?= $h($m['created_at']) ?>
                            <?php if (($m['note'] ?? '') !== ''): ?> · <span translate="no"><?= $h($m['note']) ?></span><?php endif; ?>
                        </span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= $h(url('atak/sse/fichiers/mentions/' . (int) $m['id'] . '/lever')) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
                                <input type="hidden" name="back" value="<?= $h($back) ?>">
                                <button class="btn btn--ghost" type="submit">Lever</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($r['watch'] as $w): ?>
                    <li class="fq-mention<?= $w['level'] === 'prioritaire' ? ' fq-mention--grave' : '' ?>">
                        <span><span class="fq-mention__label">Avis de recherche</span> <span class="fq-code">watchlist · <?= $h($w['ref']) ?></span></span>
                        <span class="fq-mention__meta"><span translate="no"><?= $h($w['display_name']) ?></span> · <?= $h(C::label(C::WATCH_LEVELS, $w['level'], $w['level'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($r['identities'] !== []): ?>
        <section class="fq-block">
            <h2>Personne <span class="fq-source">Source : registre des identités</span></h2>
            <?php foreach ($r['identities'] as $i): ?>
                <dl class="fq-person">
                    <div><dt>Nom</dt><dd translate="no"><?= $h($i['display_name']) ?></dd></div>
                    <div><dt>Né(e) le</dt><dd><?= $h(C::birthDateLabel($i['birth_date'] ?? null)) ?></dd></div>
                    <div><dt>Lieu de naissance</dt><dd translate="no"><?= $h($i['birth_place'] ?? '—') ?></dd></div>
                    <div><dt>Nationalité</dt><dd><?= $h($i['nationality'] ?? '—') ?></dd></div>
                    <div><dt>Profession</dt><dd translate="no"><?= $h($i['profession'] ?? '—') ?></dd></div>
                    <div><dt>Fiche</dt><dd><a class="link" href="<?= $h(url('atak/sse/fichiers/sujet/' . (int) $i['id'])) ?>">Ouvrir la fiche <?= $h($i['ref']) ?></a></dd></div>
                </dl>
                <?php if ($canManage): ?>
                    <div class="fq-actions"><?= $mentionForm('identity', (int) $i['id']) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="fq-block">
        <h2>Papiers <span class="fq-source">Source : registre des papiers</span></h2>
        <?php if ($r['documents'] === []): ?>
            <p class="fq-empty">Aucun papier dans les fichiers.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Papier</th><th>Pays</th><th>Numéro</th><th>Titulaire</th><th>Expire le</th><th>État</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($r['documents'] as $d): ?>
                        <tr>
                            <td><a class="link" href="<?= $h(url('atak/sse/papiers/documents/' . (int) $d['id'])) ?>"><?= $h($d['doc_type_label']) ?></a> <span class="fq-code"><?= $h($d['ref']) ?></span></td>
                            <td><?= $h($d['country_code']) ?></td>
                            <td translate="no"><?= $h($d['doc_number']) ?></td>
                            <td translate="no"><?= $h($d['holder']) ?></td>
                            <td><?= $h($d['expires_on'] ?? '—') ?></td>
                            <td><span class="fq-state fq-state--<?= $h($d['state']) ?>"><?= $h($d['state_label']) ?></span> <span class="fq-code"><?= $h($d['state']) ?></span></td>
                            <td>
                                <?php if ($canManage): ?>
                                    <div class="fq-actions">
                                        <?php foreach (($d['state'] === 'stolen' || $d['state'] === 'lost') ? ['none' => 'Lever le signalement'] : ['stolen' => 'Déclarer volé', 'lost' => 'Déclarer perdu'] as $rep => $label): ?>
                                            <form method="post" action="<?= $h(url('atak/sse/fichiers/documents/' . (int) $d['id'] . '/signalement')) ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= $h((string) $csrfToken) ?>">
                                                <input type="hidden" name="back" value="<?= $h($back) ?>">
                                                <input type="hidden" name="report" value="<?= $h($rep) ?>">
                                                <button class="btn btn--ghost" type="submit"><?= $h($label) ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                    <details class="fq-details"><summary>Poser une mention sur ce papier</summary><div class="fq-actions"><?= $mentionForm('document', (int) $d['id']) ?></div></details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="fq-block">
        <h2>Fiches SEEK II <span class="fq-source">Source : enregistrements biométriques remontés du jeu</span></h2>
        <?php if ($r['seek'] === []): ?>
            <p class="fq-empty">Aucune fiche biométrique.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Enregistrement</th><th>Reçu le</th><th>Nom déclaré</th><th>Verdict</th><th>Lieu</th><th>Contrôleur</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($r['seek'] as $s): ?>
                        <tr>
                            <td translate="no"><?= $h($s['rec']) ?></td>
                            <td><?= $h($s['created_at']) ?></td>
                            <td translate="no"><?= $h($s['display_name'] !== '' ? $s['display_name'] : '—') ?></td>
                            <td><?= $h($s['verdict_label']) ?> <span class="fq-code"><?= $h($s['verdict']) ?></span></td>
                            <td translate="no"><?= $h($s['grid'] ?? '—') ?></td>
                            <td translate="no"><?= $h($s['controller_callsign'] ?? '—') ?></td>
                            <td><?php if ($canManage): ?><details class="fq-details"><summary>Poser une mention</summary><div class="fq-actions"><?= $mentionForm('seek', (int) $s['id']) ?></div></details><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="fq-block">
        <h2>Contrôles subis <span class="fq-source">Source : contrôles remontés du terrain</span></h2>
        <?php if ($r['checks'] === []): ?>
            <p class="fq-empty">Aucun contrôle enregistré.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Contrôle</th><th>Date</th><th>Lieu</th><th>Contrôleur</th><th>Niveaux</th><th>Verdict</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($r['checks'], 0, 30) as $chk): ?>
                        <tr>
                            <td><a class="link" href="<?= $h(url('atak/sse/papiers/controles/' . (int) $chk['id'])) ?>"><?= $h($chk['ref']) ?></a></td>
                            <td><?= $h($chk['created_at']) ?></td>
                            <td translate="no"><?= $h($chk['grid'] ?? '—') ?></td>
                            <td translate="no"><?= $h($chk['controller_callsign'] ?? '—') ?></td>
                            <td><?= $h(implode(' ', $chk['levels'])) ?></td>
                            <td><span class="badge idp-verdict--<?= $h($chk['verdict'] !== '' ? $chk['verdict'] : 'none') ?>"><?= $h($chk['verdict_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="fq-block">
        <h2>Codes de la réponse <span class="fq-source">Ce que le terminal en jeu reçoit</span></h2>
        <ul class="fq-codes">
            <?php foreach ($answer['flags'] as $flag): ?>
                <li><?= $h(F::flagLabel((string) $flag)) ?> <span class="fq-code" translate="no"><?= $h($flag) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <details class="fq-details">
            <summary>Réponse transmise au jeu</summary>
            <pre class="fq-json"><?= $h((string) json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
        <?php if (($replayUrl ?? '') !== ''): ?>
            <div class="fq-actions"><a class="btn btn--ghost" href="<?= $h($replayUrl) ?>">Rejouer la requête</a></div>
        <?php endif; ?>
    </section>
</article>
