<?php

declare(strict_types=1);

namespace App\Services\Backup;

use PDO;
use RuntimeException;

/**
 * Dump et restauration MySQL pour une copie de données.
 * Préfère mysqldump / mysql s’ils sont dans le PATH ; sinon dump PHP (tables + données + vues).
 */
final class MysqlSnapshotIo
{
    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     * @param callable(string):void $log
     * @return array{method: string, bytes: int}
     */
    public function dumpToGzip(PDO $pdo, array $cfg, string $gzPath, callable $log): array
    {
        $dir = dirname($gzPath);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier de copie.');
        }

        $mysqldump = $this->which('mysqldump');
        if ($mysqldump !== null) {
            try {
                $bytes = $this->dumpWithClient($mysqldump, $cfg, $gzPath, $log);
                if ($bytes > 32) {
                    return ['method' => 'mysqldump', 'bytes' => $bytes];
                }
                $log('mysqldump a produit un fichier trop petit — bascule sur le dump PHP.');
            } catch (\Throwable $e) {
                $log('mysqldump indisponible (' . $e->getMessage() . ') — dump PHP.');
            }
        } else {
            $log('mysqldump introuvable — dump PHP.');
        }

        $bytes = $this->dumpWithPhp($pdo, $cfg, $gzPath, $log);

        return ['method' => 'php', 'bytes' => $bytes];
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     * @param callable(string):void $log
     * @return array{method: string}
     */
    public function restoreFromGzip(PDO $pdo, array $cfg, string $gzPath, callable $log): array
    {
        if (!is_file($gzPath) || filesize($gzPath) === 0) {
            throw new RuntimeException('Fichier de base introuvable dans la copie.');
        }

        $mysql = $this->which('mysql');
        if ($mysql !== null) {
            $sqlPath = $this->gunzipToTemp($gzPath);
            try {
                $this->restoreWithClient($mysql, $cfg, $sqlPath, $log);
                $log('Base restaurée avec le client mysql.');

                return ['method' => 'mysql'];
            } catch (\Throwable $e) {
                $log('Client mysql en échec (' . $e->getMessage() . ') — restauration PHP.');
            } finally {
                @unlink($sqlPath);
            }
        } else {
            $log('Client mysql introuvable — restauration PHP.');
        }

        $this->restoreWithPhp($pdo, $gzPath, $log);
        $log('Base restaurée en PHP.');

        return ['method' => 'php'];
    }

