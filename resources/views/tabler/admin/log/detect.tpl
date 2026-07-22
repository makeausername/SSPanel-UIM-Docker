{include file='admin/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center"><div class="col">
                <h2 class="page-title"><span class="home-title">XNode 审计事件</span></h2>
                <div class="page-pretitle my-3"><span class="home-subtitle">查看节点阻止或记录的规则命中</span></div>
            </div></div>
        </div>
    </div>
    <div class="page-body"><div class="container-xl"><div class="card"><div class="table-responsive">
        <table id="data-table" class="table card-table table-vcenter text-nowrap datatable">
            <thead><tr>{foreach $details['field'] as $key => $value}<th>{$value}</th>{/foreach}</tr></thead>
        </table>
    </div></div></div></div>

    {include file='datatable.tpl'}
    <script>
        tableConfig.serverSide = true;
        tableConfig.ajax = {url: '/admin/detect/log/ajax', type: 'POST', dataSrc: 'logs.data'};
        tableConfig.order = [[0, 'desc']];
        tableConfig.columnDefs = [{orderable: false, targets: [3, 4, 5, 6, 7, 8, 9]}];
        new DataTable('#data-table', tableConfig);
    </script>

    {include file='admin/footer.tpl'}
