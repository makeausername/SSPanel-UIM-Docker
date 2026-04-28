# SSPanel-UIM Docker 一键部署版

这是一个基于 SSPanel-UIM 改造的自用 Docker 部署版本。

本版本重点加入：

- Docker Compose 一键部署
- Caddy 自动 HTTPS / Let’s Encrypt 自动续签
- MariaDB
- Redis
- Nginx
- PHP-FPM
- Scheduler 定时任务
- 前台中英文切换
- 后台保持中文
- Docker 性能优化配置
- 交互式安装向导
- 可自定义 muKey，方便后期节点对接

> 本仓库为自用版本，不是 SSPanel-UIM 官方仓库。

---

## 功能特点

### Docker 一键安装

通过：

```bash
bash install.sh
```

即可完成：

```text
生成 .env
生成 config/.config.php
生成 config/appprofile.php
构建 Docker 镜像
启动 MariaDB
启动 Redis
启动 PHP-FPM app
启动 Nginx
启动 Caddy
启动 Scheduler
初始化数据库
创建管理员账号
```

---

### HTTPS 自动证书

本版本使用 Caddy 作为入口网关。

HTTPS 模式下：

```text
Caddy 自动申请 Let’s Encrypt 证书
Caddy 自动续签证书
证书数据持久化保存
Nginx 只作为内部服务
app:9000 不对外暴露
MariaDB / Redis 不对外暴露
```

---

### HTTP / HTTPS 两种模式

安装时可以选择：

```text
HTTP 测试模式
HTTPS 正式模式
```

HTTP 模式：

```text
只占用 80 端口
不占用 443 端口
cookie_secure=false
baseUrl=http://...
```

HTTPS 模式：

```text
占用 80 / 443 端口
自动申请 SSL
cookie_secure=true
baseUrl=https://...
```

---

### 前台中英文切换

前台用户区域支持：

```text
简体中文
English
```

登录页和用户中心均提供语言切换入口。

后台 `/admin` 保持中文。

---

## 环境要求

推荐系统：

```text
Ubuntu 22.04
Ubuntu 24.04
Debian 12
```

推荐配置：

```text
CPU：2 核以上
内存：2GB 以上
硬盘：20GB 以上
系统：64 位 Linux
```

正式 HTTPS 部署需要：

```text
域名已经解析到服务器 IP
服务器开放 80 端口
服务器开放 443 端口
没有其他服务占用 80 / 443
```

如果使用 Cloudflare，首次签发证书建议：

```text
DNS only
不要开启小黄云代理
证书签发成功后再根据需要调整
```

---

## 一、安装 Docker

如果服务器还没有 Docker，可以先执行：

```bash
sudo -i
```

```bash
apt update
```

```bash
apt install -y ca-certificates curl gnupg git lsb-release ufw
```

卸载可能冲突的旧包：

```bash
for pkg in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do
  apt remove -y "$pkg" 2>/dev/null || true
done
```

添加 Docker 官方源：

```bash
install -m 0755 -d /etc/apt/keyrings
```

```bash
curl -fsSL "https://download.docker.com/linux/$(. /etc/os-release && echo "$ID")/gpg" -o /etc/apt/keyrings/docker.asc
```

```bash
chmod a+r /etc/apt/keyrings/docker.asc
```

```bash
cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/$(. /etc/os-release && echo "$ID")
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
```

```bash
apt update
```

安装 Docker：

```bash
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

启动 Docker：

```bash
systemctl enable --now docker
```

检查 Docker：

```bash
docker version
```

```bash
docker compose version
```

```bash
docker run --rm hello-world
```

---

## 二、开放防火墙端口

如果使用 UFW：

```bash
ufw allow 22/tcp
```

```bash
ufw allow 80/tcp
```

```bash
ufw allow 443/tcp
```

```bash
ufw --force enable
```

```bash
ufw status
```

同时还需要在服务器厂商后台安全组开放：

```text
22
80
443
```

---

## 三、克隆仓库

如果仓库是私有仓库，克隆时需要 GitHub 认证。

HTTPS 克隆：

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_PRIVATE_REPO.git
```

进入目录：

```bash
cd YOUR_PRIVATE_REPO
```

如果使用 SSH：

