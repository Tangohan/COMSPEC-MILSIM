-- Pointage / présence : annulation d’événement, typologie, check-in jour J, rappel unique.
-- Appliqué de façon idempotente via run-migrations.php (ALTER conditionnels).

-- community_events
--   cancelled_at DATETIME NULL
--   cancelled_reason VARCHAR(500) NULL
--   event_type VARCHAR(32) NOT NULL DEFAULT 'evenement'  -- operation | evenement | formation | autre

-- community_event_rsvps
--   checked_in_at DATETIME NULL
--   reminder_sent_at DATETIME NULL
