<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use function hash_equals;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;

/**
 * @property int    $id
 * @property string $item
 * @property string $value
 * @property string $class
 * @property string $is_public
 * @property string $type
 * @property string $default
 * @property string $mark
 *
 * @mixin Builder
 */
final class Config extends Model
{
    public const SECRET_MASK = '********';

    protected $connection = 'default';
    protected $table = 'config';

    public static function obtain($item): bool|int|array|string
    {
        $config = (new Config())->where('item', $item)->first();

        if ($config === null) {
            return '';
        }

        return match ($config->type) {
            'bool' => (bool) $config->value,
            'int' => (int) $config->value,
            'array' => json_decode($config->value),
            default => (string) $config->value,
        };
    }

    public static function getClass($class): array
    {
        $configs = [];
        $all_configs = (new Config())->where('class', $class)->get();

        foreach ($all_configs as $config) {
            $configs[$config->item] = match ($config->type) {
                'bool' => (bool) $config->value,
                'int' => (int) $config->value,
                'array' => json_decode($config->value),
                default => (string) $config->value,
            };
        }

        return $configs;
    }

    public static function getItemListByClass($class): array
    {
        $items = [];
        $all_configs = (new Config())->where('class', $class)->get();

        foreach ($all_configs as $config) {
            $items[] = $config->item;
        }

        return $items;
    }

    public static function getPublicConfig(): array
    {
        $configs = [];
        $all_configs = (new Config())->where('is_public', '1')->get();

        foreach ($all_configs as $config) {
            $configs[$config->item] = match ($config->type) {
                'bool' => (bool) $config->value,
                'int' => (int) $config->value,
                'array' => json_decode($config->value),
                default => (string) $config->value,
            };
        }

        return $configs;
    }

    public static function set(string $item, mixed $value): bool
    {
        $value = is_array($value) ? json_encode($value) : $value;

        try {
            $config = (new Config())->where('item', $item)->first();

            if ($config === null) {
                return false;
            }

            $config->value = $value;

            return $config->save();
        } catch (QueryException $e) {
            return false;
        }
    }

    public static function getAdminClass(string $class): array
    {
        $configs = self::getClass($class);

        foreach ($configs as $item => $value) {
            if (self::isSecretItem((string) $item) && $value !== '' && $value !== null) {
                $configs[$item] = self::SECRET_MASK;
            }
        }

        return $configs;
    }

    public static function setFromAdmin(string $item, mixed $value): bool
    {
        if (self::isSecretItem($item)) {
            $stringValue = is_string($value) ? trim($value) : '';
            if ($stringValue === '' || hash_equals(self::SECRET_MASK, $stringValue)) {
                return true;
            }
        }

        return self::set($item, $value);
    }

    public static function isSecretItem(string $item): bool
    {
        $item = strtolower(trim($item));

        if (preg_match('/(?:^|_)(?:secret|token|password|passwd)$/', $item) === 1) {
            return true;
        }

        if (str_contains($item, 'private_key') || str_contains($item, 'access_key')) {
            return true;
        }

        return str_ends_with($item, '_api_key')
            || (str_ends_with($item, '_key')
                && ! str_ends_with($item, '_public_key')
                && ! str_ends_with($item, '_sitekey'));
    }

}
