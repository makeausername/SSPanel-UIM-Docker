# EzIPLC / SSPanel-UIM-Docker

这是一个基于 SSPanel-UIM 的 Docker 化面板仓库，面向 EzIPLC 的生产部署和日常运维做了定制。

本仓库包含 XNode 集成，用于接入 VLESS Reality Vision 节点。当前推荐的 XNode Agent 版本为 `v0.1.5`。

XNode 集成支持节点注册、配置同步、用户同步、流量上报、在线上报、心跳、运行状态、自检探针，以及外部大陆探针上报。

本文假设生产路径为 `/opt/SSPanel-UIM-Docker`，域名使用 `panel.example.com`，节点域名使用 `node1.example.com`、`node2.example.com`。实际操作时请替换为自己的域名、节点 ID 和安全密码。不要在文档、截图、工单或聊天记录里暴露真实令牌、私钥、数据库密码、节点 token、探针 token 或 Reality private key。

除特别说明外，服务器命令均为 Linux `bash` 命令。本地 Windows 只用于查看仓库状态时，请使用 PowerShell：

```powershell
git status --short --branch
```

## 1. 当前状态

面板侧 XNode 集成已经合并到默认分支 `master`。当前生产推荐的节点端版本是 `xnode-agent v0.1.5 stable`。

在完成真实节点测试、大陆探针测试、前台节点状态验证、订阅输出验证之后，本项目适合小规模生产使用。

后续变更建议以问题修复、文档完善、兼容性调整和小型 UI 改进为主，避免在生产分支上直接做大范围重构。

## 2. 架构说明

```text
User browser
  -> SSPanel / user pages
  -> V2Ray subscription

Admin
  -> node edit page
  -> XNode one-click install command

xnode-agent
  -> /node/api/v1/enroll
  -> /node/api/v1/config
  -> /node/api/v1/users
  -> /node/api/v1/traffic
  -> /node/api/v1/online
  -> /node/api/v1/runtime
  -> /node/api/v1/heartbeat
  -> /node/api/v1/probe

xnode-probe
  -> /probe/api/v1/report
```

令牌说明：

- `xne_` enroll token：一次性节点注册令牌，只用于节点首次注册。
- `xn_` node token：节点注册成功后保存在节点侧，供 `xnode-agent` 调用面板 API。
- `xnp_` probe token：外部探针令牌，供 `xnode-probe` 上报大陆探测结果。
- 这些 token 在数据库中只保存哈希值，不保存明文。
- 不要在截图、日志、README、Issue、PR 或聊天工具里暴露任何 token 明文。

## 3. 生产环境要求

推荐使用 Ubuntu 服务器。生产环境至少需要：

- Docker 和 Docker Compose。
- 域名已经解析到面板服务器。
- 如启用 HTTPS，反向代理和证书由本项目栈处理。
- MySQL/MariaDB 和 Redis 容器正常运行。
- 服务器可以访问 GitHub。
- 节点服务器可以访问 GitHub release assets，用于安装 `xnode-agent`。

部署前检查：

```bash
uname -a
docker --version
docker compose version
git --version
curl -I https://github.com
```

## 4. 首次部署

新服务器首次部署可以使用通用路径 `/opt/SSPanel-UIM-Docker`：

```bash
cd /opt
git clone https://github.com/makeausername/SSPanel-UIM-Docker.git
cd /opt/SSPanel-UIM-Docker
git checkout master
```

如果服务器已经部署过，不要重新 clone。已有部署应在原目录中拉取更新并重建服务：

```bash
cd /opt/SSPanel-UIM-Docker
git pull
docker compose up -d --build
docker compose ps
```

首次生产启动：

```bash
cd /opt/SSPanel-UIM-Docker

docker compose up -d --build
docker compose ps
```

## 5. 环境配置

不要提交 `.env`、`config/.config.php` 或任何包含密钥的配置文件。

生产配置应至少确认：

- 面板域名和 `baseUrl` 正确。
- 数据库密码、Redis 密码足够强。
- 支付配置、Telegram 配置等私密信息只保存在服务器。
- 使用项目现有配置模式，不在 README 或脚本中写死真实密钥。

查看容器状态和最近日志：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose ps
docker compose logs --since=10m app nginx caddy 2>/dev/null || true
```

## 6. 数据库迁移

拉取新 `master` 代码后，执行迁移：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose exec -T app php xcat Migration latest
```

该命令会创建或更新 XNode 相关表：

- `node_tokens`
- `node_runtimes`
- `node_report_receipts`
- `node_probe_results`
- `node_probe_states`
- `node_profiles`

重大生产更新前请先备份数据库。迁移失败时不要继续升级节点，先查看 `app` 日志和数据库日志。

