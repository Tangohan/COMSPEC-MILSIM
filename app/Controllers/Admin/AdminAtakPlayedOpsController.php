<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakPlayedOpRepository;
use App\Support\AtakMissionKey;
use App\Support\AtakOpReconciler;
use App\Support\AtakPlayedOpRules;
use App\Support\ModuleFeatureAccess;

/**
 * Gestion des parties détectées en jeu (fenêtre « Gérer » du suivi des CR et du cycle de mission) : renommer, typer,
 * catégoriser, rattacher à une opération planifiée, ignorer (cette partie ou à l'avenir), compter à nouveau, supprimer
 * (logiquement) et restaurer, actions groupées ; types, règles d'exclusion et catégories de la communauté.
 *
 * Tout passe en POST avec le jeton CSRF, réservé aux gestionnaires ATAK de la communauté ; chaque écriture est filtrée
 * par communauté (une partie, un type, une règle ou une opération d'une autre communauté est introuvable).
 */
final class AdminAtakPlayedOpsController
{
    private const BASE = 'back-office/atak/comptes-rendus';

    public function __construct(private ?AtakPlayedOpRepository $repo = null)
    {
        $this->repo ??= new AtakPlayedOpRepository();
    }

    /** Nom, type, catégorie et rattachement d'une partie. */
    public function save(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        if ($key === '') {
            return $this->done($back, 'error', 'Partie introuvable.');
        }
        $fields = ['label' => self::nullable(mb_substr(trim((string) $request->input('nom', '')), 0, 200))];
        if ($request->input('type_id') !== null) {
            $typeId = (int) $request->input('type_id', 0);
            if ($typeId > 0 && $this->repo->findType($tenantId, $typeId) === null) {
                return $this->done($back, 'error', 'Type introuvable.', $key);
            }
            $fields['type_id'] = $typeId > 0 ? $typeId : null;
        }
        if ($request->input('categorie') !== null) {
            $cat = trim((string) $request->input('categorie', ''));
            if ($cat !== '' && !in_array($cat, $this->repo->categories($tenantId), true)) {
                return $this->done($back, 'error', 'Catégorie inconnue : ajoutez-la d’abord à la liste des catégories.', $key);
            }
            $fields['category'] = self::nullable($cat);
        }
        if ($request->input('lien') !== null) {
            $link = $this->linkFields($tenantId, (string) $request->input('lien', 'auto'));
            if ($link === null) {
                return $this->done($back, 'error', 'Opération à rattacher introuvable.', $key);
            }
            $fields += $link;
        }
        $ok = $this->repo->saveMeta($tenantId, $key, $fields, $actorId);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? 'Partie enregistrée.' : 'Enregistrement impossible : lancez run-migrations.php puis réessayez.', $key);
    }

    /** Rattacher seulement (liste du cycle de mission). */
    public function link(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        $link = $this->linkFields($tenantId, (string) $request->input('lien', 'auto'));
        if ($key === '' || $link === null) {
            return $this->done($back, 'error', 'Partie ou opération introuvable.');
        }
        $ok = $this->repo->saveMeta($tenantId, $key, $link, $actorId);
        $msg = match ($link['link_mode']) {
            'manual' => 'Partie rattachée.',
            'none' => 'Partie marquée sans opération prévue.',
            default => 'Rattachement rendu à la proposition automatique.',
        };

        return $this->done($back, $ok ? 'success' : 'error', $ok ? $msg : 'Enregistrement impossible.', $key);
    }

    /** Ignorer cette partie, ou à l'avenir (règle sur la mission ou la carte). */
    public function ignore(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        if ($key === '') {
            return $this->done($back, 'error', 'Partie introuvable.');
        }
        if ((string) $request->input('portee', 'partie') !== 'regle') {
            $ok = $this->repo->saveMeta($tenantId, $key, ['ignored_at' => 'NOW', 'count_anyway' => 0], $actorId);

            return $this->done($back, $ok ? 'success' : 'error', $ok ? 'Partie ignorée : elle ne compte plus dans les heures, la présence et les statistiques.' : 'Enregistrement impossible.', $key);
        }
        $field = AtakPlayedOpRules::normalizeField((string) $request->input('champ', 'mission'), true);
        $pattern = AtakPlayedOpRules::normalizePattern((string) $request->input('motif', ''));
        if ($pattern === '') {
            $pattern = AtakPlayedOpRules::fieldValue($key, $field);
        }
        $ruleId = $this->repo->createRule($tenantId, $field, $pattern, (string) $request->input('note', ''), $actorId);
        if ($ruleId < 1) {
            return $this->done($back, 'error', 'Règle impossible à enregistrer : indiquez un motif.', $key);
        }
        $this->repo->saveMeta($tenantId, $key, ['count_anyway' => 0], $actorId);
        $hits = 0;
        foreach (array_keys($this->repo->detected($tenantId, 365, 300)) as $k) {
            $hits += AtakPlayedOpRules::matches($pattern, AtakPlayedOpRules::fieldValue($k, $field)) ? 1 : 0;
        }

        return $this->done($back, 'success', 'Règle enregistrée : ' . $hits . ' partie(s) déjà jouée(s) concernée(s), et les prochaines.', $key);
    }

    /** Compter à nouveau une partie ignorée (à la main ou par une règle). */
    public function count(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        $rule = AtakPlayedOpRules::matchingRule($key, $this->repo->rules($tenantId));
        $ok = $key !== '' && $this->repo->saveMeta($tenantId, $key, ['ignored_at' => null, 'count_anyway' => $rule !== null ? 1 : 0], $actorId);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? 'La partie compte de nouveau.' : 'Partie introuvable.', $key);
    }

    /** Suppression logique (restaurable) après confirmation explicite. */
    public function delete(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        if ($key === '' || (string) $request->input('confirme', '') !== '1') {
            return $this->done($back, 'error', 'Cochez la confirmation pour supprimer la partie : rien n’a été supprimé.', $key);
        }
        $ok = $this->repo->saveMeta($tenantId, $key, ['deleted_at' => 'NOW'], $actorId);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? 'Partie supprimée. Elle reste restaurable depuis « Parties supprimées ».' : 'Suppression impossible.');
    }

    public function restore(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        $ok = $key !== '' && $this->repo->saveMeta($tenantId, $key, ['deleted_at' => null], $actorId);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? 'Partie restaurée.' : 'Partie introuvable.', $key);
    }

    /** Actions groupées sur les lignes cochées : catégoriser, ignorer, compter, supprimer. */
    public function bulk(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $raw = $request->input('ops', []);
        $keys = array_values(array_unique(array_filter(array_map(
            static fn (mixed $k): string => AtakMissionKey::normalize($k),
            is_array($raw) ? array_slice($raw, 0, 200) : []
        ))));
        if ($keys === []) {
            return $this->done($back, 'error', 'Cochez au moins une partie.');
        }
        $action = (string) $request->input('action', '');
        $fields = null;
        switch ($action) {
            case 'categoriser':
                $cat = trim((string) $request->input('categorie', ''));
                if ($cat !== '' && !in_array($cat, $this->repo->categories($tenantId), true)) {
                    return $this->done($back, 'error', 'Catégorie inconnue.');
                }
                $fields = ['category' => self::nullable($cat)];
                break;
            case 'ignorer':
                $fields = ['ignored_at' => 'NOW', 'count_anyway' => 0];
                break;
            case 'compter':
                $fields = ['ignored_at' => null];
                break;
            case 'supprimer':
                if ((string) $request->input('confirme', '') !== '1') {
                    return $this->done($back, 'error', 'Cochez la confirmation pour supprimer : rien n’a été supprimé.');
                }
                $fields = ['deleted_at' => 'NOW'];
                break;
        }
        if ($fields === null) {
            return $this->done($back, 'error', 'Choisissez une action.');
        }
        $rules = $action === 'compter' ? $this->repo->rules($tenantId) : [];
        $n = 0;
        foreach ($keys as $k) {
            $f = $fields;
            if ($action === 'compter') {
                $f['count_anyway'] = AtakPlayedOpRules::matchingRule($k, $rules) !== null ? 1 : 0;
            }
            $n += $this->repo->saveMeta($tenantId, $k, $f, $actorId) ? 1 : 0;
        }

        return $this->done($back, $n > 0 ? 'success' : 'error', $n . ' partie(s) mise(s) à jour.');
    }

    /** Enregistrer un type d'opération (avec sa règle de détection), éventuellement appliqué à la partie ouverte. */
    public function typeStore(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId, $back] = $ctx;
        $cat = trim((string) $request->input('categorie', ''));
        if ($cat !== '' && !in_array($cat, $this->repo->categories($tenantId), true)) {
            $cat = '';
        }
        $id = $this->repo->createType($tenantId, [
            'name' => (string) $request->input('nom', ''),
            'color' => (string) $request->input('couleur', ''),
            'icon' => (string) $request->input('icone', ''),
            'match_field' => (string) $request->input('champ', 'mission'),
            'pattern' => (string) $request->input('motif', ''),
            'category' => $cat,
        ], $actorId);
        if ($id < 1) {
            return $this->done($back, 'error', 'Type impossible à enregistrer : donnez-lui un nom.');
        }
        $key = AtakMissionKey::normalize((string) $request->input('op', ''));
        if ($key !== '') {
            $type = $this->repo->findType($tenantId, $id);
            $label = trim((string) ($this->repo->meta($tenantId)[$key]['label'] ?? '')) ?: AtakMissionKey::label($key);
            $field = AtakPlayedOpRules::normalizeField((string) ($type['match_field'] ?? 'mission'));
            if ($type !== null && !AtakPlayedOpRules::matches((string) $type['pattern'], AtakPlayedOpRules::fieldValue($key, $field, $label))) {
                $this->repo->saveMeta($tenantId, $key, ['type_id' => $id], $actorId);
            }
        }

        return $this->done($back, 'success', 'Type enregistré : les prochaines parties qui correspondent à sa règle seront typées automatiquement.', $key);
    }

    public function typeArchive(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, , $back] = $ctx;
        $archive = (string) $request->input('archiver', '1') === '1';
        $ok = $this->repo->setTypeArchived($tenantId, (int) ($params['id'] ?? 0), $archive);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? ($archive ? 'Type archivé : il ne type plus les parties.' : 'Type réactivé.') : 'Type introuvable.', '', 'reglages');
    }

    public function ruleToggle(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, , $back] = $ctx;
        $disable = (string) $request->input('desactiver', '1') === '1';
        $ok = $this->repo->setRuleDisabled($tenantId, (int) ($params['id'] ?? 0), $disable);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? ($disable ? 'Règle désactivée : les parties concernées comptent de nouveau.' : 'Règle réactivée.') : 'Règle introuvable.', '', 'reglages');
    }

    public function categories(Request $request, array $params = []): Response
    {
        $ctx = $this->context($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, , $back] = $ctx;
        $list = AtakPlayedOpRules::parseCategories((string) $request->input('liste', ''));
        if ($list === []) {
            return $this->done($back, 'error', 'Indiquez au moins une catégorie.', '', 'reglages');
        }
        $ok = $this->repo->saveCategories($tenantId, $list);

        return $this->done($back, $ok ? 'success' : 'error', $ok ? 'Catégories enregistrées.' : 'Enregistrement impossible.', '', 'reglages');
    }

    /** @return array{0: int, 1: ?int, 2: string}|Response */
    private function context(Request $request): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('dashboard'));
        }
        $back = (string) $request->input('retour', 'suivi') === 'cycle' ? 'cycle' : 'suivi';
        $forbidden = ModuleFeatureAccess::guardAtak('manage', self::BASE);
        if ($forbidden instanceof Response) {
            return $forbidden;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            return $this->done($back, 'error', 'Session expirée. Réessayez.');
        }

        return [$tenantId, $userId, $back];
    }

    /**
     * Champs de rattachement depuis « plan:12 », « cycle:3 », « event:7 », « none », « auto » ; null si la cible
     * n'appartient pas à la communauté.
     *
     * @return array<string, mixed>|null
     */
    private function linkFields(int $tenantId, string $raw): ?array
    {
        [$kind, $id] = AtakOpReconciler::parseLink($raw);
        $fields = ['plan_id' => null, 'cycle_id' => null, 'event_id' => null, 'link_mode' => $kind === 'none' ? 'none' : null];
        if ($id < 1) {
            return $fields;
        }
        if (!$this->repo->ownsTarget($tenantId, $kind, $id)) {
            return null;
        }
        $fields[$kind . '_id'] = $id;
        $fields['link_mode'] = 'manual';

        return $fields;
    }

    private function done(string $back, string $kind, string $message, string $key = '', string $open = ''): Response
    {
        Session::flash($kind, $message);
        if ($back === 'cycle') {
            return Response::redirect(url('back-office/atak/cycle-mission') . ($open !== '' ? '#bo-mcycle-' . $open : '#bo-mcycle-parties'));
        }
        $q = ['onglet' => 'suivi'];
        if ($key !== '' && $kind === 'success') {
            $q['op'] = $key;
        }

        return Response::redirect(url('back-office/atak/comptes-rendus') . '?' . http_build_query($q) . ($open !== '' ? '#dbf-parties-' . $open : '#dbf-parties'));
    }

    private static function nullable(string $v): ?string
    {
        return $v !== '' ? $v : null;
    }
}
