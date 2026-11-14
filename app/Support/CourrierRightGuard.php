<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\Response;

/**
 * Droit interne des actions du Bureau Courrier (audit 5.8 / courrier).
 *
 * Les routes ne portent que l'authentification et la sanction de modération : le droit se vérifie ici, comme le menu
 * (config/navigation.php). Lecture : « Voir le Bureau Courrier ». Écriture : « Créer des documents » ou « Valider ».
 * Refus : 403 JSON pour un script, message et retour au tableau du courrier (ou à l'accueil du portail) pour un navigateur.
 */
final class CourrierRightGuard
{
    /** Gestion de la communauté : traités comme gestionnaires du courrier, comme la traçabilité et les contrôles internes du module. */
    private const MANAGERS = BackOfficeRightGuard::MANAGERS;

    public const VIEW = ['courrier.view', ...self::MANAGERS];

    /** Archives : même règle que le menu. */
    public const ARCHIVES = ['courrier.view', 'courrier.archive', ...self::MANAGERS];

    /** Rédiger : l'éditeur et ses aides se lisent avec « Voir » ou le droit de rédaction du menu. */
    public const EDITOR_READ = ['courrier.view', 'courrier.create', 'courrier.validate', ...self::MANAGERS];

    /** Enregistrer un courrier ou un modèle, fixer un format par défaut : droit de rédiger ou de valider. */
    public const WRITE = ['courrier.create', 'courrier.validate', ...self::MANAGERS];

    /** Passage d'étape : chaque étape garde ensuite son droit propre (validation, archivage, auteur). */
    public const WORKFLOW = ['courrier.view', 'courrier.create', 'courrier.validate', 'courrier.archive', ...self::MANAGERS];

    /** @param list<string> $rights un seul suffit */
    public static function deniedUnless(Request $request, array $rights): ?Response
    {
        $home = BackOfficeRightGuard::allows(self::VIEW) ? url('courrier') : url('dashboard');

        return BackOfficeRightGuard::deniedUnless($request, $rights, $home);
    }
}