## 7. 创建管理员账号

本项目使用 `xcat` 工具创建管理员账号。交互式创建：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose exec -it app php xcat Tool createAdmin
```

当前版本支持邮箱和密码参数时，也可以使用非交互方式：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose exec -T app php xcat Tool createAdmin admin@example.com "CHANGE_ME_STRONG_PASSWORD"
```

如果你的当前版本不支持非交互参数，请使用交互式命令。

## 8. 创建和配置节点

登录后台后进入节点管理页面创建节点：

```text
https://panel.example.com/admin/node
```

编辑节点示例：

```text
https://panel.example.com/admin/node/1/edit
```

节点配置建议：

- 节点类型选择 XNode / VLESS Reality Vision 对应的类型。
- `server` 填节点域名，例如 `node1.example.com`。
- 节点等级、用户组、倍率按业务需要设置。
- 节点应启用并展示给符合条件的用户。
- 节点域名应解析到节点服务器，不是面板服务器。

## 9. 生成 XNode 节点安装命令

推荐方式是在后台节点编辑页生成命令：

1. 打开后台节点编辑页。
2. 找到 XNode 安装命令按钮或区域。
3. 生成一次性安装命令。
4. 只在对应节点服务器上执行该命令。

生成的命令会包含：

- 面板 URL。
- 节点 ID。
- 节点域名。
- 一次性 enroll token，格式为 `xne_...`。
- Agent 版本 `v0.1.5`。

也可以在面板服务器用 CLI 生成 enroll token：

```bash
cd /opt/SSPanel-UIM-Docker

docker compose exec -T app php xcat Tool generateXNodeEnrollToken 1 600
```

注意事项：

- token 明文只显示一次。
- 不要截图或转发 token。
- token 应在 TTL 内使用。
- 节点注册成功后，enroll token 会被标记为已使用。

## 10. 安装 xnode-agent 节点

以下命令在节点服务器执行，不是在面板服务器执行。请替换面板 URL、节点 ID、节点域名和 enroll token。

通用安装命令：

```bash
curl -fsSL https://raw.githubusercontent.com/makeausername/xnode-agent/main/scripts/install.sh | bash -s -- \
  --panel-url "https://panel.example.com" \
  --node-id "1" \
  --node-domain "node1.example.com" \
  --enroll-token "xne_xxx" \
  --version "v0.1.5"
```

低资源节点推荐命令：

```bash
curl -fsSL https://raw.githubusercontent.com/makeausername/xnode-agent/main/scripts/install.sh | bash -s -- \
  --panel-url "https://panel.example.com" \
  --node-id "1" \
  --node-domain "node1.example.com" \
  --enroll-token "xne_xxx" \
  --version "v0.1.5" \
  --online-interval-sec 120 \
  --access-log-tail-bytes 1048576 \
  --access-log-max-lines 5000 \
  --probe-interval-sec 300
```

安装完成后，节点侧应保存 `xn_` node token，并由 `xnode-agent` 自动使用。不要手工把 `xn_` token 写入面板或公开日志。

## 11. 验证节点状态

在节点服务器执行：

```bash
systemctl status xnode-agent --no-pager
systemctl status xnode-xray --no-pager
/usr/local/bin/xnode --version
/usr/local/bin/xnode --check
/usr/local/bin/xnode --metrics
journalctl -u xnode-agent.service -n 100 --no-pager -o cat
```

在面板服务器检查数据库状态：

```bash
cd /opt/SSPanel-UIM-Docker

docker compose exec -T app php -r '
require_once "vendor/autoload.php";
require_once "config/.config.php";
require_once "config/appprofile.php";
require_once "app/predefine.php";

App\Services\Boot::setTime();
App\Services\Boot::bootDb();

echo json_encode([
    "nodes" => App\Services\DB::select("
        SELECT id, name, server, node_heartbeat, FROM_UNIXTIME(node_heartbeat) AS heartbeat_time, type
        FROM node
        WHERE id IN (1,2)
        ORDER BY id
    "),
    "runtime" => App\Services\DB::select("
        SELECT node_id, agent_version, core_version, state, last_error, last_seen, FROM_UNIXTIME(last_seen) AS last_seen_time
        FROM node_runtimes
        WHERE node_id IN (1,2)
        ORDER BY node_id
    "),
    "receipts" => App\Services\DB::select("
        SELECT node_id, report_type, period_start, period_end, created_at
        FROM node_report_receipts
        WHERE node_id IN (1,2)
        ORDER BY id DESC
        LIMIT 30
    ")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
'
```

预期结果：

