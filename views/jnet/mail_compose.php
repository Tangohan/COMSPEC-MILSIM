<?php
declare(strict_types=1);
/**
 * Rédaction d’un message d’unité : destinataires nominatifs et groupes de diffusion.
 *
 * @var list<array<string,mixed>> $mailBoxes
 * @var list<array{key:string,label:string,description:string,count:int,kind:string}> $mailGroups
 * @var list<array<string,mixed>> $mailDirectory
 * @var array<string,array{label:string,hint:string}> $mailPrecedences
 * @var array{subject:string,body:string,precedence:string,groups:list<string>,members:list<int>} $mailDraft
 * @var int $mailEmailLimit
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$boxes = is_array($mailBoxes ?? null) ? $mailBoxes : [];
$groups = is_array($mailGroups ?? null) ? $mailGroups : [];
$directory = is_array($mailDirectory ?? null) ? $mailDirectory : [];
$precedences = is_array($mailPrecedences ?? null) ? $mailPrecedences : [];
$draft = is_array($mailDraft ?? null) ? $mailDraft : [];
$emailLimit = (int) ($mailEmailLimit ?? 25);
$checkedGroups = array_flip(array_map('strval', (array) ($draft['groups'] ?? [])));
$checkedMembers = array_flip(array_map('intval', (array) ($draft['members'] ?? [])));
$currentPrecedence = (string) ($draft['precedence'] ?? 'routine');

$groupsByKind = ['unite' => [], 'orbat' => [], 'liste' => []];
foreach ($groups as $g) {
    $kind = (string) ($g['kind'] ?? 'orbat');
    $groupsByKind[$kind][] = $g;
}
$kindLabels = [
    'unite' => 'Diffusion générale',
    'orbat' => 'Formations de l’unité',
    'liste' => 'Listes de diffusion enregistrées',
];
?>
<div class="jnet-mail jnet-mail--compose">
    <nav class="jnet-mail__folders" aria-label="Boîtes de messagerie">
        <div class="jnet-panel__head"><h2>Messagerie</h2></div>
        <a class="jnet-mail__compose is-active" href="<?= $h(url('jnet/courrier/nouveau')) ?>">Rédiger un message</a>
        <?php foreach ($boxes as $b): ?>
            <?php $count = (int) ($b['count'] ?? 0); ?>
            <a href="<?= $h((string) ($b['href'] ?? '#')) ?>">
                <span><?= $h((string) ($b['label'] ?? '')) ?></span>
                <?php if ($count > 0): ?><i><?= $count ?></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="jnet-compose" method="post" action="<?= $h(url('jnet/courrier/nouveau')) ?>">
        <?= \App\Core\Csrf::field() ?>

        <div class="jnet-panel__head">
            <h2>Nouveau message d’unité</h2>
            <span class="jnet-meta">Diffusion interne — consultations journalisées</span>
        </div>

        <div class="jnet-compose__grid">
            <div class="jnet-field jnet-field--wide">
                <label for="jnet-subject">Objet du message</label>
                <input id="jnet-subject" name="subject" type="text" maxlength="180"
                       placeholder="Ex. Consignes de préparation pour la sortie de dimanche"
                       value="<?= $h((string) ($draft['subject'] ?? '')) ?>">
                <p class="jnet-field__hint">Un objet clair aide les destinataires à retrouver le message plus tard.</p>
            </div>

            <div class="jnet-field">
                <label for="jnet-precedence">Niveau d’urgence</label>
                <select id="jnet-precedence" name="precedence">
                    <?php foreach ($precedences as $key => $meta): ?>
                        <option value="<?= $h((string) $key) ?>" <?= $currentPrecedence === (string) $key ? 'selected' : '' ?>>
                            <?= $h((string) $meta['label']) ?> — <?= $h((string) $meta['hint']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="jnet-field__hint">Le niveau apparaît en tête du message dans la boîte des destinataires.</p>
            </div>
        </div>

        <?php if ($groups === [] && $directory === []): ?>
            <p class="jnet-empty" style="padding:1rem;">
                Aucun membre actif n’est encore rattaché à cette unité : la messagerie sera disponible dès que
                des comptes seront enregistrés et affectés.
            </p>
        <?php else: ?>
            <div class="jnet-compose__cols">
                <section class="jnet-compose__block" aria-label="Groupes de destinataires">
                    <h3>Groupes de destinataires</h3>
                    <p class="jnet-field__hint">
                        Un groupe envoie le message à tous ses membres actifs. Les doublons sont automatiquement écartés.
                    </p>
                    <?php foreach ($kindLabels as $kind => $kindLabel): ?>
                        <?php if ($groupsByKind[$kind] === []) { continue; } ?>
                        <p class="jnet-compose__legend"><?= $h($kindLabel) ?></p>
                        <ul class="jnet-picker jnet-picker--groups">
                            <?php foreach ($groupsByKind[$kind] as $g): ?>
                                <?php $key = (string) $g['key']; $id = 'grp-' . preg_replace('/[^a-z0-9]+/i', '-', $key); ?>
                                <li>
                                    <input type="checkbox" id="<?= $h($id) ?>" name="groups[]" value="<?= $h($key) ?>"
                                           data-count="<?= (int) $g['count'] ?>"
                                        <?= isset($checkedGroups[$key]) ? 'checked' : '' ?>>
                                    <label for="<?= $h($id) ?>">
                                        <strong><?= $h((string) $g['label']) ?></strong>
                                        <span><?= (int) $g['count'] ?> membre<?= (int) $g['count'] > 1 ? 's' : '' ?></span>
                                        <small><?= $h((string) $g['description']) ?></small>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </section>

                <section class="jnet-compose__block" aria-label="Destinataires nominatifs">
                    <h3>Destinataires nominatifs</h3>
                    <div class="jnet-field">
                        <label class="sr-only" for="jnet-people-filter">Filtrer l’annuaire</label>
                        <input id="jnet-people-filter" type="search" autocomplete="off"
                               placeholder="Filtrer par nom, indicatif, section ou fonction…">
                    </div>
                    <ul class="jnet-picker jnet-picker--people" id="jnet-people">
                        <?php foreach ($directory as $m): ?>
                            <?php
                            $mid = (int) ($m['id'] ?? 0);
                            $haystack = mb_strtolower(trim(
                                (string) ($m['name'] ?? '') . ' ' . (string) ($m['callsign'] ?? '') . ' '
                                . (string) ($m['unit'] ?? '') . ' ' . (string) ($m['function'] ?? '')
                            ));
                            ?>
                            <li data-search="<?= $h($haystack) ?>">
                                <input type="checkbox" id="mbr-<?= $mid ?>" name="members[]" value="<?= $mid ?>"
                                    <?= isset($checkedMembers[$mid]) ? 'checked' : '' ?>>
                                <label for="mbr-<?= $mid ?>">
                                    <?php if (!empty($m['photo'])): ?>
                                        <img src="<?= $h((string) $m['photo']) ?>" alt="">
                                    <?php else: ?>
                                        <span class="jnet-picker__initials"><?= $h((string) ($m['initials'] ?? '??')) ?></span>
                                    <?php endif; ?>
                                    <span class="jnet-picker__id">
                                        <strong><?= $h((string) ($m['name'] ?? '')) ?></strong>
                                        <small><?= $h((string) ($m['unit'] ?? '—')) ?> · <?= $h((string) ($m['function'] ?? '—')) ?></small>
                                    </span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="jnet-empty jnet-picker__empty" id="jnet-people-empty" hidden>Aucun membre ne correspond à ce filtre.</p>
                </section>
            </div>

            <div class="jnet-field jnet-field--wide">
                <label for="jnet-body">Corps du message</label>
                <textarea id="jnet-body" name="body" rows="9" required
                          placeholder="Rédigez votre message : contexte, ce qui est attendu, échéance."><?= $h((string) ($draft['body'] ?? '')) ?></textarea>
                <p class="jnet-field__hint">
                    Au-delà de <?= $emailLimit ?> destinataires, le message reste dans la messagerie sans envoi d’e-mail
                    pour éviter de saturer les boîtes personnelles.
                </p>
            </div>

            <div class="jnet-compose__foot">
                <p class="jnet-compose__recap" id="jnet-recap">Aucun destinataire sélectionné pour l’instant.</p>
                <div class="jnet-mail__actions">
                    <a class="jnet-btn" href="<?= $h(url('jnet/courrier')) ?>">Annuler</a>
                    <button class="jnet-btn jnet-btn--solid" type="submit">Transmettre le message</button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
(function () {
    var form = document.querySelector('.jnet-compose');
    if (!form) { return; }

    var filter = document.getElementById('jnet-people-filter');
    var people = document.getElementById('jnet-people');
    var emptyNote = document.getElementById('jnet-people-empty');
    var recap = document.getElementById('jnet-recap');

    function refreshRecap() {
        var groups = form.querySelectorAll('input[name="groups[]"]:checked');
        var members = form.querySelectorAll('input[name="members[]"]:checked');
        var parts = [];
        var reach = 0;
        groups.forEach(function (input) {
            reach += parseInt(input.getAttribute('data-count') || '0', 10);
        });
        if (groups.length > 0) {
            parts.push(groups.length + (groups.length > 1 ? ' groupes' : ' groupe') + ' (' + reach + ' membre' + (reach > 1 ? 's' : '') + ' au total)');
        }
        if (members.length > 0) {
            parts.push(members.length + (members.length > 1 ? ' destinataires nominatifs' : ' destinataire nominatif'));
        }
        recap.textContent = parts.length === 0
            ? 'Aucun destinataire sélectionné pour l’instant.'
            : 'Sélection : ' + parts.join(' + ') + '. Les doublons seront écartés à l’envoi.';
    }

    form.addEventListener('change', function (event) {
        if (event.target && event.target.type === 'checkbox') { refreshRecap(); }
    });
    refreshRecap();

    if (filter && people) {
        filter.addEventListener('input', function () {
            var term = filter.value.trim().toLowerCase();
            var visible = 0;
            people.querySelectorAll('li').forEach(function (row) {
                var match = term === '' || (row.getAttribute('data-search') || '').indexOf(term) !== -1;
                row.hidden = !match;
                if (match) { visible++; }
            });
            if (emptyNote) { emptyNote.hidden = visible !== 0; }
        });
    }
})();
</script>
