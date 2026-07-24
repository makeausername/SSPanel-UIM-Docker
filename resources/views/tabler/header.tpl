<!doctype html>
<html lang="{$config['locale']}" data-bs-theme="auto">

<head>
    <meta charset="utf-8"/>
    <meta name="robots" content="noindex">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>{$config['appName']}</title>
    <!-- Auto dark mode -->
    <script>
        ;(function () {
            const htmlElement = document.querySelector("html")
            const theme = htmlElement.getAttribute("data-bs-theme");

            if(theme === 'dark-auto' || theme === 'auto') {
                function updateTheme() {
                    htmlElement.setAttribute("data-bs-theme",
                        window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")
                }
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateTheme)
                updateTheme()
            }
        })()
    </script>
    <!-- CSS files -->
    <link href="https://{$config['jsdelivr_url']}/npm/@tabler/core@1.4.0/dist/css/tabler.min.css" rel="stylesheet"/>
    <link href="https://{$config['jsdelivr_url']}/npm/@tabler/icons-webfont@3.45.0/dist/tabler-icons.min.css" rel="stylesheet"/>
    <style>
        .frontend-locale-switcher {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1030;
        }

        .frontend-locale-switcher form {
            align-items: center;
            display: flex;
            gap: .25rem;
        }

        @media (min-width: 992px) {
            :root {
                margin-left: 0;
            }
        }
    </style>
    <!-- JS files -->
    <script src="/assets/js/fuck.min.js"></script>
    <script src="https://{$config['jsdelivr_url']}/npm/htmx.org@2.0.10/dist/htmx.min.js"
            integrity="sha384-H5SrcfygHmAuTDZphMHqBJLc3FhssKjG7w/CeCpFReSfwBWDTKpkzPP8c+cLsK+V"
            crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.frontend-locale-switcher')) {
                return;
            }

            const switcher = document.createElement('div');
            const redirectPath = window.location.pathname + window.location.search + window.location.hash;
            switcher.className = 'frontend-locale-switcher';
            switcher.innerHTML = `
                <form method="post" action="/locale" aria-label="{trans key='common.switch_language'}">
                    <input type="hidden" name="redirect">
                    <span class="text-secondary small">{trans key='common.language'}</span>
                    <button type="submit" name="locale" value="zh-CN"
                            class="btn btn-sm {if $current_locale === 'zh-CN'}btn-primary{else}btn-outline-secondary{/if}">
                        {trans key='locale.zh-CN'}
                    </button>
                    <button type="submit" name="locale" value="en-US"
                            class="btn btn-sm {if $current_locale === 'en-US'}btn-primary{else}btn-outline-secondary{/if}">
                        {trans key='locale.en-US'}
                    </button>
                </form>
            `;
            switcher.querySelector('input[name="redirect"]').value = redirectPath;
            document.body.appendChild(switcher);
        });
    </script>
</head>
