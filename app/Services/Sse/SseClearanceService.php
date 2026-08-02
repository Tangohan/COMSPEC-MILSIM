<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePortalSettingsRepository;

/**
 * Habilitation de lecture SSE — jusqu'où la session courante peut lire en clair.
 *
 * ## Pourquoi cette classe existe
 *
 * L'écran de déclassification produisait une version expurgée au niveau demandé
 * dans l'URL, sans vérifier que le demandeur y avait droit. Produire un document
 * expurgé et restreindre qui peut le lire sont deux choses différentes : la
 * première seule ne protège rien, il suffisait de changer le paramètre.
 *
 * ## Le plafond
 *
 * Une session porte un **plafond d'habilitation**. Toute demande de lecture est
 * rabattue dessus, jamais l'inverse. Le paramètre d'URL exprime un souhait, il
 * n'accorde rien.
 *
 * ## D'où vient le plafond
 *
 * Deux sources, dans cet ordre :
 *
 *  1. **Habilitation explicite** — permissions `atak.sse.clearance.*`. C'est la
 *     voie propre quand la communauté veut gérer ses habilitations à la main.
 *  2. **Report des rôles existants** — si aucune habilitation explicite n'est
 *     accordée, le plafond est déduit des permissions déjà en place.
 *
 * Ce report est délibéré. Sans lui, la mise en production de cette version
 * mettrait tout le monde au plancher tant qu'un administrateur n'a pas assigné
 * les nouvelles permissions — et personne ne comprendrait pourquoi les dossiers
 * sont devenus illisibles du jour au lendemain. Une règle de sécurité qu'on
 * désactive en urgence parce qu'elle a tout cassé ne protège plus rien.
 */
final class SseClearanceService
{
    /** Habilitation portée par un code d'accès invité. */
    public const SESSION_LEVEL = 'sse_clearance_level';

