<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CustomGradeSystemRepository;
use App\Repositories\TenantRepository;
use App\Services\Advancement\GradeScaleTemplateService;
use App\Support\CustomGradeTemplates;

/**
 * Grades sur mesure (référentiel des grades → onglet « Sur mesure ») : systèmes, catégories et grades
 * créés par la communauté (SCP, Star Wars, Warhammer 40k, univers maison…). L’affichage est fait par
 * GradeReferentielController::index ; ici, uniquement les actions (POST + CSRF).
 */
class CustomGradeSystemController
{
    public function __construct(
        private CustomGradeSystemRepository $custom,
        private TenantRepository $tenantRepository,
        private GradeScaleTemplateService $templates,
    ) {
    }

    public function storeSystem(Request $request, array $params = []): Response
    {
        return $this->guard($request, 0, function (int $tenantId) use ($request): Response {
            $template = (string) $request->input('template', 'vide');
            if (CustomGradeTemplates::find($template) === null) {
                $template = 'vide';
            }
            try {
                $id = $this->custom->createSystem($tenantId, (string) $request->input('label', ''), $template);
            } catch (\RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect($this->back(0));
            }
            if ($request->input('use_now')) {
                $this->useSystem($tenantId, $id);
                Session::flash('success', 'Système créé et utilisé par la communauté : les fiches membres proposent maintenant ces grades.');
            } else {
                Session::flash('success', 'Système créé. Ajustez les catégories et les grades, puis « Utiliser pour la communauté ».');
            }

            return Response::redirect($this->back($id));
        });
    }

    public function updateSystem(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->guard($request, $id, function (int $tenantId) use ($request, $id): Response {
            if ($this->custom->renameSystem($tenantId, $id, (string) $request->input('label', ''))) {
                Session::flash('success', 'Système renommé.');
            } else {
                Session::flash('error', 'Le nom du système est requis.');
            }

            return Response::redirect($this->back($id));
        });
    }

    public function useSystemAction(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->guard($request, $id, function (int $tenantId) use ($id): Response {
            if ($this->useSystem($tenantId, $id)) {
                Session::flash('success', 'La communauté utilise maintenant ce système. Les grades déjà attribués sont conservés : attribuez les nouveaux grades depuis les fiches membres.');
            } else {
                Session::flash('error', 'Système introuvable.');
            }

            return Response::redirect($this->back($id));
        });
    }

    public function deleteSystem(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->guard($request, $id, function (int $tenantId) use ($id): Response {
            $system = $this->custom->findSystem($tenantId, $id);
            if ($system === null) {
                return Response::redirect($this->back(0));
            }
            if ($this->currentSystemCode($tenantId) === (string) $system['code']) {
                Session::flash('error', 'Ce système est celui de la communauté : choisissez d’abord un autre système (français, américain ou un autre sur mesure).');

                return Response::redirect($this->back($id));
            }
            $holders = $this->custom->deleteSystem($tenantId, $id);
            if ($holders > 0) {
                Session::flash('error', $holders . ' membre(s) portent encore un grade de ce système. Attribuez-leur un autre grade avant de le supprimer.');

                return Response::redirect($this->back($id));
            }
            Session::flash('success', 'Système supprimé.');

            return Response::redirect($this->back(0));
        });
    }

    public function storeCategory(Request $request, array $params = []): Response
    {
        $systemId = (int) $request->input('grade_system_id', 0);

        return $this->guard($request, $systemId, function (int $tenantId) use ($request, $systemId): Response {
            if ($this->custom->createCategory($tenantId, $systemId, (string) $request->input('label', '')) > 0) {
                $this->syncAdvancement($tenantId, $systemId);
                Session::flash('success', 'Catégorie ajoutée en bas de l’échelle.');
            } else {
                Session::flash('error', 'Le nom de la catégorie est requis.');
            }

            return Response::redirect($this->back($systemId));
        });
    }

    public function updateCategory(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->categoryAction($request, $id, function (int $tenantId, int $systemId) use ($request, $id): void {
            if ($this->custom->renameCategory($tenantId, $id, (string) $request->input('label', ''))) {
                Session::flash('success', 'Catégorie renommée.');
            } else {
                Session::flash('error', 'Le nom de la catégorie est requis.');
            }
        });
    }

    public function moveCategory(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->categoryAction($request, $id, function (int $tenantId, int $systemId) use ($request, $id): void {
            $this->custom->moveCategory($tenantId, $id, $request->input('dir') === 'up' ? 1 : -1);
        });
    }

    public function deleteCategory(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->categoryAction($request, $id, function (int $tenantId, int $systemId) use ($id): void {
            $holders = $this->custom->deleteCategory($tenantId, $id);
            if ($holders > 0) {
                Session::flash('error', $holders . ' membre(s) portent un grade de cette catégorie. Attribuez-leur un autre grade ou déplacez ces grades avant de supprimer la catégorie.');
            } else {
                Session::flash('success', 'Catégorie supprimée.');
            }
        });
    }

