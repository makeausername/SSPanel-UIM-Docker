# Docker 一键安装说明

本文档说明当前 Docker Compose 一键安装方案的使用方式。当前阶段为 HTTP 部署文档，不包含 SSL、Caddy、Traefik、Certbot 或自动 HTTPS。

## 前置条件

推荐使用一台干净的 Linux 服务器部署。

服务器需要准备：

- 已安装 Docker。
- 已安装 Docker Compose v2 插件，可以执行 `docker compose version`。
- 已安装 Git。
- HTTP 阶段需要开放服务器的 80 端口，或安装时填写其他可用 HTTP 端口。
- 如果使用域名访问，建议先把域名 A 记录解析到服务器 IP。HTTP 阶段域名不是强制要求，也可以先用服务器 IP 测试。

运行环境说明：

- Docker 镜像默认使用 PHP 8.3 FPM。项目要求 PHP 8.2+，官方手动安装文档使用 PHP 8.4；当前 Docker 方案选择 PHP 8.3 是为了在满足要求的前提下降低依赖波动。
- 镜像会安装或启用官方要求的 PHP 扩展：`bcmath`、`curl`、`fileinfo`、`gmp`、`json`、`mbstring`、`mysqli`、`openssl`、`pdo`、`pdo_mysql`、`posix`、`redis`、`sodium`、`xml`、`yaml`、`zip`，并启用推荐的 `opcache`。
- Docker Compose 默认使用 MariaDB 10.11。官方要求 MariaDB 10.11+，并推荐 MariaDB 11.8 LTS；升级到 11.8 可作为后续运行时加固步骤。
- Docker Compose 默认使用 Redis 7 Alpine，满足 Redis 7.0+ 要求。

安全说明：

- MariaDB 和 Redis 默认只在 Docker 内部网络中使用，不会把数据库端口公开到公网。
- 安装脚本会生成 `.env`、`config/.config.php`、`config/appprofile.php`，这些文件包含本地部署配置或敏感信息，不要提交到 Git。
- 当前 Docker 阶段是 HTTP-only，安装脚本会在生成的 `config/.config.php` 中关闭 Secure Cookie，保证浏览器可以在 HTTP 下保存登录 Cookie。后续启用 HTTPS 或反向代理后，应重新检查并开启 Cookie Secure 设置。

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

- 域名，不需要填写 `http://` 或 `https://`。
- 站点名称，默认 `SSPanel-UIM`。
- HTTP 端口，默认 `80`。
- 数据库名，默认 `sspanel`。
- 数据库用户名，默认 `sspanel`。
- 数据库密码。
- 数据库 root 密码。
- Redis 密码，可留空。
- 管理员邮箱。
- 管理员密码。
- 时区，默认 `Asia/Shanghai`。

示例输入：

```text
Domain name, without http:// or https://: example.com
App/site name [SSPanel-UIM]: My Panel
HTTP port [80]: 80
Database name [sspanel]: sspanel
Database username [sspanel]: sspanel
Database password:
Database root password:
Redis password (optional):
Admin email: admin@example.com
Admin password:
Timezone [Asia/Shanghai]: Asia/Shanghai
```

## install.sh 会做什么

`install.sh` 会执行以下步骤：

1. 检查 `docker` 和 `docker compose` 是否可用。
2. 生成 Docker Compose 使用的 `.env` 文件。
3. 根据 `config/.config.example.php` 生成 `config/.config.php`。
4. 根据 `config/appprofile.example.php` 生成 `config/appprofile.php`。
5. 构建 Docker 镜像。
6. 启动 MariaDB 和 Redis。
7. 等待 MariaDB 和 Redis 就绪。
8. 启动 app、nginx、scheduler 服务。
9. 检查容器内 `vendor/autoload.php` 是否存在，确认 Composer 依赖已安装完成。
10. 执行数据库迁移和配置导入。
11. 创建管理员账号。
12. 打印最终访问地址和常用命令。

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
- `docker compose down` 不会删除 volume。不要在不了解后果的情况下使用 `docker compose down -v`。

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

### 80 端口已被占用

如果服务器已有 nginx、Apache 或其他程序占用 80 端口，安装时可以填写其他 HTTP 端口，例如 `8080`。

也可以检查占用：

```bash
sudo ss -ltnp | grep ':80'
```

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

## 当前限制

- 当前 Docker 阶段仅说明 HTTP 部署。
- 暂无自动 SSL。
- Caddy/HTTPS 会在后续阶段加入。
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
