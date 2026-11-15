<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Services\Community\TenantTypeConfig;
use App\Support\JnetMemberPaths;
use App\Support\JnetSpaceTree;
use App\Support\JnetUi;

/**
 * Page d'arrivée des membres sur JNET (« Une unité. Un espace. ») : le registre des unités avec le droit du lecteur et
 * ses non-lus, puis les rubriques personnelles (profil, ATAK, données, service) qui renvoient aux pages existantes.
 *
 * Aucun droit n'est décidé ici : le registre ne montre que les unités déjà visibles du lecteur (arbre ORBAT filtré par
 * confidentialité) et chaque lien mène à une page qui refait sa propre vérification côté serveur.
 */
final class JnetMemberHub
{
    public const RIGHT_ADMIN = 'admin';
    public const RIGHT_CHIEF = 'chief';
    public const RIGHT_WRITE = 'write';
    public const RIGHT_READ = 'read';

    public const RIGHT_LABELS = [
        self::RIGHT_ADMIN => 'Administration',
        self::RIGHT_CHIEF => 'Chef de l’unité',
        self::RIGHT_WRITE => 'Lecture et écriture',
        self::RIGHT_READ => 'Lecture seule',
    ];

    /** Adresse de la demande d'affectation (page existante « Mon avancement »). */
    public const REQUEST_PATH = JnetMemberPaths::BASE . '/avancement#mav-aff-title';

    /**
     * Droit du lecteur sur un espace : administrateur de la plateforme, chef de l'unité (ou d'une unité au-dessus),
     * lecture et écriture (membre de l'unité ou d'une unité au-dessus : mêmes règles que la publication), sinon
     * lecture seule.
     *
     * @param list<int> $viewerUnitIds
     * @param list<int> $commandedUnitIds
     * @return array{key: string, label: string}
     */
    public static function rightFor(JnetSpaceTree $tree, int $spaceId, array $viewerUnitIds, array $commandedUnitIds, bool $orgWriter): array
    {
        $key = self::RIGHT_READ;
        if ($orgWriter) {
            $key = self::RIGHT_ADMIN;
        } else {
            foreach ($commandedUnitIds as $c) {
                if ($tree->has($c) && $tree->isWithin($spaceId, $c)) {
                    $key = self::RIGHT_CHIEF;
                    break;
                }
            }
            if ($key === self::RIGHT_READ && $tree->canPostIn($viewerUnitIds, $spaceId, false)) {
                $key = self::RIGHT_WRITE;
            }
        }

        return ['key' => $key, 'label' => self::RIGHT_LABELS[$key]];
    }

    /**
     * Registre des espaces : ceux du lecteur d'abord, puis les autres, chacun dans l'ordre de l'ORBAT ; `tree` reprend
     * toutes les unités visibles dans l'ordre de la hiérarchie, avec leur profondeur et leur filiation.
     *
     * @param list<int> $viewerUnitIds unités d'appartenance
     * @param list<int> $commandedUnitIds
     * @param array<int, int> $unread non-lus par espace (JnetSpaceService::unreadByUnit)
     * @param array<int, array<string, mixed>> $settings réglages d'espace par unité (JnetUnitSpaceSettings::findAll)
     * @param array<int, array{total?: int, available?: int}> $strengths effectif par unité (JnetSpaceService::strengthByUnit)
     * @param array<int, list<array<string, mixed>>> $linkMarks liaisons d'unités actives par unité (JnetLinkService::hubMarks) :
     *        un simple marquage de la ligne, qui n'ouvre rien
     * @return array{mine: list<array<string, mixed>>, others: list<array<string, mixed>>, tree: list<array<string, mixed>>}
     */
    public static function register(
        JnetSpaceTree $tree,
        array $viewerUnitIds,
        array $commandedUnitIds,
        bool $orgWriter,
        array $unread,
        array $settings,
        array $strengths = [],
        array $linkMarks = []
    ): array {
        $mine = [];
        $others = [];
        $all = [];
        $ordered = [];
        $walk = static function (int $id) use (&$walk, &$ordered, $tree): void {
            foreach ($tree->childrenOf($id) as $child) {
                $ordered[] = $child;
                $walk($child);
            }
        };
        $walk(JnetSpaceTree::ORG);
        foreach ($ordered as $id) {
            $node = (array) $tree->node($id);
            $look = JnetUnitSpaceSettings::look($settings[$id] ?? JnetUnitSpaceSettings::defaults(), $node);
            $members = (int) ($node['strength'] ?? 0);
            $total = (int) ($strengths[$id]['total'] ?? 0);
            $available = (int) ($strengths[$id]['available'] ?? 0);
            $parent = self::parentLabel($tree, $id);
            $isMine = in_array($id, $viewerUnitIds, true);
            $row = [
                'id' => $id,
                'label' => (string) ($node['label'] ?? 'Unité'),
                'members' => $members,
                'parent' => $parent,
                'depth' => max(1, (int) ($node['depth'] ?? 1)),
                'chain' => self::chainLabels($tree, $id),
                'mine' => $isMine,
                'right' => self::rightFor($tree, $id, $viewerUnitIds, $commandedUnitIds, $orgWriter),
                'unread' => (int) ($unread[$id] ?? 0),
                'href' => url('jnet/u/' . $id),
                'requestHref' => url(self::REQUEST_PATH),
                'accent' => (string) $look['accent'],
                'monogram' => (string) $look['monogram'],
                'emblem' => $look['emblem'],
                'banner' => $look['banner'],
                'links' => array_values((array) ($linkMarks[$id] ?? [])),
                'photo' => JnetUi::genericPhoto($id),
            ] + self::describe($node, $available, $total > 0 ? $total : $members, $parent, $total > 0);
            $all[] = $row;
            if ($isMine) {
                $mine[] = $row;
            } else {
                $others[] = $row;
            }
        }

        return ['mine' => $mine, 'others' => $others, 'tree' => $all];
    }

