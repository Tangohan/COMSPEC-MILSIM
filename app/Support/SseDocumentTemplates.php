<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue versionné des modèles de documents du bureau SSE.
 *
 * Chaque modèle est du HTML riche (même liste blanche que {@see SseRichText}) composé comme les fiches du portail : en-tête
 * officiel (service, nature du document, bandeau de classification, référence, dossier, date, rédacteur), sections numérotées
 * guidées par un texte d'aide grisé à remplacer (span « sse-hint »), tableaux à lignes prévues, zones de signature et pied
 * de page. Les valeurs du dossier sont des champs dynamiques (span « sse-field ») : ils se mettent à jour à l'affichage
 * et dans le PDF. La classification en bandeau, haut et bas de chaque page, vient du gabarit du PDF et de la feuille.
 *
 * Le choix des modèles suit ce que le portail sait déjà produire : documents rédigés (flash, note, compte rendu, synthèse,
 * diffusion), avis et fiches de recherche, contrôles d'identité, signalements et incidents, dossiers d'intérêt.
 */
final class SseDocumentTemplates
{
    /** Code du modèle vide, toujours proposé. */
    public const BLANK = 'page_blanche';

    /**
     * code => [label, description, document_type, classification, group, icon]
     *
     * @var array<string, array{label:string, description:string, document_type:string, classification:string, group:string, icon:string}>
     */
    public const CATALOGUE = [
        'note_renseignement' => [
            'label' => 'Note de renseignement',
            'description' => 'Faits établis, source et fiabilité, analyse, personnes concernées et recommandations.',
            'document_type' => 'note_analyse', 'classification' => 'confidentiel', 'group' => 'analyse', 'icon' => 'M4 3h8v10H4zM6 6h4M6 8.5h4M6 11h2',
        ],
        'flash' => [
            'label' => 'Flash renseignement',
            'description' => 'Une alerte courte : qui, quoi, où, quand, et l’action immédiate recommandée.',
            'document_type' => 'flash', 'classification' => 'confidentiel', 'group' => 'analyse', 'icon' => 'M9 2L4 9h4l-1 5 5-7H8z',
        ],
        'sitrep' => [
            'label' => 'Note de synthèse (SITREP)',
            'description' => 'Point de situation sur une période : événements marquants, menaces, points d’attention et décisions attendues.',
            'document_type' => 'synthese', 'classification' => 'encadrement', 'group' => 'analyse', 'icon' => 'M3 13V8M7 13V4M11 13V6M2 13.5h12',
        ],
        'compte_rendu_entretien' => [
            'label' => 'Compte rendu d’entretien',
            'description' => 'Qui a été entendu, déroulé question par question, points saillants, contradictions et suites.',
            'document_type' => 'compte_rendu', 'classification' => 'confidentiel', 'group' => 'terrain', 'icon' => 'M3 4h10v6H8l-3 3v-3H3z',
        ],
        'rapport_incident' => [
            'label' => 'Rapport d’incident',
            'description' => 'Chronologie, personnes impliquées, matériel, mesures prises et recommandations.',
            'document_type' => 'compte_rendu', 'classification' => 'confidentiel', 'group' => 'terrain', 'icon' => 'M8 2l6 11H2zM8 6.5v3M8 11.2v.1',
        ],
        'fiche_controle_identite' => [
            'label' => 'Fiche de contrôle d’identité',
            'description' => 'Circonstances, pièces présentées, vérifications faites, conclusion et suite donnée.',
            'document_type' => 'compte_rendu', 'classification' => 'encadrement', 'group' => 'terrain', 'icon' => 'M2 4h12v8H2zM4.5 7.5h3M4.5 9.5h2M10 6.5a1.2 1.2 0 100 2.4 1.2 1.2 0 000-2.4z',
        ],
        'proces_verbal' => [
            'label' => 'Procès-verbal',
            'description' => 'Constat daté et signé : cadre, exposé des faits, constatations, pièces et clôture.',
            'document_type' => 'compte_rendu', 'classification' => 'confidentiel', 'group' => 'terrain', 'icon' => 'M4 2h6l3 3v9H4zM10 2v3h3M6 8h5M6 10.5h5',
        ],
        'fiche_recherche' => [
            'label' => 'Fiche de recherche / avis de recherche',
            'description' => 'Signalement d’une personne recherchée, motif, conduite à tenir, pièces connues et durée de validité.',
            'document_type' => 'diffusion', 'classification' => 'encadrement', 'group' => 'diffusion', 'icon' => 'M7 2.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9zM10.5 10.5L14 14',
        ],
        'courrier_officiel' => [
            'label' => 'Courrier officiel',
            'description' => 'Lettre à en-tête : expéditeur, destinataire, objet, références, corps et formule de politesse.',
            'document_type' => 'diffusion', 'classification' => 'interne', 'group' => 'diffusion', 'icon' => 'M2 4h12v8H2zM2 4.5l6 4.5 6-4.5',
        ],
        self::BLANK => [
            'label' => 'Page blanche',
            'description' => 'Seul l’en-tête officiel est posé : vous rédigez librement le reste.',
            'document_type' => 'note_analyse', 'classification' => 'confidentiel', 'group' => 'libre', 'icon' => 'M4 2h8v12H4z',
        ],
    ];

