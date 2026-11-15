<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

use App\Repositories\EnlistmentRepository;
use App\Repositories\MemberIntegrationPackageRepository as Packages;
use PDO;
use Throwable;

/**
 * Paquet d'intégration : à la fin du recrutement, au moment où l'on intègre un candidat accepté, tout ce que reçoit
 * le nouveau membre (grade, unité, emploi, niveaux d'accès, qualifications d'office, formations, mot d'accueil) est
 * appliqué d'un coup.
 *
 * - Sans paquet choisi, l'intégration se passe exactement comme avant (le parcours existant, sans transaction ajoutée).
 * - Avec un paquet : tout est validé avant la moindre écriture, puis appliqué dans une seule transaction. Une étape qui
 *   échoue annule le tout et le message dit laquelle.
 * - Rejouable : une qualification déjà détenue, une formation déjà suivie ne sont pas refaites ; rien n'est journalisé
 *   ni envoyé une seconde fois.
 * - Les droits du recruteur plafonnent ce qu'il peut attribuer : un élément qu'il n'a pas le droit de donner n'est pas
 *   appliqué et reste signalé « à attribuer par un responsable ».
 * - Les qualifications passent par les règles des qualifications (prérequis, délivrance à la main), jamais en direct.
 */
final class MemberIntegrationPackageService
{
    public function __construct(
        private PDO $pdo,
        private Packages $packages,
        private EnlistmentRepository $enlistments,
        private IntegrationPorts $ports,
    ) {
    }

    /**
     * Ce qui sera proposé au recruteur pour une candidature : le paquet retenu et ses éléments, avec ce que ses droits
     * lui permettent de donner.
     *
     * @param array<string, mixed> $enlistment
     * @param array{qualify?: bool, enroll?: bool} $caps
     * @return array{package: ?array<string, mixed>, qualifications: list<array<string, mixed>>, courses: list<array<string, mixed>>, application: ?array<string, mixed>, hasPackages: bool}
     */
    public function preview(int $tenantId, array $enlistment, int $unitId, array $caps, int $forcePackageId = 0): array
    {
        $empty = ['package' => null, 'qualifications' => [], 'courses' => [], 'application' => null, 'hasPackages' => false];
        if (!$this->packages->tablesExist()) {
            return $empty;
        }
        $all = $this->packages->listForTenant($tenantId, true);
        $empty['hasPackages'] = $all !== [];
        $package = $forcePackageId > 0
            ? $this->packages->find($tenantId, $forcePackageId)
            : $this->packages->resolve($tenantId, (int) ($enlistment['recruitment_opening_id'] ?? 0), $unitId);
        if ($package === null) {
            return $empty;
        }
        $items = $this->packages->items($tenantId, (int) $package['id']);
        $qNames = $this->packages->qualificationNames($tenantId, array_column(array_filter($items, static fn (array $i): bool => $i['item_type'] === Packages::ITEM_QUALIFICATION), 'ref_id'));
        $cNames = $this->packages->courseNames($tenantId, array_column(array_filter($items, static fn (array $i): bool => $i['item_type'] === Packages::ITEM_COURSE), 'ref_id'));
        $quals = [];
        $courses = [];
        foreach ($items as $it) {
            if ($it['item_type'] === Packages::ITEM_QUALIFICATION) {
                $name = $qNames[$it['ref_id']] ?? null;
                $blocker = $name === null ? 'Cette qualification n’existe plus : retirez-la du paquet.' : $this->ports->qualificationBlocker($tenantId, $it['ref_id']);
                $needsRights = $blocker === null && empty($caps['qualify']);
                $quals[] = $it + [
                    'name' => $name ?? ('#' . $it['ref_id']),
                    'blocked' => $blocker !== null,
                    'needs_rights' => $needsRights,
                    'reason' => $blocker !== null
                        ? ($name === null ? $blocker : 'Ne peut pas être délivrée à l’intégration : ' . $blocker)
                        : ($needsRights ? 'Vous n’avez pas le droit d’attribuer des qualifications : elle restera à attribuer par un responsable.' : ''),
                ];
            } else {
                $name = $cNames[$it['ref_id']] ?? null;
                $needsRights = $name !== null && empty($caps['enroll']);
                $courses[] = $it + [
                    'name' => $name ?? ('#' . $it['ref_id']),
                    'blocked' => $name === null,
                    'needs_rights' => $needsRights,
                    'reason' => $name === null ? 'Cette formation n’existe plus : retirez-la du paquet.' : ($needsRights ? 'Vous n’avez pas le droit d’inscrire à une formation : elle restera à attribuer par un responsable.' : ''),
                ];
            }
        }

        return [
            'package' => $package,
            'qualifications' => $quals,
            'courses' => $courses,
            'application' => $this->packages->application($tenantId, (int) ($enlistment['id'] ?? 0)),
            'hasPackages' => true,
        ];
    }