    /**
     * Ce qu'on lit sous le nom d'une unité : son type (échelon, fonction) puis des faits (chef, effectif, parent).
     * Jamais le code brut de l'ORBAT : sans échelon ni fonction connus, « Unité ».
     *
     * @param array<string, mixed> $node nœud de JnetSpaceTree
     * @return array{kind: string, echelon: string, facts: list<string>}
     */
    public static function describe(array $node, int $available, int $total, string $parent, bool $hasAvailability): array
    {
        $echelon = JnetUi::echelonLabel((string) ($node['echelon'] ?? ''));
        $function = JnetUi::functionLabel((string) ($node['function'] ?? ''));
        $kind = implode(' · ', array_values(array_filter([$echelon, $function], static fn (string $v): bool => $v !== '')));
        $facts = [];
        $leader = trim((string) ($node['leader'] ?? ''));
        if ($leader !== '') {
            $facts[] = 'Chef : ' . $leader;
        }
        if ($total > 0) {
            $facts[] = $hasAvailability
                ? $available . ' / ' . $total . ' disponible' . ($available > 1 ? 's' : '')
                : JnetUi::count($total, 'membre');
        }
        if ($parent !== '') {
            $facts[] = 'Sous ' . $parent;
        }

        return ['kind' => $kind !== '' ? $kind : 'Unité', 'echelon' => $echelon, 'facts' => $facts];
    }

    /**
     * Fil d'échelons au-dessus d'une unité, de l'Organisation à son parent.
     *
     * @return list<string>
     */
    private static function chainLabels(JnetSpaceTree $tree, int $id): array
    {
        $out = [];
        foreach (array_reverse($tree->ancestors($id)) as $a) {
            $label = trim((string) ($tree->node($a)['label'] ?? ''));
            if ($label !== '') {
                $out[] = $label;
            }
        }

        return $out;
    }

