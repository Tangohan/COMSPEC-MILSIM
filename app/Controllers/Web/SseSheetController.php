<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseSheetRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetLevelService;
use App\Services\Sse\SseSheetVisibility;
use App\Services\Sse\SseSheetReissueService;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseVigilanceFollow;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseRedactionExplain;
use App\Support\SseSheetCatalog as D;
use App\Support\SseSheetText as T;
use App\Support\SseWantedCatalog as C;

/**
 * Portail SSE : fiches PDF d'une personne ou d'une identité fictive (identification, vigilance, renseignement, contrôle
 * terrain, avis de recherche) : éditeur de textes avec brouillons, aperçu, émission, re-téléchargement et trames de phrases.
 *
 * Droits (SseSheetAccess, politique d'accès du portail) ; chaque écriture exige le jeton CSRF ; la communauté est toujours
 * celle de la session (jamais lue dans la requête) ; la fiche, le brouillon et le document appartiennent à cette communauté
 * (une fiche d'une autre communauté est introuvable). Émettre ou prévisualiser ne modifie jamais la fiche.
 */
final class SseSheetController
{
    private const KINDS = ['personne' => 'person', 'identite' => 'identity'];

    private ?SseSheetAccess $rights = null;

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseSheetFactsService $facts = null,
        private ?SseSheetService $service = null,
        private ?SseSheetRepository $repo = null,
        ?SseSheetAccess $rights = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->clearance ??= new SseClearanceService($this->access);
        $this->facts ??= new SseSheetFactsService();
        $this->service ??= new SseSheetService($this->facts);
        $this->repo ??= new SseSheetRepository();
        $this->rights = $rights;
    }

    // ----------------------------------------------------------------- Éditeur

    public function editor(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, $kind, $model] = $ctx;
        $draft = $this->draftOf($request->queryString('brouillon'), $subject, $model);
        $old = [];
        if ($draft !== null) {
            $old = ['lang' => $draft['lang'], 'texts' => $draft['texts']] + $draft['params'];
        }
        foreach (['lang', 'classification', 'faces', 'threat', 'armed', 'validity'] as $k) {
            if ($request->queryString($k) !== '') {
                $old[$k] = $request->queryString($k);
            }
        }

        return $this->renderEditor($subject, $kind, $model, $old, [], 200, $draft, (int) $request->queryString('emis', '0'));
    }

    /** Textes par défaut du modèle pour une langue et une classification (JSON), pour recharger l'éditeur sans le perdre. */
    public function defaults(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, , $model] = $ctx;
        $r = $this->r();
        $lang = D::lang($request->queryString('lang', 'fr'));
        $level = SseSheetService::clampLevel($request->queryString('classification'), $r);
        $override = SseSheetVisibility::clean($request->query('visibility'));
        $texts = $this->service->defaults($r->tenantId(), $subject, $model, $lang, $level, $r, $override);

        return Response::json(['ok' => true, 'texts' => $texts, 'level' => $level]);
    }

    // ------------------------------------------------------------- Brouillons

    public function draftSave(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params, true);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, , $model] = $ctx;
        $r = $this->r();
        if (!Csrf::verify($request)) {
            return Response::json(['ok' => false, 'error' => 'Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.'], 419);
        }
        if (!SseSheetRepository::ready()) {
            return Response::json(['ok' => false, 'error' => 'Les brouillons seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée.'], 503);
        }
        $input = $this->input($request, $model);
        $draft = $this->draftOf($request->inputString('draft_id'), $subject, $model);
        $identityIds = array_map(static fn (array $i): int => (int) $i['id'], $subject['identities']);
        if ($draft === null && count($this->repo->listDrafts($r->tenantId(), $subject['person_id'], $identityIds)) >= SseSheetRepository::MAX_DRAFTS_PER_SUBJECT) {
            return Response::json(['ok' => false, 'error' => sprintf('%d brouillons au plus par fiche : supprimez-en un avant d’en créer un autre.', SseSheetRepository::MAX_DRAFTS_PER_SUBJECT)], 422);
        }
        $title = trim($request->inputString('draft_title'));
        $title = $title !== '' ? $title : ($draft['title'] ?? '');
        if ($title === '') {
            $title = D::label($model, 'fr') . ' · ' . gmdate('d/m H:i');
        }
        $id = $this->repo->saveDraft(
            $r->tenantId(), $draft !== null ? (int) $draft['id'] : null, $subject['person_id'], $subject['identity_id'], $model, $input['lang'], $title,
            ['texts' => $input['texts'], 'params' => array_diff_key($input, ['texts' => 1, 'lang' => 1])], $r->userId()
        );

        return Response::json(['ok' => true, 'id' => $id, 'title' => $title, 'saved_at' => gmdate('H:i') . ' UTC']);
    }

    public function draftDelete(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, $kind, $model] = $ctx;
        $back = $this->editorUrl($kind, (int) $params['id'], $model);
        if (!Csrf::verify($request)) {
            return $this->flashBack('error', 'Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.', $back);
        }
        $draft = $this->draftOf((string) ($params['draft'] ?? ''), $subject, $model);
        if ($draft === null) {
            return $this->flashBack('error', 'Brouillon introuvable.', $back);
        }
        $this->repo->deleteDraft($this->r()->tenantId(), (int) $draft['id']);

        return $this->flashBack('success', 'Brouillon supprimé.', $back);
    }

    // ----------------------------------------------------------- Aperçu, émission

    public function preview(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params, $request->queryString('format') === 'json' || $request->inputString('format') === 'json');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, $kind, $model] = $ctx;
        $json = $request->inputString('format') === 'json';
        $back = $this->editorUrl($kind, (int) $params['id'], $model);
        if (!Csrf::verify($request)) {
            return $json ? Response::json(['ok' => false, 'errors' => ['Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.']], 419)
                : $this->flashBack('error', 'Votre page est restée ouverte trop longtemps : rechargez-la puis recommencez.', $back);
        }
        if (!SseSheetService::previewAllowed()) {
            $msg = 'Trop d’aperçus en une minute : patientez quelques secondes.';

            return $json ? Response::json(['ok' => false, 'errors' => [$msg]], 429) : $this->flashBack('error', $msg, $back);
        }
        $res = $this->service->preview($this->r()->tenantId(), $subject, $model, $this->input($request, $model), $this->r());
        if ($json) {
            return Response::json(['ok' => $res['ok'], 'errors' => $res['errors'], 'pages' => $res['pages'], 'overflow' => $res['overflow'], 'sensitive' => $res['sensitive']], $res['ok'] ? 200 : 422);
        }
        if (!$res['ok'] || $res['bytes'] === null) {
            return $this->flashBack('error', implode(' ', $res['errors']) !== '' ? implode(' ', $res['errors']) : 'L’aperçu n’a pas pu être généré.', $back);
        }

        return $this->pdf($res['bytes'], 'apercu-' . $model . '.pdf', true);
    }

    public function issue(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, $kind, $model] = $ctx;
        $r = $this->r();
        $input = $this->input($request, $model);
        if (!Csrf::verify($request)) {
            return $this->renderEditor($subject, $kind, $model, $input, ['Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez (vos textes sont conservés ci-dessous).'], 419);
        }
        $res = $this->service->issue($r->tenantId(), $subject, $model, $input, $r);
        if (!$res['ok'] || $res['bytes'] === null) {
            return $this->renderEditor($subject, $kind, $model, $input, $res['errors'] !== [] ? $res['errors'] : ['Le document n’a pas pu être émis.'], $res['error'] === 'forbidden' ? 403 : 422);
        }
        if ($res['id'] > 0) {
            Session::flash('success', 'Document ' . $res['ref'] . ' émis. Il est dans « Documents émis » de la fiche.');

            return Response::redirect($this->editorUrl($kind, (int) $params['id'], $model) . '?emis=' . (int) $res['id']);
        }

        // Avis de recherche sans l'étape « fiches PDF » : comme avant, le PDF est servi directement.
        return $this->pdf($res['bytes'], $res['filename'], false);
    }

    /**
     * Génération rapide : le PDF est émis directement, textes tirés des trames du modèle (rien à remplir), avec la classification
     * par défaut de la communauté. Mêmes droits, même jeton CSRF, même plafond de débit que l'émission depuis l'éditeur ; le
     * texte réellement imprimé est conservé au journal d'émission.
     */
    public function quick(Request $request, array $params = []): Response
    {
        $ctx = $this->context($params);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$subject, $kind, $model] = $ctx;
        $back = $kind === 'identite' ? url('atak/sse/papiers/identites/' . (int) ($params['id'] ?? 0)) : url('atak/sse/identites/' . (int) ($params['id'] ?? 0) . '?fiche=personne');
        if ($model === 'notice') {
            return $this->flashBack('error', 'L’avis de recherche se télécharge depuis son propre bouton.', $back);
        }
        if (!Csrf::verify($request)) {
            return $this->flashBack('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back);
        }
        $r = $this->r();
        $res = $this->service->issue($r->tenantId(), $subject, $model, $this->service->quickInput($r->tenantId(), $r, SseWantedNoticeController::readerLang()), $r);
        if (!$res['ok'] || $res['bytes'] === null) {
            return $this->flashBack('error', $res['errors'] !== [] ? implode(' ', $res['errors']) : 'Le document n’a pas pu être émis.', $back);
        }

        return $this->pdf($res['bytes'], $res['filename'], false);
    }

    /** Re-téléchargement d'un document émis (texte imprimé conservé) : au moins l'habilitation de sa classification. */
    public function download(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if ($request->queryString('niveau') !== '') {
            return $this->downloadAtLevel($request, $params);
        }
        if (!$r->canDraft()) {
            return $this->flashBack('error', 'Action non autorisée.', url('atak/sse/accueil'));
        }
        $res = $this->service->reissue($r->tenantId(), (int) ($params['id'] ?? 0), $r);
        if (!$res['ok'] || $res['bytes'] === null) {
            $msg = match ($res['error']) {
                'forbidden' => 'Ce document est classé au-dessus de votre habilitation.',
                'engine' => 'Le PDF n’a pas pu être généré : le moteur PDF ou ses polices sont indisponibles.',
                default => 'Document introuvable.',
            };

            return $this->flashBack('error', $msg, url('atak/sse/identites'));
        }

        return $this->pdf($res['bytes'], $res['filename'], $request->queryString('mode') === 'impression');
    }

    /**
     * Téléchargement à un niveau de diffusion choisi (?niveau=…) : copie rendue depuis l'instantané, sans rien émettre. Débit limité,
     * journalisé. Document absent, autre communauté, droits ou habilitation insuffisants : la même réponse 404.
     */
    private function downloadAtLevel(Request $request, array $params): Response
    {
        $r = $this->r();
        $notFound = static fn (): Response => (new Response())->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')->header('Cache-Control', 'no-store')->setBody('Not found');
        if (!$r->canDraft()) {
            return $notFound();
        }
        $id = (int) ($params['id'] ?? 0);
        if (!SseSheetLevelService::allowed()) {
            return (new Response())->setStatusCode(429)->header('Content-Type', 'text/plain; charset=utf-8')->header('Cache-Control', 'no-store')->header('Retry-After', '30')->setBody('Too many requests');
        }
        $res = (new SseSheetLevelService($this->repo, $this->facts, $this->service))->download($r->tenantId(), $id, $request->queryString('niveau'), $r);
        if ($res['error'] === 'notfound') {
            return $notFound();
        }
        $back = $res['issue'] !== null ? SseSheetReissueService::backUrl($res['issue']) : url('atak/sse/identites');
        if (!$res['ok'] || $res['bytes'] === null) {
            $msg = match ($res['error']) {
                'ceiling' => 'Ce niveau dépasse votre habilitation.',
                'reissue' => 'Ce niveau rendrait lisibles des passages que ce document ne contient pas : réémettez-le à un niveau supérieur.',
                'level', 'format' => 'Ce niveau n’est pas disponible pour ce document.',
                'engine' => 'Le PDF n’a pas pu être généré : le moteur PDF ou ses polices sont indisponibles.',
                default => 'Document introuvable.',
            };

            return $this->flashBack('error', $msg, $back);
        }
        $level = \App\Support\SseUsClassification::abbr(SseSheetService::clampLevel($request->queryString('niveau'), $r));

        return $this->pdf($res['bytes'], (string) preg_replace('/\.pdf$/', '-' . $level . '.pdf', $res['filename']), $request->queryString('mode') === 'impression');
    }

    /**
     * Réémission d'un document émis à un niveau de diffusion supérieur (motif obligatoire, plafond : l'habilitation de l'opérateur).
     * L'ancien document est conservé ; le nouveau porte sa propre référence, liée à l'ancienne. Journalisée.
     */
    public function reissueHigher(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $json = str_contains(strtolower((string) $request->header('Accept')), 'application/json');
        // Depuis la fenêtre de téléchargement (Accept : JSON) : même contrôle, réponse JSON ; sinon redirection avec message.
        $fail = function (string $code, string $message, string $to, int $status) use ($json): Response {
            return $json ? Response::json(['ok' => false, 'error' => $code, 'message' => $message], $status) : $this->flashBack('error', $message, $to);
        };
        $id = (int) ($params['id'] ?? 0);
        $issue = $r->canDraft() && SseSheetRepository::ready() ? $this->repo->findIssue($r->tenantId(), $id) : null;
        if ($issue === null) {
            return $r->canDraft() ? $fail('notfound', 'Document introuvable.', url('atak/sse/identites'), 404) : $fail('forbidden', 'Action non autorisée.', url('atak/sse/identites'), 403);
        }
        $back = SseSheetReissueService::backUrl($issue);
        if (!Csrf::verify($request)) {
            return $fail('csrf', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.', $back, 419);
        }
        $res = (new SseSheetReissueService($this->repo, $this->facts, $this->service))->reissue($r->tenantId(), $id, $request->inputString('classification'), $request->inputString('reason'), $r);
        if (!$res['ok']) {
            return $fail($res['error'], $res['message'], $back, match ($res['error']) {
                'forbidden', 'ceiling' => 403,
                'notfound' => 404,
                default => 422,
            });
        }
        $message = 'Document ' . $res['ref'] . ' émis à un niveau supérieur (réémission de ' . $res['old_ref'] . '). L’ancien document est conservé.';
        if ($json) {
            return Response::json([
                'ok' => true, 'id' => $res['id'], 'ref' => $res['ref'], 'old_ref' => $res['old_ref'], 'message' => $message,
                'level' => SseSheetService::clampLevel($request->inputString('classification'), $r),
                'pdf_url' => url('atak/sse/identites/emissions/' . (int) $res['id'] . '/pdf'),
            ]);
        }

        return $this->flashBack('success', $message, $back);
    }

    // ----------------------------------------------------------------- Phrases

    public function phrases(Request $request, array $params = []): Response
    {
        $r = $this->r();
        if (!$r->canDraft()) {
            return $this->flashBack('error', 'Action non autorisée.', url('atak/sse/accueil'));
        }
        $edit = null;
        if ($request->queryString('modifier') !== '' && SseSheetRepository::ready()) {
            $edit = $this->repo->findPhrase($r->tenantId(), (int) $request->queryString('modifier'));
        }

        return $this->page('atak.sse.sheets.phrases', [
            'title' => 'Trames de phrases',
            'ready' => SseSheetRepository::ready(),
            'phrases' => SseSheetRepository::ready() ? $this->repo->listPhrases($r->tenantId()) : [],
            'edit' => $edit, 'errors' => [], 'old' => [],
            'canManagePhrases' => $r->canManagePhrases(),
            'models' => D::MODELS, 'variables' => T::VARIABLES,
            'lang' => SseWantedNoticeController::readerLang(),
        ]);
    }

    public function phraseSave(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $back = url('atak/sse/identites/emission/phrases');
        if (!Csrf::verify($request)) {
            return $this->flashBack('error', 'Votre page est restée ouverte trop longtemps : rechargez la page puis recommencez.', $back);
        }
        if (!$r->canManagePhrases()) {
            return $this->flashBack('error', 'Droit manquant : écrire dans « Fichiers et sujets » (gestion des dossiers SSE). Demandez-le à un gestionnaire des accès SSE.', $back);
        }
        if (!SseSheetRepository::ready()) {
            return $this->flashBack('error', 'Les trames seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée.', $back);
        }
        $label = C::line($request->inputString('label'), 80);
        $body = T::clean($request->inputString('body'), 600);
        $model = $request->inputString('model');
        $errors = [];
        if (mb_strlen($label, 'UTF-8') < 2) {
            $errors[] = 'Donnez un nom à la trame (2 caractères au moins).';
        }
        if (mb_strlen($body, 'UTF-8') < 2) {
            $errors[] = 'Le texte de la trame est vide.';
        }
        $id = (int) $request->inputString('id', '0');
        if ($id > 0 && $this->repo->findPhrase($r->tenantId(), $id) === null) {
            return $this->flashBack('error', 'Trame introuvable.', $back);
        }
        if ($id < 1 && $this->repo->countPhrases($r->tenantId()) >= SseSheetRepository::MAX_PHRASES) {
            $errors[] = sprintf('%d trames au plus par communauté : supprimez-en une avant d’en créer une autre.', SseSheetRepository::MAX_PHRASES);
        }
        if ($errors !== []) {
            return $this->page('atak.sse.sheets.phrases', [
                'title' => 'Trames de phrases', 'ready' => true, 'phrases' => $this->repo->listPhrases($r->tenantId()), 'edit' => null, 'errors' => $errors,
                'old' => ['id' => $id, 'label' => $label, 'body' => $body, 'model' => $model, 'lang' => $request->inputString('lang')],
                'canManagePhrases' => true, 'models' => D::MODELS, 'variables' => T::VARIABLES, 'lang' => SseWantedNoticeController::readerLang(),
            ], 422);
        }
        $this->repo->savePhrase($r->tenantId(), $id > 0 ? $id : null, $model, $request->inputString('lang'), $label, $body, $r->userId());

        return $this->flashBack('success', 'Trame enregistrée.', $back);
    }

    public function phraseDelete(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $back = url('atak/sse/identites/emission/phrases');
        if (!Csrf::verify($request)) {
            return $this->flashBack('error', 'Votre page est restée ouverte trop longtemps : rechargez la page puis recommencez.', $back);
        }
        if (!$r->canManagePhrases() || !SseSheetRepository::ready()) {
            return $this->flashBack('error', 'Action non autorisée.', $back);
        }
        $ok = $this->repo->deletePhrase($r->tenantId(), (int) ($params['id'] ?? 0));

        return $ok ? $this->flashBack('success', 'Trame supprimée.', $back) : $this->flashBack('error', 'Trame introuvable.', $back);
    }

    // ------------------------------------------------------------------ Outils

    /**
     * Fiche, modèle et droits d'une requête, ou la réponse de refus.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: string}|Response
     */
    private function context(array $params, bool $json = false): array|Response
    {
        $r = $this->r();
        $kind = (string) ($params['kind'] ?? '');
        $model = (string) ($params['model'] ?? '');
        $deny = fn (string $msg, int $code): Response => $json ? Response::json(['ok' => false, 'error' => $msg], $code) : $this->flashBack('error', $msg, url('atak/sse/identites'));
        if (!$r->canDraft()) {
            return $deny('Action non autorisée.', 403);
        }
        if (!isset(self::KINDS[$kind])) {
            return $deny('Fiche introuvable.', 404);
        }
        if (!D::exists($model)) {
            return $deny('Modèle de document inconnu.', 404);
        }
        $subject = $this->facts->subject($r->tenantId(), self::KINDS[$kind], (int) ($params['id'] ?? 0), $r);
        if ($subject === null) {
            return $deny('Fiche introuvable.', 404);
        }

        return [$subject, $kind, $model];
    }

    /** @param array<string, mixed> $subject @return array<string, mixed>|null brouillon de CETTE fiche et de CE modèle */
    private function draftOf(string $id, array $subject, string $model): ?array
    {
        $n = (int) $id;
        if ($n < 1 || !SseSheetRepository::ready()) {
            return null;
        }
        $d = $this->repo->findDraft($this->r()->tenantId(), $n);
        if ($d === null || $d['model'] !== $model) {
            return null;
        }
        $mine = ($subject['person_id'] !== null && $d['person_id'] === $subject['person_id'])
            || ($subject['identity_id'] !== null && $d['identity_id'] === $subject['identity_id']);

        return $mine ? $d : null;
    }

    /**
     * Saisie du formulaire : langue, classification, pages, réglages du modèle et textes (jamais de HTML : T::clean).
     *
     * @return array<string, mixed>
     */
    private function input(Request $request, string $model): array
    {
        $texts = [];
        $typed = $request->input('texts');
        foreach (D::texts($model) as [$key, $max]) {
            if (is_array($typed) && array_key_exists($key, $typed)) {
                $texts[$key] = T::clean($typed[$key], $max * 2);
            }
        }
        $checks = $request->input('checks');

        $base = [
            'lang' => D::lang($request->inputString('lang', 'fr')),
            'classification' => $request->inputString('classification'),
            'faces' => $request->inputString('faces', 'auto'),
            'avec_notice' => $request->inputString('avec_notice', '1'),
            'validity' => $request->inputString('validity'),
            'threat' => $request->inputString('threat'),
            'armed' => $request->inputString('armed'),
            'checks' => $request->inputString('checks_sent') === '1' ? (is_array($checks) ? array_values(array_map(static fn ($c): string => mb_substr((string) $c, 0, 40), array_slice($checks, 0, 40))) : []) : null,
            'texts' => $texts,
            // Surcharge d'émission des niveaux de visibilité (SseSheetVisibility : jamais sous le plancher de sécurité).
            'visibility' => SseSheetVisibility::clean($request->input('visibility')),
        ];
        if ($request->inputString('preview_level') !== '') {
            // Aperçu à un niveau précis depuis la fenêtre « Visibilité par niveau » : seulement pour l'aperçu, jamais pour l'émission.
            $base['classification'] = $request->inputString('preview_level');
        }
        if ($model === 'vigilance') {
            // Fiche de vigilance : niveau, consignes, motifs, zone et service à informer (SseVigilanceFollow::readInput).
            $base = SseVigilanceFollow::readInput([
                'level' => $request->inputString('level'), 'measures' => $request->input('measures'), 'measures_sent' => $request->inputString('measures_sent'),
                'reasons' => $request->input('reasons'), 'zone' => $request->inputString('zone'), 'service' => $request->inputString('service'),
                'service_other' => $request->inputString('service_other'), 'report_channel' => $request->inputString('report_channel'), 'report_delay' => $request->inputString('report_delay'),
            ], $base) + $base;
        }

        if ($model === 'vigilance') {
            // Raccourcis de durée : « h6 », « h24 », « h72 » (heures) ou « until_lifted » (sans échéance) ; sinon un nombre de jours.
            $v = (string) ($base['validity'] ?? '');
            if (preg_match('/^h(\d+)$/', $v, $mm) === 1 && in_array((int) $mm[1], D::VALIDITY_HOURS, true)) {
                $base['validity_hours'] = (int) $mm[1];
                $base['validity'] = (string) D::DEFAULT_VALIDITY;
            }
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $old valeurs saisies ou du brouillon
     * @param list<string> $errors
     * @param array<string, mixed>|null $draft
     */
    private function renderEditor(array $subject, string $kind, string $model, array $old, array $errors, int $status = 200, ?array $draft = null, int $issued = 0): Response
    {
        $r = $this->r();
        $tenantId = $r->tenantId();
        $lang = D::lang($old['lang'] ?? SseWantedNoticeController::readerLang());
        $level = SseSheetService::clampLevel($old['classification'] ?? '', $r);
        $texts = is_array($old['texts'] ?? null) ? $old['texts'] : [];
        $visOverride = SseSheetVisibility::clean($old['visibility'] ?? []);
        $visReq = $this->service->visibilityFor($tenantId, $model, ['visibility' => $visOverride]);
        $defaults = $this->service->defaults($tenantId, $subject, $model, $lang, $level, $r, $visOverride);
        foreach ($defaults as $k => $v) {
            $texts[$k] ??= $v;
        }
        $raw = $this->facts->raw($tenantId, $subject, $r, $lang);
        $masked = $this->facts->mask($raw, $level, $visReq)['facts'];
        $ready = SseSheetRepository::ready();
        $identityIds = array_map(static fn (array $i): int => (int) $i['id'], $subject['identities']);
        $variables = $this->facts->variables($masked, $lang, ['ref' => '', 'service' => '', 'date' => gmdate('Y-m-d')]);
        $issuedDoc = $issued > 0 && $ready ? $this->repo->findIssue($tenantId, $issued) : null;
        $issuedMine = $issuedDoc !== null && (($subject['person_id'] !== null && $issuedDoc['person_id'] === $subject['person_id']) || ($subject['identity_id'] !== null && $issuedDoc['identity_id'] === $subject['identity_id']));

        $vig = [];
        if ($model === 'vigilance') {
            $ui = SseWantedNoticeController::readerLang() === 'en' ? 1 : 0;
            $vig = SseVigilanceFollow::editorData((new SseWantedSettingsRepository())->get($tenantId), $ui);
            $vigIssued = $issuedMine && is_array($issuedDoc) ? (new \App\Services\Sse\SseAlertService())->findVigilance($tenantId, (int) $issuedDoc['id']) : null;
            $vigIssued = $vigIssued !== null ? SseVigilanceFollow::present($vigIssued, $r, $ui) : null;
        }

        return $this->page('atak.sse.sheets.editor', [
            'vig' => $vig, 'vigIssued' => $vigIssued ?? null,
            'title' => D::label($model, $lang) . ' — ' . (string) $subject['ref'],
            'subject' => $subject, 'kind' => $kind, 'model' => $model, 'modelDef' => D::MODELS[$model],
            'lang' => $lang, 'level' => $level, 'levels' => SseSheetService::levelsFor($r),
            'texts' => $texts, 'textFields' => D::texts($model),
            'old' => $old, 'errors' => $errors, 'draft' => $draft,
            'denial' => $r->denial($model), 'ready' => $ready,
            'drafts' => $ready ? $this->repo->listDrafts($tenantId, $subject['person_id'], $identityIds, $model) : [],
            'phrases' => $ready ? $this->repo->listPhrases($tenantId, $model, $lang) : [],
            'issues' => $ready ? $this->repo->listIssues($tenantId, $subject['person_id'], $identityIds, 30) : [],
            'issuedDoc' => $issuedMine ? $issuedDoc : null,
            'checks' => $raw['checks'], 'selectedChecks' => is_array($old['checks'] ?? null) ? $old['checks'] : array_map(static fn (array $c): string => (string) $c['ref'], $raw['checks']),
            'threat' => (string) ($old['threat'] ?? $raw['threat']), 'armed' => (string) ($old['armed'] ?? ($raw['armed'] !== '' ? $raw['armed'] : 'unknown')),
            'validity' => D::validity($old['validity'] ?? D::DEFAULT_VALIDITY), 'faces' => D::faces($old['faces'] ?? null),
            'variables' => $variables, 'variableDefs' => T::VARIABLES,
            'redactionPreview' => SseRedactionExplain::preview($raw, SseSheetService::levelsFor($r), $visReq),
            'visReq' => $visReq, 'visTable' => $model !== 'notice' ? SseSheetVisibility::table($visReq, $this->service->visibilityFor($tenantId, $model)) : null,
            'defaultLevel' => SseSheetService::clampLevel($this->settingsDefault($tenantId), $r),
            'rights' => $r, 'back' => $kind === 'identite' ? url('atak/sse/papiers/identites/' . (int) $subject['identity_id']) : url('atak/sse/identites/' . (int) $subject['person_id'] . '?fiche=personne'),
            'readerLang' => SseWantedNoticeController::readerLang(),
        ], $status);
    }

    private function settingsDefault(int $tenantId): string
    {
        try {
            return (string) ((new \App\Repositories\SseWantedSettingsRepository())->get($tenantId)['default_classification'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function editorUrl(string $kind, int $id, string $model): string
    {
        return url('atak/sse/identites/emission/' . $kind . '/' . $id . '/' . $model);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data, int $status = 200): Response
    {
        $data += ['csrfToken' => Csrf::token()];
        $response = (new SseIdentityRegistryController($this->access, $this->clearance))->page($view, $data);

        return $status === 200 ? $response : $response->setStatusCode($status);
    }

    private function pdf(string $bytes, string $filename, bool $inline): Response
    {
        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($bytes);
    }

    private function r(): SseSheetAccess
    {
        return $this->rights ??= SseSheetAccess::fromSession($this->access, $this->clearance);
    }

    private function flashBack(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }
}
