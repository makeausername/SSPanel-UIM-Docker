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
                                        <td>#{$log->id}</td>
                                        <td>{$log->node_id}</td>
                                        <td>{$log->node_name}</td>
                                        <td>{$log->list_id}</td>
                                        <td>{$log->rule->name}</td>
                                        <td>{$log->rule->text}</td>
                                        <td>{$log->rule->regex}</td>
                                        <td>{$log->rule->type}</td>
                                        <td>{$log->datetime}</td>
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
