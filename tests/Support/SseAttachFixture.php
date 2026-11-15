<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Bureau RÉALISTE pour la fenêtre « Rattacher » : par-dessus le graphe du widget « Liaisons » (SseLinksFixture), 40 dossiers,
 * 30 personnes, 20 identités, 15 documents, 10 pistes, 6 investigations, des sites, des objets du registre, des fiches de
 * renseignement et des dossiers d'intérêt, avec des classifications variées, un catalogue d'étiquettes et quelques
 * rattachements déjà posés. Trois lecteurs : lecteur à habilitation basse, opérateur, gestionnaire.
 */
final class SseAttachFixture
{
    private const CASE_TITLES = [
        'Réseau des passeurs du col nord', 'Vols de carburant au dépôt est', 'Filière de faux papiers', 'Cache d’armes de la vallée', 'Attentat du marché central',
        'Trafic de médicaments', 'Enlèvement du convoi humanitaire', 'Atelier d’explosifs artisanaux', 'Financement de la cellule Ouest', 'Infiltration du poste frontière',
        'Sabotage de la ligne électrique', 'Réseau de guetteurs du port', 'Contrebande de pièces détachées', 'Disparition de l’interprète', 'Tirs sur la patrouille Bravo',
        'Recrutement dans les camps', 'Blanchiment par le bureau de change', 'Écoute du relais radio', 'Menaces contre le maire', 'Entrepôt clandestin du quai 4',
    ];

    private const LAST = ['Benali', 'Okonkwo', 'Vasquez', 'Hadjadj', 'Morozov', 'Nasser', 'Delacroix', 'Tanaka', 'Mbeki', 'Rahimi', 'Castellan', 'Yilmaz', 'Petrovic', 'Haddad', 'Lindqvist'];

    private const FIRST = ['Samir', 'Chinedu', 'Lucía', 'Karim', 'Dmitri', 'Leïla', 'Étienne', 'Hiro', 'Thabo', 'Farid', 'Inès', 'Emre', 'Milan', 'Nour', 'Astrid'];

