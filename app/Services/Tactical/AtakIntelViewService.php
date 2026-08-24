<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Support\ComspecApiKeyAuth;
use App\Core\Session;

/**
 * Masquage roleplay du flux intel (certificat / compromission / brouillage).
 * Pas de vrai chiffrement : scramble déterministe pour affichage cohérent.
 */
final class AtakIntelViewService
{
    public const MODE_CLEAR = 'clear';
    public const MODE_ENCRYPTED = 'encrypted';
    public const MODE_JAMMED = 'jammed';

    public function __construct(
        private ?AtakRealismRepository $realism = null,
        private ?TenantAtakConfigRepository $atakConfig = null,
        private ?AtakDataRepository $atakData = null,
    ) {
        $this->realism ??= new AtakRealismRepository();
        $this->atakConfig ??= new TenantAtakConfigRepository();
        $this->atakData ??= new AtakDataRepository();
    }

    /**
     * @param array{
     *   is_game?:bool,
     *   terminal_uid?:?string,
     *   user_id?:?int,
     *   jam_intensity?:float|int|null,
     *   link_state?:?string,
     *   zone_type?:?string
     * } $identity
     * @return array{mode:string, reason:string, reason_label:string, intensity:float, scramble_enabled:bool}
     */
    public function resolveViewerMode(int $tenantId, int $mapId, array $identity = []): array
    {
        $roleplay = $this->atakConfig->getRoleplayConfig($tenantId);
        $scrambleOn = !empty($roleplay['intel_scramble_enabled']);
        $jamIntensity = $this->resolveJamIntensity($tenantId, $roleplay, $identity);

        if (!$scrambleOn) {
            if ($jamIntensity >= 25.0) {
                return $this->view(self::MODE_JAMMED, 'jamming', 'Brouillage — signal dégradé', $jamIntensity, false);
            }

            return $this->view(self::MODE_CLEAR, 'scramble_off', '', 0.0, false);
        }

        $isGame = (bool) ($identity['is_game'] ?? false);
        $terminalUid = trim((string) ($identity['terminal_uid'] ?? ''));

        // Appel jeu sans terminal_uid identifié (ex. serveur / DLL sans identifiant spécifique) :
        // on ne peut pas vérifier de certificat → traiter comme passerelle serveur (clair).
        if ($isGame && $terminalUid === '') {
            if ($jamIntensity >= 25.0) {
                return $this->view(self::MODE_JAMMED, 'jamming', 'Brouillage — signal dégradé', $jamIntensity, false);
            }
            return $this->view(self::MODE_CLEAR, 'game_server', '', 0.0, false);
        }

        if ($isGame || $terminalUid !== '') {
            $terminal = $terminalUid !== ''
                ? $this->realism->findTerminalByUid($tenantId, $terminalUid)
                : null;
            $compromise = strtolower(trim((string) ($terminal['compromise_state'] ?? 'none')));
            if (in_array($compromise, ['captured', 'compromised'], true)) {
                $label = $compromise === 'captured'
                    ? 'Terminal capturé — données illisibles'
                    : 'Terminal compromis — données illisibles';

                return $this->view(self::MODE_ENCRYPTED, 'compromised', $label, 100.0, true);
            }

            $terminalId = (int) ($terminal['id'] ?? 0);
            $cert = $terminalId > 0
                ? $this->realism->findLatestCertificateForTerminal($tenantId, $terminalId)
                : null;
            if ($cert === null || !$this->realism->certificateIsValid($cert)) {
                return $this->view(
                    self::MODE_ENCRYPTED,
                    'bad_certificate',
                    'Signal chiffré — certificat manquant ou invalide',
                    100.0,
                    true
                );
            }

            $defaultDomain = $this->realism->ensureDefaultCryptoDomain($tenantId);
            $defaultDomainId = (int) ($defaultDomain['id'] ?? 0);
            $certDomainId = (int) ($cert['crypto_domain_id'] ?? 0);
            if ($defaultDomainId > 0 && $certDomainId > 0 && $certDomainId !== $defaultDomainId) {
                // Domaine différent du réseau ami actif → gibberish (écoute hors réseau)
                $certDomain = $this->realism->findCryptoDomainById($tenantId, $certDomainId);
                $certStatus = (string) ($certDomain['status'] ?? '');
                if ($certStatus !== 'active') {
                    return $this->view(
                        self::MODE_ENCRYPTED,
                        'bad_domain',
                        'Signal chiffré — certificat manquant ou invalide',
                        100.0,
                        true
                    );
                }
            }
            if ($defaultDomainId > 0 && $certDomainId === 0) {
                // Ancien certificat sans domaine : toléré tant qu’il est valide
            }

            if ($jamIntensity >= 25.0) {
                return $this->view(self::MODE_JAMMED, 'jamming', 'Brouillage — signal dégradé', $jamIntensity, true);
            }

            return $this->view(self::MODE_CLEAR, 'ok', '', 0.0, true);
        }

        // TOC web authentifié : passerelle C2 (clair), sauf brouillage global fort
        $sessionUser = (int) ($identity['user_id'] ?? Session::get('user_id') ?? 0);
        if ($sessionUser > 0) {
            if ($jamIntensity >= 40.0) {
                return $this->view(self::MODE_JAMMED, 'jamming', 'Brouillage — signal dégradé', $jamIntensity, true);
            }

            return $this->view(self::MODE_CLEAR, 'toc_gateway', '', 0.0, true);
        }

        // Session anonyme / écoute sans certificat
        return $this->view(
            self::MODE_ENCRYPTED,
            'no_certificate',
            'Signal chiffré — certificat manquant ou invalide',
            100.0,
            true
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function scramblePayload(array $view, string $type, array $row): array
    {
        $mode = (string) ($view['mode'] ?? self::MODE_CLEAR);
        if ($mode === self::MODE_CLEAR || empty($view['scramble_enabled'])) {
            return $row;
        }
        $intensity = (float) ($view['intensity'] ?? 100.0);
        $entityId = (string) ($row['id'] ?? $row['call_sign'] ?? $row['arma_name'] ?? 'x');

        return match ($type) {
            'chat' => $this->scrambleChat($row, $mode, $intensity, $entityId),
            'order' => $this->scrambleOrder($row, $mode, $intensity, $entityId),
            'marker' => $this->scrambleMarker($row, $mode, $intensity, $entityId),
            'unit' => $this->scrambleUnit($row, $mode, $intensity, $entityId),
            'alert' => $this->scrambleAlert($row, $mode, $intensity, $entityId),
            default => $row,
        };
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function scrambleList(array $view, string $type, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $this->scramblePayload($view, $type, $row);
        }

        return $out;
    }

    /**
     * Alertes appareils pour TOC / Athena.
     *
     * @return list<array<string, mixed>>
     */
    public function collectDeviceAlerts(int $tenantId, int $mapId): array
    {
        $alerts = [];
        $now = time();

        if ($this->realism->tablesReady()) {
            foreach ($this->realism->listTerminals($tenantId) as $terminal) {
                if (AtakRealismRepository::isWebSessionTerminal($terminal)) {
                    continue;
                }
                $uid = (string) ($terminal['terminal_uid'] ?? '');
                $label = (string) ($terminal['terminal_label'] ?? $terminal['operator_callsign'] ?? $uid);
                $compromise = strtolower((string) ($terminal['compromise_state'] ?? 'none'));
                if (in_array($compromise, ['captured', 'compromised'], true)) {
                    $alerts[] = [
                        'code' => $compromise === 'captured' ? 'captured' : 'compromised',
                        'severity' => 'critical',
                        'title' => $compromise === 'captured' ? 'Appareil capturé' : 'Appareil compromis',
                        'message' => $label . ' — données illisibles jusqu’à reprise du contrôle.',
                        'call_sign' => (string) ($terminal['operator_callsign'] ?? ''),
                        'terminal_uid' => $uid,
                        'at' => (string) ($terminal['compromised_at'] ?? $terminal['updated_at'] ?? ''),
                    ];
                }
                $status = strtolower((string) ($terminal['status'] ?? ''));
                if (in_array($status, ['lost', 'revoked'], true)) {
                    $alerts[] = [
                        'code' => 'terminal_' . $status,
                        'severity' => 'warn',
                        'title' => $status === 'lost' ? 'Terminal perdu' : 'Terminal révoqué',
                        'message' => $label . ' n’est plus considéré comme fiable.',
                        'call_sign' => (string) ($terminal['operator_callsign'] ?? ''),
                        'terminal_uid' => $uid,
                        'at' => (string) ($terminal['updated_at'] ?? ''),
                    ];
                }
                $terminalId = (int) ($terminal['id'] ?? 0);
                if ($terminalId > 0) {
                    $cert = $this->realism->findLatestCertificateForTerminal($tenantId, $terminalId);
                    if ($cert === null || !$this->realism->certificateIsValid($cert)) {
                        $alerts[] = [
                            'code' => 'bad_certificate',
                            'severity' => 'warn',
                            'title' => 'Certificat invalide',
                            'message' => $label . ' — certificat manquant, expiré ou révoqué.',
                            'call_sign' => (string) ($terminal['operator_callsign'] ?? ''),
                            'terminal_uid' => $uid,
                            'at' => (string) ($cert['updated_at'] ?? $terminal['updated_at'] ?? ''),
                        ];
                    }
                }
            }
        }

        try {
            $units = $this->atakData->getUnits($tenantId, $mapId);
        } catch (\Throwable) {
            $units = [];
        }
        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }
            $cs = (string) ($unit['call_sign'] ?? '');
            $status = strtolower((string) ($unit['status'] ?? ''));
            $extra = AtakDataRepository::decodeExtra($unit['extra'] ?? null);
            $link = strtolower((string) ($extra['link_state'] ?? $status));
            $updated = strtotime((string) ($unit['updated_at'] ?? '')) ?: 0;
            $stale = $updated > 0 && ($now - $updated) > 180;

            if ($link === 'offline' || $status === 'offline' || $stale) {
                $alerts[] = [
                    'code' => 'offline',
                    'severity' => 'warn',
                    'title' => 'Hors liaison',
                    'message' => ($cs !== '' ? $cs : 'Unité') . ' — plus de contact fiable.',
                    'call_sign' => $cs,
                    'terminal_uid' => (string) ($extra['terminal_uid'] ?? ''),
                    'at' => (string) ($unit['updated_at'] ?? ''),
                ];
            } elseif ($link === 'degraded' || !empty($extra['zone_jammed']) || ($extra['zone_type'] ?? '') === 'jammer') {
                $alerts[] = [
                    'code' => 'jammed',
                    'severity' => 'warn',
                    'title' => 'Brouillage / liaison dégradée',
                    'message' => ($cs !== '' ? $cs : 'Unité') . ' — signal dégradé.',
                    'call_sign' => $cs,
                    'terminal_uid' => (string) ($extra['terminal_uid'] ?? ''),
                    'at' => (string) ($unit['updated_at'] ?? ''),
                ];
            }
            $damage = (int) ($extra['damage_level'] ?? $extra['atak_damage'] ?? 0);
            if ($damage >= 2) {
                $alerts[] = [
                    'code' => 'damaged',
                    'severity' => $damage >= 3 ? 'critical' : 'warn',
                    'title' => 'Appareil endommagé',
                    'message' => ($cs !== '' ? $cs : 'Unité') . ' — terminal endommagé (niveau ' . $damage . ').',
                    'call_sign' => $cs,
                    'terminal_uid' => (string) ($extra['terminal_uid'] ?? ''),
                    'at' => (string) ($unit['updated_at'] ?? ''),
                ];
            }
        }

        // Déduplique par code+callsign
        $seen = [];
        $unique = [];
        foreach ($alerts as $a) {
            $key = ($a['code'] ?? '') . '|' . ($a['call_sign'] ?? '') . '|' . ($a['terminal_uid'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $a;
        }

        return $unique;
    }

    /**
     * Construit l’identité viewer depuis une requête HTTP typique ATAK.
     *
     * @return array{is_game:bool, terminal_uid:?string, user_id:?int, jam_intensity:?float, link_state:?string, zone_type:?string}
     */
    public function identityFromRequest(
        ?string $terminalUid = null,
        ?int $userId = null,
        ?float $jamIntensity = null,
        ?string $linkState = null,
        ?string $zoneType = null
    ): array {
        $isGame = ComspecApiKeyAuth::extractPresentedKey() !== ''
            || ComspecApiKeyAuth::armaInlineAuthOk();

        return [
            'is_game' => $isGame,
            'terminal_uid' => $terminalUid !== null && $terminalUid !== '' ? $terminalUid : null,
            'user_id' => $userId,
            'jam_intensity' => $jamIntensity,
            'link_state' => $linkState,
            'zone_type' => $zoneType,
        ];
    }

    /**
     * @param array<string, mixed> $roleplay
     * @param array<string, mixed> $identity
     */
    private function resolveJamIntensity(int $tenantId, array $roleplay, array $identity): float
    {
        if (isset($identity['jam_intensity']) && is_numeric($identity['jam_intensity'])) {
            return max(0.0, min(100.0, (float) $identity['jam_intensity']));
        }
        $zone = strtolower((string) ($identity['zone_type'] ?? ''));
        $link = strtolower((string) ($identity['link_state'] ?? ''));
        if ($zone === 'jammer' || $link === 'zone_jammed') {
            return 85.0;
        }
        if ($zone === 'interference') {
            return 55.0;
        }
        if ($zone === 'degraded' || $link === 'degraded') {
            return 35.0;
        }
        if (!empty($roleplay['network_enabled'])) {
            $mode = (string) ($roleplay['network_mode'] ?? 'normal');
            $loss = (float) ($roleplay['packet_loss_percent'] ?? 0);
            if ($mode === 'hostile') {
                return max(50.0, $loss);
            }
            if ($mode === 'degraded') {
                return max(30.0, $loss);
            }
            if ($loss >= 25.0) {
                return $loss;
            }
        }

        return 0.0;
    }

    /**
     * @return array{mode:string, reason:string, reason_label:string, intensity:float, scramble_enabled:bool}
     */
    private function view(string $mode, string $reason, string $label, float $intensity, bool $enabled): array
    {
        return [
            'mode' => $mode,
            'reason' => $reason,
            'reason_label' => $label,
            'intensity' => max(0.0, min(100.0, $intensity)),
            'scramble_enabled' => $enabled,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrambleChat(array $row, string $mode, float $intensity, string $entityId): array
    {
        $row['author'] = $this->maskText((string) ($row['author'] ?? ''), $mode, $intensity, $entityId . ':author', 4, 18);
        $row['body'] = $this->maskText((string) ($row['body'] ?? ''), $mode, $intensity, $entityId . ':body', 12, 96);
        $row['_intel_scrambled'] = true;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrambleOrder(array $row, string $mode, float $intensity, string $entityId): array
    {
        foreach (['title', 'subject', 'body', 'content', 'summary', 'text', 'order_text', 'recipient_label'] as $field) {
            if (isset($row[$field]) && is_string($row[$field]) && $row[$field] !== '') {
                $row[$field] = $this->maskText($row[$field], $mode, $intensity, $entityId . ':' . $field, 8, 120);
            }
        }
        if (isset($row['grid_ref']) && is_string($row['grid_ref'])) {
            $row['grid_ref'] = $this->maskGrid($row['grid_ref'], $mode, $intensity, $entityId . ':grid');
        }
        $row['_intel_scrambled'] = true;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrambleMarker(array $row, string $mode, float $intensity, string $entityId): array
    {
        $raw = $row['markerData'] ?? null;
        $data = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (!is_array($data)) {
            $data = [];
        }
        foreach (['text', 'label', 'title', 'note', 'notes', 'description', 'grid', 'grid_ref'] as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                $data[$field] = $field === 'grid' || $field === 'grid_ref'
                    ? $this->maskGrid($data[$field], $mode, $intensity, $entityId . ':' . $field)
                    : $this->maskText($data[$field], $mode, $intensity, $entityId . ':' . $field, 6, 64);
            }
        }
        if (isset($data['lat']) && is_numeric($data['lat'])) {
            $data['lat'] = $this->jitterCoord((float) $data['lat'], $mode, $intensity, $entityId . ':lat');
        }
        if (isset($data['lng']) && is_numeric($data['lng'])) {
            $data['lng'] = $this->jitterCoord((float) $data['lng'], $mode, $intensity, $entityId . ':lng');
        }
        if (isset($data['x']) && is_numeric($data['x'])) {
            $data['x'] = $this->jitterCoord((float) $data['x'], $mode, $intensity, $entityId . ':x', 40.0);
        }
        if (isset($data['y']) && is_numeric($data['y'])) {
            $data['y'] = $this->jitterCoord((float) $data['y'], $mode, $intensity, $entityId . ':y', 40.0);
        }
        $row['markerData'] = is_string($raw) ? (json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $raw) : $data;
        $row['_intel_scrambled'] = true;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrambleUnit(array $row, string $mode, float $intensity, string $entityId): array
    {
        // Conserve id / status liaison pour alertes TOC
        if (isset($row['call_sign'])) {
            $row['call_sign'] = $this->maskText((string) $row['call_sign'], $mode, $intensity, $entityId . ':cs', 3, 12);
        }
        if (isset($row['grid_ref'])) {
            $row['grid_ref'] = $this->maskGrid((string) $row['grid_ref'], $mode, $intensity, $entityId . ':grid');
        }
        if (isset($row['pos_x']) && is_numeric($row['pos_x'])) {
            $row['pos_x'] = $this->jitterCoord((float) $row['pos_x'], $mode, $intensity, $entityId . ':px', 35.0);
        }
        if (isset($row['pos_y']) && is_numeric($row['pos_y'])) {
            $row['pos_y'] = $this->jitterCoord((float) $row['pos_y'], $mode, $intensity, $entityId . ':py', 35.0);
        }
        $extra = AtakDataRepository::decodeExtra($row['extra'] ?? null);
        foreach (['display_name', 'unit_name', 'role', 'notes', 'grid', 'grid_ref'] as $field) {
            if (isset($extra[$field]) && is_string($extra[$field]) && $extra[$field] !== '') {
                $extra[$field] = $field === 'grid' || $field === 'grid_ref'
                    ? $this->maskGrid($extra[$field], $mode, $intensity, $entityId . ':ex:' . $field)
                    : $this->maskText($extra[$field], $mode, $intensity, $entityId . ':ex:' . $field, 4, 40);
            }
        }
        $row['extra'] = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $row['extra'];
        $row['_intel_scrambled'] = true;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function scrambleAlert(array $row, string $mode, float $intensity, string $entityId): array
    {
        foreach (['message', 'body', 'text', 'summary', 'title', 'detail'] as $field) {
            if (isset($row[$field]) && is_string($row[$field]) && $row[$field] !== '') {
                $row[$field] = $this->maskText($row[$field], $mode, $intensity, $entityId . ':' . $field, 10, 100);
            }
        }
        if (isset($row['grid_ref'])) {
            $row['grid_ref'] = $this->maskGrid((string) $row['grid_ref'], $mode, $intensity, $entityId . ':grid');
        }
        if (isset($row['call_sign']) && $mode === self::MODE_ENCRYPTED) {
            $row['call_sign'] = $this->maskText((string) $row['call_sign'], $mode, $intensity, $entityId . ':cs', 3, 12);
        }
        $row['_intel_scrambled'] = true;

        return $row;
    }

    private function maskText(string $text, string $mode, float $intensity, string $seed, int $minLen, int $maxLen): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }
        $len = max($minLen, min($maxLen, mb_strlen($text)));
        if ($mode === self::MODE_ENCRYPTED) {
            return $this->gibberish($seed, $len);
        }
        // jammed : corruption partielle
        $ratio = max(0.15, min(0.85, $intensity / 100.0));
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($chars as $i => $ch) {
            if (preg_match('/\s/u', $ch)) {
                $out[] = $ch;
                continue;
            }
            $h = hexdec(substr(hash('sha256', $seed . '|' . $i), 0, 2)) / 255.0;
            $out[] = $h < $ratio ? $this->radioChar($seed, $i) : $ch;
        }

        return implode('', $out);
    }

    private function maskGrid(string $grid, string $mode, float $intensity, string $seed): string
    {
        $grid = trim($grid);
        if ($grid === '') {
            return $grid;
        }
        if ($mode === self::MODE_ENCRYPTED) {
            return $this->gibberish($seed, max(6, min(12, mb_strlen($grid))));
        }
        $digits = preg_replace('/\D+/', '', $grid) ?? '';
        if ($digits === '') {
            return $this->maskText($grid, $mode, $intensity, $seed, 6, 12);
        }
        $masked = $this->maskText($digits, $mode, $intensity, $seed, strlen($digits), strlen($digits));

        return preg_replace('/\d/', 'X', $grid) !== null
            ? substr($masked, 0, strlen($digits))
            : $masked;
    }

    private function jitterCoord(float $value, string $mode, float $intensity, string $seed, float $scale = 0.004): float
    {
        $h = hexdec(substr(hash('sha256', $seed), 0, 8));
        $signed = (($h % 2000) / 1000.0) - 1.0; // -1..1
        $factor = $mode === self::MODE_ENCRYPTED ? 1.0 : max(0.2, $intensity / 100.0);

        return $value + ($signed * $scale * $factor);
    }

    private function gibberish(string $seed, int $len): string
    {
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $this->radioChar($seed, $i);
        }

        return $out;
    }

    private function radioChar(string $seed, int $i): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $idx = hexdec(substr(hash('sha256', $seed . '#' . $i), 0, 2)) % strlen($alphabet);

        return $alphabet[$idx];
    }
}
