{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='money.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='money.subtitle'}</span>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="btn-list">
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal"
                           data-bs-target="#topup">
                            <i class="icon ti ti-plus"></i>
                            {trans key='money.recharge'}
                        </a>
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal"
                           data-bs-target="#apply-giftcard-dialog">
                            <i class="icon ti ti-cash-banknote"></i>
                            {trans key='money.apply_giftcard'}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-sm-12 col-lg-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table card-table table-vcenter text-nowrap datatable">
                                <thead>
                                <tr>
                                    <th>{trans key='money.event_id'}</th>
                                    <th>{trans key='money.before_balance'}</th>
                                    <th>{trans key='money.after_balance'}</th>
                                    <th>{trans key='money.amount_changed'}</th>
                                    <th>{trans key='money.remark'}</th>
                                    <th>{trans key='money.changed_at'}</th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach $moneylogs as $moneylog}
                                    <tr>
                                        <td>{$moneylog->id}</td>
                                        <td>{$moneylog->before}</td>
                                        <td>{$moneylog->after}</td>
                                        <td>{$moneylog->amount}</td>
                                        <td>{$moneylog->remark}</td>
                                        <td>{$moneylog->create_time}</td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="apply-giftcard-dialog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{trans key='money.apply_giftcard'}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3 row">
                        <div class="col">
                            <input id="giftcard" type="text" class="form-control"
                                   placeholder="{trans key='money.giftcard_placeholder'}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">{trans key='common.cancel'}</button>
                    <button id="apply-giftcard" class="btn btn-primary" data-bs-dismiss="modal"
                            hx-post="/user/giftcard" hx-swap="none"
                            hx-vals='js:{ giftcard: document.getElementById("giftcard").value }'>
                        {trans key='money.redeem'}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="topup" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{trans key='money.recharge'}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3 row">
                        <div class="col">
                            <input id="topup_amount" type="number" step="10" class="form-control"
                                   placeholder="{trans key='money.topup_amount_placeholder'}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">{trans key='common.cancel'}</button>
                    <button id="apply-topup" class="btn btn-primary" data-bs-dismiss="modal"
                            hx-post="/user/order/create" hx-swap="none"
                            hx-vals='js:{
                                amount: document.getElementById("topup_amount").value,
                                type: "topup"
                            }'>
                        {trans key='money.topup'}
                    </button>
                </div>
            </div>
        </div>
    </div>

{include file='user/footer.tpl'}
