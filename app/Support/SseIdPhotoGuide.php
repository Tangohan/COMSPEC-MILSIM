<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Aide « Photo d'identité façon photomaton » (COMSPEC IDENTITY) : prompts d'exemple à copier pour un générateur
 * d'image, en français et en anglais, et variantes. Contenu à copier tel quel : affiché sans traduction
 * (translate="no"), chaque langue a son propre texte. Même texte que docs/guide/photo-identite.md.
 */
final class SseIdPhotoGuide
{
    /** @var array<string, list<string>> langue => phrases du prompt */
    public const PROMPTS = [
        'fr' => [
            'À partir de la capture d’écran jointe d’un personnage du jeu vidéo Arma 3, crée une photo d’identité de jeu stylisée, façon photomaton (cabine photo).',
            'C’est un personnage fictif de jeu vidéo, pas une vraie personne : il ne doit ressembler à aucune personne réelle identifiable.',
            'Garde ses traits généraux, sa coupe de cheveux, sa couleur de peau et sa pilosité.',
            'Cadrage serré tête et haut des épaules, centré, format portrait 35 × 45 mm.',
            'Tête droite, regard droit dans l’objectif, bouche fermée, expression neutre, oreilles et front dégagés.',
            'Éclairage frontal uniforme et plat, comme le flash diffus d’une cabine photomaton, sans ombres marquées.',
            'Fond uni gris clair ou blanc cassé.',
            'Pas de lunettes de soleil ni de couvre-chef (sauf couvre-chef religieux), pas de casque, pas d’équipement tactique.',
            'Rendu photo réaliste mais propre, image bien nette ; légère texture de tirage photomaton facultative.',
            'Aucun texte, logo, tampon ni filigrane dans l’image.',
        ],
        'en' => [
            'Using the attached screenshot of a character from the video game Arma 3, create a stylised in-game ID photo in the style of a photo booth picture.',
            'This is a fictional video game character, not a real person: it must not look like any real, identifiable person.',
            'Keep the general features, haircut, skin tone and facial hair.',
            'Tight framing of the head and top of the shoulders, centred, 35 × 45 mm portrait format.',
            'Head straight, looking straight into the lens, mouth closed, neutral expression, ears and forehead uncovered.',
            'Even, flat frontal lighting like the diffused flash of a photo booth, no harsh shadows.',
            'Plain light grey or off-white background.',
            'No sunglasses and no headwear (except religious headwear), no helmet, no tactical gear.',
            'Realistic but clean photo rendering, sharp image; a slight photo booth print texture is optional.',
            'No text, logo, stamp or watermark in the image.',
        ],
    ];

    /** @var array<string, array{fr: string, en: string}> variante => phrase ajoutée au prompt */
    public const VARIANTS = [
        'tired' => [
            'fr' => 'Ajoute des traits tirés : cernes marqués, teint un peu terne, regard las, l’expression reste neutre.',
            'en' => 'Add a tired look: visible dark circles, slightly dull complexion, weary eyes, the expression stays neutral.',
        ],
        'beard' => [
            'fr' => 'Ajoute une barbe courte de quelques jours, bien nette, sans cacher la bouche.',
            'en' => 'Add a short, neat few-days beard that does not hide the mouth.',
        ],
        'scar' => [
            'fr' => 'Ajoute une fine cicatrice ancienne et discrète sur la joue gauche.',
            'en' => 'Add a thin, faded old scar on the left cheek.',
        ],
        'older' => [
            'fr' => 'Vieillis le personnage d’environ 15 ans : rides légères, tempes grisonnantes.',
            'en' => 'Age the character by about 15 years: light wrinkles, greying temples.',
        ],
        'younger' => [
            'fr' => 'Rajeunis le personnage d’environ 10 ans, sans changer ses traits.',
            'en' => 'Make the character about 10 years younger without changing the features.',
        ],
    ];

    public static function prompt(string $lang): string
    {
        return implode(' ', self::PROMPTS[$lang] ?? self::PROMPTS['fr']);
    }
}
