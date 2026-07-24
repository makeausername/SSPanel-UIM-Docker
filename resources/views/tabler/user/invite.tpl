{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='user.invite.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='user.invite.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-12">
                    <div class="row row-deck row-cards">
                        <div class="col-sm-12 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title">{trans key='user.invite.rules'}</h3>
                                    <ul>
                                        <li>{trans key='user.invite.reward_rule_30_days'}</li>
                                        <li>{trans key='user.invite.reward_rule_60_days'}</li>
                                        <li>{trans key='user.invite.reward_rule_once'}</li>
                                        <li>{trans key='user.invite.reward_rule_accumulate'}</li>
                                    </ul>
                                    <p>
                                        {trans key='user.invite.applied_days'}:
                                        <code>{$applied_days}</code> {trans key='user.invite.days'}
                                    </p>
                                    <p class="mb-0">
                                        {trans key='user.invite.pending_days'}:
                                        <code>{$pending_days}</code> {trans key='user.invite.days'}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title">{trans key='user.invite.link'}</h3>
                                    <input class="form-control" id="invite-url" value="{$invite_url}" disabled>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex">
                                        <button class="btn text-red btn-link"
                                                hx-post="/user/invite/reset" hx-swap="none">
                                            {trans key='common.reset'}
                                        </button>
                                        <button data-clipboard-text="{$invite_url}"
                                                class="copy btn btn-primary ms-auto">
                                            {trans key='common.copy'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 my-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='user.invite.reward_records'}</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table card-table table-vcenter text-nowrap datatable">
                                <thead>
                                <tr>
                                    <th>{trans key='user.invite.record_id'}</th>
                                    <th>{trans key='user.invite.invited_user_id'}</th>
                                    <th>{trans key='user.invite.invited_username'}</th>
                                    <th>{trans key='user.invite.purchased_plan'}</th>
                                    <th>{trans key='user.invite.reward_days'}</th>
                                    <th>{trans key='user.invite.reward_status'}</th>
                                    <th>{trans key='user.invite.reward_time'}</th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach $rewards as $reward}
                                    <tr>
                                        <td>{$reward->id}</td>
                                        <td>{$reward->invited_user_id}</td>
                                        <td>{$reward->user_name|escape:'html'}</td>
                                        <td>{$reward->product_name|escape:'html'}</td>
                                        <td>{$reward->reward_days} {trans key='user.invite.days'}</td>
                                        <td>
                                            {if $reward->status === 'applied'}
                                                {trans key='user.invite.status_applied'}
                                            {elseif $reward->status === 'pending'}
                                                {trans key='user.invite.status_pending'}
                                            {else}
                                                {trans key='user.invite.status_cancelled'}
                                            {/if}
                                        </td>
                                        <td>{$reward->created_at}</td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                        {if $last_page > 1}
                            <div class="card-footer d-flex justify-content-end gap-2">
                                {if $current_page > 1}
                                    <a class="btn btn-outline-primary" href="?page={$current_page - 1}">&larr;</a>
                                {/if}
                                <span class="btn disabled">{$current_page} / {$last_page}</span>
                                {if $current_page < $last_page}
                                    <a class="btn btn-outline-primary" href="?page={$current_page + 1}">&rarr;</a>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {include file='user/footer.tpl'}
