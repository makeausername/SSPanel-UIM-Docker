# Docker 一键安装说明

本文档说明当前 Docker Compose 一键安装方案的使用方式。安装脚本支持两种模式：

- HTTP-only 模式：适合本地测试、内网测试或暂时没有 HTTPS 的部署。
- HTTPS 模式：使用 Caddy 作为 Docker 入口网关，自动申请并续期 Let's Encrypt 证书。

本方案不使用 Certbot、Traefik 或手写证书续期脚本。

## 前置条件

推荐使用一台干净的 Linux 服务器部署。

服务器需要准备：

- 已安装 Docker。
- 已安装 Docker Compose v2 插件，可以执行 `docker compose version`。
- 已安装 Git。
- HTTP-only 模式需要开放服务器的 80 端口，或安装时填写其他可用 HTTP 端口；HTTP-only 模式不会绑定服务器的 443 端口。
- HTTPS 模式固定使用公网 80 和 443 端口，需要域名 A/AAAA 记录指向服务器 IP，并开放 80 和 443 端口。
- HTTPS 模式下，服务器上不能有其他程序占用 80/443 端口。
- 如果使用 Cloudflare，证书申请失败时建议先把 DNS 记录切换为 DNS-only，证书签发完成后再按需调整代理模式。

运行环境说明：

- Docker 镜像默认使用 PHP 8.3 FPM。项目要求 PHP 8.2+，官方手动安装文档使用 PHP 8.4；当前 Docker 方案选择 PHP 8.3 是为了在满足要求的前提下降低依赖波动。
- 镜像会安装或启用官方要求的 PHP 扩展：`bcmath`、`curl`、`fileinfo`、`gmp`、`json`、`mbstring`、`mysqli`、`openssl`、`pdo`、`pdo_mysql`、`posix`、`redis`、`sodium`、`xml`、`yaml`、`zip`，并启用推荐的 `opcache`。
- Docker Compose 默认使用 MariaDB 10.11。官方要求 MariaDB 10.11+，并推荐 MariaDB 11.8 LTS；升级到 11.8 可作为后续运行时加固步骤。
- Docker Compose 默认使用 Redis 7 Alpine，满足 Redis 7.0+ 要求。
- Docker Compose 使用 Caddy 作为公开入口，Caddy 反向代理到内部 nginx，nginx 继续负责 `public/` 静态文件和 PHP-FPM 转发。

性能调优、容器 ulimit、MariaDB/Redis 配置和 OPcache 行为请参考 [Docker 性能优化说明](docker-performance.md)。

安全说明：

- MariaDB 和 Redis 默认只在 Docker 内部网络中使用，不会把数据库端口公开到公网。
- 安装脚本会生成 `.env`、`config/.config.php`、`config/appprofile.php`，这些文件包含本地部署配置或敏感信息，不要提交到 Git。
- HTTP-only 模式下，安装脚本会在生成的 `config/.config.php` 中设置 `cookie_secure=false`，保证浏览器可以在 HTTP 下保存登录 Cookie。
- HTTPS 模式下，安装脚本会设置 `cookie_secure=true`。如果后续更换反向代理或 TLS 终止方式，应重新检查 Cookie Secure 设置。
- Docker nginx 会根据 Caddy 传入的 `X-Forwarded-Proto` 为 PHP 设置 HTTPS 状态，并在 HTTPS 模式下让 PHP session cookie 使用 Secure。
- Caddy 的证书和账号数据保存在 Docker volume 中。不要在不了解后果的情况下删除 `caddy_data` 或执行 `docker compose down -v`。
- HTTPS 模式会生成被 Git 忽略的 `docker-compose.override.yml`，用于额外发布 443 端口；HTTP-only 模式会移除安装脚本生成的该文件。

## 首次安装

克隆仓库并切换到 Docker 安装分支：

```bash
git clone https://github.com/Anankke/SSPanel-UIM.git
cd SSPanel-UIM
git checkout docker-one-click-install
```

运行安装脚本：

```bash
bash install.sh
```

脚本会依次询问以下内容：

- 是否启用 Caddy 自动 HTTPS。
- 域名，不需要填写 `http://` 或 `https://`。
- 站点名称，默认 `SSPanel-UIM`。
- HTTP 端口，默认 `80`。如果选择 HTTPS 模式，脚本会固定使用 HTTP 80 和 HTTPS 443，不再询问自定义端口。
- 数据库名，默认 `sspanel`。
- 数据库用户名，默认 `sspanel`。
- 数据库密码。
- 数据库 root 密码。
- Redis 密码，可留空。
- 管理员邮箱。
- 管理员密码。
- 时区，默认 `Asia/Shanghai`。
- `muKey`，用于节点通信。可以自定义；留空则自动生成强随机值。

示例输入：

