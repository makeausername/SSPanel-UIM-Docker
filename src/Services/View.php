<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use Illuminate\Database\DatabaseManager;
use Smarty\Smarty;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use function is_dir;
use function is_string;
use function preg_match;
use function trim;
use const BASE_PATH;

final class View
{
    public static DatabaseManager $connection;
    public static float $beginTime;

    public static function getSmarty(): Smarty
    {
        $smarty = new Smarty(); //实例化smarty
        $user = Auth::getUser();

        $smarty->setTemplateDir(BASE_PATH . '/resources/views/' . self::getTheme($user) . '/'); //设置模板文件存放目录
        $smarty->setCompileDir(BASE_PATH . '/storage/framework/smarty/compile/'); //设置生成文件存放目录
        $smarty->setCacheDir(BASE_PATH . '/storage/framework/smarty/cache/'); //设置缓存文件存放目录
        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'trans',
            static function (array $params): string {
                $key = (string) ($params['key'] ?? '');

                if ($key === '') {
                    return '';
                }

                unset($params['key']);
                $locale = isset($params['locale']) ? (string) $params['locale'] : null;
                unset($params['locale']);

                return FrontendI18n::trans($key, $params, $locale);
            }
        );
        // add config
        $smarty->assign('config', self::getConfig());
        $smarty->assign('public_setting', Config::getPublicConfig());
        $smarty->assign('current_locale', Locale::current());
        $smarty->assign('frontend_locales', Locale::supportedLocales());
        $smarty->assign('user', $user);

        return $smarty;
    }

    public static function getTwig(): Environment
    {
        $user = Auth::getUser();
        $loader = new FilesystemLoader(BASE_PATH . '/resources/views/' . self::getTheme($user) . '/');

        $twig = new Environment($loader, [
            'cache' => BASE_PATH . '/storage/framework/twig/cache/',
        ]);

        $twig->addGlobal('config', self::getConfig());
        $twig->addGlobal('public_setting', Config::getPublicConfig());
        $twig->addGlobal('current_locale', Locale::current());
        $twig->addGlobal('frontend_locales', Locale::supportedLocales());
        $twig->addGlobal('user', $user);

        return $twig;
    }

    public static function getTheme($user): string
    {
        $configuredTheme = is_string($_ENV['theme'] ?? null) ? trim($_ENV['theme']) : '';
        $preferredTheme = $user->isLogin ? (string) $user->theme : $configuredTheme;

        if (self::isValidTheme($preferredTheme)) {
            return $preferredTheme;
        }

        return self::isValidTheme($configuredTheme) ? $configuredTheme : 'tabler';
    }

    public static function isValidTheme(string $theme): bool
    {
        $theme = trim($theme);

        return $theme !== ''
            && preg_match('/^[A-Za-z0-9_-]+$/', $theme) === 1
            && is_dir(BASE_PATH . '/resources/views/' . $theme);
    }

    public static function getConfig(): array
    {
        return [
            'appName' => $_ENV['appName'],
            'baseUrl' => $_ENV['baseUrl'],
            'jump_delay' => $_ENV['jump_delay'],
            'enable_kill' => $_ENV['enable_kill'],
            'enable_change_email' => $_ENV['enable_change_email'],
            'enable_r2_client_download' => $_ENV['enable_r2_client_download'],
            'jsdelivr_url' => $_ENV['jsdelivr_url'],
            'locale' => Locale::current(),
            'site_locale' => $_ENV['locale'],
        ];
    }
}
