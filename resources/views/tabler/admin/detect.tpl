{include file='admin/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title"><span class="home-title">XNode 审计规则</span></h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">规则由面板统一下发到所有适用的节点；系统托管规则可停用但不能删除</span>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-detect-dialog">
                        <i class="icon ti ti-plus"></i> 添加审计规则
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="alert alert-info">
                默认启用：私有网络地址、BitTorrent、出站 SMTP 25 端口，以及用户提供的投诉域名清单。规则变更会由节点自动同步，无需逐台配置。
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table id="data-table" class="table card-table table-vcenter text-nowrap datatable">
                        <thead><tr>
                            {foreach $details['field'] as $key => $value}<th>{$value}</th>{/foreach}
                        </tr></thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="add-detect-dialog" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加 XNode 审计规则</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {foreach $details['add_dialog'] as $form}
                        <div class="form-group mb-3 row">
                            <label class="form-label col-3 col-form-label">{$form['info']}</label>
                            <div class="col">
                                {if $form['type'] === 'input'}
                                    <input id="{$form['id']}" type="text" class="form-control" placeholder="{$form['placeholder']}">
                                {elseif $form['type'] === 'textarea'}
                                    <textarea id="{$form['id']}" class="form-control" rows="{$form['rows']}" placeholder="{$form['placeholder']}"></textarea>
                                {elseif $form['type'] === 'select'}
                                    <select id="{$form['id']}" class="form-select">
                                        {foreach $form['select'] as $key => $value}<option value="{$key}">{$value}</option>{/foreach}
                                    </select>
                                {/if}
                            </div>
                        </div>
                    {/foreach}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">取消</button>
                    <button id="add-detect-button" type="button" class="btn btn-primary" data-bs-dismiss="modal">提交</button>
                </div>
            </div>
        </div>
    </div>

    {include file='datatable.tpl'}
    <script>
        tableConfig.ajax = {
            url: '/admin/detect/ajax',
            type: 'POST',
            dataSrc: 'rules'
        };
        tableConfig.order = [[1, 'asc']];
        tableConfig.columnDefs = [
            {
                targets: [0, 3, 4],
                orderable: false
            }
        ];
        let table = new DataTable('#data-table', tableConfig);

        $('#add-detect-button').click(function () {
            $.ajax({
                type: 'POST',
                url: '/admin/detect/add',
                dataType: 'json',
                data: {
                    {foreach $details['add_dialog'] as $form}
                    {$form['id']}: $('#{$form['id']}').val(),
                    {/foreach}
                },
                success: showResult
            });
        });

        function showResult(data) {
            if (data.ret === 1) {
                $('#success-message').text(data.msg);
                $('#success-dialog').modal('show');
                table.ajax.reload(null, false);
            } else {
                $('#fail-message').text(data.msg);
                $('#fail-dialog').modal('show');
            }
        }

        function toggleRule(ruleId) {
            $.ajax({
                url: '/admin/detect/' + ruleId + '/toggle',
                type: 'PUT',
                dataType: 'json',
                success: showResult
            });
        }

        function deleteRule(ruleId) {
            $('#notice-message').text('确定删除这条审计规则？');
            $('#notice-dialog').modal('show');
            $('#notice-confirm').off('click').on('click', function () {
                $.ajax({
                    url: '/admin/detect/' + ruleId,
                    type: 'DELETE',
                    dataType: 'json',
                    success: showResult
                });
            });
        }
    </script>

    {include file='admin/footer.tpl'}