```bash
git clone git@github.com:YOUR_USERNAME/YOUR_PRIVATE_REPO.git
```

进入目录：

```bash
cd YOUR_PRIVATE_REPO
```

查看当前分支：

```bash
git status --short --branch
```

---

## 四、运行安装向导

执行：

```bash
chmod +x install.sh
```

```bash
bash install.sh
```

安装向导会依次询问：

```text
部署模式
域名
站点名称
HTTP / HTTPS 配置
数据库名
数据库用户
数据库密码
数据库 root 密码
Redis 密码
管理员邮箱
管理员密码
muKey
时区
```

推荐正式环境选择：

```text
HTTPS 正式模式
```

HTTPS 模式下请确保：

```text
域名 A 记录已经指向服务器 IP
80 端口开放
443 端口开放
没有其他服务占用 80 / 443
```

---

## 五、安装完成后检查

查看容器状态：

```bash
docker compose ps
```

理想状态：

```text
app        Up
nginx      Up
caddy      Up
mariadb    Up healthy
redis      Up healthy
scheduler  Up
```

检查 Compose 配置：

```bash
docker compose config
```

检查 Nginx 配置：

```bash
docker compose exec nginx nginx -t
```

检查 PHP-FPM 配置：

```bash
docker compose exec app php-fpm -t
```

---

## 六、访问网站

浏览器打开：

```text
https://你的域名
```

常用入口：

```text
https://你的域名/auth/login
https://你的域名/user
https://你的域名/admin
```

---

## 七、测试 HTTPS 登录

把邮箱替换成你的管理员邮箱：

```bash
read -rsp "Admin password: " ADMIN_PASS; echo
```

```bash
curl -sS -i \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode 'email=你的管理员邮箱' \
  --data-urlencode "password=${ADMIN_PASS}" \
  --data-urlencode 'remember_me=true' \
  https://你的域名/auth/login
```

```bash
unset ADMIN_PASS
```

成功时应看到：

```text
hx-redirect: /user
```

HTTPS 模式下 Cookie 应包含：

```text
secure; HttpOnly
```

---

## 八、常用命令

进入项目目录：

```bash
cd ~/YOUR_PRIVATE_REPO
```

查看容器状态：

```bash
docker compose ps
```

查看所有容器，包括已停止：

```bash
docker compose ps -a
```

查看 app 日志：

```bash
docker compose logs -f app
```

查看 nginx 日志：

```bash
docker compose logs -f nginx
```

查看 caddy 日志：

```bash
docker compose logs -f caddy
```

查看 scheduler 日志：

```bash
docker compose logs -f scheduler
```

查看 MariaDB 日志：

```bash
docker compose logs -f mariadb
```

查看 Redis 日志：

```bash
docker compose logs -f redis
```

查看最近 100 行 app 日志：

```bash
docker compose logs --tail=100 app
```

查看最近 100 行 nginx 日志：

```bash
docker compose logs --tail=100 nginx
```

查看最近 100 行 caddy 日志：

```bash
docker compose logs --tail=100 caddy
```

重启全部服务：

```bash
docker compose restart
```

重启 app：

```bash
docker compose restart app
```

重启 nginx：

```bash
docker compose restart nginx
```

重启 caddy：

```bash
docker compose restart caddy
```

重启 scheduler：

```bash
docker compose restart scheduler
```

停止服务：

```bash
docker compose down
```

启动服务：

```bash
docker compose up -d
```

重新构建并启动：

```bash
docker compose up -d --build
```

强制重新构建 app 镜像：

```bash
docker compose build --no-cache app
```

```bash
docker compose up -d
```

查看实时资源占用：

```bash
docker stats
```

---

## 九、更新代码

拉取最新代码：

```bash
git pull
```

如果只改了文档或安装脚本，通常不需要重启容器。

如果改了 Docker / PHP / Nginx / Caddy 配置，建议：

```bash
docker compose config
```

```bash
docker compose down
```

```bash
docker compose up -d --build
```

```bash
docker compose ps
```

---

## 十、修改配置后如何生效

### 修改 .env

如果修改了：

```text
.env
```

建议执行：

```bash
docker compose config
```

```bash
docker compose down
```

```bash
docker compose up -d
```

### 修改 config/.config.php

如果修改了：

