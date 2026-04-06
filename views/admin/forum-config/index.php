<?php
$categories = $categories ?? [];
$forumConfig = $forumConfig ?? [];
$bannedWords = $bannedWords ?? [];
$blacklistedDomains = $blacklistedDomains ?? [];
$baseUrl = url('');
$csrfToken = \App\Core\Csrf::token();
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$forumName = $forumConfig['name'] ?? 'Forum';
/** Accès au brief / forum pour les membres (réglage plateforme — aligné sur forum_disabled_for_member_response). */
$forumEnabled = function_exists('forum_is_enabled') ? forum_is_enabled() : true;
$communitySectionOn = !empty($forumConfig['community_section_enabled']);

if (!function_exists('forum_admin_setting_bool')) {
    /**
     * @param mixed $raw
     */
    function forum_admin_setting_bool($raw, bool $default = false): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}

$fcScopeLabels = [
    'general' => 'Membres',
    'platform' => 'Plateforme entière',
    'organization' => 'Unité / organisation',
    'moderation' => 'Modération',
];

/**
 * @param mixed $cat
 */
$fcScopeLabel = static function ($cat) use ($fcScopeLabels): string {
    $scope = is_array($cat) ? ($cat['scope'] ?? 'general') : 'general';

    return $fcScopeLabels[$scope] ?? $fcScopeLabels['general'];
};

$forumIdentityName = trim((string) ($forumConfig['forum_name'] ?? $forumConfig['name'] ?? ''));

