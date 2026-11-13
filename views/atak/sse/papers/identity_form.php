<?php
declare(strict_types=1);
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $draft */
/** @var list<array<string,mixed>> $countries */
/** @var array<string,mixed>|null $person */
/** @var list<array{id:int,label:string}> $maps */
$canSeeTruth = !empty($canSeeTruth);
$generated = !empty($generated);
$kinds = C::KINDS;
if (!$canSeeTruth) {
    unset($kinds['cover'], $kinds['forged']);
}
$val = static fn (string $k): string => (string) ($draft[$k] ?? '');
require __DIR__ . '/_subnav.php';
?>

<div class="page-heading">
    <div>
        <h1>Nouvelle identité</h1>
        <p>Saisissez l’identité à la main, ou tirez-en une au sort, cohérente avec le pays choisi : même tirage que le générateur du mod SSE, une graine donne toujours la même personne.</p>
    </div>
</div>

<form class="panel" method="get" action="<?= $h(url('atak/sse/papiers/identites/nouvelle')) ?>">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Tirage au sort</div></div>
    <div class="panel-body">
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="gen_country">Pays</label>
                <select id="gen_country" name="country">
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= $h($c['code']) ?>" <?= $val('nationality') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="gen_sex">Sexe</label>
                <select id="gen_sex" name="sex">
                    <option value="">Au hasard</option>
                    <option value="M">Masculin</option>
                    <option value="F">Féminin</option>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="gen_kind">Nature</label>
                <select id="gen_kind" name="kind">
                    <?php foreach ($kinds as $k => $label): ?>
                        <option value="<?= $h($k) ?>" <?= $k === 'generated' ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="gen_seed">Graine (facultative)</label>
                <input id="gen_seed" name="seed" type="number" min="0" max="16777215" inputmode="numeric" placeholder="Au hasard">
            </div>
            <?php if (($draft['person_id'] ?? null) !== null): ?>
                <input type="hidden" name="person_id" value="<?= (int) $draft['person_id'] ?>">
            <?php endif; ?>
        </div>
        <div class="idp-actions" style="margin-top:10px">
            <button class="btn" type="submit" name="generer" value="1">Générer</button>
            <?php if ($generated): ?><span class="muted">Identité tirée avec la graine <?= (int) ($draft['seed'] ?? 0) ?> : relisez-la, puis enregistrez.</span><?php endif; ?>
        </div>
    </div>
</form>

<form class="panel" method="post" action="<?= $h(url('atak/sse/papiers/identites')) ?>" style="margin-top:10px">
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="seed" value="<?= $h($val('seed')) ?>">
    <div class="panel-header"><div class="panel-title"><span class="panel-index">02</span> Identité</div></div>
    <div class="panel-body">
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="kind">Nature</label>
                <select id="kind" name="kind">
                    <?php foreach ($kinds as $k => $label): ?>
                        <option value="<?= $h($k) ?>" <?= $val('kind') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="last_name">Nom</label>
                <input id="last_name" name="last_name" type="text" required maxlength="120" value="<?= $h($val('last_name')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="first_name">Prénom</label>
                <input id="first_name" name="first_name" type="text" required maxlength="120" value="<?= $h($val('first_name')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="sex">Sexe</label>
                <select id="sex" name="sex">
                    <?php foreach (C::SEXES as $k => $label): ?>
                        <option value="<?= $h($k) ?>" <?= ($val('sex') !== '' ? $val('sex') : 'X') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="birth_date">Date de naissance</label>
                <input id="birth_date" name="birth_date" type="date" value="<?= $h($val('birth_date')) ?>">
            </div>
            <div class="lab-form-field">
                <label for="birth_place">Lieu de naissance</label>
                <input id="birth_place" name="birth_place" type="text" maxlength="120" value="<?= $h($val('birth_place')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="nationality">Nationalité</label>
                <select id="nationality" name="nationality">
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= $h($c['code']) ?>" <?= $val('nationality') === $c['code'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="profession">Profession</label>
                <input id="profession" name="profession" type="text" maxlength="120" value="<?= $h($val('profession')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="person_id">Fiche personne rattachée (numéro IDN)</label>
                <input id="person_id" name="person_id" type="number" min="0" inputmode="numeric" value="<?= ($draft['person_id'] ?? 0) > 0 ? (int) $draft['person_id'] : '' ?>" placeholder="Aucune">
                <?php if (is_array($person ?? null)): ?><small class="muted"><?= $h($person['display_name'] ?? '') ?></small><?php endif; ?>
            </div>
            <div class="lab-form-field">
                <label for="arma_key">Personnage en jeu (nom de variable de l’unité)</label>
                <input id="arma_key" name="arma_key" type="text" maxlength="64" autocomplete="off" placeholder="Facultatif">
            </div>
            <div class="lab-form-field">
                <label for="context_id">Carte</label>
                <select id="context_id" name="context_id">
                    <option value="0">Toutes les cartes</option>
                    <?php foreach ($maps as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="op_key">Opération</label>
                <input id="op_key" name="op_key" type="text" maxlength="64" autocomplete="off" placeholder="Toutes les opérations">
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="notes">Notes internes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="4000"></textarea>
            </div>
        </div>
        <div class="idp-actions" style="margin-top:10px">
            <button class="btn" type="submit">Enregistrer l’identité</button>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/identites')) ?>">Annuler</a>
        </div>
    </div>
</form>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