    /**
     * @return array<string, int>
     */
    public static function seed(PDO $pdo, bool $withAttach = true, bool $withLinks = true): array
    {
        $ids = SseLinksFixture::seed($pdo, $withLinks);
        $ins = static function (string $table, array $row) use ($pdo): int {
            $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

            return (int) $pdo->lastInsertId();
        };
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (21, 1, '76561190000000021', 'Lecteur Vingt-et-un'), (22, 1, '76561190000000022', 'Opératrice Lambert'), (23, 1, '76561190000000023', 'Chef de bureau Morel')");
        $pdo->exec("UPDATE sse_cases SET summary = 'Passages nocturnes organisés par le col, guides payés en liquide.', created_by = 7, updated_at = '2026-11-12 09:00:00' WHERE id = " . (int) $ids['case']);
        $levels = ['interne', 'encadrement', 'encadrement', 'confidentiel', 'interne', 'encadrement', 'tres_restreint', 'encadrement'];
        $statuses = ['ouvert', 'en_cours', 'ouvert', 'en_cours', 'clos', 'ouvert'];

        // 38 dossiers de plus (40 avec les deux du graphe) : titres avec accents, classifications et états variés.
        for ($i = 0; $i < 38; ++$i) {
            $title = self::CASE_TITLES[$i % 20] . ($i >= 20 ? ' (suite)' : '');
            $n = $i + 3;
            $ids['case' . $n] = $ins('sse_cases', [
                'tenant_id' => 1, 'reference_code' => 'SSE-2026-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'title' => $title, 'status' => $statuses[$i % 6],
                'classification' => $levels[$i % 8], 'summary' => 'Instruction en cours : ' . mb_strtolower($title) . ', recoupements attendus du terrain.',
                'created_by' => [7, 22, 23][$i % 3], 'created_at' => '2026-10-' . str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT) . ' 08:00:00',
                'updated_at' => '2026-11-' . str_pad((string) (1 + $i % 14), 2, '0', STR_PAD_LEFT) . ' 10:00:00',
            ]);
        }
        // 29 personnes de plus (30) et 18 identités de plus (20 avec les deux masquées du graphe, plus l'identité civile).
        for ($i = 0; $i < 29; ++$i) {
            $ids['person' . ($i + 2)] = $ins('sse_persons', [
                'tenant_id' => 1, 'last_name' => self::LAST[$i % 15], 'first_name' => self::FIRST[($i * 7) % 15], 'alias' => $i % 4 === 0 ? 'Le ' . ['Renard', 'Borgne', 'Docteur', 'Corbeau'][$i % 16 / 4] : null,
                'status' => $i % 5 === 0 ? 'suspect' : 'civil', 'affiliation' => $i % 3 === 0 ? 'Cellule Ouest' : null, 'classification' => $i % 9 === 4 ? 'confidentiel' : null,
                'submitter_user_id' => 22, 'created_at' => '2026-10-' . str_pad((string) (1 + $i % 28), 2, '0', STR_PAD_LEFT) . ' 11:00:00',
            ]);
        }
        for ($i = 0; $i < 18; ++$i) {
            $pid = $ids['person' . ($i + 2)];
            $ids['identity' . ($i + 2)] = $ins('sse_identities', [
                'tenant_id' => 1, 'person_id' => $pid, 'kind' => 'civil', 'last_name' => self::LAST[$i % 15], 'first_name' => self::FIRST[($i * 7) % 15],
                'created_by_user_id' => 22, 'created_at' => '2026-10-' . str_pad((string) (2 + $i), 2, '0', STR_PAD_LEFT) . ' 12:00:00',
            ]);
        }
        // 14 documents de plus (15).
        $docTitles = ['Synthèse hebdomadaire', 'Compte rendu d’audition', 'Note de situation', 'Procès-verbal de saisie', 'Fiche d’exploitation', 'Demande de recoupement', 'Rapport de filature'];
        for ($i = 0; $i < 14; ++$i) {
            $ids['document' . ($i + 2)] = $ins('sse_documents', [
                'tenant_id' => 1, 'reference_code' => 'DOC-2026-' . str_pad((string) ($i + 10), 4, '0', STR_PAD_LEFT), 'case_id' => $i % 3 === 0 ? $ids['case' . (3 + $i)] : null,
                'title' => $docTitles[$i % 7] . ' n° ' . ($i + 1), 'status' => $i % 2 === 0 ? 'valide' : 'brouillon', 'classification' => $levels[($i + 1) % 8],
                'created_by' => [7, 22][$i % 2], 'created_at' => '2026-11-' . str_pad((string) (1 + $i), 2, '0', STR_PAD_LEFT) . ' 09:00:00',
            ]);
        }
        // 6 investigations, avec des objets du registre (organisations, véhicules, matériels).
        $meshTitles = ['Toile de la filière nord', 'Cartographie de la cellule Ouest', 'Circuit de l’argent', 'Logistique du port', 'Contacts de l’interprète', 'Chaîne des faux papiers'];
        foreach ($meshTitles as $i => $title) {
            $ids['mesh' . ($i + 1)] = $ins('sse_meshes', [
                'tenant_id' => 1, 'reference_code' => 'INV-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'title' => $title, 'summary' => 'Qui connaît qui, et par quel intermédiaire.',
                'classification' => ['encadrement', 'confidentiel', 'encadrement', 'interne', 'tres_restreint', 'encadrement'][$i], 'status' => $i === 3 ? 'clos' : 'ouvert', 'created_by' => 7,
                'created_at' => '2026-10-1' . $i . ' 08:00:00', 'updated_at' => '2026-11-0' . ($i + 1) . ' 08:00:00',
            ]);
        }
        foreach ([['organization', 'Cellule Ouest', 'Groupe armé local', 1], ['organization', 'Transports Hadjadj', 'Société écran', 3], ['organization', 'Bureau de change Castellan', 'Blanchiment présumé', 3],
            ['vehicle', 'Fourgon blanc TK-4412', 'Vu au col et au port', 1], ['vehicle', 'Pick-up gris sans plaque', 'Patrouille Bravo', 4], ['weapon', 'Lot de 12 fusils', 'Saisie du quai 4', 4],
            ['phone', 'Téléphone à clapet noir', 'Saisi sur le guetteur', 2], ['terminal', 'Ordinateur portable', 'Atelier de faux papiers', 6]] as $n => [$kind, $label, $detail, $mesh]) {
            $ids['node' . ($n + 1)] = $ins('sse_mesh_nodes', ['mesh_id' => $ids['mesh' . $mesh], 'tenant_id' => 1, 'kind' => $kind, 'label' => $label, 'detail' => $detail, 'created_at' => '2026-10-20 10:00:00']);
        }
        foreach ([['Poste frontière du col nord', 'poste', 'ouvert'], ['Quai 4 du port', 'entrepot', 'ouvert'], ['Marché central', 'lieu public', 'clos'], ['Ferme isolée de la vallée', 'cache', 'ouvert']] as $n => [$name, $type, $status]) {
            $ids['site' . ($n + 2)] = $ins('sse_sites', ['tenant_id' => 1, 'name' => $name, 'site_type' => $type, 'status' => $status, 'summary' => 'Relevé par la patrouille.', 'created_at' => '2026-10-2' . $n . ' 07:00:00']);
        }
        foreach ([['Opération Aube claire', 'ouvert'], ['Opération Rideau de fer', 'clos']] as $n => [$name, $status]) {
            $ids['campaign' . ($n + 1)] = $ins('sse_campaigns', ['tenant_id' => 1, 'name' => $name, 'type' => 'operation', 'status' => $status, 'created_at' => '2026-09-0' . ($n + 1) . ' 08:00:00']);
        }
        foreach ([['INT-2026-0001', 'Homme au manteau gris', 'a_qualifier', 7], ['INT-2026-0002', 'Guetteuse du pont', 'en_collecte', 22], ['INT-2026-0003', 'Chauffeur du fourgon', 'en_analyse', 7]] as $n => [$ref, $designation, $status, $by]) {
            $ids['interest' . ($n + 1)] = $ins('sse_interest_cases', ['tenant_id' => 1, 'reference_code' => $ref, 'temporary_designation' => $designation, 'status' => $status, 'created_by' => $by, 'opening_reason' => 'Signalé par deux patrouilles.', 'created_at' => '2026-11-0' . ($n + 2) . ' 08:00:00']);
        }
        // Fiches de renseignement : une « Secret » (au-dessus du lecteur), une au même endroit qu'une fiche déjà classée.
        $pdo->exec("UPDATE sse_field_notes SET place_label = 'Pont nord', grid_reference = '045 112' WHERE id = " . (int) $ids['note']);
        $ids['note_secret'] = $ins('sse_field_notes', ['tenant_id' => 1, 'reference_code' => 'FR-2026-0011', 'title' => 'Source protégée au port', 'classification' => 'confidentiel', 'status' => 'transmise', 'author_user_id' => 7, 'created_at' => '2026-11-11 08:00:00']);
        $ids['note_free'] = $ins('sse_field_notes', ['tenant_id' => 1, 'reference_code' => 'FR-2026-0012', 'title' => 'Mouvement de nuit au pont', 'place_label' => 'Pont nord', 'grid_reference' => '045 112', 'classification' => 'interne', 'status' => 'transmise', 'author_user_id' => 22, 'created_at' => '2026-11-12 22:10:00']);
        $ids['note_low'] = $ins('sse_field_notes', ['tenant_id' => 1, 'reference_code' => 'FR-2026-0013', 'title' => 'Rumeur au marché', 'classification' => 'interne', 'status' => 'transmise', 'author_user_id' => 22, 'created_at' => '2026-11-13 10:00:00']);
        // Une autre communauté : jamais visible depuis la communauté 1.
        $ids['case_t2'] = $ins('sse_cases', ['tenant_id' => 2, 'reference_code' => 'SSE-2026-9001', 'title' => 'Dossier de l’autre communauté', 'status' => 'ouvert', 'classification' => 'interne', 'created_at' => '2026-11-01 08:00:00']);

