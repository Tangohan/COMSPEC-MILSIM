<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;

/**
 * « Tout générer » de la fiche membre : crée en une fois ce qui manque pour que le membre parte en jeu avec ses papiers,
 * sans rien demander de ce qui se déduit, et sans jamais remplacer une valeur déjà saisie.
 *
 *  - Identité principale : créée depuis la fiche du personnage, sinon tirée au sort dans le pays ; existante, seuls ses
 *    champs vides sont complétés (date et lieu de naissance, nationalité, sexe).
 *  - Papiers de l'identité principale, émis : carte d'identité (passeport si le pays n'en délivre pas), permis de
 *    conduire si le pays en délivre, carte militaire si le membre a un grade. Numéro libre au format du pays, dates
 *    selon la durée du type, autorité du pays. Un brouillon ou un papier validé du même type est émis tel quel.
 *  - Couverture (habilitation Confidentiel) : chaque identité de couverture du membre reçoit sa pièce d'identité émise.
 *  - Kit : rien à composer, le kit automatique du paquet les envoie (identité réelle et couverture, selon le réglage).
 *
 * Résultat : une ligne lisible (« Créé : … · Déjà en place : … »). Journal d'audit « id_member.generate_all ».
 */
final class MemberIdentityAutoService
{
    /** @var callable(array<string, mixed>): void */
    private $audit;

    public function __construct(
        private ?MemberIdentityPapersService $papers = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseIdDocumentRepository $documents = null,
        private ?SseIdCountryRepository $countries = null,
        ?callable $audit = null,
    ) {
        $this->audit = $audit ?? static function (array $entry): void {
            try {
                (new SseAuditLogRepository())->write($entry);
            } catch (\Throwable $e) {
                error_log('Tout générer : journal d’audit indisponible : ' . $e->getMessage());
            }
        };
        $this->identities ??= new SseIdentityRepository();
        $this->documents ??= new SseIdDocumentRepository();
        $this->countries ??= new SseIdCountryRepository();
        $this->papers ??= new MemberIdentityPapersService($this->identities, $this->documents, new SseIdKitRepository(), null, $this->countries, $this->audit);
    }

    /**
     * @param array<string, mixed> $proposal MemberIdentityPapersService::primaryProposal() (fiche du personnage)
     * @return array{ok: bool, message: string, created: list<string>, kept: list<string>}
     */
    public function generateAll(int $tenantId, int $userId, array $proposal, bool $hasGrade, bool $truth, ?int $actorId, ?int $seed = null): array
    {
        $active = $this->countries->listForTenant($tenantId);
        if ($active === []) {
            return ['ok' => false, 'message' => 'Aucun pays de jeu actif : ajoutez-en un dans le registre des pays.', 'created' => [], 'kept' => []];
        }
        $created = [];
        $kept = [];
        $generator = new SseIdentityGeneratorService();
        $seed ??= SseIdentityGeneratorService::randomSeed();

        // --- Identité principale -------------------------------------------------------------------------------
        $primary = null;
        foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
            if (!empty($i['is_primary'])) {
                $primary = $i;
            }
        }
        $natCode = (string) ($primary['nationality'] ?? '') ?: (string) ($proposal['nationality'] ?? '');
        $country = ($natCode !== '' ? $this->countries->findByCode($tenantId, $natCode) : null);
        if ($country === null || !$country['active']) {
            $country = $active[0];
        }
        $sex = in_array($primary['sex'] ?? ($proposal['sex'] ?? 'X'), ['M', 'F'], true) ? (string) ($primary['sex'] ?? $proposal['sex']) : null;
        $drawn = $generator->generate($country, $seed, $sex);
        if ($primary === null) {
            $data = [];
            foreach (['last_name', 'first_name', 'birth_date', 'birth_place', 'sex', 'nationality', 'profession'] as $k) {
                $v = trim((string) ($proposal[$k] ?? ''));
                $data[$k] = ($v !== '' && !($k === 'sex' && $v === 'X')) ? $v : (string) ($drawn[$k] ?? '');
            }
            $data['nationality'] = (string) $country['code'];
            $res = $this->papers->savePrimary($tenantId, $userId, $data, $actorId);
            if (!$res['ok']) {
                return ['ok' => false, 'message' => (string) ($res['error'] ?? 'Identité principale non créée.'), 'created' => [], 'kept' => []];
            }
            $primary = $this->identities->find((int) $res['id'], $tenantId);
            $created[] = 'identité principale ' . MemberIdentityPapersService::displayName($primary);
        } else {
            $fill = [];
            $labels = ['birth_date' => 'date de naissance', 'birth_place' => 'lieu de naissance', 'nationality' => 'nationalité', 'sex' => 'sexe'];
            foreach ($labels as $k => $label) {
                $cur = trim((string) ($primary[$k] ?? ''));
                if ($cur === '' || ($k === 'sex' && $cur === 'X')) {
                    $v = trim((string) ($proposal[$k] ?? ''));
                    $fill[$k] = $k === 'nationality' ? (string) $country['code'] : (($v !== '' && $v !== 'X') ? $v : (string) ($drawn[$k] ?? ''));
                }
            }
            if ($fill !== []) {
                $this->identities->update((int) $primary['id'], $tenantId, array_merge($primary, $fill));
                $primary = $this->identities->find((int) $primary['id'], $tenantId);
                $created[] = 'identité principale complétée (' . implode(', ', array_map(static fn (string $k): string => $labels[$k], array_keys($fill))) . ')';
            } else {
                $kept[] = 'identité principale ' . $primary['ref'];
            }
        }
        $primary['is_primary'] = true;