- `node.node_heartbeat` 是近期时间。
- `node_runtimes.last_seen` 是近期时间。
- `node_report_receipts` 有 `heartbeat`、`online`、`traffic` 等记录。
- 当前台节点心跳在 600 秒内时，前台节点状态应为绿色。

## 12. 订阅验证

用户订阅在节点运行状态包含 `public_key` 和 `short_ids` 后，应输出 VLESS Reality 链接。

用户侧需要满足：

- 用户有有效 UUID。
- 用户未被封禁。
- 用户等级满足节点等级要求。
- 用户组匹配节点组。

订阅 URL 格式：

```text
https://panel.example.com/sub/<token>/v2ray
```

浏览器不能把节点域名当成普通网站直接打开。VLESS Reality 节点不是普通 HTTP 网站端点，客户端应使用 VLESS Reality 配置连接。

## 13. 外部大陆探针 xnode-probe

`xnode-probe` 建议运行在单独的大陆 VPS。它使用 `xnp_` probe token 上报到面板：

```text
/probe/api/v1/report
```

外部探针不使用 `xne_` enroll token，也不使用 `xn_` node token。

在面板服务器生成 probe token：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose exec -T app php xcat Tool generateXNodeProbeToken
```

在探针 VPS 安装：

```bash
curl -fsSL https://raw.githubusercontent.com/makeausername/xnode-agent/main/scripts/install-probe.sh | bash -s -- \
  --panel-url "https://panel.example.com" \
  --probe-token "xnp_xxx" \
  --probe-region "cn" \
  --probe-provider "aliyun" \
  --probe-location "cn-mainland-1" \
  --target "1:node1.example.com:443" \
  --target "2:node2.example.com:443" \
  --interval-sec 300 \
  --version "v0.1.5"
```

一次性验证：

```bash
xnode-probe --once \
  --panel-url "https://panel.example.com" \
  --probe-token "xnp_xxx" \
  --probe-region "cn" \
  --probe-provider "aliyun" \
  --probe-location "cn-mainland-1" \
  --target "1:node1.example.com:443" \
  --target "2:node2.example.com:443" \
  --json
```

## 14. 探针 DB 验证

在面板服务器执行：

```bash
cd /opt/SSPanel-UIM-Docker

docker compose exec -T app php -r '
require_once "vendor/autoload.php";
require_once "config/.config.php";
require_once "config/appprofile.php";
require_once "app/predefine.php";

App\Services\Boot::setTime();
App\Services\Boot::bootDb();

