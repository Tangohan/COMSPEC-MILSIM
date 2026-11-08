<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAiProfileRepository;

/**
 * Profils d'IA, côté mod : même canal que la bibliothèque de l'éditeur (SubmitIdCheck → POST /api/sse/identity/checks,
 * champ « kind », réponse « OK|id|ref ») : aucune nouvelle DLL.
 *   ai_profiles  ref = lignes pour parseSimpleArray [id, réf., libellé, nom, camp, catégorie] (liste d'Eden, hors partie)
 *   ai_keep      id = identifiant de l'élément gardé pour les prochaines parties, ref = kept
 *   ai_unkeep    id = identifiant retiré (0 : inconnu), ref = removed | unknown
 * Au démarrage d'une mission, le serveur reçoit profils et éléments gardés dans le paquet Identity (bloc « settings »,
 * que la DLL publiée transmet déjà).
 */
final class SseAiProfileService
{
    public const KINDS = ['ai_profiles', 'ai_keep', 'ai_unkeep'];
    private const PAGE_BYTES = 7400;

    public function __construct(private ?SseAiProfileRepository $profiles = null)
    {
        $this->profiles ??= new SseAiProfileRepository();
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string}
     */
    public function handle(int $tenantId, ?int $userId, int $mapId, array $body): array
    {
        $op = is_string($body['op'] ?? null) ? $body['op'] : null;

        return match ($body['kind'] ?? null) {
            'ai_profiles' => ['id' => 0, 'ref' => self::listLine($this->profiles->forPack($tenantId, $mapId, $op))],
            'ai_keep' => is_array($body['place'] ?? null)
                ? ['id' => $this->profiles->addPlacement($tenantId, $mapId, $op, $body['place'], $userId), 'ref' => 'kept']
                : ['error' => 'invalid'],
            'ai_unkeep' => $this->unkeep($tenantId, is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0),
            default => ['error' => 'invalid'],
        };
    }

    /** @return array{id: int, ref: string} */
    private function unkeep(int $tenantId, int $id): array
    {
        return $this->profiles->removePlacement($id, $tenantId) ? ['id' => $id, 'ref' => 'removed'] : ['id' => 0, 'ref' => 'unknown'];
    }

    /**
     * Texte d'une ligne, sans « | » ni retour à la ligne, lisible par parseSimpleArray ; coupé à la taille d'une réponse.
     *
     * @param list<array<string, mixed>> $rows
     */
    public static function listLine(array $rows): string
    {
        $clean = static fn (string $s): string => str_replace(['"', '|', "\n", "\r"], ['""', ' ', ' ', ' '], $s);
        $out = [];
        $size = 2;
        foreach ($rows as $r) {
            $line = sprintf(
                '[%d,"%s","%s","%s","%s","%s"]',
                (int) $r['id'], $clean((string) $r['ref']), $clean((string) $r['label']), $clean((string) $r['name']), $clean((string) $r['side']), $clean((string) $r['category'])
            );
            if ($size + strlen($line) + 1 > self::PAGE_BYTES) {
                break;
            }
            $out[] = $line;
            $size += strlen($line) + 1;
        }

        return '[' . implode(',', $out) . ']';
    }
}
