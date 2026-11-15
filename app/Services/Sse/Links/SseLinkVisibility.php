<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Support\SseLinkCatalog;

/**
 * Filtre de confidentialité appliqué à CHAQUE nœud du chemin, avec les droits du lecteur.
 *
 *  - cut        : l'élément n'existe pas pour ce lecteur (identité de couverture, d'emprunt ou falsifiée sans l'habilitation
 *                 Confidentiel, et tout ce qui la révèle) : il est retiré du chemin, sans trace ni indice ;
 *  - restricted : le lecteur n'a pas le droit de le lire (section, identité d'un membre, classification, portée du code
 *                 d'accès, diffusion du dossier d'intérêt) : « Élément restreint », sans nom, référence, type ni adresse, et
 *                 le chemin ne passe pas au travers ;
 *  - ok         : visible.
 */
final class SseLinkVisibility
{
    public const OK = 'ok';

    public const RESTRICTED = 'restricted';

    public const CUT = 'cut';

    /**
     * @param array<string, mixed> $node description d'un adaptateur
     */
    public static function judge(string $type, string $id, array $node, SseLinkViewer $viewer): string
    {
        if (!empty($node['secret']) && !$viewer->canSeeTruth()) {
            return self::CUT;
        }
        $sections = $node['sections'] ?? [SseLinkCatalog::TYPES[$type]['section'] ?? ''];
        foreach ((array) $sections as $section) {
            if ((string) $section !== '' && !$viewer->canRead((string) $section)) {
                return self::RESTRICTED;
            }
        }
        $member = (int) ($node['member'] ?? 0);
        if ($member > 0 && !$viewer->memberAllowed($member)) {
            return self::RESTRICTED;
        }
        $class = (string) ($node['classification'] ?? '');
        if ($class !== '' && !$viewer->clearanceAllows($class)) {
            return self::RESTRICTED;
        }
        if ($type === 'case') {
            $scope = $viewer->caseScope();
            if ($scope !== null && !in_array((int) $id, $scope, true)) {
                return self::RESTRICTED;
            }
        }
        if (!empty($node['interest']) && !$viewer->canOpenInterest((int) $id)) {
            return self::RESTRICTED;
        }

        return self::OK;
    }
}