    public function which(string $binary): ?string
    {
        $envKey = strtoupper($binary) . '_BIN';
        $fromEnv = trim((string) (getenv($envKey) ?: ($_ENV[$envKey] ?? '')));
        if ($fromEnv !== '' && is_file($fromEnv)) {
            return $fromEnv;
        }

        $names = PHP_OS_FAMILY === 'Windows' ? [$binary . '.exe', $binary] : [$binary];
        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        foreach ($paths as $dir) {
            $dir = trim($dir);
            if ($dir === '') {
                continue;
            }
            foreach ($names as $name) {
                $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach (['/usr/bin', '/usr/local/bin', '/bin'] as $dir) {
            $candidate = $dir . '/' . $binary;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     * @param callable(string):void $log
     */
    private function dumpWithClient(string $mysqldump, array $cfg, string $gzPath, callable $log): int
    {
        $cnf = $this->writeClientCnf($cfg);
        $args = [
            $mysqldump,
            '--defaults-extra-file=' . $cnf,
            '--single-transaction',
            '--quick',
            '--hex-blob',
            '--default-character-set=' . ($cfg['charset'] ?? 'utf8mb4'),
            '--routines',
            '--events',
            '--triggers',
            '--skip-comments',
            $cfg['database'],
        ];

        try {
            $bytes = $this->pipeToGzip($args, $gzPath);
        } finally {
            @unlink($cnf);
        }

        $log('Dump mysqldump : ' . number_format($bytes) . ' octets compressés.');

        return $bytes;
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     * @param callable(string):void $log
     */
    private function restoreWithClient(string $mysql, array $cfg, string $sqlPath, callable $log): void
    {
        $cnf = $this->writeClientCnf($cfg);
        $args = [
            $mysql,
            '--defaults-extra-file=' . $cnf,
            '--default-character-set=' . ($cfg['charset'] ?? 'utf8mb4'),
            $cfg['database'],
        ];
        $spec = [
            0 => ['file', $sqlPath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        try {
            $proc = proc_open($args, $spec, $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($proc)) {
                throw new RuntimeException('Impossible de lancer mysql.');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
            if ($code !== 0) {
                throw new RuntimeException(trim((string) $stderr . ' ' . $stdout) !== '' ? trim((string) $stderr . ' ' . $stdout) : 'mysql code ' . $code);
            }
        } finally {
            @unlink($cnf);
        }
        unset($log);
    }

    /**
     * @param list<string> $args
     */
    private function pipeToGzip(array $args, string $gzPath): int
    {
        $spec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($args, $spec, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            throw new RuntimeException('Impossible de lancer mysqldump.');
        }

        $gz = gzopen($gzPath, 'wb6');
        if ($gz === false) {
            proc_close($proc);
            throw new RuntimeException('Impossible d’écrire le dump compressé.');
        }

        $bytes = 0;
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 1024 * 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $written = gzwrite($gz, $chunk);
            if ($written === false) {
                gzclose($gz);
                proc_close($proc);
                throw new RuntimeException('Écriture du dump interrompue.');
            }
            $bytes += $written;
        }
        gzclose($gz);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) {
            @unlink($gzPath);
            throw new RuntimeException(trim((string) $stderr) !== '' ? trim((string) $stderr) : 'mysqldump code ' . $code);
        }

        return (int) filesize($gzPath);
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     * @param callable(string):void $log
     */
    private function dumpWithPhp(PDO $pdo, array $cfg, string $gzPath, callable $log): int
    {
        $gz = gzopen($gzPath, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Impossible d’écrire le dump compressé.');
        }

        $charset = $cfg['charset'] ?? 'utf8mb4';
        if (preg_match('/^[a-zA-Z0-9_]+$/', $charset) !== 1) {
            $charset = 'utf8mb4';
        }
        $write = static function (string $sql) use ($gz): void {
            gzwrite($gz, $sql);
        };

        $write("-- Athena complete data snapshot (php)\n");
        $write("SET NAMES {$charset};\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\n");
        $write("SET UNIQUE_CHECKS=0;\n");
        $write("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $this->listBaseTables($pdo);
        $log('Tables à copier : ' . count($tables) . '.');

        $prevBuffered = true;
        try {
            $prevBuffered = (bool) $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } catch (\Throwable) {
            // pilote sans l’attribut : on continue en bufferisé
        }

        try {
            $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            foreach ($tables as $table) {
                $this->dumpTable($pdo, $table, $write);
            }
            foreach ($this->listViews($pdo) as $view) {
                $this->dumpView($pdo, $view, $write);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            gzclose($gz);
            @unlink($gzPath);
            throw $e;
        } finally {
            try {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $prevBuffered);
            } catch (\Throwable) {
            }
        }

        $write("SET UNIQUE_CHECKS=1;\n");
        $write("SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        $bytes = (int) filesize($gzPath);
        $log('Dump PHP : ' . number_format($bytes) . ' octets compressés.');

        return $bytes;
    }

    /**
     * @param callable(string):void $write
     */
    private function dumpTable(PDO $pdo, string $table, callable $write): void
    {
        $ident = $this->ident($table);
        $create = $pdo->query('SHOW CREATE TABLE ' . $ident);
        if ($create === false) {
            throw new RuntimeException('SHOW CREATE TABLE a échoué pour ' . $table);
        }
        $row = $create->fetch(PDO::FETCH_ASSOC) ?: [];
        $ddl = (string) ($row['Create Table'] ?? $row['Create table'] ?? '');
        if ($ddl === '') {
            throw new RuntimeException('DDL vide pour ' . $table);
        }

        $write("DROP TABLE IF EXISTS {$ident};\n");
        $write($ddl . ";\n\n");

        $select = $pdo->query('SELECT * FROM ' . $ident, PDO::FETCH_ASSOC);
        if ($select === false) {
            throw new RuntimeException('Lecture impossible pour ' . $table);
        }

        $batch = [];
        $batchBytes = 0;
        $cols = null;
        $flush = function () use (&$batch, &$batchBytes, &$cols, $ident, $write): void {
            if ($batch === [] || $cols === null) {
                return;
            }
            $write('INSERT INTO ' . $ident . ' (' . $cols . ') VALUES ' . implode(",\n", $batch) . ";\n");
            $batch = [];
            $batchBytes = 0;
        };

        while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = implode(', ', array_map(fn (string $c): string => $this->ident($c), array_keys($row)));
            }
            $vals = [];
            foreach ($row as $value) {
                $vals[] = $this->quoteValue($pdo, $value);
            }
            $tuple = '(' . implode(',', $vals) . ')';
            $batch[] = $tuple;
            $batchBytes += strlen($tuple);
            if (count($batch) >= 80 || $batchBytes >= 800000) {
                $flush();
            }
        }
        $flush();
        $select->closeCursor();
        $write("\n");
    }

    /**
     * @param callable(string):void $write
     */
    private function dumpView(PDO $pdo, string $view, callable $write): void
    {
        $ident = $this->ident($view);
        $stmt = $pdo->query('SHOW CREATE VIEW ' . $ident);
        if ($stmt === false) {
            return;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $ddl = (string) ($row['Create View'] ?? $row['Create view'] ?? '');
        if ($ddl === '') {
            return;
        }
        $write("DROP VIEW IF EXISTS {$ident};\n");
        $write($ddl . ";\n\n");
    }

    /**
     * @return list<string>
     */
    private function listBaseTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if ($stmt === false) {
            return [];
        }
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $out[] = (string) $row[0];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function listViews(PDO $pdo): array
    {
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        if ($stmt === false) {
            return [];
        }
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $out[] = (string) $row[0];
        }

        return $out;
    }

    /**
     * @param callable(string):void $log
     */
    private function restoreWithPhp(PDO $pdo, string $gzPath, callable $log): void
    {
        $gz = gzopen($gzPath, 'rb');
        if ($gz === false) {
            throw new RuntimeException('Impossible de lire le dump.');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET UNIQUE_CHECKS=0');

        $buf = '';
        $inString = false;
        $quote = '';
        $count = 0;
        try {
            while (!gzeof($gz)) {
                $chunk = gzread($gz, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $len = strlen($chunk);
                for ($i = 0; $i < $len; $i++) {
                    $ch = $chunk[$i];
                    $buf .= $ch;
                    if ($inString) {
                        if ($ch === '\\') {
                            if ($i + 1 < $len) {
                                $buf .= $chunk[$i + 1];
                                $i++;
                            }
                            continue;
                        }
                        if ($ch === $quote) {
                            $inString = false;
                            $quote = '';
                        }
                        continue;
                    }
                    if ($ch === "'" || $ch === '"' || $ch === '`') {
                        $inString = true;
                        $quote = $ch;
                        continue;
                    }
                    if ($ch === ';') {
                        $sql = trim($buf);
                        $buf = '';
                        if ($sql === '' || $sql === ';') {
                            continue;
                        }
                        $pdo->exec($sql);
                        $count++;
                    }
                }
            }
            $tail = trim($buf);
            if ($tail !== '' && $tail !== ';') {
                $pdo->exec($tail);
                $count++;
            }
        } finally {
            gzclose($gz);
            $pdo->exec('SET UNIQUE_CHECKS=1');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        $log('Instructions SQL exécutées : ' . $count . '.');
    }

    private function quoteValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $str = (string) $value;
        if ($str === '') {
            return $pdo->quote($str);
        }
        if (str_contains($str, "\0") || preg_match('//u', $str) !== 1) {
            return "x'" . bin2hex($str) . "'";
        }

        return $pdo->quote($str);
    }

    private function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset?:string} $cfg
     */
    private function writeClientCnf(array $cfg): string
    {
        $path = tempnam(sys_get_temp_dir(), 'athena_mysql_');
        if ($path === false) {
            throw new RuntimeException('Impossible de préparer le fichier client MySQL.');
        }
        $cnf = $path . '.cnf';
        @unlink($path);
        $body = "[client]\n"
            . 'user=' . $this->cnfValue($cfg['username']) . "\n"
            . 'password=' . $this->cnfValue($cfg['password']) . "\n"
            . 'host=' . $this->cnfValue($cfg['host']) . "\n"
            . 'port=' . (int) $cfg['port'] . "\n";
        if (file_put_contents($cnf, $body) === false) {
            throw new RuntimeException('Impossible d’écrire le fichier client MySQL.');
        }
        @chmod($cnf, 0600);

        return $cnf;
    }

    private function cnfValue(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function gunzipToTemp(string $gzPath): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'athena_sql_');
        if ($tmp === false) {
            throw new RuntimeException('Impossible de décompresser le dump.');
        }
        $sql = $tmp . '.sql';
        @unlink($tmp);
        $in = gzopen($gzPath, 'rb');
        $out = fopen($sql, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Impossible de décompresser le dump.');
        }
        while (!gzeof($in)) {
            $chunk = gzread($in, 1024 * 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);

        return $sql;
    }
}