$forumSettingGroups = [
    [
        'id' => 'fc-essentiel',
        'title' => 'Titre et accès',
        'summary' => 'Nom du forum affiché dans le brief, section dédiée à votre unité et message affiché aux membres si cette section est fermée.',
        'defaultOpen' => true,
        'fields' => [
            [
                'key' => 'forum_name',
                'type' => 'text',
                'label' => 'Titre du forum',
                'help' => 'Affiché en tête du forum et dans le navigateur.',
                'value' => $forumIdentityName,
            ],
            [
                'key' => 'forum_subtitle',
                'type' => 'text',
                'label' => 'Sous-titre',
                'help' => 'Courte ligne sous le titre (optionnel).',
                'value' => (string) ($forumConfig['forum_subtitle'] ?? $forumConfig['subtitle'] ?? ''),
            ],
            [
                'key' => 'forum_tagline',
                'type' => 'text',
                'label' => 'Accroche',
                'help' => 'Phrase d’introduction sur la page d’accueil du forum.',
                'value' => (string) ($forumConfig['forum_tagline'] ?? $forumConfig['tagline'] ?? ''),
            ],
            [
                'key' => 'forum_context',
                'type' => 'text',
                'label' => 'Mention de contexte',
                'help' => 'Petit libellé de contexte (ex. organisation ou mission).',
                'value' => (string) ($forumConfig['forum_context'] ?? $forumConfig['context'] ?? ''),
            ],
            [
                'key' => 'forum_community_section_enabled',
                'type' => 'toggle',
                'label' => 'Section « unité » visible dans le brief',
                'help' => 'L’espace réservé à votre communauté dans le brief (canaux internes d’unité). Désactivez-le pour masquer cette partie aux membres tout en laissant le reste du brief ouvert (annonces générales, etc.). Les personnes habilitées à la modération ou au back-office voient encore cette section. Pour fermer tout le brief pour tout le monde, le réglage se fait côté administration plateforme.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_community_section_enabled'] ?? null, $communitySectionOn),
            ],
            [
                'key' => 'forum_community_section_notice',
                'type' => 'textarea',
                'label' => 'Message si la section unité est fermée',
                'help' => 'Texte affiché aux membres à la place des canaux d’unité (ex. consigne pour rejoindre un canal vocal externe). Laisser vide pour un message par défaut.',
                'value' => (string) ($forumConfig['forum_community_section_notice'] ?? $forumConfig['community_section_notice'] ?? ''),
            ],
            [
                'key' => 'forum_guest_read',
                'type' => 'toggle',
                'label' => 'Lecture possible sans être connecté',
                'help' => 'Enregistré pour référence : sur ce portail, le forum reste accessible uniquement après connexion.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_guest_read'] ?? null, false),
            ],
        ],
    ],
    [
        'id' => 'fc-affichage',
        'title' => 'Affichage et rôles',
        'summary' => 'Image d’en-tête et libellés liés aux rôles.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_hero_image_url',
                'type' => 'text',
                'label' => 'Image d’en-tête',
                'help' => 'Adresse complète en https ou chemin commençant par / (bannière large, fichier léger, de préférence WebP ou JPEG).',
                'value' => (string) ($forumConfig['forum_hero_image_url'] ?? ''),
            ],
            [
                'key' => 'forum_role_read_label',
                'type' => 'text',
                'label' => 'Libellé « lecture seule »',
                'help' => 'Texte affiché pour les profils qui ne peuvent qu’observer.',
                'value' => (string) ($forumConfig['forum_role_read_label'] ?? ''),
            ],
            [
                'key' => 'forum_role_write_label',
                'type' => 'text',
                'label' => 'Libellé « peut participer »',
                'help' => 'Texte affiché pour les profils autorisés à publier.',
                'value' => (string) ($forumConfig['forum_role_write_label'] ?? ''),
            ],
        ],
    ],
    [
        'id' => 'fc-listes',
        'title' => 'Listes et rythme',
        'summary' => 'Pagination et délai entre deux messages.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_topics_per_page',
                'type' => 'number',
                'label' => 'Sujets par page',
                'help' => 'Nombre de sujets listés dans un canal.',
                'value' => (string) ($forumConfig['forum_topics_per_page'] ?? ''),
                'attrs' => 'min="1" max="200" step="1"',
            ],
            [
                'key' => 'forum_posts_per_page',
                'type' => 'number',
                'label' => 'Messages par page',
                'help' => 'Nombre de réponses visibles avant pagination.',
                'value' => (string) ($forumConfig['forum_posts_per_page'] ?? ''),
                'attrs' => 'min="1" max="200" step="1"',
            ],
            [
                'key' => 'forum_cooldown_seconds',
                'type' => 'number',
                'label' => 'Pause entre deux envois (secondes)',
                'help' => 'Réduit le spam en imposant un court délai entre deux messages d’un même membre.',
                'value' => (string) ($forumConfig['forum_cooldown_seconds'] ?? ''),
                'attrs' => 'min="0" max="86400" step="1"',
            ],
            [
                'key' => 'forum_max_post_length',
                'type' => 'number',
                'label' => 'Longueur maximale d’un message (caractères)',
                'help' => 'Plafond pour un sujet ou une réponse. Les pièces jointes ne comptent pas dans cette limite.',
                'value' => (string) ($forumConfig['forum_max_post_length'] ?? ''),
                'attrs' => 'min="500" max="200000" step="1"',
            ],
        ],
    ],
    [
        'id' => 'fc-antispam',
        'title' => 'Anti-spam',
        'summary' => 'Filtres automatiques sur la longueur des messages.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_antispam_enabled',
                'type' => 'toggle',
                'label' => 'Règles de longueur minimale',
                'help' => 'Rejette les messages trop courts souvent utilisés pour le spam.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_antispam_enabled'] ?? null, false),
            ],
            [
                'key' => 'forum_antispam_min_length',
                'type' => 'number',
                'label' => 'Longueur minimale (caractères)',
                'help' => 'Nombre minimum de caractères pour accepter un message.',
                'value' => (string) ($forumConfig['forum_antispam_min_length'] ?? ''),
                'attrs' => 'min="1" max="5000" step="1"',
            ],
        ],
    ],
    [
        'id' => 'fc-pj',
        'title' => 'Pièces jointes',
        'summary' => 'Taille maximale et types de fichiers acceptés.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_attachments_max_size',
                'type' => 'number',
                'label' => 'Taille maximale (octets)',
                'help' => 'Plafond par fichier. Vérifiez aussi la limite côté serveur.',
                'value' => (string) ($forumConfig['forum_attachments_max_size'] ?? ''),
                'attrs' => 'min="0" step="1"',
            ],
            [
                'key' => 'forum_attachments_allowed_ext',
                'type' => 'text',
                'label' => 'Types de fichiers autorisés',
                'help' => 'Uniquement parmi : jpg, jpeg, png, gif, webp, pdf — listez-les séparées par une virgule. Laisser vide pour tout autoriser.',
                'value' => (string) ($forumConfig['forum_attachments_allowed_ext'] ?? ''),
            ],
        ],
    ],
    [
        'id' => 'fc-liens',
        'title' => 'Liens externes',
        'summary' => 'Avertissement avant d’ouvrir un site tiers.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_url_gate_enabled',
                'type' => 'toggle',
                'label' => 'Afficher une page d’avertissement',
                'help' => 'Avant d’ouvrir un site externe depuis un message, afficher une page de confirmation avec délai de sécurité.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_url_gate_enabled'] ?? null, true),
            ],
        ],
    ],
    [
        'id' => 'fc-notif',
        'title' => 'Notifications',
        'summary' => 'Alertes envoyées à l’équipe de modération.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_notify_moderators',
                'type' => 'toggle',
                'label' => 'Prévenir les modérateurs',
                'help' => 'Lorsque l’analyse automatique signale un message, envoyer une alerte dans le centre de notifications forum (icône cloche) aux rôles de direction et modération.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_notify_moderators'] ?? null, false),
            ],
        ],
    ],
    [
        'id' => 'fc-auto',
        'title' => 'Automatisation',
        'summary' => 'Sandbox et assistant automatique (selon modules actifs).',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_sandbox_enabled',
                'type' => 'toggle',
                'label' => 'File d’attente « bac à sable »',
                'help' => 'Les messages signalés par l’analyse automatique restent invisibles pour les membres jusqu’à validation par un modérateur.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_sandbox_enabled'] ?? null, false),
            ],
            [
                'key' => 'forum_bot_enabled',
                'type' => 'toggle',
                'label' => 'Assistant de modération',
                'help' => 'Analyse les nouveaux messages (mots et sites interdits, liens multiples) et alimente le journal de modération. À désactiver seulement pour diagnostic.',
                'checked' => forum_admin_setting_bool($forumConfig['forum_bot_enabled'] ?? null, true),
            ],
        ],
    ],
    [
        'id' => 'fc-modo-texte',
        'title' => 'Aide aux modérateurs',
        'summary' => 'Texte d’information affiché dans l’espace modération.',
        'defaultOpen' => false,
        'fields' => [
            [
                'key' => 'forum_moderation_tutorial_html',
                'type' => 'textarea',
                'label' => 'Contenu d’aide (HTML simple)',
                'help' => 'Rappels internes, procédures, contacts. Utilisez du HTML simple (paragraphes, listes).',
                'value' => (string) ($forumConfig['forum_moderation_tutorial_html'] ?? ''),
            ],
        ],
    ],
];
?>
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-12" x-data="forumConfigPage()">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Administration</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900">Configuration du forum</h1>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
                Organisez les canaux, puis ajustez les options d’accès et de comportement. Les changements des réglages sont appliqués après « Enregistrer les réglages » en bas de chaque bloc.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold <?= $forumEnabled ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' ?>">
                    <?= $forumEnabled ? 'Forum ouvert' : 'Forum désactivé pour les membres' ?>
                </span>
                <span class="text-xs text-slate-500">Titre affiché : <?= htmlspecialchars($forumName) ?></span>
            </div>
        </div>
        <div class="flex shrink-0 flex-col gap-2 sm:items-end">
            <a href="<?= htmlspecialchars($baseUrl) ?>/forum" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-emerald-300 hover:text-emerald-900">
                Ouvrir le forum
            </a>
            <a href="<?= htmlspecialchars($baseUrl) ?>/forum/moderation" class="text-center text-sm font-medium text-slate-600 hover:text-slate-900">Espace modération →</a>
        </div>
    </div>

    <div x-show="banner" x-cloak x-transition class="mb-6 rounded-xl border px-4 py-3 text-sm"
         :class="banner.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900'"
         x-text="banner.text"></div>

    <?php if ($success): ?>
    <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
    <p class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <nav class="mb-10 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Sections de la page">
        <a href="#fc-categories" class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100 hover:text-slate-900">Canaux</a>
        <a href="#fc-settings" class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100 hover:text-slate-900">Réglages</a>
        <a href="#fc-filters" class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100 hover:text-slate-900">Filtres</a>
        <a href="#fc-tools" class="rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-600 hover:bg-slate-100 hover:text-slate-900">Outils</a>
    </nav>

    <!-- Canaux -->
    <section id="fc-categories" class="mb-12 scroll-mt-24">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Canaux du forum</h2>
                <p class="mt-1 text-sm text-slate-600">Une ligne = un canal principal ; les sous-canaux sont indentés. Créez d’abord les canaux racine, puis les sous-canaux si besoin.</p>
            </div>
            <button type="button" @click="fcOpenCreate(null)" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                Nouveau canal
            </button>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <?php if (empty($categories)): ?>
            <p class="p-8 text-center text-sm text-slate-600">Aucun canal pour l’instant. Utilisez « Nouveau canal » pour créer le premier (ex. Annonces, Opérations, Débrief).</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Ordre</th>
                            <th class="px-4 py-3">Nom</th>
                            <th class="px-4 py-3">Adresse dans l’URL</th>
                            <th class="px-4 py-3">Qui voit ce canal</th>
                            <th class="px-4 py-3">Publications</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($categories as $cat): ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600"><?= (int)($cat['display_order'] ?? 0) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars($cat['name'] ?? '') ?></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars($cat['slug'] ?? '') ?></td>
                            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($fcScopeLabel($cat)) ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($cat['is_locked'])): ?>
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Fermé</span>
                                <?php else: ?>
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Ouvert</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button type="button" @click="fcOpenCreate(<?= (int)($cat['id']) ?>)" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Sous-canal</button>
                                    <button type="button" @click='fcOpenEdit(<?= json_encode($cat) ?>)' class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Modifier</button>
                                    <button type="button" @click="fcLock(<?= (int)($cat['id']) ?>, <?= !empty($cat['is_locked']) ? 'false' : 'true' ?>)" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"><?= !empty($cat['is_locked']) ? 'Rouvrir' : 'Fermer' ?></button>
                                    <button type="button" @click="fcDelete(<?= (int)($cat['id']) ?>)" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Supprimer</button>
                                </div>
                            </td>
                        </tr>
                        <?php foreach ($cat['children'] ?? [] as $sub): ?>
                        <tr class="bg-slate-50/50 hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 pl-8 text-slate-400">↳</td>
                            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($sub['name'] ?? '') ?></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars($sub['slug'] ?? '') ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($fcScopeLabel($sub + ['scope' => $sub['scope'] ?? ($cat['scope'] ?? 'general')])) ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($sub['is_locked'])): ?>
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">Fermé</span>
                                <?php else: ?>
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Ouvert</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button type="button" @click='fcOpenEdit(<?= json_encode($sub + ['_parent_name' => $cat['name'] ?? '']) ?>)' class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Modifier</button>
                                    <button type="button" @click="fcLock(<?= (int)($sub['id']) ?>, <?= !empty($sub['is_locked']) ? 'false' : 'true' ?>)" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"><?= !empty($sub['is_locked']) ? 'Rouvrir' : 'Fermer' ?></button>
                                    <button type="button" @click="fcDelete(<?= (int)($sub['id']) ?>)" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Supprimer</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Réglages -->
    <section id="fc-settings" class="mb-12 scroll-mt-24">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">Réglages du forum</h2>
            <p class="mt-1 text-sm text-slate-600">Modifiez une ou plusieurs sections, puis enregistrez : tout est envoyé en une seule fois.</p>
        </div>
        <div class="space-y-3">
            <?php foreach ($forumSettingGroups as $gi => $group): ?>
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="{ open: <?= !empty($group['defaultOpen']) ? 'true' : 'false' ?> }">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left transition hover:bg-slate-50 sm:px-5">
                    <span>
                        <span class="block text-sm font-bold text-slate-900"><?= htmlspecialchars($group['title']) ?></span>
                        <span class="mt-0.5 block text-xs text-slate-500"><?= htmlspecialchars($group['summary']) ?></span>
                    </span>
                    <span class="shrink-0 text-slate-400" x-text="open ? 'Réduire' : 'Déplier'"></span>
                </button>
                <div x-show="open" class="border-t border-slate-100">
                    <div class="space-y-5 px-4 py-5 sm:px-5">
                        <?php foreach ($group['fields'] as $field): ?>
                        <div class="border-b border-slate-100 pb-5 last:border-0 last:pb-0">
                            <?php if ($field['type'] === 'toggle'): ?>
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" class="fc-setting-input mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" data-setting-key="<?= htmlspecialchars($field['key']) ?>" <?= !empty($field['checked']) ? 'checked' : '' ?>>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($field['label']) ?></span>
                                    <?php if (!empty($field['help'])): ?>
                                    <span class="mt-1 block text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($field['help']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php elseif ($field['type'] === 'textarea'): ?>
                            <label class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($field['label']) ?></label>
                            <?php if (!empty($field['help'])): ?>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($field['help']) ?></p>
                            <?php endif; ?>
                            <textarea class="fc-setting-input mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" rows="4" data-setting-key="<?= htmlspecialchars($field['key']) ?>"><?= htmlspecialchars($field['value']) ?></textarea>
                            <?php else: ?>
                            <label class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($field['label']) ?></label>
                            <?php if (!empty($field['help'])): ?>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars($field['help']) ?></p>
                            <?php endif; ?>
                            <input
                                type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>"
                                class="fc-setting-input mt-2 w-full max-w-xl rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                data-setting-key="<?= htmlspecialchars($field['key']) ?>"
                                value="<?= htmlspecialchars($field['value']) ?>"
                                <?= $field['attrs'] ?? '' ?>
                            >
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="button" @click="saveForumSettings()" :disabled="savingSettings" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50">
                <span x-show="!savingSettings">Enregistrer tous les réglages</span>
                <span x-show="savingSettings" x-cloak>Enregistrement…</span>
            </button>
            <p class="text-xs text-slate-500">Pensez à enregistrer après modification, même si vous n’avez changé qu’une section.</p>
        </div>
    </section>

    <!-- XP -->
    <section class="mb-12 rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
        <h2 class="text-sm font-bold text-slate-900">Points d’expérience (XP)</h2>
        <p class="mt-2 text-sm text-slate-600">Les montants sont gérés par le module XP du site, pas sur cette page.</p>
        <p class="mt-3 text-sm font-medium text-slate-800">
            Création de sujet : <?= htmlspecialchars((string)($forumConfig['xp_topic'] ?? '—')) ?> XP
            <span class="mx-2 text-slate-300">·</span>
            Réponse : <?= htmlspecialchars((string)($forumConfig['xp_reply'] ?? '—')) ?> XP
            <span class="mx-2 text-slate-300">·</span>
            Longueur max. message : <?= htmlspecialchars((string)($forumConfig['forum_max_post_length'] ?? forum_get_setting('forum_max_post_length', 10000))) ?> caractères
        </p>
    </section>

    <!-- Filtres -->
    <section id="fc-filters" class="mb-12 scroll-mt-24">
        <h2 class="text-lg font-bold text-slate-900">Filtres de contenu</h2>
        <p class="mt-1 text-sm text-slate-600">Mots et sites bloqués dans les messages. Utile pour limiter les abus sans tout passer en modération manuelle.</p>
        <div class="mt-6 grid gap-8 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Mots et expressions refusés</h3>
                <div class="mt-3 flex gap-2">
                    <input type="text" x-model="bannedWordInput" placeholder="Ajouter un mot ou une expression" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <button type="button" @click="bwAdd()" class="shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter</button>
                </div>
                <ul class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <?php foreach ($bannedWords as $w): ?>
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <span class="min-w-0 truncate"><?= htmlspecialchars(is_array($w) ? ($w['word'] ?? $w) : $w) ?></span>
                        <button type="button" @click="bwDelete(<?= is_array($w) ? (int)($w['id'] ?? 0) : 0 ?>)" class="shrink-0 text-xs font-semibold text-rose-600 hover:text-rose-800">Retirer</button>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($bannedWords)): ?>
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Aucun mot listé.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800">Sites web interdits</h3>
                <p class="mt-1 text-xs text-slate-500">Indiquez le nom de domaine seul, sans https ni chemin (ex. mauvais-exemple.com).</p>
                <div class="mt-3 flex gap-2">
                    <input type="text" x-model="blacklistedDomainInput" placeholder="exemple.com" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <button type="button" @click="bdAdd()" class="shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter</button>
                </div>
                <ul class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <?php foreach ($blacklistedDomains as $d): ?>
                    <li class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <span class="min-w-0 truncate font-mono text-xs"><?= htmlspecialchars(is_array($d) ? ($d['domain'] ?? $d) : $d) ?></span>
                        <button type="button" @click="bdDelete(<?= is_array($d) ? (int)($d['id'] ?? 0) : 0 ?>)" class="shrink-0 text-xs font-semibold text-rose-600 hover:text-rose-800">Retirer</button>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($blacklistedDomains)): ?>
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Aucun domaine listé.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- Outils -->
    <section id="fc-tools" class="mb-12 scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">Outils et vérifications</h2>
        <p class="mt-2 text-sm text-slate-600">Tests réservés aux modules de modération automatique. Les files d’attente détaillées se consultent depuis l’espace modération lorsqu’elles sont disponibles.</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="button" @click="botSelfTest()" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">Test de l’assistant</button>
            <button type="button" @click="botPreview()" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">Aperçu</button>
            <a href="<?= htmlspecialchars($baseUrl) ?>/back-office/forum-moderation" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-100">Console modération</a>
        </div>
    </section>

    <!-- Modale catégorie -->
    <div x-show="fcModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-[2px]" @click.self="fcModalOpen = false">
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @click.stop>
            <h3 class="text-lg font-bold text-slate-900" x-text="fcEditId ? 'Modifier le canal' : 'Nouveau canal'"></h3>
            <p class="mt-1 text-xs text-slate-500" x-show="fcForm.parent_id">Sous-canal : choisissez le canal parent ci-dessous.</p>
            <form class="mt-5 space-y-4" @submit.prevent="fcSubmitForm()">
                <input type="hidden" name="id" x-model="fcEditId">
                <div>
                    <label class="block text-sm font-semibold text-slate-800">Nom affiché</label>
                    <input type="text" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" name="name" x-model="fcForm.name" required autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-800">Adresse courte dans l’URL</label>
                    <input type="text" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" name="slug" x-model="fcForm.slug" placeholder="ex. annonces" autocomplete="off">
                    <p class="mt-1 text-xs text-slate-500">Lettres minuscules et tirets. Doit être unique pour votre organisation.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-800">Description</label>
                    <textarea class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" name="description" x-model="fcForm.description" rows="2"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-800">Ordre d’affichage</label>
                    <input type="number" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" name="display_order" x-model="fcForm.display_order" min="0">
                    <p class="mt-1 text-xs text-slate-500">Plus le nombre est petit, plus le canal apparaît haut dans la liste.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-800">Rattachement</label>
                    <select class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" x-model="fcForm.parent_id">
                        <option value="">Canal principal (racine)</option>
                        <?php foreach ($categories as $rc): ?>
                        <option value="<?= (int)($rc['id'] ?? 0) ?>"><?= htmlspecialchars($rc['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Un seul niveau de sous-canal. La visibilité reprend celle du canal parent.</p>
                </div>
                <div x-show="!fcForm.parent_id">
                    <label class="block text-sm font-semibold text-slate-800">Visibilité du canal</label>
                    <select class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" x-model="fcForm.scope">
                        <option value="general">Tous les membres connectés</option>
                        <option value="platform">Toute la plateforme</option>
                        <option value="organization">Réservé à l’unité / organisation</option>
                        <option value="moderation">Réservé à l’équipe de modération</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
                    <button type="button" @click="fcModalOpen = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function forumConfigPage() {
    const BASE = <?= json_encode($baseUrl) ?>;
    const CSRF = <?= json_encode($csrfToken) ?>;
    return {
        fcModalOpen: false,
        fcEditId: null,
        fcForm: { name: '', slug: '', description: '', display_order: 0, parent_id: '', scope: 'general' },
        bannedWordInput: '',
        blacklistedDomainInput: '',
        banner: null,
        savingSettings: false,
        fcOpenCreate(parentId) {
            this.fcEditId = null;
            this.fcForm = { name: '', slug: '', description: '', display_order: 0, parent_id: parentId ? String(parentId) : '', scope: 'general' };
            this.fcModalOpen = true;
        },
        fcOpenEdit(cat) {
            this.fcEditId = cat.id;
            this.fcForm = {
                name: cat.name || '',
                slug: cat.slug || '',
                description: cat.description || '',
                display_order: cat.display_order ?? 0,
                parent_id: cat.parent_id ? String(cat.parent_id) : '',
                scope: cat.scope || 'general',
            };
            this.fcModalOpen = true;
        },
        showBanner(type, text) {
            this.banner = { type, text };
            clearTimeout(this._bannerT);
            this._bannerT = setTimeout(() => { this.banner = null; }, 5000);
        },
        async fcSubmitForm() {
            const action = this.fcEditId ? 'update' : 'create';
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', action);
            if (this.fcEditId) body.append('id', this.fcEditId);
            body.append('name', this.fcForm.name);
            body.append('slug', this.fcForm.slug || this.fcForm.name.toLowerCase().replace(/\s+/g, '-'));
            body.append('description', this.fcForm.description);
            body.append('display_order', this.fcForm.display_order);
            body.append('parent_id', this.fcForm.parent_id || '');
            if (!this.fcForm.parent_id) {
                body.append('scope', this.fcForm.scope || 'general');
            }
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.fcModalOpen = false; location.reload(); return; }
                this.showBanner('error', j.message || 'Impossible d’enregistrer le canal.');
            } catch (e) { this.showBanner('error', 'Problème de connexion. Réessayez.'); }
        },
        async fcLock(id, locked) {
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'lock');
            body.append('id', id);
            body.append('locked', locked ? '1' : '0');
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                this.showBanner('error', j.message || 'Action impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async fcDelete(id) {
            if (!confirm('Supprimer ce canal ? Les sujets éventuels doivent être vides ou déplacés avant.')) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/admin/forum-categories', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                this.showBanner('error', j.message || 'Suppression impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        collectSettings() {
            const settings = {};
            document.querySelectorAll('[data-setting-key]').forEach((el) => {
                const k = el.dataset.settingKey;
                if (!k) return;
                if (el.type === 'checkbox') {
                    settings[k] = el.checked ? '1' : '0';
                } else {
                    settings[k] = el.value;
                }
            });
            return settings;
        },
        async saveForumSettings() {
            this.savingSettings = true;
            const settings = this.collectSettings();
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('settings', JSON.stringify(settings));
            try {
                const r = await fetch(BASE + '/api/admin/site-settings', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) {
                    this.showBanner('success', 'Réglages enregistrés.');
                } else {
                    this.showBanner('error', j.message || 'Enregistrement refusé.');
                }
            } catch (e) {
                this.showBanner('error', 'Problème de connexion.');
            } finally {
                this.savingSettings = false;
            }
        },
        async bwAdd() {
            const word = this.bannedWordInput.trim();
            if (!word) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'add_banned_word');
            body.append('word', word);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.bannedWordInput = ''; location.reload(); return; }
                this.showBanner('error', j.message || 'Ajout impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async bwDelete(id) {
            if (!id) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete_banned_word');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                this.showBanner('error', j.message || 'Suppression impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async bdAdd() {
            const domain = this.blacklistedDomainInput.trim();
            if (!domain) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'add_blacklisted_domain');
            body.append('domain', domain);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { this.blacklistedDomainInput = ''; location.reload(); return; }
                this.showBanner('error', j.message || 'Ajout impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async bdDelete(id) {
            if (!id) return;
            const body = new FormData();
            body.append('_csrf_token', CSRF);
            body.append('action', 'delete_blacklisted_domain');
            body.append('id', id);
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body });
                const j = await r.json().catch(() => ({}));
                if (r.ok && (j.success || j.ok)) { location.reload(); return; }
                this.showBanner('error', j.message || 'Suppression impossible.');
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async botSelfTest() {
            const b = new FormData();
            b.append('_csrf_token', CSRF);
            b.append('action', 'bot_self_test');
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body: b });
                const j = await r.json().catch(() => ({}));
                this.showBanner(r.ok ? 'success' : 'error', j.message || (r.ok ? 'Test terminé.' : 'Échec du test.'));
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
        async botPreview() {
            const b = new FormData();
            b.append('_csrf_token', CSRF);
            b.append('action', 'bot_preview');
            try {
                const r = await fetch(BASE + '/api/back-office/forum-moderation', { method: 'POST', body: b });
                const j = await r.json().catch(() => ({}));
                this.showBanner(r.ok ? 'success' : 'error', j.message || (r.ok ? 'Aperçu généré.' : 'Aperçu indisponible.'));
            } catch (e) { this.showBanner('error', 'Problème de connexion.'); }
        },
    };
}
</script>
