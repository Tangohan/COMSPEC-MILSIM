<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\AarReportRepository;
use App\Repositories\AarReportTemplateRepository;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakDebriefRepository;
use App\Support\AtakDebriefSpec;
use App\Support\AtakMissionKey;
use App\Support\SteamId;

/**
 * CR de fin d'opération imposé sur le téléphone ATAK : envoi depuis le site, fiche lue par le téléphone
 * (« AdminControl.Poll »), événements et réponses remontés par le téléphone (POST /api/atak/reports, type DEBRIEF).
 */
final class AtakDebriefService
{
    /** Le mod annonce qu'il sait ouvrir la fiche : corps de « AdminControl.Poll » {caps: "debrief"}. */
    public const CAPABILITY = 'debrief';

    public function __construct(
        private ?AtakDebriefRepository $debriefs = null,
        private ?AtakAdminControlRepository $fleet = null,
    ) {
        $this->debriefs ??= new AtakDebriefRepository();
        $this->fleet ??= new AtakAdminControlRepository();
    }

    public function repository(): AtakDebriefRepository
    {
        return $this->debriefs;
    }

    /**
     * Questionnaire d'un modèle de debriefing ; [] = questionnaire intégré au mod.
     *
     * @return list<array<string, mixed>>
     */
    public function questionsFor(int $tenantId, int $templateId): array
    {
        if ($templateId < 1) {
            return [];
        }
        try {
            $tpl = (new AarReportTemplateRepository())->findForTenant($tenantId, $templateId);
        } catch (\Throwable) {
            return [];
        }

        return is_array($tpl['fields'] ?? null) ? $tpl['fields'] : [];
    }

    /** Texte à lire tiré d'un compte rendu post-op (paragraphes séparés par « ¶ », retour à la ligne dans le téléphone). */
    public function readTextFromAar(int $tenantId, int $aarId): string
    {
        if ($aarId < 1) {
            return '';
        }
        try {
            $r = (new AarReportRepository())->findForTenant($tenantId, $aarId);
        } catch (\Throwable) {
            return '';
        }
        if ($r === null) {
            return '';
        }
        $parts = [];
        if (trim((string) ($r['summary_text'] ?? '')) !== '') {
            $parts[] = trim((string) $r['summary_text']);
        }
        if (($r['strengths'] ?? []) !== []) {
            $parts[] = 'Points forts : ' . implode(' ; ', $r['strengths']);
        }
        if (($r['weaknesses'] ?? []) !== []) {
            $parts[] = 'À travailler : ' . implode(' ; ', $r['weaknesses']);
        }
        $actions = array_filter(array_map(static fn (array $a): string => trim((string) ($a['label'] ?? $a['text'] ?? '')), $r['open_actions'] ?? []));
        if ($actions !== []) {
            $parts[] = 'Actions décidées : ' . implode(' ; ', $actions);
        }

        return implode(' ¶ ', $parts);
    }

    /**
     * Demande partie du site : une ligne par joueur et un avis ATAK « debrief » (validé par le joueur en jeu).
     *
     * @param array{mission_key: string, label: string, mode: string, template_id: int, aar_report_id: int, intro: string,
     *   read_text: string, hours: int} $opts
     * @param list<array{user_id: ?int, steam_uid: ?string, callsign: string}> $players
     * @return array{id: int, sent: int}
     */
    public function sendFromSite(int $tenantId, array $opts, array $players, ?int $actorId, string $actorLabel): array
    {
        if (!$this->fleet->ready() || !$this->debriefs->ready()) {
            return ['id' => 0, 'sent' => 0];
        }
        $mode = isset(AtakDebriefSpec::MODES[$opts['mode']]) ? $opts['mode'] : 'fill';
        $readText = trim($opts['read_text']);
        if ($mode === 'read' && $readText === '' && $opts['aar_report_id'] > 0) {
            $readText = $this->readTextFromAar($tenantId, $opts['aar_report_id']);
        }
        $hours = max(1, min(24 * 30, $opts['hours']));
        $expires = gmdate('Y-m-d H:i:s', time() + $hours * 3600);
        $id = $this->debriefs->create([
            'tenant_id' => $tenantId,
            'mission_key' => $opts['mission_key'],
            'label' => $opts['label'],
            'mode' => $mode,
            'template_id' => $mode === 'fill' ? $opts['template_id'] : null,
            'aar_report_id' => $opts['aar_report_id'] ?: null,
            'intro' => $opts['intro'],
            'read_text' => $readText,
            'questions' => $mode === 'fill' ? $this->questionsFor($tenantId, $opts['template_id']) : [],
            'source' => 'site',
            'actor_id' => $actorId,
            'actor_label' => $actorLabel,
            'expires_at' => $expires,
        ]);
        if ($id < 1) {
            return ['id' => 0, 'sent' => 0];
        }
        $label = trim($opts['label']) ?: 'Fin d’opération';
        $sent = 0;
        $seen = [];
        foreach ($players as $p) {
            $uid = (int) ($p['user_id'] ?? 0) ?: null;
            $steam = SteamId::normalize((string) ($p['steam_uid'] ?? ''));
            $who = AtakDebriefRepository::whoKey($uid, $steam);
            if ($who === '' || isset($seen[$who])) {
                continue;
            }
            $seen[$who] = true;
            $rid = $this->debriefs->addRecipient($id, $tenantId, $uid, $steam, (string) ($p['callsign'] ?? ''));
            $mid = $this->fleet->createMessage([
                'tenant_id' => $tenantId,
                'user_id' => $uid,
                'steam_uid' => $steam,
                'terminal_uid' => null,
                'kind' => 'debrief',
                'title' => mb_substr(($mode === 'read' ? 'CR à lire · ' : 'CR à remplir · ') . $label, 0, 120),
                'body' => AtakDebriefSpec::legacyBody($mode, $label),
                'level' => 'warning',
                'require_ack' => true,
                'expires_at' => $expires,
                'actor_id' => $actorId,
            ]);
            if ($rid > 0 && $mid > 0) {
                $this->debriefs->setRecipientMessage($rid, $mid);
                $sent++;
            }
        }

        return ['id' => $id, 'sent' => $sent];
    }