```text
Enable HTTPS with automatic Let's Encrypt certificates through Caddy? [y/N]: y
HTTPS mode selected.
Before continuing, make sure DNS A/AAAA records point to this server and ports 80/443 are open.
Domain name, without http:// or https://: example.com
App/site name [SSPanel-UIM]: My Panel
HTTP port: 80
HTTPS port: 443
Database name [sspanel]: sspanel
Database username [sspanel]: sspanel
Database password:
Database root password:
Redis password (optional):
Admin email: admin@example.com
Admin password:
Timezone [Asia/Shanghai]: Asia/Shanghai
请输入 muKey（用于节点通信，留空则自动生成强随机值）:
```

如果选择 HTTP-only 模式，脚本会把访问地址生成为 `http://域名` 或 `http://域名:端口`，并关闭 Secure Cookie。

如果选择 HTTPS 模式，脚本会把访问地址生成为 `https://域名`，固定发布 80/443 端口，生成 `docker-compose.override.yml`，Caddy 会自动申请和续期证书，并开启 Secure Cookie。

`muKey` 会写入 `config/.config.php`，不会写入 `.env`，安装完成后也不会在终端输出明文。请妥善备份 `config/.config.php`。节点已经接入后，不要随意修改 `muKey`，除非同步更新所有节点侧配置。

如果 `.env` 已存在，脚本会询问是否备份并覆盖；如果拒绝，安装会停止。
如果 `config/.config.php` 已存在，脚本会询问是否备份并重新生成；如果拒绝，脚本不会修改该文件，并会提示当前模式需要手动确认的 `baseUrl`、`cookie_secure`，现有 `muKey` 也会保持不变。
如果 `config/appprofile.php` 已存在，脚本会询问是否备份并重新生成；如果拒绝，脚本会继续使用现有文件。

## install.sh 会做什么

`install.sh` 会执行以下步骤：

1. 检查 `docker` 和 `docker compose` 是否可用。
2. 生成 Docker Compose 使用的 `.env` 文件。
3. 如果选择 HTTPS，生成 `docker-compose.override.yml` 来发布 443 端口；如果选择 HTTP-only，移除安装脚本生成的 override 文件。
4. 根据 `config/.config.example.php` 生成 `config/.config.php`。
5. 根据 `config/appprofile.example.php` 生成 `config/appprofile.php`。
6. 构建 Docker 镜像。
7. 启动 MariaDB 和 Redis。
8. 等待 MariaDB 和 Redis 就绪。
9. 启动 app、nginx、caddy、scheduler 服务。
10. 检查容器内 `vendor/autoload.php` 是否存在，确认 Composer 依赖已安装完成。
11. 执行数据库迁移和配置导入。
12. 创建管理员账号。
13. 打印最终访问地址和常用命令。

初始化命令顺序为：

```bash
docker compose exec app php xcat Migration new
docker compose exec app php xcat Migration latest
docker compose exec app php xcat Tool importSetting
docker compose exec app php xcat Tool createAdmin "admin@example.com" "your_admin_password"
```

## 常用命令

查看服务状态：

```bash
docker compose ps
```

查看 app 日志：

```bash
docker compose logs -f app
```

查看 nginx 日志：

```bash
docker compose logs -f nginx
```

查看 Caddy 入口网关日志：

```bash
docker compose logs -f caddy
```

查看定时任务日志：

```bash
docker compose logs -f scheduler
```

手动运行一次计划任务：

```bash
docker compose exec app php xcat Cron
```

停止服务：

```bash
docker compose down
```

重新启动服务：

```bash
docker compose up -d
```

## 更新流程

更新代码：

```bash
git pull
```

重新构建镜像：

```bash
docker compose build
```

启动或重建服务：

```bash
docker compose up -d
```

如果本次更新包含数据库变更，按项目发布说明执行迁移。常用迁移命令为：

```bash
docker compose exec app php xcat Migration latest
docker compose exec app php xcat Tool importSetting
```

如果不确定是否需要迁移，先查看更新说明或在测试环境验证。

## 备份与恢复

建议至少备份以下内容：

- MariaDB 数据库或 Docker volume。
- `.env`。
- `config/.config.php`。
- `config/appprofile.php`。
- 如有需要，备份 `public/clients` 和相关存储文件。

`config/.config.php` 内包含 `key`、`muKey`、数据库密码和 Redis 密码。请把它作为敏感文件保存，不要公开。

导出数据库示例：

```bash
docker compose exec mariadb mariadb-dump -u root -p sspanel > sspanel.sql
```

恢复数据库示例：

```bash
docker compose exec -T mariadb mariadb -u root -p sspanel < sspanel.sql
```

注意：

- 不要把 `.env`、`config/.config.php`、`config/appprofile.php` 提交到 Git。
- 恢复前先确认目标数据库和 volume，避免覆盖生产数据。
- `docker compose down` 不会删除 volume。不要在不了解后果的情况下使用 `docker compose down -v`，否则会删除数据库、Redis 数据和 Caddy 证书数据。
- HTTPS 模式下请备份 Caddy 的 Docker volume，证书和 ACME 账号数据存放在 `caddy_data` / `caddy_config` 中。
- HTTPS 模式生成的 `docker-compose.override.yml` 可以随 `.env` 一起备份；它不包含密码，但控制是否发布 443 端口。