    /**
     * Applique l'intégration. $selection : package_id (0 : pas de paquet), qualification_ids et course_ids retenus par
     * le recruteur parmi ceux du paquet (un identifiant étranger au paquet est refusé).
     *
     * @param array<string, mixed> $options options du parcours d'intégration déjà normalisées et plafonnées par ses droits
     * @param array{package_id?: int, qualification_ids?: list<int>, course_ids?: list<int>} $selection
     * @param array{qualify?: bool, enroll?: bool} $caps
     * @return array{ok: bool, message: ?string, user_id?: int, applied?: array<string, mixed>, unchanged?: bool}
     */
    public function apply(int $tenantId, int $enlistmentId, int $actorId, array $options, array $selection, array $caps): array
    {
        $enlistment = $this->enlistments->findForTenant($tenantId, $enlistmentId);
        if (!$enlistment || (string) ($enlistment['status'] ?? '') !== 'reviewed') {
            return ['ok' => false, 'message' => 'Seules les candidatures acceptées peuvent être intégrées.'];
        }
        $packageId = (int) ($selection['package_id'] ?? 0);
        if ($packageId < 1) {
            // Comportement d'avant les paquets : le parcours d'intégration seul.
            $base = $this->ports->applyBase($tenantId, $enlistmentId, $actorId, $options);

            return $base + ['ok' => false, 'message' => null];
        }
        $package = $this->packages->find($tenantId, $packageId);
        if ($package === null) {
            return ['ok' => false, 'message' => 'Ce paquet d’intégration n’existe pas dans votre communauté. Rien n’a été appliqué.'];
        }

        $items = $this->packages->items($tenantId, $packageId);
        $inPackage = ['qualification' => [], 'course' => []];
        foreach ($items as $it) {
            $inPackage[$it['item_type']][$it['ref_id']] = $it;
        }
        $wantQ = $this->ids($selection['qualification_ids'] ?? []);
        $wantC = $this->ids($selection['course_ids'] ?? []);
        foreach ($wantQ as $id) {
            if (!isset($inPackage['qualification'][$id])) {
                return ['ok' => false, 'message' => 'Une qualification choisie ne fait pas partie du paquet. Rien n’a été appliqué.'];
            }
        }
        foreach ($wantC as $id) {
            if (!isset($inPackage['course'][$id])) {
                return ['ok' => false, 'message' => 'Une formation choisie ne fait pas partie du paquet. Rien n’a été appliqué.'];
            }
        }

        // Plafond des droits : ce que le recruteur n'a pas le droit de donner n'est pas appliqué, et reste signalé.
        $toAward = [];
        $toEnroll = [];
        $pending = [];
        foreach ($wantQ as $id) {
            if (empty($caps['qualify'])) {
                $pending[] = ['type' => 'qualification', 'ref_id' => $id];
            } else {
                $toAward[] = $inPackage['qualification'][$id];
            }
        }
        foreach ($wantC as $id) {
            if (empty($caps['enroll'])) {
                $pending[] = ['type' => 'course', 'ref_id' => $id];
            } else {
                $toEnroll[] = $inPackage['course'][$id];
            }
        }

        // Tout est vérifié avant la moindre écriture.
        $qNames = $this->packages->qualificationNames($tenantId, array_merge(array_column($toAward, 'ref_id'), array_column($pending, 'ref_id')));
        $cNames = $this->packages->courseNames($tenantId, array_column($toEnroll, 'ref_id'));
        foreach ($toAward as $it) {
            if (!isset($qNames[$it['ref_id']])) {
                return ['ok' => false, 'message' => 'Une qualification du paquet n’existe plus dans votre communauté : retirez-la du paquet ou de la sélection. Rien n’a été appliqué.'];
            }
            $blocker = $this->ports->qualificationBlocker($tenantId, $it['ref_id']);
            if ($blocker !== null) {
                return ['ok' => false, 'message' => '« ' . $qNames[$it['ref_id']] . ' » : ' . $blocker . ' Retirez-la de la sélection. Rien n’a été appliqué.'];
            }
        }
        foreach ($toEnroll as $it) {
            if (!isset($cNames[$it['ref_id']])) {
                return ['ok' => false, 'message' => 'Une formation du paquet n’existe plus dans votre communauté : retirez-la du paquet ou de la sélection. Rien n’a été appliqué.'];
            }
        }

        $userId = $this->ports->ensureAccount($tenantId, $enlistmentId, $actorId);
        if ($userId < 1) {
            return ['ok' => false, 'message' => 'Le compte membre n’a pas pu être créé ou lié. Rien du paquet n’a été appliqué ; réessayez ou utilisez le rattachement manuel.'];
        }

        $previous = $this->packages->application($tenantId, $enlistmentId);
        $own = !$this->pdo->inTransaction();
        if ($own) {
            $this->pdo->beginTransaction();
        }
        $step = 'le parcours d’intégration';
        $results = ['qualifications' => [], 'courses' => []];
        $baseMessage = null;
        try {
            $base = $this->ports->applyBase($tenantId, $enlistmentId, $actorId, $options + ['skip_welcome' => true]);
            if (empty($base['ok'])) {
                throw new \RuntimeException((string) ($base['message'] ?? 'Intégration impossible.'));
            }
            $baseMessage = $base['message'] ?? null;
            $userId = (int) ($base['user_id'] ?? $userId);
            foreach ($toAward as $it) {
                $step = 'la qualification « ' . $qNames[$it['ref_id']] . ' »';
                $results['qualifications'][$it['ref_id']] = [
                    'status' => $it['status'],
                    'result' => $this->ports->awardQualification($tenantId, $userId, $it['ref_id'], $it['status'], (int) $it['valid_months'], $actorId),
                ];
            }
            foreach ($toEnroll as $it) {
                $step = 'la formation « ' . $cNames[$it['ref_id']] . ' »';
                $results['courses'][$it['ref_id']] = ['result' => $this->ports->enrollCourse($tenantId, $userId, $it['ref_id'], $actorId)];
            }
            $step = 'l’enregistrement du paquet';
            $applied = $this->mergeApplied($previous['applied'] ?? [], $packageId, $results, $pending);
            $this->packages->recordApplication($tenantId, $enlistmentId, $userId, $packageId, $actorId, $applied);
            if ($own) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($own && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'ok' => false,
                'message' => 'Rien n’a été appliqué : échec sur ' . $step . ' (' . $this->short($e) . '). Corrigez puis relancez : l’intégration est rejouable sans doublon.',
                'user_id' => $userId,
            ];
        }

        $changed = $previous === null || $this->anyApplied($results);
        $recap = $this->recap($tenantId, $package, $options, $toAward, $toEnroll, $pending, $qNames, $cNames);
        if ($changed) {
            try {
                $this->ports->journal($tenantId, $userId, $enlistmentId, $actorId, 'Paquet d’intégration « ' . (string) $package['name'] . ' » appliqué', $this->journalText($recap, $results));
            } catch (Throwable) {
                // Le journal ne doit pas défaire une intégration déjà enregistrée.
            }
        }
        $welcomeAlready = $previous !== null && trim((string) ($previous['welcome_sent_at'] ?? '')) !== '';
        if (!$welcomeAlready) {
            try {
                if ($this->ports->sendWelcome($tenantId, $userId, $recap)) {
                    $this->packages->markWelcomeSent($tenantId, $enlistmentId);
                }
            } catch (Throwable) {
            }
        }

        return [
            'ok' => true,
            'message' => $baseMessage,
            'user_id' => $userId,
            'steam_id' => $base['steam_id'] ?? null,
            'steam_synced' => (bool) ($base['steam_synced'] ?? false),
            'applied' => $recap,
            'unchanged' => !$changed,
        ];
    }

