{include file='admin/header.tpl'}

<script src="//{$config['jsdelivr_url']}/npm/jsoneditor@latest/dist/jsoneditor.min.js"></script>
<link href="//{$config['jsdelivr_url']}/npm/jsoneditor@latest/dist/jsoneditor.min.css" rel="stylesheet" type="text/css">

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">创建节点</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">创建各类节点</span>
                    </div>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a id="create-node" href="#" class="btn btn-primary">
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
                                <label class="form-label col-3 col-form-label required">名称</label>
                                <div class="col">
                                    <input id="name" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">连接地址</label>
                                <div class="col">
                                    <input id="server" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">流量倍率</label>
                                <div class="col">
                                    <input id="traffic_rate" type="text" class="form-control"
                                           value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">接入类型</label>
                                <div class="col">
                                    <select id="sort" class="col form-select">
                                        <option value="15" selected>XNode / VLESS Reality Vision</option>
                                        <option value="14">Trojan</option>
                                        <option value="11">Vmess</option>
                                        <option value="2">TUIC</option>
                                        <option value="1">Shadowsocks2022</option>
                                        <option value="0">Shadowsocks</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <div class="col offset-3">
                                    <button id="apply-xnode-reality-template" class="btn btn-outline-primary btn-sm" type="button">
                                        <i class="icon ti ti-wand"></i>
                                        使用 XNode Reality 模板
                                    </button>
                                </div>
                            </div>
                            <div id="xnode-managed-policy" class="alert alert-info" role="alert">
                                <strong>XNode 统一流量策略 / Uniform traffic policy</strong><br>
                                上行和下行分别按固定 <strong>2×</strong> 计入用户套餐；关闭动态倍率，不限速、不限制节点流量，每月 1 日重置节点统计。
                                / Upload and download are each billed at a fixed 2× rate, with no dynamic rate, speed limit, or node quota; node statistics reset on day 1.
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
                            <div class="form-group mb-3 row">
                                <span class="col">显示此节点</span>
                                <span class="col-auto">
                                    <label class="form-check form-check-single form-switch">
                                        <input id="type" class="form-check-input" type="checkbox" checked="">
                                    </label>
                                </span>
                            </div>
                            <div class="hr-text">
                                <span>动态倍率</span>
                            </div>
                            <div class="form-group mb-3 row">
                                <span class="col">启用动态流量倍率</span>
                                <span class="col-auto">
                                    <label class="form-check form-check-single form-switch">
                                        <input id="is_dynamic_rate" class="form-check-input" type="checkbox" checked="">
                                    </label>
                                </span>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">动态流量倍率计算方式</label>
                                <div class="col">
                                    <select id="dynamic_rate_type" class="col form-select">
                                        <option value="0">Logistic</option>
                                        <option value="1">Linear</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">最大倍率</label>
                                <div class="col">
                                    <input id="max_rate" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">最大倍率时间（时）</label>
                                <div class="col">
                                    <input id="max_rate_time" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">最小倍率</label>
                                <div class="col">
                                    <input id="min_rate" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label">最小倍率时间（时）</label>
                                <div class="col">
                                    <input id="min_rate_time" type="text" class="form-control" value="">
                                </div>
                                <label class="form-label col-form-label">
                                    最大倍率时间必须大于最小倍率时间，否则将不会生效
                                </label>
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
                                <label class="form-label col-3 col-form-label required">等级</label>
                                <div class="col">
                                    <input id="node_class" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">组别</label>
                                <div class="col">
                                    <input id="node_group" type="text" class="form-control" value="">
                                </div>
                            </div>
                            <div class="hr-text">
                                <span>流量设置</span>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">可用流量 (GB)</label>
                                <div class="col">
                                    <input id="node_bandwidth_limit" type="text" class="form-control"
                                           value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">流量重置日</label>
                                <div class="col">
                                    <input id="bandwidthlimit_resetday" type="text" class="form-control"
                                           value="">
                                </div>
                            </div>
                            <div class="form-group mb-3 row">
                                <label class="form-label col-3 col-form-label required">速率限制 (Mbps)</label>
                                <div class="col">
                                    <input id="node_speedlimit" type="text" class="form-control"
                                           value="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const container = document.getElementById('custom_config');
    let options = {
        modes: ['code', 'tree'],
    };
    const editor = new JSONEditor(container, options);

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
            '#max_rate': trafficRate,
            '#max_rate_time': '22',
            '#min_rate': trafficRate,
            '#min_rate_time': '3',
            '#node_class': '0',
            '#node_group': '0',
            '#node_speedlimit': '0',
            '#node_bandwidth_limit': '0',
            '#bandwidthlimit_resetday': '1'
        };

        if (isXNode) {
            Object.entries(values).forEach(([selector, value]) => $(selector).val(value));
            $('#is_dynamic_rate').prop('checked', false);
            $('#dynamic_rate_type').val('0');
        }

        Object.keys(values).forEach(selector => $(selector).prop('readonly', isXNode));
        $('#is_dynamic_rate, #dynamic_rate_type').prop('disabled', isXNode);
        $('#xnode-managed-policy').toggleClass('d-none', !isXNode);
    }

    $("#apply-xnode-reality-template").click(function () {
        $('#sort').val('15').trigger('change');
        editor.set(xnodeRealityTemplate);
    });

    $('#sort').on('change', applyXNodeManagedPolicy);
    applyXNodeManagedPolicy();
    editor.set(xnodeRealityTemplate);

    $("#create-node").click(function () {
        $.ajax({
            url: '/admin/node',
            type: 'POST',
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
                    if ($('#sort').val() === '15' && data.node_id) {
                        window.setTimeout(function () {
                            location.href = '/admin/node/' + data.node_id + '/edit?open_xnode_install=1';
                        }, {$config['jump_delay']});
                    } else {
                        window.setTimeout("location.href=top.document.referrer", {$config['jump_delay']});
                    }
                } else {
                    $('#fail-message').text(data.msg);
                    $('#fail-dialog').modal('show');
                }
            }
        })
    });
</script>

{include file='admin/footer.tpl'}