## 故障排查

### docker command not found

说明 Docker 没有安装，或当前用户找不到 `docker` 命令。

检查：

```bash
docker --version
```

安装 Docker 后，重新登录 shell，再运行安装脚本。

### docker compose not found

当前方案使用 Docker Compose v2 插件，命令是 `docker compose`，不是 `docker-compose`。

检查：

```bash
docker compose version
```

如果命令不存在，请安装 Docker Compose 插件。

### 80 或 443 端口已被占用

如果服务器已有 nginx、Apache 或其他程序占用 80 端口，HTTP-only 模式安装时可以填写其他 HTTP 端口，例如 `8080`。HTTP-only 模式不会占用 443。

HTTPS 模式需要 Caddy 使用公网 80 和 443 端口申请证书，安装脚本会固定使用这两个端口，建议先停止占用端口的服务。

也可以检查占用：

```bash
sudo ss -ltnp | grep ':80'
sudo ss -ltnp | grep ':443'
```

### Caddy 证书申请失败

先查看 Caddy 日志：

```bash
docker compose logs -f caddy
```

常见原因：

- 域名 A/AAAA 记录没有指向当前服务器。
- 服务器防火墙或云厂商安全组没有开放 80/443。
- 服务器已有其他进程占用 80/443。
- Cloudflare 代理模式影响证书验证。可以先切换为 DNS-only，证书签发完成后再按需调整。
- Let's Encrypt 对同一域名有频率限制，反复失败后需要等待一段时间再重试。

证书数据保存在 Caddy Docker volume 中，正常更新、重启或 `docker compose down` 不会删除证书。

### 数据库等待超时

先查看 MariaDB 日志：

```bash
docker compose logs -f mariadb
```

常见原因：

- 数据库密码不符合 MariaDB 初始化要求。
- 旧的 MariaDB volume 已存在，里面保存的是另一套账号密码。
- 服务器磁盘空间不足。

安装脚本不会自动删除数据库 volume。如果需要重新初始化数据库，请先确认已经备份数据。

### Redis 认证问题

如果安装时设置了 Redis 密码，`.env` 和 `config/.config.php` 中的 Redis 密码需要一致。

查看 Redis 日志：

```bash
docker compose logs -f redis
```

如果 Redis 密码留空，Redis 只在 Docker 内部网络中使用，不会公开 Redis 端口。

### 构建时 composer install 失败

查看构建输出中的 Composer 错误。常见原因：

- 服务器无法访问 Composer 镜像或 GitHub。
- PHP 扩展构建失败，例如 `gmp`、`sodium`、`redis` 或 `yaml`。
- 网络代理或 DNS 配置异常。

可以重新执行：

```bash
docker compose build
```

### 权限或缓存问题

如果页面报缓存、模板、写入权限错误，查看 app 日志：

```bash
docker compose logs -f app
```

也可以重启 app 服务：

```bash
docker compose up -d app
```

### 管理员创建失败

如果 `createAdmin` 失败，可能是管理员已经存在。先尝试登录已创建的管理员账号。

如需手动重新执行：

```bash
docker compose exec app php xcat Tool createAdmin "admin@example.com" "your_admin_password"
```

### 登录 Cookie 或 HTTPS 状态异常

HTTP-only 模式下，`config/.config.php` 应包含：

```php
$_ENV['baseUrl'] = 'http://example.com';
$_ENV['cookie_secure'] = false;
```

HTTPS 模式下，`config/.config.php` 应包含：

```php
$_ENV['baseUrl'] = 'https://example.com';
$_ENV['cookie_secure'] = true;
```

如果登录后跳转或 Cookie 行为异常，先确认 `baseUrl` 和 `cookie_secure` 与当前部署模式一致，再查看 Caddy 和 nginx 日志：

```bash
docker compose logs -f caddy
docker compose logs -f nginx
```

## 当前限制

- 当前 Docker 阶段支持 HTTP-only 和 Caddy 自动 HTTPS 两种模式。
- 暂未加入 Certbot、Traefik 或手动证书续期脚本。
- HTTPS 模式依赖真实域名、正确 DNS 和公网 80/443 端口；本阶段不支持非标准 ACME 端口。
- 仍需要在真实 Docker 主机上完成运行时测试。
- 推荐数据库目标是 MariaDB 10.11+。
- MySQL 兼容性在实际测试前不保证。

## 运行时测试清单

部署完成后，建议按以下清单测试：

- 默认中文前台。
- 英文浏览器自动识别。
- 手动切换语言。
- 管理后台保持中文。
- 登录、注册、找回密码、MFA。
- 用户首页、资料、邀请、设置页面。
- 商店、订单、账单、支付页面。
- 节点、倍率、文档页面。
- 工单页面。
- 余额或资金页面。
- 审计或检测页面。
- 前台订单和账单 DataTables 显示英文。
- 后台 DataTables 显示中文。
- 支付回调行为不变。
- 订阅 URL 行为不变。
- scheduler 定时任务正常运行。

查看 scheduler 日志：

```bash
docker compose logs -f scheduler
```