```text
config/.config.php
```

建议执行：

```bash
docker compose restart app scheduler nginx
```

### 修改 config/appprofile.php

如果修改了：

```text
config/appprofile.php
```

建议执行：

```bash
docker compose restart app scheduler nginx
```

### 修改 Caddyfile

如果修改了：

```text
docker/caddy/Caddyfile
```

建议执行：

```bash
docker compose restart caddy
```

### 修改 Nginx 配置

如果修改了：

```text
docker/nginx/default.conf
```

先检查：

```bash
docker compose exec nginx nginx -t
```

再重启：

```bash
docker compose restart nginx
```

### 修改 PHP 配置

如果修改了：

```text
docker/php/php.ini
docker/php/opcache.ini
docker/php/www.conf
Dockerfile
```

建议重新构建：

```bash
docker compose build --no-cache app
```

```bash
docker compose up -d
```

### 修改 MariaDB 配置

如果修改了：

```text
docker/mariadb/99-sspanel.cnf
```

执行：

```bash
docker compose restart mariadb
```

### 修改 Redis 配置

如果修改了：

```text
docker/redis/redis.conf
```

执行：

```bash
docker compose restart redis
```

---

## 十一、备份

安装完成后，建议立刻备份：

```text
.env
config/.config.php
config/appprofile.php
数据库
```

创建备份目录：

```bash
mkdir -p ~/sspanel-backup
```

备份配置文件：

```bash
cp .env ~/sspanel-backup/.env.backup
```

```bash
cp config/.config.php ~/sspanel-backup/.config.php.backup
```

```bash
cp config/appprofile.php ~/sspanel-backup/appprofile.php.backup
```

备份数据库：

```bash
docker compose exec -T mariadb sh -lc 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > ~/sspanel-backup/sspanel.sql
```

查看备份：

```bash
ls -lh ~/sspanel-backup
```

---

## 十二、恢复数据库

先确认备份文件存在：

```bash
ls -lh ~/sspanel-backup/sspanel.sql
```

恢复数据库：

```bash
docker compose exec -T mariadb sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < ~/sspanel-backup/sspanel.sql
```

恢复后重启服务：

```bash
docker compose restart app scheduler
```

---

## 十三、重要文件说明

```text
.env
```

Docker 环境变量文件，包含数据库密码、Redis 配置、Caddy 配置等。

```text
config/.config.php
```

SSPanel 核心配置文件，包含 baseUrl、muKey、数据库配置等。

```text
config/appprofile.php
```

应用配置文件。

```text
docker-compose.yml
```

Docker Compose 主配置。

```text
docker-compose.override.yml
```

HTTPS 模式下由安装脚本生成，用于绑定 443 端口。该文件默认被 Git 忽略。

```text
docker/caddy/Caddyfile
```

Caddy HTTPS 反向代理配置。

```text
docker/nginx/default.conf
```

Nginx 内部 Web 配置。

```text
docker/php/php.ini
```

PHP 基础配置。

```text
docker/php/opcache.ini
```

OPcache 配置。

```text
docker/php/www.conf
```

PHP-FPM 进程池配置。

```text
docker/mariadb/99-sspanel.cnf
```

MariaDB 性能配置。

```text
docker/redis/redis.conf
```

Redis 持久化与性能配置。

---

## 十四、muKey 注意事项

`muKey` 用于后期节点通信。

安装时可以：

```text
手动输入 muKey
留空自动生成
```

注意：

```text
muKey 必须妥善保存
muKey 位于 config/.config.php
节点接入后不要随便修改 muKey
如果修改 muKey，所有节点侧配置也必须同步修改
```

---

## 十五、HTTPS 注意事项

HTTPS 模式需要：

```text
域名解析正确
80 端口开放
443 端口开放
Caddy 正常运行
没有其他服务占用 80 / 443
```

查看 Caddy 日志：

```bash
docker compose logs -f caddy
```

测试 HTTPS：

```bash
curl -I https://你的域名
```

正常应看到：

```text
HTTP/2 200
via: 1.1 Caddy
```

---

## 十六、不要执行的危险命令

不要随便执行：

```bash
docker compose down -v
```

这个命令会删除 Docker volume，可能导致：