echo json_encode([
    "probe_states" => App\Services\DB::select("
        SELECT node_id, status, previous_status, probe_region, probe_provider, probe_location, probe_type, target_host, target_port, latency_ms, error, last_checked_at, last_changed_at
        FROM node_probe_states
        WHERE node_id IN (1,2)
        ORDER BY node_id
    "),
    "recent_probe_results" => App\Services\DB::select("
        SELECT node_id, probe_region, probe_provider, probe_location, probe_type, target_host, target_port, status, latency_ms, error, checked_at, created_at
        FROM node_probe_results
        WHERE node_id IN (1,2)
        ORDER BY id DESC
        LIMIT 20
    ")
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
'
```

预期结果：

- `node_probe_results` 有近期记录。
- `node_probe_states` 被更新。
- `status = ok` 表示探针侧可达。
- `suspected_blocked` 表示大陆探针怀疑该节点被阻断，不一定代表 `xnode-agent` 已停止。

## 15. 常用运维命令

面板服务器：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose ps
docker compose logs -f app
docker compose logs -f nginx
docker compose logs --since=10m app nginx caddy 2>/dev/null | grep -Ei "502|bad gateway|connect\(\) failed|connection refused|error|exception|fatal|panic|SQLSTATE|undefined" || true
```

节点服务器：

```bash
systemctl status xnode-agent --no-pager
systemctl status xnode-xray --no-pager
journalctl -u xnode-agent.service -f -o cat
/usr/local/bin/xnode --metrics
/usr/local/bin/xnode --probe-once
```

探针 VPS：

```bash
systemctl status xnode-probe.timer --no-pager
systemctl status xnode-probe.service --no-pager
journalctl -u xnode-probe.service -n 100 --no-pager -o cat
```

## 16. 升级流程

面板升级：

```bash
cd /opt/SSPanel-UIM-Docker

git fetch origin master
git checkout -B master origin/master
git log --oneline -1

docker compose up -d --build app scheduler
docker compose restart nginx caddy
docker compose exec -T app php xcat Migration latest
docker compose ps
```

节点升级：

使用后台节点编辑页生成的新安装命令，或手工运行 `install.sh` 并指定版本：

```bash
curl -fsSL https://raw.githubusercontent.com/makeausername/xnode-agent/main/scripts/install.sh | bash -s -- \
  --panel-url "https://panel.example.com" \
  --node-id "1" \
  --node-domain "node1.example.com" \
  --enroll-token "xne_xxx" \
  --version "v0.1.5"
```

升级后重新执行节点状态和订阅验证。

## 17. 回滚流程

重大升级前先备份数据库。代码可以回滚到旧 commit，但数据库迁移不应随意回滚，除非你有明确备份和恢复计划。

安全示例：

```bash
cd /opt/SSPanel-UIM-Docker
git log --oneline -10
git checkout -B rollback <COMMIT_SHA>
docker compose up -d --build app scheduler
docker compose restart nginx caddy
```

节点端如需回滚，可以重新安装上一个确认可用的 xnode-agent release 版本。

注意：

- 不要在没有备份的情况下回滚数据库。
- 严重数据库问题优先恢复备份。
- 回滚后重新验证管理员登录、订阅输出、节点心跳、探针上报。

## 18. 备份建议

建议定期备份：

- 数据库 dump。
- `.env`。
- `config/.config.php`。
- `config/appprofile.php`。
- 用户上传资源和业务需要保留的本地文件。

密钥配置应私密备份，不要提交到 Git。

示例：

```bash
cd /opt/SSPanel-UIM-Docker
mkdir -p /opt/backups/sspanel

docker compose exec -T mariadb sh -lc 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > /opt/backups/sspanel/sspanel-$(date +%F-%H%M%S).sql
```

如果你的实际服务名、数据库名、用户名或密码变量不同，请按当前部署调整。部分 MySQL 镜像可能使用 `mysqldump` 而不是 `mariadb-dump`。

## 19. 常见问题

### 节点前台显示黄色，但节点实际可用

检查 `node.node_heartbeat` 是否近期更新。前台状态依赖心跳时间，首次注册或刚重启后可能需要等待下一次心跳。

### `node_report_receipts` 没有 traffic

检查是否有真实用户流量，查看 `xnode-agent` 日志，确认节点侧保存的是有效 `xn_` node token。

### 订阅没有 VLESS 链接

检查 `node_runtimes.public_key` 和 `node_runtimes.short_ids_json` 是否存在且格式正确；同时检查用户 UUID、用户等级、用户组和节点启用状态。

### `xnode-probe` 返回 HTTP 401

检查是否使用 `xnp_` probe token。数据库中对应 token 应为 `token_type = probe`、`node_id = 0`，且未过期、未撤销。

### `xnode-probe` 返回 HTTP 500

查看面板日志，重点搜索 `ProbeApiV1Controller`、`NodeProbeService`、`SQLSTATE`：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose logs --since=10m app | grep -Ei "ProbeApiV1Controller|NodeProbeService|SQLSTATE|exception|fatal|error" || true
```

### 浏览器无法打开节点域名

VLESS Reality 节点不是普通网站端点，浏览器不能直接访问节点域名来判断节点是否可用。请使用客户端订阅或节点探针验证。

### 面板 502

先查看容器状态和入口日志：

```bash
cd /opt/SSPanel-UIM-Docker
docker compose ps
docker compose logs --since=10m app nginx caddy 2>/dev/null || true
```

重点检查 `app` 是否启动、`nginx` 是否能连接 `app:9000`、`caddy` 是否正确转发。

## 20. 安全注意事项

- 不要在截图、聊天记录或工单中贴出 token。
- 不要提交 `.env`、带真实密钥的 `config/.config.php`、node token、probe token、Reality private key 或 `xray.json`。
- `xne_` 一次性 enroll token 应短期有效，用完即失效。
- `xnp_` probe token 泄露后应立即轮换。
- 不要在没有强密码、MFA 或访问策略的情况下暴露后台管理入口。
- 备份应加密或保存在私密位置。
- 节点服务器和探针 VPS 应只保存自己需要的最小凭据。

## 21. 生产验收清单

上线前逐项确认：

- 面板容器健康。
- `Migration latest` 已完成。
- 管理员可以登录。
- 节点编辑页可以生成 `v0.1.5` 安装命令。
- `xnode-agent` 注册成功。
- `xnode-xray` 处于 active 状态。
- `node_runtimes.last_seen` 持续更新。
- `node.node_heartbeat` 持续更新。
- 前台节点状态为绿色。
- `/sub/<token>/v2ray` 输出 VLESS Reality 链接。
- `xnode-probe` 上报被面板接受。
- `node_probe_results` 和 `node_probe_states` 持续更新。
- 没有持续的 `500`、`502`、`SQLSTATE` 错误。
- 所有 token、私钥和配置密钥没有泄露。
