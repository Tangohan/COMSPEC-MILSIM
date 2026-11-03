<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdRealCountryCatalog;
use App\Support\SseIdWorkshopCatalog;

/**
 * Atelier de documents (COMSPEC IDENTITY, Lot 6) : création et modification d'un papier avec son aperçu (recto, verso,
 * pages du passeport), copie d'un papier (papier dupliqué), modèle d'aperçu partagé par la page et l'export image.
 *
 * Règles : seul un brouillon se modifie (données imprimées, modèle, champs en plus) ; les statuts se changent à tout
 * moment ; l'identité ne se modifie plus dès qu'un autre de ses papiers est validé ou émis (il est déjà en jeu).
 * Vérité de jeu (nature de l'identité, origine du papier, falsification) : habilitation Confidentiel seulement.
 */
final class SseIdWorkshopService
{
    public function __construct(
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
    ) {
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
    }

    /**
     * L'identité d'un papier peut-elle encore être modifiée ? Non si un autre de ses papiers est validé, émis ou révoqué.
     */
    public function identityLocked(int $tenantId, int $identityId, int $exceptDocId = 0): bool
    {
        foreach ($this->documents->list($tenantId, ['identity_id' => $identityId, 'limit' => 100]) as $d) {
            if ((int) $d['id'] !== $exceptDocId && $d['workflow'] !== 'draft') {
                return true;
            }
        }

        return false;
    }

