<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseArmaModelRepository;

/**
 * Atelier modèles SSE pour missions Arma (@COMSPEC_SSE).
 * Produit un payload compatible comspec_sse_fnc_createModel / importModel.
 */
final class SseArmaModelService
{
    private SseArmaModelRepository $repo;

    public function __construct(?SseArmaModelRepository $repo = null)
    {
        $this->repo = $repo ?? new SseArmaModelRepository();
    }

    public function repository(): SseArmaModelRepository
    {
        return $this->repo;
    }

    /**
     * @param array<string,mixed> $input Formulaire web
     * @return array{ok:bool,errors:list<string>,data?:array<string,mixed>}
     */
    public function normalizeFromForm(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors[] = 'Indiquez un nom de modèle (160 caractères max).';
        }

        $profile = (string) ($input['profile_code'] ?? 'INSURGENT');
        if (!isset(SseArmaModelRepository::PROFILE_LABELS[$profile])) {
            $errors[] = 'Choisissez un profil valide.';
        }
        $complexity = (string) ($input['complexity_code'] ?? 'DETAILED');
        if (!isset(SseArmaModelRepository::COMPLEXITY_LABELS[$complexity])) {
            $errors[] = 'Choisissez une richesse de détail valide.';
        }
        $region = (string) ($input['region_code'] ?? 'IRAQ');
        if (!isset(SseArmaModelRepository::REGION_LABELS[$region])) {
            $errors[] = 'Choisissez une région valide.';
        }
        $theme = (string) ($input['theme_code'] ?? 'weapons_cache');
        if (!isset(SseArmaModelRepository::THEME_LABELS[$theme])) {
            $errors[] = 'Choisissez un thème narratif valide.';
        }

        $status = (string) ($input['status'] ?? 'draft');
        if (!isset(SseArmaModelRepository::STATUS_LABELS[$status])) {
            $status = 'draft';
        }

        $networkSize = (int) ($input['network_size'] ?? 8);
        if ($networkSize < 0 || $networkSize > 40) {
            $errors[] = 'La taille du réseau doit être entre 0 et 40.';
        }

        $noise = $this->parseOptionalFloat($input['noise_probability'] ?? null);
        $falseLead = $this->parseOptionalFloat($input['false_lead_probability'] ?? null);
        if ($noise !== null && ($noise < 0 || $noise > 1)) {
            $errors[] = 'Choisissez un niveau de bruit dans la liste proposée.';
        }
        if ($falseLead !== null && ($falseLead < 0 || $falseLead > 1)) {
            $errors[] = 'Choisissez un niveau de fausses pistes dans la liste proposée.';
        }

        $aliasPool = $this->linesToList((string) ($input['alias_pool_text'] ?? ''));
        $contactPool = $this->linesToList((string) ($input['contact_pool_text'] ?? ''));
        $smsTemplates = $this->linesToList((string) ($input['sms_templates_text'] ?? ''));
        $docTemplates = $this->linesToList((string) ($input['document_templates_text'] ?? ''));
        $codewords = $this->linesToList((string) ($input['codewords_text'] ?? ''));
        $tags = $this->linesToList((string) ($input['tags_text'] ?? ''));