    /** @var array<string,string> */
    public const GROUPS = [
        'analyse' => 'Analyser et alerter',
        'terrain' => 'Consigner le terrain',
        'diffusion' => 'Diffuser',
        'libre' => 'Partir de zéro',
    ];

    public static function exists(string $code): bool
    {
        return isset(self::CATALOGUE[$code]);
    }

    /** @return array<string,array<string,string>> */
    public static function catalogue(): array
    {
        return self::CATALOGUE;
    }

    public static function label(string $code): string
    {
        return self::CATALOGUE[$code]['label'] ?? self::CATALOGUE[self::BLANK]['label'];
    }

    /**
     * Valeurs des champs dynamiques posées dans le modèle.
     *
     * @param array<string,mixed> $ctx nom, grade, date, dossier (référence), classification (libellé), reference (du document), titre
     * @return array{nom:string,grade:string,date:string,dossier:string,classification:string,reference:string,titre:string}
     */
    public static function context(array $ctx = []): array
    {
        $get = static fn (string $k, string $fallback): string => trim((string) ($ctx[$k] ?? '')) !== '' ? trim((string) $ctx[$k]) : $fallback;

        return [
            'nom' => $get('nom', 'Rédacteur'),
            'grade' => $get('grade', 'Grade'),
            'date' => $get('date', date('d/m/Y')),
            'dossier' => $get('dossier', 'Aucun dossier'),
            'classification' => $get('classification', 'Confidentiel'),
            'reference' => $get('reference', 'Attribuée à l’enregistrement'),
            'titre' => $get('titre', ''),
        ];
    }

    /** Corps HTML assaini d'un modèle du catalogue (page blanche si le code est inconnu). */
    public static function body(string $code, array $ctx = []): string
    {
        $code = self::exists($code) ? $code : self::BLANK;
        $c = self::context($ctx);
        $kind = mb_strtoupper(self::CATALOGUE[$code]['label'], 'UTF-8');
        $html = self::header($c, $kind) . self::blocks($code, $c) . self::footer();

        return SseRichText::sanitize($html);
    }

    /* ---------------------------------------------------------------- briques */

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function field(string $name, array $c): string
    {
        return '<span class="sse-field" data-field="' . $name . '">' . self::e((string) ($c[$name] ?? '')) . '</span>';
    }

    private static function hint(string $text): string
    {
        return '<span class="sse-hint">' . self::e($text) . '</span>';
    }

    private static function bold(string $text): string
    {
        return '<strong>' . self::e($text) . '</strong>';
    }

