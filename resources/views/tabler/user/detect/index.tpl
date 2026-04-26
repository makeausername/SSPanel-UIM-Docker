{include file='user/header.tpl'}

<!-- 审计规则是用来防止 DMCA 和邮件 Spam，不是用来给用户建墙用的，不要以为你在中国开机场同时把“违法网站”墙了，被抓了能少判哪怕一天的刑期 -->
<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='detect.rules_title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='detect.rules_subtitle'}</span>
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
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                <tr>
                                    <th>{trans key='detect.id'}</th>
                                    <th>{trans key='detect.name'}</th>
                                    <th>{trans key='detect.description'}</th>
                                    <th>{trans key='detect.regex'}</th>
                                    <th>{trans key='detect.type'}</th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach $rules as $rule}
                                    <tr>
                                        <td>#{$rule->id}</td>
                                        <td>{$rule->name}</td>
                                        <td>{$rule->text}</td>
                                        <td>{$rule->regex}</td>
                                        {if $rule->type === 1}
                                            <td>{trans key='detect.packet_plain_match'}</td>
                                        {/if}
                                        {if $rule->type === 2}
                                            <td>{trans key='detect.packet_hex_match'}</td>
                                        {/if}
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

    {include file='user/footer.tpl'}
