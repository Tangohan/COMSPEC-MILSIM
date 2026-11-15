<?php
declare(strict_types=1);

/**
 * « Aligner sur l'état civil » : bouton et fenêtre de confirmation avec l'avant / après (identité officielle d'un membre).
 * Réutilisé par la liste des papiers du registre, la fiche papiers du membre (bureau SSE et effectifs) et « Mes papiers ».
 * Les styles et le script sont à part (sse-civil-align.css, sse-civil-align.js) : le design du portail SSE pourra les reprendre.
 *
 * @var array{dom: string, post: string, csrf: string, identity_id: int, name: string, member: string, changes: list<array{field: string, label: string, old: string, new: string}>, documents: list<array<string, mixed>>, back?: string, button?: string, class?: string, self?: bool} $cal
 */
$cah = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (empty($GLOBALS['__sse_civil_align_assets'])) {
    $GLOBALS['__sse_civil_align_assets'] = true;
    echo '<link rel="stylesheet" href="' . $cah(asset_url('assets/css/sse-civil-align.css')) . '">';
    echo '<script src="' . $cah(asset_url('assets/js/sse-civil-align.js')) . '" defer></script>';
}
$calButton = (string) ($cal['button'] ?? 'Aligner sur l’état civil');
$calSelf = !empty($cal['self']);
?>
<button type="button" class="<?= $cah($cal['class'] ?? 'btn') ?> civil-align__open" data-civil-open="<?= $cah($cal['dom']) ?>"><?= $cah($calButton) ?></button>
<dialog class="civil-align" id="<?= $cah($cal['dom']) ?>" aria-labelledby="<?= $cah($cal['dom']) ?>-title">
    <form method="post" action="<?= $cah($cal['post']) ?>" class="civil-align__card">
        <input type="hidden" name="_csrf_token" value="<?= $cah($cal['csrf']) ?>">
        <input type="hidden" name="identity_id" value="<?= (int) $cal['identity_id'] ?>">
        <?php if (($cal['back'] ?? '') !== ''): ?><input type="hidden" name="back" value="<?= $cah($cal['back']) ?>"><?php endif; ?>
        <header class="civil-align__head">
            <h2 id="<?= $cah($cal['dom']) ?>-title"><?= $cah($calButton) ?></h2>
            <button type="button" class="civil-align__close" data-civil-close aria-label="Fermer">×</button>
        </header>
        <div class="civil-align__body">
            <?php if ($calSelf): ?>
                <p>Votre identité officielle reprendra votre état civil.</p>
            <?php else: ?>
                <p>L’identité officielle de ce membre reprendra son état civil.</p>
            <?php endif; ?>
            <p>Les anciennes valeurs restent dans l’historique de l’identité et peuvent être restaurées.</p>
            <table class="civil-align__table">
                <thead><tr><th scope="col">Champ</th><th scope="col">Avant</th><th scope="col">Après</th></tr></thead>
                <tbody>
                <?php foreach ($cal['changes'] as $calChange): ?>
                    <tr>
                        <th scope="row"><?= $cah($calChange['label']) ?></th>
                        <td translate="no"><?= $cah($calChange['old'] !== '' ? $calChange['old'] : '—') ?></td>
                        <td translate="no"><b><?= $cah($calChange['new'] !== '' ? $calChange['new'] : '—') ?></b></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($cal['documents'] !== []): ?>
                <p>Les papiers officiels de cette identité porteront la nouvelle valeur ; leur numéro, leur autorité et leurs dates ne changent pas :</p>
                <ul class="civil-align__docs">
                    <?php foreach ($cal['documents'] as $calDoc): ?>
                        <li translate="no"><?= $cah($calDoc['ref']) ?> · <?= $cah($calDoc['type']) ?> · <?= $cah($calDoc['country']) ?> <?= $cah($calDoc['number']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="civil-align__note">Les identités de couverture et leurs papiers ne sont pas modifiés.</p>
        </div>
        <footer class="civil-align__foot">
            <button type="button" class="btn btn--ghost civil-align__cancel" data-civil-close>Annuler</button>
            <button type="submit" class="btn civil-align__confirm"><?= $cah($calButton) ?></button>
        </footer>
    </form>
</dialog>