    /**
     * Enregistre le papier de l'atelier (création si $doc = null). L'identité est reprise ($identity) ou créée depuis
     * les champs saisis.
     *
     * @param array<string, mixed> $input champs du formulaire (identité, papier, statuts, print[])
     * @param array<string, mixed>|null $identity identité existante (hydratée) ou null pour en créer une
     * @param array<string, mixed>|null $doc papier existant (hydraté) ou null
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function save(int $tenantId, array $input, ?array $identity, ?array $doc, bool $truth, ?int $actorId, ?string $photoPath = null): array
    {
        if ($doc !== null && $doc['workflow'] !== 'draft') {
            return ['ok' => false, 'error' => 'Seul un brouillon se modifie. Repassez le papier en brouillon, ou dupliquez-le.'];
        }
        $country = $this->countries->findByCode($tenantId, (string) ($input['country_code'] ?? ''));
        if ($country === null || !$country['active'] || (!$country['fictional'] && empty($country['real_sim']))) {
            return ['ok' => false, 'error' => 'Choisissez un pays émetteur fictif et actif.'];
        }
        $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $input['doc_type'] ?? null, 'id_card');
        if (!in_array($type, $country['doc_types'], true)) {
            return ['ok' => false, 'error' => 'Ce pays ne délivre pas ce type de papier.'];
        }
        $number = SseIdDocumentRepository::normalizeNumber((string) ($input['doc_number'] ?? ''));
        if ($number === '') {
            $number = $this->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::randomSeed());
        }
        if (!SseIdentityGeneratorService::matchesFormat($number, (string) $country['number_format'])) {
            return ['ok' => false, 'error' => 'Ce numéro ne suit pas le format des papiers de ce pays (' . $country['number_format'] . ').'];
        }
        if ($this->documents->numberExists($tenantId, (string) $country['code'], $type, $number, (int) ($doc['id'] ?? 0))) {
            return ['ok' => false, 'error' => 'Ce numéro est déjà attribué à un autre papier de ce pays.'];
        }

        // Identité : créée, ou mise à jour tant qu'aucun autre de ses papiers n'est en jeu.
        $idData = [
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'sex' => (string) ($input['sex'] ?? 'X'),
            'birth_date' => (string) ($input['birth_date'] ?? ''),
            'birth_place' => (string) ($input['birth_place'] ?? ''),
            'nationality' => (string) (($input['nationality'] ?? '') !== '' ? $input['nationality'] : $country['code']),
            'profession' => (string) ($input['profession'] ?? ''),
            'alias' => (string) ($input['alias'] ?? ''),
        ];
        $kind = (string) ($input['kind'] ?? 'civil');
        if (!$truth && in_array($kind, MemberIdentityPapersService::SECRET, true)) {
            $kind = 'civil';
        }
        if ($identity === null) {
            if ($idData['last_name'] === '' || $idData['first_name'] === '') {
                return ['ok' => false, 'error' => 'Le nom et le prénom sont obligatoires.'];
            }
            $identityId = $this->identities->create($tenantId, $idData + [
                'kind' => $kind,
                'context_id' => (int) ($input['context_id'] ?? 0),
                'op_key' => (string) ($input['op_key'] ?? ''),
                'photo_path' => $photoPath,
                'seed' => $input['seed'] ?? null,
            ], $actorId);
            $identity = $this->identities->find($identityId, $tenantId) ?? [];
        } elseif (!$this->identityLocked($tenantId, (int) $identity['id'], (int) ($doc['id'] ?? 0)) && $idData['last_name'] !== '' && $idData['first_name'] !== '') {
            $keepKind = !$truth && in_array($identity['kind'], MemberIdentityPapersService::SECRET, true);
            $this->identities->update((int) $identity['id'], $tenantId, $idData + [
                'kind' => $keepKind ? $identity['kind'] : ($truth ? $kind : ($identity['kind'] === 'generated' ? 'generated' : $kind)),
                'context_id' => $identity['context_id'], 'op_key' => $identity['op_key'], 'person_id' => $identity['person_id'],
                'arma_key' => $identity['arma_key'], 'notes' => $identity['notes'],
                'category' => $identity['category'], 'side' => $identity['side'], 'faction' => $identity['faction'],
                'language' => $identity['language'], 'zone' => $identity['zone'],
                'photo_path' => $photoPath ?? (($input['photo_remove'] ?? '') === '1' ? null : $identity['photo_path']),
            ]);
        } elseif ($photoPath !== null) {
            $this->identities->update((int) $identity['id'], $tenantId, ['photo_path' => $photoPath] + $identity);
        }
        $identityId = (int) $identity['id'];

        $statuses = [
            'chip_status' => $input['chip_status'] ?? ($doc['chip_status'] ?? 'ok'),
            'st_integrity' => $input['st_integrity'] ?? ($doc['st_integrity'] ?? 'intact'),
            'st_validity' => $input['st_validity'] ?? ($doc['st_validity'] ?? 'valid'),
            'st_report' => $input['st_report'] ?? ($doc['st_report'] ?? 'none'),
            'st_attribution' => $input['st_attribution'] ?? ($doc['st_attribution'] ?? 'holder'),
            'st_origin' => $doc['st_origin'] ?? match ((string) ($identity['kind'] ?? '')) {
                'cover' => 'cover',
                'forged' => 'forged',
                'generated' => 'generated',
                default => 'official',
            },
            'forgery_kind' => $doc['forgery_kind'] ?? null,
            'forgery_quality' => $doc['forgery_quality'] ?? null,
        ];
        if ($truth) {
            foreach (['st_origin', 'forgery_kind', 'forgery_quality'] as $k) {
                if (array_key_exists($k, $input)) {
                    $statuses[$k] = $input[$k];
                }
            }
        }
        $details = [
            'doc_number' => $number,
            'issued_on' => (string) ($input['issued_on'] ?? ''),
            'expires_on' => (string) ($input['expires_on'] ?? ''),
            'authority' => (string) (($input['authority'] ?? '') !== '' ? $input['authority'] : $country['authority']),
            'context_id' => (int) ($input['context_id'] ?? 0),
            'op_key' => (string) ($input['op_key'] ?? ''),
            'notes' => (string) ($input['notes'] ?? ''),
        ];
        $layout = SseIdWorkshopCatalog::layout($input['layout'] ?? null);
        $print = SseIdWorkshopCatalog::printFields($input['print'] ?? []);

        if ($doc === null) {
            $id = $this->documents->create($tenantId, $details + $statuses + [
                'identity_id' => $identityId,
                'doc_type' => $type,
                'country_code' => $country['code'],
                'holder_person_id' => $identity['person_id'] ?? null,
                'layout' => $layout,
                'print' => $print,
            ], $actorId);
            SseIdMassGeneratorService::audit($tenantId, $actorId, 'id_workshop.create', 'sse_id_document', $id, ['type' => $type, 'country' => $country['code']]);

            return ['ok' => true, 'id' => $id];
        }
        $id = (int) $doc['id'];
        if ($doc['doc_type'] !== $type || $doc['country_code'] !== $country['code']) {
            return ['ok' => false, 'error' => 'Le type et le pays d’un papier ne changent pas : dupliquez-le pour en faire un autre.'];
        }
        $this->documents->updateDetails($id, $tenantId, $details);
        $this->documents->updateStatuses($id, $tenantId, $statuses + ['holder_person_id' => $doc['holder_person_id'] ?? 0]);
        $this->documents->updatePrint($id, $tenantId, $layout, $print);
        SseIdMassGeneratorService::audit($tenantId, $actorId, 'id_workshop.update', 'sse_id_document', $id, ['layout' => $layout]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Copie d'un papier (remplacement d'un papier révoqué, perdu, volé ou expiré) : nouveau numéro, nouvelles dates,
     * mêmes données imprimées, en brouillon, reliée à l'original.
     *
     * @param array<string, mixed> $doc
     */
    public function duplicate(int $tenantId, array $doc, ?int $actorId): ?int
    {
        $country = $this->countries->findByCode($tenantId, (string) $doc['country_code']);
        if ($country === null) {
            return null;
        }
        $type = (string) $doc['doc_type'];
        $id = $this->documents->create($tenantId, [
            'identity_id' => $doc['identity_id'], 'doc_type' => $type, 'country_code' => $doc['country_code'],
            'doc_number' => $this->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::randomSeed()),
            'issued_on' => gmdate('Y-m-d'),
            'expires_on' => gmdate('Y-m-d', (int) strtotime('+' . SseIdCountryRepository::validityFor($country, $type) . ' years')),
            'authority' => $doc['authority'], 'context_id' => $doc['context_id'], 'op_key' => $doc['op_key'],
            'holder_person_id' => $doc['holder_person_id'], 'chip_status' => $doc['chip_status'], 'st_origin' => $doc['st_origin'],
            'st_attribution' => $doc['st_attribution'], 'forgery_kind' => $doc['forgery_kind'], 'forgery_quality' => $doc['forgery_quality'],
            'layout' => $doc['layout'] ?? null, 'print' => $doc['print'] ?? [], 'duplicated_from_id' => (int) $doc['id'],
            'batch_id' => $doc['batch_id'] ?? null,
        ], $actorId);
        if (($doc['background_path'] ?? null) !== null) {
            $this->documents->setBackground($id, $tenantId, (string) $doc['background_path']);
        }
        SseIdMassGeneratorService::audit($tenantId, $actorId, 'id_workshop.duplicate', 'sse_id_document', $id, ['from' => (int) $doc['id']]);

