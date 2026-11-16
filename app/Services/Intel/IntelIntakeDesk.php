<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Repositories\MemberNotificationRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Services\Sse\Links\SseLinkViewer;
use App\Services\Sse\Links\SseLinkWriter;
use App\Support\SseFieldNoteCatalog;
use App\Support\SseLinkCatalog;

/**
 * Le traitement d'une remontée, une seule fois pour ses deux habillages du bureau SSE : la page de la remontée
 * (/atak/sse/remontees/{source}/{id}, où renvoie l'ancienne adresse du back-office) et l'onglet « Suivi » d'une fiche.
 * Les contrôleurs décident QUI a quels droits
 * (tableau $rights) ; ce service prépare ce que la page affiche et exécute les actions selon ces droits.
 *
 * Droits attendus :
 *   treat   : prendre en compte, commenter, changer l'état, transmettre, doublon, attribuer, changer le type et les thèmes,
 *             ignorer une proposition de Cerbère ou la signaler comme mauvaise détection (« cerbere ») ;
 *   manage  : modifier les données, flouter une pièce, supprimer, restaurer ;
 *   redact  : caviarder et lever un caviardage (plus haute habilitation) ;
 *   steam   : lire l'identifiant Steam de l'émetteur (journalisé) ;
 *   convert : convertir en fiche, dossier, dossier d'intérêt, demande de collecte ;
 *   link    : relier à un élément du bureau SSE ;
 *   discord : demander une notification Discord.
 */
class IntelIntakeDesk
{
    public const RIGHTS = ['treat', 'manage', 'redact', 'steam', 'convert', 'link', 'discord'];

    /** Droit exigé par action. */
    public const ACTION_RIGHT = [
        'take' => 'treat', 'comment' => 'treat', 'state' => 'treat', 'assign' => 'treat', 'type' => 'treat', 'themes' => 'treat', 'cerbere' => 'treat',
        'edit' => 'manage', 'blur' => 'manage', 'delete' => 'manage', 'restore' => 'manage',
        'redact' => 'redact', 'unredact' => 'redact',
        'steam' => 'steam', 'convert' => 'convert', 'link' => 'link', 'discord' => 'discord',
    ];

    public const NOTIFY_ACK = 'intel_intake_ack';

    public function __construct(
        private ?IntelIntakeService $intake = null,
        private ?IntelIntakeAdvisor $advisor = null,
        private ?IntelIntakeConversion $conversion = null,
        private ?IntelIntakeDiscord $discord = null,
        private ?SseLinkViewer $linkViewer = null,
        private ?\Closure $notify = null,
    ) {
        $this->intake ??= new IntelIntakeService();
    }

    public function intake(): IntelIntakeService
    {
        return $this->intake;
    }

    public function advisor(): IntelIntakeAdvisor
    {
        return $this->advisor ??= new IntelIntakeAdvisor();
    }

    private function conversion(): IntelIntakeConversion
    {
        return $this->conversion ??= new IntelIntakeConversion($this->intake);
    }

    public function discord(): IntelIntakeDiscord
    {
        return $this->discord ??= new IntelIntakeDiscord();
    }

    private function linkViewer(): SseLinkViewer
    {
        return $this->linkViewer ??= new SseLinkSessionViewer();
    }

    /**
     * @param array<string, mixed> $rights
     * @return array<string, bool>
     */
    public static function rights(array $rights): array
    {
        $out = [];
        foreach (self::RIGHTS as $r) {
            $out[$r] = !empty($rights[$r]);
        }

        return $out;
    }

