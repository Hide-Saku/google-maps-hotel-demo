<?php
declare(strict_types=1);

/** 入力の検証。返り値は [整形済みの値 または null, エラーの配列]。 */
final class Validator
{
    public const CATEGORIES = ['restaurant' => '飲食', 'sight' => '観光', 'shop' => '買い物', 'transport' => '交通'];

    public static function facility(array $in): array
    {
        $err = [];
        $name = self::str($in['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            $err[] = '施設名は1〜100文字で入力してください';
        }
        $cat = is_string($in['category'] ?? null) ? $in['category'] : '';
        if (!isset(self::CATEGORIES[$cat])) {
            $err[] = 'カテゴリが正しくありません';
        }
        $lat = self::coord($in['lat'] ?? null, -90.0, 90.0);
        if ($lat === null) {
            $err[] = '緯度は -90〜90 の数値で入力してください';
        }
        $lng = self::coord($in['lng'] ?? null, -180.0, 180.0);
        if ($lng === null) {
            $err[] = '経度は -180〜180 の数値で入力してください';
        }
        $address = self::str($in['address'] ?? '');
        if (mb_strlen($address) > 200) {
            $err[] = '住所は200文字以内で入力してください';
        }
        $desc = self::str($in['description'] ?? '');
        if (mb_strlen($desc) > 500) {
            $err[] = '説明は500文字以内で入力してください';
        }
        if ($err) {
            return [null, $err];
        }
        return [['name' => $name, 'category' => $cat, 'lat' => $lat, 'lng' => $lng, 'address' => $address, 'description' => $desc], []];
    }

    public static function hotelSettings(array $in): array
    {
        $err = [];
        $name = self::str($in['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            $err[] = 'ホテル名は1〜100文字で入力してください';
        }
        $lat = self::coord($in['center_lat'] ?? null, -90.0, 90.0);
        $lng = self::coord($in['center_lng'] ?? null, -180.0, 180.0);
        if ($lat === null || $lng === null) {
            $err[] = '地図の中心（緯度・経度）が正しくありません';
        }
        $zoomRaw = is_string($in['zoom'] ?? null) ? trim($in['zoom']) : '';
        if (!preg_match('/^\d{1,2}$/', $zoomRaw) || (int)$zoomRaw < 1 || (int)$zoomRaw > 20) {
            $err[] = 'ズームは 1〜20 の整数で入力してください';
        }
        $cats = $in['categories'] ?? [];
        $cats = is_array($cats) ? array_values(array_unique(array_filter($cats, 'is_string'))) : [];
        foreach ($cats as $c) {
            if (!isset(self::CATEGORIES[$c])) {
                $err[] = 'カテゴリが正しくありません';
                break;
            }
        }
        if (!$cats) {
            $err[] = '表示するカテゴリを1つ以上選んでください';
        }
        if ($err) {
            return [null, $err];
        }
        return [['name' => $name, 'center_lat' => $lat, 'center_lng' => $lng, 'zoom' => (int)$zoomRaw, 'categories' => $cats], []];
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? trim($v) : (is_int($v) || is_float($v) ? trim((string)$v) : '');
    }

    /** 数値（文字列または数）を範囲つきで検証し、小数6桁の文字列にする。 */
    private static function coord(mixed $v, float $min, float $max): ?string
    {
        if (is_int($v) || is_float($v)) {
            $s = rtrim(rtrim(number_format((float)$v, 10, '.', ''), '0'), '.');
        } elseif (is_string($v)) {
            $s = trim($v);
        } else {
            return null;
        }
        if (!preg_match('/^-?\d{1,3}(\.\d{1,10})?$/', $s)) {
            return null;
        }
        $f = (float)$s;
        if ($f < $min || $f > $max) {
            return null;
        }
        return number_format($f, 6, '.', '');
    }
}
