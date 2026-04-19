<?php
declare(strict_types=1);
$doc = is_array($hrCharterAdminDoc ?? null) ? $hrCharterAdminDoc : [];
$csrf = htmlspecialchars((string) ($hrCharterAdminCsrf ?? ''), ENT_QUOTES, 'UTF-8');
$title = htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$body = (string) ($doc['body_html'] ?? '');
require base_path('views/admin/training/partials/command_shell_open.php');
?>
                <header class="tc-panel p-6 md:p-8">
                    <p class="tc-kicker">Engagement membres</p>
                    <h1 class="tc-hero-title mb-4">Charte liée aux formations</h1>
                    <p class="text-slate-600 text-sm max-w-2xl leading-relaxed">
                        Texte présenté aux membres avant l’accès au catalogue. Les mises à jour remplacent la version affichée ; les confirmations déjà enregistrées restent dans l’historique.
                    </p>
                </header>
                <section class="tc-panel p-6 md:p-8 space-y-6">
                    <form method="post" action="<?= htmlspecialchars(training_lms_admin_url('charte-rh'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-5 max-w-3xl">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Titre affiché</label>
                            <input type="text" name="title" value="<?= $title ?>" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Contenu (HTML)</label>
                            <textarea name="body_html" rows="16" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <p class="mt-1 text-xs text-slate-500">Balises HTML courantes autorisées ; vérifiez la lisibilité sur mobile.</p>
                        </div>
                        <button type="submit" class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Enregistrer</button>
                    </form>
                </section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
