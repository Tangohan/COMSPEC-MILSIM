<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Core\Database;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EventAutomationRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\EventRecurrence;
use PDO;

final class EndlessEventSeriesService
{
    public function __construct(private FeatureGateService $gate)
    {
    }

    /** Maintient douze occurrences à venir, avec verrouillage et quota pour chaque création. */
    public function replenish(): int
    {
        $pdo = Database::getPdo();
        $automation = new EventAutomationRepository($pdo);
        $events = new CommunityEventRepository();
        $created = 0;
        foreach ($automation->activeSeries() as $row) {
            // Initialiser les dépendances susceptibles de migrer avant d’ouvrir la transaction.
            $this->gate->allows((int) $row['tenant_id'], 'events');
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare('SELECT * FROM community_event_series WHERE id = ? AND active = 1 FOR UPDATE');
                $lock->execute([$row['id']]);
                $series = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$series) {
                    $pdo->commit();
                    continue;
                }
                $tenant = (int) $series['tenant_id'];
                $count = $pdo->prepare('SELECT COUNT(*) FROM community_events WHERE tenant_id = ? AND series_id = ? AND cancelled_at IS NULL AND starts_at >= NOW()');
                $count->execute([$tenant, $series['id']]);
                $needed = max(0, 12 - (int) $count->fetchColumn());
                $template = json_decode((string) $series['template_json'], true, 512, JSON_THROW_ON_ERROR);
                $days = json_decode((string) $series['weekdays_json'], true, 512, JSON_THROW_ON_ERROR);
                $cursor = (string) $series['cursor_at'];
                // Après une longue interruption, reprendre les prochains créneaux sans créer d’historique fictif.
                $after = max($cursor, date('Y-m-d H:i:s'));
                for ($i = 0; $i < $needed; $i++) {
                    if (!$this->gate->allows($tenant, 'events')) {
                        break;
                    }
                    $next = EventRecurrence::nextOccurrence((string) $series['anchor_at'], $after, (string) $series['frequency'], $days);
                    if ($next === null) {
                        break;
                    }
                    $end = date('Y-m-d H:i:s', (int) strtotime($next) + (int) $template['duration']);
                    $id = $events->create($tenant, (int) $series['created_by_user_id'], $template['title'], $template['description'], $template['location'], $next, $end, $template['campaign_tag'], $template['event_type']);
                    $events->assignSeries($id, $tenant, (string) $series['id']);
                    $events->updateDetails($id, $tenant, $template['details']);
                    if (!empty($template['discord'])) {
                        $d = $template['discord'];
                        $automation->configureDiscord($tenant, $id, $d['destination'], (bool) $d['rsvp'], $d['locale']);
                    }
                    $this->gate->recordQuotaUse($tenant, 'events', (int) $series['created_by_user_id']);
                    $cursor = $after = $next;
                    $created++;
                }
                $pdo->prepare('UPDATE community_event_series SET cursor_at = ?, checked_at = NOW() WHERE id = ?')->execute([$cursor, $series['id']]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        return $created;
    }
}
