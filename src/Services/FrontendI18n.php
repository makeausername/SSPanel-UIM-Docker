<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use const BASE_PATH;

final class FrontendI18n
{
    /**
     * @var array<string, Translator>
     */
    private static array $translators = [];

    public static function trans(string $key, array $parameters = [], ?string $locale = null): string
    {
        $locale = Locale::normalize($locale ?? Locale::current()) ?? Locale::DEFAULT_LOCALE;
        $resourceLocale = Locale::resourceName($locale);

        return self::getTranslator($resourceLocale)->trans($key, $parameters);
    }

    public static function getTranslator(string $resourceLocale): Translator
    {
        if (isset(self::$translators[$resourceLocale])) {
            return self::$translators[$resourceLocale];
        }

        $translator = new Translator($resourceLocale);
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            BASE_PATH . '/resources/locale/frontend/' . $resourceLocale . '.php',
            $resourceLocale
        );

        self::$translators[$resourceLocale] = $translator;

        return $translator;
    }
}
