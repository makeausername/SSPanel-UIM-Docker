{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='order.create.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='order.create.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-sm-12 col-md-6 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='order.content'}</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-transparent table-responsive">
                                <tr hidden>
                                    <td>{trans key='shop.product.id'}</td>
                                    <td id="product-id" class="text-end">{$product->id}</td>
                                </tr>
                                <tr>
                                    <td>{trans key='shop.product.name'}</td>
                                    <td class="text-end">{$product->name|escape:'html'}</td>
                                </tr>
                                <tr>
                                    <td>{trans key='shop.product.type'}</td>
                                    <td class="text-end">{$product->type_text}</td>
                                </tr>
                                {if $product->type === 'tabp' || $product->type === 'time'}
                                    {if $product->content->monthly_plan}
                                        <tr>
                                            <td>{trans key='shop.product.subscription_period'}</td>
                                            <td class="text-end">{trans key='shop.product.one_year'}</td>
                                        </tr>
                                        <tr>
                                            <td>{trans key='shop.product.node_access'}</td>
                                            <td class="text-end">{trans key='shop.product.all_nodes'}</td>
                                        </tr>
                                    {else}
                                        <tr>
                                            <td>{trans key='shop.product.duration'}</td>
                                            <td class="text-end">{$product->content->time} {trans key='common.days'}</td>
                                        </tr>
                                        <tr>
                                            <td>{trans key='shop.product.level_duration'}</td>
                                            <td class="text-end">{$product->content->class_time} {trans key='common.days'}</td>
                                        </tr>
                                        <tr>
                                            <td>{trans key='shop.product.level'}</td>
                                            <td class="text-end">Lv. {$product->content->class}</td>
                                        </tr>
                                    {/if}
                                {/if}
                                {if $product->type === 'tabp' || $product->type === 'bandwidth'}
                                    <tr>
                                        <td>
                                            {if $product->content->monthly_plan}
                                                {trans key='shop.product.monthly_traffic'}
                                            {elseif $product->content->current_month_only}
                                                {trans key='shop.product.current_month_traffic'}
                                            {else}
                                                {trans key='shop.product.available_traffic'}
                                            {/if}
                                        </td>
                                        {if $product->content->unlimited_bandwidth}
                                            <td class="text-end">{trans key='common.unlimited'}</td>
                                        {else}
                                            <td class="text-end">{$product->content->bandwidth} GB</td>
                                        {/if}
                                    </tr>
                                {/if}
                                {if $product->type === 'tabp' || $product->type === 'time'}
                                    <tr>
                                        <td>{trans key='shop.product.speed_limit'}</td>
                                        {if $product->content->speed_limit === '0'}
                                            <td class="text-end">{trans key='common.unlimited'}</td>
                                        {else}
                                            <td class="text-end">{$product->content->speed_limit} Mbps</td>
                                        {/if}
                                    </tr>
                                    <tr>
                                        <td>{trans key='shop.product.concurrent_ip_limit'}</td>
                                        {if $product->content->ip_limit === '0'}
                                            <td class="text-end">{trans key='common.unlimited'}</td>
                                        {else}
                                            <td class="text-end">{$product->content->ip_limit}</td>
                                        {/if}
                                    </tr>
                                {/if}
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6 col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='order.price_details_yuan'}</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-transparent table-responsive">
                                <tr>
                                    <td>{trans key='shop.product.price'}</td>
                                    <td class="text-end">{$product->price}</td>
                                </tr>
                                <tr>
                                    <td>{trans key='order.coupon'}</td>
                                    <td class="text-end" id="coupon-code"></td>
                                </tr>
                                <tr>
                                    <td>{trans key='order.discount_amount'}</td>
                                    <td class="text-end" id="product-buy-discount"></td>
                                </tr>
                                <tr>
                                    <td>{trans key='order.actual_payment'}</td>
                                    <td class="text-end" id="product-buy-total">{$product->price}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="card my-3">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='order.coupon'}</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="input-group mb-2">
                                    <input id="coupon" type="text" class="form-control"
                                           placeholder="{trans key='order.coupon_placeholder'}">
                                    <button class="btn" type="button"
                                            hx-post="/user/coupon" hx-swap="none"
                                            hx-vals='js:{
                                                coupon: document.getElementById("coupon").value,
                                                product_id: {$product->id},
                                            }'>
                                        {trans key='common.apply'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card my-3">
                        <div class="card-body">
                            <button class="btn btn-primary w-100 my-3"
                                    hx-post="/user/order/create" hx-swap="none"
                                    hx-vals='js:{
                                        type: "product",
                                        coupon: document.getElementById("coupon").value,
                                        product_id: {$product->id},
                                    }'>
                                {trans key='order.create.submit'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {include file='user/footer.tpl'}