    private static function parentLabel(JnetSpaceTree $tree, int $id): string
    {
        $parent = $tree->parentOf($id);

        return $parent !== null && $parent !== JnetSpaceTree::ORG ? (string) ($tree->node($parent)['label'] ?? '') : '';
    }
    /**
     * Rubriques personnelles. Chaque entrée est une page existante ; celles d'un module que le profil de la communauté
     * ne propose pas sont retirées (même règle que les menus : TenantTypeConfig::uriAllowed). Les pages « Ma situation »
     * sont sous /jnet/moi (JnetMemberPaths) : la même liste nourrit le hub et le rail « Mon espace » des pages.
     *
     * Entrée : label, desc, href, badge (nombre à signaler), path (chemin sans slash), page (clé de JnetMemberPaths::PAGES
     * quand c'en est une : sert à allumer l'entrée courante du rail).
     *
     * @param array{tenantType?: string, pilotLogbook?: bool, unreadMail?: int, badges?: array<string, int>} $ctx
     * @return list<array{key: string, title: string, lead: string, items: list<array{label: string, desc: string, href: string, badge: int, path: string, page: string}>}>
     */
    public static function sections(array $ctx): array
    {
        $type = (string) ($ctx['tenantType'] ?? 'full');
        $pilot = !empty($ctx['pilotLogbook']);
        $mail = max(0, (int) ($ctx['unreadMail'] ?? 0));
        $badges = (array) ($ctx['badges'] ?? []);
        $badge = static fn (string $key): int => max(0, (int) ($badges[$key] ?? 0));
        $moi = static fn (string $page): array => [JnetMemberPaths::path($page), $page];

        // [libellé, description, [chemin, page], pastille, drapeau]
        $catalog = [
            ['key' => 'unite', 'title' => 'Mon unité', 'lead' => 'Votre affectation, vos camarades et ce que l’unité traite en ce moment.', 'items' => [
                ['Fiche de mon unité', 'Affectation, fonction, chef et camarades', $moi('unite')],
                ['Messagerie', 'Messages reçus et envoyés dans l’extranet', ['jnet/courrier', ''], $mail],
                ['Opérations', 'Missions en cours et planifiées', ['jnet/operations', '']],
                ['Annuaire du personnel', 'Retrouver un camarade, sa fonction, sa section', ['jnet/personnel', '']],
                ['Bibliothèque', 'Consignes, doctrines et documents publiés', ['jnet/bibliotheque', '']],
                ['Événements', 'Le calendrier de la communauté et vos réponses', $moi('evenements'), $badge('events_rsvp_pending')],
            ]],
            ['key' => 'profil', 'title' => 'Mon profil', 'lead' => 'Votre fiche, votre compte et votre parcours.', 'items' => [
                ['Ma fiche', 'Votre fiche personnelle et son suivi', $moi('ma-fiche')],
                ['Mon compte', 'Connexion, portrait et préférences', ['account', '']],
                ['Mes qualifications', 'Brevets obtenus, en cours et à renouveler', $moi('qualifications'), $badge('qualifications_expiring')],
                ['Mon avancement', 'Campagnes, demandes d’avancement et d’affectation', $moi('avancement')],
                ['Dossier de carrière', 'La frise de votre parcours', $moi('carriere')],
                ['Décorations', 'Distinctions reçues', $moi('decorations')],
                ['Ma dotation', 'Ce qui vous est attribué', $moi('dotation')],
                ['Mes accès', 'Rôle, grade et demandes de droits', ['account/acces', '']],
            ]],
            ['key' => 'atak', 'title' => 'Mon ATAK', 'lead' => 'Votre téléphone, sa liaison avec Athena et ce que vous écoutez en jeu.', 'items' => [
                ['Ma liaison ATAK', 'Contrôles, session et historique de liaison', $moi('liaison-atak')],
                ['Première liaison', 'Relier un téléphone pas à pas', $moi('premiere-liaison')],
                ['Mes appareils', 'Téléphones liés, à retirer si besoin', $moi('appareils')],
                ['Musique', 'Bibliothèque et playlists écoutées en jeu', $moi('musique')],
                ['Mon carnet de vol', 'Vos vols relevés en jeu et ceux que vous notez', $moi('carnet-de-vol'), 0, 'pilot'],
            ]],
            ['key' => 'donnees', 'title' => 'Mes données', 'lead' => 'Vos pièces, vos papiers et ce que le site garde sur vous.', 'items' => [
                ['Mon coffre', 'Pièces RH, brevets et attestations', $moi('coffre')],
                ['Mes papiers', 'Identité et papiers pour le jeu de rôle', $moi('mes-papiers')],
                ['Fiabilisation des données', 'Relire votre fiche et confirmer qu’elle est exacte', $moi('fiabilisation'), $badge('data_campaign_pending')],
                ['Exporter ou supprimer mes données', 'Copie de vos informations, suppression du compte', ['account/donnees', '']],
            ]],
            ['key' => 'service', 'title' => 'Mon service', 'lead' => 'Ce qui vous attend : tâches, avis demandés, démarches.', 'items' => [
                ['Mon service', 'Tâches, avis à donner et agenda', ['mon-service', '']],
                ['Mes démarches', 'Absences, élévation, souhaits d’évolution et documents', $moi('mes-demarches'), $badge('my_enlistments_pending')],
            ]],
        ];

        $out = [];
        foreach ($catalog as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                [$label, $desc, [$path, $page]] = $item;
                if (($item[4] ?? '') === 'pilot' && !$pilot) {
                    continue;
                }
                if (!TenantTypeConfig::uriAllowed($type, $path)) {
                    continue;
                }
                $items[] = ['label' => $label, 'desc' => $desc, 'href' => url($path), 'badge' => (int) ($item[3] ?? 0), 'path' => $path, 'page' => $page];
            }
            if ($items !== []) {
                $out[] = ['key' => $section['key'], 'title' => $section['title'], 'lead' => $section['lead'], 'items' => $items];
            }
        }

        return $out;
    }
}
