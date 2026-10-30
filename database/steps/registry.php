<?php

declare(strict_types=1);

/**
 * Active queue for schema changes added after the 2026-10-29 baseline.
 *
 * Historical entries are retained in registry-archive.php for reference only.
 * Never register the archive or scan migrations/*.sql from this queue. Add an
 * explicit, self-contained step when a new table or column is needed. Successful
 * steps are skipped on normal runs; --step=id is an explicit retry of an active entry.
 */
return [];