    /** Relance des joueurs qui n'ont pas répondu : nouvel avis pour chacun. @return int avis envoyés */
    public function remind(int $tenantId, array $debrief, ?int $actorId): int
    {
        $n = 0;
        $expires = gmdate('Y-m-d H:i:s', time() + 48 * 3600);
        foreach ($this->debriefs->recipients($tenantId, (int) $debrief['id']) as $r) {
            if (!empty($r['submitted_at']) || !empty($r['read_at'])) {
                continue;
            }
            if (!empty($r['message_id']) && empty($r['msg_acked_at']) && empty($r['msg_cancelled_at'])) {
                // L'avis précédent est remplacé par celui-ci.
                $this->fleet->cancelMessage((int) $r['message_id']);
            }
            $mid = $this->fleet->createMessage([
                'tenant_id' => $tenantId,
                'user_id' => ((int) ($r['user_id'] ?? 0)) ?: null,
                'steam_uid' => ((string) ($r['steam_uid'] ?? '')) ?: null,
                'terminal_uid' => null,
                'kind' => 'debrief',
                'title' => mb_substr('Rappel · ' . (string) $debrief['label'], 0, 120),
                'body' => AtakDebriefSpec::legacyBody((string) $debrief['mode'], (string) $debrief['label']),
                'level' => 'warning',
                'require_ack' => true,
                'expires_at' => $expires,
                'actor_id' => $actorId,
            ]);
            if ($mid > 0) {
                $this->debriefs->setRecipientMessage((int) $r['id'], $mid);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Avis « debrief » tels que le téléphone les lit (fiche dans le texte, méta « debrief=<id>;mode=<mode> »), plus la ligne
     * de réglages « debrief_cfg » (questionnaire par défaut, droit d'imposer le CR) quand le mod sait les lire.
     *
     * @param list<array<string, mixed>> $messages lignes déjà préparées pour la réponse
     * @return list<array<string, mixed>>
     */
    public function decoratePoll(int $tenantId, ?int $userId, ?string $steam, array $messages, bool $capable): array
    {
        $ids = [];
        foreach ($messages as $m) {
            if (($m['kind'] ?? '') === 'debrief') {
                $ids[] = (int) $m['id'];
            }
        }
        $byMessage = $ids !== [] ? $this->debriefs->debriefsForMessages($ids) : [];
        foreach ($messages as &$m) {
            if (($m['kind'] ?? '') !== 'debrief') {
                continue;
            }
            $d = $byMessage[(int) $m['id']] ?? null;
            if ($d === null || !empty($d['closed_at'])) {
                continue;
            }
            if (!$capable) {
                // ATAK trop ancien : avis classique (le texte demande la mise à jour).
                $m['kind'] = 'message';
                continue;
            }
            $m['body'] = AtakDebriefSpec::phoneSpec((string) $d['mode'], (string) $d['label'], (string) ($d['intro'] ?? ''), (string) ($d['read_text'] ?? ''), $d['questions']);
            $m['meta'] = 'debrief=' . (int) $d['id'] . ';mode=' . (string) $d['mode'] . ';src=site';
        }
        unset($m);
        if (!$capable) {
            return $messages;
        }
        $size = 0;
        foreach ($messages as $m) {
            $size += strlen((string) ($m['body'] ?? '')) + strlen((string) ($m['title'] ?? '')) + 40;
        }
        $cfg = $this->configRow($tenantId, $userId, $steam);
        if ($size + strlen($cfg['body']) < 7200) {
            $messages[] = $cfg;
        }

        return $messages;
    }

    /**
     * Réglages de la communauté pour le téléphone : questionnaire par défaut et droit d'imposer le CR.
     * L'identifiant change avec le contenu : le téléphone ne relit la ligne que si elle a changé.
     *
     * @return array{id: string, kind: string, level: string, title: string, body: string, require_ack: bool, created_at: string, meta: string}
     */
    public function configRow(int $tenantId, ?int $userId, ?string $steam): array
    {
        $s = $this->debriefs->settings($tenantId);
        $may = $this->debriefs->playerMayForce($tenantId, $userId, $steam);
        $spec = AtakDebriefSpec::phoneSpec($s['default_mode'], '', $s['intro'], '', $this->questionsFor($tenantId, $s['template_id']));
        $meta = 'rights=' . ($may ? '1' : '0') . ';zeus=' . ($s['zeus_allowed'] ? '1' : '0') . ';mode=' . $s['default_mode'];

        return [
            'id' => 'C' . sprintf('%u', crc32($spec . $meta)),
            'kind' => 'debrief_cfg',
            'level' => 'info',
            'title' => '',
            'body' => $spec,
            'require_ack' => false,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'meta' => $meta,
        ];
    }

    /**
     * Compte rendu de type DEBRIEF reçu du téléphone. structured_data :
     *   debrief_event : forced (demande faite en jeu, liste des joueurs), opened, read, submitted (réponses a_<id>)
     *   debrief_id    : demande du site ; debrief_ref : demande faite en jeu (créée au premier événement reçu)
     *   mode, label, players (forced : [{uid, name}…]), a_<id> (réponses)
     *
     * @param array<string, mixed> $body corps de POST /api/atak/reports
     * @param array<string, mixed>|null $actor joueur authentifié (user_id, steam_uid, callsign)
     * @return array{handled: bool, keep_report: bool, debrief_id: int}
     */
    public function handleGameReport(int $tenantId, array $body, ?array $actor, ?int $reportId = null): array
    {
        $sd = is_array($body['structured_data'] ?? null) ? $body['structured_data'] : [];
        $event = strtolower(trim((string) ($sd['debrief_event'] ?? 'submitted')));
        if (!in_array($event, ['forced', 'opened', 'read', 'submitted'], true)) {
            $event = 'submitted';
        }
        $userId = ((int) ($actor['user_id'] ?? 0)) ?: null;
        $steam = SteamId::normalize((string) ($actor['steam_uid'] ?? $body['submitter_steam_id'] ?? ''));
        $callsign = trim((string) ($body['submitter_callsign'] ?? $actor['callsign'] ?? ''));
        $debrief = null;
        $siteId = (int) ($sd['debrief_id'] ?? 0);
        if ($siteId > 0) {
            $debrief = $this->debriefs->find($tenantId, $siteId);
        }
        $ref = preg_replace('/[^A-Za-z0-9_\-:.]/', '', (string) ($sd['debrief_ref'] ?? '')) ?? '';
        if ($debrief === null && $ref !== '') {
            $debrief = $this->debriefs->findByGameRef($tenantId, $ref);
            if ($debrief === null) {
                $mode = ($sd['mode'] ?? '') === 'read' ? 'read' : 'fill';
                $newId = $this->debriefs->create([
                    'tenant_id' => $tenantId,
                    'mission_key' => AtakMissionKey::normalize($body['mission_key'] ?? null),
                    'label' => trim((string) ($sd['label'] ?? '')) ?: 'Fin d’opération',
                    'mode' => $mode,
                    'read_text' => (string) ($sd['read_text'] ?? ''),
                    'source' => 'game',
                    'game_ref' => $ref,
                    'actor_label' => trim((string) ($sd['by'] ?? '')) ?: ($event === 'forced' ? $callsign : 'En jeu'),
                    'actor_id' => $event === 'forced' ? $userId : null,
                ]);
                $debrief = $newId > 0 ? $this->debriefs->find($tenantId, $newId) : $this->debriefs->findByGameRef($tenantId, $ref);
            }
        }
        if ($debrief === null) {
            return ['handled' => false, 'keep_report' => $event === 'submitted', 'debrief_id' => 0];
        }
        $id = (int) $debrief['id'];
        if ($event === 'forced') {
            foreach (is_array($sd['players'] ?? null) ? array_slice($sd['players'], 0, 300) : [] as $p) {
                $p = is_array($p) ? $p : ['uid' => (string) $p];
                $pSteam = SteamId::normalize((string) ($p['uid'] ?? ''));
                if ($pSteam === null) {
                    continue;
                }
                $this->debriefs->addRecipient($id, $tenantId, null, $pSteam, (string) ($p['name'] ?? ''));
            }

            return ['handled' => true, 'keep_report' => false, 'debrief_id' => $id];
        }
        $answers = $event === 'submitted' ? AtakDebriefSpec::answers($sd) : [];
        $this->debriefs->recordEvent($tenantId, $id, $userId, $steam, $callsign, $event, $answers, $reportId);

        return ['handled' => true, 'keep_report' => $event === 'submitted', 'debrief_id' => $id];
    }
}
