{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='detect.log_title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='detect.log_subtitle'}</span>
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
                                    <th>{trans key='detect.event_id'}</th>
                                    <th>{trans key='detect.node_id'}</th>
                                    <th>{trans key='detect.node_name'}</th>
                                    <th>{trans key='detect.rule_id'}</th>
                                    <th>{trans key='detect.name'}</th>
                                    <th>{trans key='detect.description'}</th>
                                    <th>{trans key='detect.regex'}</th>
                                    <th>{trans key='detect.type'}</th>
                                    <th>{trans key='detect.time'}</th>
                                </tr>
                                </thead>
                                <tbody>
                                {foreach $logs as $log}
                                    <tr>
                                        <td>#{$log->id|escape:'html'}</td>
                                        <td>{$log->node_id|escape:'html'}</td>
                                        <td>{$log->node_name|escape:'html'}</td>
                                        <td>{$log->list_id|escape:'html'}</td>
                                        {if $log->rule !== null}
                                            <td>{$log->rule->name|escape:'html'}</td>
                                            <td>{$log->rule->text|escape:'html'}</td>
                                            <td>{$log->rule->regex|escape:'html'}</td>
                                            <td>{$log->rule->type|escape:'html'}</td>
                                        {else}
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                        {/if}
                                        <td>{$log->datetime|escape:'html'}</td>
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
