<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseTagRepository;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseFieldNoteTagCatalog as Cat;
use PDO;

/**
 * Jeu de données RÉALISTE des étiquettes de fiches (base SseFieldNoteTagsSqlite) :
 *   communauté 1 : 60 fiches de types variés ; un référentiel réglé (35 étiquettes actives dont 8 propres, 3 archivées),
 *     des types de fiche réglés (une trame, des champs et des étiquettes obligatoires), des règles ;
 *   communauté 2 : aucune ligne (catalogue livré tel quel), deux fiches.
 * seed() renvoie les identifiants utiles. Les vues se rendent avec les tableaux bâtis ici comme le font les contrôleurs.
 */
final class SseFieldNoteTagsFixture
{
    public const ACTOR = ['user_id' => 7, 'label' => 'Analyste Sept'];

    /** Étiquettes livrées gardées actives par la communauté 1 (les autres sont désactivées). */
    public const KEPT = [
        'HUMINT', 'SIGINT', 'IMINT', 'TECHINT', 'DOMEX', 'PIR', 'SIR', 'RFI', 'SPOTREP', 'SALUTE', 'PATROL DEBRIEF', 'SSE REPORT',
        'IED', 'WEAPONS CACHE', 'HVI', 'NOFORN', 'REL TO', 'ORCON', 'TIME-SENSITIVE', 'PERISHABLE', 'UNCONFIRMED', 'CORROBORATED',
    ];

    /** Catégories d'origine gardées actives par la communauté 1. */
    public const KEPT_CATEGORIES = ['ARMEMENT', 'PERSON', 'LOGIST', 'MOUV', 'SECUR'];

    /** Étiquettes propres : code, famille, libellé FR, libellé EN, description, couleur, complément, état. */
    public const CUSTOM = [
        ['ROUTE WATCH', 'theme', 'Surveillance d’itinéraire', 'Route watch', 'Passages relevés sur un axe suivi par le bureau.', 'blue', '', 'active'],
        ['CHECKPOINT', 'theme', 'Point de contrôle', 'Checkpoint', 'Fait constaté à un poste de contrôle.', 'green', '', 'active'],
        ['SAFE HOUSE', 'theme', 'Planque', 'Safe house', 'Logement utilisé pour cacher des personnes ou du matériel.', 'amber', '', 'active'],
        ['MARITIME', 'theme', 'Activité maritime', 'Maritime activity', 'Embarcations, quais et débarquements.', 'blue', '', 'active'],
        ['LTIOV', 'requirement', 'Dernier délai utile', 'Latest time information is of value', 'Échéance après laquelle la réponse ne sert plus.', 'violet', 'date', 'active'],
        ['KLE REPORT', 'product', 'Compte rendu d’entretien avec un responsable', 'Key leader engagement report', 'Entretien avec un notable : demandes, engagements, suites.', 'slate', '', 'active'],
        ['NARCO', 'category', 'Stupéfiants', 'Narcotics', 'Production, transport et vente de stupéfiants.', 'violet', '', 'active'],
        ['FRONTIERE', 'category', 'Frontière', 'Border', 'Passages, postes et trafics à la frontière.', 'green', '', 'active'],
        ['OP HIVER', 'theme', 'Opération Hiver', 'Operation Winter', 'Fiches de l’opération close en février.', 'slate', '', 'archived'],
        ['SECTEUR EST', 'theme', 'Secteur est', 'Eastern sector', 'Ancien découpage des secteurs.', 'slate', '', 'archived'],
        ['CONVOI JEUDI', 'theme', 'Convoi du jeudi', 'Thursday convoy', 'Suivi clos du convoi hebdomadaire.', 'amber', '', 'archived'],
    ];

