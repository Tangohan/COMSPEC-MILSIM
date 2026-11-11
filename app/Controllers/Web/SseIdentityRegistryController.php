<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdSettingsRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdCheckInsightService;
use App\Services\Sse\SseIdSubjectMatchService;
use App\Services\Sse\SseIdentityGeneratorService;
use App\Services\Sse\SseMrzService;
use App\Support\SseIdCountryCatalog;
use App\Support\SseIdRealCountryCatalog;
use App\Support\SseIdentityCatalog;

/**
 * Portail SSE — RENS › Identités & documents (COMSPEC IDENTITY, Lot 1).
 *
 * Registre des identités fictives et des papiers (carte, passeport, permis) des pays fictifs, cycle
 * brouillon → validé → émis → révoqué, statuts du papier, contrôles reçus du terrain.
 *
 * Lecture : accès au portail SSE. Création, statuts et cycle : gestion des dossiers SSE (atak.sse.case.manage,
 * atak.sse.grant ou admin.access), jamais en invité. Vérité de jeu (nature de l'identité, faux, couverture) :
 * habilitation Confidentiel ou plus ; en dessous elle est masquée.
 */
final class SseIdentityRegistryController
{
    private const BASE = 'atak/sse/papiers';

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCheckRepository $checks = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SsePersonRepository $persons = null,
        private ?SseIdentityGeneratorService $generator = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->checks ??= new SseIdCheckRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->persons ??= new SsePersonRepository();
        $this->generator ??= new SseIdentityGeneratorService();
    }

    // ---------------------------------------------------------------- Papiers

    public function documentsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $truth = $this->canSeeTruth();
        $filters = [
            'workflow' => $request->queryString('workflow'),
            'doc_type' => $request->queryString('doc_type'),
            'country_code' => $request->queryString('country'),
            'origin' => $truth ? $request->queryString('origin') : '',
            'q' => $request->queryString('q'),
        ];

        return $this->view('atak.sse.papers.documents', [
            'title' => 'Papiers d’identité',
            'documents' => $this->documents->list($tenantId, $filters),
            'filters' => $filters,
            'counts' => $this->documents->counts($tenantId),
            'countries' => $this->countries->listForTenant($tenantId),
            'papersTab' => 'documents',
        ]);
    }

    public function documentCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $identity = $this->identities->find((int) $request->query('identity_id', 0), $tenantId);
        if ($identity === null) {
            Session::flash('error', 'Choisissez d’abord l’identité qui portera ce papier.');

            return Response::redirect(url(self::BASE . '/identites'));
        }
        $countries = $this->countries->listForTenant($tenantId);
        $countryCode = strtoupper($request->queryString('country', (string) ($identity['nationality'] ?? '')));
        $country = $this->pickCountry($countries, $countryCode);
        $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $request->queryString('doc_type'), 'id_card');
        $issued = gmdate('Y-m-d');
        if ($country !== null && !in_array($type, $country['doc_types'], true)) {
            $type = (string) ($country['doc_types'][0] ?? $type);
        }
        $expires = gmdate('Y-m-d', (int) strtotime('+' . ($country !== null ? SseIdCountryRepository::validityFor($country, $type) : 10) . ' years'));
        $number = $country !== null ? $this->freeNumber($tenantId, $country, $type, (int) ($identity['seed'] ?? 0) ?: SseIdentityGeneratorService::randomSeed()) : '';
        $preview = $country !== null ? SseMrzService::forDocument(
            ['doc_type' => $type, 'country_code' => $country['code'], 'doc_number' => $number, 'expires_on' => $expires],
            $identity,
            (string) $country['mrz_code']
        ) : [];

        return $this->view('atak.sse.papers.document_form', [
            'title' => 'Nouveau papier',
            'identity' => $identity,
            'countries' => $countries,
            'country' => $country,
            'docType' => $type,
            'proposal' => ['doc_number' => $number, 'issued_on' => $issued, 'expires_on' => $expires, 'authority' => (string) ($country['authority'] ?? '')],
            'mrzPreview' => $preview,
            'maps' => $this->maps(),
            'papersTab' => 'documents',
        ]);
    }

    public function documentStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $identity = $this->identities->find((int) $request->input('identity_id', 0), $tenantId);
        $country = $this->countries->findByCode($tenantId, $request->inputString('country_code'));
        if ($identity === null || $country === null || !$country['active']) {
            Session::flash('error', 'Identité ou pays introuvable.');

            return Response::redirect(url(self::BASE . '/identites'));
        }
        $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $request->inputString('doc_type'), 'id_card');
        $back = url(self::BASE . '/documents/nouveau') . '?identity_id=' . (int) $identity['id'] . '&country=' . urlencode((string) $country['code']) . '&doc_type=' . $type;
        $number = SseIdDocumentRepository::normalizeNumber($request->inputString('doc_number'));
        if ($number === '') {
            $number = $this->freeNumber($tenantId, $country, $type, SseIdentityGeneratorService::randomSeed());
        }
        if (!SseIdentityGeneratorService::matchesFormat($number, (string) $country['number_format'])) {
            Session::flash('error', 'Ce numéro ne suit pas le format des papiers de ce pays.');

            return Response::redirect($back);
        }
        if ($this->documents->numberExists($tenantId, (string) $country['code'], $type, $number)) {
            Session::flash('error', 'Ce numéro est déjà attribué à un autre papier de ce pays.');

            return Response::redirect($back);
        }
        $data = [
            'identity_id' => (int) $identity['id'],
            'doc_type' => $type,
            'country_code' => $country['code'],
            'doc_number' => $number,
            'issued_on' => $request->inputString('issued_on'),
            'expires_on' => $request->inputString('expires_on'),
            'authority' => $request->inputString('authority'),
            'context_id' => (int) $request->input('context_id', 0),
            'op_key' => $request->inputString('op_key'),
            'holder_person_id' => $identity['person_id'],
            'notes' => $request->inputString('notes'),
        ];
        // Origine par défaut selon la nature de l'identité ; un faux reste un faux.
        $data['st_origin'] = match ($identity['kind']) {
            'cover' => 'cover',
            'forged' => 'forged',
            'generated' => 'generated',
            default => 'official',
        };
        if ($this->canSeeTruth()) {
            foreach (['st_origin', 'forgery_kind', 'forgery_quality'] as $k) {
                $v = $request->inputString($k);
                if ($v !== '') {
                    $data[$k] = $v;
                }
            }
        }
        $id = $this->documents->create($tenantId, $data, $this->userId());
        Session::flash('success', 'Papier enregistré en brouillon. Validez-le, puis émettez-le pour qu’il parte en jeu.');

        return Response::redirect(url(self::BASE . '/documents/' . $id));
    }

    public function documentShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $doc = $this->documents->find((int) ($params['id'] ?? 0), $tenantId);
        if ($doc === null) {
            Session::flash('error', 'Papier introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $identity = $this->identities->find((int) $doc['identity_id'], $tenantId) ?? [];
        $country = $this->countries->findByCode($tenantId, (string) $doc['country_code']);
        $holder = ($pid = (int) ($doc['holder_person_id'] ?? 0)) > 0 ? $this->persons->findById($pid, $tenantId) : null;

        return $this->view('atak.sse.papers.document_show', [
            'title' => $doc['ref'] . ' — ' . $doc['doc_type_label'],
            'doc' => $doc,
            'identity' => $identity,
            'country' => $country,
            'holder' => $holder,
            'mrz' => SseMrzService::forDocument($doc, $identity, (string) ($country['mrz_code'] ?? '')),
            'checks' => $this->checks->list($tenantId, ['document_id' => (int) $doc['id'], 'limit' => 50]),
            'instances' => $this->documents->instances((int) $doc['id'], $tenantId),
            'transitions' => SseIdentityCatalog::TRANSITIONS[$doc['workflow']] ?? [],
            'papersTab' => 'documents',
            'personPhotos' => \App\Services\Sse\SseIdWorkshopService::personPhotos($tenantId, $identity['person_id'] ?? null),
        ]);
    }

    public function documentStatuses(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/documents/' . $id);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $doc = $this->documents->find($id, $tenantId);
        if ($doc === null) {
            return $this->denied(url(self::BASE));
        }
        $data = [
            'chip_status' => $request->inputString('chip_status'),
            'st_integrity' => $request->inputString('st_integrity'),
            'st_validity' => $request->inputString('st_validity'),
            'st_report' => $request->inputString('st_report'),
            'st_attribution' => $request->inputString('st_attribution'),
            'holder_person_id' => (int) $request->input('holder_person_id', 0) ?: ($doc['holder_person_id'] ?? 0),
            // Sans habilitation, la vérité de jeu déjà posée est conservée telle quelle.
            'st_origin' => $doc['st_origin'],
            'forgery_kind' => $doc['forgery_kind'],
            'forgery_quality' => $doc['forgery_quality'],
        ];
        if ($this->canSeeTruth()) {
            $data['st_origin'] = $request->inputString('st_origin');
            $data['forgery_kind'] = $request->inputString('forgery_kind');
            $data['forgery_quality'] = $request->inputString('forgery_quality');
        }
        if ($data['holder_person_id'] > 0 && $this->persons->findById((int) $data['holder_person_id'], $tenantId) === null) {
            Session::flash('error', 'Fiche personne introuvable : titulaire inchangé.');
            $data['holder_person_id'] = $doc['holder_person_id'] ?? 0;
        }
        $this->documents->updateStatuses($id, $tenantId, $data);
        Session::flash('success', 'Statuts du papier enregistrés.');

        return Response::redirect($back);
    }

    public function documentWorkflow(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/documents/' . $id);
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $to = SseIdentityCatalog::pick(SseIdentityCatalog::WORKFLOWS, $request->inputString('to'), '');
        if ($to === 'revoked' && trim($request->inputString('reason')) === '') {
            Session::flash('error', 'Indiquez le motif de la révocation.');

            return Response::redirect($back);
        }
        if ($to === '' || !$this->documents->transition($id, $this->tenantId(), $to, $this->userId(), $request->inputString('reason'))) {
            Session::flash('error', 'Cette étape n’est pas possible depuis l’état actuel du papier.');

            return Response::redirect($back);
        }
        Session::flash('success', match ($to) {
            'validated' => 'Papier validé. Il partira en jeu une fois émis.',
            'issued' => 'Papier émis : il sera chargé par le serveur de jeu à la prochaine mise à jour.',
            'revoked' => 'Papier révoqué : les contrôles en ligne le signalent désormais comme annulé.',
            default => 'Papier repassé en brouillon.',
        });

        return Response::redirect($back);
    }

    // ------------------------------------------------------- Pays émetteurs

    public function countriesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $q = $request->queryString('q');
        $countries = $this->countries->search($tenantId, $q);
        $used = [];
        foreach ($countries as $c) {
            $used[$c['code']] = $this->documents->countForCountry($tenantId, (string) $c['code']);
        }

        return $this->view('atak.sse.papers.countries', [
            'title' => 'Pays émetteurs',
            'countries' => $countries,
            'used' => $used,
            'q' => $q,
            'papersTab' => 'pays',
        ]);
    }

    public function countryForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return $this->denied(url(self::BASE . '/pays'));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $country = $id > 0 ? $this->countries->find($id, $tenantId) : null;
        if ($id > 0 && $country === null) {
            Session::flash('error', 'Pays introuvable.');

            return Response::redirect(url(self::BASE . '/pays'));
        }

        return $this->view('atak.sse.papers.country_form', [
            'title' => $country !== null ? 'Modifier le pays' : 'Nouveau pays émetteur',
            'country' => $country,
            'used' => $country !== null ? $this->documents->countForCountry($tenantId, (string) $country['code']) : 0,
            'papersTab' => 'pays',
        ]);
    }

    public function countryStore(Request $request, array $params = []): Response
    {
        return $this->saveCountry($request, 0);
    }

    public function countryUpdate(Request $request, array $params = []): Response
    {
        return $this->saveCountry($request, (int) ($params['id'] ?? 0));
    }

    /** Jeu de pays réels stylisés proposé à l'import (univers réaliste-simulation), avec aperçu du gabarit. */
    public function countryRealForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return $this->denied(url(self::BASE . '/pays'));
        }
        $tenantId = $this->tenantId();
        $present = [];
        foreach ($this->countries->listForTenant($tenantId, false) as $c) {
            $present[$c['code']] = $c;
        }

        return $this->view('atak.sse.papers.country_real_import', [
            'title' => 'Ajouter des pays réels (simulation)',
            'catalog' => self::realCatalogPreview(),
            'present' => $present,
            'papersTab' => 'pays',
        ]);
    }

    public function countryRealImport(Request $request, array $params = []): Response
    {
        $form = url(self::BASE . '/pays/reels');
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied(url(self::BASE . '/pays'));
        }
        $res = self::validateRealImport([
            'universe' => $request->inputString('universe'),
            'guardrails' => $request->inputString('guardrails') === '1',
            'codes' => $request->input('codes', []),
        ]);
        if ($res['errors'] !== []) {
            Session::flash('error', implode(' ', $res['errors']));

            return Response::redirect($form);
        }
        $tenantId = $this->tenantId();
        $out = $this->countries->importRealSet($tenantId, $res['codes'], $this->userId());
        $this->audit($tenantId, 'id_country.import_real_sim', 0, $out + ['universe' => SseIdRealCountryCatalog::UNIVERSE]);
        $msg = [];
        if ($out['added'] !== []) {
            $msg[] = 'Ajoutés : ' . implode(', ', $out['added']) . '.';
        }
        if ($out['restored'] !== []) {
            $msg[] = 'Rétablis : ' . implode(', ', $out['restored']) . '.';
        }
        if ($out['kept'] !== []) {
            $msg[] = 'Déjà présents : ' . implode(', ', $out['kept']) . '.';
        }
        if ($out['conflicts'] !== []) {
            $msg[] = 'Code déjà pris par un pays de la communauté, laissé tel quel : ' . implode(', ', $out['conflicts']) . '.';
        }
        Session::flash('success', 'Pays réels stylisés (univers réaliste-simulation). ' . implode(' ', $msg) . ' Leurs papiers restent des documents de jeu marqués SIMULATION.');

        return Response::redirect(url(self::BASE . '/pays'));
    }

    /**
     * Garde-fous de l'import : univers réaliste-simulation choisi, engagement coché, au moins un pays du jeu.
     *
     * @param array{universe?: mixed, guardrails?: mixed, codes?: mixed} $input
     * @return array{codes: list<string>, errors: list<string>}
     */
    public static function validateRealImport(array $input): array
    {
        $errors = [];
        if (($input['universe'] ?? '') !== SseIdRealCountryCatalog::UNIVERSE) {
            $errors[] = 'Choisissez l’univers « Réaliste — simulation » : les pays réels stylisés lui sont réservés.';
        }
        if (empty($input['guardrails'])) {
            $errors[] = 'Cochez l’engagement : papiers de jeu marqués SIMULATION, gabarits génériques, photos de personnages du jeu uniquement.';
        }
        $codes = [];
        foreach (is_array($input['codes'] ?? null) ? $input['codes'] : [] as $c) {
            $c = strtoupper(trim(is_scalar($c) ? (string) $c : ''));
            if (SseIdRealCountryCatalog::has($c) && !in_array($c, $codes, true)) {
                $codes[] = $c;
            }
        }
        if ($codes === []) {
            $errors[] = 'Choisissez au moins un pays.';
        }

        return ['codes' => $codes, 'errors' => $errors];
    }

    /**
     * Fiches d'aperçu du jeu stylisé : données du pays, numéro et bande MRZ de spécimen (passeport TD3 s'il existe).
     *
     * @return list<array<string, mixed>>
     */
    public static function realCatalogPreview(): array
    {
        $out = [];
        foreach (array_keys(SseIdRealCountryCatalog::COUNTRIES) as $code) {
            $d = SseIdRealCountryCatalog::countryData($code);
            $c = (array) SseIdRealCountryCatalog::get($code);
            $number = SseIdentityGeneratorService::documentNumber((string) $d['number_format'], 424242, 'specimen');
            $type = in_array('passport', $d['doc_types'], true) ? 'passport' : (string) $d['doc_types'][0];
            $out[] = $d + [
                'name_native' => (string) $c['name_native'],
                'zone' => (string) $c['zone'],
                'zone_label' => SseIdRealCountryCatalog::ZONES[$c['zone']]['label'],
                'flag' => SseIdRealCountryCatalog::flagDataUri($code),
                'specimen_number' => $number,
                'specimen_type' => $type,
                'mrz' => SseMrzService::forDocument(
                    ['doc_type' => $type, 'country_code' => $code, 'doc_number' => $number, 'expires_on' => '2035-12-31'],
                    ['last_name' => 'SPECIMEN', 'first_name' => 'SIMULATION', 'nationality' => $code, 'birth_date' => '1990-01-01', 'sex' => 'M'],
                    (string) $d['mrz_code']
                ),
            ];
        }

        return $out;
    }

    public function countryArchive(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/pays');
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $country = $this->countries->find($id, $tenantId);
        if ($country === null) {
            return $this->denied($back);
        }
        $archive = $request->inputString('restore') !== '1';
        $this->countries->setArchived($id, $tenantId, $archive);
        $this->audit($tenantId, $archive ? 'id_country.archive' : 'id_country.restore', $id, ['code' => $country['code']]);
        Session::flash('success', $archive
            ? 'Pays archivé : il n’est plus proposé pour de nouveaux papiers, les papiers déjà émis restent valables.'
            : 'Pays rétabli : il est de nouveau proposé dans les formulaires.');

        return Response::redirect($back);
    }

    /**
     * Valide un pays saisi (création si $existing = null ; le code n'est jamais modifié après création).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @param callable(string): bool $codeTaken
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public static function validateCountry(array $input, ?array $existing, callable $codeTaken): array
    {
        $errors = [];
        $real = $existing !== null && !empty($existing['real_sim']);
        $theme = SseIdentityCatalog::pick(SseIdCountryCatalog::THEMES, $input['theme'] ?? null, 'contemporary');
        $code = $existing !== null ? (string) $existing['code'] : strtoupper(trim((string) ($input['code'] ?? '')));
        if ($real) {
            // Pays réel stylisé : univers, code MRZ OACI et format générique du jeu imposés (garde-fous).
            $theme = SseIdRealCountryCatalog::UNIVERSE;
            $input['mrz_code'] = (string) $existing['mrz_code'];
            $input['number_format'] = (string) $existing['number_format'];
            $input['region'] = ($input['region'] ?? '') !== '' ? $input['region'] : (string) ($existing['region'] ?? '');
        }
        if (!$real && $theme === SseIdRealCountryCatalog::UNIVERSE && $existing !== null) {
            $errors[] = 'L’univers « Réaliste — simulation » est réservé aux pays réels stylisés : ajoutez-les avec le bouton « Ajouter des pays réels (simulation) ».';
        }
        if ($existing === null) {
            $p = SseIdCountryCatalog::codeProblem($code, $theme);
            if ($p !== '') {
                $errors[] = $p;
            } elseif ($codeTaken($code)) {
                $errors[] = 'Ce code est déjà utilisé par un autre pays de la communauté.';
            }
        }
        $mrz = strtoupper(trim((string) ($input['mrz_code'] ?? '')));
        $mrz = $mrz !== '' ? $mrz : $code;
        // Code de bande identique au code du pays : déjà vérifié ci-dessus (pas de message en double).
        if (($mrz !== $code || $existing !== null) && ($p = SseIdCountryCatalog::mrzProblem($mrz, $theme, $real)) !== '') {
            $errors[] = $p;
        }
        $format = strtoupper(trim((string) ($input['number_format'] ?? '')));
        if (($p = SseIdCountryCatalog::formatProblem($format)) !== '') {
            $errors[] = $p;
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Le nom du pays est obligatoire.';
        }
        if (empty($input['fictional'])) {
            $errors[] = $real
                ? 'Cochez la case « document de jeu » : les papiers de ce pays restent des spécimens marqués SIMULATION, sans valeur.'
                : 'Cochez la case « pays fictif » : seuls des pays imaginaires peuvent émettre des papiers de jeu.';
        }
        $types = SseIdCountryCatalog::pickMany($input['doc_types'] ?? [], SseIdentityCatalog::DOC_TYPES);
        if ($types === []) {
            $errors[] = 'Choisissez au moins un type de papier délivré par ce pays.';
        }
        $validity = [];
        foreach ($types as $t) {
            $y = $input['validity'][$t] ?? 10;
            $y = is_numeric($y) ? (int) $y : 0;
            if ($y < SseIdCountryCatalog::VALIDITY_MIN || $y > SseIdCountryCatalog::VALIDITY_MAX) {
                $errors[] = 'La durée de validité d’un papier va de 1 à 50 ans.';
                break;
            }
            $validity[$t] = $y;
        }
        $languages = SseIdCountryCatalog::pickMany($input['languages'] ?? [], SseIdCountryCatalog::LANGUAGES);

        return [
            'data' => [
                'code' => $code,
                'name' => $name,
                'name_en' => trim((string) ($input['name_en'] ?? '')),
                'mrz_code' => $mrz,
                'number_format' => $format,
                'authority' => trim((string) ($input['authority'] ?? '')),
                'region' => isset(SseIdCountryCatalog::REGIONS[(string) ($input['region'] ?? '')]) ? (string) ($input['region'] ?? '') : ($real ? (string) ($existing['region'] ?? '') : ''),
                'theme' => $theme,
                'languages' => $languages !== [] ? $languages : ['fr', 'en'],
                'doc_types' => $types,
                'validity' => $validity,
                'color_primary' => SseIdCountryCatalog::color($input['color_primary'] ?? null),
                'color_secondary' => SseIdCountryCatalog::color($input['color_secondary'] ?? null),
                'flag_path' => $real ? null : ($existing['flag_path'] ?? null),
            ],
            'errors' => $errors,
        ];
    }

    private function saveCountry(Request $request, int $id): Response
    {
        $list = url(self::BASE . '/pays');
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($list);
        }
        $tenantId = $this->tenantId();
        $existing = $id > 0 ? $this->countries->find($id, $tenantId) : null;
        if ($id > 0 && $existing === null) {
            return $this->denied($list);
        }
        $validity = $request->input('validity', []);
        $input = [
            'code' => $request->inputString('code'),
            'name' => $request->inputString('name'),
            'name_en' => $request->inputString('name_en'),
            'mrz_code' => $request->inputString('mrz_code'),
            'number_format' => $request->inputString('number_format'),
            'authority' => $request->inputString('authority'),
            'region' => $request->inputString('region'),
            'theme' => $request->inputString('theme'),
            'fictional' => $request->inputString('fictional') === '1',
            'languages' => $request->input('languages', []),
            'doc_types' => $request->input('doc_types', []),
            'validity' => is_array($validity) ? $validity : [],
            'color_primary' => $request->inputString('color_primary'),
            'color_secondary' => $request->inputString('color_secondary'),
        ];
        $res = self::validateCountry($input, $existing, fn (string $code): bool => $this->countries->findByCode($tenantId, $code) !== null);
        $form = $id > 0 ? url(self::BASE . '/pays/' . $id) : url(self::BASE . '/pays/nouveau');
        if ($res['errors'] !== []) {
            Session::flash('error', implode(' ', $res['errors']));

            return Response::redirect($form);
        }
        $data = $res['data'];
        if ($request->inputString('flag_remove') === '1') {
            $data['flag_path'] = null;
        }
        // Pays réel stylisé : pas de drapeau ni d'armoiries téléversés (drapeau simple du jeu, aucun emblème officiel).
        $flag = !empty($existing['real_sim']) ? null : $this->storeFlag($tenantId, (string) $data['code']);
        if ($flag === false) {
            Session::flash('error', 'Le drapeau doit être une image PNG, JPG ou WebP de 1 Mo maximum.');

            return Response::redirect($form);
        }
        if (is_string($flag)) {
            $data['flag_path'] = $flag;
        }
        if ($existing !== null) {
            $this->countries->update($id, $tenantId, $data);
            $this->audit($tenantId, 'id_country.update', $id, ['code' => $data['code']]);
            Session::flash('success', 'Pays enregistré.');
        } else {
            $id = $this->countries->create($tenantId, $data, $this->userId());
            $this->audit($tenantId, 'id_country.create', $id, ['code' => $data['code'], 'theme' => $data['theme']]);
            Session::flash('success', 'Pays créé : il est proposé dans les formulaires de papiers et part au serveur de jeu.');
        }

        return Response::redirect($list);
    }

    /**
     * Drapeau ou armoiries téléversés : chemin public, null si rien n'est envoyé, false si le fichier est refusé.
     * Vrai type contrôlé, image réencodée sans métadonnées (SseIdImageService).
     */
    private function storeFlag(int $tenantId, string $code): string|false|null
    {
        $res = (new \App\Services\Sse\SseIdImageService())->storeUpload($_FILES['flag'] ?? null, 'flag', $tenantId);
        if (!empty($res['none'])) {
            return null;
        }

        return $res['ok'] ? (string) $res['path'] : false;
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenantId, string $action, int $objectId, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $this->userId(),
                'actor_label' => 'Registre des papiers',
                'action' => $action,
                'object_type' => 'sse_id_country',
                'object_id' => $objectId,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Registre des papiers : audit indisponible : ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------- Identités

    public function identitiesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $truth = $this->canSeeTruth();
        $filters = [
            'kind' => $truth ? $request->queryString('kind') : '',
            'nationality' => $request->queryString('country'),
            'q' => $request->queryString('q'),
        ];

        return $this->view('atak.sse.papers.identities', [
            'title' => 'Identités fictives',
            'identities' => $this->identities->list($tenantId, $filters),
            'filters' => $filters,
            'countries' => $this->countries->listForTenant($tenantId),
            'papersTab' => 'identites',
        ]);
    }

    public function identityCreateForm(Request $request, array $params = []): Response
    {
        if (!$this->canManage()) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $countries = $this->countries->listForTenant($tenantId);
        $country = $this->pickCountry($countries, strtoupper($request->queryString('country')));
        $draft = [
            'kind' => SseIdentityCatalog::pick(SseIdentityCatalog::KINDS, $request->queryString('kind'), 'civil'),
            'person_id' => (int) $request->query('person_id', 0) ?: null,
            'nationality' => $country['code'] ?? '',
        ];
        $generated = false;
        if ($request->queryString('generer') !== '' && $country !== null) {
            $seedRaw = $request->queryString('seed');
            $seed = ctype_digit($seedRaw) ? (int) $seedRaw : null;
            $sex = $request->queryString('sex');
            $draft = array_merge($draft, $this->generator->generate($country, $seed, in_array($sex, ['M', 'F'], true) ? $sex : null));
            if ($request->queryString('kind') !== '') {
                $draft['kind'] = SseIdentityCatalog::pick(SseIdentityCatalog::KINDS, $request->queryString('kind'), 'generated');
            }
            $generated = true;
        }
        $person = ($draft['person_id'] ?? 0) > 0 ? $this->persons->findById((int) $draft['person_id'], $tenantId) : null;

        return $this->view('atak.sse.papers.identity_form', [
            'title' => 'Nouvelle identité',
            'draft' => $draft,
            'generated' => $generated,
            'countries' => $countries,
            'person' => $person,
            'maps' => $this->maps(),
            'papersTab' => 'identites',
        ]);
    }

    public function identityStore(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $last = trim($request->inputString('last_name'));
        $first = trim($request->inputString('first_name'));
        if ($last === '' || $first === '') {
            Session::flash('error', 'Le nom et le prénom sont obligatoires.');

            return Response::redirect(url(self::BASE . '/identites/nouvelle'));
        }
        $personId = (int) $request->input('person_id', 0);
        if ($personId > 0 && $this->persons->findById($personId, $tenantId) === null) {
            $personId = 0;
        }
        $kind = $request->inputString('kind');
        if (!$this->canSeeTruth() && in_array($kind, MemberIdentityPapersService::SECRET, true)) {
            $kind = 'civil';
        }
        $id = $this->identities->create($tenantId, [
            'kind' => $kind,
            'last_name' => $last,
            'first_name' => $first,
            'sex' => $request->inputString('sex'),
            'birth_date' => $request->inputString('birth_date'),
            'birth_place' => $request->inputString('birth_place'),
            'nationality' => $request->inputString('nationality'),
            'profession' => $request->inputString('profession'),
            'arma_key' => $request->inputString('arma_key'),
            'person_id' => $personId,
            'context_id' => (int) $request->input('context_id', 0),
            'op_key' => $request->inputString('op_key'),
            'seed' => $request->inputString('seed'),
            'notes' => $request->inputString('notes'),
        ], $this->userId());
        Session::flash('success', 'Identité enregistrée. Ajoutez-lui un papier.');

        return Response::redirect(url(self::BASE . '/identites/' . $id));
    }

    public function identityShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $identity = $this->identities->find((int) ($params['id'] ?? 0), $tenantId);
        if ($identity === null) {
            Session::flash('error', 'Identité introuvable.');

            return Response::redirect(url(self::BASE . '/identites'));
        }
        $person = ($identity['person_id'] ?? 0) > 0 ? $this->persons->findById((int) $identity['person_id'], $tenantId) : null;

        return $this->view('atak.sse.papers.identity_show', [
            'title' => $identity['ref'] . ' — ' . $identity['display_name'],
            'identity' => $identity,
            'person' => $person,
            'documents' => $this->documents->list($tenantId, ['identity_id' => (int) $identity['id']]),
            'countries' => $this->countries->listForTenant($tenantId),
            'papersTab' => 'identites',
            'personPhotos' => \App\Services\Sse\SseIdWorkshopService::personPhotos($tenantId, $identity['person_id']),
        ]);
    }

    // ------------------------------------------------------- Papiers des membres

    /**
     * Liste des membres de la communauté et de leurs papiers, accès direct des responsables RENS (gestion SSE ou
     * habilitation Encadrement) sans droit Effectifs. Aucune donnée RH : nom affiché, indicatif, unité.
     */
    public function membersIndex(Request $request, array $params = []): Response
    {
        $rights = $this->memberPapersRights();
        if ($rights === null) {
            return $this->denied(url('atak/sse'));
        }
        $tenantId = $this->tenantId();
        $filters = [
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
            'unit' => (int) $request->query('unit', 0),
            'steam' => in_array($request->queryString('steam'), ['oui', 'non'], true) ? $request->queryString('steam') : '',
            'kit' => in_array($request->queryString('kit'), ['oui', 'non'], true) ? $request->queryString('kit') : '',
        ];
        $rows = [];
        $units = [];
        try {
            $directory = (new \App\Repositories\UserRepository())->listPersonnelDirectoryRich($tenantId, $filters['q'], 300);
            $stats = (new \App\Services\Sse\MemberIdentityPapersService())->roster($tenantId, array_map(static fn (array $r): int => (int) $r['id'], $directory), $rights['truth']);
            foreach ($directory as $r) {
                $uid = (int) $r['id'];
                $unitId = (int) ($r['primary_unit_id'] ?? 0);
                if ($unitId > 0) {
                    $units[$unitId] = (string) ($r['unit_name'] ?? ('#' . $unitId));
                }
                $st = $stats[$uid] ?? ['steam_linked' => false, 'issued' => 0, 'kit' => false, 'last_check' => null];
                if (($filters['unit'] > 0 && $unitId !== $filters['unit'])
                    || ($filters['steam'] !== '' && $st['steam_linked'] !== ($filters['steam'] === 'oui'))
                    || ($filters['kit'] !== '' && $st['kit'] !== ($filters['kit'] === 'oui'))) {
                    continue;
                }
                $rows[] = [
                    'id' => $uid,
                    'name' => (string) ($r['display_name'] ?? ''),
                    'callsign' => (string) ($r['callsign'] ?? ''),
                    'unit' => (string) ($r['unit_name'] ?? ''),
                ] + $st;
            }
        } catch (\Throwable $e) {
            error_log('SSE › papiers des membres indisponibles : ' . $e->getMessage());
        }
        asort($units);

        return $this->view('atak.sse.papers.members', [
            'title' => 'Papiers des membres',
            'members' => $rows,
            'units' => $units,
            'filters' => $filters,
            'papersTab' => 'membres',
        ]);
    }

    /**
     * Papiers d'un membre : exactement l'onglet « Identité & papiers » de la fiche membre (même vue partielle, même
     * service), sans le reste de la fiche (ni état civil RH, ni documents RH, ni autres onglets).
     */
    public function memberShow(Request $request, array $params = []): Response
    {
        $rights = $this->memberPapersRights();
        if ($rights === null) {
            return $this->denied(url('atak/sse'));
        }
        $tenantId = $this->tenantId();
        $member = (new \App\Repositories\UserRepository())->findById((int) ($params['id'] ?? 0), $tenantId);
        $section = $member !== null ? \App\Controllers\Admin\MemberIdentityPapersController::sectionFor($tenantId, $member, true) : null;
        if ($member === null || $section === null) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url(self::BASE . '/membres'));
        }

        $coverPanel = null;
        if (!empty($rights['manage'])) {
            try {
                $coverPanel = (new \App\Services\Sse\SseCoverExportService())->panel($tenantId, (int) $member['id'], true, (int) $member['id'] === (int) $this->userId());
            } catch (\Throwable $e) {
                error_log('Papiers membre : couvertures générées indisponibles : ' . $e->getMessage());
            }
        }

        return $this->view('atak.sse.papers.member', [
            'title' => 'Papiers — ' . (string) ($member['display_name'] ?? ''),
            'memberName' => (string) ($member['display_name'] ?? ''),
            'memberId' => (int) $member['id'],
            'memberPapers' => $section,
            // Lien vers la fiche membre complète seulement avec un droit Effectifs.
            'memberFileUrl' => \App\Support\EffectifsLmsAccess::allows(\App\Core\Gate::getInstance()) ? effectifs_workspace_url('membres/' . (int) $member['id']) : null,
            'csrfToken' => Csrf::token(),
            'papersTab' => 'membres',
            'coverPanel' => $coverPanel,
        ]);
    }

    /**
     * Rendu d'une page du portail SSE pour un autre contrôleur (formulaire de papier d'un membre).
     *
     * @param array<string, mixed> $data
     */
    public function page(string $view, array $data): Response
    {
        return $this->view($view, $data);
    }

    /** @return array{view: bool, manage: bool, truth: bool, cover: bool, sse: bool}|null null = accès refusé */
    private function memberPapersRights(): ?array
    {
        if ($this->access->isGuest() || $this->userId() === null) {
            return null;
        }
        $rights = \App\Controllers\Admin\MemberIdentityPapersController::currentRights($this->clearance);

        return $rights['sse'] ? $rights : null;
    }

    // --------------------------------------------------------------- Contrôles

    public function checksIndex(Request $request, array $params = []): Response
    {
        $filters = [
            'verdict' => $request->queryString('verdict'),
            'q' => $request->queryString('q'),
        ];

        return $this->view('atak.sse.papers.checks', [
            'title' => 'Contrôles d’identité',
            'checks' => $this->checks->list($this->tenantId(), $filters),
            'filters' => $filters,
            'papersTab' => 'controles',
            'autoTransmit' => (new SseIdSettingsRepository())->autoTransmit($this->tenantId()),
            'canManageSettings' => $this->canManage(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Envoi automatique des contrôles vers Athena, pour toute la communauté (reçu en jeu avec le paquet). */
    public function checksSettings(Request $request, array $params = []): Response
    {
        if (!$this->canManage() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied(url(self::BASE . '/controles'));
        }
        $tenantId = $this->tenantId();
        $enabled = $request->inputString('auto_transmit') === '1';
        (new SseIdSettingsRepository())->setAutoTransmit($tenantId, $enabled);
        $this->audit($tenantId, 'id_settings.auto_transmit', $tenantId, ['auto_transmit' => $enabled]);
        Session::flash('success', 'Réglage enregistré. Il s’applique en jeu au prochain chargement du paquet.');

        return Response::redirect(url(self::BASE . '/controles'));
    }

    /**
     * Tableau de bord identité d'une opération (COMSPEC IDENTITY, Lot 2) : contrôles, détections, faux détectés (et
     * faux réellement présentés, habilitation Confidentiel), recherchés interpellés, contrôleurs, dernières alertes et
     * brouillons FRM nés des contrôles, à valider. Filtres : carte (map) et opération (op ; « - » = sans opération).
     */
    public function dashboard(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $insights = new SseIdCheckInsightService(null, $this->checks);
        $mapId = max(0, (int) $request->queryString('map'));
        $opRaw = trim($request->queryString('op'));
        $opKey = $opRaw === '' ? null : ($opRaw === '-' ? '' : mb_substr($opRaw, 0, 64));
        $truth = $this->canSeeTruth();

        return $this->view('atak.sse.papers.dashboard', [
            'title' => 'Tableau de bord identité',
            'board' => $insights->dashboard($tenantId, $mapId > 0 ? $mapId : null, $opKey, $truth),
            'operations' => $insights->operations($tenantId),
            'filters' => ['map' => $mapId, 'op' => $opRaw],
            'scopeLabel' => $opKey === null ? ($mapId > 0 ? $insights->mapLabel($mapId) : 'Toutes les opérations') : (($opKey === '' ? 'Hors opération' : $opKey) . ($mapId > 0 ? ' · ' . $insights->mapLabel($mapId) : '')),
            'papersTab' => 'tableau',
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function checkShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $check = $this->checks->find((int) ($params['id'] ?? 0), $tenantId);
        if ($check === null) {
            Session::flash('error', 'Contrôle introuvable.');

            return Response::redirect(url(self::BASE . '/controles'));
        }

        try {
            $check = (new SseIdCheckInsightService(null, $this->checks))->enrich($tenantId, [$check])[0];
        } catch (\Throwable) {
            // Fiche et photos indisponibles : le contrôle s'affiche seul.
        }

        return $this->view('atak.sse.papers.check_show', [
            'title' => $check['ref'],
            'check' => $check,
            'doc' => $check['document_id'] !== null ? $this->documents->find((int) $check['document_id'], $tenantId) : null,
            'person' => $check['person_id'] !== null ? $this->persons->findById((int) $check['person_id'], $tenantId) : null,
            'papersTab' => 'controles',
        ]);
    }

    /**
     * Bloc « Documents et contrôles » de la fiche personne SSE (identités rattachées, papiers, contrôles reçus).
     *
     * @return array{identities: list<array<string, mixed>>, documents: list<array<string, mixed>>, checks: list<array<string, mixed>>, dossier?: array{total: int, photos: int, groups: list<array<string, mixed>>}, suggestions: list<array{type: string, id: int, name: string, ref: string}>, truth: bool}
     */
    public static function personPapers(int $tenantId, int $personId): array
    {
        $empty = ['identities' => [], 'documents' => [], 'checks' => [], 'suggestions' => [], 'truth' => false];
        if ($tenantId < 1 || $personId < 1) {
            return $empty;
        }
        try {
            $truth = (new SseClearanceService())->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
            $identities = (new SseIdentityRepository())->listForPerson($tenantId, $personId);
            if (!$truth) {
                // Alias, couvertures et faux rattachés à la personne : vérité de jeu, réservée à l'habilitation Confidentiel.
                $identities = array_values(array_filter($identities, static fn (array $i): bool => !in_array($i['kind'], MemberIdentityPapersService::SECRET, true)));
            }
            $documents = (new SseIdDocumentRepository())->list($tenantId, ['holder_person_id' => $personId, 'limit' => 50]);
            if (!$truth) {
                $documents = array_values(array_filter($documents, static fn (array $d): bool => !MemberIdentityPapersService::docIsSecret($d)));
            }

            $documentIds = array_map(static fn (array $d): int => (int) $d['id'], $documents);
            // Sans habilitation : ni contrôle d'un papier masqué, ni nom présenté qui trahirait une couverture. Une fiche
            // sans aucun nom connu n'a rien à trahir : ses contrôles restent lisibles.
            $person = (new SsePersonRepository())->findById($personId, $tenantId) ?? [];
            $knownNames = array_merge($identities, trim((string) ($person['last_name'] ?? '')) !== '' ? [$person] : []);
            $keep = static function (array $checks) use ($truth, $documentIds, $knownNames): array {
                if ($truth) {
                    return $checks;
                }
                $checks = array_values(array_filter($checks, static fn (array $c): bool => (int) ($c['document_id'] ?? 0) === 0 || in_array((int) $c['document_id'], $documentIds, true)));

                return $knownNames === [] ? $checks : MemberIdentityPapersService::withoutForeignNames($checks, $knownNames);
            };
            try {
                $dossier = (new SseIdCheckInsightService())->personDossier($tenantId, $personId, $documentIds, 200, $keep);
            } catch (\Throwable) {
                $dossier = ['total' => 0, 'photos' => 0, 'groups' => []];
            }

            return [
                'identities' => $identities,
                'documents' => $documents,
                'checks' => $keep((new SseIdCheckRepository())->list($tenantId, ['person_id' => $personId, 'limit' => 30])),
                'dossier' => $dossier,
                // Le recoupement relève de la vérité de jeu : il relierait une fiche à une identité d'emprunt.
                'suggestions' => $truth ? (new SseIdSubjectMatchService())->suggestionsForPerson($tenantId, $personId) : [],
                'truth' => $truth,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    // ----------------------------------------------------------------- Outils

    /**
     * @param list<array<string, mixed>> $countries
     * @return array<string, mixed>|null
     */
    private function pickCountry(array $countries, string $code): ?array
    {
        foreach ($countries as $c) {
            if ($c['code'] === $code) {
                return $c;
            }
        }

        return $countries[0] ?? null;
    }

    /** @param array<string, mixed> $country */
    private function freeNumber(int $tenantId, array $country, string $type, int $seed): string
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

    /** @return list<array{id: int, label: string}> */
    private function maps(): array
    {
        try {
            $out = [];
            foreach ((new AtakMapRepository())->getAll() as $m) {
                $out[] = ['id' => (int) $m['id'], 'label' => (string) ($m['name'] ?? $m['display_name'] ?? $m['slug'] ?? ('#' . $m['id']))];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): Response
    {
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = sse_ui_theme();
        $data['sseThemeOptions'] = sse_ui_theme_options();
        $data['canManage'] = $this->canManage();
        $data['canGrant'] = !$this->access->isGuest() && function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
        $data['canSeeTruth'] = $this->canSeeTruth();
        $data['activeNav'] = 'papiers';
        try {
            $data['canMemberPapers'] = $this->memberPapersRights() !== null;
        } catch (\Throwable) {
            $data['canMemberPapers'] = false;
        }
        try {
            // Atelier, kits types, préparation de mission (lot 6) : gestion SSE ou habilitation Encadrement.
            $workshopRights = SseIdWorkshopController::rightsFor($this->access->isGuest() || $this->userId() === null, $this->canManage(), $this->clearance->allows(SseCaseRepository::CLASS_COMMAND), false);
            $data['canWorkshop'] ??= $workshopRights['view'];
            // Images du titulaire (photo, signature) : mêmes droits d'écriture que l'atelier.
            $data['canEditImages'] ??= $workshopRights['manage'];
        } catch (\Throwable) {
            $data['canWorkshop'] = false;
            $data['canEditImages'] = false;
        }

        return Response::view($view, $data);
    }

    private function denied(?string $back = null): Response
    {
        Session::flash('error', 'Action non autorisée.');

        return Response::redirect($back ?? url(self::BASE));
    }

    private function tenantId(): int
    {
        $tid = $this->access->tenantId();

        return $tid > 0 ? $tid : (int) Session::get('tenant_id');
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    private function canManage(): bool
    {
        if ($this->access->isGuest()) {
            return false;
        }

        return function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));
    }

    private function canSeeTruth(): bool
    {
        return $this->clearance->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
    }
}
