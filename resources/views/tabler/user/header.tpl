<!doctype html>
<html lang="{$config['locale']}">

<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="referrer" content="never">
    <title>{$config['appName']}</title>
    <!-- CSS files -->
    <link href="https://{$config['jsdelivr_url']}/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://{$config['jsdelivr_url']}/npm/@tabler/icons-webfont@3.45.0/dist/tabler-icons.min.css" rel="stylesheet"/>
    <!-- JS files -->
    <script src="/assets/js/fuck.min.js"></script>
    <script src="https://{$config['jsdelivr_url']}/npm/qrcode_js@1.0.0/qrcode.min.js"></script>
    <script src="https://{$config['jsdelivr_url']}/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>
    <script src="https://{$config['jsdelivr_url']}/npm/htmx.org@2.0.10/dist/htmx.min.js"
            integrity="sha384-H5SrcfygHmAuTDZphMHqBJLc3FhssKjG7w/CeCpFReSfwBWDTKpkzPP8c+cLsK+V"
            crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.frontend-locale-redirect').forEach(function (input) {
                input.value = window.location.pathname + window.location.search + window.location.hash;
            });
        });
    </script>
    <style>
        .home-subtitle {
            font-size: 14px;
        }

        .home-title {
            font-size: 36px;
        }
    </style>
</head>

{if $user->is_dark_mode}
<body data-bs-theme="dark">
{else}
<body>
{/if}
<div class="page">
    <header class="navbar navbar-expand-md navbar-overlap d-print-none" data-bs-theme="dark">
        <div class="container-xl" style="background-image: none;">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-nav flex-row order-md-last">
                <div class="nav-item dropdown me-2">
                    <a href="#" class="nav-link px-0" data-bs-toggle="dropdown"
                       aria-label="{trans key='common.switch_language'}">
                        <i class="ti ti-language icon"></i>
                        <span class="d-none d-md-inline ms-1">{trans key='common.language'}</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <form method="post" action="/locale" class="m-0">
                            <input type="hidden" name="redirect" class="frontend-locale-redirect">
                            <button type="submit" name="locale" value="zh-CN"
                                    class="dropdown-item {if $current_locale === 'zh-CN'}active{/if}">
                                {trans key='locale.zh-CN'}
                            </button>
                            <button type="submit" name="locale" value="en-US"
                                    class="dropdown-item {if $current_locale === 'en-US'}active{/if}">
                                {trans key='locale.en-US'}
                            </button>
                        </form>
                    </div>
                </div>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown"
                       aria-label="Open user menu">
                            <span class="avatar avatar-sm"
                                  style="background-image: url({$user->dice_bear})"></span>
                        <div class="d-none d-xl-block ps-2">
                            <div>{$user->email|escape:'html'}</div>
                            <div class="mt-1 small text-secondary">{$user->user_name|escape:'html'}</div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <div class="dropdown-divider"></div>
                        <a href="/user/logout" class="dropdown-item">{trans key='user.nav.logout'}</a>
                    </div>
                </div>
            </div>
            <div class="collapse navbar-collapse" id="navbar-menu">
                <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/user">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-home icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.dashboard'}
                                    </span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-base" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-user icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.my'}
                                    </span>
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-menu-columns">
                                    <div class="dropdown-menu-column">
                                        <a class="dropdown-item" href="/user/profile">
                                            <i class="ti ti-info-square"></i>&nbsp;
                                            {trans key='user.nav.account'}
                                        </a>
                                        <a class="dropdown-item" href="/user/edit">
                                            <i class="ti ti-edit"></i>&nbsp;
                                            {trans key='user.nav.profile'}
                                        </a>
                                        <a class="dropdown-item" href="/user/invite">
                                            <i class="ti ti-friends"></i>&nbsp;
                                            {trans key='user.nav.invite'}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-extra" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-brand-telegram icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.usage'}
                                    </span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="/user/server">
                                    <i class="ti ti-server"></i>&nbsp;
                                    {trans key='user.nav.nodes'}
                                </a>
                                <a class="dropdown-item" href="/user/rate">
                                    <i class="ti ti-chart-bar"></i>&nbsp;
                                    {trans key='user.nav.traffic_rate'}
                                </a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-extra" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-dots-circle-horizontal icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.support'}
                                    </span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="/user/announcement">
                                    <i class="ti ti-speakerphone"></i>&nbsp;
                                    {trans key='user.nav.announcements'}
                                </a>
                                {if $public_setting['enable_ticket']}
                                    <a class="dropdown-item" href="/user/ticket">
                                        <i class="ti ti-ticket"></i>&nbsp;
                                        {trans key='user.nav.tickets'}
                                    </a>
                                {/if}
                                {if $public_setting['display_docs'] &&
                                (! $public_setting['display_docs_only_for_paid_user'] || $user->class !== 0)}
                                    <a class="dropdown-item" href="/user/docs">
                                        <i class="ti ti-notes"></i>&nbsp;
                                        {trans key='user.nav.docs'}
                                    </a>
                                {/if}
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-extra" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-shield-check icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.audit'}
                                    </span>
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="/user/detect">
                                    <i class="ti ti-barrier-block"></i>&nbsp;
                                    {trans key='user.nav.rules'}
                                </a>
                                {if $public_setting['display_detect_log']}
                                    <a class="dropdown-item" href="/user/detect/log">
                                        <i class="ti ti-notes"></i>&nbsp;
                                        {trans key='user.nav.logs'}
                                    </a>
                                {/if}
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#navbar-layout" data-bs-toggle="dropdown"
                               data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-building-store icon"></i>
                                    </span>
                                <span class="nav-link-title">
                                        {trans key='user.nav.shop'}
                                    </span>
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-menu-columns">
                                    <div class="dropdown-menu-column">
                                        <a class="dropdown-item" href="/user/product">
                                            <i class="ti ti-list"></i>&nbsp;
                                            {trans key='user.nav.products'}
                                        </a>
                                        <a class="dropdown-item" href="/user/order">
                                            <i class="ti ti-file-invoice"></i>&nbsp;
                                            {trans key='user.nav.orders'}
                                        </a>
                                        <a class="dropdown-item" href="/user/invoice">
                                            <i class="ti ti-file-dollar"></i>&nbsp;
                                            {trans key='user.nav.invoices'}
                                        </a>
                                        <a class="dropdown-item" href="/user/money">
                                            <i class="ti ti-home-dollar"></i>&nbsp;
                                            {trans key='user.nav.balance'}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </li>
                        {if $user->is_admin}
                            <li class="nav-item">
                                <a class="nav-link" href="/admin">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="ti ti-settings icon"></i>
                                    </span>
                                    <span class="nav-link-title">
                                        {trans key='user.nav.admin'}
                                    </span>
                                </a>
                            </li>
                        {/if}
                    </ul>
                </div>
            </div>
        </div>
    </header>
