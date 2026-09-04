CREATE TABLE IF NOT EXISTS `athena_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL,
  `terminal_id` varchar(128) NOT NULL, `source_type` varchar(32) NOT NULL, `callsign` varchar(128) DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL, `server_name` varchar(191) DEFAULT NULL, `mission` varchar(191) DEFAULT NULL,
  `world` varchar(128) DEFAULT NULL, `mod_version` varchar(64) DEFAULT NULL, `extension_version` varchar(64) DEFAULT NULL,
  `last_seen_at` datetime(3) NOT NULL, `status` enum('online','degraded','offline') NOT NULL DEFAULT 'online',
  `metadata` json DEFAULT NULL, `created_at` datetime(3) NOT NULL, `updated_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_source_terminal` (`tenant_id`,`terminal_id`), KEY `idx_athena_sources_seen` (`tenant_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `athena_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `source_id` bigint unsigned NOT NULL,
  `event_id` varchar(128) NOT NULL, `schema_name` varchar(64) NOT NULL, `event_type` varchar(128) NOT NULL,
  `entity_id` varchar(191) DEFAULT NULL, `client_timestamp` datetime(3) NOT NULL, `accepted_at` datetime(3) NOT NULL,
  `persisted_at` datetime(3) NOT NULL, `world` varchar(128) DEFAULT NULL, `mission` varchar(191) DEFAULT NULL,
  `server_name` varchar(191) DEFAULT NULL, `payload` json NOT NULL, `pipeline` json DEFAULT NULL,
  `payload_size` int unsigned NOT NULL DEFAULT 0, `latency_ms` int DEFAULT NULL, `status` varchar(24) NOT NULL DEFAULT 'accepted',
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_event_id` (`tenant_id`,`event_id`), KEY `idx_athena_event_feed` (`tenant_id`,`id`),
  KEY `idx_athena_entity_history` (`tenant_id`,`entity_id`,`client_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `athena_ingest_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `event_id` varchar(128) NOT NULL,
  `event_type` varchar(128) NOT NULL, `received_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_receipt` (`tenant_id`,`event_id`), KEY `idx_athena_receipt_retention` (`tenant_id`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `athena_live_state` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `source_id` bigint unsigned NOT NULL,
  `state_type` varchar(128) NOT NULL, `entity_id` varchar(191) NOT NULL, `event_id` varchar(128) NOT NULL, `world` varchar(128) DEFAULT NULL,
  `mission` varchar(191) DEFAULT NULL, `server_name` varchar(191) DEFAULT NULL, `payload` json NOT NULL,
  `client_timestamp` datetime(3) NOT NULL, `updated_at` datetime(3) NOT NULL, `payload_size` int unsigned NOT NULL DEFAULT 0, `latency_ms` int DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_live_entity` (`tenant_id`,`source_id`,`state_type`,`entity_id`), KEY `idx_athena_live_world` (`tenant_id`,`world`,`state_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `athena_map_objects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `object_id` varchar(191) NOT NULL,
  `source_id` bigint unsigned NOT NULL, `world` varchar(128) NOT NULL, `object_type` enum('marker','drawing','route','zone','poi','intel','contact') NOT NULL,
  `subtype` varchar(128) DEFAULT NULL, `world_x` decimal(15,4) DEFAULT NULL, `world_y` decimal(15,4) DEFAULT NULL,
  `world_z` decimal(12,4) DEFAULT NULL, `heading` decimal(8,3) DEFAULT NULL, `label` varchar(255) DEFAULT NULL,
  `scope` enum('local','session','group','mission','server','tenant') NOT NULL DEFAULT 'mission', `persistent` tinyint(1) NOT NULL DEFAULT 0,
  `geometry` json DEFAULT NULL, `style` json DEFAULT NULL, `metadata` json DEFAULT NULL, `version` int unsigned NOT NULL DEFAULT 1,
  `created_by` varchar(191) NOT NULL, `created_at` datetime(3) NOT NULL, `updated_by` varchar(191) NOT NULL, `updated_at` datetime(3) NOT NULL,
  `deleted_by` varchar(191) DEFAULT NULL, `deleted_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_map_object` (`tenant_id`,`object_id`), KEY `idx_athena_map_world` (`tenant_id`,`world`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `athena_sync_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `sync_id` varchar(128) NOT NULL, `source_id` bigint unsigned NOT NULL,
  `status` enum('started','completed','failed','conflict') NOT NULL, `queue_size` int unsigned NOT NULL DEFAULT 0,
  `summary` json DEFAULT NULL, `started_at` datetime(3) NOT NULL, `completed_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_sync_id` (`tenant_id`,`sync_id`), KEY `idx_athena_sync_source` (`tenant_id`,`source_id`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `athena_sync_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `sync_session_id` bigint unsigned NOT NULL,
  `event_id` varchar(128) NOT NULL, `item_type` varchar(128) NOT NULL, `status` enum('accepted','known','coalesced','conflict','rejected') NOT NULL,
  `client_version` int unsigned DEFAULT NULL, `server_version` int unsigned DEFAULT NULL, `detail` json DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_sync_item` (`tenant_id`,`event_id`), KEY `idx_athena_sync_items_session` (`sync_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `athena_ingest_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `bucket_at` datetime NOT NULL,
  `events_accepted` int unsigned NOT NULL DEFAULT 0, `state_updates` int unsigned NOT NULL DEFAULT 0, `duplicates` int unsigned NOT NULL DEFAULT 0,
  `invalid_payloads` int unsigned NOT NULL DEFAULT 0, `auth_failures` int unsigned NOT NULL DEFAULT 0, `db_writes` int unsigned NOT NULL DEFAULT 0,
  `latency_sum_ms` bigint unsigned NOT NULL DEFAULT 0, `latency_samples` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_metric_bucket` (`tenant_id`,`bucket_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `athena_terrain_chunks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `source_id` bigint unsigned NOT NULL, `world` varchar(128) NOT NULL,
  `layer_name` varchar(64) NOT NULL, `chunk_id` varchar(191) NOT NULL, `bounds` json NOT NULL, `coverage_status` enum('unknown','partial','complete','error') NOT NULL,
  `content_hash` char(64) DEFAULT NULL, `storage_ref` varchar(500) DEFAULT NULL, `metadata` json DEFAULT NULL, `received_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_terrain_chunk` (`tenant_id`,`world`,`layer_name`,`chunk_id`), KEY `idx_athena_terrain_coverage` (`tenant_id`,`world`,`coverage_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `athena_scene_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` int unsigned NOT NULL, `source_id` bigint unsigned NOT NULL, `snapshot_id` varchar(191) NOT NULL,
  `world` varchar(128) NOT NULL, `object_count` int unsigned NOT NULL DEFAULT 0, `bounds` json DEFAULT NULL, `content_hash` char(64) DEFAULT NULL,
  `storage_ref` varchar(500) DEFAULT NULL, `metadata` json DEFAULT NULL, `captured_at` datetime(3) NOT NULL, `received_at` datetime(3) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_athena_scene_snapshot` (`tenant_id`,`snapshot_id`), KEY `idx_athena_scene_world` (`tenant_id`,`world`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