        return $id;
    }

    /**
     * Photos déjà prises de la personne SSE reliée à l'identité (fiche personne, contrôles), réutilisables comme photo
     * du titulaire. Seulement les images rangées sur le site (uploads/…).
     *
     * @return list<array{id: int, url: string, label: string}>
     */
    public static function personPhotos(int $tenantId, ?int $personId, ?SsePersonRepository $persons = null): array
    {
        if ($personId === null || $personId <= 0) {
            return [];
        }
        $out = [];
        try {
            foreach (($persons ?? new SsePersonRepository())->listPhotos($personId, $tenantId) as $p) {
                if (self::localUpload((string) $p['image_path']) !== null) {
                    $out[] = ['id' => (int) $p['id'], 'url' => (string) $p['url'], 'label' => trim($p['angle_label'] . ' · ' . substr((string) ($p['created_at'] ?? ''), 0, 10), ' ·')];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return array_slice($out, -24);
    }

    /**
     * Copie une photo de la personne SSE comme photo du titulaire (réencodée, recadrée, sans métadonnées).
     *
     * @return array{ok: bool, path?: string, error?: string}
     */
    public static function copyPersonPhoto(int $tenantId, ?int $personId, int $photoId, SseIdImageService $images, ?SsePersonRepository $persons = null): array
    {
        if ($personId !== null && $personId > 0) {
            foreach (($persons ?? new SsePersonRepository())->listPhotos($personId, $tenantId) as $p) {
                if ((int) $p['id'] === $photoId && ($file = self::localUpload((string) $p['image_path'])) !== null) {
                    return $images->storeFile($file, 'photo', $tenantId);
                }
            }
        }

        return ['ok' => false, 'error' => 'Cette photo n’appartient pas à la personne reliée à l’identité.'];
    }

    /** Chemin disque d'une image rangée sous public/uploads (null sinon). */
    private static function localUpload(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if (preg_match('#(?:^|/)(uploads/[A-Za-z0-9_\-./]+\.(?:png|jpe?g|webp))$#i', $path, $m) !== 1 || str_contains($m[1], '..')) {
            return null;
        }
        $file = base_path('public/' . $m[1]);

        return is_file($file) ? $file : null;
    }

    /** @param array<string, mixed> $country */
    public function freeNumber(int $tenantId, array $country, string $type, int $seed): string
    {
        $format = (string) $country['number_format'];
        for ($i = 0; $i < 20; $i++) {
            $n = SseIdentityGeneratorService::documentNumber($format, $seed, $type . $i);
            if (!$this->documents->numberExists($tenantId, (string) $country['code'], $type, $n)) {
                return $n;
            }
        }

        return SseIdentityGeneratorService::documentNumber($format, SseIdentityGeneratorService::randomSeed(), $type);
    }

    /**
     * Bande MRZ calculée pour l'aperçu en direct (SseMrzService) et contrôle de ses chiffres.
     *
     * @param array<string, mixed> $q doc_type, country, number, last, first, dob, sex, nat, expires
     * @return array{lines: list<string>, valid: bool, format: string}
     */
    public function mrzPreview(int $tenantId, array $q): array
    {
        $country = $this->countries->findByCode($tenantId, (string) ($q['country'] ?? ''));
        $mrzCode = (string) ($country['mrz_code'] ?? strtoupper(substr((string) ($q['country'] ?? ''), 0, 3)));
        $lines = SseMrzService::forDocument(
            [
                'doc_type' => SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $q['doc_type'] ?? null, 'id_card'),
                'country_code' => (string) ($country['code'] ?? ''),
                'doc_number' => SseIdDocumentRepository::normalizeNumber((string) ($q['number'] ?? '')),
                'expires_on' => self::date($q['expires'] ?? null),
            ],
            [
                'last_name' => mb_substr((string) ($q['last'] ?? ''), 0, 120),
                'first_name' => mb_substr((string) ($q['first'] ?? ''), 0, 120),
                'nationality' => strtoupper(mb_substr((string) ($q['nat'] ?? ''), 0, 3)),
                'birth_date' => self::date($q['dob'] ?? null),
                'sex' => SseIdentityCatalog::pick(SseIdentityCatalog::SEXES, strtoupper((string) ($q['sex'] ?? '')), 'X'),
            ],
            $mrzCode
        );
        $check = SseMrzService::verify($lines);

        return ['lines' => $lines, 'valid' => $check['valid'], 'format' => $check['format']];
    }

    /**
     * Données de l'aperçu (page, export image) : pays et couleurs, identité, papier, champs imprimés, bande MRZ.
     *
     * @param array<string, mixed> $doc papier hydraté (ou brouillon de formulaire : mêmes clés)
     * @param array<string, mixed> $identity identité hydratée (mêmes clés)
     * @param array<string, mixed>|null $country pays hydraté
     * @return array<string, mixed>
     */
    public static function previewModel(array $doc, array $identity, ?array $country): array
    {
        $colors = array_values(array_filter([$country['color_primary'] ?? null, $country['color_secondary'] ?? null]));
        $colors += [0 => '#1f4e79', 1 => '#c9a227'];
        $real = !empty($country['real_sim']);
        $flag = $real ? SseIdRealCountryCatalog::flagDataUri((string) $country['code'])
            : (($country['flag_path'] ?? null) !== null ? (string) url((string) $country['flag_path']) : null);

        return [
            'doc_type' => (string) ($doc['doc_type'] ?? 'id_card'),
            'doc_type_label' => SseIdentityCatalog::label(SseIdentityCatalog::DOC_TYPES, (string) ($doc['doc_type'] ?? 'id_card')),
            'layout' => SseIdWorkshopCatalog::layout($doc['layout'] ?? null),
            'background_url' => ($doc['background_path'] ?? null) !== null ? (string) url((string) $doc['background_path']) : null,
            'country' => [
                'code' => (string) ($country['code'] ?? ($doc['country_code'] ?? '')),
                'name' => (string) ($country['name'] ?? ($doc['country_code'] ?? '')),
                'name_en' => (string) ($country['name_en'] ?? ''),
                'colors' => $colors,
                'flag_url' => $flag,
                // Pays réel stylisé : gabarit générique de sa zone (europe|americas|other), jamais un vrai modèle.
                'real_sim' => $real,
                'zone' => $real ? SseIdRealCountryCatalog::zone((string) $country['code']) : '',
            ],
            'identity' => [
                'last_name' => (string) ($identity['last_name'] ?? ''),
                'first_name' => (string) ($identity['first_name'] ?? ''),
                'sex' => (string) ($identity['sex'] ?? 'X'),
                'birth_date' => (string) ($identity['birth_date'] ?? ''),
                'birth_place' => (string) ($identity['birth_place'] ?? ''),
                'nationality' => (string) ($identity['nationality'] ?? ''),
                'profession' => (string) ($identity['profession'] ?? ''),
                'photo_url' => ($identity['photo_path'] ?? null) !== null ? (string) url((string) $identity['photo_path']) : null,
                'signature_url' => ($identity['signature_path'] ?? null) !== null ? (string) url((string) $identity['signature_path']) : null,
            ],
            'doc' => [
                'ref' => (string) ($doc['ref'] ?? ''),
                'number' => (string) ($doc['doc_number'] ?? ''),
                'issued_on' => (string) ($doc['issued_on'] ?? ''),
                'expires_on' => (string) ($doc['expires_on'] ?? ''),
                'authority' => (string) (($doc['authority'] ?? '') !== '' ? $doc['authority'] : ($country['authority'] ?? '')),
                'workflow' => (string) ($doc['workflow'] ?? 'draft'),
                'st_validity' => (string) ($doc['st_validity'] ?? 'valid'),
                'st_report' => (string) ($doc['st_report'] ?? 'none'),
            ],
            'print' => SseIdWorkshopCatalog::printFields($doc['print'] ?? []),
            'mrz' => SseMrzService::forDocument($doc, $identity, (string) ($country['mrz_code'] ?? '')),
        ];
    }

    private static function date(mixed $v): ?string
    {
        $s = trim(is_scalar($v) ? (string) $v : '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 ? $s : null;
    }
}