        $forced = [];
        $forcedName = trim((string) ($input['forced_name'] ?? ''));
        $forcedAlias = trim((string) ($input['forced_alias'] ?? ''));
        $forcedNationality = trim((string) ($input['forced_nationality'] ?? ''));
        if ($forcedName !== '') {
            $forced['name'] = $forcedName;
        }
        if ($forcedAlias !== '') {
            $forced['alias'] = $forcedAlias;
        }
        if ($forcedNationality !== '') {
            $forced['nationality'] = $forcedNationality;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $payload = [
            'aliasPool' => $aliasPool,
            'contactPool' => $contactPool,
            'smsTemplates' => $smsTemplates,
            'documentTemplates' => $docTemplates,
            'codewords' => $codewords,
            'forcedIdentity' => $forced,
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];

        $publicId = trim((string) ($input['public_id'] ?? ''));
        if ($publicId === '') {
            $publicId = $this->makePublicId($name);
        } else {
            $publicId = $this->sanitizePublicId($publicId);
            if ($publicId === '') {
                $errors[] = 'L’identifiant technique du modèle est invalide.';

                return ['ok' => false, 'errors' => $errors];
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'data' => [
                'public_id' => $publicId,
                'name' => $name,
                'author_label' => trim((string) ($input['author_label'] ?? '')) ?: null,
                'status' => $status,
                'profile_code' => $profile,
                'complexity_code' => $complexity,
                'region_code' => $region,
                'theme_code' => $theme,
                'include_biometrics' => !empty($input['include_biometrics']),
                'include_phone' => !empty($input['include_phone']),
                'include_documents' => !empty($input['include_documents']),
                'include_computer' => !empty($input['include_computer']),
                'network_size' => $networkSize,
                'noise_probability' => $noise,
                'false_lead_probability' => $falseLead,
                'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE) ?: '[]',
                'payload' => $payload,
                'tags' => $tags,
            ],
        ];
    }

    /**
     * Format attendu par comspec_sse_fnc_importModel / createModel.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function toArmaModel(array $row): array
    {
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
        $forced = is_array($payload['forcedIdentity'] ?? null) ? $payload['forcedIdentity'] : [];

        $model = [
            'id' => (string) ($row['public_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'author' => (string) ($row['author_label'] ?? 'COMSPEC Web'),
            'source' => 'WEB',
            'version' => (int) ($row['version'] ?? 1),
            'profile' => (string) ($row['profile_code'] ?? 'INSURGENT'),
            'complexity' => (string) ($row['complexity_code'] ?? 'DETAILED'),
            'region' => (string) ($row['region_code'] ?? 'IRAQ'),
            'theme' => (string) ($row['theme_code'] ?? 'weapons_cache'),
            'includeBiometrics' => !empty($row['include_biometrics']),
            'includePhone' => !empty($row['include_phone']),
            'includeDocuments' => !empty($row['include_documents']),
            'includeComputer' => !empty($row['include_computer']),
            'networkSize' => (int) ($row['network_size'] ?? 8),
            'tags' => is_array($row['tags'] ?? null) ? $row['tags'] : [],
            'notes' => (string) ($row['notes'] ?? ($payload['notes'] ?? '')),
        ];

        if (isset($row['noise_probability']) && $row['noise_probability'] !== null && $row['noise_probability'] !== '') {
            $model['noiseProbability'] = (float) $row['noise_probability'];
        }
        if (isset($row['false_lead_probability']) && $row['false_lead_probability'] !== null && $row['false_lead_probability'] !== '') {
            $model['falseLeadProbability'] = (float) $row['false_lead_probability'];
        }

        foreach (['aliasPool', 'contactPool', 'smsTemplates', 'documentTemplates', 'codewords'] as $key) {
            if (!empty($payload[$key]) && is_array($payload[$key])) {
                $model[$key] = array_values($payload[$key]);
            }
        }
        if ($forced !== []) {
            $model['forcedIdentity'] = $forced;
        }

        return $model;
    }

    /**
     * Snippet SQF pour coller en init mission / Zeus (createModel + apply).
     *
     * @param array<string,mixed> $row
     */
    public function toSqfImportBlock(array $row): string
    {
        $arma = $this->toArmaModel($row);
        $name = (string) ($arma['name'] ?? 'Modele SSE');
        $author = (string) ($arma['author'] ?? 'COMSPEC Web');
        $pairs = $this->armaModelToSqfPairs($arma);
        $nameEsc = str_replace('"', '""', $name);
        $authorEsc = str_replace('"', '""', $author);

        return "// Modèle SSE — {$nameEsc} (atelier web)\n"
            . "private _model = [\"{$nameEsc}\", createHashMapFromArray [\n"
            . $pairs
            . "], \"{$authorEsc}\"] call comspec_sse_fnc_createModel;\n"
            . "[_model, true] call comspec_sse_fnc_saveModel;\n"
            . "// Appliquer sur une unité :\n"
            . "// [_unit, _model get \"id\"] call comspec_sse_fnc_applyModel;\n";
    }

    /**
     * Export sérialisé (paires) compatible comspec_sse_fnc_importModel.
     *
     * @param array<string,mixed> $row
     * @return list<array{0:string,1:mixed}>
     */
    public function toArmaSerializedPairs(array $row): array
    {
        return $this->valueToPairs($this->toArmaModel($row));
    }

    /**
     * @param array<string,mixed> $arma
     */
    private function armaModelToSqfPairs(array $arma): string
    {
        $skip = ['id', 'name', 'author', 'source', 'version'];
        $lines = [];
        foreach ($arma as $key => $value) {
            if (in_array((string) $key, $skip, true)) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if ($key === 'forcedIdentity' && is_array($value) && $value === []) {
                continue;
            }
            $lines[] = '    ["' . $this->sqfEscapeKey((string) $key) . '", ' . $this->toSqfLiteral($value) . ']';
        }

        return $lines === [] ? '' : implode(",\n", $lines) . "\n";
    }

    private function sqfEscapeKey(string $key): string
    {
        return str_replace('"', '""', $key);
    }

    private function toSqfLiteral(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            if (is_float($value) && floor($value) == $value) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.') ?: '0';
        }
        if (is_string($value)) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            // Associative (forcedIdentity) -> createHashMapFromArray
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = '["' . $this->sqfEscapeKey((string) $k) . '", ' . $this->toSqfLiteral($v) . ']';
                }