    /** Permissions d'habilitation explicites, du plus bas au plus haut. */
    public const PERMISSIONS = [
        SseCaseRepository::CLASS_COMMAND => 'atak.sse.clearance.encadrement',
        SseCaseRepository::CLASS_CONFIDENTIAL => 'atak.sse.clearance.confidentiel',
        SseCaseRepository::CLASS_RESTRICTED => 'atak.sse.clearance.tres_restreint',
    ];

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SsePortalSettingsRepository $settings = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->settings ??= new SsePortalSettingsRepository();
    }

    /**
     * Le verrou d'ouverture par classification est-il armé pour cette communauté ?
     *
     * Désarmé par défaut : voir SsePortalSettingsRepository::CASE_LOCK pour la
     * raison. Il s'arme depuis le registre des dossiers, après revue de ce qu'il
     * fermerait.
     */
    public function caseLockEnabled(int $tenantId): bool
    {
        return $this->settings->getBool($tenantId, SsePortalSettingsRepository::CASE_LOCK, false);
    }

    /**
     * Les écrans de travail sont-ils caviardés pour cette communauté ?
     *
     * Distinct du verrou de dossier : les documents de diffusion sont **toujours**
     * rabattus sur l'habilitation du lecteur, c'est leur objet. Les écrans de
     * travail ne le sont que sur décision, parce que les caviarder retire des
     * informations dont la cellule se sert pour travailler.
     */
    public function workingRedactionEnabled(int $tenantId): bool
    {
        return $this->settings->getBool($tenantId, SsePortalSettingsRepository::WORKING_REDACTION, false);
    }

    /**
     * Rabat une liste de fiches personnes si les écrans de travail sont caviardés.
     *
     * @param list<array<string, mixed>> $people
     * @return list<array<string, mixed>>
     */
    public function redactPeopleForScreens(array $people, int $tenantId, ?int $caseId = null): array
    {
        if ($people === [] || !$this->workingRedactionEnabled($tenantId)) {
            return $people;
        }

        $redaction = new SseRedactionService();
        $manual = $caseId !== null ? $redaction->listForCase($caseId, $tenantId) : [];

        return $redaction->apply(
            ['case' => [], 'people' => $people, 'sites' => []],
            $this->maxLevel(),
            $manual
        )['people'];
    }

    /**
     * Qui pourra encore ouvrir un dossier de cette classification, une fois le
     * verrou armé. Formulé en rôles, pas en permissions : c'est ce que
     * l'encadrement lit pour décider.
     */
    public static function whoCanOpen(string $classification): string
    {
        // Chaque libellé se suffit à lui-même : dans le registre les dossiers ne
        // sont pas triés par classification, un « idem ci-dessus » ne renverrait
        // à rien de visible.
        return match ($classification) {
            SseCaseRepository::CLASS_RESTRICTED =>
                'Administration du portail et droit d’octroi',
            SseCaseRepository::CLASS_CONFIDENTIAL =>
                'Administration, droit d’octroi, gestion des dossiers',
            SseCaseRepository::CLASS_COMMAND =>
                'Tout membre ayant accès au portail SSE',
            default =>
                'Tout le monde, invités compris',
        };
    }

    /**
     * Nombre de dossiers qu'armer le verrou rendrait inaccessibles à la session
     * courante — la seule mesure que le portail peut donner honnêtement sans
     * énumérer les habilitations de chaque membre.
     *
     * @param list<array<string, mixed>> $cases
     */
    public function countLockedForMe(array $cases): int
    {
        $n = 0;
        foreach ($cases as $case) {
            if ($this->caseAboveClearance($case)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Plafond de lecture de la session courante.
     */
    public function maxLevel(): string
    {
        // Un invité ne lit que ce que son code lui accorde. Par défaut : le
        // plancher. On ne fait pas confiance à quelqu'un parce qu'il détient un
        // code — le code dit qui il est, pas ce qu'il a le droit de voir.
        if ($this->access->isGuest()) {
            $carried = (string) (Session::get(self::SESSION_LEVEL, '') ?: '');

            return isset(SseRedactionService::LEVELS[$carried])
                ? $carried
                : SseCaseRepository::CLASS_INTERNAL;
        }

        if (!function_exists('can')) {
            return SseCaseRepository::CLASS_INTERNAL;
        }

        // 1. Habilitation explicite — la plus haute accordée l'emporte.
        $explicit = null;
        foreach (self::PERMISSIONS as $level => $permission) {
            if (can($permission)) {
                $explicit = $level;
            }
        }
        if ($explicit !== null) {
            return $explicit;
        }

        // 2. Report des rôles existants.
        if (can('admin.access') || can('atak.sse.grant')) {
            return SseCaseRepository::CLASS_RESTRICTED;
        }
        if (can('atak.sse.case.manage')) {
            return SseCaseRepository::CLASS_CONFIDENTIAL;
        }
        if (can('atak.sse.access') || can('atak.sse.cases')) {
            return SseCaseRepository::CLASS_COMMAND;
        }

        return SseCaseRepository::CLASS_INTERNAL;
    }

    /**
     * Rabat une demande de lecture sur le plafond.
     *
     * Ne lève pas d'erreur : demander plus haut que son habilitation n'est pas une
     * faute, c'est le comportement normal de quelqu'un qui explore l'écran. On
     * sert ce qu'il a le droit de voir, et on le lui dit.
     */
    public function clamp(string $requested): string
    {
        $max = $this->maxLevel();
        if (!isset(SseRedactionService::LEVELS[$requested])) {
            return $max;
        }

        return SseRedactionService::levelRank($requested) > SseRedactionService::levelRank($max)
            ? $max
            : $requested;
    }

    public function allows(string $level): bool
    {
        return SseRedactionService::levelRank($level) <= SseRedactionService::levelRank($this->maxLevel());
    }

    /**
     * D'où vient le plafond, en clair — affiché à l'écran.
     *
     * Une habilitation qu'on ne peut pas expliquer se conteste mal : l'opérateur
     * doit pouvoir dire à son encadrement pourquoi il ne voit pas quelque chose.
     */
    public function origin(): string
    {
        if ($this->access->isGuest()) {
            $carried = (string) (Session::get(self::SESSION_LEVEL, '') ?: '');

            return isset(SseRedactionService::LEVELS[$carried])
                ? 'Habilitation portée par votre code d’accès temporaire.'
                : 'Accès invité sans habilitation : lecture au niveau le plus large uniquement.';
        }

        if (function_exists('can')) {
            foreach (array_reverse(self::PERMISSIONS, true) as $level => $permission) {
                if (can($permission)) {
                    return sprintf(
                        'Habilitation « %s » accordée à votre compte.',
                        SseRedactionService::levelLabel($level)
                    );
                }
            }
            if (can('admin.access') || can('atak.sse.grant')) {
                return 'Report de vos droits d’administration du portail SSE.';
            }
            if (can('atak.sse.case.manage')) {
                return 'Report de vos droits de gestion des dossiers.';
            }
            if (can('atak.sse.access') || can('atak.sse.cases')) {
                return 'Report de votre accès au portail SSE.';
            }
        }

        return 'Aucune habilitation reconnue.';
    }

    /**
     * Le dossier est-il tenu à un niveau que la session ne peut pas lire ?
     *
     * On le signale sans bloquer : décider qu'un dossier devient inaccessible à
     * ceux qui l'ouvraient hier est un arbitrage d'exploitation, pas une décision
     * technique. L'écran le dit, l'encadrement tranche.
     *
     * @param array<string, mixed> $case
     */
    public function caseAboveClearance(array $case): bool
    {
        $classification = (string) ($case['classification'] ?? '');
        if (!isset(SseRedactionService::LEVELS[$classification])) {
            return false;
        }

        return !$this->allows($classification);
    }
}