    /** En-tête officiel : service, nature du document, bandeau de classification, référence, dossier, date, rédacteur. */
    private static function header(array $c, string $kind): string
    {
        $shade = 'background-color:#e2e8f0;';

        return '<table><tbody>'
            . '<tr><th colspan="2" style="text-align:left;' . $shade . '">ATHENA · Bureau de renseignement (SSE)</th>'
            . '<th colspan="2" style="text-align:right;' . $shade . '">' . self::e($kind) . '</th></tr>'
            . '<tr><td colspan="4" style="text-align:center;color:#b91c1c;"><strong>CLASSIFICATION : ' . self::field('classification', $c) . '</strong></td></tr>'
            . '<tr><td style="background-color:#e2e8f0;">' . self::bold('Référence') . '</td><td>' . self::field('reference', $c) . '</td>'
            . '<td style="background-color:#e2e8f0;">' . self::bold('Dossier') . '</td><td>' . self::field('dossier', $c) . '</td></tr>'
            . '<tr><td style="background-color:#e2e8f0;">' . self::bold('Date') . '</td><td>' . self::field('date', $c) . '</td>'
            . '<td style="background-color:#e2e8f0;">' . self::bold('Rédacteur') . '</td><td>' . self::field('grade', $c) . ' ' . self::field('nom', $c) . '</td></tr>'
            . '</tbody></table>';
    }

    private static function footer(): string
    {
        return '<hr><p style="text-align:center;color:#475569;">Document à usage restreint : ne le diffusez pas hors du canal prévu par sa classification.</p>';
    }

    private static function title(string $text): string
    {
        return '<h2 style="text-align:center;">' . self::e($text) . '</h2>';
    }

    /** Section numérotée avec son texte d'aide. */
    private static function section(string $n, string $title, string $hint): string
    {
        return '<h3>' . self::e($n . '. ' . $title) . '</h3><p>' . self::hint($hint) . '</p>';
    }

    /** @param list<string> $items */
    private static function bullets(string $n, string $title, array $items): string
    {
        $li = '';
        foreach ($items as $it) {
            $li .= '<li>' . self::hint($it) . '</li>';
        }

        return '<h3>' . self::e($n . '. ' . $title) . '</h3><ul>' . $li . '</ul>';
    }

    /**
     * Tableau « libellé, valeur à saisir » sur deux colonnes de libellés et de valeurs.
     *
     * @param list<array{0:string,1:string}> $rows libellé, aide
     */
    private static function pairs(array $rows, ?string $caption = null): string
    {
        $out = $caption !== null ? '<h3>' . self::e($caption) . '</h3>' : '';
        $out .= '<table><tbody>';
        $n = count($rows);
        for ($i = 0; $i < $n; $i += 2) {
            $a = $rows[$i];
            $b = $rows[$i + 1] ?? null;
            $out .= '<tr><td style="background-color:#e2e8f0;">' . self::bold($a[0]) . '</td><td>' . self::hint($a[1]) . '</td>';
            $out .= $b !== null
                ? '<td style="background-color:#e2e8f0;">' . self::bold($b[0]) . '</td><td>' . self::hint($b[1]) . '</td>'
                : '<td></td><td></td>';
            $out .= '</tr>';
        }

        return $out . '</tbody></table>';
    }

    /**
     * Tableau à ligne d'en-tête grisée et lignes à remplir.
     *
     * @param list<string> $heads
     * @param list<string> $hintRow aide de la première ligne, une par colonne
     */
    private static function grid(string $caption, array $heads, array $hintRow, int $rows = 3): string
    {
        $out = '<h3>' . self::e($caption) . '</h3><table><tbody><tr>';
        foreach ($heads as $h) {
            $out .= '<th style="background-color:#e2e8f0;">' . self::e($h) . '</th>';
        }
        $out .= '</tr><tr>';
        foreach ($hintRow as $h) {
            $out .= '<td>' . self::hint($h) . '</td>';
        }
        $out .= '</tr>';
        for ($r = 1; $r < $rows; $r++) {
            $out .= '<tr>' . str_repeat('<td><br></td>', count($heads)) . '</tr>';
        }

        return $out . '</tbody></table>';
    }

    /**
     * Zones de signature : une colonne par signataire, espace réservé sous le libellé.
     *
     * @param list<string> $who
     */
    private static function signatures(array $who, array $c): string
    {
        $out = '<h3>Signatures</h3><table><tbody><tr>';
        foreach ($who as $w) {
            $out .= '<th style="background-color:#e2e8f0;">' . self::e($w) . '</th>';
        }
        $out .= '</tr><tr>';
        foreach ($who as $i => $w) {
            $out .= '<td>' . ($i === 0 ? '<p>Fait le ' . self::field('date', $c) . '</p>' : '<p>Date :</p>') . '<p>Nom, grade et signature :</p><p><br></p><p><br></p><p><br></p></td>';
        }

        return $out . '</tr></tbody></table>';
    }

