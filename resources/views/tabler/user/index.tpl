{include file='user/header.tpl'}
{assign var=eziplcWindowsDownloadUrl value='/downloads/eziplc-windows.exe'}
{assign var=eziplcAndroidDownloadUrl value='/downloads/eziplc-android.apk'}
{assign var=eziplcWindowsGuideUrl value='/user/docs/windows'}
{assign var=eziplcAndroidGuideUrl value='/docs/android'}
{assign var=eziplcAppleGuideUrl value='/user/docs/shadowrocket'}

<style>
/* Animation classes for collapsible sections */
.collapsible-section {
    transition: all 0.35s ease;
    overflow: hidden;
}

.collapsible-section.collapsing {
    opacity: 0.3;
    transform: scale(0.98);
}

.collapsible-section.expanded {
    opacity: 1;
    transform: scale(1);
}

/* Client item hover effects */
.client-item:hover {
    border-color: var(--tblr-primary) !important;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

/* Copy button feedback */
.copy.copied {
    background-color: var(--tblr-success) !important;
    border-color: var(--tblr-success) !important;
}

.client-item {
    transition: all 0.3s;
}

.client-item:hover {
    background: var(--tblr-bg-surface-secondary);
    transform: translateX(5px);
}

@media (max-width: 576px) {
    .client-item:hover {
        transform: none;
    }
    
    .client-item .btn-group-vertical {
        margin-top: 0.5rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .copy button {
        word-break: keep-all;
        white-space: nowrap;
    }
    
    /* Enhanced mobile button styles */
    .btn-group-vertical .btn {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        min-height: 44px; /* iOS recommended touch target */
    }
    
    .btn-group-vertical {
        gap: 0.5rem;
    }
    
    .client-item {
        padding: 1rem !important;
    }
    
}

/* 手风琴样式 */
.accordion-button:not(.collapsed) {
    background: var(--tblr-primary-lt);
    color: var(--tblr-primary);
}

/* 敏感信息模糊效果 */
.spoiler {
    filter: blur(5px);
    transition: filter 0.3s;
}

.spoiler:hover {
    filter: none;
}
</style>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='user.dashboard.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='user.dashboard.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="row row-cards">
                        {foreach $info_cards as $card}
                        <div class="col-sm-6 col-lg-3">
                            <div class="card card-sm">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <span class="bg-{$card.color} text-white avatar">
                                                <i class="ti {$card.icon} icon"></i>
                                            </span>
                                        </div>
                                        <div class="col">
                                            <div class="font-weight-medium">
                                                {$card.title}
                                            </div>
                                            <div class="text-secondary">
                                                {$card.value}
                                            </div>
                                        </div>
                                        {if isset($card.action_url)}
                                        <div class="col-auto">
                                            <a href="{$card.action_url}" class="btn btn-primary btn-icon">
                                                <i class="ti ti-plus icon"></i>
                                            </a>
                                        </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                        {/foreach}
                    </div>
                </div>
            </div>

            <div class="row row-cards align-items-start">
                <div class="col-12 col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='user.dashboard.quick_config'}</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h4 class="mb-3">
                                    <i class="ti ti-link"></i> {trans key='user.dashboard.subscription_address'}
                                </h4>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" value="{$UniversalSub}/v2ray" readonly id="universal-sub-link">
                                    <button class="btn btn-primary copy" data-clipboard-text="{$UniversalSub}/v2ray">
                                        <i class="ti ti-copy"></i> {trans key='user.dashboard.copy_subscription'}
                                    </button>
                                </div>
                                <p class="text-muted mb-0">
                                    <small>{trans key='user.dashboard.subscription_hint'}</small>
                                </p>
                            </div>

                            <div class="accordion" id="eziplc-platform-accordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#eziplc-windows"
                                                aria-expanded="false" aria-controls="eziplc-windows">
                                            <i class="ti ti-brand-windows me-2"></i> Windows
                                        </button>
                                    </h2>
                                    <div id="eziplc-windows" class="accordion-collapse collapse"
                                         data-bs-parent="#eziplc-platform-accordion">
                                        <div class="accordion-body py-4">
                                            <div class="client-item d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between p-3 border rounded gap-3">
                                                <div class="flex-fill">
                                                    <h5 class="mb-1">EzIPLC</h5>
                                                    <small class="text-muted">{trans key='user.dashboard.eziplc_windows_description'}</small>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <a class="btn btn-primary" href="{$eziplcWindowsDownloadUrl}">
                                                        <i class="ti ti-download"></i> {trans key='common.download'}
                                                    </a>
                                                    <a class="btn btn-outline-primary" href="{$eziplcWindowsGuideUrl}">
                                                        <i class="ti ti-book"></i> {trans key='user.dashboard.setup_guide'}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#eziplc-android"
                                                aria-expanded="false" aria-controls="eziplc-android">
                                            <i class="ti ti-brand-android me-2"></i> Android
                                        </button>
                                    </h2>
                                    <div id="eziplc-android" class="accordion-collapse collapse"
                                         data-bs-parent="#eziplc-platform-accordion">
                                        <div class="accordion-body py-4">
                                            <div class="client-item d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between p-3 border rounded gap-3">
                                                <div class="flex-fill">
                                                    <h5 class="mb-1">EzIPLC</h5>
                                                    <small class="text-muted">{trans key='user.dashboard.eziplc_android_description'}</small>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <a class="btn btn-primary" href="{$eziplcAndroidDownloadUrl}">
                                                        <i class="ti ti-download"></i> {trans key='common.download'}
                                                    </a>
                                                    <a class="btn btn-outline-primary" href="{$eziplcAndroidGuideUrl}">
                                                        <i class="ti ti-book"></i> {trans key='user.dashboard.setup_guide'}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#eziplc-apple"
                                                aria-expanded="false" aria-controls="eziplc-apple">
                                            <i class="ti ti-brand-apple me-2"></i> {trans key='user.dashboard.apple_platform'}
                                        </button>
                                    </h2>
                                    <div id="eziplc-apple" class="accordion-collapse collapse"
                                         data-bs-parent="#eziplc-platform-accordion">
                                        <div class="accordion-body py-4">
                                            <div class="client-item d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between p-3 border rounded gap-3">
                                                <div class="flex-fill">
                                                    <h5 class="mb-1">Shadowrocket</h5>
                                                    <small class="text-muted d-block">{trans key='user.dashboard.shadowrocket_description_1'}</small>
                                                    <small class="text-muted d-block">{trans key='user.dashboard.shadowrocket_description_2'}</small>
                                                    <small class="text-muted d-block">{trans key='user.dashboard.shadowrocket_description_3'}</small>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <a class="btn btn-outline-primary" href="{$eziplcAppleGuideUrl}">
                                                        <i class="ti ti-book"></i> {trans key='user.dashboard.setup_guide'}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-ghost-secondary w-100" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#connection-info" aria-expanded="false">
                                    <i class="ti ti-info-circle"></i>
                                    {trans key='user.dashboard.connection_info'}
                                    <i class="ti ti-chevron-down ms-1"></i>
                                </button>
                                <div class="collapse mt-2" id="connection-info">
                                    <div class="p-3 bg-light rounded">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                    <tbody>
                                                    <tr>
                                                        <td class="text-muted" style="width: 100px;">{trans key='user.dashboard.port'}</td>
                                                        <td><code>{$user->port}</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted">{trans key='user.dashboard.connection_password'}</td>
                                                        <td><code class="spoiler">{$user->passwd}</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted">UUID</td>
                                                        <td><code class="spoiler" style="font-size: 0.8em;">{$user->uuid}</code></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted">{trans key='user.dashboard.encryption_method'}</td>
                                                        <td><code>{$user->method}</code></td>
                                                    </tr>
                                                    </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                
                            </div>
                        </div>
                    </div>

                <div class="col-12 col-lg-5">
                    <div class="vstack gap-3">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title">{trans key='user.dashboard.traffic_usage'}</h3>
                                <div class="progress progress-separated mb-3">
                                    {if $user->LastusedTrafficPercent() < '1'}
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: 1%"></div>
                                    {else}
                                    <div class="progress-bar bg-primary" role="progressbar"
                                         style="width: {$user->LastusedTrafficPercent()}%">
                                    </div>
                                    {/if}
                                    {if $user->TodayusedTrafficPercent() < '1'}
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 1%"></div>
                                    {else}
                                    <div class="progress-bar bg-success" role="progressbar"
                                         style="width: {$user->TodayusedTrafficPercent()}%"></div>
                                    {/if}
                                </div>
                                <div class="row">
                                    <div class="col-auto d-flex align-items-center pe-2">
                                        <span class="legend me-2 bg-primary"></span>
                                        <span>{trans key='user.dashboard.past_usage'} {$user->LastusedTraffic()}</span>
                                    </div>
                                    <div class="col-auto d-flex align-items-center px-2">
                                        <span class="legend me-2 bg-success"></span>
                                        <span>{trans key='user.dashboard.today_usage'} {$user->TodayusedTraffic()}</span>
                                    </div>
                                    <div class="col-auto d-flex align-items-center ps-2">
                                        <span class="legend me-2"></span>
                                        <span>{trans key='user.dashboard.remaining_traffic'} {$user->unusedTraffic()}</span>
                                    </div>
                                </div>
                                <p class="my-3">
                                    {if $user->class === 0}
                                    {trans key='user.dashboard.go_to'}
                                    <a href="/user/product">{trans key='user.nav.shop'}</a>
                                    {trans key='user.dashboard.buy_plan'}
                                    {else}
                                    {trans key='user.dashboard.account_level_prefix'} {$user->class} {trans key='user.dashboard.account_expires_prefix'} {$class_expire_days} {trans key='user.dashboard.account_expires_suffix'}{trans key='user.dashboard.account_expires_date_prefix'}{$user->class_expire}{trans key='user.dashboard.account_expires_date_suffix'}
                                    {/if}
                                </p>
                            </div>
                        </div>
                        {if $public_setting['traffic_log']}
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title">{trans key='user.dashboard.hourly_usage'}</h3>
                                <div id="traffic-log"></div>
                            </div>
                        </div>
                        {/if}
                        <div class="card">
                            <div class="ribbon ribbon-top bg-yellow">
                                <i class="ti ti-bell-ringing icon"></i>
                            </div>
                            <div class="card-body">
                                <h3 class="card-title">
                                    {trans key='user.dashboard.pinned_announcement'}
                                    {if $ann !== null}
                                    <span class="card-subtitle">{$ann->date}</span>
                                    {/if}
                                </h3>
                                <p class="text-secondary">
                                    {if $ann !== null}
                                    {$ann->content}
                                    {else}
                                    {trans key='user.dashboard.no_announcement'}
                                    {/if}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                {if $public_setting['enable_checkin']}
            <div class="row row-cards">
                <div class="col-lg-8 col-sm-12">
                    <div class="card">
                        <div class="card-stamp">
                            <div class="card-stamp-icon bg-green">
                                <i class="ti ti-check"></i>
                            </div>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title">{trans key='user.dashboard.daily_checkin'}</h3>
                            <p>
                                {trans key='user.dashboard.checkin_reward_prefix'}
                                {if $public_setting['checkin_min'] !== $public_setting['checkin_max']}
                                &nbsp;
                                <code>{$public_setting['checkin_min']} MB</code>
                                {trans key='user.dashboard.to'}
                                <code>{$public_setting['checkin_max']} MB</code>
                                {trans key='user.dashboard.checkin_reward_suffix'}
                                {else}
                                <code>{$public_setting['checkin_min']} MB</code>
                                {/if}
                            </p>
                            <p>
                                {trans key='user.dashboard.last_checkin_time'}<code id="last-checkin-time">{$user->lastCheckInTime()}</code>
                            </p>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex">
                                {if !$user->isAbleToCheckin()}
                                <button id="check-in" class="btn btn-primary ms-auto" disabled>{trans key='user.dashboard.checked_in'}</button>
                                {else}
                                {if $public_setting['enable_checkin_captcha']}
                                {include file='captcha/div.tpl'}
                                {/if}
                                <button id="check-in" class="btn btn-primary ms-auto"
                                    hx-post="/user/checkin" hx-swap="none" hx-vals='js:{
                                    {if $public_setting['enable_checkin_captcha']}
                                    {include file='captcha/ajax.tpl'}
                                    {/if}
                                    }'>
                                    {trans key='user.dashboard.checkin'}
                                </button>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {/if}
        </div>
    </div>

    {if $public_setting['enable_checkin_captcha'] && $user->isAbleToCheckin()}
        {include file='captcha/js.tpl'}
    {/if}

    {if $public_setting['traffic_log']}
    <script src="https://{$config['jsdelivr_url']}/npm/@tabler/core@1.4.0/dist/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script>
        function getTrafficChartConfig(trafficData) {
            return {
                chart: {
                    type: "line",
                    fontFamily: "inherit",
                    height: '100%',
                    parentHeightOffset: 0,
                    toolbar: {
                        show: false
                    },
                    animations: {
                        enabled: false
                    }
                },
                stroke: {
                    curve: "smooth"
                },
                fill: {
                    opacity: 1
                },
                series: [
                    {
                        name: "{trans key='user.dashboard.traffic_chart_name'}",
                        data: trafficData
                    }
                ],
                tooltip: {
                    theme: "dark"
                },
                grid: {
                    padding: {
                        top: -20,
                        right: 0,
                        left: 0,
                        bottom: 0
                    },
                    strokeDashArray: 4
                },
                xaxis: {
                    title: {
                        text: "{trans key='user.dashboard.hour'}"
                    },
                    labels: {
                        padding: 0
                    },
                    tooltip: {
                        enabled: false
                    },
                    axisBorder: {
                        show: false
                    },
                    categories: [
                        "00", "01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11",
                        "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23"
                    ]
                },
                yaxis: {
                    title: {
                        text: "{trans key='user.dashboard.traffic_chart_name'}",
                        rotate: -90
                    },
                    labels: {
                        padding: 14
                    }
                },
                colors: ["#FF4500"],
                legend: {
                    show: false
                }
            };
        }
        
        function initTrafficChart() {
            const chartElement = document.getElementById('traffic-log');
            if (!chartElement || !window.ApexCharts) return;
            
            try {
                const chart = new ApexCharts(chartElement, getTrafficChartConfig({$traffic_logs}));
                chart.render();
            } catch (error) {
                console.error('流量图表初始化失败:', error);
            }
        }
        
        document.addEventListener("DOMContentLoaded", function () {
            initTrafficChart();
        });
    </script>
    {/if}

    <script>
    window.USER_DASHBOARD_I18N = {
        clipboardSuccess: "{trans key='common.copied'}",
        clipboardError: "{trans key='common.copy_failed_select'}"
    };
    
    {literal}
    const CONFIG = {
        FEEDBACK_TIMEOUT: 2000,         // 反馈提示持续时间（毫秒）
        CLIPBOARD_SUCCESS_TEXT: window.USER_DASHBOARD_I18N.clipboardSuccess,
        CLIPBOARD_ERROR_TEXT: window.USER_DASHBOARD_I18N.clipboardError
    };
    
    function safeInit(fn, name) {
        try {
            fn();
        } catch (error) {
            console.error(`${name} 初始化失败:`, error);
        }
    }
    
    function createElement(tag, className, content) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (content) element.textContent = content;
        return element;
    }
    
    function createIcon(iconClass) {
        const icon = createElement('i', 'ti ' + iconClass);
        return icon;
    }
    
    function initClipboard() {
        if (typeof ClipboardJS === 'undefined') {
            console.warn('ClipboardJS 未加载');
            return;
        }
        
        const clipboard = new ClipboardJS('.copy');
        
        clipboard.on('success', function(e) {
            e.clearSelection();
            const originalText = e.trigger.innerHTML;
            const checkIcon = createIcon('ti-check');
            e.trigger.innerHTML = '';
            e.trigger.appendChild(checkIcon);
            e.trigger.appendChild(document.createTextNode(' ' + CONFIG.CLIPBOARD_SUCCESS_TEXT));
            setTimeout(function() {
                e.trigger.innerHTML = originalText;
            }, CONFIG.FEEDBACK_TIMEOUT);
        });
        
        clipboard.on('error', function(e) {
            console.error('复制失败:', e.action);
            alert(CONFIG.CLIPBOARD_ERROR_TEXT);
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        safeInit(initClipboard, '剪贴板功能');
    });
    {/literal}
    </script>

    {include file='user/footer.tpl'}
