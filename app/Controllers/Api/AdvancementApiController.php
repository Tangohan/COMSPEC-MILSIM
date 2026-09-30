<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Services\Advancement\AdvancementService;
use Throwable;

final class AdvancementApiController
{
    public function __construct(
        private AdvancementRepository $repository,
        private AdvancementService $service,
    ) {}

    public function grades(Request $request, array $params = []): Response
    {
        return Response::json(['data' => $this->repository->listGrades($this->tenantId(), true)]);
    }

    public function storeGrade(Request $request, array $params = []): Response
    {
        return $this->mutate(function () use ($request): array {
            $id = $this->repository->saveGrade($this->tenantId(), $this->body($request));

            return ['id' => $id];
        }, 201);
    }

    public function updateGrade(Request $request, array $params = []): Response
    {
        return $this->mutate(function () use ($request, $params): array {
            $id = (int) ($params['id'] ?? 0);
            $this->repository->saveGrade($this->tenantId(), $this->body($request), $id);

            return ['id' => $id];
        });
    }

    public function archiveGrade(Request $request, array $params = []): Response
    {
        return $this->mutate(fn (): array => [
            'archived' => $this->repository->archiveGrade($this->tenantId(), (int) ($params['id'] ?? 0)),
        ]);
    }

    public function filieres(Request $request, array $params = []): Response
    {
        return Response::json(['data' => $this->repository->listFilieres($this->tenantId(), true)]);
    }

    public function storeFiliere(Request $request, array $params = []): Response
    {
        return $this->mutate(function () use ($request): array {
            $id = $this->repository->saveFiliere($this->tenantId(), $this->body($request));

            return ['id' => $id];
        }, 201);
    }

    public function campaigns(Request $request, array $params = []): Response
    {
        return Response::json(['data' => $this->repository->listCampaigns($this->tenantId())]);
    }

    public function storeCampaign(Request $request, array $params = []): Response
    {
        return $this->mutate(function () use ($request): array {
            $id = $this->repository->createCampaign(
                $this->tenantId(),
                $this->body($request),
                $this->actorId()
            );

            return ['id' => $id];
        }, 201);
    }

    public function showCampaign(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $campaign = $this->repository->findCampaign($this->tenantId(), $id);
        if ($campaign === null) {
            return Response::json(['error' => 'not_found'], 404);
        }

        return Response::json([
            'data' => $campaign,
            'candidacies' => $this->repository->listCandidacies($this->tenantId(), $id),
        ]);
    }

    public function apply(Request $request, array $params = []): Response
    {
        return $this->mutate(function () use ($request, $params): array {
            $body = $this->body($request);
            $personnelId = (int) ($body['personnel_id'] ?? $this->actorId());
            $id = $this->service->apply(
                $this->tenantId(),
                (int) ($params['id'] ?? 0),
                $personnelId,
                $body,
                $this->actorId()
            );

            return ['id' => $id];
        }, 201);
    }

    public function commission(Request $request, array $params = []): Response
    {
        return $this->mutate(fn (): array => [
            'transitioned' => $this->repository->updateCampaignStatus(
                $this->tenantId(),
                (int) ($params['id'] ?? 0),
                'open',
                'commission'
            ),
        ]);
    }

    public function publish(Request $request, array $params = []): Response
    {
        return $this->mutate(fn (): array => [
            'promoted' => $this->service->publish(
                $this->tenantId(),
                (int) ($params['id'] ?? 0),
                $this->actorId()
            ),
        ]);
    }

    private function mutate(callable $callback, int $status = 200): Response
    {
        try {
            return Response::json(['ok' => true] + $callback(), $status);
        } catch (Throwable $e) {
            return Response::json(['ok' => false, 'error' => 'invalid_request', 'message' => $e->getMessage()], 422);
        }
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : $_POST;
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function actorId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }
}
