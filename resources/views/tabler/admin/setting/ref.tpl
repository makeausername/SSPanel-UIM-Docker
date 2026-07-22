{include file='admin/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='user.invite.admin_title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='user.invite.admin_subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">{trans key='user.invite.admin_fixed_rule_title'}</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <p>{trans key='user.invite.admin_fixed_rule_read_only'}</p>
                                <ul class="mb-0">
                                    <li>{trans key='user.invite.reward_rule_30_days'}</li>
                                    <li>{trans key='user.invite.reward_rule_60_days'}</li>
                                    <li>{trans key='user.invite.reward_rule_once'}</li>
                                    <li>{trans key='user.invite.reward_rule_accumulate'}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {include file='admin/footer.tpl'}