    /** @return list<int> */
    private function ids(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $v): bool => $v > 0)));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $results
     * @param list<array<string, mixed>> $pending
     * @return array<string, mixed>
     */
    private function mergeApplied(array $before, int $packageId, array $results, array $pending): array
    {
        $q = is_array($before['qualifications'] ?? null) ? $before['qualifications'] : [];
        $c = is_array($before['courses'] ?? null) ? $before['courses'] : [];
        foreach ($results['qualifications'] as $id => $r) {
            $q[(string) $id] = $r['status'];
        }
        foreach ($results['courses'] as $id => $r) {
            $c[(string) $id] = true;
        }
        $stillPending = [];
        foreach ($pending as $p) {
            $key = $p['type'] === 'qualification' ? 'qualifications' : 'courses';
            $done = $key === 'qualifications' ? isset($q[(string) $p['ref_id']]) : isset($c[(string) $p['ref_id']]);
            if (!$done) {
                $stillPending[] = $p;
            }
        }

        return ['package_id' => $packageId, 'qualifications' => $q, 'courses' => $c, 'pending' => $stillPending];
    }

    /** @param array<string, mixed> $results */
    private function anyApplied(array $results): bool
    {
        foreach (['qualifications', 'courses'] as $k) {
            foreach ($results[$k] as $r) {
                if (($r['result'] ?? '') === 'applied') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $package
     * @param array<string, mixed> $options
     * @param list<array<string, mixed>> $toAward
     * @param list<array<string, mixed>> $toEnroll
     * @param list<array<string, mixed>> $pending
     * @param array<int, string> $qNames
     * @param array<int, string> $cNames
     * @return array<string, mixed>
     */
    private function recap(int $tenantId, array $package, array $options, array $toAward, array $toEnroll, array $pending, array $qNames, array $cNames): array
    {
        $labels = $this->ports->baseLabels($tenantId, $options);
        $pendingNames = $this->packages->qualificationNames($tenantId, array_column(array_filter($pending, static fn (array $p): bool => $p['type'] === 'qualification'), 'ref_id'));
        $pendingCourses = $this->packages->courseNames($tenantId, array_column(array_filter($pending, static fn (array $p): bool => $p['type'] === 'course'), 'ref_id'));
        $qualifications = [];
        foreach ($toAward as $it) {
            $qualifications[] = ['name' => $qNames[$it['ref_id']] ?? '', 'status' => $it['status']];
        }
        $courses = [];
        foreach ($toEnroll as $it) {
            $courses[] = $cNames[$it['ref_id']] ?? '';
        }

        return [
            'package' => (string) $package['name'],
            'base' => $labels,
            'qualifications' => $qualifications,
            'courses' => $courses,
            'pending' => array_merge(array_values($pendingNames), array_values($pendingCourses)),
            'welcome' => (string) ($package['welcome_message'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $recap
     * @param array<string, mixed> $results
     */
    private function journalText(array $recap, array $results): string
    {
        $lines = [];
        $b = $recap['base'];
        foreach (['grade' => 'Grade', 'unit' => 'Unité', 'job' => 'Emploi'] as $k => $label) {
            if (trim((string) ($b[$k] ?? '')) !== '') {
                $lines[] = $label . ' : ' . $b[$k];
            }
        }
        if (($b['roles'] ?? []) !== []) {
            $lines[] = 'Niveau d’accès : ' . implode(', ', $b['roles']);
        }
        foreach ($recap['qualifications'] as $q) {
            $lines[] = 'Qualification : ' . $q['name'] . ($q['status'] === 'in_training' ? ' (en formation)' : '');
        }
        foreach ($recap['courses'] as $c) {
            $lines[] = 'Formation : ' . $c;
        }
        foreach ($recap['pending'] as $p) {
            $lines[] = 'À attribuer par un responsable : ' . $p;
        }

        return implode("\n", $lines);
    }

    private function short(Throwable $e): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? ''), 0, 200);
    }
}
