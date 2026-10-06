<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AwardDefinitionRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PersonnelCareerEventRepository;
use App\Repositories\TenantDecorationMotifRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\DecorationMotifStorageService;
use App\Services\Personnel\PersonnelServiceHistoryWriter;
use App\Support\DecorationCatalog;
use App\Support\DecorationImageImport;
use RuntimeException;
use Throwable;

final class AwardReferentielController
{
    public function __construct(
        private AwardDefinitionRepository $definitions,
        private PersonnelAwardRepository $awards,
        private UserRepository $users,
        private PersonnelCareerEventRepository $careerEvents,
        private TenantDecorationMotifRepository $motifs,
        private DecorationMotifStorageService $motifStorage,
        private ?PersonnelServiceHistoryWriter $serviceHistoryWriter = null,
    ) {
        $this->serviceHistoryWriter ??= new PersonnelServiceHistoryWriter(
            new \App\Repositories\PersonnelServiceHistoryRepository()
        );
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.awards_index',
            'title' => 'Décorations',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Décorations et citations',
            'boPageKicker' => 'ORGANISATION · DÉCORATIONS',
            'boPageSubtitle' => 'Ce que l’on reconnaît avoir fait — distinct des qualifications (ce que l’on sait faire).',
            'definitions' => $this->definitions->listForTenant($tenantId, true),
            'imagesReady' => $this->definitions->supportsImages(),
            'maxImportFiles' => DecorationImageImport::MAX_FILES,
            'members' => $this->users->listForTenant($tenantId, null, 'active', null, 200, 0, true),
            'customMotifs' => $this->motifs->listForTenant($tenantId, false),
            'patternChoices' => DecorationCatalog::PATTERN_CHOICES,
            'glyphChoices' => [
                '' => 'Aucun',
                'star' => 'Étoile',
                'cross' => 'Croix',
                'wreath' => 'Couronne',
                'circle' => 'Cercle',
            ],
            'loadDecorationsKit' => true,
            'backOfficePageCss' => ['decorations-kit.css', 'referentiel-decorations.css'],
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $name = trim((string) $request->input('name', ''));
        $code = DecorationImageImport::sanitizeCode((string) $request->input('code', ''));
        if ($code === '' && $name !== '') {
            $code = DecorationImageImport::codeFromName($name);
        }
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        if ($this->definitions->findByCode($tenantId, $code) !== null) {
            Session::flash('error', 'Le code ' . $code . ' est déjà utilisé. Choisissez-en un autre, ou importez l’insigne en lot pour mettre à jour la décoration existante.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        $imagePath = null;
        $upload = $_FILES['image'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = $this->motifStorage->storeUpload($tenantId, $upload, 'insignes');
            } catch (RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(url('back-office/referentiels/decorations'));
            } catch (Throwable) {
                Session::flash('error', 'Impossible d’enregistrer l’insigne.');

                return Response::redirect(url('back-office/referentiels/decorations'));
            }
        }
        $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'branch' => $request->input('branch'),
            'decoration_grade' => $request->input('decoration_grade'),
            'award_criterion' => $request->input('award_criterion'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'image_path' => $imagePath,
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Décoration « ' . $name . ' » créée.');

        return Response::redirect(url('back-office/referentiels/decorations#referentiel'));
    }

    /**
     * Modifie une décoration (texte et insigne). Le code reste celui d’origine s’il est laissé vide.
     */
    public function update(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->definitions->find($tenantId, $id);
        if ($existing === null || !empty($existing['archived_at'])) {
            Session::flash('error', 'Décoration introuvable ou archivée.');

            return Response::redirect(url('back-office/referentiels/decorations#referentiel'));
        }
        $name = trim((string) $request->input('name', ''));
        $code = DecorationImageImport::sanitizeCode((string) $request->input('code', ''));
        if ($code === '') {
            $code = (string) ($existing['code'] ?? '');
        }
        if ($name === '') {
            Session::flash('error', 'Le nom est obligatoire.');

            return Response::redirect(url('back-office/referentiels/decorations#deco-' . $id));
        }
        if ($code !== (string) ($existing['code'] ?? '')) {
            $clash = $this->definitions->findByCode($tenantId, $code);
            if ($clash !== null && (int) ($clash['id'] ?? 0) !== $id) {
                Session::flash('error', 'Le code ' . $code . ' est déjà utilisé par une autre décoration.');

                return Response::redirect(url('back-office/referentiels/decorations#deco-' . $id));
            }
        }
        $data = [
            'code' => $code,
            'name' => $name,
            'branch' => $request->input('branch'),
            'decoration_grade' => $request->input('decoration_grade'),
            'award_criterion' => $request->input('award_criterion'),
            'sort_order' => (int) $request->input('sort_order', (int) ($existing['sort_order'] ?? 0)),
        ];
        $oldImage = isset($existing['image_path']) ? (string) $existing['image_path'] : null;
        $upload = $_FILES['image'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $data['image_path'] = $this->motifStorage->storeUpload($tenantId, $upload, 'insignes');
                $this->motifStorage->delete($oldImage);
            } catch (RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(url('back-office/referentiels/decorations#deco-' . $id));
            } catch (Throwable) {
                Session::flash('error', 'Impossible d’enregistrer l’insigne.');

                return Response::redirect(url('back-office/referentiels/decorations#deco-' . $id));
            }
        } elseif ((string) $request->input('remove_image', '') === '1') {
            $this->motifStorage->delete($oldImage);
            $data['image_path'] = null;
        }
        $this->definitions->update($tenantId, $id, $data);
        Session::flash('success', 'Décoration « ' . $name . ' » mise à jour.');

        return Response::redirect(url('back-office/referentiels/decorations#deco-' . $id));
    }

    /**
     * Import en lot : chaque image déposée crée la décoration (ou met à jour celle qui a le même code).
     */
    public function importImages(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $back = url('back-office/referentiels/decorations#import');
        if (!$this->definitions->supportsImages()) {
            Session::flash('error', 'La base n’a pas encore la colonne des insignes : lancez run-migrations.php puis réessayez.');

            return Response::redirect($back);
        }
        $asArray = static fn (mixed $v): array => is_array($v) ? $v : [];
        $built = DecorationImageImport::buildRows(
            DecorationImageImport::normalizeUploads($_FILES['images'] ?? null),
            $asArray($request->input('bulk_name', [])),
            $asArray($request->input('bulk_code', [])),
            $asArray($request->input('bulk_branch', [])),
            $asArray($request->input('bulk_criterion', []))
        );
        if ($built['rows'] === []) {
            $msg = 'Aucune image reçue. Déposez un ou plusieurs fichiers PNG, WebP ou JPEG.';
            if ($built['skipped'] !== []) {
                $msg .= ' Ignorés : ' . implode(', ', $built['skipped']) . '.';
            }
            Session::flash('error', $msg);

            return Response::redirect($back);
        }
        $actor = (int) Session::get('user_id');
        $created = 0;
        $updated = 0;
        $errors = $built['skipped'];
        foreach ($built['rows'] as $row) {
            try {
                $path = $this->motifStorage->storeUpload($tenantId, $row['file'], 'insignes');
            } catch (RuntimeException $e) {
                $errors[] = $row['file']['name'] . ' (' . rtrim($e->getMessage(), '.') . ')';
                continue;
            } catch (Throwable) {
                $errors[] = $row['file']['name'] . ' (enregistrement impossible)';
                continue;
            }
            $data = [
                'code' => $row['code'],
                'name' => $row['name'],
                'image_path' => $path,
            ];
            if ($row['branch'] !== '') {
                $data['branch'] = $row['branch'];
            }
            if ($row['criterion'] !== '') {
                $data['award_criterion'] = $row['criterion'];
            }
            try {
                $existing = $this->definitions->findByCode($tenantId, $row['code']);
                if ($existing !== null) {
                    $this->definitions->update($tenantId, (int) $existing['id'], $data + ['restore' => true]);
                    $this->motifStorage->delete(isset($existing['image_path']) ? (string) $existing['image_path'] : null);
                    $updated++;
                } else {
                    $this->definitions->create($tenantId, $data, $actor > 0 ? $actor : null);
                    $created++;
                }
            } catch (Throwable) {
                $this->motifStorage->delete($path);
                $errors[] = $row['file']['name'] . ' (enregistrement en base impossible)';
            }
        }
        $parts = [];
        if ($created > 0) {
            $parts[] = $created . ' décoration' . ($created > 1 ? 's créées' : ' créée');
        }
        if ($updated > 0) {
            $parts[] = $updated . ' mise' . ($updated > 1 ? 's' : '') . ' à jour';
        }
        if ($parts !== []) {
            Session::flash('success', 'Import terminé : ' . implode(', ', $parts) . '.');
        }
        if ($errors !== []) {
            Session::flash('error', 'Non importés : ' . implode(', ', $errors) . '.');
        }

        return Response::redirect(url('back-office/referentiels/decorations#referentiel'));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->definitions->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Décoration archivée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
    }

    public function grant(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $personnelId = (int) $request->input('personnel_id', 0);
        $definitionId = (int) $request->input('definition_id', 0);
        if ($personnelId < 1 || $definitionId < 1) {
            Session::flash('error', 'Personnel et décoration obligatoires.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        $awardedAt = (string) $request->input('awarded_at', date('Y-m-d'));
        $citation = trim((string) $request->input('citation_text', ''));
        $authority = trim((string) $request->input('authority', ''));
        $this->awards->create($tenantId, [
            'personnel_id' => $personnelId,
            'definition_id' => $definitionId,
            'citation_text' => $citation,
            'authority' => $authority,
            'awarded_at' => $awardedAt,
        ], (int) Session::get('user_id'));
        $this->careerEvents->record($tenantId, $personnelId, 'decoration_awarded', (int) Session::get('user_id'), [
            'definition_id' => $definitionId,
        ]);
        $defName = 'Décoration';
        try {
            $def = $this->definitions->find($tenantId, $definitionId);
            $defName = trim((string) ($def['name'] ?? $def['short_name'] ?? $defName)) ?: $defName;
        } catch (Throwable) {
        }
        $this->serviceHistoryWriter->recordAward(
            $personnelId,
            $defName,
            $citation,
            $awardedAt,
            (int) Session::get('user_id'),
            $authority !== '' ? $authority : null
        );
        Session::flash('success', 'Citation enregistrée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
    }

    public function storeMotif(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        $name = trim((string) $request->input('motif_name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour le motif.');

            return Response::redirect(url('back-office/referentiels/decorations#motifs'));
        }

        $motifType = (string) $request->input('motif_type', 'ribbon') === 'medal' ? 'medal' : 'ribbon';
        $pattern = trim((string) $request->input('pattern_class', 'dk-rb-svc2'));
        if (!isset(DecorationCatalog::PATTERN_CHOICES[$pattern])) {
            $pattern = 'dk-rb-svc2';
        }

        $imagePath = null;
        $upload = $_FILES['motif_image'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $imagePath = $this->motifStorage->storeUpload($tenantId, $upload);
            } catch (RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(url('back-office/referentiels/decorations#motifs'));
            } catch (Throwable) {
                Session::flash('error', 'Impossible d’enregistrer l’image du motif.');

                return Response::redirect(url('back-office/referentiels/decorations#motifs'));
            }
        }

        $glyph = trim((string) $request->input('glyph', ''));
        $allowedGlyphs = ['star', 'cross', 'wreath', 'circle'];
        if ($glyph !== '' && !in_array($glyph, $allowedGlyphs, true)) {
            $glyph = '';
        }

        $this->motifs->create($tenantId, [
            'name' => $name,
            'motif_type' => $motifType,
            'level_label' => $request->input('level_label'),
            'description' => $request->input('motif_description'),
            'pattern_class' => $pattern,
            'drop_class' => $motifType === 'medal' ? $pattern : null,
            'disc_class' => $motifType === 'medal' ? 'dk-disc-svc' : null,
            'glyph' => $motifType === 'medal' ? ($glyph !== '' ? $glyph : 'circle') : null,
            'colors' => $this->parseColors($request),
            'image_path' => $imagePath,
            'sort_order' => (int) $request->input('motif_sort_order', 0),
        ], (int) Session::get('user_id'));

        Session::flash('success', 'Motif ajouté au catalogue de la communauté.');

        return Response::redirect(url('back-office/referentiels/decorations#motifs'));
    }

    public function updateMotif(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->motifs->find($id, $tenantId);
        if ($existing === null) {
            Session::flash('error', 'Motif introuvable.');

            return Response::redirect(url('back-office/referentiels/decorations#motifs'));
        }

        $name = trim((string) $request->input('motif_name', ''));
        if ($name === '') {
            Session::flash('error', 'Indiquez un nom pour le motif.');

            return Response::redirect(url('back-office/referentiels/decorations#motifs'));
        }

        $motifType = (string) $request->input('motif_type', 'ribbon') === 'medal' ? 'medal' : 'ribbon';
        $pattern = trim((string) $request->input('pattern_class', 'dk-rb-svc2'));
        if (!isset(DecorationCatalog::PATTERN_CHOICES[$pattern])) {
            $pattern = (string) ($existing['pattern_class'] ?? 'dk-rb-svc2');
        }

        $data = [
            'name' => $name,
            'motif_type' => $motifType,
            'level_label' => $request->input('level_label'),
            'description' => $request->input('motif_description'),
            'pattern_class' => $pattern,
            'drop_class' => $motifType === 'medal' ? $pattern : null,
            'disc_class' => $motifType === 'medal' ? 'dk-disc-svc' : null,
            'sort_order' => (int) $request->input('motif_sort_order', 0),
            'colors' => $this->parseColors($request),
        ];

        $glyph = trim((string) $request->input('glyph', ''));
        $allowedGlyphs = ['star', 'cross', 'wreath', 'circle'];
        if ($motifType === 'medal') {
            $data['glyph'] = in_array($glyph, $allowedGlyphs, true) ? $glyph : 'circle';
        } else {
            $data['glyph'] = null;
        }

        $upload = $_FILES['motif_image'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = $this->motifStorage->storeUpload($tenantId, $upload);
                $this->motifStorage->delete(isset($existing['image_path']) ? (string) $existing['image_path'] : null);
                $data['image_path'] = $newPath;
            } catch (RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(url('back-office/referentiels/decorations#motifs'));
            } catch (Throwable) {
                Session::flash('error', 'Impossible d’enregistrer l’image du motif.');

                return Response::redirect(url('back-office/referentiels/decorations#motifs'));
            }
        } elseif ((string) $request->input('remove_image', '') === '1') {
            $this->motifStorage->delete(isset($existing['image_path']) ? (string) $existing['image_path'] : null);
            $data['image_path'] = null;
        }

        $this->motifs->update($tenantId, $id, $data);
        Session::flash('success', 'Motif mis à jour.');

        return Response::redirect(url('back-office/referentiels/decorations#motifs'));
    }

    public function archiveMotif(Request $request, array $params = []): Response
    {
        $tenantId = $this->post($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->motifs->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Motif retiré du catalogue.');

        return Response::redirect(url('back-office/referentiels/decorations#motifs'));
    }

    /**
     * @return list<string>
     */
    private function parseColors(Request $request): array
    {
        $colors = [];
        foreach (['color_1', 'color_2', 'color_3'] as $key) {
            $hex = strtoupper(trim((string) $request->input($key, '')));
            if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                $colors[] = $hex;
            }
        }

        return $colors;
    }

    private function tenant(): int|Response
    {
        $id = (int) Session::get('tenant_id');

        return $id > 0 ? $id : Response::redirect(url('login'));
    }

    private function post(Request $request): int|Response
    {
        $tenantId = $this->tenant();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }

        return $tenantId;
    }
}
