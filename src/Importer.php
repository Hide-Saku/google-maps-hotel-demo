<?php
declare(strict_types=1);

/**
 * 外部 API の代わりに、ローカルの JSON（モック）から施設を取り込む。
 * 取得元(source)＋外部ID(external_id)をキーにするので、同じデータを何度流しても件数は増えない（冪等）。
 * 不正な行はスキップして、DB の import_skipped と logs/import_skipped.log に理由を残す。
 */
final class Importer
{
    public static function run(string $file, ?string $logFile = null): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('取り込みファイルを読めません: ' . $file);
        }
        $doc = json_decode($raw, true);
        if (!is_array($doc) || !is_string($doc['source'] ?? null) || !is_array($doc['items'] ?? null)
            || !preg_match('/^[a-z0-9_-]{1,30}$/', $doc['source'])) {
            throw new RuntimeException('取り込みファイルの形式が正しくありません（source と items が必要）');
        }
        $source = $doc['source'];
        $pdo = Db::pdo();
        $stats = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        $skipped = [];
        $seen = [];
        $hotels = [];

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO import_runs (source, file, status) VALUES (?, ?, ?)')
                ->execute([$source, basename($file), 'running']);
            $runId = (int)$pdo->lastInsertId();
            $find = $pdo->prepare('SELECT id, name, category, lat, lng, address, description FROM facilities WHERE hotel_id = ? AND source = ? AND external_id = ?');
            $upd = $pdo->prepare('UPDATE facilities SET name = ?, category = ?, lat = ?, lng = ?, address = ?, description = ? WHERE id = ?');

            foreach ($doc['items'] as $i => $row) {
                [$clean, $hotel, $ext, $reason] = self::check($row, $hotels, $seen);
                if ($reason !== null) {
                    $stats['skipped']++;
                    $skipped[] = ['row' => (int)$i, 'reason' => $reason, 'raw' => json_encode($row, JSON_UNESCAPED_UNICODE)];
                    continue;
                }
                $seen[$hotel['id'] . '|' . $ext] = true;
                $find->execute([$hotel['id'], $source, $ext]);
                $cur = $find->fetch();
                if (!$cur) {
                    Repo::createFacility((int)$hotel['id'], $clean, $source, $ext);
                    $stats['inserted']++;
                } elseif (self::differs($cur, $clean)) {
                    $upd->execute([$clean['name'], $clean['category'], $clean['lat'], $clean['lng'], $clean['address'], $clean['description'], $cur['id']]);
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
            }

            $ins = $pdo->prepare('INSERT INTO import_skipped (run_id, row_index, reason, raw_json) VALUES (?, ?, ?, ?)');
            foreach ($skipped as $s) {
                $ins->execute([$runId, $s['row'], $s['reason'], (string)$s['raw']]);
            }
            $pdo->prepare('UPDATE import_runs SET inserted = ?, updated = ?, unchanged = ?, skipped = ?, status = ? WHERE id = ?')
                ->execute([$stats['inserted'], $stats['updated'], $stats['unchanged'], $stats['skipped'], 'ok', $runId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if ($logFile !== null && $skipped) {
            $lines = '';
            foreach ($skipped as $s) {
                $lines .= sprintf("%s run=%d row=%d skip: %s | %s\n", date('c'), $runId, $s['row'], $s['reason'], $s['raw']);
            }
            @file_put_contents($logFile, $lines, FILE_APPEND | LOCK_EX);
        }
        return $stats + ['run_id' => $runId, 'skipped_rows' => $skipped];
    }

    /** 1行を検証する。返り値: [整形済みの値, ホテル, 外部ID, スキップ理由 または null] */
    private static function check(mixed $row, array &$hotels, array $seen): array
    {
        if (!is_array($row)) {
            return [null, null, null, '行がオブジェクトではありません'];
        }
        $slug = is_string($row['hotel'] ?? null) ? $row['hotel'] : '';
        if (!preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
            return [null, null, null, 'hotel（スラッグ）が正しくありません'];
        }
        if (!array_key_exists($slug, $hotels)) {
            $hotels[$slug] = Repo::hotelBySlug($slug);
        }
        $hotel = $hotels[$slug];
        if ($hotel === null) {
            return [null, null, null, '存在しないホテルです: ' . $slug];
        }
        $ext = is_string($row['external_id'] ?? null) ? trim($row['external_id']) : '';
        if ($ext === '' || strlen($ext) > 64) {
            return [null, null, null, 'external_id がありません（または長すぎます）'];
        }
        [$clean, $errors] = Validator::facility($row);
        if ($clean === null) {
            return [null, null, null, implode(' / ', $errors)];
        }
        if (isset($seen[$hotel['id'] . '|' . $ext])) {
            return [null, null, null, '同じファイル内で external_id が重複しています: ' . $ext];
        }
        return [$clean, $hotel, $ext, null];
    }

    private static function differs(array $cur, array $new): bool
    {
        foreach (['name', 'category', 'lat', 'lng', 'address', 'description'] as $k) {
            if ((string)$cur[$k] !== (string)$new[$k]) {
                return true;
            }
        }
        return false;
    }
}
