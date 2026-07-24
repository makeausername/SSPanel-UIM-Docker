<?php

declare(strict_types=1);

namespace App\Services;

use function in_array;
use function is_string;
use function strtolower;
use function strtoupper;
use function trim;

final class NodeCountryService
{
    /**
     * ISO 3166-1 alpha-2 countries and territories supported by Tabler 1.4 flags.
     */
    private const SUPPORTED_CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ',
        'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW',
        'CX', 'CY', 'CZ',
        'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ',
        'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'EU',
        'FI', 'FJ', 'FK', 'FM', 'FO', 'FR',
        'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT',
        'GU', 'GW', 'GY',
        'HK', 'HM', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT',
        'JE', 'JM', 'JO', 'JP',
        'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY',
        'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS',
        'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
        'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ',
        'OM',
        'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY',
        'QA',
        'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
        'ST', 'SV', 'SX', 'SY', 'SZ',
        'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ',
        'UA', 'UG', 'UM', 'US', 'UY', 'UZ',
        'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU',
        'WF', 'WS',
        'YE', 'YT',
        'ZA', 'ZM', 'ZW',
    ];

    /**
     * Common node locations shown as suggestions. Any supported code remains valid.
     *
     * @var array<string, string>
     */
    private const COMMON_OPTIONS = [
        'CN' => '中国大陆',
        'HK' => '中国香港',
        'MO' => '中国澳门',
        'TW' => '中国台湾',
        'SG' => '新加坡',
        'JP' => '日本',
        'KR' => '韩国',
        'US' => '美国',
        'CA' => '加拿大',
        'GB' => '英国',
        'DE' => '德国',
        'FR' => '法国',
        'NL' => '荷兰',
        'FI' => '芬兰',
        'AU' => '澳大利亚',
        'RU' => '俄罗斯',
        'IN' => '印度',
        'TH' => '泰国',
        'MY' => '马来西亚',
        'PH' => '菲律宾',
        'VN' => '越南',
        'ID' => '印度尼西亚',
        'AE' => '阿联酋',
        'TR' => '土耳其',
        'BR' => '巴西',
        'ZA' => '南非',
    ];

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $code = strtoupper(trim($value));
        if ($code === '') {
            return '';
        }

        return in_array($code, self::SUPPORTED_CODES, true) ? $code : null;
    }

    public static function flagCode(mixed $value): string
    {
        $code = self::normalize($value);

        return $code === null || $code === '' ? '' : strtolower($code);
    }

    /**
     * @return array<string, string>
     */
    public static function commonOptions(): array
    {
        return self::COMMON_OPTIONS;
    }
}