    /**
     * @return array{notes: list<int>, tagged: int, long: int, short: int, other: list<int>}
     */
    public static function seed(PDO $pdo, bool $withTags = true): array
    {
        $ins = static function (string $table, array $row) use ($pdo): int {
            $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

            return (int) $pdo->lastInsertId();
        };
        $kinds = ['FRM', 'FRM', 'FRO', 'FRT', 'FRC', 'FRM', 'FRA', 'FRT', 'FRO', 'FRC'];
        $places = ['Pont nord de Nidasos', 'Port de Selakano', 'Col de Thronos', 'Hameau d’Orino', 'Dépôt de carburant de Pyrgos', 'Marché de Kalithea', 'Route côtière, borne 14', 'Ancienne carrière de Dorida', 'Quai des pêcheurs de Feres', 'Carrefour de Panagia'];
        $subjects = [
            'Convoi de nuit, trois véhicules feux éteints', 'Caisses de munitions dans un pick-up blanc', 'Entretien avec le maire du hameau', 'Drone observé au-dessus du poste',
            'Téléphone saisi lors de la fouille de l’entrepôt', 'Climat tendu au marché après les contrôles', 'Embarcation rapide accostée sans feux', 'Fils et détonateurs sous une bâche',
            'Homme correspondant au signalement de l’avis 12', 'Collecte d’argent chez les commerçants du port', 'Brouillage radio constaté pendant vingt minutes', 'Passeport présenté au poste, photographie conforme',
        ];
        $themes = [['ARMEMENT', 'LOGIST'], ['MOUV'], ['PERSON', 'SECUR'], ['LOGIST', 'MOUV', 'SECUR'], ['ARMEMENT'], ['PERSON'], ['SECUR', 'MOUV']];
        $urgencies = ['routine', 'routine', 'normal', 'urgent', 'routine', 'normal', 'critique', 'routine'];
        $sources = ['HUMINT', 'IMINT', 'TECHINT', '', 'SIGINT', 'HUMINT', 'OSINT', ''];
        $classes = ['interne', 'interne', 'encadrement', 'confidentiel', 'interne', 'encadrement'];
        $statuses = ['transmise', 'prise_en_compte', 'exploitee', 'transmise', 'sans_suite', 'prise_en_compte'];
        $authors = ['ALPHA-2', 'BRAVO-1', 'CHARLIE-4', 'Analyste Sept', 'DELTA-6'];
        $notes = [];
        for ($i = 0; $i < 60; $i++) {
            $ts = strtotime('2026-11-14 21:40:00') - $i * 17 * 3600 - ($i % 5) * 1260;
            $kind = $kinds[$i % count($kinds)];
            $subject = $subjects[$i % count($subjects)];
            $place = $places[($i * 3) % count($places)];
            $notes[] = $ins('sse_field_notes', [
                'tenant_id' => 1, 'context_id' => 1, 'reference_code' => sprintf('FR-2026-%06d', 100 - $i), 'note_kind' => $kind,
                'themes' => json_encode($themes[$i % count($themes)]), 'title' => $i % 4 === 3 ? '' : $subject . ', ' . mb_strtolower(mb_substr($place, 0, 1)) . mb_substr($place, 1),
                'body' => $subject . ". Constat fait sur place par l’équipe, à " . date('H \h i', $ts) . ".\n\nLe détail figure dans les pièces jointes ; la source est connue du poste.",
                'observed_at' => date('Y-m-d H:i:s', $ts), 'place_label' => $place, 'grid_reference' => sprintf('%05d %05d', 24000 + $i * 37, 22000 + $i * 53),
                'pos_x' => 15000.0 + $i * 21, 'pos_y' => 17000.0 + $i * 33, 'urgency' => $i === 0 ? 'critique' : $urgencies[$i % count($urgencies)],
                'intel_source' => $sources[$i % count($sources)] !== '' ? $sources[$i % count($sources)] : null, 'classification' => $i === 0 ? 'confidentiel' : $classes[$i % count($classes)],
                'source_reliability' => ['B', 'C', 'A', 'C', 'D'][$i % 5], 'info_credibility' => [2, 3, 1, 3, 4][$i % 5], 'status' => $statuses[$i % count($statuses)],
                'origin' => $i % 3 === 0 ? 'web' : 'atak', 'author_label' => $authors[$i % count($authors)], 'author_user_id' => 9,
                'author_unit' => $i % 2 === 0 ? 'Section Bravo' : '', 'created_at' => date('Y-m-d H:i:s', $ts + 900),
            ]);
        }
        $other = [];
        foreach (['Poste de Girna : passage d’un camion bâché', 'Relevé d’un marquage sur le mur de l’école'] as $n => $title) {
            $other[] = $ins('sse_field_notes', [
                'tenant_id' => 2, 'context_id' => 1, 'reference_code' => 'FR-2026-00000' . ($n + 1), 'note_kind' => 'FRM', 'themes' => '["MOUV"]', 'title' => $title,
                'body' => $title . '.', 'observed_at' => '2026-11-10 10:00:00', 'place_label' => 'Girna', 'urgency' => 'routine', 'classification' => 'interne',
                'status' => 'transmise', 'origin' => 'atak', 'author_label' => 'ECHO-3', 'created_at' => '2026-11-10 10:05:00',
            ]);
        }
        $ids = ['notes' => $notes, 'tagged' => 0, 'long' => $notes[0], 'short' => $notes[3], 'other' => $other];
        if (!$withTags) {
            return $ids;
        }

        $repo = new SseTagRepository();
        $service = new SseFieldNoteTagService($repo, static function (array $entry): void {
        });
        // Catalogue livré : la communauté 1 n'en garde qu'une partie.
        foreach (Cat::delivered() as $key => $tag) {
            $keep = $tag['family'] === 'category' ? in_array($tag['code'], self::KEPT_CATEGORIES, true) : in_array($tag['code'], self::KEPT, true);
            if ($keep === $tag['default_on']) {
                continue;
            }
            $repo->save(1, $key, [
                'family' => $tag['family'], 'code' => $tag['code'], 'name' => $tag['label_fr'], 'name_en' => $tag['label_en'], 'description' => $tag['desc_fr'],
                'description_en' => $tag['desc_en'], 'color' => $tag['color'], 'qualifier' => $tag['qualifier'], 'sort_order' => $tag['sort'], 'origin' => 'catalog',
                'status' => $keep ? Cat::STATUS_ACTIVE : Cat::STATUS_DISABLED,
            ], 7, SseTagRepository::SCOPE_NOTES);
        }
        // Une étiquette livrée renommée par la communauté.
        $service->saveTag(1, ['key' => 'pir', 'label_fr' => 'Besoin prioritaire du chef de corps', 'label_en' => 'Priority intelligence requirement', 'desc_fr' => 'Question prioritaire du chef de corps sur l’adversaire.', 'desc_en' => 'Priority question about the adversary.', 'color' => 'violet', 'sort' => 140], self::ACTOR);
        foreach (self::CUSTOM as [$code, $family, $fr, $en, $desc, $color, $qualifier, $status]) {
            $service->saveTag(1, ['code' => $code, 'family' => $family, 'label_fr' => $fr, 'label_en' => $en, 'desc_fr' => $desc, 'desc_en' => '', 'color' => $color, 'sort' => 0, 'qualifier' => $qualifier], self::ACTOR);
            if ($status !== 'active') {
                $service->setStatus(1, Cat::slug($code), $status, self::ACTOR);
            }
        }
        $service->saveKind(1, 'FRT', ['label' => 'Fiche technique', 'enabled' => true, 'sort' => 20, 'suggested' => ['techint', 'domex', 'sse-report', 'weapons-cache'], 'required_fields' => ['place_label'], 'required_tags' => ['family:discipline']], self::ACTOR);
        $service->saveKind(1, 'FRO', ['template' => "Effectif :\nActivité :\nLieu :\nUnité :\nHeure :\nÉquipement :", 'suggested' => ['imint', 'salute', 'sir', 'route-watch']], self::ACTOR);
        $service->saveKind(1, 'FRC', ['label' => 'Fiche d’entretien', 'suggested' => ['humint', 'kle-report', 'unconfirmed']], self::ACTOR);
        $service->saveKind(1, 'FRA', ['enabled' => false], self::ACTOR);
        $service->saveKind(1, 'FRM', ['required_tags' => ['family:requirement']], self::ACTOR);
        $service->saveRules(1, ['default_validity' => 'unconfirmed', 'precedence' => ['critique' => 'FLASH', 'urgent' => 'IMMEDIATE', 'normal' => 'PRIORITY', 'routine' => 'ROUTINE'], 'rel_to' => 'USA, ALTIS'], self::ACTOR);

        // Étiquetage : 50 fiches sur 60, par jeux réalistes.
        $sets = [
            [['humint', ''], ['pir', '2'], ['spotrep', ''], ['hvi', ''], ['noforn', ''], ['orcon', ''], ['time-sensitive', ''], ['perishable', '2026-11-20 18:00'], ['route-watch', '']],
            [['imint', ''], ['sir', '4'], ['salute', ''], ['route-watch', ''], ['unconfirmed', '']],
            [['techint', ''], ['domex', ''], ['sse-report', ''], ['weapons-cache', ''], ['corroborated', '']],
            [['humint', ''], ['kle-report', ''], ['rfi', '17'], ['rel-to', 'USA, ALTIS'], ['unconfirmed', '']],
            [['sigint', ''], ['pir', '1'], ['spotrep', ''], ['time-sensitive', ''], ['noforn', '']],
            [['humint', ''], ['patrol-debrief', ''], ['checkpoint', ''], ['narco', ''], ['unconfirmed', '']],
            [['imint', ''], ['maritime', ''], ['salute', ''], ['ltiov', '2026-11-18 06:00'], ['frontiere', '']],
            [['techint', ''], ['ied', ''], ['sse-report', ''], ['pir', '3'], ['corroborated', ''], ['op-hiver', '']],
            [['humint', ''], ['safe-house', ''], ['hvi', ''], ['sir', '2'], ['orcon', '']],
            [['domex', ''], ['sse-report', ''], ['convoi-jeudi', ''], ['secteur-est', '']],
        ];
        foreach ($notes as $i => $noteId) {
            if ($i % 6 === 5) {
                continue;
            }
            $set = $sets[$i % count($sets)];
            $repo->replaceLinks(1, Cat::SCOPE, Cat::OBJECT_NOTE, (string) $noteId, array_map(static fn (array $t): array => ['slug' => $t[0], 'qualifier' => $t[1]], $set), 7);
            $ids['tagged']++;
        }
        $service->forget();

        return $ids;
    }