    /**
     * Tout ce que la page d'une remontée affiche.
     *
     * @param array{id: int, level: string} $viewer
     * @param array<string, mixed> $rights
     * @return array<string, mixed>|null null : remontée introuvable dans cette communauté
     */
    public function view(int $tenantId, string $source, int $id, array $viewer, array $rights, ?int $now = null): ?array
    {
        $item = $this->intake->find($tenantId, $source, $id);
        if ($item === null) {
            return null;
        }
        $rights = self::rights($rights);
        $now ??= time();
        $flat = $item;
        $flat['redactions'] = count($item['redactions']);
        $flat['attachments'] = count($item['attachments']);
        $flat['readers'] = count($item['reads']);
        $flat['reads'] = array_sum(array_column($item['reads'], 'count'));
        $row = $item['row'];
        $open = in_array($item['state'], IntelIntakeService::OPEN_STATES, true);
        $treat = $rights['treat'] && empty($item['deleted']);

        $timeline = [];
        foreach ($item['events'] as $e) {
            $timeline[] = ['at' => (string) $e['at'], 'type' => $e['kind'] === 'comment' ? 'comment' : 'event', 'event' => $e];
        }
        foreach ($item['reads'] as $r) {
            $timeline[] = ['at' => (string) $r['first'], 'type' => 'read', 'read' => $r];
        }
        usort($timeline, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        $links = $source === 'fiche' ? $this->links($id) : ['ready' => false, 'items' => [], 'can' => false];
        $discordStatus = ['ready' => false, 'reason' => '', 'channel' => '', 'linked' => false];
        $discordJournal = [];
        try {
            $discordStatus = $this->discord()->status($tenantId, $now);
            $discordJournal = $discordStatus['linked'] ? $this->discord()->journal($tenantId, $item) : [];
        } catch (\Throwable) {
        }

        return [
            'item' => $item,
            'rights' => $rights,
            'viewer' => $viewer,
            'open' => $open,
            'badges' => IntelIntakePresenter::badges($flat, $now),
            'cycle' => IntelIntakePresenter::cycle($item),
            'delay' => IntelIntakePresenter::delay($item, $now),
            'urgency' => [
                'level' => IntelIntakePresenter::urgencyLevel((string) $item['priority']),
                'label' => IntelIntakePresenter::urgencyLabel($source, (string) $item['priority']),
            ],
            'classification' => IntelIntakePresenter::classificationLabel($source, (string) $item['classification']),
            'origin' => IntelIntakePresenter::originLabel($source, (string) $item['origin']),
            'themes' => $source === 'fiche' ? IntelIntakePresenter::themes($row['themes'] ?? '') : [],
            'reliability' => $source === 'fiche' ? IntelIntakePresenter::reliabilityLabel($row['source_reliability'] ?? '', $row['info_credibility'] ?? 0) : '',
            'intel_source' => $source === 'fiche' && trim((string) ($row['intel_source'] ?? '')) !== ''
                ? (string) $row['intel_source'] . ' — ' . SseFieldNoteCatalog::sourceLabel((string) $row['intel_source']) : '',
            'timeline' => $timeline,
            'proposals' => $treat ? $this->advisor()->proposals($item, $tenantId) : [],
            'assignee_proposal' => $treat && $open ? $this->advisor()->assignee($tenantId, $item) : null,
            'duplicates' => $treat ? $this->advisor()->duplicates($tenantId, $item) : [],
            'forward_targets' => $treat ? $this->advisor()->forwardTargets($tenantId, '', 40) : [],
            'conversions' => $this->conversion()->options($item),
            'links' => $links,
            'discord' => $discordStatus + ['journal' => $discordJournal, 'preview' => $this->discord()->message($item, 'manual')],
            'reasons' => IntelIntakePresenter::REASON_TEMPLATES,
            'steam_masked' => IntelIntakePresenter::maskSteam((string) ($item['sender']['steam'] ?? '')),
            'case' => $this->caseCard($tenantId, $item['case_id'] ?? null),
        ];
    }

    /** @return array{id: int, ref: string, title: string}|null */
    private function caseCard(int $tenantId, ?int $caseId): ?array
    {
        if ($caseId === null || $caseId < 1) {
            return null;
        }
        try {
            $c = \App\Core\Database::getInstance()->fetchOne('SELECT id, reference_code, title FROM sse_cases WHERE id = :id AND tenant_id = :t', ['id' => $caseId, 't' => $tenantId]);
        } catch (\Throwable) {
            $c = null;
        }

        return $c ? ['id' => (int) $c['id'], 'ref' => (string) $c['reference_code'], 'title' => (string) ($c['title'] ?? '')] : null;
    }

    /**
     * Liens directs d'une fiche, lus par le service de liens du bureau SSE (mêmes règles de visibilité).
     *
     * @return array{ready: bool, can: bool, items: list<array<string, mixed>>}
     */
    public function links(int $noteId): array
    {
        $out = ['ready' => false, 'can' => false, 'items' => []];
        try {
            $viewer = $this->linkViewer();
            $out['ready'] = \App\Repositories\SseEntityLinkRepository::ready();
            $out['can'] = $out['ready'] && !$viewer->isGuest() && $viewer->canWrite(SseLinkCatalog::TYPES['note']['section']);
            $g = (new SseLinkGraph($viewer))->resolve('note', (string) $noteId, 1);
            if (empty($g['found'])) {
                return $out;
            }
            foreach ((array) $g['direct'] as $group) {
                foreach ((array) $group as $d) {
                    $node = $g['nodes'][$d['node']] ?? null;
                    $edge = $g['edges'][$d['edge']] ?? null;
                    if ($node === null || $edge === null) {
                        continue;
                    }
                    $out['items'][] = [
                        'type_label' => (string) ($node['type_label'] ?? ''),
                        'ref' => (string) ($node['ref'] ?? ''),
                        'title' => !empty($node['restricted']) ? 'Élément réservé' : (string) ($node['title'] ?? ''),
                        'href' => !empty($node['restricted']) ? '' : (string) ($node['href'] ?? ''),
                        'relation' => (string) (!empty($d['forward']) ? ($edge['label'] ?? '') : ($edge['inverse'] ?? ($edge['label'] ?? ''))),
                        'reason' => (string) ($edge['reason'] ?? ''),
                        'auto' => str_starts_with((string) ($edge['rule'] ?? ''), 'cerbere.'),
                        'actor' => (string) ($edge['actor'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /**
     * Éléments du bureau SSE reliables à une fiche (recherche de la fenêtre « Relier »).
     *
     * @return list<array<string, mixed>>
     */
    public function linkSearch(string $query): array
    {
        $q = trim(mb_substr($query, 0, 80));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $items = [];
        try {
            $graph = new SseLinkGraph($this->linkViewer());
            foreach (['case', 'interest', 'note', 'person', 'identity', 'site', 'document'] as $type) {
                foreach ($graph->search($type, $q, 4) as $hit) {
                    $items[] = ['value' => (string) $hit['key'], 'label' => trim((string) $hit['ref'] . ' — ' . (string) $hit['title'], ' —'), 'kind' => (string) $hit['type_label']];
                }
            }
        } catch (\Throwable) {
        }

        return array_slice($items, 0, 20);
    }

    /**
     * Une proposition de Cerbère appliquée (champs cerbere_* du bouton Appliquer) : notée pour les statistiques de la règle.
     *
     * @param array<string, mixed> $input
     * @param array{id: int, label: string} $actor
     */
    private function cerbereApplied(bool $ok, int $tenantId, string $source, int $id, array $input, array $actor): void
    {
        if ($ok && isset($input['cerbere_target'])) {
            $this->advisor()->detection()->applied($tenantId, $source, $id, $input, $actor);
        }
    }

    /**
     * Exécute une action. Ne lève jamais : renvoie le message à afficher.
     *
     * @param array<string, mixed> $input champs du formulaire
     * @param array{id: int, label: string} $actor
     * @param array<string, mixed> $rights
     * @return array{ok: bool, message: string, deleted: bool, data: array<string, mixed>}
     */
    public function perform(int $tenantId, string $source, int $id, string $action, array $input, array $actor, array $rights, string $level): array
    {
        $rights = self::rights($rights);
        $res = static fn (bool $ok, string $message, array $data = [], bool $deleted = false): array => ['ok' => $ok, 'message' => $message, 'deleted' => $deleted, 'data' => $data];
        $need = self::ACTION_RIGHT[$action] ?? null;
        if ($need === null || !$this->intake->schemaReady()) {
            return $res(false, 'Action impossible.');
        }
        $item = $this->intake->find($tenantId, $source, $id);
        if ($item === null) {
            return $res(false, 'Remontée introuvable.');
        }
        if (!$rights[$need]) {
            return $res(false, self::denied($need));
        }
        $in = static fn (string $k, string $d = ''): string => trim((string) (is_scalar($input[$k] ?? null) ? $input[$k] : $d));
        // Une urgence « Critique » proposée par Cerbère ne s'applique qu'après vérification explicite du texte.
        if ($action === 'edit' && $in('cerbere_target') === 'urgency' && $in('cerbere_value') === 'critique' && $in('cerbere_checked') !== '1') {
            return $res(false, 'Cochez « J’ai vérifié le texte » avant d’appliquer une urgence Critique.');
        }
        try {
            switch ($action) {
                case 'cerbere':
                    $fb = $this->advisor()->detection()->feedback($tenantId, $source, $id, $input, $actor);

                    return $res($fb['ok'], $fb['message']);
                case 'take':
                    $ok = $this->intake->take($tenantId, $source, $id, $actor);
                    if ($ok) {
                        $this->acknowledgeToSender($tenantId, $item, $actor);
                    }

                    return $res($ok, $ok ? 'Remontée prise en compte : elle vous est attribuée.' : 'Cette remontée est déjà prise en compte.');
                case 'comment':
                    $ok = $this->intake->comment($tenantId, $source, $id, $actor, $in('body'));

                    return $res($ok, $ok ? 'Commentaire ajouté.' : 'Le commentaire est vide.');
                case 'state':
                    return $this->state($tenantId, $item, $actor, $in('state'), $in('note'), $in('target'));
                case 'assign':
                    $ok = $this->intake->assign($tenantId, $source, $id, $actor, (int) $in('user_id', '0') ?: null);

                    return $res($ok, $ok ? 'Attribution mise à jour.' : 'Membre introuvable.');
                case 'type':
                    $ok = $this->intake->changeType($tenantId, $source, $id, $actor, $in('type'));
                    $this->cerbereApplied($ok, $tenantId, $source, $id, $input, $actor);

                    return $res($ok, $ok ? 'Type changé.' : 'Type inchangé ou inconnu.');
                case 'themes':
                    $ok = $source === 'fiche' && $this->intake->setThemes($tenantId, $id, $actor, $input['themes'] ?? '');
                    $this->cerbereApplied($ok, $tenantId, $source, $id, $input, $actor);

                    return $res($ok, $ok ? 'Thèmes mis à jour.' : 'Thèmes inchangés.');
                case 'edit':
                    $ok = $this->intake->updateData($tenantId, $source, $id, $actor, array_intersect_key($input, IntelIntakeService::EDITABLE[$source] ?? []), $level) !== [];
                    $this->cerbereApplied($ok, $tenantId, $source, $id, $input, $actor);

                    return $res($ok, $ok ? 'Données enregistrées.' : 'Aucune modification.');
                case 'redact':
                    $ok = $this->intake->addRedaction($tenantId, $source, $id, $actor, $in('field', 'body'), (string) ($input['phrase'] ?? ''), $in('level'), array_map('intval', (array) ($input['allowed'] ?? [])), $in('reason'));

                    return $res($ok, $ok ? 'Passage caviardé.' : 'Passage introuvable dans le champ choisi : copiez-le tel qu’il est écrit.');
                case 'unredact':
                    $ok = $this->intake->removeRedaction($tenantId, $source, $id, $actor, (int) $in('redaction_id', '0'));

                    return $res($ok, $ok ? 'Caviardage levé.' : 'Caviardage introuvable.');
                case 'blur':
                    $ok = $source === 'fiche' && $this->intake->setBlur($tenantId, $id, $actor, (int) $in('attachment_id', '0'), $in('blurred') === '1');

                    return $res($ok, $ok ? 'Pièce jointe mise à jour.' : 'Pièce jointe introuvable.');
                case 'delete':
                    if (mb_strtoupper($in('confirm')) !== mb_strtoupper((string) $item['ref'])) {
                        return $res(false, 'Suppression non confirmée : recopiez la référence de la remontée.');
                    }
                    $ok = $this->intake->delete($tenantId, $source, $id, $actor, $in('reason'));

                    return $res($ok, $ok ? 'Remontée supprimée. Elle reste restaurable depuis le filtre « Supprimées ».' : 'Suppression impossible.', [], $ok);
                case 'restore':
                    $ok = $this->intake->restore($tenantId, $source, $id, $actor);

                    return $res($ok, $ok ? 'Remontée restaurée.' : 'Restauration impossible.');
                case 'steam':
                    $steam = (string) ($item['sender']['steam'] ?? '');
                    if ($steam === '') {
                        return $res(false, 'Aucun identifiant Steam n’accompagne cette remontée.');
                    }
                    $this->intake->journal($tenantId, $source, $id, 'steam', $actor, null, ['mode' => $in('mode') === 'copy' ? 'copie' : 'lecture']);

                    return $res(true, 'Identifiant Steam de l’émetteur : ' . $steam . '. Cette consultation est notée dans le fil.', ['steam' => $steam]);
                case 'convert':
                    $r = $this->conversion()->convert($tenantId, $source, $id, $in('kind'), $actor, $level);
                    if (!$r['ok']) {
                        return $res(false, $r['error']);
                    }

                    return $res(true, ($r['existing'] ? 'Déjà convertie : ' : 'Conversion enregistrée : ') . $r['ref'] . '.', ['url' => $r['url'], 'ref' => $r['ref'], 'existing' => $r['existing']]);
                case 'link':
                    return $this->link($tenantId, $item, $actor, $in('to'), $in('relation'), $in('reason'));
                case 'discord':
                    $r = $this->discord()->request($tenantId, $source, $id, $actor);

                    return $res($r['ok'], $r['ok'] ? 'Notification demandée : le bot l’enverra dans les cinq minutes.' : $r['error']);
            }
        } catch (\Throwable $e) {
            error_log('[remontees] action ' . $action . ' : ' . mb_substr($e->getMessage(), 0, 300));
        }

        return $res(false, 'Action impossible.');
    }

    public static function denied(string $right): string
    {
        return match ($right) {
            'redact' => 'Caviarder est réservé aux habilités « Très secret (TS) ».',
            'steam' => 'L’identifiant Steam est réservé aux administrateurs de la communauté.',
            'manage' => 'Modifier, flouter, supprimer et restaurer sont réservés aux administrateurs et aux responsables du renseignement.',
            'convert' => 'Droit manquant : écrire dans les dossiers du bureau SSE.',
            'link' => 'Droit manquant : écrire dans les fiches du bureau SSE.',
            'discord' => 'Droit manquant : traiter les remontées.',
            default => 'Droit manquant : traiter les remontées.',
        };
    }

    /**
     * @param array<string, mixed> $item
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, message: string, deleted: bool, data: array<string, mixed>}
     */
    private function state(int $tenantId, array $item, array $actor, string $state, string $note, string $target): array
    {
        $res = static fn (bool $ok, string $message): array => ['ok' => $ok, 'message' => $message, 'deleted' => false, 'data' => []];
        if (!isset(IntelIntakeService::STATES[$state])) {
            return $res(false, 'État inconnu.');
        }
        if ($state === 'transmise') {
            // Plus de saisie libre : un échelon de la liste ou un dossier de la communauté.
            $valid = $this->advisor()->validForward($tenantId, $target);
            if ($valid === null) {
                return $res(false, 'Destinataire inconnu : choisissez un échelon, une cellule ou un dossier SSE dans la liste proposée.');
            }
            $target = $valid;
        }
        $wasNew = $item['state'] === 'a_traiter';
        $ok = $this->intake->setState($tenantId, (string) $item['source'], (int) $item['id'], $actor, $state, $note, $target);
        if (!$ok) {
            return $res(false, match ($state) {
                'doublon' => 'Remontée d’origine introuvable : choisissez-la dans la liste ou donnez sa référence, autre que celle-ci.',
                default => 'État inchangé.',
            });
        }
        if ($wasNew && in_array($state, ['prise_en_compte', 'en_cours'], true)) {
            $this->acknowledgeToSender($tenantId, $item, $actor);
        }

        return $res(true, 'État mis à jour : ' . IntelIntakeService::STATES[$state] . '.');
    }

    /**
     * @param array<string, mixed> $item
     * @param array{id: int, label: string} $actor
     * @return array{ok: bool, message: string, deleted: bool, data: array<string, mixed>}
     */
    private function link(int $tenantId, array $item, array $actor, string $to, string $relation, string $reason): array
    {
        $res = static fn (bool $ok, string $message): array => ['ok' => $ok, 'message' => $message, 'deleted' => false, 'data' => []];
        if ($item['source'] !== 'fiche') {
            return $res(false, 'Un compte rendu se relie par « Doublon », « Transmettre » à un dossier ou « Convertir » en fiche.');
        }
        $target = SseLinkCatalog::parseRef($to);
        if ($target === null) {
            return $res(false, 'Choisissez l’élément à relier dans la liste proposée.');
        }
        $viewer = $this->linkViewer();
        $r = (new SseLinkWriter($viewer, new SseLinkGraph($viewer)))->add('note', (string) $item['id'], $target['type'], $target['id'], $relation, $reason);
        if (!$r['ok']) {
            return $res(false, (string) $r['error']);
        }
        $this->intake->journal($tenantId, 'fiche', (int) $item['id'], 'link', $actor, null, ['to' => $to, 'type' => SseLinkCatalog::typeLabel($target['type'])]);

        return $res(true, 'Lien enregistré.');
    }

    /**
     * Accusé à l'émetteur : notification du site (centre de notifications existant) s'il a un compte et que ce n'est
     * pas lui qui traite. Le statut de sa fiche passe par ailleurs à « Prise en compte ». Rien à l'écran du jeu.
     *
     * @param array<string, mixed> $item
     * @param array{id: int, label: string} $actor
     */
    private function acknowledgeToSender(int $tenantId, array $item, array $actor): void
    {
        $userId = (int) ($item['sender']['user_id'] ?? 0);
        if ($userId < 1 || $userId === (int) $actor['id']) {
            return;
        }
        try {
            $payload = ['ref' => (string) $item['ref'], 'source' => (string) $item['source'], 'type' => (string) $item['type_label']];
            if ($this->notify !== null) {
                ($this->notify)($tenantId, $userId, self::NOTIFY_ACK, $payload);

                return;
            }
            (new MemberNotificationRepository())->create($tenantId, $userId, self::NOTIFY_ACK, $payload);
        } catch (\Throwable) {
        }
    }

    /** Filtres par pastille de la liste. */
    public const BADGE_FILTERS = [
        'retard' => 'En retard',
        'urgente' => 'Urgentes',
        'pieces' => 'Avec pièces',
        'caviardee' => 'Caviardées',
        'liee' => 'Liées à un dossier',
        'non_attribuee' => 'Sans traitant',
    ];

    /** Colonnes triables de la liste. */
    public const SORTS = ['recu' => 'Reçue', 'urgence' => 'Urgence', 'etat' => 'État', 'emetteur' => 'Émetteur', 'attente' => 'Attente', 'traitant' => 'Traitant'];

    /**
     * Liste prête à afficher : pastilles et délai de chaque ligne, filtre par pastille, tri.
     *
     * @param list<array<string, mixed>> $items lignes de IntelIntakeService::listItems
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>}
     */
    public static function refine(array $items, string $badge = '', string $sort = 'recu', string $dir = 'desc', ?int $now = null): array
    {
        $now ??= time();
        $rank = ['critical' => 3, 'high' => 2, 'medium' => 1, 'low' => 0];
        $counts = array_fill_keys(array_keys(self::BADGE_FILTERS), 0);
        foreach ($items as $i => $it) {
            $delay = IntelIntakePresenter::delay($it, $now);
            $level = IntelIntakePresenter::urgencyLevel((string) $it['priority']);
            $flags = [
                'retard' => $delay['late'],
                'urgente' => $rank[$level] >= 2,
                'pieces' => (int) ($it['attachments'] ?? 0) > 0,
                'caviardee' => (int) ($it['redactions'] ?? 0) > 0,
                'liee' => !empty($it['linked']),
                'non_attribuee' => $it['assignee_user_id'] === null && in_array($it['state'], IntelIntakeService::OPEN_STATES, true),
            ];
            foreach ($flags as $k => $on) {
                $counts[$k] += $on ? 1 : 0;
            }
            $items[$i]['flags'] = $flags;
            $items[$i]['delay'] = $delay;
            $items[$i]['urgency_rank'] = $rank[$level];
            $items[$i]['badges'] = IntelIntakePresenter::badges($it, $now);
        }
        if (isset(self::BADGE_FILTERS[$badge])) {
            $items = array_values(array_filter($items, static fn (array $it): bool => $it['flags'][$badge]));
        }
        $states = array_flip(array_keys(IntelIntakeService::STATES));
        $key = match ($sort) {
            'urgence' => static fn (array $it): array => [$it['urgency_rank'], (string) $it['at']],
            'etat' => static fn (array $it): array => [-($states[$it['state']] ?? 99), (string) $it['at']],
            'emetteur' => static fn (array $it): array => [mb_strtolower((string) $it['author']), ''],
            'attente' => static fn (array $it): array => [$it['delay']['acked'] ? -1 : $it['delay']['waited'], (string) $it['at']],
            'traitant' => static fn (array $it): array => [mb_strtolower((string) $it['assignee_label']), ''],
            default => static fn (array $it): array => [(string) $it['at'], ''],
        };
        usort($items, static fn (array $a, array $b): int => ($dir === 'asc' ? 1 : -1) * ($key($a) <=> $key($b)));

        return ['items' => $items, 'counts' => $counts];
    }

    /** Niveau par défaut d'un lecteur sans service d'habilitation. */
    public static function fallbackLevel(bool $high): string
    {
        return $high ? SseCaseRepository::CLASS_RESTRICTED : SseCaseRepository::CLASS_INTERNAL;
    }
}
