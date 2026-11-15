<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseWantedCatalog as C;
use App\Support\SseUsClassification;
use App\Support\SseWantedChecks;
use App\Support\SseWantedCodes;
use App\Support\SseWantedPhysical;
use App\Support\SseWantedRealNames;
use App\Support\SseWantedServices;

/**
 * Avis de recherche posés depuis la fiche d'une identité fictive : validation, un seul avis actif par identité, levée avec
 * motif obligatoire, trace dans la chronologie de l'identité et au journal d'audit SSE. L'avis est un fait de jeu de rôle :
 * aucune donnée réelle (ni membre, ni organisation).
 *
 * Les droits sont ceux de {@see SseWantedAccess} ; l'identité doit appartenir à la communauté de la session.
 */
final class SseWantedService
{
    public const MIN_TEXT = 3;

    public function __construct(
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdentityEventRepository $events = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseAuditLogRepository $audit = null,
        private ?SseWantedExtrasRepository $extras = null,
        private ?SseWantedMetaRepository $meta = null,
        private ?\Closure $notify = null,
        private ?\Closure $recipients = null,
    ) {
        $this->notices ??= new SseWantedNoticeRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->events ??= new SseIdentityEventRepository();
        $this->settings ??= new SseWantedSettingsRepository();
    }

    /**
     * Valide la saisie du formulaire « Placer un avis de recherche ».
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $config réglages de la communauté
     * @param string $lang langue du texte composé à partir des codes (« fr » ou « en »)
     * @return array{data: array<string, mixed>, errors: list<string>, service: array<string, mixed>|null}
     */
    public static function validate(array $input, array $config, ?\DateTimeImmutable $now = null, string $lang = 'fr'): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $errors = [];
        $types = C::types($config);
        $type = is_string($input['notice_type'] ?? null) ? $input['notice_type'] : '';
        if (!isset($types[$type])) {
            $errors[] = 'Choisissez un type d’avis.';
            $type = (string) (array_key_first($types) ?? 'poi');
        }
        $threat = is_string($input['threat_level'] ?? null) ? $input['threat_level'] : '';
        if (!isset(C::THREATS[$threat])) {
            $errors[] = 'Choisissez un niveau de menace.';
            $threat = 'medium';
        }
        $armed = is_string($input['armed'] ?? null) ? $input['armed'] : 'unknown';
        if (!isset(C::ARMED[$armed])) {
            $errors[] = 'État « armé et dangereux » invalide.';
            $armed = 'unknown';
        }
        // Service demandeur : un code du catalogue ou de la communauté préremplit service, coordonnées, autorité et valeurs par défaut ;
        // sans code (« Autre… »), la saisie reste libre mais passe le même contrôle des noms réels.
        $serviceCode = strtoupper(C::line($input['service_code'] ?? '', 30));
        $service = null;
        if ($serviceCode !== '') {
            $service = SseWantedServices::find($serviceCode, $config);
            if ($service === null) {
                $errors[] = 'Service demandeur inconnu : choisissez un service de la liste ou « Autre ».';
                $serviceCode = '';
            }
        }
        $classes = SseUsClassification::codes();
        $classification = is_string($input['classification'] ?? null) && in_array($input['classification'], $classes, true)
            ? (string) $input['classification']
            : (string) ($service['class'] ?? ($config['default_classification'] ?? 'encadrement'));
        $prefill = $service !== null ? SseWantedServices::prefill($service, $lang) : [];
        // Codes de motifs et de mesures : codes connus de la communauté seulement ; le texte vide est composé à partir d'eux.
        $selection = SseWantedCodes::normalizeSelection([
            SseWantedCodes::REASONS => $input['codes_reasons'] ?? [],
            SseWantedCodes::MEASURES => $input['codes_measures'] ?? [],
        ], $config);
        $reason = C::text($input['reason'] ?? '', C::REASON_MAX);
        if ($reason === '' && $selection[SseWantedCodes::REASONS] !== []) {
            $reason = SseWantedCodes::compose($selection[SseWantedCodes::REASONS], SseWantedCodes::REASONS, $lang === 'en' ? 'en' : 'fr', $config);
        }
        if (mb_strlen($reason) < self::MIN_TEXT) {
            $errors[] = 'Indiquez les motifs ou la raison de l’intérêt (au moins quelques mots).';
        }
        $instructions = C::text($input['instructions'] ?? '', C::INSTRUCTIONS_MAX);
        if ($instructions === '' && $selection[SseWantedCodes::MEASURES] !== []) {
            $instructions = SseWantedCodes::compose($selection[SseWantedCodes::MEASURES], SseWantedCodes::MEASURES, $lang === 'en' ? 'en' : 'fr', $config);
        }
        if ($instructions === '' && $prefill !== [] && is_string($prefill['instructions'] ?? null)) {
            $instructions = C::text($prefill['instructions'], C::INSTRUCTIONS_MAX);
        }
        if ($instructions === '') {
            $instructions = C::text($config['default_instructions_' . ($lang === 'en' ? 'en' : 'fr')] ?? '', C::INSTRUCTIONS_MAX);
        }
        $templateKey = is_string($input['codes_template'] ?? null) ? $input['codes_template'] : '';
        $template = $templateKey !== '' && isset(SseWantedCodes::templates($config)[$templateKey]) ? $templateKey : null;
        $physical = SseWantedPhysical::normalize(is_array($input['physical'] ?? null) ? $input['physical'] : []);
        foreach ([$physical['marks'] ?? '', $physical['tattoos'] ?? '', $physical['scars'] ?? '', $physical['clothing'] ?? '', $physical['notes'] ?? ''] as $text) {
            if (is_string($text) && SseWantedRealNames::find($text) !== null) {
                $errors[] = 'Le signalement ne cite pas d’organisation réelle : utilisez un texte fictif.';
                break;
            }
        }
        $issuedRaw = $input['issued_at'] ?? '';
        $issued = C::dateTime($issuedRaw);
        if (trim(is_scalar($issuedRaw) ? (string) $issuedRaw : '') !== '' && $issued === null) {
            $errors[] = 'Date d’émission invalide.';
        }
        $issued ??= $now->format('Y-m-d H:i:s');
        $expiresRaw = $input['expires_at'] ?? '';
        $expires = C::dateTime($expiresRaw);
        if (trim(is_scalar($expiresRaw) ? (string) $expiresRaw : '') !== '' && $expires === null) {
            $errors[] = 'Date d’expiration invalide.';
        }
        $serviceDays = $serviceCode !== '' ? (int) (SseWantedServices::find($serviceCode, $config)['exp'] ?? 0) : 0;
        $expiryDays = $serviceDays > 0 ? $serviceDays : (int) ($config['default_expiry_days'] ?? 0);
        $noExpiry = ($input['no_expiry'] ?? '') === '1';
        if ($expires === null && !$noExpiry && $expiryDays > 0 && trim(is_scalar($expiresRaw) ? (string) $expiresRaw : '') === '') {
            $expires = $now->modify('+' . $expiryDays . ' days')->format('Y-m-d H:i:s');
        }
        if ($expires !== null && $expires <= $issued) {
            $errors[] = 'La date d’expiration doit suivre la date d’émission.';
        }
        $seenRaw = $input['last_seen_at'] ?? '';
        $seen = C::dateTime($seenRaw);
        if (trim(is_scalar($seenRaw) ? (string) $seenRaw : '') !== '' && $seen === null) {
            $errors[] = 'Date du dernier lieu connu invalide.';
        }
        $contactLabel = C::line($input['contact_label'] ?? '', 160);
        $contactDetail = C::line($input['contact_detail'] ?? '', 200);
        if ($contactLabel === '') {
            $contactLabel = (string) ($prefill['contact_label'] ?? C::line($config['contact_label'] ?? '', 160));
        }
        if ($contactDetail === '') {
            $contactDetail = (string) ($prefill['contact_detail'] ?? C::line($config['contact_detail'] ?? '', 200));
        }
        foreach ([$contactLabel, $contactDetail, $reason, $instructions] as $text) {
            $hit = SseWantedRealNames::find($text);
            if ($hit !== null) {
                $errors[] = 'Un avis de jeu de rôle ne cite pas d’organisation réelle (« ' . $hit . ' ») : utilisez un service fictif.';
                break;
            }
        }
        $authority = $service !== null ? (string) $service['auth_fr'] : (string) ($config['authority_fr'] ?? '');

