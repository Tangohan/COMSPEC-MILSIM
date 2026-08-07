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
            $errors[] = 'Le bruit doit être entre 0 et 1 (ex. 0,15).';
        }
        if ($falseLead !== null && ($falseLead < 0 || $falseLead > 1)) {
            $errors[] = 'Les fausses pistes doivent être entre 0 et 1.';
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
     * @return list<array{key:string,label:string,description:string}>
     */
    public function builtinTemplates(): array
    {
        return [
            [
                'key' => 'chef_hvt',
                'label' => 'Chef / HVT',
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
                'label' => 'Courrier',
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
                'label' => 'Financier',
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
                'label' => 'Cellule IED',
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

        return (float) $value;
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
