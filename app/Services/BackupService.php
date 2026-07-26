<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * 站点备份：数据库（全表 JSON 导出，跨 SQLite/MySQL）+ 材质文件 + RSA 密钥 → zip。
 * 备份文件存 storage/backups/。
 */
class BackupService
{
    /** 备份中排除的表（会话/缓存类瞬态数据） */
    protected const EXCLUDED_TABLES = ['sessions', 'cache', 'cache_locks', 'migrations'];

    public function backupsDir(): string
    {
        $dir = storage_path('backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    /** @return array{file: string, size: int} */
    public function create(): array
    {
        $timestamp = now()->format('Ymd_His');
        $path = $this->backupsDir().DIRECTORY_SEPARATOR."backup_{$timestamp}.zip";

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建备份文件。');
        }

        // 1. 数据库 → database/<table>.json
        foreach ($this->tables() as $table) {
            $rows = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
            $zip->addFromString(
                "database/{$table}.json",
                json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        // 2. 材质文件
        $texturesDir = storage_path('textures');
        if (is_dir($texturesDir)) {
            foreach (scandir($texturesDir) as $file) {
                $full = $texturesDir.DIRECTORY_SEPARATOR.$file;
                if (is_file($full)) {
                    $zip->addFile($full, "textures/{$file}");
                }
            }
        }

        // 3. Yggdrasil RSA 密钥（丢失会导致所有客户端验签失败，必须备份）
        foreach (['private.pem', 'public.pem'] as $key) {
            $keyPath = storage_path('yggdrasil'.DIRECTORY_SEPARATOR.$key);
            if (is_file($keyPath)) {
                $zip->addFile($keyPath, "yggdrasil/{$key}");
            }
        }

        // 4. 元信息
        $zip->addFromString('manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'app_version' => '1.0.0',
            'db_connection' => config('database.default'),
            'tables' => $this->tables(),
        ], JSON_PRETTY_PRINT));

        $zip->close();

        return ['file' => $path, 'size' => (int) filesize($path)];
    }

    /** @return array<int, array{name: string, size: int, created_at: int}> */
    public function list(): array
    {
        $backups = [];

        foreach (glob($this->backupsDir().DIRECTORY_SEPARATOR.'backup_*.zip') as $file) {
            $backups[] = [
                'name' => basename($file),
                'size' => (int) filesize($file),
                'created_at' => (int) filemtime($file),
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    public function delete(string $name): bool
    {
        $path = $this->resolve($name);

        return $path !== null && unlink($path);
    }

    /** 防路径穿越的文件名解析 */
    public function resolve(string $name): ?string
    {
        if (! preg_match('/^backup_\d{8}_\d{6}\.zip$/', $name)) {
            return null;
        }

        $path = $this->backupsDir().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /** 保留最近 N 份，删除更旧的 */
    public function prune(int $keep = 10): int
    {
        $removed = 0;

        foreach (array_slice($this->list(), $keep) as $backup) {
            if ($this->delete($backup['name'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /** @return string[] */
    protected function tables(): array
    {
        $connection = config('database.default');

        $names = $connection === 'sqlite'
            ? array_column(DB::select("SELECT name FROM sqlite_master WHERE type='table'"), 'name')
            : array_map(fn ($t) => array_values((array) $t)[0], DB::select('SHOW TABLES'));

        return array_values(array_filter(
            $names,
            fn ($t) => ! in_array($t, self::EXCLUDED_TABLES, true) && ! str_starts_with($t, 'sqlite_')
        ));
    }
}