    public static function service(): SseFieldNoteTagService
    {
        return new SseFieldNoteTagService(new SseTagRepository());
    }

    /** Variables de la liste des fiches, bâties comme SseFieldNoteController::index (sans le caviardage du back-office). @return array<string, mixed> */
    public static function listData(int $tenantId, array $filters = [], bool $canWrite = true): array
    {
        $filters += ['q' => '', 'status' => '', 'note_kind' => '', 'theme' => '', 'urgency' => '', 'tag' => '', 'tag_family' => ''];
        $service = self::service();
        $repo = new SseFieldNoteRepository();
        $notes = $repo->listForTenant($tenantId, array_merge($filters, ['limit' => 150], $service->noteFilters($tenantId, $filters)));
        $tags = $service->tagsForNotes($tenantId, array_map(static fn (array $n): int => (int) $n['id'], $notes));
        foreach ($notes as $i => $note) {
            $notes[$i] = $service->decorate($tenantId, $note, 'fr', $tags[(int) $note['id']] ?? []);
        }
        $kindOptions = [];
        foreach ($service->kinds($tenantId) as $code => $kind) {
            $kindOptions[$code] = $kind['label'];
        }
        $themeOptions = [];
        foreach ($service->activeThemes($tenantId) as $code => $theme) {
            $themeOptions[$code] = $code . ' — ' . $theme['label'];
        }

        return self::shell($canWrite, false) + [
            'title' => 'Fiches de renseignement', 'activeNav' => 'fiches', 'notes' => $notes,
            'counters' => ['total' => count($notes), 'today' => 2, 'immediate' => 3, 'untriaged' => 11], 'filters' => $filters,
            'kindOptions' => $kindOptions, 'themeOptions' => $themeOptions, 'urgencyOptions' => SseFieldNoteCatalog::urgencyOptions(),
            'statusOptions' => SseFieldNoteCatalog::STATUSES, 'tagFamilies' => $service->pickerFamilies($tenantId, 'fr', false), 'tagsReady' => $service->ready(),
        ];
    }

