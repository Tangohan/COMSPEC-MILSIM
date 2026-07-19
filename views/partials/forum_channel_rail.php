<?php
declare(strict_types=1);

/**
 * Rail persistante « salons » façon Discord — visible sur toutes les pages du forum
 * (accueil, catégorie, sujet, nouveau sujet). Catégories racines + sous-catégories,
 * catégorie courante mise en évidence.
 *
 * S'appuie sur les variables déjà présentes dans le scope du layout (extract() unique
 * fait par Response::view avant l'inclusion du contenu) : $category (page catégorie),
 * $topic (page sujet, contient category_id).
 */

$forumRailTenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
if ($forumRailTenantId < 1) {
    return;
}

$forumRailActiveCategoryId = 0;
if (isset($category) && is_array($category)) {
    $forumRailActiveCategoryId = (int) ($category['id'] ?? 0);
} elseif (isset($topic) && is_array($topic)) {
    $forumRailActiveCategoryId = (int) ($topic['category_id'] ?? 0);
}

$forumRailRoots = [];
try {
    $forumRailRoots = \App\Core\Container::get(\App\Repositories\ForumCategoryRepository::class)
        ->listForTenantWithChildren($forumRailTenantId);
} catch (\Throwable) {
    $forumRailRoots = [];
}
if ($forumRailRoots === []) {
    return;
}

$forumRailQuery = is_array($forumTenantQuery ?? null) ? $forumTenantQuery : [];
$forumRailUrl = static function (string $slug) use ($forumRailQuery): string {
    return function_exists('forum_build_category_url')
        ? forum_build_category_url($slug, $forumRailQuery)
        : url('forum/category/' . rawurlencode($slug));
};

?>
<button type="button" class="forum-rail-toggle" data-forum-rail-toggle aria-expanded="false" aria-controls="forum-rail">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span>Salons</span>
</button>
<div class="forum-rail-backdrop" data-forum-rail-backdrop hidden></div>
<aside id="forum-rail" class="forum-rail" data-forum-rail>
    <div class="forum-rail__head">
        <a href="<?= htmlspecialchars(url('forum') . ($forumRailQuery !== [] ? '?' . http_build_query($forumRailQuery) : ''), ENT_QUOTES, 'UTF-8') ?>" class="forum-rail__home<?= $forumRailActiveCategoryId === 0 ? ' is-active' : '' ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
            <span>Accueil du forum</span>
        </a>
    </div>
    <nav class="forum-rail__nav" aria-label="Salons du forum">
        <?php foreach ($forumRailRoots as $root): ?>
            <?php
            $rootId = (int) ($root['id'] ?? 0);
            $rootSlug = trim((string) ($root['slug'] ?? ''));
            $rootName = trim((string) ($root['name'] ?? ''));
            $children = is_array($root['children'] ?? null) ? $root['children'] : [];
            if ($rootSlug === '') {
                continue;
            }
            ?>
        <div class="forum-rail__group">
            <a href="<?= htmlspecialchars($forumRailUrl($rootSlug), ENT_QUOTES, 'UTF-8') ?>" class="forum-rail__root<?= $rootId === $forumRailActiveCategoryId ? ' is-active' : '' ?>">
                <span class="forum-rail__root-name"><?= htmlspecialchars($rootName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($root['topic_count'])): ?><span class="forum-rail__count"><?= (int) $root['topic_count'] ?></span><?php endif; ?>
            </a>
            <?php if ($children !== []): ?>
            <div class="forum-rail__children">
                <?php foreach ($children as $child): ?>
                    <?php
                    $childId = (int) ($child['id'] ?? 0);
                    $childSlug = trim((string) ($child['slug'] ?? ''));
                    $childName = trim((string) ($child['name'] ?? ''));
                    if ($childSlug === '') {
                        continue;
                    }
                    ?>
                <a href="<?= htmlspecialchars($forumRailUrl($childSlug), ENT_QUOTES, 'UTF-8') ?>" class="forum-rail__channel<?= $childId === $forumRailActiveCategoryId ? ' is-active' : '' ?>">
                    <span class="forum-rail__hash" aria-hidden="true">#</span>
                    <span class="forum-rail__channel-name"><?= htmlspecialchars($childName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($child['topic_count'])): ?><span class="forum-rail__count"><?= (int) $child['topic_count'] ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </nav>
</aside>
<style>
.forum-rail-toggle {
    display: none;
    position: fixed;
    top: 0.85rem;
    left: 0.85rem;
    z-index: 41;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    border-radius: 0.6rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    color: #0f172a;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
}
.forum-rail-backdrop {
    position: fixed;
    inset: 0;
    z-index: 39;
    background: rgba(2, 6, 23, 0.5);
}
.forum-rail {
    position: sticky;
    top: 0;
    align-self: flex-start;
    width: 15.5rem;
    flex-shrink: 0;
    max-height: 100vh;
    overflow-y: auto;
    border-right: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 1rem 0.75rem 2rem;
}
.forum-rail__head { padding: 0 0.25rem 0.75rem; margin-bottom: 0.5rem; border-bottom: 1px solid #e2e8f0; }
.forum-rail__home {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.6rem;
    border-radius: 0.5rem;
    color: #334155;
    font-size: 0.8125rem;
    font-weight: 800;
    text-decoration: none;
}
.forum-rail__home:hover, .forum-rail__home.is-active { background: #e2e8f0; color: #0f172a; }
.forum-rail__group { margin-bottom: 0.35rem; }
.forum-rail__root {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.6rem;
    border-radius: 0.5rem;
    color: #475569;
    font-size: 0.6875rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    text-decoration: none;
}
.forum-rail__root:hover, .forum-rail__root.is-active { background: #e2e8f0; color: #0f172a; }
.forum-rail__children { display: flex; flex-direction: column; gap: 0.05rem; margin-top: 0.1rem; }
.forum-rail__channel {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.6rem 0.4rem 1.15rem;
    border-radius: 0.5rem;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 600;
    text-decoration: none;
}
.forum-rail__channel:hover { background: #eef2f7; color: #0f172a; }
.forum-rail__channel.is-active { background: #dcfce7; color: #065f46; font-weight: 800; }
.forum-rail__hash { color: #94a3b8; font-weight: 900; }
.forum-rail__channel.is-active .forum-rail__hash { color: #059669; }
.forum-rail__channel-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.forum-rail__count {
    flex-shrink: 0;
    font-size: 0.625rem;
    font-weight: 800;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
}

@media (max-width: 900px) {
    .forum-rail-toggle { display: inline-flex; }
    .forum-rail {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 40;
        transform: translateX(-100%);
        transition: transform 0.2s ease;
        box-shadow: 0 0 0 1px rgba(0,0,0,0.02);
    }
    .forum-rail.is-open { transform: translateX(0); }
    .forum-rail-backdrop.is-open { display: block; }
}
@media (min-width: 901px) {
    .forum-rail-backdrop { display: none !important; }
}
</style>
<script>
(function () {
    var toggle = document.querySelector('[data-forum-rail-toggle]');
    var rail = document.querySelector('[data-forum-rail]');
    var backdrop = document.querySelector('[data-forum-rail-backdrop]');
    if (!toggle || !rail || !backdrop) return;

    function setOpen(open) {
        rail.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () { setOpen(!rail.classList.contains('is-open')); });
    backdrop.addEventListener('click', function () { setOpen(false); });
})();
</script>
