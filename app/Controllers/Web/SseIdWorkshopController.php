<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitTemplateRepository;
use App\Repositories\SseIdOperationRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseIdentityGeneratorService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseIdKitAssignmentService;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Services\Sse\SseIdTransferService;
use App\Services\Sse\SseIdWorkshopService;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdWorkshopCatalog;

/**
 * Portail SSE — RENS › Identités & documents (COMSPEC IDENTITY, Lot 6) : atelier de documents (aperçu recto, verso et
 * pages du passeport, MRZ calculée en direct, export image), kits documentaires types, préparation de mission en
 * masse (population, recherchés et HVT par camp), briefs de couverture, import et export.
 *
 * Droits (rightsFor) : voir = gestion SSE ou habilitation Encadrement ; créer, générer, affecter, émettre = gestion SSE
 * et habilitation Encadrement ; vérité de jeu (nature, origine, faux, couvertures) = habilitation Confidentiel. Jamais
 * en invité. Chaque écriture est journalisée (journal d'audit SSE).
 */
final class SseIdWorkshopController
{
    private const BASE = 'atak/sse/papiers';

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        private ?SseIdKitTemplateRepository $templates = null,
        private ?SseIdOperationRepository $operations = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->templates ??= new SseIdKitTemplateRepository();
        $this->operations ??= new SseIdOperationRepository();
    }

    /**
     * Droits de l'atelier, de la préparation de mission et des kits types.
     *
     * @return array{view: bool, manage: bool, truth: bool}
     */
    public static function rightsFor(bool $guest, bool $sseManage, bool $command, bool $confidential): array
    {
        if ($guest) {
            return ['view' => false, 'manage' => false, 'truth' => false];
        }

        return [
            'view' => $sseManage || $command,
            'manage' => $sseManage && $command,
            'truth' => $confidential,
        ];
    }

    // ---------------------------------------------------------------- Atelier

    public function workshopIndex(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $docs = $this->documents->list($tenantId, ['workflow' => $request->queryString('workflow'), 'q' => $request->queryString('q'), 'limit' => 100]);
        if (!$r['truth']) {
            $docs = array_values(array_filter($docs, static fn (array $d): bool => !\App\Services\Sse\MemberIdentityPapersService::docIsSecret($d)));
        }

        return $this->page('atak.sse.papers.workshop_index', [
            'title' => 'Atelier de documents',
            'docs' => $docs,
            'filters' => ['workflow' => $request->queryString('workflow'), 'q' => $request->queryString('q')],
            'rights' => $r,
            'papersTab' => 'atelier',
        ]);
    }

    public function workshopEditor(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $countries = $this->countries->listForTenant($tenantId);
        $id = (int) ($params['id'] ?? 0);
        $doc = $id > 0 ? $this->documents->find($id, $tenantId) : null;
        if ($id > 0 && ($doc === null || (!$r['truth'] && \App\Services\Sse\MemberIdentityPapersService::docIsSecret($doc)))) {
            Session::flash('error', 'Papier introuvable.');

            return Response::redirect(url(self::BASE . '/atelier'));
        }
        if ($doc === null && !$r['manage']) {
            return $this->denied(url(self::BASE . '/atelier'));
        }
        $identity = $doc !== null
            ? ($this->identities->find((int) $doc['identity_id'], $tenantId) ?? [])
            : (($iid = (int) $request->query('identity_id', 0)) > 0 ? ($this->identities->find($iid, $tenantId) ?? []) : []);
        if (!$r['truth'] && in_array($identity['kind'] ?? '', \App\Services\Sse\MemberIdentityPapersService::SECRET, true)) {
            $identity = [];
        }
        $countryCode = (string) ($doc['country_code'] ?? strtoupper($request->queryString('country', (string) ($identity['nationality'] ?? ''))));
        $country = null;
        foreach ($countries as $c) {
            if ($c['code'] === $countryCode) {
                $country = $c;
            }
        }
        $country ??= $doc !== null ? $this->countries->findByCode($tenantId, $countryCode) : ($countries[0] ?? null);
        $workshop = new SseIdWorkshopService($this->identities, $this->documents, $this->countries);
        if ($doc === null) {
            // Nouveau papier : proposition (identité générée si aucune n'est choisie, numéro libre, dates du pays).
            $type = SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $request->queryString('doc_type'), (string) ($country['doc_types'][0] ?? 'id_card'));
            if ($identity === [] && $country !== null) {
                $identity = (new SseIdentityGeneratorService())->generate($country) + ['kind' => 'civil', 'photo_path' => null, 'alias' => ''];
            }
            $doc = [
                'id' => 0, 'ref' => '', 'doc_type' => $type, 'country_code' => (string) ($country['code'] ?? ''), 'workflow' => 'draft',
                'doc_number' => $country !== null ? $workshop->freeNumber($tenantId, $country, $type, (int) ($identity['seed'] ?? 0) ?: SseIdentityGeneratorService::randomSeed()) : '',
                'issued_on' => gmdate('Y-m-d'),
                'expires_on' => gmdate('Y-m-d', (int) strtotime('+' . ($country !== null ? SseIdCountryRepository::validityFor($country, $type) : 10) . ' years')),
                'authority' => (string) ($country['authority'] ?? ''), 'layout' => 'modern', 'print' => [],
                'chip_status' => 'ok', 'st_integrity' => 'intact', 'st_validity' => 'valid', 'st_report' => 'none', 'st_attribution' => 'holder',
                'st_origin' => 'official', 'forgery_kind' => null, 'forgery_quality' => null, 'context_id' => null, 'op_key' => null, 'notes' => null,
                'duplicated_from_id' => null,
            ];
        }
        $countryJs = [];
        foreach ($countries as $c) {
            $countryJs[$c['code']] = [
                'name' => $c['name'], 'authority' => $c['authority'], 'mrz' => $c['mrz_code'], 'format' => $c['number_format'],
                'types' => $c['doc_types'], 'validity' => $c['validity'],
                'colors' => array_values(array_filter([$c['color_primary'], $c['color_secondary']])),
                'flag' => !empty($c['real_sim']) ? \App\Support\SseIdRealCountryCatalog::flagDataUri((string) $c['code'])
                    : ($c['flag_path'] !== null ? (string) url($c['flag_path']) : null),
                'zone' => (string) ($c['zone'] ?? ''),
            ];
        }
        $editable = $r['manage'] && $doc['workflow'] === 'draft';

        return $this->page('atak.sse.papers.workshop_editor', [
            'title' => $doc['id'] > 0 ? 'Atelier — ' . $doc['ref'] : 'Atelier — nouveau papier',
            'doc' => $doc,
            'identity' => $identity,
            'identityLocked' => ($identity['id'] ?? 0) > 0 && $workshop->identityLocked($tenantId, (int) $identity['id'], (int) $doc['id']),
            'country' => $country,
            'countries' => $countries,
            'countryJs' => $countryJs,
            'preview' => SseIdWorkshopService::previewModel($doc, $identity, $country),
            'editable' => $editable,
            'transitions' => $doc['id'] > 0 && $r['manage'] ? (SseIdentityCatalog::TRANSITIONS[$doc['workflow']] ?? []) : [],
            'duplicates' => $doc['id'] > 0 ? $this->documents->duplicatesOf((int) $doc['id'], $tenantId) : [],
            'maps' => $this->maps(),
            'personPhotos' => SseIdWorkshopService::personPhotos($tenantId, $identity['person_id'] ?? null),
            'rights' => $r,
            'papersTab' => 'atelier',
        ]);
    }

    /** GET …/atelier/mrz : bande MRZ calculée par SseMrzService pour l'aperçu en direct. */
    public function workshopMrz(Request $request, array $params = []): Response
    {
        if (!$this->rights()['view']) {
            return Response::json(['error' => 'forbidden'], 403);
        }
        $q = [];
        foreach (['doc_type', 'country', 'number', 'last', 'first', 'dob', 'sex', 'nat', 'expires'] as $k) {
            $q[$k] = mb_substr($request->queryString($k), 0, 120);
        }

        return Response::json((new SseIdWorkshopService($this->identities, $this->documents, $this->countries))->mrzPreview($this->tenantId(), $q));
    }

    public function workshopStore(Request $request, array $params = []): Response
    {
        return $this->workshopSave($request, 0);
    }

    public function workshopUpdate(Request $request, array $params = []): Response
    {
        return $this->workshopSave($request, (int) ($params['id'] ?? 0));
    }

    private function workshopSave(Request $request, int $id): Response
    {
        $r = $this->rights();
        $back = $id > 0 ? url(self::BASE . '/atelier/' . $id) : url(self::BASE . '/atelier/nouveau');
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $doc = $id > 0 ? $this->documents->find($id, $tenantId) : null;
        if ($id > 0 && ($doc === null || (!$r['truth'] && \App\Services\Sse\MemberIdentityPapersService::docIsSecret($doc)))) {
            return $this->denied(url(self::BASE . '/atelier'));
        }
        $identityId = $doc !== null ? (int) $doc['identity_id'] : (int) $request->input('identity_id', 0);
        $identity = $identityId > 0 ? $this->identities->find($identityId, $tenantId) : null;
        if ($identity !== null && !$r['truth'] && in_array($identity['kind'], \App\Services\Sse\MemberIdentityPapersService::SECRET, true)) {
            return $this->denied(url(self::BASE . '/atelier'));
        }
        $images = new SseIdImageService();
        $photo = $images->storeUpload($_FILES['photo'] ?? null, 'photo', $tenantId);
        if (!$photo['ok'] && empty($photo['none'])) {
            Session::flash('error', 'Photo : ' . ($photo['error'] ?? 'image refusée.'));

            return Response::redirect($back);
        }
        if (!$photo['ok'] && ($pickId = (int) $request->input('photo_existing', 0)) > 0) {
            $photo = SseIdWorkshopService::copyPersonPhoto($tenantId, $identity['person_id'] ?? null, $pickId, $images);
            if (!$photo['ok']) {
                Session::flash('error', 'Photo : ' . ($photo['error'] ?? 'image refusée.'));

                return Response::redirect($back);
            }
        }
        $signature = $images->storeUpload($_FILES['signature'] ?? null, 'signature', $tenantId);
        $background = $images->storeUpload($_FILES['background'] ?? null, 'background', $tenantId);
        foreach (['Signature' => $signature, 'Image de fond' => $background] as $label => $res) {
            if (!$res['ok'] && empty($res['none'])) {
                Session::flash('error', $label . ' : ' . ($res['error'] ?? 'image refusée.'));

                return Response::redirect($back);
            }
        }
        $input = [];
        foreach ([
            'last_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality', 'profession', 'alias', 'kind', 'seed',
            'doc_type', 'country_code', 'doc_number', 'issued_on', 'expires_on', 'authority', 'op_key', 'notes', 'layout',
            'chip_status', 'st_integrity', 'st_validity', 'st_report', 'st_attribution', 'photo_remove',
        ] as $k) {
            $input[$k] = $request->inputString($k);
        }
        if ($r['truth']) {
            foreach (['st_origin', 'forgery_kind', 'forgery_quality'] as $k) {
                $input[$k] = $request->inputString($k);
            }
        }
        $input['context_id'] = (int) $request->input('context_id', 0);
        $print = $request->input('print', []);
        $input['print'] = is_array($print) ? $print : [];
        $res = (new SseIdWorkshopService($this->identities, $this->documents, $this->countries))
            ->save($tenantId, $input, $identity, $doc, $r['truth'], $this->userId(), $photo['path'] ?? null);
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Enregistrement impossible.'));

            return Response::redirect($back);
        }
        $saved = $this->documents->find((int) $res['id'], $tenantId);
        if ($saved !== null) {
            $changed = [];
            if (isset($signature['path']) || $request->inputString('signature_remove') === '1') {
                $this->identities->setImages((int) $saved['identity_id'], $tenantId, ['signature_path' => $signature['path'] ?? null]);
                $changed[] = 'signature';
            }
            if (isset($background['path']) || $request->inputString('background_remove') === '1') {
                $this->documents->setBackground((int) $saved['id'], $tenantId, $background['path'] ?? null);
                $changed[] = 'background';
            }
            if (isset($photo['path'])) {
                $changed[] = 'photo';
            }
            if ($changed !== []) {
                SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_images.update', 'sse_id_document', (int) $saved['id'], ['images' => $changed]);
            }
        }
        Session::flash('success', $doc === null ? 'Papier créé en brouillon. Validez-le, puis émettez-le pour qu’il parte en jeu.' : 'Papier enregistré.');

        return Response::redirect(url(self::BASE . '/atelier/' . (int) $res['id']));
    }

    public function workshopCycle(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/atelier/' . $id);
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $doc = $this->documents->find($id, $tenantId);
        if ($doc === null || (!$r['truth'] && \App\Services\Sse\MemberIdentityPapersService::docIsSecret($doc))) {
            return $this->denied(url(self::BASE . '/atelier'));
        }
        $to = SseIdentityCatalog::pick(SseIdentityCatalog::WORKFLOWS, $request->inputString('to'), '');
        $reason = trim($request->inputString('reason'));
        if ($to === 'revoked' && $reason === '') {
            Session::flash('error', 'Indiquez le motif de la révocation.');

            return Response::redirect($back);
        }
        if ($to === '' || !$this->documents->transition($id, $tenantId, $to, $this->userId(), $reason)) {
            Session::flash('error', 'Cette étape n’est pas possible depuis l’état actuel du papier.');

            return Response::redirect($back);
        }
        SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_workshop.' . $to, 'sse_id_document', $id, $reason !== '' ? ['reason' => mb_substr($reason, 0, 255)] : []);
        Session::flash('success', match ($to) {
            'validated' => 'Papier validé. Il partira en jeu une fois émis.',
            'issued' => 'Papier émis : il sera chargé par le serveur de jeu à la prochaine mise à jour.',
            'revoked' => 'Papier révoqué : les contrôles en ligne le signalent désormais comme annulé. Dupliquez-le pour le remplacer.',
            default => 'Papier repassé en brouillon.',
        });

        return Response::redirect($back);
    }

    public function workshopDuplicate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/atelier/' . $id);
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $doc = $this->documents->find($id, $tenantId);
        if ($doc === null || (!$r['truth'] && \App\Services\Sse\MemberIdentityPapersService::docIsSecret($doc))) {
            return $this->denied(url(self::BASE . '/atelier'));
        }
        $newId = (new SseIdWorkshopService($this->identities, $this->documents, $this->countries))->duplicate($tenantId, $doc, $this->userId());
        if ($newId === null) {
            Session::flash('error', 'Le pays de ce papier est introuvable : copie impossible.');

            return Response::redirect($back);
        }
        Session::flash('success', 'Copie créée en brouillon avec un nouveau numéro et de nouvelles dates.');

        return Response::redirect(url(self::BASE . '/atelier/' . $newId));
    }

    /**
     * POST …/identites/{id}/images : photo du titulaire (téléversée, ou reprise d'une photo de la personne SSE) et
     * signature, depuis la fiche de l'identité ou le spécimen d'un papier. Mêmes droits que l'atelier (gestion SSE et
     * habilitation Encadrement), jamais en invité ; images réencodées sans métadonnées ; journalisé.
     */
    public function identityImages(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();
        $docId = (int) $request->input('document_id', 0);
        $doc = $docId > 0 ? $this->documents->find($docId, $tenantId) : null;
        $back = $doc !== null && (int) $doc['identity_id'] === $id ? url(self::BASE . '/documents/' . $docId) : url(self::BASE . '/identites/' . $id);
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $identity = $this->identities->find($id, $tenantId);
        if ($identity === null || (!$r['truth'] && in_array($identity['kind'], \App\Services\Sse\MemberIdentityPapersService::SECRET, true))) {
            return $this->denied(url(self::BASE . '/identites'));
        }
        $images = new SseIdImageService();
        $set = [];
        $photo = $images->storeUpload($_FILES['photo'] ?? null, 'photo', $tenantId);
        if (!$photo['ok'] && empty($photo['none'])) {
            Session::flash('error', 'Photo : ' . ($photo['error'] ?? 'image refusée.'));

            return Response::redirect($back);
        }
        if (!$photo['ok'] && ($pickId = (int) $request->input('photo_existing', 0)) > 0) {
            $photo = SseIdWorkshopService::copyPersonPhoto($tenantId, $identity['person_id'], $pickId, $images);
            if (!$photo['ok']) {
                Session::flash('error', 'Photo : ' . ($photo['error'] ?? 'image refusée.'));

                return Response::redirect($back);
            }
        }
        if (isset($photo['path']) || $request->inputString('photo_remove') === '1') {
            $set['photo_path'] = $photo['path'] ?? null;
        }
        $signature = $images->storeUpload($_FILES['signature'] ?? null, 'signature', $tenantId);
        if (!$signature['ok'] && empty($signature['none'])) {
            Session::flash('error', 'Signature : ' . ($signature['error'] ?? 'image refusée.'));

            return Response::redirect($back);
        }
        if (isset($signature['path']) || $request->inputString('signature_remove') === '1') {
            $set['signature_path'] = $signature['path'] ?? null;
        }
        if ($set === []) {
            Session::flash('error', 'Choisissez une image à envoyer ou à retirer.');

            return Response::redirect($back);
        }
        $this->identities->setImages($id, $tenantId, $set);
        SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_images.update', 'sse_identity', $id, ['images' => array_keys($set)]);
        Session::flash('success', 'Images du titulaire enregistrées. Elles apparaissent sur l’aperçu de chaque papier, sous le filigrane SIMULATION.');

        return Response::redirect($back);
    }

    // -------------------------------------------------------------- Kits types

    public function kitTemplates(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }

        return $this->page('atak.sse.papers.kit_templates', [
            'title' => 'Kits documentaires types',
            'templates' => $this->templates->list($this->tenantId(), true),
            'rights' => $r,
            'papersTab' => 'kits',
        ]);
    }

    public function kitTemplateForm(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $template = $id > 0 ? $this->templates->find($id, $tenantId) : null;
        if ($id > 0 && $template === null) {
            Session::flash('error', 'Kit type introuvable.');

            return Response::redirect(url(self::BASE . '/kits-types'));
        }
        if ($template === null) {
            if (!$r['manage']) {
                return $this->denied(url(self::BASE . '/kits-types'));
            }
            $preset = $request->queryString('modele');
            $base = SseIdWorkshopCatalog::KIT_PRESETS[$preset] ?? ['name' => '', 'doc_types' => ['id_card'], 'identity_mode' => 'primary'];
            $template = SseIdKitTemplateRepository::normalize($base + ['category' => isset(SseIdWorkshopCatalog::KIT_PRESETS[$preset]) ? $preset : 'other']) + ['id' => 0, 'ref' => '', 'archived' => false];
        }
        $members = [];
        if ($template['id'] > 0 && $r['manage']) {
            try {
                foreach ((new \App\Repositories\UserRepository())->listPersonnelDirectoryRich($tenantId, '', 300) as $u) {
                    $members[] = ['id' => (int) $u['id'], 'name' => (string) ($u['display_name'] ?? ''), 'callsign' => (string) ($u['callsign'] ?? ''), 'unit' => (string) ($u['unit_name'] ?? '')];
                }
            } catch (\Throwable $e) {
                error_log('Kits types : membres indisponibles : ' . $e->getMessage());
            }
        }

        return $this->page('atak.sse.papers.kit_template_form', [
            'title' => $template['id'] > 0 ? 'Kit type — ' . $template['name'] : 'Nouveau kit type',
            'template' => $template,
            'countries' => $this->countries->listForTenant($tenantId),
            'members' => $members,
            'maps' => $this->maps(),
            'rights' => $r,
            'papersTab' => 'kits',
        ]);
    }

    public function kitTemplateStore(Request $request, array $params = []): Response
    {
        return $this->kitTemplateSave($request, 0);
    }

    public function kitTemplateUpdate(Request $request, array $params = []): Response
    {
        return $this->kitTemplateSave($request, (int) ($params['id'] ?? 0));
    }

    private function kitTemplateSave(Request $request, int $id): Response
    {
        $r = $this->rights();
        $list = url(self::BASE . '/kits-types');
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($list);
        }
        $tenantId = $this->tenantId();
        if ($id > 0 && $this->templates->find($id, $tenantId) === null) {
            return $this->denied($list);
        }
        $data = [];
        foreach (['name', 'category', 'description', 'country_code', 'identity_mode', 'profession', 'layout', 'phone', 'address', 'biography', 'legend', 'contacts', 'instructions'] as $k) {
            $data[$k] = $request->inputString($k);
        }
        $types = $request->input('doc_types', []);
        $data['doc_types'] = is_array($types) ? $types : [];
        $print = $request->input('print', []);
        $data['print'] = is_array($print) ? $print : [];
        if (trim($data['name']) === '') {
            Session::flash('error', 'Donnez un nom au kit type.');

            return Response::redirect($id > 0 ? url(self::BASE . '/kits-types/' . $id) : url(self::BASE . '/kits-types/nouveau'));
        }
        if ($data['identity_mode'] === 'cover' && !$r['truth']) {
            $data['identity_mode'] = 'alias';
        }
        if ($id > 0) {
            $this->templates->update($id, $tenantId, $data);
            SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_kit_template.update', 'sse_id_kit_template', $id, ['name' => $data['name']]);
        } else {
            $id = $this->templates->create($tenantId, $data, $this->userId());
            SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_kit_template.create', 'sse_id_kit_template', $id, ['name' => $data['name']]);
        }
        Session::flash('success', 'Kit type enregistré. Affectez-le à des membres pour générer leurs papiers.');

        return Response::redirect(url(self::BASE . '/kits-types/' . $id));
    }

    public function kitTemplateArchive(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied(url(self::BASE . '/kits-types'));
        }
        $tenantId = $this->tenantId();
        $archive = $request->inputString('restore') !== '1';
        if ($this->templates->setArchived($id, $tenantId, $archive)) {
            SseIdMassGeneratorService::audit($tenantId, $this->userId(), $archive ? 'id_kit_template.archive' : 'id_kit_template.restore', 'sse_id_kit_template', $id, []);
            Session::flash('success', $archive ? 'Kit type archivé.' : 'Kit type rétabli.');
        }

        return Response::redirect(url(self::BASE . '/kits-types'));
    }

    public function kitTemplateAssign(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/kits-types/' . $id);
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $template = $this->templates->find($id, $tenantId);
        if ($template === null || $template['archived']) {
            return $this->denied(url(self::BASE . '/kits-types'));
        }
        $raw = $request->input('user_ids', []);
        $userIds = [];
        $users = new \App\Repositories\UserRepository();
        foreach (is_array($raw) ? array_slice($raw, 0, 100) : [] as $uid) {
            // Membres de la communauté seulement.
            if ((int) $uid > 0 && $users->findById((int) $uid, $tenantId) !== null) {
                $userIds[] = (int) $uid;
            }
        }
        if ($userIds === []) {
            Session::flash('error', 'Choisissez au moins un membre.');

            return Response::redirect($back);
        }
        $res = (new SseIdKitAssignmentService())->assign(
            $tenantId,
            $template,
            $userIds,
            (int) $request->input('context_id', 0) ?: null,
            trim($request->inputString('op_key')) ?: null,
            $request->inputString('issue') === '1',
            $r['truth'],
            $this->userId()
        );
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Affectation impossible.'));
        } else {
            Session::flash('success', $res['members'] . ' membre(s) équipé(s), ' . $res['documents'] . ' papier(s) créé(s), kit de mission et brief enregistrés.'
                . ($res['skipped'] !== [] ? ' ' . count($res['skipped']) . ' membre(s) sans identité principale ignoré(s).' : ''));
        }

        return Response::redirect($back);
    }

    // ------------------------------------------------- Préparation de mission

    public function missionPrep(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $countries = $this->countries->listForTenant($tenantId);
        $byCode = [];
        foreach ($countries as $c) {
            $byCode[$c['code']] = $c;
        }
        $mapId = max(0, (int) $request->query('map', 0));
        $opKey = mb_substr(trim($request->queryString('op')), 0, 64);
        $scopeSet = $request->queryString('map') !== '' || $opKey !== '';
        $preview = null;
        $params = null;
        $seed = 0;
        if ($request->queryString('apercu') === '1') {
            $raw = [
                'label' => $request->queryString('label'),
                'countries' => (array) $request->query('countries', []),
                'doc_types' => (array) $request->query('doc_types', []),
                'language' => $request->queryString('language'),
                'zone' => $request->queryString('zone'),
                'age_min' => $request->queryString('age_min'),
                'age_max' => $request->queryString('age_max'),
                'female_pct' => $request->queryString('female_pct'),
                'visible_sides' => (array) $request->query('visible_sides', []),
                'categories' => (array) $request->query('cat', []),
            ];
            $params = SseIdMassGeneratorService::normalizeParams($raw, array_keys($byCode), $r['truth']);
            $seedRaw = $request->queryString('seed');
            $seed = ctype_digit($seedRaw) ? (int) $seedRaw % (SseIdentityGeneratorService::MAX_SEED + 1) : SseIdentityGeneratorService::randomSeed();
            $profiles = (new SseIdMassGeneratorService())->preview($params, $byCode, $seed);
            $preview = ['profiles' => $profiles, 'summary' => SseIdMassGeneratorService::summary($profiles)];
        }
        $ctx = $mapId > 0 ? $mapId : null;
        $op = $opKey !== '' ? $opKey : null;
        $board = null;
        if ($scopeSet) {
            $wanted = $this->operations->listWanted($tenantId, $ctx, $op);
            if (!$r['truth']) {
                $wanted = array_values(array_filter($wanted, static fn (array $w): bool => !in_array($w['kind'], \App\Services\Sse\MemberIdentityPapersService::SECRET, true)));
            }
            $batches = $this->operations->listBatches($tenantId, $ctx, $op);
            $briefs = $this->operations->listBriefs($tenantId, $ctx, $op);
            $visibility = [];
            foreach (SseIdWorkshopCatalog::SIDES as $side => $label) {
                $visibility[$side] = [
                    'label' => $label,
                    'wanted' => count(array_filter($wanted, static fn (array $w): bool => $w['sides'] === [] || in_array($side, $w['sides'], true))),
                ];
            }
            $population = [];
            foreach ($batches as $b) {
                if (!$b['archived']) {
                    foreach ($this->operations->batchIdentities($tenantId, $b['id'], 300) as $i) {
                        $k = ($i['side'] ?? 'civ') . '|' . ($i['category'] ?? 'civilian');
                        $population[$k] = ($population[$k] ?? 0) + 1;
                    }
                }
            }
            $board = ['wanted' => $wanted, 'batches' => $batches, 'briefs' => $briefs, 'visibility' => $visibility, 'population' => $population];
        }

        return $this->page('atak.sse.papers.mission_prep', [
            'title' => 'Préparation de mission — identités et couvertures',
            'countries' => $countries,
            'maps' => $this->maps(),
            'scope' => ['map' => $mapId, 'op' => $opKey, 'set' => $scopeSet],
            'scopes' => $this->operations->scopes($tenantId),
            'params' => $params,
            'seed' => $seed,
            'preview' => $preview,
            'board' => $board,
            'rights' => $r,
            'papersTab' => 'preparation',
        ]);
    }

    public function missionPrepCommit(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied(url(self::BASE . '/preparation'));
        }
        $tenantId = $this->tenantId();
        $codes = array_map(static fn (array $c): string => (string) $c['code'], $this->countries->listForTenant($tenantId));
        $json = json_decode($request->inputString('params_json'), true);
        $params = SseIdMassGeneratorService::normalizeParams(is_array($json) ? $json : [], $codes, $r['truth']);
        $mapId = (int) $request->input('map', 0);
        $opKey = mb_substr(trim($request->inputString('op')), 0, 64);
        $back = url(self::BASE . '/preparation') . '?map=' . $mapId . '&op=' . rawurlencode($opKey);
        if ($request->inputString('confirm') !== '1') {
            Session::flash('error', 'Cochez la confirmation avant d’enregistrer la population.');

            return Response::redirect($back);
        }
        $seed = max(0, (int) $request->input('seed', 0));
        $res = (new SseIdMassGeneratorService())->commit($tenantId, $mapId > 0 ? $mapId : null, $opKey !== '' ? $opKey : null, $params, $seed, $this->userId());
        if (!$res['ok']) {
            Session::flash('error', (string) ($res['error'] ?? 'Génération impossible.'));
        } else {
            Session::flash('success', $res['identities'] . ' identité(s) et ' . $res['documents'] . ' papier(s) émis enregistrés, ' . $res['wanted'] . ' recherché(s) ou HVT ajouté(s) à l’opération. Ils partent au serveur de jeu avec le pack.');
        }

        return Response::redirect($back);
    }

    public function batchArchive(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $id = (int) ($params['id'] ?? 0);
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied(url(self::BASE . '/preparation'));
        }
        $tenantId = $this->tenantId();
        $batch = $this->operations->findBatch($id, $tenantId);
        if ($batch === null) {
            return $this->denied(url(self::BASE . '/preparation'));
        }
        $n = (new SseIdMassGeneratorService())->archive($tenantId, $id, $this->userId());
        Session::flash('success', 'Lot retiré : ' . $n . ' papier(s) révoqué(s), ses recherchés retirés de l’opération.');

        return Response::redirect(url(self::BASE . '/preparation') . '?map=' . (int) ($batch['context_id'] ?? 0) . '&op=' . rawurlencode((string) ($batch['op_key'] ?? '')));
    }

    public function wantedStore(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $mapId = (int) $request->input('map', 0);
        $opKey = mb_substr(trim($request->inputString('op')), 0, 64);
        $back = url(self::BASE . '/preparation') . '?map=' . $mapId . '&op=' . rawurlencode($opKey);
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $ref = strtoupper(trim($request->inputString('identity_ref')));
        $identityId = (int) preg_replace('/\D/', '', $ref);
        $identity = $identityId > 0 ? $this->identities->find($identityId, $tenantId) : null;
        if ($identity === null || (!$r['truth'] && in_array($identity['kind'], \App\Services\Sse\MemberIdentityPapersService::SECRET, true))) {
            Session::flash('error', 'Identité introuvable : indiquez sa référence IDT (fiche Identités).');

            return Response::redirect($back);
        }
        $sides = $request->input('sides', []);
        $id = $this->operations->addWanted($tenantId, $mapId > 0 ? $mapId : null, $opKey !== '' ? $opKey : null, $identityId, $request->inputString('level'), $request->inputString('alias'), is_array($sides) ? $sides : [], $request->inputString('notes'), null, $this->userId());
        SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_op_wanted.create', 'sse_id_op_wanted', $id, ['identity' => $identityId, 'map' => $mapId, 'op' => $opKey]);
        Session::flash('success', 'Ajouté aux recherchés de l’opération.');

        return Response::redirect($back);
    }

    public function wantedRemove(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = url(self::BASE . '/preparation') . '?map=' . (int) $request->input('map', 0) . '&op=' . rawurlencode(trim($request->inputString('op')));
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($this->operations->removeWanted($id, $this->tenantId())) {
            SseIdMassGeneratorService::audit($this->tenantId(), $this->userId(), 'id_op_wanted.delete', 'sse_id_op_wanted', $id, []);
            Session::flash('success', 'Retiré des recherchés de l’opération.');
        }

        return Response::redirect($back);
    }

    // ----------------------------------------------------------------- Briefs

    public function briefsIndex(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $members = [];
        try {
            foreach ((new \App\Repositories\UserRepository())->listPersonnelDirectoryRich($tenantId, '', 300) as $u) {
                $members[] = ['id' => (int) $u['id'], 'name' => (string) ($u['display_name'] ?? '')];
            }
        } catch (\Throwable $e) {
            error_log('Briefs : membres indisponibles : ' . $e->getMessage());
        }
        $edit = ($eid = (int) $request->query('modifier', 0)) > 0 ? $this->operations->findBrief($eid, $tenantId) : null;

        return $this->page('atak.sse.papers.briefs', [
            'title' => 'Briefs de couverture',
            'briefs' => $this->operations->listBriefs($tenantId),
            'members' => $members,
            'edit' => $edit,
            'maps' => $this->maps(),
            'rights' => $r,
            'papersTab' => 'briefs',
        ]);
    }

    public function briefStore(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = url(self::BASE . '/briefs');
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $tenantId = $this->tenantId();
        $userId = (int) $request->input('user_id', 0);
        if ($userId < 1 || (new \App\Repositories\UserRepository())->findById($userId, $tenantId) === null) {
            Session::flash('error', 'Choisissez le membre qui reçoit ce brief.');

            return Response::redirect($back);
        }
        $data = [];
        foreach (['title', 'biography', 'legend', 'contacts', 'instructions', 'phone', 'address'] as $k) {
            $data[$k] = $request->inputString($k);
        }
        if (trim($data['title']) === '') {
            $data['title'] = 'Fiche de couverture';
        }
        $id = $this->operations->saveBrief($tenantId, $userId, (int) $request->input('context_id', 0) ?: null, trim($request->inputString('op_key')) ?: null, $data, $this->userId());
        SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_brief.save', 'sse_id_brief', $id, ['user' => $userId]);
        Session::flash('success', 'Brief enregistré : le joueur le lit dans Ma situation › Mes papiers, et le serveur de jeu le reçoit avec le pack.');

        return Response::redirect($back);
    }

    public function briefDelete(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = url(self::BASE . '/briefs');
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $id = (int) ($params['id'] ?? 0);
        if ($this->operations->deleteBrief($id, $this->tenantId())) {
            SseIdMassGeneratorService::audit($this->tenantId(), $this->userId(), 'id_brief.delete', 'sse_id_brief', $id, []);
            Session::flash('success', 'Brief supprimé.');
        }

        return Response::redirect($back);
    }

    // -------------------------------------------------------- Import / export

    public function transferIndex(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }

        return $this->page('atak.sse.papers.transfer', [
            'title' => 'Import et export',
            'columns' => SseIdTransferService::COLUMNS,
            'scopes' => $this->operations->scopes($this->tenantId()),
            'rights' => $r,
            'papersTab' => 'echanges',
        ]);
    }

    public function transferExport(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        if (!$r['view']) {
            return $this->denied(url(self::BASE));
        }
        $tenantId = $this->tenantId();
        $rows = (new SseIdTransferService($this->identities, $this->documents, $this->countries))
            ->rows($tenantId, $r['truth'], ['op' => $request->queryString('op'), 'map' => (int) $request->query('map', 0)]);
        SseIdMassGeneratorService::audit($tenantId, $this->userId(), 'id_transfer.export', 'sse_identity', 0, ['rows' => count($rows), 'truth' => $r['truth']]);
        $json = $request->queryString('format') === 'json';
        $name = 'identites-simulation-' . gmdate('Ymd-Hi') . ($json ? '.json' : '.csv');

        return (new Response())
            ->header('Content-Type', $json ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->header('Cache-Control', 'no-store')
            ->setBody($json ? SseIdTransferService::toJson($rows) : SseIdTransferService::toCsv($rows));
    }

    public function transferImport(Request $request, array $params = []): Response
    {
        $r = $this->rights();
        $back = url(self::BASE . '/echanges');
        if (!$r['manage'] || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->denied($back);
        }
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024 || !is_uploaded_file((string) $file['tmp_name'])) {
            Session::flash('error', 'Envoyez un fichier CSV ou JSON de 2 Mo maximum.');

            return Response::redirect($back);
        }
        $rows = SseIdTransferService::parse((string) file_get_contents((string) $file['tmp_name']), (string) ($file['name'] ?? ''));
        if ($rows === null || $rows === []) {
            Session::flash('error', 'Fichier illisible ou vide.');

            return Response::redirect($back);
        }
        $res = (new SseIdTransferService($this->identities, $this->documents, $this->countries))->import($this->tenantId(), $rows, $r['truth'], $this->userId());
        Session::flash($res['documents'] + $res['identities'] > 0 ? 'success' : 'error', $res['identities'] . ' identité(s) et ' . $res['documents'] . ' papier(s) importés en brouillon, ' . $res['skipped'] . ' ligne(s) écartée(s).'
            . ($res['errors'] !== [] ? ' ' . implode(' ', array_slice($res['errors'], 0, 5)) : ''));

        return Response::redirect($back);
    }

    // ----------------------------------------------------------------- Outils

    /** @return array{view: bool, manage: bool, truth: bool} */
    private function rights(): array
    {
        try {
            $command = $this->clearance->allows(SseCaseRepository::CLASS_COMMAND);
            $confidential = $this->clearance->allows(SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            $command = $confidential = false;
        }
        $sseManage = function_exists('can') && (can('atak.sse.case.manage') || can('atak.sse.grant') || can('admin.access'));

        return self::rightsFor($this->access->isGuest() || $this->userId() === null, $sseManage, $command, $confidential);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return (new SseIdentityRegistryController($this->access, $this->clearance))->page($view, $data + ['csrfToken' => Csrf::token()]);
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
}