    /** Variables de la page d'une fiche, bâties comme SseFieldNoteController::show. @return array<string, mixed> */
    public static function showData(int $tenantId, int $noteId, bool $canWrite = true, bool $canManage = true): array
    {
        $service = self::service();
        $repo = new SseFieldNoteRepository();
        $note = $repo->findForTenant($tenantId, $noteId) ?? [];
        $note['attachments'] = [];
        $note = $service->decorate($tenantId, $note);
        \App\Services\Sse\Links\SseLinksWidget::useViewer(ArraySseLinkViewer::full());

        return self::shell($canWrite, $canManage) + [
            'title' => (string) ($note['reference_code'] ?? ''), 'activeNav' => 'fiches', 'note' => $note, 'linkedCase' => null, 'openCases' => [],
            'statusOptions' => SseFieldNoteCatalog::STATUSES, 'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX, 'history' => [],
            'tagFamilies' => $canWrite ? $service->pickerFamilies($tenantId, 'fr', true, array_column($note['tags'], 'key')) : [],
            'tagsReady' => $service->ready(), 'tagsMax' => Cat::TAGS_PER_NOTE,
            'tagSuggestions' => [(string) $note['note_kind'] => $service->kinds($tenantId)[(string) $note['note_kind']]['suggested'] ?? []],
            'relToDefault' => $service->rules($tenantId)['rel_to'],
        ];
    }

