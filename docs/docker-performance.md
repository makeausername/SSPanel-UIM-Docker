# Docker 性能优化说明

本文档说明 Docker Compose 部署方案中已经内置的性能配置，以及在不同服务器规格下可以手动调整的参数。

这些配置只作用于 Docker 服务和容器内配置。不要直接照搬传统 Linux 安装文档去修改宿主机的 `/etc/sysctl.conf`、`/etc/security/limits.conf` 或系统 logrotate 配置，除非你明确知道这些设置会影响整台服务器。

## 已内置的优化

- PHP-FPM 使用可通过环境变量调整的进程池配置。
- PHP 运行时默认限制为 `memory_limit=256M`、上传和 POST 限制为 `50M`、最长执行时间为 `300` 秒。
- OPcache 已启用，默认按生产环境优化。
- MariaDB 使用 UTF-8、较高连接数和较短空闲超时。
- Redis 启用 RDB 快照和 AOF everysec 持久化。
- app、scheduler、nginx、caddy、mariadb、redis 服务均设置 `nofile` ulimit 为 `65535`。

## PHP-FPM 调优

Docker 镜像内置 `docker/php/www.conf` 模板。容器启动时会读取 `.env` 中的变量并生成 PHP-FPM pool 配置。

可调整变量如下：

```env
PHP_FPM_PM=dynamic
PHP_FPM_MAX_CHILDREN=20
PHP_FPM_START_SERVERS=4
PHP_FPM_MIN_SPARE_SERVERS=2
PHP_FPM_MAX_SPARE_SERVERS=8
PHP_FPM_MAX_REQUESTS=500
```

默认值偏保守，适合小型 VPS。官方传统部署文档中的思路是按内存估算 `pm.max_children`：

```text
pm.max_children ≈ 可分配给 PHP 的内存 / 单个 PHP-FPM 进程平均内存
```

在 SSPanel-UIM 的 Docker 部署中，可以先按单个 PHP-FPM 进程约 `30MB` 估算。例如服务器有 1GB 内存，但 MariaDB、Redis、Caddy、nginx 和系统本身也需要内存，给 PHP 分配 600MB 时：

```text
600 / 30 = 20
```

调整后重启 app 和 scheduler：

```bash
docker compose up -d app scheduler
```

如果出现内存不足、容器被系统杀掉或响应明显变慢，应降低 `PHP_FPM_MAX_CHILDREN`。

## PHP 运行时限制

当前 Docker PHP 配置位于 `docker/php/php.ini`：

```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
```

如果需要上传更大的文件，可以同时调整 `upload_max_filesize`、`post_max_size` 和 nginx 的 `client_max_body_size`。不要只改其中一个值。

时区优先通过安装脚本生成的配置和 `.env` 中的 `TZ` 控制。容器默认使用 `Asia/Shanghai`。

## OPcache

当前 OPcache 配置位于 `docker/php/opcache.ini`：

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
```

`opcache.validate_timestamps=0` 适合生产 Docker 部署，可以减少文件变更检查开销。代价是代码更新后必须重启 app 容器，否则 PHP 可能继续使用旧缓存：

```bash
docker compose up -d --build app scheduler
```

## MariaDB

MariaDB 配置挂载自 `docker/mariadb/99-sspanel.cnf`：

```ini
[mysqld]
max_connections = 500
connect_timeout = 10
wait_timeout = 600
interactive_timeout = 600
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
sql_mode = STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

默认没有设置很大的 InnoDB buffer pool，避免小内存服务器启动后直接占用过多内存。数据库压力较大时，可以结合服务器内存和实际查询情况再添加更细的 InnoDB 参数。

查看 MariaDB 日志：

```bash
docker compose logs --tail=100 mariadb
```

进入数据库：

```bash
docker compose exec mariadb mariadb -u root -p
```

## Redis

Redis 配置挂载自 `docker/redis/redis.conf`：

```conf
save 900 1
save 300 10
save 60 10000
appendonly yes
appendfsync everysec
tcp-keepalive 60
timeout 300
```

该配置同时保留 RDB 快照和 AOF 持久化。`appendfsync everysec` 是性能和数据安全之间的折中。Redis 密码仍由 `.env` 中的 `REDIS_PASSWORD` 控制，留空时仅在 Docker 内部网络中无密码运行。

查看 Redis 日志：

```bash
docker compose logs --tail=100 redis
```

如果启用了 Redis 密码：

```bash
docker compose exec redis sh -c 'redis-cli -a "$REDIS_PASSWORD" ping'
```

如果未启用 Redis 密码：

```bash
docker compose exec redis redis-cli ping
```

## Docker ulimits

Compose 已为主要服务设置：

```yaml
ulimits:
  nofile:
    soft: 65535
    hard: 65535
```

这会提高容器内可打开文件数限制，适合 Web 服务、数据库和 Redis。宿主机仍可能有自己的系统级限制；如果服务量很大，需要同时检查宿主机限制。

## 可选宿主机 sysctl

以下设置不是安装脚本的一部分，也不会由 Docker Compose 自动修改。只有在你理解影响范围并且服务器只用于该部署或已完成变更评估时，才建议手动设置。

查看当前值：

```bash
sysctl net.core.somaxconn
sysctl net.ipv4.tcp_max_syn_backlog
sysctl vm.overcommit_memory
```

临时调整示例：

```bash
sudo sysctl -w net.core.somaxconn=65535
sudo sysctl -w net.ipv4.tcp_max_syn_backlog=65535
sudo sysctl -w vm.overcommit_memory=1
```

持久化宿主机设置会影响整台服务器，建议先在业务低峰期验证。不要在不了解后果的情况下批量写入 `/etc/sysctl.conf`。

## 监控命令

查看容器资源：

```bash
docker stats
```

查看服务状态：

```bash
docker compose ps
```

查看关键日志：

```bash
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f caddy
docker compose logs -f scheduler
```

查看最近日志：

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 mariadb
docker compose logs --tail=100 redis
docker compose logs --tail=100 scheduler
```

## 故障排查

### 内存占用过高

先查看实时资源：

```bash
docker stats
```

如果 app 占用过高，降低 `PHP_FPM_MAX_CHILDREN`。如果 MariaDB 占用过高，不要盲目增加 InnoDB buffer pool；先确认服务器剩余内存。

### 数据库慢或连接过多

查看 MariaDB 日志：

```bash
docker compose logs --tail=100 mariadb
```

进入数据库检查连接：

```bash
docker compose exec mariadb mariadb -u root -p -e "SHOW PROCESSLIST;"
```

如果经常达到连接上限，可以结合业务规模调整 `max_connections`，同时检查是否存在异常请求或队列堆积。

### Redis 持久化异常

查看 Redis 日志：

```bash
docker compose logs --tail=100 redis
```

确认 volume 正常挂载：

```bash
docker compose ps redis
```

不要删除 `redis_data` volume，除非你明确要清空 Redis 数据。

### 502 或 504

检查 app、nginx、caddy：

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 nginx
docker compose logs --tail=100 caddy
```

常见原因包括 PHP-FPM 子进程不足、app 容器重启、数据库连接慢、Caddy 或 nginx 无法访问内部服务。

### 更新代码后页面仍像旧版本

Docker 生产配置关闭了 OPcache 时间戳检查。更新代码或镜像后需要重建并重启 app：

```bash
docker compose up -d --build app scheduler
```
