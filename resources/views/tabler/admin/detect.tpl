{include file='admin/header.tpl'}

<style>
    .audit-rules-page .page-header {
        padding-bottom: 2rem;
    }

    .audit-rules-page .audit-summary-card,
    .audit-rules-page .audit-rules-card {
        border: 0;
        box-shadow: 0 0.25rem 1.25rem rgba(24, 36, 51, 0.08);
    }

    .audit-rules-page .audit-summary-card {
        overflow: hidden;
    }

    .audit-rules-page .audit-summary-card::before {
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        content: '';
        background: var(--tblr-primary);
    }

    .audit-summary-icon {
        display: inline-flex;
        width: 3rem;
        height: 3rem;
        flex: 0 0 3rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        color: var(--tblr-primary);
        background: rgba(var(--tblr-primary-rgb), 0.1);
    }

    .audit-summary-icon .icon {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 1.5rem;
    }

    .audit-summary-tags .badge {
        padding: 0.4rem 0.6rem;
        font-weight: 500;
    }

    .audit-rules-card .card-header {
        min-height: 4.5rem;
        border-bottom-color: var(--tblr-border-color-translucent);
    }

    .audit-rules-page .dt-container > .row:first-child {
        align-items: center;
        gap: 0.75rem;
        margin: 0;
        border-bottom: 1px solid var(--tblr-border-color-translucent);
    }

    .audit-rules-page .dt-search label {
        display: flex;
        align-items: center;
        margin: 0;
    }

    .audit-rules-page .dt-search input {
        width: 16rem;
        min-height: 2.5rem;
        margin-left: 0 !important;
        border-radius: 0.5rem;
    }

    .audit-rule-table {
        min-width: 1160px;
        margin-bottom: 0 !important;
        table-layout: fixed;
    }

    .audit-rule-table th,
    .audit-rule-table td {
        white-space: normal !important;
    }

    .audit-rule-table th {
        padding-top: 0.85rem;
        padding-bottom: 0.85rem;
        color: var(--tblr-secondary-color);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .audit-rule-table td {
        padding-top: 1rem;
        padding-bottom: 1rem;
        vertical-align: top;
    }

    .audit-rule-table th:nth-child(1) { width: 190px; }
    .audit-rule-table th:nth-child(2) { width: 220px; }
    .audit-rule-table th:nth-child(3) { width: 280px; }
    .audit-rule-table th:nth-child(4) { width: 145px; }
    .audit-rule-table th:nth-child(5) { width: 120px; }
    .audit-rule-table th:nth-child(6) { width: 120px; }
    .audit-rule-table th:nth-child(7) { width: 95px; }
    .audit-rule-table th:nth-child(8) { width: 150px; }

    .audit-rule-name {
        color: var(--tblr-body-color);
        font-weight: 600;
        line-height: 1.45;
    }

    .audit-rule-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
        margin-top: 0.45rem;
        color: var(--tblr-secondary-color);
        font-size: 0.75rem;
    }

    .audit-rule-description {
        color: var(--tblr-secondary-color);
        line-height: 1.65;
    }

    .audit-pattern-preview,
    .audit-pattern-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .audit-pattern-chip {
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        padding: 0.25rem 0.45rem;
        border: 1px solid var(--tblr-border-color-translucent);
        border-radius: 0.4rem;
        color: var(--tblr-body-color);
        background: var(--tblr-bg-surface-secondary);
        font-size: 0.75rem;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .audit-patterns details {
        margin-top: 0.55rem;
    }

    .audit-patterns summary {
        display: inline-flex;
        cursor: pointer;
        color: var(--tblr-primary);
        font-size: 0.8rem;
        font-weight: 500;
        list-style: none;
    }

    .audit-patterns summary::-webkit-details-marker {
        display: none;
    }

    .audit-patterns details[open] summary {
        margin-bottom: 0.6rem;
    }

    .audit-pattern-list {
        max-height: 11rem;
        overflow-y: auto;
        padding: 0.65rem;
        border-radius: 0.5rem;
        background: var(--tblr-bg-surface-secondary);
    }

    .audit-policy-stack {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        align-items: flex-start;
    }

    .audit-status {
        display: inline-flex;
        gap: 0.4rem;
        align-items: center;
        font-weight: 500;
    }

    .audit-status-dot {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
    }

    .audit-rule-actions {
        display: flex;
        gap: 0.4rem;
        justify-content: flex-end;
    }

    @media (max-width: 767.98px) {
        .audit-rules-page .page-header .row {
            gap: 1rem;
        }

        .audit-rules-page .page-header .col-auto,
        .audit-rules-page .page-header .btn {
            width: 100%;
        }

        .audit-rules-page .dt-container > .row:first-child > div,
        .audit-rules-page .dt-search,
        .audit-rules-page .dt-search label,
        .audit-rules-page .dt-search input {
            width: 100%;
        }

        .audit-summary-icon {
            display: none;
        }
    }
</style>

<div class="page-wrapper audit-rules-page">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title"><span class="home-title">XNode 审计规则</span></h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">集中管理节点流量策略，规则变更会自动同步到适用节点</span>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-detect-dialog">
                        <i class="icon ti ti-plus"></i>
                        添加审计规则
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card audit-summary-card position-relative">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="audit-summary-icon">
                                    <i class="icon ti ti-shield-check"></i>
                                </span>
                                <div class="flex-fill">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="card-title mb-0">默认防护已启用</h3>
                                        <span class="badge bg-green-lt text-green">节点自动同步</span>
                                    </div>
                                    <div class="text-secondary mb-2">
                                        系统托管规则可停用但不能删除；新增或调整规则后，无需逐台登录节点配置。
                                    </div>
                                    <div class="audit-summary-tags d-flex flex-wrap gap-2">
                                        <span class="badge bg-blue-lt text-blue">私有网络</span>
                                        <span class="badge bg-purple-lt text-purple">BitTorrent</span>
                                        <span class="badge bg-orange-lt text-orange">出站 SMTP 25</span>
                                        <span class="badge bg-red-lt text-red">投诉域名清单</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card audit-rules-card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title mb-1">规则列表</h3>
                                <div class="text-secondary small">按优先级下发，数字越小越先匹配</div>
                            </div>
                            <div class="card-actions">
                                <a href="/admin/detect/log" class="btn btn-ghost-secondary btn-sm">
                                    <i class="icon ti ti-list-details"></i>
                                    查看命中记录
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="data-table" class="table card-table table-vcenter datatable audit-rule-table">
                                <thead>
                                <tr>
                                    {foreach $details['field'] as $key => $value}
                                        <th>{$value}</th>
                                    {/foreach}
                                </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal modal-blur fade" id="add-detect-dialog" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">添加 XNode 审计规则</h5>
                        <div class="text-secondary small mt-1">规则保存后将在节点下一次同步时生效</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {foreach $details['add_dialog'] as $form}
                            <div class="{if $form['type'] === 'textarea'}col-12{else}col-md-6{/if}">
                                <label class="form-label" for="{$form['id']}">{$form['info']}</label>
                                {if $form['type'] === 'input'}
                                    <input id="{$form['id']}" type="text" class="form-control" placeholder="{$form['placeholder']}">
                                {elseif $form['type'] === 'textarea'}
                                    <textarea id="{$form['id']}" class="form-control font-monospace" rows="{$form['rows']}" placeholder="{$form['placeholder']}"></textarea>
                                {elseif $form['type'] === 'select'}
                                    <select id="{$form['id']}" class="form-select">
                                        {foreach $form['select'] as $key => $value}
                                            <option value="{$key}">{$value}</option>
                                        {/foreach}
                                    </select>
                                {/if}
                            </div>
                        {/foreach}
                    </div>
                    <div class="alert alert-info mt-4 mb-0 py-2">
                        <i class="icon ti ti-info-circle me-1"></i>
                        应用范围选择“所有节点”时，范围 ID 请留空。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn me-auto" data-bs-dismiss="modal">取消</button>
                    <button id="add-detect-button" type="button" class="btn btn-primary">
                        <i class="icon ti ti-device-floppy"></i>
                        保存并下发
                    </button>
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
        tableConfig.scrollX = false;
        tableConfig.order = [];
        tableConfig.language.sSearch = '';
        tableConfig.language.searchPlaceholder = '搜索规则、域名或说明';
        tableConfig.columnDefs = [
            {
                targets: '_all',
                orderable: false
            }
        ];

        const defaultTableInit = tableConfig.initComplete;
        tableConfig.initComplete = function () {
            defaultTableInit.call(this);
            $('#data-table_wrapper .dt-search input')
                .addClass('form-control')
                .attr('aria-label', '搜索审计规则');
        };

        let table = new DataTable('#data-table', tableConfig);

        $('#add-detect-button').click(function () {
            const button = $(this);
            const originalContent = button.html();
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>保存中');

            $.ajax({
                type: 'POST',
                url: '/admin/detect/add',
                dataType: 'json',
                data: {
                    {foreach $details['add_dialog'] as $form}
                    {$form['id']}: $('#{$form['id']}').val(),
                    {/foreach}
                },
                success: function (data) {
                    if (data.ret === 1) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('add-detect-dialog')).hide();
                        $('#add-detect-dialog input, #add-detect-dialog textarea').val('');
                        $('#add-detect-dialog select').prop('selectedIndex', 0);
                    }
                    showResult(data);
                },
                complete: function () {
                    button.prop('disabled', false).html(originalContent);
                }
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