                return 'createHashMapFromArray [' . implode(', ', $parts) . ']';
            }
            $parts = array_map(fn ($v) => $this->toSqfLiteral($v), $value);

            return '[' . implode(', ', $parts) . ']';
        }

        return 'nil';
    }

    /**
     * @return list<array{0:string,1:mixed}>|list<mixed>|mixed
     */
    private function valueToPairs(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $isAssoc = $value !== [] && array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[] = [(string) $k, $this->valueToPairs($v)];
            }

            return $out;
        }

        return array_map(fn ($v) => $this->valueToPairs($v), $value);
    }

    /**
     * @return list<array{key:string,label:string,description:string,group?:string,defaults:array<string,mixed>}>
     */
    public function builtinTemplates(): array
    {
        return [
            // —— Irak 2010–2020 ——
            [
                'key' => 'iq_2010_2020_cellule_armes',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Cellule cache d’armes',
                'description' => 'Cellule insurgée (Anbar / Ninewa), cache, téléphone dual-SIM, documents manuscrits.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Cache d’armes',
                    'status' => 'published',
                    'profile_code' => 'INSURGENT',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'weapons_cache',
                    'include_biometrics' => '1',
                    'include_phone' => '1',
                    'include_documents' => '1',
                    'include_computer' => '0',
                    'network_size' => '9',
                    'noise_probability' => '0.18',
                    'false_lead_probability' => '0.22',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "Abu Yassin\nAbu Hamza\nAl-Saqr\nLe Magasinier\nFrère 7",
                    'contact_pool_text' => "THE DRIVER\nWAREHOUSE\nABU MARIAM\nRELAY-ANBAR\nSHADOW",
                    'sms_templates_text' => "Les caisses sont au hangar OUEST.\nNe déplacez rien avant la prière du soir.\nCheckpoint renforcé — passez par le canal.\nConfirmez le comptage des chargeurs.",
                    'document_templates_text' => "Inventaire armes — secteur Nord (brouillon)\nPlan manuscrit dépôt + grille\nListe de contacts (prénoms seulement)\nReçu carburant pickup blanc",
                    'codewords_text' => "SABLE\nORAGE\nLUNE\nPUITS",
                    'tags_text' => "irak\n2010-2020\ncache\narmes",
                    'notes' => 'Modèle type Irak 2010–2020 : cellule armement, faible digital lourd, beaucoup de papier et SMS.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_ied',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Cellule IED',
                'description' => 'Technicien engins, atelier, codes radio et PC récupéré.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Cellule IED',
                    'status' => 'published',
                    'profile_code' => 'TECHNICIAN',
                    'complexity_code' => 'HIGH_VALUE',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'ied_cell',
                    'include_computer' => '1',
                    'network_size' => '7',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "L’Ingénieur\nAbu Fil\nÉclair\nLe Chimiste",
                    'sms_templates_text' => "Fils prêts — manque détonateur.\nNe touchez pas au véhicule jaune.\nTest ce soir près du wadi.\nPhotos du circuit dans le téléphone secondaire.",
                    'document_templates_text' => "Schéma de câblage (crayon)\nListe composants marché\nHoraires de pose — secteur EST\nNotes sur délais de retard",
                    'codewords_text' => "ÉCLAIR\nFIL\nCHARGE\nWADI",
                    'tags_text' => "irak\n2010-2020\nied\ntechnicien",
                    'notes' => 'Atelier IED type période 2010–2020 : preuves techniques + messagerie courte.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_hvt',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Chef / émir de secteur',
                'description' => 'HVT commandement, réseau dense, biométrie et digital riches.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Chef de secteur',
                    'status' => 'published',
                    'profile_code' => 'COMMANDER',
                    'complexity_code' => 'HIGH_VALUE',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'meeting_alpha',
                    'include_computer' => '1',
                    'network_size' => '14',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "Abu Karim\nAl-Rashid\nLe Contremaître\nÉmir Nord",
                    'sms_templates_text' => "Réunion point ALPHA confirmée.\nChangez le lieu — trop d’yeux.\nNe contactez personne avant demain.\nLes frères de l’Ouest arrivent jeudi.",
                    'document_templates_text' => "Ordre de mission — diffusion limitée\nListe des chefs d’équipe\nCarte annotée points de rendez-vous\nNotes sur les indics soupçonnés",
                    'codewords_text' => "ALPHA\nORAGE\nCROISSANT\nNID",
                    'tags_text' => "irak\n2010-2020\nhvt\ncommandement",
                    'notes' => 'Cible de haute valeur — réunion, réseau, biométrie.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_courrier',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Courrier / passeur',
                'description' => 'Itinéraires, peu de contacts, fausses pistes volontaires.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Courrier frontière',
                    'status' => 'published',
                    'profile_code' => 'COURIER',
                    'complexity_code' => 'STANDARD',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'courier_run',
                    'network_size' => '5',
                    'noise_probability' => '0.25',
                    'false_lead_probability' => '0.35',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "Le Chauffeur\nColis\nRelais-2\nSandman",
                    'sms_templates_text' => "Colis prêt au marché.\nChangement d’itinéraire — pont EST fermé.\nConfirmez réception avant 22h.\nNe garez pas le pickup devant la mosquée.",
                    'document_templates_text' => "Itinéraire manuscrit (ratures)\nTicket carburant\nListe de points de dépôt",
                    'codewords_text' => "COLIS\nPONT\nCANAL",
                    'tags_text' => "irak\n2010-2020\ncourrier",
                    'notes' => 'Courrier tactique avec leurres d’itinéraire.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_financier',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Relais financier',
                'description' => 'Hawala / drops, listes de montants, reçus à détruire.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Relais financier',
                    'status' => 'published',
                    'profile_code' => 'FINANCIER',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'finance_drop',
                    'include_documents' => '1',
                    'include_computer' => '1',
                    'network_size' => '8',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "Le Changeur\nAbu Caisse\nTHE ACCOUNTANT",
                    'sms_templates_text' => "Fonds disponibles après-midi.\nUtiliser le compte secondaire.\nFactures à brûler après lecture.\nLe drop SUD est reporté.",
                    'document_templates_text' => "Liste de montants — livraison SUD\nReçu cachet — ne pas scanner\nNoms des intermédiaires (brouillon)\nCarnet de dettes partielles",
                    'codewords_text' => "CAISSE\nBALANCE\nSUD",
                    'tags_text' => "irak\n2010-2020\nfinance",
                    'notes' => 'Nœud financier type période conflictuelle irakienne.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_safehouse',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Planque urbaine',
                'description' => 'Safehouse, documents, objets du quotidien + matériel mixte.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Planque urbaine',
                    'status' => 'published',
                    'profile_code' => 'INSURGENT',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'safehouse',
                    'include_documents' => '1',
                    'network_size' => '6',
                    'forced_nationality' => 'Irakienne',
                    'alias_pool_text' => "Nid\nMaison bleue\nLe Locataire",
                    'sms_templates_text' => "La clé est sous le pot.\nNe laissez pas de lumières après 21h.\nVisiteurs demain — nettoyez la pièce du fond.",
                    'document_templates_text' => "Bail / quittance manuscrite\nListe courses + munitions mélangées\nPlan de la maison annoté",
                    'codewords_text' => "NID\nCLEF\nCOUR",
                    'tags_text' => "irak\n2010-2020\nplanque",
                    'notes' => 'Site d’habitation utilisé comme planque.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'iq_2010_2020_civil',
                'group' => 'Irak 2010–2020',
                'label' => 'Irak — Civil (bruit)',
                'description' => 'Couverture civile, fort bruit, peu d’intérêt opérationnel.',
                'defaults' => [
                    'name' => 'Irak 2010-2020 — Civil couverture',
                    'status' => 'published',
                    'profile_code' => 'CIVILIAN',
                    'complexity_code' => 'LIGHT',
                    'region_code' => 'IRAQ',
                    'theme_code' => 'RANDOM',
                    'noise_probability' => '0.55',
                    'false_lead_probability' => '0.15',
                    'include_computer' => '0',
                    'include_biometrics' => '0',
                    'network_size' => '6',
                    'forced_nationality' => 'Irakienne',
                    'tags_text' => "irak\n2010-2020\nbruit\ncivil",
                    'notes' => 'Bruit de fond pour noyer l’exploitation.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],

            // —— Russie / Est 2020–2024 ——
            [
                'key' => 'ru_2020_2024_recon',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Cellule reconnaissance',
                'description' => 'Observateurs, grilles, messages courts, discipline OPSEC.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Reconnaissance',
                    'status' => 'published',
                    'profile_code' => 'INTELLIGENCE',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'meeting_alpha',
                    'include_phone' => '1',
                    'include_documents' => '1',
                    'include_computer' => '1',
                    'network_size' => '8',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Sokol\nBerkut\nNavigator\nTigr-2\nVolga",
                    'contact_pool_text' => "BASE-NORTH\nRELAY-K\nDRIVER-7\nANALYST-M\nLOG-12",
                    'sms_templates_text' => "Point d’observation tenu jusqu’à 04h.\nChangement de grille — utiliser la carte B.\nNe répondez pas aux numéros inconnus.\nPhoto du carrefour envoyée sur le canal secondaire.",
                    'document_templates_text' => "Carte annotée axes logistiques\nListe d’horaires de rotation\nNotes d’observation (météo / trafic)\nOrdre de mission tronqué",
                    'codewords_text' => "SOKOL\nZARYA\nMOST\nTUMAN",
                    'tags_text' => "russie\n2020-2024\nrecon\nintel",
                    'notes' => 'Cellule ISR / observation type théâtre Est 2020–2024.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_logistics',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Nœud logistique',
                'description' => 'Carburant, munitions, convois, documents d’entrepôt.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Logistique',
                    'status' => 'published',
                    'profile_code' => 'LOGISTICS',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'fuel_delivery',
                    'include_documents' => '1',
                    'include_phone' => '1',
                    'network_size' => '10',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Sklad\nCiterne\nKonvoi\nMekhanik",
                    'sms_templates_text' => "Citerne prête — départ 02h.\nChanger le point de ravitaillement.\nCompter les caisses avant chargement.\nRoute Ouest saturée — passer par le détour.",
                    'document_templates_text' => "Bordereau carburant\nInventaire caisses (partiel)\nListe chauffeurs / plaques\nPlan d’entrepôt annoté",
                    'codewords_text' => "SKLAD\nCITERNE\nDETROUR\nNUIT",
                    'tags_text' => "russie\n2020-2024\nlogistique",
                    'notes' => 'Nœud logistique (fuel / munitions) période 2020–2024.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_command',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Poste de commandement',
                'description' => 'Officier / coordinateur, PC, radio, réseau dense.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Poste de commandement',
                    'status' => 'published',
                    'profile_code' => 'COMMANDER',
                    'complexity_code' => 'HIGH_VALUE',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'meeting_alpha',
                    'include_computer' => '1',
                    'include_biometrics' => '1',
                    'network_size' => '12',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Komandir\nSever\nOryol\nShtab",
                    'sms_templates_text' => "Briefing à 19h — salle B.\nReporter le mouvement jusqu’à nouvel ordre.\nCanal principal compromis — basculez.\nConfirmez l’état des stocks avant minuit.",
                    'document_templates_text' => "Ordre d’opération (extraits)\nListe d’indicatifs radio\nCalendrier des rotations\nNotes sur pertes / remplacements",
                    'codewords_text' => "ZARYA\nORYOL\nSHTAB\nBAGAZH",
                    'tags_text' => "russie\n2020-2024\ncommandement\nhvt",
                    'notes' => 'PC tactique — haute valeur pour SSE.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_drone',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Cellule drone / ISR',
                'description' => 'Technicien drone, cartes SD, vidéos, PC portable.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Cellule drone',
                    'status' => 'published',
                    'profile_code' => 'TECHNICIAN',
                    'complexity_code' => 'HIGH_VALUE',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'drone_ops',
                    'include_computer' => '1',
                    'network_size' => '6',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Pilot\nKamera\nBpla\nInzhener",
                    'sms_templates_text' => "Batteries chargées — fenêtre météo OK.\nPerte de lien à 12h14 — rejouer la carte.\nNe laissez pas le boîtier dans le véhicule.\nCoordonnées cible sur la clé secondaire.",
                    'document_templates_text' => "Journal de vols\nListe fréquences / canaux\nInventaire pièces drone\nCaptures annotées (imprimées)",
                    'codewords_text' => "BPLA\nOKNO\nKARTA\nSVYAZ",
                    'tags_text' => "russie\n2020-2024\ndrone\nisr",
                    'notes' => 'Exploitation numérique forte (médias / TECHINT).',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_ew',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Radio / brouillage',
                'description' => 'Technicien radio, fréquences, matériel EW léger.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Radio / EW',
                    'status' => 'published',
                    'profile_code' => 'TECHNICIAN',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'courier_run',
                    'include_phone' => '1',
                    'include_documents' => '1',
                    'include_computer' => '1',
                    'network_size' => '5',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Radist\nShum\nVolna\nAntena",
                    'sms_templates_text' => "Fenêtre de brouillage 03h–05h.\nNe passez plus sur le canal 3.\nMatériel à récupérer au dépôt NORD.\nTest réussi — signal faible côté EST.",
                    'document_templates_text' => "Table de fréquences (annotée)\nSchéma d’antenne\nListe pannes / pièces\nOrdre d’emploi du temps EW",
                    'codewords_text' => "SHUM\nVOLNA\nTISHINA\nMOST",
                    'tags_text' => "russie\n2020-2024\nradio\new",
                    'notes' => 'Nœud radio / brouillage pour scénarios TECHINT.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_infoops',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Propagande / info ops',
                'description' => 'Médias, contenus, comptes, PC et téléphone saturés.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Info ops',
                    'status' => 'published',
                    'profile_code' => 'INTELLIGENCE',
                    'complexity_code' => 'DETAILED',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'propaganda',
                    'include_computer' => '1',
                    'include_phone' => '1',
                    'network_size' => '9',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Redaktor\nKanal\nGolos\nMirror",
                    'sms_templates_text' => "Publier après 20h — fuseau local.\nRetirer la vidéo d’hier.\nNouveau script dans le dossier partagé.\nNe mentionnez pas la source primaire.",
                    'document_templates_text' => "Calendrier de publication\nScripts / talking points\nListe de comptes relais\nNotes de validation",
                    'codewords_text' => "KANAL\nZERKALO\nGOLOS\nEFIR",
                    'tags_text' => "russie\n2020-2024\npropagande\nmedia",
                    'notes' => 'Cellule influence / médias.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_courier',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Courrier civil',
                'description' => 'Messager discret, peu de digital, fausses pistes.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Courrier civil',
                    'status' => 'published',
                    'profile_code' => 'COURIER',
                    'complexity_code' => 'STANDARD',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'courier_run',
                    'network_size' => '4',
                    'noise_probability' => '0.3',
                    'false_lead_probability' => '0.3',
                    'forced_nationality' => 'Russe',
                    'alias_pool_text' => "Kurier\nSumka\nTaktsi",
                    'sms_templates_text' => "Colis au point B.\nRetard — contrôles sur la route.\nConfirmez avant d’ouvrir.\nChangez de téléphone demain.",
                    'document_templates_text' => "Ticket de bus / essence\nAdresse griffonnée\nListe de points de dépôt",
                    'codewords_text' => "SUMKA\nTOCHKA\nOKNO",
                    'tags_text' => "russie\n2020-2024\ncourrier",
                    'notes' => 'Courrier bas profil, fort potentiel de leurres.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],
            [
                'key' => 'ru_2020_2024_civil',
                'group' => 'Russie / Est 2020–2024',
                'label' => 'Russie — Civil (bruit)',
                'description' => 'Civil local, SMS quotidiens, peu d’intérêt SSE.',
                'defaults' => [
                    'name' => 'Russie 2020-2024 — Civil couverture',
                    'status' => 'published',
                    'profile_code' => 'CIVILIAN',
                    'complexity_code' => 'LIGHT',
                    'region_code' => 'RUSSIA',
                    'theme_code' => 'RANDOM',
                    'noise_probability' => '0.6',
                    'false_lead_probability' => '0.12',
                    'include_computer' => '0',
                    'include_biometrics' => '0',
                    'network_size' => '7',
                    'forced_nationality' => 'Russe',
                    'tags_text' => "russie\n2020-2024\nbruit\ncivil",
                    'notes' => 'Bruit de fond théâtre Est.',
                    'author_label' => 'COMSPEC — catalogue ère',
                ],
            ],

            // —— Génériques (conservés) ——
            [
                'key' => 'chef_hvt',
                'group' => 'Générique',
                'label' => 'Chef / HVT (générique)',
                'description' => 'Profil commandant, téléphone + documents, réseau dense.',
                'defaults' => [
                    'name' => 'Chef cellule locale',
                    'profile_code' => 'COMMANDER',
                    'complexity_code' => 'HIGH_VALUE',
                    'theme_code' => 'meeting_alpha',
                    'include_computer' => '1',
                    'network_size' => '12',
                    'alias_pool_text' => "Abu Karim\nAl-Rashid\nLe Contremaître",
                    'sms_templates_text' => "Réunion confirmée. Point ALPHA.\nLivraison reportée — changez le lieu.\nNe contactez personne avant demain.",
                    'codewords_text' => "ORAGE\nLUNE\nSABLE",
                ],
            ],
            [
                'key' => 'courrier',
                'group' => 'Générique',
                'label' => 'Courrier (générique)',
                'description' => 'Peu de contacts, messages courts, fausses pistes possibles.',
                'defaults' => [
                    'name' => 'Courrier tactique',
                    'profile_code' => 'COURIER',
                    'complexity_code' => 'STANDARD',
                    'theme_code' => 'courier_run',
                    'network_size' => '4',
                    'noise_probability' => '0.2',
                    'false_lead_probability' => '0.25',
                    'sms_templates_text' => "Colis prêt.\nChangement d'itinéraire.\nConfirmez réception.",
                ],
            ],
            [
                'key' => 'financier',
                'group' => 'Générique',
                'label' => 'Financier (générique)',
                'description' => 'Drops, listes, documents comptables.',
                'defaults' => [
                    'name' => 'Relais financier',
                    'profile_code' => 'FINANCIER',
                    'complexity_code' => 'DETAILED',
                    'theme_code' => 'finance_drop',
                    'include_documents' => '1',
                    'document_templates_text' => "Liste de montants — livraison SUD\nReçu cachet — ne pas scanner\nNoms des intermédiaires (brouillon)",
                ],
            ],
            [
                'key' => 'cellule_ied',
                'group' => 'Générique',
                'label' => 'Cellule IED (générique)',
                'description' => 'Technicien + thème engins explosifs.',
                'defaults' => [
                    'name' => 'Cellule engins',
                    'profile_code' => 'TECHNICIAN',
                    'complexity_code' => 'DETAILED',
                    'theme_code' => 'ied_cell',
                    'include_computer' => '1',
                    'codewords_text' => "ÉCLAIR\nFIL\nCHARGE",
                ],
            ],
            [
                'key' => 'civil_bruit',
                'group' => 'Générique',
                'label' => 'Civil (bruit)',
                'description' => 'Profil civil, beaucoup de bruit pour brouiller l’exploitation.',
                'defaults' => [
                    'name' => 'Civil — couverture',
                    'profile_code' => 'CIVILIAN',
                    'complexity_code' => 'LIGHT',
                    'theme_code' => 'RANDOM',
                    'noise_probability' => '0.45',
                    'false_lead_probability' => '0.35',
                    'include_computer' => '0',
                    'network_size' => '6',
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $templateDefaults
     * @return array<string,mixed>
     */
    public function mergeTemplateDefaults(array $templateDefaults): array
    {
        $base = [
            'status' => 'draft',
            'profile_code' => 'INSURGENT',
            'complexity_code' => 'DETAILED',
            'region_code' => 'IRAQ',
            'theme_code' => 'weapons_cache',
            'include_biometrics' => '1',
            'include_phone' => '1',
            'include_documents' => '1',
            'include_computer' => '0',
            'network_size' => '8',
            'noise_probability' => '',
            'false_lead_probability' => '',
            'alias_pool_text' => '',
            'contact_pool_text' => '',
            'sms_templates_text' => '',
            'document_templates_text' => '',
            'codewords_text' => '',
            'tags_text' => '',
            'forced_name' => '',
            'forced_alias' => '',
            'forced_nationality' => '',
            'notes' => '',
            'author_label' => '',
        ];

        return array_merge($base, $templateDefaults);
    }

    /**
     * Remplit les champs texte à partir d’une ligne BDD hydratée.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function rowToFormValues(array $row): array
    {
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
        $forced = is_array($payload['forcedIdentity'] ?? null) ? $payload['forcedIdentity'] : [];

        return [
            'public_id' => (string) ($row['public_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'author_label' => (string) ($row['author_label'] ?? ''),
            'status' => (string) ($row['status'] ?? 'draft'),
            'profile_code' => (string) ($row['profile_code'] ?? 'INSURGENT'),
            'complexity_code' => (string) ($row['complexity_code'] ?? 'DETAILED'),
            'region_code' => (string) ($row['region_code'] ?? 'IRAQ'),
            'theme_code' => (string) ($row['theme_code'] ?? 'weapons_cache'),
            'include_biometrics' => !empty($row['include_biometrics']) ? '1' : '0',
            'include_phone' => !empty($row['include_phone']) ? '1' : '0',
            'include_documents' => !empty($row['include_documents']) ? '1' : '0',
            'include_computer' => !empty($row['include_computer']) ? '1' : '0',
            'network_size' => (string) ((int) ($row['network_size'] ?? 8)),
            'noise_probability' => $row['noise_probability'] !== null && $row['noise_probability'] !== ''
                ? (string) $row['noise_probability'] : '',
            'false_lead_probability' => $row['false_lead_probability'] !== null && $row['false_lead_probability'] !== ''
                ? (string) $row['false_lead_probability'] : '',
            'notes' => (string) ($row['notes'] ?? ''),
            'alias_pool_text' => $this->listToLines($payload['aliasPool'] ?? []),
            'contact_pool_text' => $this->listToLines($payload['contactPool'] ?? []),
            'sms_templates_text' => $this->listToLines($payload['smsTemplates'] ?? []),
            'document_templates_text' => $this->listToLines($payload['documentTemplates'] ?? []),
            'codewords_text' => $this->listToLines($payload['codewords'] ?? []),
            'tags_text' => $this->listToLines($row['tags'] ?? []),
            'forced_name' => (string) ($forced['name'] ?? ''),
            'forced_alias' => (string) ($forced['alias'] ?? ''),
            'forced_nationality' => (string) ($forced['nationality'] ?? ''),
        ];
    }

    /**
     * Aligne une probabilité stockée (0–1 ou pourcentage) sur un choix de liste métier.
     */
    public static function snapProbabilityChoice(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            return '';
        }
        $v = (float) $normalized;
        if ($v > 1.0) {
            $v = $v / 100.0;
        }
        $v = max(0.0, min(1.0, $v));
        $buckets = [0.0, 0.1, 0.2, 0.35, 0.5];
        $best = 0.0;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($buckets as $b) {
            $d = abs($v - $b);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $b;
            }
        }
        if ($best === 0.0) {
            return '0';
        }

        return rtrim(rtrim(sprintf('%.2f', $best), '0'), '.');
    }

    private function parseOptionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (!is_numeric($value)) {
            return null;
        }
        $v = (float) $value;
        // Accepte un pourcentage saisi (ex. 15) en plus de 0–1.
        if ($v > 1.0 && $v <= 100.0) {
            $v = $v / 100.0;
        }

        return $v;
    }

    /**
     * @return list<string>
     */
    private function linesToList(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (mb_strlen($line) > 240) {
                $line = mb_substr($line, 0, 240);
            }
            $out[] = $line;
            if (count($out) >= 80) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param mixed $list
     */
    private function listToLines(mixed $list): string
    {
        if (!is_array($list)) {
            return '';
        }

        return implode("\n", array_map('strval', $list));
    }

    private function makePublicId(string $name): string
    {
        $slug = $this->sanitizePublicId($name);
        if ($slug === '') {
            $slug = 'modele';
        }

        return 'web_' . $slug . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function sanitizePublicId(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/[^a-z0-9_\-]+/', '_', $raw) ?? '';
        $raw = trim($raw, '_-');
        if (mb_strlen($raw) > 64) {
            $raw = mb_substr($raw, 0, 64);
        }

        return $raw;
    }
}
