<?php
declare(strict_types=1);
$doc = is_array($hrCharterAdminDoc ?? null) ? $hrCharterAdminDoc : [];
$csrf = htmlspecialchars((string) ($hrCharterAdminCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$body = (string) ($doc['body_html'] ?? '');
$publishedAt = trim((string) ($doc['published_at'] ?? ''));
$publishedLabel = '';
if ($publishedAt !== '') {
    $ts = strtotime($publishedAt);
    if ($ts !== false) {
        $publishedLabel = date('d/m/Y à H:i', $ts);
    }
}
$memberCharterUrl = url('account/charte-formations');
require base_path('views/admin/training/partials/command_shell_open.php');
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/training_custom_page_editor.css'), ENT_QUOTES, 'UTF-8') ?>">
                <section class="tc-panel tc-charter-admin">
                    <header class="tc-charter-admin__head">
                        <div class="min-w-0 flex-1">
                            <p class="tc-kicker mb-1.5">Engagement membres</p>
                            <h1 class="tc-hero-title !text-xl md:!text-2xl mb-1.5">Charte liée aux formations</h1>
                            <p class="text-slate-600 text-sm leading-relaxed max-w-2xl">
                                Texte présenté aux membres avant l’accès au catalogue. Une mise à jour remplace la version affichée ; les confirmations déjà enregistrées restent dans l’historique.
                            </p>
                        </div>
                        <div class="tc-charter-admin__meta shrink-0">
                            <?php if ($publishedLabel !== ''): ?>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Dernière publication</p>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5"><?= htmlspecialchars($publishedLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($memberCharterUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="tc-charter-admin__member-link">
                                Voir la page membres
                                <span aria-hidden="true">↗</span>
                            </a>
                        </div>
                    </header>

                    <form
                        method="post"
                        action="<?= htmlspecialchars(training_lms_admin_url('charte-rh'), ENT_QUOTES, 'UTF-8') ?>"
                        id="hr-charter-admin-form"
                        class="tc-charter-admin__form"
                    >
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">

                        <div class="tc-charter-admin__field">
                            <label for="hr-charter-title" class="tc-charter-admin__label">Titre affiché aux membres</label>
                            <input
                                type="text"
                                id="hr-charter-title"
                                name="title"
                                value="<?= $title ?>"
                                required
                                maxlength="255"
                                class="tc-charter-admin__input"
                                autocomplete="off"
                            >
                        </div>

                        <div class="tc-charter-admin__workspace">
                            <div class="tc-charter-admin__editor">
                                <div class="tc-charter-admin__editor-head">
                                    <label for="hr-charter-body" class="tc-charter-admin__label mb-0">Texte de la charte</label>
                                    <p class="text-xs text-slate-500">Mise en forme : titres, listes, liens. Relisez l’aperçu avant d’enregistrer.</p>
                                </div>
                                <textarea
                                    name="body_html"
                                    id="hr-charter-body"
                                    rows="14"
                                    class="tc-charter-admin__textarea"
                                ><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <aside class="tc-charter-admin__preview-pane" aria-label="Aperçu pour les membres">
                                <div class="tc-charter-admin__editor-head">
                                    <p class="tc-charter-admin__label mb-0">Aperçu</p>
                                    <p class="text-xs text-slate-500">Comme les membres le verront sur leur page d’engagement.</p>
                                </div>
                                <div id="hr-charter-preview" class="tc-charter-admin__preview prose prose-slate max-w-none text-sm"></div>
                            </aside>
                        </div>

                        <div class="tc-charter-admin__actions">
                            <p class="text-xs text-slate-500 leading-relaxed max-w-md">
                                Enregistrer publie immédiatement cette version pour les membres qui n’ont pas encore confirmé leur prise en compte.
                            </p>
                            <button type="submit" class="tc-btn-primary tc-btn-emerald">Enregistrer la charte</button>
                        </div>
                    </form>
                </section>
<script src="<?= htmlspecialchars(url('assets/js/hr_charter_admin_editor.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
