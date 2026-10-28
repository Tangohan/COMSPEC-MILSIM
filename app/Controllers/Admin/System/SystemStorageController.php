<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Platform\PlatformDataBrowserService;
use App\Services\Platform\PlatformStorageService;
use App\Support\MaintenanceGuard;
use App\Support\PlatformStorageCatalog;

final class SystemStorageController
{
    public function __construct(
        private ?PlatformStorageService $storage = null,
        private ?AuditService $audit = null,
        private ?PlatformDataBrowserService $browser = null,
    ) {
        $this->storage ??= new PlatformStorageService();
        $this->audit ??= new AuditService();
        $this->browser ??= new PlatformDataBrowserService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $groups = [];
        foreach (PlatformStorageCatalog::purgeGroups() as $group) {
            $usage = $this->storage->groupUsage($group);
            $bytes = 0;
            foreach ($usage['tables'] as $t) {
                $bytes += (int) ($t['bytes'] ?? 0);
            }
            foreach ($usage['directories'] as $d) {
                $bytes += (int) ($d['bytes'] ?? 0);
            }
            $groups[] = $group + ['usage' => $usage, 'bytes' => $bytes];
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.storage',
            'title' => 'Espace disque et historiques',
            'disk' => $this->storage->diskSnapshot(),
            'directories' => $this->storage->directorySnapshots(),
            'largestTables' => $this->storage->largestTables(20),
            'purgeGroups' => $groups,
            'confirmWord' => PlatformStorageCatalog::CONFIRM_WORD,
            'tableCount' => count($this->browser->listTables()),
        ]);
    }

    public function purge(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/storage'));
        }

        $key = trim((string) $request->input('group_key', ''));
        $typed = trim((string) $request->input('confirm_word', ''));
        $ack = $request->input('acknowledge') === '1';

        if (!$ack) {
            Session::flash('error', 'Cochez la confirmation : l’action concerne toutes les communautés.');

            return Response::redirect(url('admin/system/storage'));
        }
        if (strcasecmp($typed, PlatformStorageCatalog::CONFIRM_WORD) !== 0) {
            Session::flash('error', 'Saisissez le mot de confirmation indiqué pour valider le vidage.');

            return Response::redirect(url('admin/system/storage'));
        }

        $group = PlatformStorageCatalog::groupByKey($key);
        if ($group === null) {
            Session::flash('error', 'Lot introuvable.');

            return Response::redirect(url('admin/system/storage'));
        }

        @set_time_limit(300);
        $result = $this->storage->purgeGroup($key);
        $actorId = Session::get('user_id') ? (int) Session::get('user_id') : null;
        try {
            $this->audit->log(
                AuditAction::PLATFORM_STORAGE_PURGED,
                null,
                $actorId,
                'storage_purge',
                null,
                $group['title'],
                (string) ($result['files_removed'] ?? 0),
                MaintenanceGuard::resolveClientIp()
            );
        } catch (\Throwable) {
        }

        if (!empty($result['ok'])) {
            $files = (int) ($result['files_removed'] ?? 0);
            Session::flash(
                'success',
                $files > 0
                    ? 'Lot « ' . $group['title'] . ' » vidé. ' . $files . ' fichier(s) retiré(s) du disque.'
                    : 'Lot « ' . $group['title'] . ' » vidé.'
            );
        } else {
            Session::flash('error', (string) ($result['message'] ?? 'Vidage impossible.'));
        }

        return Response::redirect(url('admin/system/storage'));
    }

    /** Explorateur : liste des tables, avec recherche sur le nom. */
    public function tables(Request $request, array $params = []): Response
    {
        $search = trim((string) $request->query('q', ''));

        return Response::view('layout.main', [
            'content' => 'admin.system.storage_tables',
            'title' => 'Explorateur de données',
            'tables' => $this->browser->listTables($search),
            'search' => $search,
        ]);
    }

    /** Explorateur : lignes d’une table (recherche, filtre, tri, pages, export CSV). */
    public function table(Request $request, array $params = []): Response
    {
        $table = $this->browser->resolveTable((string) ($params['table'] ?? ''));
        if ($table === null) {
            Session::flash('error', 'Table introuvable.');

            return Response::redirect(url('admin/system/storage/donnees'));
        }
        $opts = [
            'q' => trim((string) $request->query('q', '')),
            'col' => (string) $request->query('col', ''),
            'op' => (string) $request->query('op', 'contains'),
            'val' => (string) $request->query('val', ''),
            'sort' => (string) $request->query('sort', ''),
            'dir' => (string) $request->query('dir', 'desc'),
            'page' => (int) $request->query('page', 1),
        ];
        $columns = $this->browser->columns($table);

        if ($request->query('format') === 'csv') {
            $opts['page'] = 1;
            $result = $this->browser->browse($table, $opts, PlatformDataBrowserService::EXPORT_LIMIT);

            return $this->csv($table, $columns, $result['rows']);
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.storage_table',
            'title' => 'Données · ' . $table,
            'table' => $table,
            'columns' => $columns,
            'primaryKey' => $this->browser->primaryKey($table),
            'result' => $this->browser->browse($table, $opts),
            'opts' => $opts,
            'browser' => $this->browser,
            'isProtected' => PlatformDataBrowserService::isProtected($table),
            'isReadOnly' => PlatformDataBrowserService::isReadOnly($table),
        ]);
    }

    /** Explorateur : fiche d’une ligne (lecture complète et formulaire de modification). */
    public function row(Request $request, array $params = []): Response
    {
        $table = $this->browser->resolveTable((string) ($params['table'] ?? ''));
        $pk = $request->query('pk');
        $row = ($table !== null && is_array($pk)) ? $this->browser->findRow($table, $pk) : null;
        if ($table === null || $row === null) {
            Session::flash('error', 'Ligne introuvable.');

            return Response::redirect($table !== null ? $this->tableUrl($table) : url('admin/system/storage/donnees'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.storage_row',
            'title' => 'Ligne · ' . $table,
            'table' => $table,
            'columns' => $this->browser->columns($table),
            'row' => $row,
            'pk' => $pk,
            'isReadOnly' => PlatformDataBrowserService::isReadOnly($table),
            'back' => (string) $request->query('retour', ''),
        ]);
    }

    public function updateRow(Request $request, array $params = []): Response
    {
        $table = $this->browser->resolveTable((string) ($params['table'] ?? ''));
        $pk = $request->input('pk');
        if ($table === null || !is_array($pk) || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou ligne introuvable. Réessayez.');

            return Response::redirect(url('admin/system/storage/donnees'));
        }
        $values = $request->input('v', []);
        $nulls = $request->input('null', []);
        $result = $this->browser->updateRow(
            $table,
            $pk,
            is_array($values) ? $values : [],
            is_array($nulls) ? array_values(array_map('strval', $nulls)) : []
        );
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Modification impossible.'));
        } elseif (($result['changes'] ?? []) === []) {
            Session::flash('success', 'Aucune modification à enregistrer.');
        } else {
            $old = [];
            $new = [];
            foreach ($result['changes'] as $col => [$o, $n]) {
                $old[$col] = $o;
                $new[$col] = $n;
            }
            $this->auditWrite(AuditAction::PLATFORM_DATA_ROW_UPDATED, $table, $pk, $old, $new);
            Session::flash('success', count($result['changes']) . ' champ(s) modifié(s) : ' . implode(', ', array_keys($result['changes'])) . '.');
        }

        return Response::redirect($this->rowUrl($table, $pk, (string) $request->input('retour', '')));
    }

    public function deleteRow(Request $request, array $params = []): Response
    {
        $table = $this->browser->resolveTable((string) ($params['table'] ?? ''));
        $pk = $request->input('pk');
        if ($table === null || !is_array($pk) || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou ligne introuvable. Réessayez.');

            return Response::redirect(url('admin/system/storage/donnees'));
        }
        $result = $this->browser->deleteRow($table, $pk);
        $back = $this->safeBack((string) $request->input('retour', ''), $table);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Suppression impossible.'));

            return Response::redirect($back);
        }
        $this->auditWrite(AuditAction::PLATFORM_DATA_ROW_DELETED, $table, $pk, $result['row'] ?? [], []);
        Session::flash('success', 'Ligne supprimée de « ' . $table . ' ».');

        return Response::redirect($back);
    }

    public function emptyTable(Request $request, array $params = []): Response
    {
        $table = $this->browser->resolveTable((string) ($params['table'] ?? ''));
        if ($table === null || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée ou table introuvable. Réessayez.');

            return Response::redirect(url('admin/system/storage/donnees'));
        }
        if (trim((string) $request->input('confirm_table', '')) !== $table) {
            Session::flash('error', 'Pour vider la table, recopiez exactement son nom : ' . $table . '.');

            return Response::redirect($this->tableUrl($table));
        }
        @set_time_limit(300);
        $result = $this->browser->emptyTable($table);
        if (empty($result['ok'])) {
            Session::flash('error', (string) ($result['error'] ?? 'Vidage impossible.'));

            return Response::redirect($this->tableUrl($table));
        }
        $this->auditWrite(AuditAction::PLATFORM_DATA_TABLE_EMPTIED, $table, [], ['lignes' => (int) ($result['rows_before'] ?? 0)], ['lignes' => 0]);
        Session::flash('success', 'Table « ' . $table . ' » vidée (' . (int) ($result['rows_before'] ?? 0) . ' ligne(s) supprimée(s)).');

        return Response::redirect($this->tableUrl($table));
    }

    /**
     * @param array<string, mixed> $pk
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function auditWrite(string $action, string $table, array $pk, array $old, array $new): void
    {
        $mask = static function (array $fields): string {
            $out = [];
            foreach ($fields as $k => $v) {
                $out[$k] = PlatformDataBrowserService::isSensitiveColumn((string) $k) ? '(masqué)' : $v;
            }

            return (string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        };
        $entityId = count($pk) === 1 && ctype_digit((string) reset($pk)) ? (int) reset($pk) : null;
        try {
            $this->audit->log(
                $action,
                null,
                Session::get('user_id') ? (int) Session::get('user_id') : null,
                mb_substr('table:' . $table, 0, 100),
                $entityId,
                $mask(['cle' => $pk] + $old),
                $new === [] ? null : $mask($new),
                MaintenanceGuard::resolveClientIp()
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @param list<array<string, mixed>> $rows
     */
    private function csv(string $table, array $columns, array $rows): Response
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_column($columns, 'name'), ';', '"', '\\');
        foreach ($rows as $r) {
            $line = [];
            foreach ($columns as $c) {
                $v = PlatformDataBrowserService::displayValue($r[$c['name']] ?? null, $c['sensitive'], 0);
                // Protection tableur : une cellule qui commence par = + - @ n’est pas lue comme une formule.
                $line[] = preg_match('/^[=+\-@]/', $v) === 1 ? "'" . $v : $v;
            }
            fputcsv($fh, $line, ';', '"', '\\');
        }
        rewind($fh);
        $body = (string) stream_get_contents($fh);
        fclose($fh);

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $table . '-' . date('Ymd-His') . '.csv"')
            ->setBody($body);
    }

    private function tableUrl(string $table): string
    {
        return url('admin/system/storage/donnees/' . rawurlencode($table));
    }

    /** @param array<string, mixed> $pk */
    private function rowUrl(string $table, array $pk, string $back): string
    {
        $q = http_build_query(['pk' => $pk] + ($back !== '' ? ['retour' => $back] : []));

        return url('admin/system/storage/donnees/' . rawurlencode($table) . '/ligne') . '?' . $q;
    }

    /** Retour vers la liste filtrée d’où l’on vient, seulement si c’est bien l’explorateur de cette table. */
    private function safeBack(string $back, string $table): string
    {
        $prefix = url('admin/system/storage/donnees/' . rawurlencode($table));
        if ($back !== '' && str_starts_with($back, $prefix) && !str_contains($back, "\n")) {
            return $back;
        }

        return $this->tableUrl($table);
    }
}