    public function storeGrade(Request $request, array $params = []): Response
    {
        $categoryId = (int) $request->input('grade_category_id', 0);

        return $this->categoryAction($request, $categoryId, function (int $tenantId, int $systemId) use ($request, $categoryId): void {
            try {
                $id = $this->custom->createGrade($tenantId, $categoryId, (string) $request->input('label_long', ''), (string) $request->input('label_short', ''));
            } catch (\RuntimeException $e) {
                Session::flash('error', $e->getMessage());

                return;
            }
            if ($id > 0) {
                $this->syncAdvancement($tenantId, $systemId);
                Session::flash('success', 'Grade ajouté en bas de la catégorie : montez-le avec les flèches si besoin.');
            } else {
                Session::flash('error', 'Le nom du grade est requis.');
            }
        });
    }

    public function updateGrade(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->gradeAction($request, $id, function (int $tenantId, int $systemId) use ($request, $id): void {
            $ok = $this->custom->updateGrade(
                $tenantId,
                $id,
                (string) $request->input('label_long', ''),
                (string) $request->input('label_short', ''),
                (int) $request->input('grade_category_id', 0)
            );
            if ($ok) {
                $this->syncAdvancement($tenantId, $systemId);
            }
            Session::flash($ok ? 'success' : 'error', $ok ? 'Grade enregistré.' : 'Le nom du grade est requis.');
        });
    }

    public function moveGrade(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->gradeAction($request, $id, function (int $tenantId, int $systemId) use ($request, $id): void {
            $this->custom->moveGrade($tenantId, $id, $request->input('dir') === 'up' ? 1 : -1);
        });
    }

    public function deleteGrade(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->gradeAction($request, $id, function (int $tenantId, int $systemId) use ($id): void {
            $result = $this->custom->deleteGrade($tenantId, $id);
            if ($result['deleted']) {
                Session::flash('success', 'Grade supprimé.');
            } elseif ($result['holders'] > 0) {
                Session::flash('info', $result['holders'] . ' membre(s) portent ce grade : il est retiré des listes mais ils le gardent. Vous pouvez le remettre en bas de la catégorie.');
            }
        });
    }

    public function restoreGrade(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->gradeAction($request, $id, function (int $tenantId, int $systemId) use ($id): void {
            if ($this->custom->setGradeActive($tenantId, $id, true)) {
                Session::flash('success', 'Grade remis dans les listes.');
            }
        });
    }

    /**
     * @param callable(int, int): void $action
     */
    private function categoryAction(Request $request, int $categoryId, callable $action): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $cat = $this->custom->findCategory($tenantId, $categoryId);
        $systemId = $cat !== null ? (int) $cat['grade_system_id'] : 0;

        return $this->guard($request, $systemId, function (int $tenantId) use ($cat, $systemId, $action): Response {
            if ($cat === null) {
                Session::flash('error', 'Catégorie introuvable.');

                return Response::redirect($this->back(0));
            }
            $action($tenantId, $systemId);

            return Response::redirect($this->back($systemId));
        });
    }

    /**
     * @param callable(int, int): void $action
     */
    private function gradeAction(Request $request, int $gradeId, callable $action): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $grade = $this->custom->findGrade($tenantId, $gradeId);
        $systemId = $grade !== null ? (int) $grade['grade_system_id'] : 0;

        return $this->guard($request, $systemId, function (int $tenantId) use ($grade, $systemId, $action): Response {
            if ($grade === null) {
                Session::flash('error', 'Grade introuvable.');

                return Response::redirect($this->back(0));
            }
            $action($tenantId, $systemId);

            return Response::redirect($this->back($systemId));
        });
    }

    /**
     * @param callable(int): Response $then
     */
    private function guard(Request $request, int $systemId, callable $then): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($this->back($systemId));
        }
        if (!$this->custom->ready()) {
            Session::flash('error', 'Les grades sur mesure ne sont pas encore installés sur ce serveur (migrations à lancer).');

            return Response::redirect($this->back(0));
        }

        return $then($tenantId);
    }

    private function useSystem(int $tenantId, int $systemId): bool
    {
        $system = $this->custom->findSystem($tenantId, $systemId);
        if ($system === null) {
            return false;
        }
        $this->tenantRepository->mergeSettings($tenantId, ['grades_hidden' => false, 'grade_system_code' => (string) $system['code']]);
        \App\Support\TenantGrades::forget($tenantId);
        $this->syncAdvancement($tenantId, $systemId);

        return true;
    }

    /** Recopie l’échelle dans l’avancement quand le système est celui de la communauté. */
    private function syncAdvancement(int $tenantId, int $systemId): void
    {
        $system = $this->custom->findSystem($tenantId, $systemId);
        if ($system === null || $this->currentSystemCode($tenantId) !== (string) $system['code']) {
            return;
        }
        try {
            $this->templates->completeForTenant($tenantId, (string) $system['code']);
        } catch (\Throwable) {
            // l’échelle se complète aussi à l’ouverture du référentiel
        }
    }

    private function currentSystemCode(int $tenantId): string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);

        return trim((string) ($settings['grade_system_code'] ?? ''));
    }

    private function back(int $systemId): string
    {
        $url = url('back-office/referentiels/grades') . '?tab=custom';

        return $systemId > 0 ? $url . '&systeme=' . $systemId : $url;
    }
}
