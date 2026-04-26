{include file="user/header.tpl"}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='node.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='node.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="row row-deck row-cards">
                                    {foreach $servers as $server}
                                        <div class="col-lg-4 col-md-6 col-sm-12">
                                            <div class="card">
                                                {if $server['class'] === 0}
                                                    <div class="ribbon bg-blue">{trans key='node.free'}</div>
                                                {else}
                                                    <div class="ribbon bg-blue">LV. {$server['class']}</div>
                                                {/if}
                                                <div class="card-body">
                                                    <div class="row g-3 align-items-center">
                                                        <div class="col-auto">
                                                            <span class="status-indicator status-{$server['color']}
                                                                 status-indicator-animated">
                                                                <span class="status-indicator-circle"></span>
                                                                <span class="status-indicator-circle"></span>
                                                                <span class="status-indicator-circle"></span>
                                                            </span>
                                                        </div>
                                                        <div class="col">
                                                            <h2 class="page-title" style="font-size: 16px;">
                                                                {$server['name']}&nbsp;
                                                                <span class="card-subtitle my-2"
                                                                      style="font-size: 10px;">  {$server['node_bandwidth']} /
                                                                    {$server['node_bandwidth_limit']}
                                                                </span>
                                                            </h2>
                                                            <div class="text-secondary badges-list">
                                                                <span class="badge bg-blue-lt">
                                                                    <i class="ti ti-users"></i>
                                                                    {$server['online_user']}</span>
                                                                <span class="badge bg-blue-lt">
                                                                    {if $server['is_dynamic_rate']}
                                                                        {trans key='node.dynamic_rate'}
                                                                    {else}
                                                                        {$server['traffic_rate']} {trans key='node.rate_suffix'}
                                                                    {/if}
                                                                </span>
                                                                <span class="badge bg-blue-lt">{$server['sort']}</span>
                                                                {if $server['connection_type'] !== 0}
                                                                <span class="badge bg-blue-lt">IPv6</span>
                                                                {/if}
                                                                {if $user->class < $server['class']}
                                                                <span class="badge bg-red-lt">{trans key='node.no_permission'}</span>
                                                                <span class="badge bg-pink-lt">{trans key='node.class_too_low'}</span>
                                                                <span class="badge bg-green-lt">{trans key='node.upgrade_prefix'} <a href="/user/product">{trans key='node.product_page'}</a> {trans key='node.upgrade_suffix'}</span>
                                                                {/if}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    {/foreach}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {include file="user/footer.tpl"}
