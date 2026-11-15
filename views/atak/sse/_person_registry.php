<?php
declare(strict_types=1);
/**
 * Fiche personne : bloc « Dossier d'identité du registre ». Mène à la fiche du registre (avis de recherche, notice PDF,
 * historique, détections, images du sujet), ou propose de la créer quand la personne n'en a pas. Inclus par person_show.php.
 *
 * @var array<string, mixed> $registry données de SsePersonRegistryLinkService::pageData (vide si indisponible)
 * @var array<string, mixed> $person
 */
use App\Core\Csrf;
use App\Support\SseWantedCatalog as W;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$registry = is_array($registry ?? null) ? $registry : [];
$rIdentity = is_array($registry['identity'] ?? null) ? $registry['identity'] : null;
$rNotice = is_array($registry['notice'] ?? null) ? $registry['notice'] : null;
$rLang = ($registry['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
$rPid = (int) ($person['id'] ?? 0);
if ($registry === []) {
    return;
}
?>
<section class="panel" id="registre" style="margin-bottom:12px">
    <div class="panel-header"><div class="panel-title">Dossier d’identité du registre</div></div>
    <div class="panel-body">
        <?php if ($rIdentity !== null): ?>
            <p>
                Cette personne a une identité au registre : <strong translate="no"><?= $h($rIdentity['display_name']) ?></strong> (<span translate="no"><?= $h($rIdentity['ref']) ?></span>).
                <?php if ($rNotice !== null): ?><span class="badge badge--red">Sous avis de recherche · <?= $h($rNotice['ref']) ?> · menace <?= $h(mb_strtolower(W::label(W::THREATS, (string) $rNotice['threat_level'], $rLang))) ?></span><?php endif; ?>
            </p>
            <div class="idp-actions">
                <a class="btn" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $rIdentity['id'])) ?>">Ouvrir le dossier d’identité</a>
                <?php if (!empty($registry['canWanted']) && $rNotice === null): ?>
                    <a class="btn btn--danger" href="<?= $h(url('atak/sse/papiers/identites/' . (int) $rIdentity['id'] . '/avis/nouveau')) ?>">Placer un avis de recherche</a>
                <?php endif; ?>
                <?php if (!empty($registry['canPdf']) && $rNotice !== null): ?>
                    <a class="btn" href="<?= $h(url('atak/sse/avis/' . (int) $rNotice['id'] . '/pdf')) ?>">Notice PDF</a>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($registry['hidden'])): ?>
            <p class="muted">Le dossier d’identité de cette personne relève de la vérité de jeu : il demande l’habilitation Confidentiel.</p>
        <?php else: ?>
            <p class="muted">Aucune identité du registre n’est rattachée à cette personne. Créez-la pour pouvoir placer un avis de recherche, éditer la notice PDF et suivre l’historique et les détections.</p>
            <?php if (!empty($registry['canCreate'])): ?>
                <form method="post" action="<?= $h(url('atak/sse/identites/' . $rPid . '/registre')) ?>">
                    <?= Csrf::field() ?>
                    <button class="btn" type="submit">Créer l’identité du registre</button>
                </form>
            <?php elseif (!empty($registry['createDenial'])): ?>
                <p class="muted" role="note"><?= $h($registry['createDenial']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php if (($registry['seekImages'] ?? []) !== []): ?>
    <?php $seekImages = $registry['seekImages']; $fileMentions = is_array($registry['fileMentions'] ?? null) ? $registry['fileMentions'] : []; ?>
    <section class="panel" style="margin-bottom:12px">
        <div class="panel-header"><div class="panel-title">Images du sujet (SEEK II)</div></div>
        <div class="panel-body"><?php require __DIR__ . '/papers/_seek_images.php'; ?></div>
    </section>
<?php endif; ?>