        return [
            'data' => [
                'notice_type' => $type,
                'threat_level' => $threat,
                'armed' => $armed,
                'reason' => $reason,
                'instructions' => $instructions,
                'zone' => C::line($input['zone'] ?? '', 120) ?: null,
                'last_seen_place' => C::line($input['last_seen_place'] ?? '', 160) ?: null,
                'last_seen_at' => $seen,
                'contact_label' => $contactLabel !== '' ? $contactLabel : null,
                'contact_detail' => $contactDetail !== '' ? $contactDetail : null,
                'issued_at' => $issued,
                'expires_at' => $expires,
                'authority' => $authority,
                'service_code' => $serviceCode !== '' ? $serviceCode : null,
                'classification' => $classification,
                'codes' => SseWantedCodes::snapshot($selection, $config, $template),
                'physical' => $physical,
            ],
            'service' => $service,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Place un avis sur une identité de la communauté.
     *
     * Contrôles de cohérence ({@see SseWantedChecks}) : un bloquant refuse la pose, un avertissement la laisse passer. Avec le circuit
     * de validation de la communauté, l'avis d'un utilisateur qui n'est pas gestionnaire part « à valider » : posé mais ni diffusé au
     * jeu ni signalé aux abonnés avant la décision d'un responsable.
     *
     * @param array<string, mixed> $input
     * @param array{is_member?: bool} $ctx ce que seul le serveur sait : l'identité est-elle celle d'un membre ?
     * @return array{ok: true, id: int, ref: string, warnings: list<string>, state: string}|array{ok: false, error: string, errors: list<string>}
     */
    public function place(int $tenantId, int $identityId, array $input, SseWantedAccess $access, array $ctx = []): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles (étape de base de données en attente).']];
        }
        $identity = $this->identities->find($identityId, $tenantId);
        if ($identity === null) {
            return ['ok' => false, 'error' => 'identity', 'errors' => ['Identité introuvable.']];
        }
        $config = $this->settings->get($tenantId);
        $lang = ($input['lang'] ?? '') === 'en' ? 'en' : 'fr';
        $checked = self::validate($input, $config, null, $lang);
        if ($checked['errors'] !== []) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => $checked['errors']];
        }
        $data = $checked['data'];
        $service = $checked['service'];
        $active = $this->safe(fn (): ?array => $this->notices->activeForIdentity($tenantId, $identityId));
        $found = SseWantedChecks::run($data, $service, [
            'classification' => (string) $data['classification'],
            'has_active' => $active !== null,
            'is_member' => !empty($ctx['is_member']),
        ]);
        if ($found['blocking'] !== []) {
            return ['ok' => false, 'error' => in_array('active', array_column($found['blocking'], 'code'), true) ? 'exists' : 'checks', 'errors' => array_column($found['blocking'], 'text')];
        }
        $warnings = array_column($found['warnings'], 'text');
        $codes = $data['codes'];
        $physical = $data['physical'];
        $classification = (string) $data['classification'];
        $serviceCode = $data['service_code'];
        unset($data['codes'], $data['physical'], $data['service_code'], $data['classification']);
        $id = $this->notices->create($tenantId, $identityId, $data, (string) $config['ref_format'], $access->userId());
        if ($id === null) {
            return ['ok' => false, 'error' => 'exists', 'errors' => ['Cette identité fait déjà l’objet d’un avis actif : levez-le avant d’en placer un autre.']];
        }
        $notice = $this->notices->find($tenantId, $id);
        $ref = (string) ($notice['ref'] ?? '');
        $hasCodes = $codes['reasons'] !== [] || $codes['measures'] !== [];
        if ($hasCodes || $physical !== null) {
            $saved = false;
            try {
                $saved = ($this->extras ??= new SseWantedExtrasRepository())->save($tenantId, $id, $hasCodes ? $codes : null, $physical);
            } catch (\Throwable $e) {
                error_log('Avis de recherche : codes non enregistrés : ' . $e->getMessage());
            }
            if (!$saved) {
                $warnings[] = 'L’avis est placé, mais ses codes et son signalement n’ont pas pu être enregistrés (étape de base de données « codification des avis » en attente) : le texte de l’avis est complet.';
            }
        }
        // Processus : service, classification, circuit de validation.
        $pending = !empty($config['process']['validation']) && !$access->canAdmin();
        $metaSaved = $this->saveMeta($tenantId, $id, [
            'service_code' => $serviceCode,
            'service' => $service !== null ? self::snapshot($service) : null,
            'classification' => $classification,
            'validation_state' => $pending ? SseWantedMetaRepository::PENDING : SseWantedMetaRepository::LIVE,
            'submitted_by_user_id' => $access->userId(),
        ]);
        if (!$metaSaved) {
            $pending = false;
            if ($serviceCode !== null || !empty($config['process']['validation'])) {
                $warnings[] = 'L’avis est placé avec le texte de son service seul : le code du service et le circuit de validation demandent l’étape de base de données « processus des avis », encore en attente. L’avis est diffusé sans validation.';
            }
        }
        $this->trace($tenantId, $identityId, $id, $pending ? 'notice.submitted' : 'notice.placed', sprintf(
            $pending ? 'Avis de recherche %s proposé à la validation : %s, menace %s.' : 'Avis de recherche %s placé : %s, menace %s.',
            $ref,
            C::typeLabel((string) $data['notice_type'], $config),
            mb_strtolower(C::label(C::THREATS, (string) $data['threat_level']))
        ), [
            'ref' => $ref, 'type' => $data['notice_type'], 'threat' => $data['threat_level'], 'armed' => $data['armed'],
            'reasons' => array_column($codes['reasons'], 'code'), 'measures' => array_column($codes['measures'], 'code'),
            'service' => $serviceCode, 'service_name' => $data['contact_label'], 'classification' => $classification,
        ], $access);
        $label = trim((string) ($identity['display_name'] ?? ''));
        if ($pending) {
            $this->tell($tenantId, $identityId, 'validate', ['ref' => $ref, 'label' => $label, 'path' => 'atak/sse/avis'], $access->userId(), true);
        } elseif (!empty($config['process']['notify'])) {
            $this->tell($tenantId, $identityId, 'placed', ['ref' => $ref, 'label' => $label, 'path' => 'atak/sse/avis'], $access->userId(), false);
        }

        return ['ok' => true, 'id' => $id, 'ref' => $ref, 'warnings' => $warnings, 'state' => $pending ? 'pending' : 'live'];
    }

    /**
     * Lève un avis actif (« lifted ») ou le clôt par la capture (« captured »). Le motif est obligatoire ; le service qui lève
     * (facultatif) est noté avec l'avis.
     *
     * @return array{ok: true}|array{ok: false, error: string, errors: list<string>}
     */
    public function lift(int $tenantId, int $noticeId, string $status, string $reason, SseWantedAccess $access, ?string $serviceCode = null): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles.']];
        }
        $status = in_array($status, C::CLOSING, true) ? $status : 'lifted';
        $reason = C::text($reason, 500);
        if (mb_strlen($reason) < self::MIN_TEXT) {
            return ['ok' => false, 'error' => 'reason', 'errors' => ['Le motif de la levée est obligatoire.']];
        }
        $notice = $this->notices->find($tenantId, $noticeId);
        if ($notice === null) {
            return ['ok' => false, 'error' => 'notice', 'errors' => ['Avis introuvable.']];
        }
        $liftingService = null;
        $code = strtoupper(C::line($serviceCode ?? '', 30));
        if ($code !== '') {
            $liftingService = SseWantedServices::find($code, $this->settings->get($tenantId));
            if ($liftingService === null) {
                return ['ok' => false, 'error' => 'service', 'errors' => ['Service lévant inconnu : choisissez un service de la liste.']];
            }
        }
        if (!$this->notices->close($tenantId, $noticeId, $status, $reason, $access->userId())) {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Cet avis n’est plus actif.']];
        }
        if ($liftingService !== null) {
            $this->saveMeta($tenantId, $noticeId, ['closing_service_code' => $code]);
        }
        $this->trace(
            $tenantId,
            (int) $notice['identity_id'],
            $noticeId,
            $status === 'captured' ? 'notice.captured' : 'notice.lifted',
            sprintf($status === 'captured' ? 'Avis %s clos : personne capturée.' : 'Avis %s levé.', (string) $notice['ref']) . ($liftingService !== null ? ' Service : ' . ($liftingService['ac'] !== '' ? $liftingService['ac'] : $liftingService['fr']) . '.' : ''),
            ['ref' => $notice['ref'], 'reason' => $reason, 'service' => $liftingService !== null ? $code : null],
            $access
        );

        return ['ok' => true];
    }

    /**
     * Renouvelle un avis : un NOUVEL avis lié au précédent (même identité, mêmes motifs, mesures, signalement et service), avec de
     * nouvelles dates. L'ancien est clos « expiré » avec la mention du renouvellement : jamais deux avis actifs.
     *
     * @param array<string, mixed> $input issued_at, expires_at (facultatives : maintenant, durée du service ou durée précédente)
     * @return array{ok: true, id: int, ref: string, warnings: list<string>}|array{ok: false, error: string, errors: list<string>}
     */
    public function renew(int $tenantId, int $noticeId, array $input, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles.']];
        }
        $old = $this->notices->find($tenantId, $noticeId);
        if ($old === null) {
            return ['ok' => false, 'error' => 'notice', 'errors' => ['Avis introuvable.']];
        }
        if (!in_array((string) $old['status'], ['active', 'expired'], true)) {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Seul un avis actif ou expiré se renouvelle : un avis levé ou clos par la capture reste clos.']];
        }
        if ((string) $old['status'] === 'expired' && $this->safe(fn (): ?array => $this->notices->activeForIdentity($tenantId, (int) $old['identity_id'])) !== null) {
            return ['ok' => false, 'error' => 'exists', 'errors' => ['Cette identité fait déjà l’objet d’un autre avis actif.']];
        }
        $config = $this->settings->get($tenantId);
        $meta = ($this->meta ??= new SseWantedMetaRepository())->forNotice($tenantId, $noticeId);
        $service = $meta['service_code'] !== null ? SseWantedServices::find($meta['service_code'], $config) : null;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $issuedRaw = $input['issued_at'] ?? '';
        $issued = C::dateTime($issuedRaw) ?? $now->format('Y-m-d H:i:s');
        if (trim(is_scalar($issuedRaw) ? (string) $issuedRaw : '') !== '' && C::dateTime($issuedRaw) === null) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['Date d’émission invalide.']];
        }
        $expiresRaw = $input['expires_at'] ?? '';
        $expires = C::dateTime($expiresRaw);
        if (trim(is_scalar($expiresRaw) ? (string) $expiresRaw : '') !== '' && $expires === null) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['Date d’expiration invalide.']];
        }
        if ($expires === null && ($input['no_expiry'] ?? '') !== '1') {
            $days = $service !== null && (int) $service['exp'] > 0 ? (int) $service['exp'] : 0;
            if ($days === 0 && $old['expires_at'] !== null) {
                $days = max(1, (int) round((strtotime((string) $old['expires_at'] . ' UTC') - strtotime((string) $old['issued_at'] . ' UTC')) / 86400));
            }
            if ($days === 0) {
                $days = (int) ($config['default_expiry_days'] ?? 0);
            }
            $expires = $days > 0 ? (new \DateTimeImmutable($issued, new \DateTimeZone('UTC')))->modify('+' . $days . ' days')->format('Y-m-d H:i:s') : null;
        }
        if ($expires !== null && $expires <= $issued) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['La date d’expiration doit suivre la date d’émission.']];
        }
        if ($expires !== null && $expires <= $now->format('Y-m-d H:i:s')) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['La date d’expiration est déjà passée : le renouvellement expirerait aussitôt.']];
        }
        $row = [
            'notice_type' => $old['notice_type'], 'threat_level' => $old['threat_level'], 'armed' => $old['armed'], 'reason' => $old['reason'],
            'instructions' => $old['instructions'], 'zone' => $old['zone'], 'last_seen_place' => $old['last_seen_place'], 'last_seen_at' => $old['last_seen_at'],
            'contact_label' => $old['contact_label'], 'contact_detail' => $old['contact_detail'], 'authority' => $old['authority'],
            'issued_at' => $issued, 'expires_at' => $expires,
        ];
        if ((string) $old['status'] === 'active' && !$this->notices->close($tenantId, $noticeId, 'expired', 'Renouvelé : voir l’avis suivant de cette identité.', $access->userId())) {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Cet avis n’est plus actif.']];
        }
        $id = $this->notices->create($tenantId, (int) $old['identity_id'], $row, (string) $config['ref_format'], $access->userId());
        if ($id === null) {
            if ((string) $old['status'] === 'active') {
                $this->notices->reopen($tenantId, $noticeId);
            }

            return ['ok' => false, 'error' => 'exists', 'errors' => ['Le renouvellement n’a pas pu être créé : l’avis précédent est resté en place.']];
        }
        $new = $this->notices->find($tenantId, $id);
        $ref = (string) ($new['ref'] ?? '');
        $warnings = [];
        $extras = ($this->extras ??= new SseWantedExtrasRepository())->forNotice($tenantId, $noticeId);
        $hasCodes = $extras['codes']['reasons'] !== [] || $extras['codes']['measures'] !== [];
        $physical = $extras['physical'] !== [] ? $extras['physical'] : null;
        if (($hasCodes || $physical !== null) && !$this->extras->save($tenantId, $id, $hasCodes ? $extras['codes'] : null, $physical)) {
            $warnings[] = 'Les codes et le signalement n’ont pas pu être repris sur le nouvel avis.';
        }
        $pending = !empty($config['process']['validation']) && !$access->canAdmin();
        $metaSaved = $this->saveMeta($tenantId, $id, [
            'service_code' => $meta['service_code'],
            'service' => $meta['service'] !== [] ? $meta['service'] : null,
            'classification' => $meta['classification'],
            'parent_notice_id' => $noticeId,
            'validation_state' => $pending ? SseWantedMetaRepository::PENDING : SseWantedMetaRepository::LIVE,
            'submitted_by_user_id' => $access->userId(),
        ]);
        if (!$metaSaved) {
            $pending = false;
            $warnings[] = 'Le lien avec l’avis précédent n’a pas pu être noté (étape de base de données « processus des avis » en attente).';
        }
        $warnings[] = 'Les images jointes de l’avis précédent ne sont pas reprises : joignez-les de nouveau si besoin.';
        $this->trace($tenantId, (int) $old['identity_id'], $id, 'notice.renewed', sprintf('Avis %s renouvelé par l’avis %s.', (string) $old['ref'], $ref), [
            'ref' => $ref, 'previous_ref' => $old['ref'], 'previous_id' => $noticeId, 'issued_at' => $issued, 'expires_at' => $expires, 'service' => $meta['service_code'],
        ], $access);
        if ($pending) {
            $this->tell($tenantId, (int) $old['identity_id'], 'validate', ['ref' => $ref, 'label' => '', 'path' => 'atak/sse/avis'], $access->userId(), true);
        }

        return ['ok' => true, 'id' => $id, 'ref' => $ref, 'warnings' => $warnings];
    }

    /**
     * Prolonge un avis actif : nouvelle date d'expiration (postérieure à l'actuelle), motif obligatoire, version ajoutée à
     * l'historique de l'avis et à la chronologie de l'identité.
     *
     * @return array{ok: true, expires_at: string}|array{ok: false, error: string, errors: list<string>}
     */
    public function extend(int $tenantId, int $noticeId, string $newExpires, string $reason, SseWantedAccess $access): array
    {
        if (!$access->canManage() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['Action non autorisée.']];
        }
        if (!SseWantedNoticeRepository::ready()) {
            return ['ok' => false, 'error' => 'unavailable', 'errors' => ['Les avis de recherche ne sont pas encore disponibles.']];
        }
        $reason = C::text($reason, 500);
        if (mb_strlen($reason) < self::MIN_TEXT) {
            return ['ok' => false, 'error' => 'reason', 'errors' => ['Le motif de la prolongation est obligatoire.']];
        }
        $notice = $this->notices->find($tenantId, $noticeId);
        if ($notice === null) {
            return ['ok' => false, 'error' => 'notice', 'errors' => ['Avis introuvable.']];
        }
        if ((string) $notice['status'] !== 'active') {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Seul un avis actif se prolonge : renouvelez un avis expiré.']];
        }
        $to = C::dateTime($newExpires);
        if ($to === null) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['Nouvelle date d’expiration invalide.']];
        }
        if ($notice['expires_at'] !== null && $to <= (string) $notice['expires_at']) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['La nouvelle date doit être postérieure à la date d’expiration actuelle.']];
        }
        if ($to <= gmdate('Y-m-d H:i:s')) {
            return ['ok' => false, 'error' => 'invalid', 'errors' => ['La nouvelle date est déjà passée.']];
        }
        if (!$this->notices->extend($tenantId, $noticeId, $to)) {
            return ['ok' => false, 'error' => 'closed', 'errors' => ['Cet avis n’est plus actif.']];
        }
        $from = $notice['expires_at'];
        ($this->meta ??= new SseWantedMetaRepository())->addHistory($tenantId, $noticeId, [
            'kind' => 'extended', 'at' => gmdate('Y-m-d H:i:s'), 'by' => $access->actorLabel(), 'from' => $from, 'to' => $to, 'reason' => $reason,
        ]);
        $this->trace($tenantId, (int) $notice['identity_id'], $noticeId, 'notice.extended', sprintf('Avis %s prolongé jusqu’au %s.', (string) $notice['ref'], $to), [
            'ref' => $notice['ref'], 'from' => $from, 'to' => $to, 'reason' => $reason,
        ], $access);

        return ['ok' => true, 'expires_at' => $to];
    }

    /**
     * Décision d'un responsable sur un avis proposé : diffusé (« approve ») ou refusé avec un motif. Un avis refusé est clos : il ne
     * reste jamais actif sans avoir été diffusé.
     *
     * @return array{ok: true, state: string}|array{ok: false, error: string, errors: list<string>}
     */
    public function decide(int $tenantId, int $noticeId, bool $approve, string $reason, SseWantedAccess $access): array
    {
        if (!$access->canAdmin() || $access->tenantId() !== $tenantId) {
            return ['ok' => false, 'error' => 'forbidden', 'errors' => ['La validation est réservée aux gestionnaires de la section.']];
        }
        $reason = C::text($reason, 500);
        if (!$approve && mb_strlen($reason) < self::MIN_TEXT) {
            return ['ok' => false, 'error' => 'reason', 'errors' => ['Le motif du refus est obligatoire.']];
        }
        $notice = SseWantedNoticeRepository::ready() ? $this->notices->find($tenantId, $noticeId) : null;
        if ($notice === null) {
            return ['ok' => false, 'error' => 'notice', 'errors' => ['Avis introuvable.']];
        }
        $meta = ($this->meta ??= new SseWantedMetaRepository())->forNotice($tenantId, $noticeId);
        if ($meta['state'] !== SseWantedMetaRepository::PENDING) {
            return ['ok' => false, 'error' => 'state', 'errors' => ['Cet avis n’est pas en attente de validation.']];
        }
        if ($meta['submitted_by'] !== null && $meta['submitted_by'] === $access->userId()) {
            return ['ok' => false, 'error' => 'self', 'errors' => ['Un avis proposé par vous-même est validé par un autre responsable.']];
        }
        if (!$this->meta->decide($tenantId, $noticeId, $approve ? SseWantedMetaRepository::LIVE : SseWantedMetaRepository::REJECTED, $access->userId(), $reason)) {
            return ['ok' => false, 'error' => 'state', 'errors' => ['Cet avis n’est pas en attente de validation.']];
        }
        if (!$approve) {
            $this->notices->close($tenantId, $noticeId, 'lifted', 'Refusé à la validation : ' . $reason, $access->userId());
        }
        $this->trace($tenantId, (int) $notice['identity_id'], $noticeId, $approve ? 'notice.validated' : 'notice.rejected', sprintf($approve ? 'Avis %s validé et diffusé.' : 'Avis %s refusé à la validation.', (string) $notice['ref']), [
            'ref' => $notice['ref'], 'reason' => $reason,
        ], $access);
        $config = $this->settings->get($tenantId);
        if ($meta['submitted_by'] !== null) {
            $this->tell($tenantId, (int) $notice['identity_id'], $approve ? 'validated' : 'rejected', ['ref' => (string) $notice['ref'], 'label' => '', 'path' => 'atak/sse/avis'], $access->userId(), false, [$meta['submitted_by']]);
        }
        if ($approve && !empty($config['process']['notify'])) {
            $this->tell($tenantId, (int) $notice['identity_id'], 'placed', ['ref' => (string) $notice['ref'], 'label' => '', 'path' => 'atak/sse/avis'], $access->userId(), false);
        }

        return ['ok' => true, 'state' => $approve ? 'live' : 'rejected'];
    }

    /**
     * Passe en « expiré » les avis arrivés à échéance et le note dans la chronologie (une seule fois, par la fermeture).
     */
    public function expireDue(int $tenantId, ?int $identityId = null): void
    {
        if (!SseWantedNoticeRepository::ready()) {
            return;
        }
        try {
            foreach ($this->notices->expireDue($tenantId, $identityId) as $n) {
                $this->events->add($tenantId, (int) $n['identity_id'], 'notice.expired', 'Avis ' . $n['ref'] . ' expiré (date d’expiration atteinte).', ['ref' => $n['ref']], null, 'Système', 'base', 'system', (int) $n['id']);
            }
        } catch (\Throwable $e) {
            error_log('Avis de recherche : expiration non notée : ' . $e->getMessage());
        }
    }

    /**
     * Trace d'une génération de PDF (les événements ne sont jamais effacés).
     */
    public function tracePdf(int $tenantId, int $noticeId, string $lang, SseWantedAccess $access): void
    {
        $notice = $this->notices->find($tenantId, $noticeId);
        if ($notice === null) {
            return;
        }
        try {
            $this->events->add($tenantId, (int) $notice['identity_id'], 'notice.pdf', 'Notice PDF ' . strtoupper($lang) . ' générée pour l’avis ' . $notice['ref'] . '.', ['ref' => $notice['ref'], 'lang' => $lang], $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : génération du PDF non notée : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $details */
    private function trace(int $tenantId, int $identityId, int $noticeId, string $type, string $summary, array $details, SseWantedAccess $access): void
    {
        try {
            $this->events->add($tenantId, $identityId, $type, $summary, $details, $access->userId(), $access->actorLabel(), 'base', 'web', $noticeId);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : chronologie indisponible : ' . $e->getMessage());
        }
        try {
            ($this->audit ??= new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $access->userId(),
                'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Avis de recherche',
                'action' => $type,
                'object_type' => 'sse_wanted_notice',
                'object_id' => $noticeId,
                'reason' => (string) ($details['reason'] ?? ''),
                'after' => $details,
            ]);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : audit indisponible : ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private function saveMeta(int $tenantId, int $noticeId, array $data): bool
    {
        try {
            return ($this->meta ??= new SseWantedMetaRepository())->save($tenantId, $noticeId, $data);
        } catch (\Throwable $e) {
            error_log('Avis de recherche : processus non enregistré : ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Instantané d'un service au jour de la pose : l'avis garde son nom, son sigle, ses coordonnées et sa couleur même si le service
     * est modifié ou retiré ensuite.
     *
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    public static function snapshot(array $service): array
    {
        $palette = SseWantedServices::palette();

        return [
            'code' => $service['code'], 'fr' => $service['fr'], 'en' => $service['en'], 'ac' => $service['ac'],
            'auth_fr' => $service['auth_fr'], 'auth_en' => $service['auth_en'], 'contact' => SseWantedServices::contactLine($service),
            'color' => $palette[$service['color']] ?? '#1F3A5F', 'emblem' => $service['emblem'],
        ];
    }

    /**
     * Notifie : « placed » aux abonnés (équipes, surveillants de l'identité), « validate » aux gestionnaires, « validated » et
     * « rejected » à l'auteur. Jamais bloquant.
     *
     * @param array<string, mixed> $payload
     * @param list<int>|null $only destinataires imposés
     */
    private function tell(int $tenantId, int $identityId, string $event, array $payload, ?int $authorId, bool $managersOnly, ?array $only = null): void
    {
        try {
            $ids = $only ?? ($this->recipients !== null
                ? ($this->recipients)($tenantId, $identityId, $authorId, $managersOnly)
                : self::defaultRecipients($tenantId, $identityId, $managersOnly));
            $notify = $this->notify ?? static function (int $tenantId, int $userId, array $payload): void {
                (new \App\Repositories\MemberNotificationRepository())->create($tenantId, $userId, 'sse_wanted_notice', $payload);
            };
            foreach (array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, 100) as $userId) {
                if ($userId === $authorId && $only === null) {
                    continue;
                }
                $notify($tenantId, $userId, $payload + ['event' => $event]);
            }
        } catch (\Throwable $e) {
            error_log('Avis de recherche : notification non envoyée : ' . $e->getMessage());
        }
    }

    /** @return list<int> */
    private static function defaultRecipients(int $tenantId, int $identityId, bool $managersOnly): array
    {
        $ids = [];
        try {
            $slugs = $managersOnly ? ['atak.sse.files.admin', 'atak.sse.grant'] : ['atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin'];
            $ids = (new \App\Repositories\UserRepository())->listActiveUserIdsWithAnyPermissionSlug($tenantId, $slugs);
        } catch (\Throwable) {
        }
        if (!$managersOnly) {
            try {
                foreach (\App\Core\Database::getInstance()->fetchAll('SELECT DISTINCT user_id FROM sse_file_watches WHERE tenant_id = :t AND identity_id = :i LIMIT 50', ['t' => $tenantId, 'i' => $identityId]) as $r) {
                    $ids[] = (int) $r['user_id'];
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T|null
     */
    private function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
