<?php

declare(strict_types=1);

/**
 * Catalogue CDN / bibliothèques front (emoji, gif, svg, drapeaux, heroicons, animations, icônes).
 *
 * Usage dans une vue / contrôleur :
 *   $cdnLibs = ['icons', 'flags', 'animation', 'emoji'];
 *   // puis le layout appelle views/partials/cdn_media_libs.php
 *
 * Packs disponibles : icons, hero, emoji, emoji_picker, gif, svg, flags, animation, lottie, motion, confetti
 */
return [
    'version' => '2026.07',

    /**
     * Packs activés par défaut sur le layout portail.
     * Vide : Lucide, Iconify, Animate.css et AOS n’étaient pas utilisés
     * et violaient la CSP (pas de cdn.jsdelivr.net).
     */
    'defaults' => [],

    /** Packs activés par défaut sur le forum (messagerie / réactions). */
    'defaults_forum' => ['emoji', 'gif', 'flags'],

    /**
     * Définition des assets par pack.
     * phase: head | body (scripts différés en bas de page)
     * attrs: attributs HTML supplémentaires (defer, async, crossorigin…)
     */
    'packs' => [
        'icons' => [
            'label' => 'Icônes (Lucide + Iconify) — retiré (CSP, non utilisé)',
            'assets' => [],
            'note' => 'Ne pas recharger depuis jsDelivr. Utiliser les icônes SVG / CSS du site.',
        ],

        'hero' => [
            'label' => 'Heroicons (via Iconify)',
            'depends' => ['icons'],
            'assets' => [],
            'note' => 'Utiliser <iconify-icon icon="heroicons:outline:home"></iconify-icon> ou heroicons:solid:…',
        ],

        'emoji' => [
            'label' => 'Emoji (Twemoji SVG)',
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/@twemoji/api@15.1.0/dist/twemoji.min.js',
                    'attrs' => ['defer' => true, 'crossorigin' => 'anonymous'],
                ],
            ],
        ],

        'emoji_picker' => [
            'label' => 'Sélecteur d’emoji (Emoji Button)',
            'depends' => ['emoji'],
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js',
                    'attrs' => ['defer' => true],
                ],
            ],
        ],

        'gif' => [
            'label' => 'GIF (Tenor / Giphy helpers)',
            'assets' => [
                // Pas de SDK lourd : athena_media.js expose recherche Tenor + affichage.
            ],
            'note' => 'AthenaMedia.gif.search / .embed — clé optionnelle via meta[name=athena-tenor-key]',
        ],

        'svg' => [
            'label' => 'SVG.js',
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/@svgdotjs/svg.js@3.2.4/dist/svg.min.js',
                    'attrs' => ['defer' => true],
                ],
            ],
        ],

        'flags' => [
            'label' => 'Drapeaux (flag-icons)',
            'assets' => [
                [
                    'type' => 'css',
                    'phase' => 'head',
                    'href' => 'https://cdn.jsdelivr.net/npm/flag-icons@7.3.2/css/flag-icons.min.css',
                ],
            ],
        ],

        'animation' => [
            'label' => 'Animations CSS (Animate.css + AOS) — retiré (CSP, décoratif)',
            'assets' => [],
            'note' => 'Ne pas recharger depuis jsDelivr. Animations décoratives non utilisées sur le portail.',
        ],

        'lottie' => [
            'label' => 'Animations Lottie',
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js',
                    'attrs' => ['defer' => true],
                ],
            ],
        ],

        'motion' => [
            'label' => 'Motion / hero (GSAP)',
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js',
                    'attrs' => ['defer' => true],
                ],
            ],
        ],

        'confetti' => [
            'label' => 'Confettis (canvas-confetti)',
            'assets' => [
                [
                    'type' => 'js',
                    'phase' => 'body',
                    'src' => 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js',
                    'attrs' => ['defer' => true],
                ],
            ],
        ],
    ],

    /**
     * Raccourcis d’usage HTML (référence pour les vues).
     * Pas exposés à l’utilisateur final — documentation développeur.
     */
    'examples' => [
        'icon_lucide' => '<i data-lucide="shield" class="h-5 w-5"></i>',
        'icon_iconify' => '<iconify-icon icon="mdi:account-group" width="20" height="20"></iconify-icon>',
        'icon_hero' => '<iconify-icon icon="heroicons:outline:map" width="20" height="20"></iconify-icon>',
        'flag' => '<span class="fi fi-fr" title="France" aria-hidden="true"></span>',
        'flag_img' => '<img src="https://flagcdn.com/24x18/fr.png" width="24" height="18" alt="">',
        'emoji' => '<span class="athena-emoji">🎖️</span>',
        'gif' => '<img data-athena-gif="tenor-id" alt="" class="athena-gif">',
        'aos' => '<div data-aos="fade-up">…</div>',
        'animate_css' => '<div class="animate__animated animate__fadeIn">…</div>',
        'lottie' => '<div data-athena-lottie="/assets/lottie/example.json"></div>',
    ],
];