    /** Variables du rédacteur, bâties comme SseFieldNoteController::composer. @return array<string, mixed> */
    public static function composerData(int $tenantId, array $draft = [], array $errors = []): array
    {
        $service = self::service();
        $kinds = $service->kinds($tenantId);
        $active = $service->activeKinds($tenantId);

        return [
            'title' => 'Nouvelle fiche de renseignement', 'kinds' => $active, 'themes' => $service->activeThemes($tenantId),
            'tagFamilies' => $service->pickerFamilies($tenantId), 'tagsReady' => $service->ready(), 'tagsMax' => Cat::TAGS_PER_NOTE,
            'tagSuggestions' => array_map(static fn (array $k): array => $k['suggested'], $kinds),
            'kindTemplates' => array_filter(array_map(static fn (array $k): string => $k['template'], $kinds), static fn (string $t): bool => $t !== ''),
            'relToDefault' => $service->rules($tenantId)['rel_to'], 'urgencies' => SseFieldNoteCatalog::URGENCIES, 'sources' => SseFieldNoteCatalog::SOURCES,
            'bodyMaxLength' => SseFieldNoteCatalog::BODY_MAX_LENGTH, 'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX, 'themesMax' => SseFieldNoteCatalog::THEMES_MAX,
            'defaultKind' => isset($active['FRM']) ? 'FRM' : (string) array_key_first($active), 'authorLabel' => 'BRAVO-1', 'observedInputValue' => '2026-11-14T21:40',
            'errors' => $errors, 'draft' => $draft, 'cancelUrl' => url('atak/sse/fiches'), 'submitUrl' => url('atak/sse/fiches'),
        ];
    }

    /** Droits de session du contrôleur de gestion. @return \Closure(): array{tenant: int, user: int, guest: bool, read: bool, manage: bool, label: string} */
    public static function rights(int $tenantId, bool $manage, bool $read = true, bool $guest = false): \Closure
    {
        return static fn (): array => ['tenant' => $tenantId, 'user' => $manage ? 7 : 9, 'guest' => $guest, 'read' => $read, 'manage' => $manage, 'label' => $manage ? 'Analyste Sept' : 'Lecteur Neuf'];
    }

    /** @return array<string, mixed> */
    private static function shell(bool $canWrite, bool $canManage): array
    {
        return ['canManage' => $canManage, 'canWrite' => $canWrite, 'canGrant' => false, 'isGuest' => false, 'clearanceUntil' => 0, 'guestLabel' => '', 'sseTheme' => 'bureau'];
    }
}
