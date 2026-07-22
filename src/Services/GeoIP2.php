<?php

declare(strict_types=1);

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use function is_file;
use const BASE_PATH;

final class GeoIP2
{
    private ?Reader $city_reader = null;
    private ?Reader $country_reader = null;

    /**
     * @throws InvalidDatabaseException
     */
    public function __construct(?string $city_database = null, ?string $country_database = null)
    {
        $city_database ??= BASE_PATH . '/storage/GeoLite2-City/GeoLite2-City.mmdb';
        $country_database ??= BASE_PATH . '/storage/GeoLite2-Country/GeoLite2-Country.mmdb';

        if (is_file($city_database)) {
            $this->city_reader = new Reader($city_database);
        }

        if (is_file($country_database)) {
            $this->country_reader = new Reader($country_database);
        }
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidDatabaseException
     */
    public function getCity(string $ip): ?string
    {
        $record = $this->city_reader?->city($ip);
        return $record?->city?->names[$_ENV['geoip_locale']] ?? $record?->city?->name;
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidDatabaseException
     */
    public function getCountry(string $ip): ?string
    {
        if ($this->country_reader !== null) {
            $record = $this->country_reader->country($ip);
        } else {
            $record = $this->city_reader?->city($ip);
        }

        return $record?->country?->names[$_ENV['geoip_locale']] ?? $record?->country?->name;
    }
}
