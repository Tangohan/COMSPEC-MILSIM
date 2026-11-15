<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Sse\Links\SseLinksWidget;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseFieldNoteTimeline;

/**
 * Jeux de données RÉALISTES de la page de lecture d'une fiche de renseignement (views/atak/sse/field_note_show.php), sur la
 * base sqlite du widget « Liaisons » :
 *   a : fiche technique courte (3 catégories, C3, classée sans suite, ni pièce ni dossier), vue par une analyste ;
 *   b : fiche longue (25 lignes, 4 pièces, dossier, 6 liaisons, 8 entrées d'historique, urgence critique, Secret), analyste ;
 *   c : la fiche b vue par un lecteur en lecture seule ;
 *   d : la fiche a vue par un rédacteur (écrit, ne gère pas le suivi).
 * Les tableaux ont la forme rendue par SseFieldNoteRepository (hydrate, listAttachments) et SseFieldNoteController::show.
 */
final class SseFieldNoteShowFixture
{
    /** @return array<string, mixed> variables de la vue */
    public static function scenario(string $variant): array
    {
        $pdo = SseLinksSqlite::open();
        $ids = SseLinksFixture::seed($pdo);
        $long = in_array($variant, ['b', 'c'], true);
        $reader = $variant === 'c';
        $manage = in_array($variant, ['a', 'b'], true);

        $ins = static function (string $table, array $row) use ($pdo): int {
            $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

            return (int) $pdo->lastInsertId();
        };
        $note = $long ? self::longNote() : self::shortNote();
        $note['id'] = $ins('sse_field_notes', [
            'tenant_id' => 1, 'reference_code' => $note['reference_code'], 'title' => $note['title'], 'classification' => $note['classification'],
            'status' => $note['status'], 'case_id' => $long ? $ids['case'] : null, 'author_user_id' => 9, 'created_at' => $note['created_at'],
        ]);
        $case = null;
        if ($long) {
            $note['case_id'] = $ids['case'];
            $case = ['id' => $ids['case'], 'reference_code' => 'SSE-2026-0001', 'title' => 'Réseau des passeurs'];
            foreach ([
                ['person', $ids['person'], 'cited_in', 'La fiche décrit son passage au pont nord.', 'manual.link', 'user', 7],
                ['identity', $ids['identity'], 'related', 'Identité relevée sur le passeport présenté.', 'cerbere.link.check_document', 'system', null],
                ['paper', $ids['paper1'], 'related', 'Passeport photographié en pièce jointe 2.', 'manual.link', 'user', 7],
                ['check', $ids['check3'], 'related', 'Contrôle effectué le même soir au pont nord.', 'cerbere.link.check_retro', 'system', null],
                ['notice', $ids['notice'], 'related', 'L’avis de recherche vise la personne décrite.', 'cerbere.detect.wanted', 'system', null],
            ] as $n => [$type, $id, $relation, $reason, $rule, $by, $uid]) {
                $ins('sse_entity_links', [
                    'tenant_id' => 1, 'source_type' => 'note', 'source_id' => (string) $note['id'], 'target_type' => $type, 'target_id' => (string) $id,
                    'relation' => $relation, 'reason' => $reason, 'rule_code' => $rule, 'created_by' => $by, 'created_by_user_id' => $uid,
                    'created_at' => '2026-11-1' . $n . ' 09:00:00',
                ]);
            }
        }
        SseLinksWidget::useViewer(
            $reader ? new ArraySseLinkViewer(1, 9, ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain'], [], false, 'confidentiel') : ArraySseLinkViewer::full()
        );

        return [
            'title' => $note['reference_code'],
            'activeNav' => 'fiches',
            'note' => $note,
            'linkedCase' => $case,
            'openCases' => $manage ? [
                ['id' => $ids['case'], 'label' => 'SSE-2026-0001 — Réseau des passeurs'],
                ['id' => $ids['case'] + 50, 'label' => 'SSE-2026-0007 — Dépôt de la vallée de Nidasos'],
                ['id' => $ids['case'] + 51, 'label' => 'SSE-2026-0011 — Convois de nuit sur la route côtière'],
            ] : [],
            'statusOptions' => SseFieldNoteCatalog::STATUSES,
            'attachmentsMax' => SseFieldNoteCatalog::ATTACHMENTS_MAX,
            'history' => $manage && $long ? SseFieldNoteTimeline::fromEvents(self::events(), true) : [],
            'canManage' => $manage,
            'canWrite' => !$reader,
            'canGrant' => false,
            'isGuest' => false,
            'clearanceUntil' => 0,
            'guestLabel' => '',
            'sseTheme' => 'bureau',
        ];
    }

    public static function close(): void
    {
        SseLinksWidget::useViewer();
        SseLinksSqlite::close();
    }

    /** @return array<string, mixed> */
    private static function base(): array
    {
        return [
            'tenant_id' => 1, 'context_id' => 1, 'case_id' => null, 'interest_case_id' => null, 'author_user_id' => 9,
            'attachments' => [], 'attachment_count' => 0, 'triage_note' => null, 'triaged_at' => null, 'triaged_by' => null,
            'pos_z' => null, 'lat' => null, 'lng' => null, 'origin' => 'atak', 'origin_label' => SseFieldNoteCatalog::originLabel('atak'),
        ];
    }

    /** @return array<string, mixed> */
    private static function finish(array $note): array
    {
        $note['theme_labels'] = array_map(static fn (string $c): string => SseFieldNoteCatalog::themeLabel($c), $note['themes']);
        $note['note_kind_label'] = SseFieldNoteCatalog::kindLabel($note['note_kind']);
        $note['urgency_label'] = SseFieldNoteCatalog::urgencyLabel($note['urgency']);
        $note['intel_source_label'] = SseFieldNoteCatalog::sourceLabel((string) $note['intel_source']);
        $note['status_label'] = SseFieldNoteCatalog::statusLabel($note['status']);
        $ts = strtotime((string) $note['observed_at']);
        $note['observed_date_label'] = date('d/m/Y', $ts);
        $note['observed_time_label'] = date('H:i', $ts);
        $note['has_position'] = true;
        $note['attachment_count'] = count($note['attachments']);

        return $note + self::base();
    }

    /** @return array<string, mixed> */
    private static function shortNote(): array
    {
        return self::finish([
            'reference_code' => 'FR-2026-000002', 'title' => '', 'note_kind' => 'FRT', 'themes' => ['ARMEMENT', 'LOGIST', 'SECUR'],
            'urgency' => 'routine', 'intel_source' => 'TECHINT', 'source_reliability' => 'C', 'info_credibility' => 3,
            'classification' => 'interne', 'status' => 'sans_suite', 'observed_at' => '2026-09-01 20:10:00', 'created_at' => '2026-09-01 20:14:00',
            'place_label' => 'Nidasos', 'grid_reference' => '24227 22393', 'pos_x' => 15193.0, 'pos_y' => 17303.0,
            'body' => "Deux caisses de munitions de 7,62 mm posées à l’arrière d’un pick-up blanc, bâche relevée.\nLe véhicule est resté dix minutes devant l’entrepôt puis est reparti vers le nord.",
            'author_label' => 'ALPHA-2', 'author_unit' => '',
        ] + self::base());
    }

    /** @return array<string, mixed> */
    private static function longNote(): array
    {
        $lines = [
            'À 21 h 40, trois véhicules sont arrivés au pont nord par la route de la vallée, feux éteints.',
            'Le premier, un tout-terrain gris sans plaque, s’est arrêté à cinquante mètres du poste.',
            'Quatre hommes en sont descendus ; deux portaient un fusil d’assaut en bandoulière.',
            'Le deuxième véhicule, un camion bâché, est resté moteur tournant au milieu du pont.',
            'Le chauffeur n’a pas quitté la cabine pendant toute la durée de l’arrêt.',
            'Une femme d’une quarantaine d’années a présenté un passeport au chef de poste.',
            'Le document porte le numéro TK1234567 ; la photographie correspond à la porteuse.',
            'Elle a déclaré se rendre à Nidasos pour « une livraison de matériel agricole ».',
            '',
            'La fouille du camion a montré douze caisses en bois clouées, sans marquage.',
            'Deux d’entre elles ont été ouvertes : pièces détachées de groupes électrogènes.',
            'Sous les caisses, un double fond d’environ vingt centimètres n’a pas pu être ouvert.',
            'Un des hommes armés s’est approché du poste et a demandé combien de temps durerait le contrôle.',
            'Il parlait la langue locale avec un fort accent du sud du pays.',
            'Le troisième véhicule, une berline sombre, est resté en retrait, à l’entrée du pont.',
            'Son conducteur a été vu en train de téléphoner à deux reprises.',
            '',
            'À 22 h 05, le convoi a été autorisé à repartir faute d’élément permettant de le retenir.',
            'Il a pris la direction du nord, vers l’ancien dépôt de carburant.',
            'La berline a suivi à environ trois cents mètres, feux allumés cette fois.',
            'Le poste a relevé les traces de pneus : le camion était nettement plus chargé qu’annoncé.',
            'Une habitante du hameau voisin affirme que ce convoi passe chaque jeudi depuis trois semaines.',
            'Elle dit avoir vu les mêmes hommes décharger des caisses au dépôt, de nuit.',
            'Ces déclarations n’ont pas pu être recoupées ; la source est connue du poste et jugée sérieuse.',
            'Recommandation : placer une observation sur le dépôt jeudi prochain, entre 21 h et 23 h.',
            'Le double fond du camion justifie une fouille complète lors du prochain passage.',
            'Les photographies jointes montrent le camion, le passeport, les caisses et les traces de pneus.',
        ];
        $svg = static function (int $hue, string $shape): string {
            return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="480" height="360"><rect width="480" height="360" fill="hsl(' . $hue . ',22%,26%)"/><rect y="240" width="480" height="120" fill="hsl(' . $hue . ',18%,18%)"/>' . $shape . '</svg>');
        };
        $attachments = [];
        foreach ([
            ['photo', 'Camion bâché arrêté au milieu du pont', 30, '<rect x="120" y="150" width="220" height="100" fill="#8a8f7a"/><rect x="340" y="185" width="70" height="65" fill="#5d6455"/><circle cx="170" cy="255" r="22" fill="#111"/><circle cx="370" cy="255" r="22" fill="#111"/>'],
            ['document', 'Passeport TK1234567, page d’identité', 210, '<rect x="130" y="70" width="220" height="220" rx="8" fill="#e9e3d2"/><rect x="150" y="95" width="70" height="90" fill="#9aa3ad"/><rect x="235" y="100" width="95" height="10" fill="#555"/><rect x="235" y="125" width="80" height="10" fill="#777"/><rect x="150" y="215" width="180" height="8" fill="#777"/><rect x="150" y="235" width="180" height="8" fill="#777"/>'],
            ['photo', 'Caisses clouées sans marquage', 40, '<rect x="90" y="150" width="130" height="100" fill="#a9844f"/><rect x="230" y="130" width="150" height="120" fill="#b08d58"/><path d="M90 150l130 100M230 130l150 120" stroke="#6b5230" stroke-width="5"/>'],
            ['croquis', '', 150, '<path d="M60 300c80-70 120-60 180-20s110 20 180-40" stroke="#e5e7eb" stroke-width="6" fill="none"/><path d="M60 320c80-70 120-60 180-20s110 20 180-40" stroke="#e5e7eb" stroke-width="6" fill="none"/>'],
        ] as $i => [$kind, $caption, $hue, $shape]) {
            $attachments[] = [
                'id' => 40 + $i, 'note_id' => 0, 'tenant_id' => 1, 'kind' => $kind, 'kind_label' => SseFieldNoteCatalog::attachmentKindLabel($kind),
                'caption' => $caption, 'mime_type' => 'image/svg+xml', 'is_image' => true, 'url' => $svg($hue, $shape), 'byte_size' => 20480,
                'file_path' => 'sse/fiches/' . (40 + $i) . '.jpg', 'blurred' => $i === 1,
            ];
        }

        return self::finish([
            'reference_code' => 'FR-2026-000041', 'title' => 'Convoi de nuit au pont nord, camion à double fond', 'note_kind' => 'FRM',
            'themes' => ['ARMEMENT', 'MOUV', 'PERSON', 'LOGIST'], 'urgency' => 'critique', 'intel_source' => 'HUMINT',
            'source_reliability' => 'B', 'info_credibility' => 2, 'classification' => 'confidentiel', 'status' => 'prise_en_compte',
            'observed_at' => '2026-11-12 21:40:00', 'created_at' => '2026-11-12 22:31:00', 'place_label' => 'Pont nord, route de la vallée de Nidasos',
            'grid_reference' => '24611 22840', 'lat' => 39.91234, 'lng' => 25.23456, 'pos_x' => 15540.0, 'pos_y' => 17788.0,
            'body' => implode("\n", $lines), 'author_label' => 'BRAVO-1', 'author_unit' => 'Section Bravo', 'attachments' => $attachments,
            'triage_note' => 'Observation demandée sur le dépôt jeudi ; fouille complète du camion au prochain passage.',
            'triaged_at' => '2026-11-14 08:20:00', 'triaged_by' => 7,
        ] + self::base());
    }

    /** @return list<array<string, mixed>> événements du suivi, du plus ancien au plus récent (forme d'IntelIntakeService::events) */
    private static function events(): array
    {
        $rows = [
            ['state', 'Analyste Sept', '', ['from' => 'a_traiter', 'to' => 'en_cours'], '2026-11-12 22:45:00'],
            ['assign', 'Analyste Sept', '', ['to' => 7], '2026-11-12 22:46:00'],
            ['comment', 'Analyste Sept', 'Le numéro du passeport correspond à un papier déjà contrôlé le 2 novembre.', [], '2026-11-12 23:10:00'],
            ['type', 'Sergent Membre', '', ['from' => 'FRO', 'to' => 'FRM'], '2026-11-13 07:30:00'],
            ['edit', 'Sergent Membre', '', ['fields' => ['place_label']], '2026-11-13 07:34:00'],
            ['blur', 'Analyste Sept', '', ['attachment_id' => 41], '2026-11-13 09:02:00'],
            ['state', 'Analyste Sept', 'Transmise à la cellule dossiers pour le réseau des passeurs.', ['from' => 'en_cours', 'to' => 'transmise', 'forwarded_to' => 'SSE-2026-0001'], '2026-11-13 18:15:00'],
            ['state', 'Analyste Sept', 'Observation demandée sur le dépôt jeudi ; fouille complète du camion au prochain passage.', ['from' => 'transmise', 'to' => 'en_cours'], '2026-11-14 08:20:00'],
        ];

        return array_map(static fn (array $r, int $i): array => ['id' => $i + 1, 'kind' => $r[0], 'actor' => $r[1], 'body' => $r[2], 'meta' => $r[3], 'at' => $r[4]], $rows, array_keys($rows));
    }
}