        if ($withLinks) {
            // La fiche libre cite déjà la personne : Cerbère propose ses dossiers et son identité (liens à un saut).
            $ins('sse_entity_links', ['tenant_id' => 1, 'source_type' => 'note', 'source_id' => (string) $ids['note_free'], 'target_type' => 'person', 'target_id' => (string) $ids['person'], 'relation' => 'mentions', 'reason' => 'La fiche décrit son passage de nuit.', 'rule_code' => 'manual.link', 'created_by' => 'user', 'created_by_user_id' => 22, 'created_at' => '2026-11-12 22:30:00']);
        }
        if (!$withAttach) {
            return $ids;
        }
        // 10 pistes.
        $leadTitles = ['Le fourgon blanc passe-t-il par le col ?', 'Qui paie les guides ?', 'Origine des passeports vierges', 'Le relais radio est-il écouté ?', 'Lien entre le marché et la cellule Ouest',
            'Identité du « Docteur »', 'Dépôt de carburant : complicité interne ?', 'Où dort le guetteur ?', 'Filière des médicaments volés', 'Source dans la mairie'];
        foreach ($leadTitles as $i => $title) {
            $ids['lead' . ($i + 1)] = $ins('sse_leads', [
                'tenant_id' => 1, 'reference_code' => 'PST-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'title' => $title, 'description' => 'Hypothèse à vérifier par recoupement des fiches et des contrôles.',
                'status' => ['ouverte', 'suivie', 'ouverte', 'close', 'suivie'][$i % 5], 'classification' => ['encadrement', 'interne', 'confidentiel', 'encadrement', 'tres_restreint'][$i % 5],
                'owner_user_id' => [7, 22, 23][$i % 3], 'created_by_user_id' => 7, 'created_at' => '2026-11-0' . (1 + $i % 9) . ' 08:00:00',
            ]);
        }
        foreach ([['Prioritaire', 'red'], ['À recouper', 'amber'], ['Source fiable', 'green'], ['Terrain', 'blue'], ['Pièce à conviction', 'violet'], ['Vérifié', 'slate']] as $n => [$name, $color]) {
            $ids['tag' . ($n + 1)] = $ins('sse_attach_tags', ['tenant_id' => 1, 'name' => $name, 'slug' => \App\Support\SseAttachCatalog::tagSlug($name), 'color' => $color, 'created_by_user_id' => 7, 'created_at' => '2026-11-01 08:00:00']);
        }
        if ($withLinks) {
            // Rattachements déjà posés sur le premier dossier : une fiche, un document, un contrôle, et une pièce « Secret ».
            $att = static function (string $st, int $si, string $nature, string $label, string $reason, string $tags, int $by, string $at, int $align = 0) use ($ins, $ids, $pdo): int {
                $pair = $pdo->prepare("SELECT id FROM sse_entity_links WHERE tenant_id = 1 AND source_type = ? AND source_id = ? AND target_type = 'case' AND target_id = ?");
                $pair->execute([$st, (string) $si, (string) $ids['case']]);
                $link = (int) $pair->fetchColumn();
                if ($link < 1) {
                    $link = $ins('sse_entity_links', ['tenant_id' => 1, 'source_type' => $st, 'source_id' => (string) $si, 'target_type' => 'case', 'target_id' => (string) $ids['case'], 'relation' => $nature, 'reason' => $reason, 'rule_code' => 'manual.link', 'created_by' => 'user', 'created_by_user_id' => $by, 'created_at' => $at]);
                }

                return $ins('sse_link_attachments', ['tenant_id' => 1, 'link_id' => $link, 'source_type' => $st, 'source_id' => (string) $si, 'target_type' => 'case', 'target_id' => (string) $ids['case'], 'nature' => $nature,
                    'nature_label' => '', 'label' => $label, 'reason' => $reason, 'note' => '', 'tags' => $tags, 'align_requested' => $align, 'status' => 'active', 'created_by_user_id' => $by, 'created_at' => $at, 'removed_reason' => '']);
            };
            $ids['att_note'] = $att('note', $ids['note'], 'filed_in', 'Observation du pont (nuit du 9)', 'Recueilli dans le cadre de cette affaire', ',' . $ids['tag4'] . ',' . $ids['tag2'] . ',', 7, '2026-11-09 19:10:00', 1);
            $ids['att_doc'] = $att('document', $ids['document'], 'evidence_of', '', 'Pièce justificative du passage du 4 novembre', ',' . $ids['tag5'] . ',', 22, '2026-11-10 09:00:00');
            $ids['att_check'] = $att('check', $ids['check3'], 'concerns', 'Contrôle du pont nord', 'Concerne la même personne', '', 22, '2026-11-10 10:30:00');
            $ids['att_secret'] = $att('note', $ids['note_secret'], 'filed_in', 'Source du port', 'Recoupement à vérifier', ',' . $ids['tag1'] . ',', 7, '2026-11-11 08:30:00');
            $ids['att_site'] = $att('site', $ids['site'], 'concerns', '', 'Relevé au même endroit', ',' . $ids['tag4'] . ',', 7, '2026-11-11 15:00:00');
        }

        return $ids;
    }

    /** Lecteur à habilitation basse : lit les sections, n'écrit nulle part, habilitation CUI. */
    public static function reader(): ArraySseLinkViewer
    {
        return new ArraySseLinkViewer(1, 21, ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain', 'outils', 'analyse'], [], false, 'interne', [], null, []);
    }

    /** Opérateur : écrit dans les sections de travail, habilitation Confidentiel (C). */
    public static function operator(): ArraySseLinkViewer
    {
        return new ArraySseLinkViewer(1, 22, ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain', 'outils', 'analyse'], ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain'], false, 'encadrement', [], null, [1, 2, 3]);
    }

    /** Gestionnaire : écrit et gère, habilitation Très secret (TS). */
    public static function manager(): ArraySseLinkViewer
    {
        return ArraySseLinkViewer::full()->with(['user' => 23, 'manage' => ['dossiers', 'fichiers', 'terrain', 'papiers']]);
    }
}
