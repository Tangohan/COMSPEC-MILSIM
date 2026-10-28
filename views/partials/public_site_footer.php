<?php

declare(strict_types=1);

/**
 * Pied de page public (accueil et pages de présentation).
 * Réutilise le sélecteur de langue déjà en place. Le lien Ops reste dans l’en-tête.
 */
$psfYear = (int) date('Y');
$psfH = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$psfLocale = function_exists('site_lang') ? site_lang() : 'fr';
$psfLanguages = [
    'fr' => __('common.language_fr'),
    'en' => __('common.language_en'),
];
foreach (\App\Services\I18n\CommunityLanguages::published() as $psfCode => $psfName) {
    $psfLanguages[$psfCode] = $psfName;
}
$psfCurrentLanguage = $psfLanguages[$psfLocale] ?? $psfLanguages['fr'];

$psfColumns = [
    [
        'title' => __('home.footer_col_legal'),
        'cookies' => true,
        'links' => [
            ['href' => url('mentions-legales'), 'label' => __('legal.mentions')],
            ['href' => url('donnees-personnelles'), 'label' => __('legal.rgpd')],
            ['href' => url('cookies'), 'label' => __('legal.cookies')],
            ['href' => url('cgu'), 'label' => __('legal.cgu_desc')],
            ['href' => url('cgv'), 'label' => __('legal.cgv')],
            ['href' => url('demande-donnees'), 'label' => __('legal.exercise_rights')],
            ['href' => url('legal/site'), 'label' => __('legal.policy_conditions')],
        ],
    ],
    [
        'title' => __('home.footer_col_community'),
        'links' => [
            ['href' => url('a-propos'), 'label' => __('site.about')],
            ['href' => url('notre-histoire'), 'label' => __('site.history')],
            ['href' => url('communities'), 'label' => __('common.communities')],
            ['href' => url('nouveautes'), 'label' => __('site.changelog')],
            ['href' => url('sse'), 'label' => __('site.sse')],
            ['href' => url('atak-natif'), 'label' => __('site.atak_native')],
            ['href' => url('contribuer'), 'label' => __('site.contrib_crumb')],
        ],
    ],
    [
        'title' => __('home.footer_col_help'),
        'links' => [
            ['href' => url('contact'), 'label' => __('site.contact')],
            ['href' => url('atak-natif') . '#faq', 'label' => __('site.atakn_faq_title')],
            ['href' => url('soutenir-atak'), 'label' => __('site.support_crumb')],
            ['href' => url('traduire-site'), 'label' => __('site.sitetr_crumb')],
            ['href' => url('traduire-atak'), 'label' => __('site.atakt_crumb')],
        ],
    ],
];
?>
<footer class="psf">
    <div class="psf__sheet">
        <div class="psf__brand">
            <p class="psf__copy">© <?= $psfYear ?> Athena</p>
            <p class="psf__tagline"><?= $psfH(__('home.footer_tagline')) ?></p>
            <details class="psf-lang">
                <summary aria-label="<?= $psfH(__('common.choose_language') . ' : ' . $psfCurrentLanguage) ?>">
                    <svg class="psf-lang__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M3 12h18M12 3c2.4 2.6 3.6 5.7 3.6 9s-1.2 6.4-3.6 9c-2.4-2.6-3.6-5.7-3.6-9s1.2-6.4 3.6-9z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    </svg>
                    <span><?= $psfH($psfCurrentLanguage) ?></span>
                </summary>
                <ul>
                    <?php foreach ($psfLanguages as $psfCode => $psfLabel): ?>
                        <li>
                            <?php if ($psfLocale === $psfCode): ?>
                                <span aria-current="true"><?= $psfH($psfLabel) ?></span>
                            <?php else: ?>
                                <a href="<?= $psfH(locale_switch_url($psfCode)) ?>" hreflang="<?= $psfH($psfCode) ?>" lang="<?= $psfH($psfCode) ?>"><?= $psfH($psfLabel) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
        <nav class="psf__cols" aria-label="<?= $psfH(__('home.footer_nav_aria')) ?>">
            <?php foreach ($psfColumns as $psfColumn): ?>
                <div class="psf__col">
                    <p class="psf__heading"><?= $psfH((string) $psfColumn['title']) ?></p>
                    <ul>
                        <?php foreach ($psfColumn['links'] as $psfLink): ?>
                            <li><a href="<?= $psfH((string) $psfLink['href']) ?>"><?= $psfH((string) $psfLink['label']) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (!empty($psfColumn['cookies'])): ?>
                            <li><button type="button" data-cookie-preferences><?= $psfH(__('common.cookie_prefs')) ?></button></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>
    </div>
    <p class="psf__giant" aria-hidden="true"><span>Athena</span></p>
</footer>
