{include file='admin/header.tpl'}

<script src="https://{$config['jsdelivr_url']}/npm/jsoneditor@10.4.3/dist/jsoneditor.min.js"></script>
<link href="https://{$config['jsdelivr_url']}/npm/jsoneditor@10.4.3/dist/jsoneditor.min.css" rel="stylesheet" type="text/css">

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">节点 #{$node->id}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">编辑节点信息</span>
                    </div>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a id="save-node" href="#" class="btn btn-primary">
                            <i class="icon ti ti-device-floppy"></i>
                            保存
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-md-6 col-sm-12">
                    <div class="card">
                        <div class="card-header card-header-light">
                            <h3 class="card-title">基础信息</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">名称</label>
                                <div class="col">
                                    <input id="name" type="text" class="form-control" value="{$node->name|escape:'html'}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">连接地址</label>
                                <div class="col">
                                    <input id="server" type="text" class="form-control" value="{$node->server|escape:'html'}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">IPv4地址</label>
                                <div class="col">
                                    <input type="text" class="form-control" value="{$node->ipv4|escape:'html'}" disabled>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">IPv6地址</label>
                                <div class="col">
                                    <input type="text" class="form-control" value="{$node->ipv6|escape:'html'}" disabled>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">流量倍率</label>
                                <div class="col">
                                    <input id="traffic_rate" type="text" class="form-control"
                                           value="{$node->traffic_rate}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">接入类型</label>
                                <div class="col">
                                    <select id="sort" class="col form-select" value="{$node->sort}">
                                        <option value="14" {if $node->sort === 14}selected{/if}>Trojan</option>
                                        <option value="15" {if $node->sort === 15}selected{/if}>XNode / VLESS Reality Vision</option>
                                        <option value="11" {if $node->sort === 11}selected{/if}>Vmess</option>
                                        <option value="2" {if $node->sort === 2}selected{/if}>TUIC</option>
                                        <option value="1" {if $node->sort === 1}selected{/if}>Shadowsocks2022</option>
                                        <option value="0" {if $node->sort === 0}selected{/if}>Shadowsocks</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group mb-4 row">
                                <div class="col offset-3">
                                    <button id="apply-xnode-reality-template" class="btn btn-outline-primary btn-sm" type="button">
                                        <i class="icon ti ti-wand"></i>
                                        使用 XNode Reality 模板
                                    </button>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">自定义配置</label>
                                <div id="custom_config"></div>
                                <label class="form-label col-form-label">
                                    请参考
                                    <a href="https://docs.sspanel.io/docs/configuration/nodes" target="_blank">
                                        节点自定义配置文档
                                    </a>
                                    修改节点自定义配置
                                </label>
                            </div>
                            <div class="form-group mb-0 row">
                                <span class="col">显示此节点</span>
                                <span class="col-auto">
                                    <label class="form-check form-check-single form-switch">
                                        <input id="type" class="form-check-input" type="checkbox" {if $node->type}checked="" {/if}>
                                    </label>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-12">
                    <div class="card">
                        <div class="card-header card-header-light">
                            <h3 class="card-title">其他信息</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">等级</label>
                                <div class="col">
                                    <input id="node_class" type="text" class="form-control" value="{$node->node_class}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">组别</label>
                                <div class="col">
                                    <input id="node_group" type="text" class="form-control" value="{$node->node_group}">
                                </div>
                            </div>
                            <div class="hr-text">
                                <span>流量设置</span>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">已用流量</label>
                                <div class="col">
                                    <input id="node_bandwidth" type="text" class="form-control"
                                           value="{$node->node_bandwidth}" disabled="">
                                </div>
                                <div class="col-auto">
                                    <button id="reset-bandwidth" class="btn btn-red">重置</button>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">可用流量 (GB)</label>
                                <div class="col">
                                    <input id="node_bandwidth_limit" type="text" class="form-control"
                                           value="{$node->node_bandwidth_limit}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">流量重置日</label>
                                <div class="col">
                                    <input id="bandwidthlimit_resetday" type="text" class="form-control"
                                           value="{$node->bandwidthlimit_resetday}">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">速率限制 (Mbps)</label>
                                <div class="col">
                                    <input id="node_speedlimit" type="text" class="form-control"
                                           value="{$node->node_speedlimit}">
                                </div>
                            </div>
                            <div class="hr-text">
                                <span>高级选项</span>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">节点通讯密钥</label>
                                <input type="password" class="form-control" id="password" value="{$node_secret|escape:'html'}"
                                       disabled="">
                                <div class="row my-3">
                                    <div class="col">
                                        <button id="reset-password" class="btn btn-red">重置</button>
                                        <button id="copy-password" class="copy btn btn-primary"
                                                data-clipboard-text="{$node_secret|escape:'html'}">
                                            复制
                                        </button>
                                    </div>
                                </div>
                                <label class="form-label col-form-label">
                                    通讯密钥用于 NodeAPI 鉴权，如需更改请点击重置
                                </label>
                            </div>
                            {if $node->sort === 15}
                                <div class="hr-text">
                                    <span>节点部署</span>
                                </div>
                                <div class="form-group mb-3 row">
                                    <div class="col">
                                        <div class="btn-list">
                                            <button id="generate-xnode-install-command" class="btn btn-primary" type="button">
                                                <i class="icon ti ti-terminal"></i>
                                                生成 XNode 一键安装命令
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header card-header-light">
                            <h3 class="card-title">XNode 节点状态</h3>
                        </div>
                        <div class="card-body">
                            <div class="datagrid">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">运行状态</div>
                                    <div class="datagrid-content">
                                        <span class="badge {$xnode_summary.status_class|escape}">{$xnode_summary.status_text|escape}</span>
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">最近心跳</div>
                                    <div class="datagrid-content">{$xnode_summary.last_seen|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Agent</div>
                                    <div class="datagrid-content">{$xnode_summary.agent_version|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Xray</div>
                                    <div class="datagrid-content">{$xnode_summary.core_version|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">审计状态</div>
                                    <div class="datagrid-content">{$xnode_summary.audit_status|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">审计规则版本</div>
                                    <div class="datagrid-content text-break">{$xnode_summary.audit_revision|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">审计应用时间</div>
                                    <div class="datagrid-content">{$xnode_summary.audit_applied_at|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">当前在线 IP</div>
                                    <div class="datagrid-content">{$xnode_summary.online_count|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">节点已用流量</div>
                                    <div class="datagrid-content">{$xnode_summary.node_bandwidth|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">最近 Traffic 上报</div>
                                    <div class="datagrid-content">{$xnode_summary.latest_traffic_report|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">最近 Online 上报</div>
                                    <div class="datagrid-content">{$xnode_summary.latest_online_report|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">可达性</div>
                                    <div class="datagrid-content">
                                        <span class="badge {$xnode_probe_summary.badge_class|escape}">{$xnode_probe_summary.label|escape}</span>
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">检测区域</div>
                                    <div class="datagrid-content">{$xnode_probe_summary.latest_region|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">检测类型</div>
                                    <div class="datagrid-content">{$xnode_probe_summary.latest_probe_type|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">检测时间</div>
                                    <div class="datagrid-content">{$xnode_probe_summary.latest_checked_at|escape}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">延迟</div>
                                    <div class="datagrid-content">{$xnode_probe_summary.latest_latency_ms|escape}</div>
                                </div>
                                {if $xnode_probe_summary.latest_error !== ''}
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">检测错误</div>
                                        <div class="datagrid-content text-break">{$xnode_probe_summary.latest_error|escape}</div>
                                    </div>
                                {/if}
                                {if $xnode_summary.last_error !== ''}
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">Last Error</div>
                                        <div class="datagrid-content text-break">{$xnode_summary.last_error|escape}</div>
                                    </div>
                                {/if}
                                {if $xnode_summary.audit_error !== ''}
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">审计错误</div>
                                        <div class="datagrid-content text-break">{$xnode_summary.audit_error|escape}</div>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="xnode-install-command-dialog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">XNode 一键安装命令</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    此一键安装命令 10 分钟内有效，请勿公开分享；请以 root 身份粘贴到目标 Linux 节点服务器执行。
                </div>
                <div class="mb-3">
                    <label class="form-label">有效期</label>
                    <input id="xnode-token-expires" type="text" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Linux 一键安装命令</label>
                    <textarea id="xnode-command-text" class="form-control font-monospace" rows="18" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn me-auto" data-bs-dismiss="modal">关闭</button>
                <button id="copy-xnode-install-command" class="copy btn btn-primary" type="button" data-clipboard-target="#xnode-command-text">
                    <i class="icon ti ti-copy"></i>
                    复制命令
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    let clipboard = new ClipboardJS('.copy');
    clipboard.on('success', function (e) {
        if (e.trigger && e.trigger.id === 'copy-xnode-install-command') {
            $('#success-message').text('命令已复制，正在返回节点列表');
            $('#success-dialog').modal('show');
            $('#xnode-install-command-dialog').modal('hide');
            clearXNodeInstallCommand();
            window.setTimeout(function () {
                window.location.href = '/admin/node';
            }, 800);
            return;
        }

        $('#success-message').text('已复制到剪切板');
        $('#success-dialog').modal('show');
    });

    const container = document.getElementById('custom_config');
    let options = {
        modes: ['code', 'tree'],
    };
    const editor = new JSONEditor(container, options);
    editor.set({$node_custom_config})

    const xnodeRealityTemplate = {
        xnode: {
            enabled: true,
            profile: 'vless-reality-vision',
            port: 443,
            network: 'raw',
            security: 'reality',
            flow: 'xtls-rprx-vision',
            sni: 'www.cloudflare.com',
            fingerprint: 'chrome'
        }
    };

    function applyXNodeManagedPolicy() {
        const isXNode = $('#sort').val() === '15';
        const trafficRate = '2';
        const values = {
            '#traffic_rate': trafficRate,
            '#node_class': '0',
            '#node_group': '0',
            '#node_speedlimit': '0',
            '#node_bandwidth_limit': '0',
            '#bandwidthlimit_resetday': '1'
        };

        if (isXNode) {
            Object.entries(values).forEach(([selector, value]) => $(selector).val(value));
        }

        Object.keys(values).forEach(selector => $(selector).prop('readonly', isXNode));
    }

    $("#apply-xnode-reality-template").click(function () {
        $('#sort').val('15').trigger('change');
        editor.set(xnodeRealityTemplate);
    });

    $('#sort').on('change', applyXNodeManagedPolicy);
    applyXNodeManagedPolicy();

    $("#reset-bandwidth").click(function () {
        $.ajax({
            url: '/admin/node/{$node->id}/reset_bandwidth',
            type: 'POST',
            dataType: "json",
            success: function (data) {
                if (data.ret === 1) {
                    $('#success-message').text(data.msg);
                    $('#success-dialog').modal('show');
                } else {
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            }
        })
    });

    $("#reset-password").click(function () {
        $.ajax({
            url: '/admin/node/{$node->id}/reset_password',
            type: 'POST',
            dataType: "json",
            success: function (data) {
                if (data.ret === 1) {
                    $('#success-message').text(data.msg);
                    $('#success-dialog').modal('show');
                } else {
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            }
        })
    });

    let xnodeTokenExpireTimer = null;

    function clearXNodeInstallCommand() {
        if (xnodeTokenExpireTimer !== null) {
            window.clearTimeout(xnodeTokenExpireTimer);
            xnodeTokenExpireTimer = null;
        }

        $('#xnode-token-expires').val('');
        $('#xnode-command-text').val('');
    }

    function scheduleXNodeTokenClear(expiresIn) {
        let seconds = Number(expiresIn || 600);

        if (!Number.isFinite(seconds) || seconds <= 0) {
            seconds = 600;
        }

        xnodeTokenExpireTimer = window.setTimeout(function () {
            $('#xnode-token-expires').val('已过期，请重新生成一键安装命令');
            $('#xnode-command-text').val('');
            xnodeTokenExpireTimer = null;
        }, seconds * 1000);
    }

    $("#generate-xnode-install-command").click(function () {
        let button = $(this);
        button.prop('disabled', true);
        clearXNodeInstallCommand();

        $.ajax({
            url: '/admin/node/{$node->id}/xnode_install_command',
            type: 'POST',
            dataType: "json",
            success: function (data) {
                if (data.ret === 1) {
                    let command = data.install_command || data.command || '';

                    if (String(command).trim() === '') {
                        $('#fail-message').text('生成 XNode 命令失败：未返回安装命令');
                        $('#fail-dialog').modal('show');
                        return;
                    }

                    let expiresIn = data.expires_in || 600;
                    let expiresAt = data.expires_at_text || data.expires_at || '-';

                    $('#xnode-token-expires').val('10 分钟内有效，过期时间：' + expiresAt);
                    $('#xnode-command-text').val(command);
                    $('#xnode-install-command-dialog').modal('show');
                    scheduleXNodeTokenClear(expiresIn);
                } else {
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            },
            error: function () {
                $('#fail-message').text('生成 XNode 命令失败');
                $('#fail-dialog').modal('show');
            },
            complete: function () {
                button.prop('disabled', false);
            }
        })
    });

    let xnodeInstallButton = $("#generate-xnode-install-command");
    if (xnodeInstallButton.length > 0) {
        let shouldOpenXNodeInstall = false;

        if (window.URLSearchParams) {
            shouldOpenXNodeInstall = (new URLSearchParams(window.location.search)).get('open_xnode_install') === '1';
        } else {
            shouldOpenXNodeInstall = window.location.search.indexOf('open_xnode_install=1') !== -1;
        }

        if (shouldOpenXNodeInstall) {
            xnodeInstallButton.click();
        }
    }

    $("#xnode-install-command-dialog").on('hidden.bs.modal', function () {
        clearXNodeInstallCommand();
    });

    $("#save-node").click(function () {
        $.ajax({
            url: '/admin/node/{$node->id}',
            type: 'PUT',
            dataType: "json",
            data: {
                {foreach $update_field as $key}
                {$key}: $('#{$key}').val(),
                {/foreach}
                type: $("#type").is(":checked"),
                custom_config: JSON.stringify(editor.get()),
            },
            success: function (data) {
                if (data.ret === 1) {
                    $('#success-message').text(data.msg);
                    $('#success-dialog').modal('show');
                    redirectAfterSuccess('/admin/node', {$config['jump_delay']});
                } else {
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            }
        })
    });
</script>

{include file='admin/footer.tpl'}
