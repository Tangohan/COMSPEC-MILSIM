<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $identity */
/** @var list<array<string,mixed>> $countries */
/** @var array<string,mixed>|null $country */
/** @var string $docType */
/** @var array<string,string> $proposal */
/** @var list<string> $mrzPreview */
/** @var list<array{id:int,label:string}> $maps */
$canSeeTruth = !empty($canSeeTruth);
$defaultOrigin = match ($identity['kind']) {
    'cover' => 'cover',
    'forged' => 'forged',
    'generated' => 'generated',
    default => 'official',
};
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1><?= $h(C::label(C::DOC_TYPES, $docType)) ?> — <?= $h($identity['display_name']) ?></h1>
        <p>Le numéro proposé suit le format du pays et n’est attribué à aucun autre papier. Le papier est créé en brouillon : il ne part en jeu qu’une fois validé puis émis.</p>
    </div>
</div>

<?php if ($country === null): ?>
    <div class="empty-state"><div class="empty-state-inner"><strong>Aucun pays émetteur</strong><p>Les pays fictifs de la communauté n’ont pas pu être chargés.</p></div></div>
<?php else: ?>
<form class="panel" method="post" action="<?= $h(url('atak/sse/papiers/documents')) ?>">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>">
    <input type="hidden" name="doc_type" value="<?= $h($docType) ?>">
    <input type="hidden" name="country_code" value="<?= $h($country['code']) ?>">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Papier</div><div class="panel-meta"><?= $h($country['name']) ?> (<?= $h($country['code']) ?>) · <span class="badge badge--amber">Pays fictif</span></div></div>
    <div class="panel-body">
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="doc_number">Numéro</label>
                <input id="doc_number" name="doc_number" type="text" maxlength="20" value="<?= $h($proposal['doc_number']) ?>" autocomplete="off" style="font-family:monospace">
                <small class="muted">Format du pays : <?= $h($country['number_format']) ?> (« # » chiffre, « @ » lettre).</small>
            </div>
            <div class="lab-form-field">
                <label for="issued_on">Délivré le</label>
                <input id="issued_on" name="issued_on" type="date" value="<?= $h($proposal['issued_on']) ?>">
            </div>
            <div class="lab-form-field">
                <label for="expires_on">Expire le</label>
                <input id="expires_on" name="expires_on" type="date" value="<?= $h($proposal['expires_on']) ?>">
            </div>
            <div class="lab-form-field">
                <label for="authority">Autorité de délivrance</label>
                <input id="authority" name="authority" type="text" maxlength="160" value="<?= $h($proposal['authority']) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="context_id">Carte</label>
                <select id="context_id" name="context_id">
                    <option value="0">Toutes les cartes</option>
                    <?php foreach ($maps as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= ($identity['context_id'] ?? 0) === $m['id'] ? 'selected' : '' ?>><?= $h($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="op_key">Opération</label>
                <input id="op_key" name="op_key" type="text" maxlength="64" value="<?= $h($identity['op_key'] ?? '') ?>" autocomplete="off" placeholder="Toutes les opérations">
            </div>
            <?php if ($canSeeTruth): ?>
                <div class="lab-form-field">
                    <label for="st_origin">Origine</label>
                    <select id="st_origin" name="st_origin">
                        <?php foreach (C::ORIGINS as $k => $label): ?>
                            <option value="<?= $h($k) ?>" <?= $defaultOrigin === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lab-form-field">
                    <label for="forgery_kind">Type de falsification</label>
                    <select id="forgery_kind" name="forgery_kind">
                        <option value="">Aucune</option>
                        <?php foreach (C::FORGERY_KINDS as $k => $label): ?>
                            <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lab-form-field">
                    <label for="forgery_quality">Qualité du faux (0 à 100)</label>
                    <input id="forgery_quality" name="forgery_quality" type="number" min="0" max="100" value="50">
                </div>
            <?php endif; ?>
            <div class="lab-form-field lab-form-field--span2">
                <label for="notes">Notes internes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="4000"></textarea>
            </div>
        </div>
        <?php if ($mrzPreview !== []): ?>
            <p class="muted" style="margin-top:12px">Bande de lecture calculée avec ce numéro (elle suit vos modifications une fois le papier enregistré) :</p>
            <div class="idp-mrz" translate="no"><?= $h(implode("\n", $mrzPreview)) ?></div>
        <?php endif; ?>
        <div class="idp-actions" style="margin-top:12px">
            <button class="btn" type="submit">Créer le brouillon</button>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $identity['id'])) ?>">Annuler</a>
        </div>
    </div>
</form>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
