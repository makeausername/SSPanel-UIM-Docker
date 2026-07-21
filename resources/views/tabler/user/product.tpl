{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='shop.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='shop.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <ul class="nav nav-tabs nav-fill" data-bs-toggle="tabs">
                            <li class="nav-item">
                                <a href="#tabp" class="nav-link active" data-bs-toggle="tab">
                                    <i class="ti ti-rotate-360 icon"></i>
                                    &nbsp;{trans key='shop.product.time_traffic_package'}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#bandwidth" class="nav-link" data-bs-toggle="tab">
                                    <i class="ti ti-arrows-down-up icon"></i>
                                    &nbsp;{trans key='shop.product.traffic_package'}
                                </a>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="tab-pane active show" id="tabp">
                                    <div class="row">
                                        {foreach $tabps as $tabp}
                                            <div class="col-md-3 col-sm-12 my-3">
                                                <div class="card card-md">
                                                    <div class="card-body text-center">
                                                        <div id="product-{$tabp->id}-name"
                                                             class="text-uppercase text-secondary font-weight-medium">
                                                            {$tabp->name}</div>
                                                        <div id="product-{$tabp->id}-price"
                                                             class="display-6 my-3">
                                                            <p class="fw-bold">{$tabp->price}</p>
                                                            <i class="ti ti-currency-yuan"></i>
                                                            {if $tabp->content->monthly_plan}
                                                                <small class="fs-5">/ {trans key='shop.product.year'}</small>
                                                            {/if}
                                                        </div>
                                                        <div class="list-group list-group-flush">
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        {if $tabp->content->monthly_plan}
                                                                            <div class="text-reset d-block">
                                                                                {trans key='shop.product.all_nodes'}
                                                                            </div>
                                                                        {else}
                                                                            <div class="text-reset d-block">
                                                                                Lv. {$tabp->content->class}</div>
                                                                        {/if}
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {if $tabp->content->monthly_plan}
                                                                                {trans key='shop.product.node_access'}
                                                                            {else}
                                                                                {trans key='shop.product.level'}
                                                                            {/if}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        {if $tabp->content->monthly_plan}
                                                                            <div class="text-reset d-block">
                                                                                {trans key='shop.product.one_year'}
                                                                            </div>
                                                                        {else}
                                                                            <div class="text-reset d-block">{$tabp->content->class_time}
                                                                                {trans key='common.days'}
                                                                            </div>
                                                                        {/if}
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {if $tabp->content->monthly_plan}
                                                                                {trans key='shop.product.subscription_period'}
                                                                            {else}
                                                                                {trans key='shop.product.level_duration'}
                                                                            {/if}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        {if $tabp->content->unlimited_bandwidth}
                                                                            <div class="text-reset d-block">{trans key='common.unlimited'}</div>
                                                                        {else}
                                                                            <div class="text-reset d-block">{$tabp->content->bandwidth}
                                                                                GB
                                                                            </div>
                                                                        {/if}
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {if $tabp->content->monthly_plan}
                                                                                {trans key='shop.product.monthly_traffic'}
                                                                            {else}
                                                                                {trans key='shop.product.available_traffic'}
                                                                            {/if}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        {if $tabp->content->speed_limit === '0'}
                                                                            <div class="text-reset d-block">{trans key='common.unlimited'}</div>
                                                                        {else}
                                                                            <div class="text-reset d-block">{$tabp->content->speed_limit}
                                                                                Mbps
                                                                            </div>
                                                                        {/if}
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {trans key='shop.product.connection_speed'}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        {if $tabp->content->ip_limit === '0'}
                                                                            <div class="text-reset d-block">{trans key='common.unlimited'}</div>
                                                                        {else}
                                                                            <div class="text-reset d-block">{$tabp->content->ip_limit}</div>
                                                                        {/if}
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {trans key='shop.product.concurrent_ip_count'}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row g-2">
                                                            {if $tabp->stock === -1 || $tabp->stock > 0}
                                                                <div class="col">
                                                                    <a href="/user/order/create?product_id={$tabp->id}"
                                                                       class="btn btn-primary w-100 my-3">{trans key='shop.product.buy'}</a>
                                                                </div>
                                                            {else}
                                                                <div class="col">
                                                                    <a href="" class="btn btn-primary w-100 my-3"
                                                                       disabled>{trans key='shop.product.sold_out'}</a>
                                                                </div>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        {/foreach}
                                    </div>
                                </div>
                                <div class="tab-pane show" id="bandwidth">
                                    <div class="row">
                                        {foreach $bandwidths as $bandwidth}
                                            <div class="col-md-3 col-sm-12 my-3">
                                                <div class="card card-md">
                                                    <div class="card-body text-center">
                                                        <div id="product-{$bandwidth->id}-name"
                                                             class="text-uppercase text-secondary font-weight-medium">
                                                            {$bandwidth->name}</div>
                                                        <div id="product-{$bandwidth->id}-price"
                                                             class="display-6 my-3">
                                                            <p class="fw-bold">{$bandwidth->price}</p>
                                                            <i class="ti ti-currency-yuan"></i>
                                                        </div>
                                                        <div class="list-group list-group-flush">
                                                            <div class="list-group-item">
                                                                <div class="row align-items-center">
                                                                    <div class="col text-truncate">
                                                                        <div class="text-reset d-block">{$bandwidth->content->bandwidth}
                                                                            GB
                                                                        </div>
                                                                        <div class="d-block text-secondary text-truncate mt-n1">
                                                                            {if $bandwidth->content->current_month_only}
                                                                                {trans key='shop.product.current_month_traffic'}
                                                                            {else}
                                                                                {trans key='shop.product.available_traffic'}
                                                                            {/if}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row g-2">
                                                            {if $bandwidth->stock === -1 || $bandwidth->stock > 0}
                                                                <div class="col">
                                                                    <a href="/user/order/create?product_id={$bandwidth->id}"
                                                                       class="btn btn-primary w-100 my-3">{trans key='shop.product.buy'}</a>
                                                                </div>
                                                            {else}
                                                                <div class="col">
                                                                    <a href="" class="btn btn-primary w-100 my-3"
                                                                       disabled>{trans key='shop.product.sold_out'}</a>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {include file='user/footer.tpl'}
