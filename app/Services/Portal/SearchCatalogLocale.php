<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Support\HtmlTranslator;

/**
 * Pages et guides proposés par les recherches (portail, back-office) dans la langue du visiteur.
 * La réponse JSON ne passe pas par la traduction de la page : sans cela, un visiteur anglais lisait les titres
 * en français et une recherche en anglais (« roster », « training »…) ne trouvait rien.
 */
final class SearchCatalogLocale
{
    /**
     * Anglais : titre et sous-titre traduits ; le texte français rejoint les mots-clés pour qu’une recherche
     * en français trouve toujours la page.
     *
     * @template T of array{title: string, subtitle: string, keywords?: string}
     * @param list<T> $items
     * @return list<T>
     */
    public static function localize(array $items): array
    {
        if (!function_exists('locale') || locale() !== 'en') {
            return $items;
        }
        foreach ($items as $i => $item) {
            $title = (string) ($item['title'] ?? '');
            $subtitle = (string) ($item['subtitle'] ?? '');
            $item['title'] = HtmlTranslator::translateText($title);
            $item['subtitle'] = HtmlTranslator::translateText($subtitle);
            $item['keywords'] = trim(($item['keywords'] ?? '') . ' ' . $title . ' ' . $subtitle);
            $items[$i] = $item;
        }

        return $items;
    }
}