        // --- Papiers ----------------------------------------------------------------------------------------------
        $types = [in_array('id_card', $country['doc_types'], true) ? 'id_card' : 'passport'];
        if (in_array('driving_license', $country['doc_types'], true)) {
            $types[] = 'driving_license';
        }
        if ($hasGrade && in_array('military_id', $country['doc_types'], true)) {
            $types[] = 'military_id';
        }
        foreach ($types as $type) {
            $this->ensurePaper($tenantId, $userId, $primary, $country, $type, $actorId, $created, $kept);
        }

        // --- Couverture (vérité de jeu) ----------------------------------------------------------------------------
        if ($truth) {
            foreach ($this->identities->listForUser($tenantId, $userId) as $i) {
                if (($i['kind'] ?? '') !== 'cover') {
                    continue;
                }
                $cc = ($i['nationality'] ?? null) !== null ? $this->countries->findByCode($tenantId, (string) $i['nationality']) : null;
                if ($cc === null || !$cc['active']) {
                    $cc = $country;
                }
                $this->ensurePaper($tenantId, $userId, $i, $cc, in_array('id_card', $cc['doc_types'], true) ? 'id_card' : 'passport', $actorId, $created, $kept, ' (couverture)');
            }
        }
        ($this->audit)([
            'tenant_id' => $tenantId, 'actor_user_id' => $actorId, 'actor_label' => 'Fiche membre', 'action' => 'id_member.generate_all',
            'object_type' => 'sse_identity', 'object_id' => (int) $primary['id'],
            'after' => ['member_user_id' => $userId, 'created' => $created, 'kept' => count($kept)],
        ]);
        $message = $created === []
            ? 'Rien à générer : tout est déjà en place (' . implode(', ', $kept) . ').'
            : 'Créé : ' . implode(', ', $created) . '.' . ($kept !== [] ? ' Déjà en place : ' . implode(', ', $kept) . '.' : '');

        return ['ok' => true, 'message' => $message, 'created' => $created, 'kept' => $kept];
    }

    /**
     * Un papier émis de ce type pour l'identité : déjà là (gardé), brouillon / validé (émis), sinon créé puis émis.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $country
     * @param list<string> $created
     * @param list<string> $kept
     */
    private function ensurePaper(int $tenantId, int $userId, array $identity, array $country, string $type, ?int $actorId, array &$created, array &$kept, string $suffix = ''): void
    {
        $label = \App\Support\SseIdentityCatalog::DOC_TYPES[$type] ?? $type;
        foreach ($this->documents->list($tenantId, ['user_id' => $userId, 'limit' => 200]) as $d) {
            if ((int) $d['identity_id'] !== (int) $identity['id'] || $d['doc_type'] !== $type || $d['workflow'] === 'revoked') {
                continue;
            }
            if ($d['workflow'] === 'issued') {
                $kept[] = mb_strtolower($label) . ' ' . $d['doc_number'] . $suffix;

                return;
            }
            $this->papers->action($tenantId, $userId, $d, 'issue', '', $actorId);
            $created[] = mb_strtolower($label) . ' ' . $d['doc_number'] . ' émis' . $suffix;

            return;
        }
        if (!in_array($type, $country['doc_types'], true)) {
            return;
        }
        $p = $this->papers->proposal($tenantId, $identity, $country, $type);
        $res = $this->papers->saveDocument($tenantId, $userId, $identity, null, [
            'doc_type' => $type, 'country_code' => $country['code'], 'doc_number' => $p['doc_number'],
            'issued_on' => $p['issued_on'], 'expires_on' => $p['expires_on'], 'authority' => $p['authority'],
        ], true, $actorId);
        if (!$res['ok']) {
            return;
        }
        $doc = $this->documents->find((int) $res['id'], $tenantId);
        if ($doc !== null) {
            $this->papers->action($tenantId, $userId, $doc, 'issue', '', $actorId);
        }
        $created[] = mb_strtolower($label) . ' ' . $country['code'] . ' ' . $p['doc_number'] . ' émis' . $suffix;
    }
}
