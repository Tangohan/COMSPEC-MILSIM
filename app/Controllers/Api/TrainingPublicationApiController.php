<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TrainingPublication\TrainingPublicationService;

class TrainingPublicationApiController
{
    public function __construct(private TrainingPublicationService $service) {}

    public function create(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $publication = $this->service->createDraft($this->tenantId(), $this->userId(), $this->body($request));

            return Response::json(['publication' => $publication], 201);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function compile(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $compiled = $this->service->compile($id, $this->tenantId(), $this->userId());

            return Response::json($compiled);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function validatePublication(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $body = $this->body($request);
            $publication = $this->service->validate(
                $id,
                $this->tenantId(),
                $this->userId(),
                (string) ($body['actor_role'] ?? ''),
                (string) ($body['decision'] ?? 'approved'),
                isset($body['comment']) ? (string) $body['comment'] : null,
            );

            return Response::json(['publication' => $publication]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function release(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $publication = $this->service->release($id, $this->tenantId(), $this->userId());

            return Response::json(['publication' => $publication]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function addReadProgress(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $body = $this->body($request);
            $this->service->captureReadProof(
                $id,
                $this->tenantId(),
                $this->userId(),
                (int) ($body['seconds_read'] ?? 0),
                (int) ($body['last_page_reached'] ?? 0),
            );

            return Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function attestRead(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $body = $this->body($request);
            $this->service->attestReadProof(
                $id,
                $this->tenantId(),
                $this->userId(),
                isset($body['quiz_score']) ? (int) $body['quiz_score'] : null,
                isset($body['attestation']) ? (string) $body['attestation'] : null,
            );

            return Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function addAnnex(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $annexId = $this->service->addAnnex($id, $this->tenantId(), $this->userId(), $this->body($request));

            return Response::json(['annex_id' => $annexId], 201);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function obsolete(Request $request, array $params = []): Response
    {
        try {
            $this->validateCsrf($request);
            $id = (int) ($params['id'] ?? 0);
            $body = $this->body($request);
            $publication = $this->service->markObsolete(
                $id,
                $this->tenantId(),
                $this->userId(),
                isset($body['replacement_publication_id']) ? (int) $body['replacement_publication_id'] : null,
            );

            return Response::json(['publication' => $publication]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    private function tenantId(): int
    {
        $id = Session::get('tenant_id');
        if (!$id) {
            throw new \RuntimeException('Non autorisé.', 401);
        }

        return (int) $id;
    }

    private function userId(): int
    {
        $id = Session::get('user_id');
        if (!$id) {
            throw new \RuntimeException('Non autorisé.', 401);
        }

        return (int) $id;
    }

    private function validateCsrf(Request $request): void
    {
        $body = $this->body($request);
        $token = $request->input('_csrf_token') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf_token'] ?? null);
        if (!$token || !Csrf::validate($token)) {
            throw new \RuntimeException('Invalid CSRF token', 403);
        }
    }

    private function body(Request $request): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);

            return is_array($decoded) ? $decoded : [];
        }

        return array_merge($request->all(), $_POST);
    }

    private function error(\Throwable $e): Response
    {
        $code = (int) $e->getCode();
        if ($code < 400 || $code > 599) {
            $code = 400;
        }

        return Response::json(['error' => $e->getMessage()], $code);
    }
}