    /* ---------------------------------------------------------------- contenus */

    private static function blocks(string $code, array $c): string
    {
        switch ($code) {
            case 'note_renseignement':
                return self::title('Note de renseignement')
                    . self::pairs([['Objet', 'Sujet de la note en une ligne'], ['Destinataires', 'Qui doit la lire']])
                    . self::section('1', 'Synthèse', 'Trois lignes au plus : ce qu’il faut retenir et ce que vous recommandez.')
                    . self::bullets('2', 'Faits établis', ['Fait vérifié, avec sa date et son lieu.', 'Fait vérifié, avec sa date et son lieu.', 'Fait vérifié, avec sa date et son lieu.'])
                    . self::pairs([
                        ['Source', 'Nature et origine de l’information'], ['Fiabilité', 'A à F : justifiez en une ligne'],
                        ['Crédibilité', '1 à 6 : justifiez en une ligne'], ['Recoupement', 'Source unique ou recoupée'],
                    ], '3. Source et fiabilité')
                    . self::section('4', 'Analyse et hypothèses', 'Séparez ce qui est établi de ce qui est supposé, et indiquez votre niveau de confiance.')
                    . self::grid('5. Personnes et entités concernées', ['Nom ou identifiant', 'Rôle', 'Niveau de confiance', 'Observations'], ['Nom ou pseudonyme', 'Rôle dans les faits', 'Élevé, moyen ou faible', 'À vérifier'])
                    . self::section('6', 'Recommandations', 'Actions proposées, par ordre de priorité, avec le service concerné.')
                    . self::signatures(['Rédacteur', 'Validation du chef de bureau'], $c);

            case 'flash':
                return self::title('Flash renseignement')
                    . self::pairs([['Priorité', 'Immédiat, prioritaire ou routine'], ['Heure (Zulu)', 'JJ/MM/AAAA HH:MM Z']])
                    . self::section('1', 'Situation en une phrase', 'Qui, quoi, où, quand.')
                    . self::pairs([['Secteur', 'Zone, site ou ville'], ['Repère', 'Grille ou coordonnées']], '2. Lieu')
                    . self::bullets('3', 'Faits essentiels', ['Premier fait.', 'Deuxième fait.', 'Troisième fait.'])
                    . self::pairs([['Source', 'Observation, saisie, témoignage…'], ['Fiabilité', 'A à F']], '4. Source')
                    . self::section('5', 'Action immédiate recommandée', 'Ce qui doit être fait dans l’heure, et par qui.')
                    . self::signatures(['Rédacteur'], $c);

            case 'sitrep':
                return self::title('Note de synthèse (SITREP)')
                    . self::pairs([['Période couverte', 'Du … au …'], ['Numéro', 'SITREP n°']])
                    . self::section('1', 'Situation générale', 'Une vue d’ensemble en cinq lignes au plus.')
                    . self::grid('2. Événements marquants', ['Date et heure', 'Lieu', 'Événement', 'Évaluation'], ['JJ/MM HH:MM', 'Secteur', 'Ce qui s’est passé', 'Impact et suite'], 4)
                    . self::bullets('3', 'Menaces et indicateurs', ['Menace identifiée et son niveau.', 'Indicateur à surveiller.'])
                    . self::section('4', 'Activité du bureau', 'Pistes ouvertes, dossiers clos, ressources mobilisées.')
                    . self::section('5', 'Points d’attention (24 à 72 heures)', 'Ce qui peut changer à court terme.')
                    . self::section('6', 'Décisions attendues', 'Décision demandée, à qui, avant quelle échéance.')
                    . self::signatures(['Rédacteur', 'Validation'], $c);

            case 'compte_rendu_entretien':
                return self::title('Compte rendu d’entretien')
                    . self::pairs([
                        ['Personne entendue', 'Nom, prénom, qualité'], ['Date et heure', 'Début et fin'],
                        ['Lieu', 'Salle ou site'], ['Conduit par', 'Nom et grade'],
                        ['Présents', 'Autres personnes'], ['Cadre', 'Dossier ou motif de l’entretien'],
                    ], '1. Cadre de l’entretien')
                    . self::section('2', 'Objet', 'Ce que l’entretien devait établir.')
                    . self::grid('3. Déroulé', ['Heure', 'Question', 'Réponse'], ['HH:MM', 'Question posée', 'Réponse, avec les mots employés'], 4)
                    . self::bullets('4', 'Points saillants', ['Information nouvelle ou confirmée.', 'Information nouvelle ou confirmée.'])
                    . self::section('5', 'Contradictions et vérifications', 'Ce qui ne concorde pas avec les autres sources, et ce qu’il faut vérifier.')
                    . self::section('6', 'Suites à donner', 'Nouvel entretien, recoupement, transmission à un autre service.')
                    . self::signatures(['Conduit par', 'Personne entendue (lu et approuvé)'], $c);

            case 'rapport_incident':
                return self::title('Rapport d’incident')
                    . self::pairs([
                        ['Nature', 'Type d’incident'], ['Gravité', 'Mineure, notable ou majeure'],
                        ['Date et heure', 'Constat de l’incident'], ['Lieu', 'Secteur ou grille'],
                        ['Signalé par', 'Nom ou service'], ['Mesure d’urgence', 'Oui ou non'],
                    ], '1. Identification')
                    . self::section('2', 'Résumé', 'Ce qui s’est passé, en quelques lignes.')
                    . self::grid('3. Chronologie', ['Heure', 'Événement', 'Source'], ['HH:MM', 'Fait constaté', 'Témoin ou relevé'], 4)
                    . self::grid('4. Personnes impliquées', ['Nom', 'Rôle', 'Observations'], ['Nom ou identifiant', 'Auteur, témoin, victime…', 'À vérifier'])
                    . self::section('5', 'Matériel et pièces', 'Éléments saisis ou concernés, avec leur état.')
                    . self::section('6', 'Mesures prises', 'Ce qui a été fait, par qui et à quelle heure.')
                    . self::section('7', 'Analyse et recommandations', 'Cause probable et actions pour éviter que cela se reproduise.')
                    . self::signatures(['Rédacteur', 'Validation'], $c);

            case 'fiche_controle_identite':
                return self::title('Fiche de contrôle d’identité')
                    . self::pairs([
                        ['Date et heure', 'Début du contrôle'], ['Lieu', 'Point de contrôle'],
                        ['Agent', 'Nom et grade'], ['Motif', 'Pourquoi ce contrôle'],
                    ], '1. Circonstances')
                    . self::pairs([
                        ['Nom', 'Tel que déclaré'], ['Prénom', 'Tel que déclaré'],
                        ['Naissance', 'Date et lieu'], ['Nationalité', 'Pays'],
                    ], '2. Identité déclarée')
                    . self::grid('3. Pièces présentées', ['Type de pièce', 'Numéro', 'Émetteur', 'Constat'], ['Passeport, carte…', 'Numéro lu', 'Pays ou service', 'Conforme ou douteuse'])
                    . self::bullets('4', 'Vérifications effectuées', ['Photo comparée au porteur.', 'Zone de lecture automatique contrôlée.', 'Signes de falsification recherchés.', 'Recherche dans les fichiers du bureau.'])
                    . self::pairs([['Conclusion', 'Conforme, à vérifier ou suspect'], ['Niveau de confiance', 'Élevé, moyen ou faible']], '5. Conclusion')
                    . self::section('6', 'Suite donnée', 'Libération, retenue, transmission, fiche de personne à ouvrir.')
                    . self::signatures(['Agent', 'Personne contrôlée (facultatif)'], $c);

            case 'proces_verbal':
                return self::title('Procès-verbal')
                    . self::pairs([
                        ['Nature', 'Constat, saisie, audition…'], ['Date et heure', 'Début et fin'],
                        ['Lieu', 'Adresse ou secteur'], ['Dressé par', 'Nom et grade'],
                    ], '1. Cadre')
                    . self::section('2', 'Personnes présentes', 'Nom, qualité et rôle de chacune.')
                    . self::section('3', 'Exposé des faits', 'Ce qui a été constaté, dans l’ordre, sans interprétation.')
                    . self::bullets('4', 'Constatations', ['Constatation matérielle.', 'Constatation matérielle.'])
                    . self::grid('5. Pièces et objets', ['Repère', 'Désignation', 'État', 'Destination'], ['Numéro de scellé', 'Objet ou document', 'Intact ou dégradé', 'Conservé ou transmis'])
                    . self::section('6', 'Déclarations recueillies', 'Propos rapportés, entre guillemets.')
                    . '<p>De tout ce qui précède, il a été dressé le présent procès-verbal, lu et approuvé par les personnes soussignées.</p>'
                    . self::signatures(['Dressé par', 'Témoin', 'Autorité'], $c);

            case 'fiche_recherche':
                return self::title('Fiche de recherche')
                    . self::pairs([
                        ['Nom', 'Nom de la personne'], ['Prénom', 'Prénom'],
                        ['Alias connus', 'Pseudonymes'], ['Naissance', 'Date et lieu'],
                        ['Dernier lieu connu', 'Où et quand'], ['Statut', 'Recherchée, à localiser, à entendre'],
                    ], '1. Identité')
                    . self::section('2', 'Signalement', 'Taille, corpulence, signes distinctifs, tenue, véhicule.')
                    . self::section('3', 'Motif de la recherche', 'Pourquoi la personne est recherchée, en termes factuels.')
                    . self::bullets('4', 'Conduite à tenir', ['Ne pas intervenir seul.', 'Signaler immédiatement au service demandeur.', 'Noter l’heure, le lieu et la direction.'])
                    . self::grid('5. Pièces et identifiants connus', ['Type', 'Numéro', 'Émetteur', 'Observation'], ['Pièce ou identifiant', 'Numéro', 'Pays ou service', 'Valide ou douteuse'])
                    . self::pairs([['Service demandeur', 'Nom du service'], ['Contact', 'Canal de signalement'], ['Émise le', 'Date'], ['Valable jusqu’au', 'Date de fin ou renouvellement']], '6. Émission et validité')
                    . self::signatures(['Service demandeur', 'Validation'], $c);

            case 'courrier_officiel':
                return '<table><tbody><tr>'
                    . '<td>' . self::bold('Expéditeur') . '<p>' . self::hint('Service, adresse et contact') . '</p></td>'
                    . '<td>' . self::bold('Destinataire') . '<p>' . self::hint('Nom, qualité, service') . '</p></td></tr></tbody></table>'
                    . '<p style="text-align:right;">' . self::hint('Lieu') . ', le ' . self::field('date', $c) . '</p>'
                    . '<p>' . self::bold('Objet : ') . self::hint('Résumé du courrier en une ligne') . '</p>'
                    . '<p>' . self::bold('Références : ') . self::field('reference', $c) . ' · dossier ' . self::field('dossier', $c) . '</p>'
                    . '<p>' . self::bold('Pièces jointes : ') . self::hint('Liste, ou aucune') . '</p>'
                    . '<p>Madame, Monsieur,</p>'
                    . '<p>' . self::hint('Premier paragraphe : le motif du courrier.') . '</p>'
                    . '<p>' . self::hint('Développement : les éléments utiles, les faits, les demandes.') . '</p>'
                    . '<p>' . self::hint('Conclusion : ce que vous attendez et avant quelle date.') . '</p>'
                    . '<p>Je vous prie d’agréer, Madame, Monsieur, l’expression de ma considération distinguée.</p>'
                    . '<p style="text-align:right;">' . self::field('grade', $c) . ' ' . self::field('nom', $c) . '</p>'
                    . '<p style="text-align:right;"><br></p><p style="text-align:right;"><br></p>';

            default:
                return '<p>' . self::hint('Rédigez ici. Le texte grisé disparaît dès que vous écrivez dessus.') . '</p>';
        }
    }
}