```text
数据库丢失
Redis 数据丢失
Caddy 证书数据丢失
需要重新初始化
```

正常停止服务只需要：

```bash
docker compose down
```

---

## 十七、常见问题

### 1. HTTPS 证书申请失败

检查 DNS：

```bash
ping 你的域名
```

检查 80 / 443：

```bash
ss -lntp
```

查看 Caddy 日志：

```bash
docker compose logs -f caddy
```

确认：

```text
域名解析到当前服务器
80 端口开放
443 端口开放
Cloudflare 暂时使用 DNS only
没有其他服务占用端口
```

---

### 2. 登录后没有反应

查看 app 日志：

```bash
docker compose logs --tail=100 app
```

查看 nginx 日志：

```bash
docker compose logs --tail=100 nginx
```

测试登录接口：

```bash
read -rsp "Admin password: " ADMIN_PASS; echo
```

```bash
curl -sS -i \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'X-Requested-With: XMLHttpRequest' \
  --data-urlencode 'email=你的管理员邮箱' \
  --data-urlencode "password=${ADMIN_PASS}" \
  --data-urlencode 'remember_me=true' \
  https://你的域名/auth/login
```

```bash
unset ADMIN_PASS
```

成功应看到：

```text
hx-redirect: /user
```

---

### 3. 数据库连接失败

查看 MariaDB 状态：

```bash
docker compose ps
```

查看 MariaDB 日志：

```bash
docker compose logs --tail=100 mariadb
```

测试数据库表：

```bash
docker compose exec -T mariadb sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "SHOW TABLES;"'
```

---

### 4. Scheduler 报错

查看 Scheduler 日志：

```bash
docker compose logs --tail=100 scheduler
```

手动执行 Cron：

```bash
docker compose exec scheduler php xcat Cron
```

---

### 5. 修改代码后页面没有变化

本版本启用了 OPcache 生产优化：

```text
opcache.validate_timestamps=0
```

修改代码后建议重建并重启：

```bash
docker compose build --no-cache app
```

```bash
docker compose up -d
```

---

## 十八、性能优化说明

本版本已包含 Docker 性能优化：

```text
PHP-FPM 进程池配置
PHP 运行参数
OPcache
MariaDB 参数
Redis RDB / AOF 持久化
Docker nofile ulimits
```

查看资源占用：

```bash
docker stats
```

查看 PHP-FPM 配置是否正常：

```bash
docker compose exec app php-fpm -t
```

如果服务器内存较小，可以适当降低：

```text
PHP_FPM_MAX_CHILDREN
PHP_FPM_START_SERVERS
PHP_FPM_MIN_SPARE_SERVERS
PHP_FPM_MAX_SPARE_SERVERS
```

这些参数通常在 `.env` 中配置。

---

## 十九、私有仓库认证说明

如果本仓库是 GitHub Private 仓库，clone 时需要认证。

HTTPS clone 时：

```bash
git clone https://github.com/YOUR_USERNAME/YOUR_PRIVATE_REPO.git
```

如果提示：

```text
Username:
Password:
```

请填写：

```text
Username：你的 GitHub 用户名
Password：GitHub Personal Access Token，不是 GitHub 登录密码
```

也可以使用 SSH：

```bash
git clone git@github.com:YOUR_USERNAME/YOUR_PRIVATE_REPO.git
```

---

## 二十、升级建议

升级前先备份：

```bash
mkdir -p ~/sspanel-backup-before-update
```

```bash
cp .env ~/sspanel-backup-before-update/.env.backup
```

```bash
cp config/.config.php ~/sspanel-backup-before-update/.config.php.backup
```

```bash
cp config/appprofile.php ~/sspanel-backup-before-update/appprofile.php.backup
```

拉取代码：

```bash
git pull
```

检查 Compose：

```bash
docker compose config
```

重建并启动：

```bash
docker compose down
```

```bash
docker compose up -d --build
```

检查状态：

```bash
docker compose ps
```

---

## 二十一、免责声明

本项目仅供自用部署、学习和测试。

请自行确保：

```text
服务器安全
数据备份
域名配置
合规使用
密码安全
数据库安全
```

不要在生产环境中使用弱密码。

不要公开 `.env`、`config/.config.php`、`config/appprofile.php`。

不要公开 muKey。
