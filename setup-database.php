<?php

declare(strict_types=1);

/**
 * Compatibility entry point for the active migration queue.
 *
 * The existing installation is the 2026-10-29 baseline. This command no longer
 * reinstalls the historical schema, seeds reference data, or runs maintenance.
 * Add new schema steps to database/steps/registry.php when needed.
 * An empty queue is a successful no-op, including without database credentials.
 *
 * php setup-database.php [--list | --status | --step=active_id]
 */
require __DIR__ . '/run-migrations.php';
