<?php
declare(strict_types=1);

$doc = is_array($hrCharterDocument ?? null) ? $hrCharterDocument : [];
$accepted = !empty($hrCharterAccepted);
$acceptedAtRaw = trim((string) ($hrCharterAcceptedAt ?? ''));
$acceptedLabel = '';
if ($acceptedAtRaw !== '') {
    $ats = strtotime($acceptedAtRaw);
    if ($ats !== false) {
        $acceptedLabel = date('d/m/Y', $ats);
    }
}
$redirect = isset($hrCharterRedirect) ? (string) $hrCharterRedirect : '';
$csrf = (string) ($hrCharterCsrf ?? '');
$docId = (int) ($doc['id'] ?? 0);
$titleDoc = trim((string) ($doc['title'] ?? ''));
$body = (string) ($doc['body_html'] ?? '');
$publishedAt = trim((string) ($doc['published_at'] ?? ''));
$publishedLabel = '';
if ($publishedAt !== '') {
    $ts = strtotime($publishedAt);
    if ($ts !== false) {
        $publishedLabel = date('d/m/Y', $ts);
    }
}

$plainBody = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
$wordCount = $plainBody === '' ? 0 : count(preg_split('/\s+/u', $plainBody, -1, PREG_SPLIT_NO_EMPTY) ?: []);
$readMinutes = $wordCount < 1 ? 0 : max(1, (int) ceil($wordCount / 180));
$readLabel = $readMinutes < 1
    ? 'Texte non rédigé'
    : ($readMinutes === 1 ? 'Environ 1 min de lecture' : 'Environ ' . $readMinutes . ' min de lecture');

$accountNavKey = 'charter';
$accountTitle = $titleDoc !== '' ? $titleDoc : 'Charte des formations';
$accountLead = $accepted
    ? 'Votre prise en compte est enregistrée. Le texte reste disponible ci-dessous si vous souhaitez le relire.'
    : 'Lisez le document jusqu’en bas, puis confirmez. Le catalogue des formations s’ouvre ensuite.';
$accountUser = is_array($user ?? null) ? $user : (is_array($accountUser ?? null) ? $accountUser : []);
require base_path('views/partials/account/shell_open.php');
?>

<div class="account-hub__stack hr-charter">
    <div class="account-hub__stat-grid hr-charter__stats">
        <article class="account-hub__stat">
            <p class="account-hub__stat-label">Situation</p>
            <p class="account-hub__stat-value <?= $accepted ? 'hr-charter__state--ok' : 'hr-charter__state--wait' ?>">
                <?= $accepted ? 'Prise en compte enregistrée' : 'À confirmer' ?>
            </p>
            <p class="account-hub__stat-meta">
                <?php if ($accepted): ?>
                    Vous pouvez relire le texte et retourner aux formations.
                <?php elseif ($redirect !== ''): ?>
                    Vous avez été dirigé ici depuis les formations.
                <?php else: ?>
                    Nécessaire avant d’ouvrir le catalogue.
                <?php endif; ?>
            </p>
        </article>
        <article class="account-hub__stat">
            <p class="account-hub__stat-label">Lecture</p>
            <p class="account-hub__stat-value"><?= htmlspecialchars($readLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="account-hub__stat-meta"><?= $accepted ? 'Document relisible à tout moment.' : 'La case s’active une fois le texte parcouru.' ?></p>
        </article>
        <?php if ($publishedLabel !== ''): ?>
        <article class="account-hub__stat">
            <p class="account-hub__stat-label">Version</p>
            <p class="account-hub__stat-value">Publiée le <?= htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="account-hub__stat-meta">Texte en vigueur pour votre communauté.</p>
        </article>
        <?php endif; ?>
        <?php if ($acceptedLabel !== ''): ?>
        <article class="account-hub__stat">
            <p class="account-hub__stat-label">Votre confirmation</p>
            <p class="account-hub__stat-value"><?= htmlspecialchars($acceptedLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="account-hub__stat-meta">Enregistrée pour cette version.</p>
        </article>
        <?php endif; ?>
    </div>

    <section class="account-hub__panel hr-charter__doc" aria-labelledby="hr-charter-doc-heading">
        <div class="account-hub__panel-head hr-charter__doc-head">
            <div>
                <p class="account-hub__panel-kicker">Document</p>
                <h2 id="hr-charter-doc-heading" class="account-hub__panel-title">
                    <?= htmlspecialchars($titleDoc !== '' ? $titleDoc : 'Charte des formations', ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($accepted): ?>
                        <span class="account-hub__badge account-hub__badge--ok">Fait</span>
                    <?php else: ?>
                        <span class="account-hub__badge account-hub__badge--warn">En attente</span>
                    <?php endif; ?>
                </h2>
            </div>
            <?php if (!$accepted): ?>
            <div class="hr-charter__progress">
                <p class="hr-charter__progress-label" id="hr-charter-progress-label">Parcours</p>
                <div class="hr-charter__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-labelledby="hr-charter-progress-label" id="hr-charter-progress">
                    <span class="hr-charter__progress-fill" id="hr-charter-progress-fill"></span>
                </div>
                <p class="hr-charter__progress-pct" id="hr-charter-progress-pct">0 %</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="hr-charter__reader">
            <div
                id="hr-charter-scroll"
                class="hr-charter__scroll<?= $accepted ? ' hr-charter__scroll--open' : '' ?>"
                tabindex="0"
                <?= $accepted ? '' : 'data-hr-charter-gate="1"' ?>
            >
                <?php if ($plainBody === ''): ?>
                <p class="account-hub__stat-meta">Le texte n’a pas encore été rédigé pour cette communauté.</p>
                <?php else: ?>
                <div class="hr-charter__prose">
                    <?= $body ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$accepted): ?>
            <div class="hr-charter__fade" aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <?php if ($accepted): ?>
        <div class="account-hub__panel-body hr-charter__actions">
            <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ink">Retour aux formations</a>
            <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--soft">Vue d’ensemble du compte</a>
        </div>
        <?php else: ?>
        <div class="hr-charter__confirm" aria-labelledby="hr-charter-confirm-heading">
            <p class="account-hub__panel-kicker">Confirmation</p>
            <h2 id="hr-charter-confirm-heading" class="account-hub__panel-title">Enregistrer votre prise en compte</h2>
            <p class="account-hub__panel-desc" id="hr-charter-hint" role="status">Faites défiler le texte jusqu’en bas pour activer la case.</p>
            <form method="post" action="<?= htmlspecialchars(url('account/charte-formations/accept'), ENT_QUOTES, 'UTF-8') ?>" class="hr-charter__form" id="hr-charter-form">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="document_id" value="<?= $docId ?>">
                <?php if ($redirect !== ''): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <label class="hr-charter__check" for="hr-charter-confirm">
                    <input type="checkbox" name="confirm" id="hr-charter-confirm" value="1" disabled>
                    <span>Je confirme avoir lu et pris connaissance de cette charte.</span>
                </label>
                <div class="hr-charter__actions">
                    <button type="submit" id="hr-charter-submit" class="account-hub__btn account-hub__btn--primary" disabled>
                        Enregistrer ma prise en compte
                    </button>
                    <button type="button" class="account-hub__btn account-hub__btn--soft" id="hr-charter-jump" hidden>
                        Aller en bas du texte
                    </button>
                </div>
            </form>
        </div>
        <script defer src="<?= htmlspecialchars(asset_url('assets/js/rh-charter.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php endif; ?>
    </section>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
