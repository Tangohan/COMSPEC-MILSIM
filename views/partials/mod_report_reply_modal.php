<?php
declare(strict_types=1);

// Fenêtre « l'équipe a répondu à votre signalement » : réponses de l'exploitation aux rapports envoyés depuis le mod,
// seulement quand « Notifier sur Athena » était coché (notification atak_mod_report_reply non lue).
// Vérifiée au plus toutes les 10 minutes par session ; rien n'est affiché sans réponse non lue.

use App\Support\ModReportReplyNotice;

$modReplyUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
if ($modReplyUserId < 1 || !empty($planGiftNotice)) {
    // La fenêtre « formule offerte » passe d'abord ; celle-ci viendra à la page suivante.
    return;
}
$modReplyLastCheck = (int) (\App\Core\Session::get(ModReportReplyNotice::SESSION_CHECK_KEY) ?? 0);
if ($modReplyLastCheck > time() - ModReportReplyNotice::CHECK_INTERVAL) {
    return;
}
$modReplyRows = [];
try {
    $modReplyRows = (new \App\Repositories\MemberNotificationRepository())->listUnreadOfTypeForUser(
        $modReplyUserId,
        \App\Services\Monitoring\AtakModReportReplyService::NOTIFICATION_TYPE,
        ModReportReplyNotice::MAX_AGE_DAYS,
        ModReportReplyNotice::MAX_SHOWN + 20
    );
} catch (\Throwable) {
}
if ($modReplyRows === []) {
    \App\Core\Session::set(ModReportReplyNotice::SESSION_CHECK_KEY, time());

    return;
}

$modReplyLang = '';
try {
    $modReplyProfile = (new \App\Repositories\UserProfileRepository())->getByUserId($modReplyUserId);
    $modReplyLang = trim((string) ($modReplyProfile['language'] ?? ''));
} catch (\Throwable) {
}
$modReplyLang = ModReportReplyNotice::locale($modReplyLang !== '' ? $modReplyLang : (function_exists('locale') ? locale() : 'fr'));
$modReplyStatuses = [];
try {
    $modReplyStatuses = (new \App\Repositories\AtakModReportRepository())->workflowStatusesByIds(ModReportReplyNotice::reportIds($modReplyRows));
} catch (\Throwable) {
}
$modReplyItems = ModReportReplyNotice::items($modReplyRows, $modReplyStatuses, $modReplyLang);
if ($modReplyItems === []) {
    \App\Core\Session::set(ModReportReplyNotice::SESSION_CHECK_KEY, time());

    return;
}
// Fermée sans « Compris » (croix, Échap) : la fenêtre revient à la prochaine vérification, pas à chaque page.
\App\Core\Session::set(ModReportReplyNotice::SESSION_CHECK_KEY, time());
$modReplyTotal = count($modReplyItems);
$modReplyItems = array_slice($modReplyItems, 0, ModReportReplyNotice::MAX_SHOWN);
$modReplyAction = url('compte/retours-signalement/vu');
$modReplyHubUrl = url('activite');
$modReplyCsrf = \App\Core\Csrf::field();
require __DIR__ . '/mod_report_reply_modal_view.php';
