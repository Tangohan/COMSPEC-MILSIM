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
use App\Support\DecorationCatalog;
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
    ) {
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
            'backOfficePageCss' => ['decorations-kit.css'],
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
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('back-office/referentiels/decorations'));
        }
        $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'decoration_grade' => $request->input('decoration_grade'),
            'award_criterion' => $request->input('award_criterion'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ], (int) Session::get('user_id'));
        Session::flash('success', 'Décoration créée.');

        return Response::redirect(url('back-office/referentiels/decorations'));
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
        $this->awards->create($tenantId, [
            'personnel_id' => $personnelId,
            'definition_id' => $definitionId,
            'citation_text' => $request->input('citation_text'),
            'authority' => $request->input('authority'),
            'awarded_at' => $request->input('awarded_at', date('Y-m-d')),
        ], (int) Session::get('user_id'));
        $this->careerEvents->record($tenantId, $personnelId, 'decoration_awarded', (int) Session::get('user_id'), [
            'definition_id' => $definitionId,
        ]);
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
