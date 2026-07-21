#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

REPOSITORY_URL="https://github.com/makeausername/SSPanel-UIM-Docker.git"
REPOSITORY_SSH_URL="git@github.com:makeausername/SSPanel-UIM-Docker.git"
REPOSITORY_BRANCH="master"
INSTALL_DIR="/opt/SSPanel-UIM-Docker"
SUPPORTED_SYSTEMS="Ubuntu 22.04, Ubuntu 24.04, Debian 12"

MODE="install"
ASKPASS_DIR=""
CLONE_TMP_DIR=""
GIT_ERROR_FILE=""

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ] && [ "${TERM:-}" != "dumb" ]; then
    BLUE=$'\033[34m'
    GREEN=$'\033[32m'
    YELLOW=$'\033[33m'
    RED=$'\033[31m'
    GRAY=$'\033[90m'
    RESET=$'\033[0m'
    INFO_MARK="i"
    OK_MARK="✓"
    WARN_MARK="!"
    ERROR_MARK="✗"
else
    BLUE=""
    GREEN=""
    YELLOW=""
    RED=""
    GRAY=""
    RESET=""
    INFO_MARK="i"
    OK_MARK="OK"
    WARN_MARK="WARN"
    ERROR_MARK="ERROR"
fi

info() {
    printf '%b%s%b %s\n' "$BLUE" "$INFO_MARK" "$RESET" "$*"
}

success() {
    printf '%b%s%b %s\n' "$GREEN" "$OK_MARK" "$RESET" "$*"
}

warn() {
    printf '%b%s%b %s\n' "$YELLOW" "$WARN_MARK" "$RESET" "$*" >&2
}

die() {
    printf '%b%s%b %s\n' "$RED" "$ERROR_MARK" "$RESET" "$*" >&2
    exit 1
}

step() {
    printf '\n%b[%s/3]%b %s\n' "$BLUE" "$1" "$RESET" "$2"
}

usage() {
    cat <<'EOF'
Usage:
  curl -fsSL https://raw.githubusercontent.com/makeausername/SSPanel-UIM-Docker/master/bootstrap.sh | sudo bash
  sudo bash bootstrap.sh
  sudo ./bootstrap.sh
  sudo bash bootstrap.sh --resume
  sudo bash bootstrap.sh --upgrade
  sudo bash bootstrap.sh --reinstall

Options:
  --resume     Continue a partial installation that has configuration files but
               no storage/.install_lock. Credentials and Docker volumes are kept.
  --upgrade    Update an existing installation without changing credentials.
  --reinstall  Enter the guarded reinstall flow. Existing Docker data is never
               deleted automatically.
  -h, --help   Show this help text.
EOF
}

parse_args() {
    if [ "$#" -gt 1 ]; then
        die "一次只能指定一个操作模式：--resume、--upgrade 或 --reinstall。"
    fi

    case "${1:-}" in
        "") MODE="install" ;;
        --resume) MODE="resume" ;;
        --upgrade) MODE="upgrade" ;;
        --reinstall) MODE="reinstall" ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            die "未知参数：$1"
            ;;
    esac
}

cleanup_clone_tmp() {
    if [ -n "$CLONE_TMP_DIR" ] && [ -d "$CLONE_TMP_DIR" ]; then
        case "$CLONE_TMP_DIR" in
            /opt/.sspanel-clone.*) rm -rf -- "$CLONE_TMP_DIR" ;;
            *) warn "拒绝清理意外的临时路径：${CLONE_TMP_DIR}" ;;
        esac
    fi
    CLONE_TMP_DIR=""
}

cleanup() {
    local status=$?

    if [ -n "$ASKPASS_DIR" ] && [ -d "$ASKPASS_DIR" ]; then
        rm -rf -- "$ASKPASS_DIR"
    fi
    if [ -n "$GIT_ERROR_FILE" ] && [ -f "$GIT_ERROR_FILE" ]; then
        rm -f -- "$GIT_ERROR_FILE"
    fi
    cleanup_clone_tmp
    unset GITHUB_TOKEN GIT_ASKPASS GIT_TERMINAL_PROMPT || true
    return "$status"
}

trap cleanup EXIT

ensure_root() {
    local script_source="${BASH_SOURCE[0]:-}"

    if [ "${EUID:-$(id -u)}" -eq 0 ]; then
        return 0
    fi

    command -v sudo >/dev/null 2>&1 || die "安装需要 root 权限，且当前系统未安装 sudo。请使用 root 用户重新运行。"

    if [ -z "$script_source" ] || [ ! -f "$script_source" ]; then
        die "无法从管道中自动提权。请使用文档中的 'curl ... | sudo bash' 命令。"
    fi

    info "正在通过 sudo 重新执行安装程序。"
    exec sudo --preserve-env=GITHUB_TOKEN,NO_COLOR bash "$script_source" "$@"
}

check_supported_system() {
    [ -r /etc/os-release ] || die "无法读取 /etc/os-release。支持的系统：${SUPPORTED_SYSTEMS}。"

    # shellcheck disable=SC1091
    . /etc/os-release

    case "${ID:-}:${VERSION_ID:-}" in
        ubuntu:22.04|ubuntu:24.04|debian:12) ;;
        *) die "不支持当前系统 ${PRETTY_NAME:-unknown}。支持的系统：${SUPPORTED_SYSTEMS}。" ;;
    esac

    success "系统检查通过：${PRETTY_NAME}."
}

install_base_dependencies() {
    local package
    local missing=()

    for package in ca-certificates curl git openssl gnupg; do
        if ! dpkg-query -W -f='${Status}' "$package" 2>/dev/null | grep -q 'install ok installed'; then
            missing+=("$package")
        fi
    done

    if [ "${#missing[@]}" -eq 0 ]; then
        success "基础依赖已就绪。"
        return 0
    fi

    info "安装缺少的基础依赖：${missing[*]}"
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${missing[@]}"
}

configure_docker_repository() {
    local arch
    local codename
    local repo_id

    # shellcheck disable=SC1091
    . /etc/os-release
    repo_id="$ID"
    codename="${VERSION_CODENAME:-}"
    arch="$(dpkg --print-architecture)"

    [ -n "$codename" ] || die "无法确定系统代号，不能安全配置 Docker 官方 APT 仓库。"

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/${repo_id}/gpg" -o /etc/apt/keyrings/docker.asc
    chmod 0644 /etc/apt/keyrings/docker.asc
    printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/%s %s stable\n' \
        "$arch" "$repo_id" "$codename" > /etc/apt/sources.list.d/docker.list
    chmod 0644 /etc/apt/sources.list.d/docker.list
    apt-get update
}

ensure_docker() {
    local install_engine="false"
    local install_plugins="false"
    local packages=()

    if ! command -v docker >/dev/null 2>&1; then
        install_engine="true"
    fi
    if ! command -v docker >/dev/null 2>&1 \
        || ! docker compose version >/dev/null 2>&1 \
        || ! docker buildx version >/dev/null 2>&1; then
        install_plugins="true"
    fi

    if [ "$install_engine" = "false" ] && [ "$install_plugins" = "false" ]; then
        if ! docker info >/dev/null 2>&1; then
            if command -v systemctl >/dev/null 2>&1; then
                systemctl enable --now docker
            fi
        fi
        docker info >/dev/null 2>&1 || die "Docker 已安装，但 daemon 不可用。请检查 systemctl status docker。"
        success "检测到可用的 Docker Engine、Compose v2 和 Buildx，保留现有安装。"
        return 0
    fi

    info "配置 Docker 官方 APT 仓库。"
    configure_docker_repository

    if [ "$install_engine" = "true" ]; then
        packages+=(docker-ce docker-ce-cli containerd.io)
    fi
    if [ "$install_plugins" = "true" ]; then
        packages+=(docker-buildx-plugin docker-compose-plugin)
    fi

    info "安装 Docker 组件：${packages[*]}"
    if ! DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${packages[@]}"; then
        die "Docker 组件安装失败。脚本不会执行 apt remove；请手工处理现有容器运行时冲突后重试。"
    fi

    if [ "$install_engine" = "true" ]; then
        command -v systemctl >/dev/null 2>&1 || die "未找到 systemctl，无法启动 Docker 服务。"
        systemctl enable --now docker
    elif ! docker info >/dev/null 2>&1 && command -v systemctl >/dev/null 2>&1; then
        systemctl enable --now docker
    fi
    docker info >/dev/null 2>&1 || die "docker info 验证失败。请检查 systemctl status docker。"
    docker compose version >/dev/null 2>&1 || die "Docker Compose v2 插件验证失败。"
    docker buildx version >/dev/null 2>&1 || die "Docker Buildx 插件验证失败。"
    success "Docker Engine、Compose v2 和 Buildx 已就绪。"
}

read_tty() {
    local variable_name="$1"
    local prompt="$2"
    local secret="${3:-false}"
    local input_value=""

    [ -r /dev/tty ] || die "当前会话没有可用的 /dev/tty，无法安全读取交互输入。"

    if [ "$secret" = "true" ]; then
        IFS= read -r -s -p "$prompt" input_value </dev/tty
        printf '\n' >/dev/tty
    else
        IFS= read -r -p "$prompt" input_value </dev/tty
    fi
    printf -v "$variable_name" '%s' "$input_value"
}

setup_askpass() {
    [ -n "${GITHUB_TOKEN:-}" ] || die "内部错误：未提供 GITHUB_TOKEN。"

    ASKPASS_DIR="$(mktemp -d "${TMPDIR:-/tmp}/sspanel-askpass.XXXXXX")"
    chmod 0700 "$ASKPASS_DIR"

    cat > "${ASKPASS_DIR}/askpass.sh" <<'EOF'
#!/usr/bin/env bash
case "$1" in
    *Username*) printf '%s\n' 'x-access-token' ;;
    *Password*) printf '%s\n' "${GITHUB_TOKEN:?}" ;;
    *) exit 1 ;;
esac
EOF
    chmod 0700 "${ASKPASS_DIR}/askpass.sh"
    export GIT_ASKPASS="${ASKPASS_DIR}/askpass.sh"
    export GIT_TERMINAL_PROMPT=0
}

clear_git_auth() {
    if [ -n "$ASKPASS_DIR" ] && [ -d "$ASKPASS_DIR" ]; then
        rm -rf -- "$ASKPASS_DIR"
    fi
    ASKPASS_DIR=""
    unset GITHUB_TOKEN GIT_ASKPASS GIT_TERMINAL_PROMPT || true
}

git_error_looks_like_auth_failure() {
    grep -Eqi 'authentication|authorization|403|401|could not read Username|repository not found|access denied' "$GIT_ERROR_FILE"
}

explain_private_repository_access() {
    cat >&2 <<'EOF'
无法匿名读取仓库。若仓库为 Private，请提供 Fine-grained Personal Access Token：
  - 只授权 makeausername/SSPanel-UIM-Docker
  - Repository permissions -> Contents -> Read-only
  - 不需要写入或 Administration 权限
请按 README 的私有仓库命令安全传入临时环境变量 GITHUB_TOKEN。
EOF
}

prepare_git_error_file() {
    if [ -n "$GIT_ERROR_FILE" ] && [ -f "$GIT_ERROR_FILE" ]; then
        rm -f -- "$GIT_ERROR_FILE"
    fi
    GIT_ERROR_FILE="$(mktemp "${TMPDIR:-/tmp}/sspanel-git-error.XXXXXX")"
    chmod 0600 "$GIT_ERROR_FILE"
}

normalize_repository_permissions() {
    local record
    local mode
    local path
    local parent

    [ -d "${INSTALL_DIR}/.git" ] \
        || die "无法规范化权限：${INSTALL_DIR} 不是 Git 仓库。"

    chmod 0755 "$INSTALL_DIR"

    while IFS= read -r -d '' record; do
        mode="${record%% *}"
        path="${record#*$'\t'}"

        [ "$path" != "$record" ] \
            || die "无法解析 Git 文件记录。"

        parent="$(dirname -- "$path")"
        while [ "$parent" != "." ] && [ "$parent" != "/" ]; do
            if [ -d "${INSTALL_DIR}/${parent}" ]; then
                chmod 0755 "${INSTALL_DIR}/${parent}"
            fi
            parent="$(dirname -- "$parent")"
        done

        # Never follow or change tracked symbolic links.
        if [ -L "${INSTALL_DIR}/${path}" ]; then
            continue
        fi

        [ -f "${INSTALL_DIR}/${path}" ] || continue

        case "$path" in
            bootstrap.sh|install.sh|docker/entrypoint.sh|docker/cron/scheduler)
                chmod 0755 "${INSTALL_DIR}/${path}"
                ;;
            *)
                case "$mode" in
                    100755)
                        chmod 0755 "${INSTALL_DIR}/${path}"
                        ;;
                    *)
                        chmod 0644 "${INSTALL_DIR}/${path}"
                        ;;
                esac
                ;;
        esac
    done < <(git -C "$INSTALL_DIR" ls-files -s -z)

    success "Git 跟踪文件权限已规范化。"
}

clone_repository() {
    local had_token="false"

    [ ! -e "$INSTALL_DIR" ] || die "目标目录 ${INSTALL_DIR} 已存在，脚本不会覆盖来源不明的目录。"
    mkdir -p /opt
    CLONE_TMP_DIR="$(mktemp -d /opt/.sspanel-clone.XXXXXX)"
    chmod 0700 "$CLONE_TMP_DIR"
    prepare_git_error_file

    info "尝试匿名克隆仓库。"
    if GIT_TERMINAL_PROMPT=0 git clone --depth 1 --branch "$REPOSITORY_BRANCH" "$REPOSITORY_URL" "$CLONE_TMP_DIR" 2>"$GIT_ERROR_FILE"; then
        :
    else
        if ! git_error_looks_like_auth_failure; then
            sed -n '1,8p' "$GIT_ERROR_FILE" >&2
            die "仓库克隆失败，请检查网络和 GitHub 可用性。"
        fi

        if [ -z "${GITHUB_TOKEN:-}" ]; then
            explain_private_repository_access
            die "缺少读取私有仓库所需的 GITHUB_TOKEN。"
        fi

        had_token="true"
        cleanup_clone_tmp
        CLONE_TMP_DIR="$(mktemp -d /opt/.sspanel-clone.XXXXXX)"
        chmod 0700 "$CLONE_TMP_DIR"
        setup_askpass
        info "使用临时 GIT_ASKPASS 读取私有仓库。"
        if ! git clone --depth 1 --branch "$REPOSITORY_BRANCH" "$REPOSITORY_URL" "$CLONE_TMP_DIR" 2>"$GIT_ERROR_FILE"; then
            die "使用 GITHUB_TOKEN 克隆失败。请确认 Token 仅授权目标仓库且 Contents 为 Read-only。"
        fi
    fi

    git -C "$CLONE_TMP_DIR" remote set-url origin "$REPOSITORY_URL"
    mv -- "$CLONE_TMP_DIR" "$INSTALL_DIR"
    CLONE_TMP_DIR=""
    normalize_repository_permissions
    clear_git_auth
    if [ "$had_token" = "true" ]; then
        success "私有仓库已安全克隆，origin 不包含凭据。"
    else
        success "仓库已克隆到 ${INSTALL_DIR}。"
    fi
}

verify_repository_origin() {
    local origin

    [ -d "${INSTALL_DIR}/.git" ] || die "${INSTALL_DIR} 已存在但不是 Git 仓库，拒绝覆盖。"
    origin="$(git -C "$INSTALL_DIR" remote get-url origin 2>/dev/null || true)"

    case "$origin" in
        "$REPOSITORY_URL"|"${REPOSITORY_URL%.git}"|"$REPOSITORY_SSH_URL") ;;
        *) die "${INSTALL_DIR} 的 origin 与预期仓库不匹配。为避免泄露潜在凭据，脚本不会显示原值，并已拒绝继续。" ;;
    esac

    git -C "$INSTALL_DIR" remote set-url origin "$REPOSITORY_URL"
}

run_authenticated_git() {
    prepare_git_error_file

    if GIT_TERMINAL_PROMPT=0 git -C "$INSTALL_DIR" "$@" 2>"$GIT_ERROR_FILE"; then
        return 0
    fi

    if ! git_error_looks_like_auth_failure; then
        sed -n '1,8p' "$GIT_ERROR_FILE" >&2
        return 1
    fi

    if [ -z "${GITHUB_TOKEN:-}" ]; then
        explain_private_repository_access
        return 1
    fi

    setup_askpass
    if ! git -C "$INSTALL_DIR" "$@" 2>"$GIT_ERROR_FILE"; then
        return 1
    fi
}

update_repository() {
    local tracked_changes

    verify_repository_origin
    tracked_changes="$(git -C "$INSTALL_DIR" status --porcelain --untracked-files=no)"
    [ -z "$tracked_changes" ] || die "仓库存在未提交的 tracked 修改。为避免覆盖本地改动，更新已终止。"

    info "更新 ${REPOSITORY_BRANCH} 分支。"
    run_authenticated_git fetch origin "$REPOSITORY_BRANCH" || die "git fetch 失败。"
    git -C "$INSTALL_DIR" checkout "$REPOSITORY_BRANCH"
    run_authenticated_git pull --ff-only origin "$REPOSITORY_BRANCH" || die "git pull --ff-only 失败。"
    git -C "$INSTALL_DIR" remote set-url origin "$REPOSITORY_URL"
    clear_git_auth
    normalize_repository_permissions
    success "仓库已更新，origin 不包含凭据。"
}

compose_has_containers() {
    [ -f "${INSTALL_DIR}/docker-compose.yml" ] || return 1
    [ -n "$(cd "$INSTALL_DIR" && DB_PASSWORD=probe DB_ROOT_PASSWORD=probe docker compose ps -aq 2>/dev/null || true)" ]
}

has_mariadb_volume() {
    local project_name

    project_name="$(basename "$INSTALL_DIR" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9_-]+/-/g; s/^[^a-z0-9]+//')"
    [ -n "$(docker volume ls -q \
        --filter "label=com.docker.compose.project=${project_name}" \
        --filter 'label=com.docker.compose.volume=mariadb_data' \
        2>/dev/null || true)" ]
}

has_existing_installation() {
    [ -f "${INSTALL_DIR}/.env" ] \
        || [ -f "${INSTALL_DIR}/storage/.install_lock" ] \
        || [ -f "${INSTALL_DIR}/config/.config.php" ] \
        || [ -f "${INSTALL_DIR}/config/appprofile.php" ] \
        || [ -f "${INSTALL_DIR}/docker-compose.override.yml" ] \
        || compose_has_containers \
        || has_mariadb_volume
}

wait_for_service_ready() {
    local service="$1"
    local timeout_seconds="$2"
    local started_at
    local container_id
    local status
    local health
    local effective_status

    started_at="$(date +%s)"
    while true; do
        container_id="$(docker compose ps -q "$service" | sed -n '1p')"
        if [ -n "$container_id" ]; then
            status="$(docker inspect --format '{{.State.Status}}' "$container_id" 2>/dev/null || true)"
            health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$container_id" 2>/dev/null || true)"
            effective_status="${health:-${status:-unknown}}"

            case "$effective_status" in
                running|healthy)
                    success "${service} is ready."
                    return 0
                    ;;
                unhealthy|exited|dead)
                    docker compose logs --tail=100 "$service" >&2 || true
                    die "${service} entered ${effective_status} state."
                    ;;
            esac
        fi

        if [ $(( "$(date +%s)" - started_at )) -ge "$timeout_seconds" ]; then
            docker compose logs --tail=100 "$service" >&2 || true
            die "Timed out waiting for ${service} readiness."
        fi

        sleep 3
    done
}

guard_default_install() {
    if has_existing_installation; then
        if [ -f "${INSTALL_DIR}/.env" ] \
            && [ -f "${INSTALL_DIR}/config/.config.php" ] \
            && [ ! -e "${INSTALL_DIR}/storage/.install_lock" ]; then
            die "检测到未完成的安装。请保留现有配置和 Docker volume，并使用 bootstrap.sh --resume。"
        fi
        die "检测到已有安装，为避免数据库凭据失配，本次安装已安全终止。请使用 bootstrap.sh --upgrade。"
    fi
}

guard_resume_installation() {
    [ -d "$INSTALL_DIR" ] || die "未找到 ${INSTALL_DIR}，不能执行恢复。首次安装请不要传 --resume。"
    verify_repository_origin
    [ -f "${INSTALL_DIR}/.env" ] || die "--resume 仅适用于已存在 .env 的部分安装。"
    [ -f "${INSTALL_DIR}/config/.config.php" ] || die "--resume 仅适用于已存在 config/.config.php 的部分安装。"
    [ ! -e "${INSTALL_DIR}/storage/.install_lock" ] \
        || die "检测到 storage/.install_lock；该安装已经完成，请使用 --upgrade。"
}

create_upgrade_backup() {
    local backup_dir="${INSTALL_DIR}/storage/backups"
    local timestamp
    local backup_file
    local temporary_file

    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    backup_file="${backup_dir}/sspanel-before-upgrade-${timestamp}.sql"
    temporary_file="${backup_file}.tmp"
    install -d -m 700 "$backup_dir"

    info "Creating a database backup before migrations."
    if ! docker compose exec -T mariadb sh -c \
        'exec mariadb-dump --single-transaction --quick --routines --events -u root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
        > "$temporary_file"; then
        rm -f -- "$temporary_file"
        die "Database backup failed; upgrade stopped before migration."
    fi

    if [ ! -s "$temporary_file" ]; then
        rm -f -- "$temporary_file"
        die "Database backup is empty; upgrade stopped before migration."
    fi

    mv -- "$temporary_file" "$backup_file"
    sha256sum "$backup_file" > "${backup_file}.sha256"
    success "Database backup created: ${backup_file}"
}

confirm_reinstall() {
    local confirmation

    warn "重装会替换现有面板配置，并可能导致现有数据不可访问。"
    warn "本脚本绝不会自动删除 Docker volume。请先完成可恢复的数据库和配置备份。"
    read_tty confirmation "如确认继续，请完整输入 DELETE ALL PANEL DATA: " "false"
    [ "$confirmation" = "DELETE ALL PANEL DATA" ] || die "确认文本不匹配，重装已安全终止。"

    if compose_has_containers || has_mariadb_volume; then
        cat >&2 <<'EOF'
检测到现有容器或 MariaDB volume。为避免误删生产数据，脚本不会自动清理它们。
请先完成备份，手工停止并删除明确属于本项目的旧容器和数据卷，再重新运行 --reinstall。
不要在未确认备份可恢复前执行 docker compose down -v。
EOF
        die "现有 Docker 数据尚未由用户手工处理，重装未执行。"
    fi
}

run_upgrade() {
    [ -d "$INSTALL_DIR" ] || die "未找到 ${INSTALL_DIR}，不能执行升级。首次安装请不要传 --upgrade。"
    verify_repository_origin
    [ -f "${INSTALL_DIR}/.env" ] || die "升级需要保留的 .env 不存在。"
    [ -f "${INSTALL_DIR}/config/.config.php" ] || die "升级需要保留的 config/.config.php 不存在。"
    [ -f "${INSTALL_DIR}/config/appprofile.php" ] || die "升级需要保留的 config/appprofile.php 不存在。"

    update_repository
    cd "$INSTALL_DIR"

    info "校验 Compose 配置。"
    docker compose config >/dev/null
    docker compose stop scheduler >/dev/null 2>&1 || true
    docker compose up -d mariadb redis
    wait_for_service_ready mariadb 180
    wait_for_service_ready redis 180
    create_upgrade_backup
    info "构建并启动现有服务；不会重新生成任何凭据。"
    docker compose build
    docker compose run --rm -T app php xcat Migration latest
    docker compose up -d app nginx caddy
    wait_for_service_ready app 180
    docker compose exec -T app test -f vendor/autoload.php
    docker compose up -d scheduler
    info "Restarting nginx after the app image and database migration are ready."
    docker compose restart nginx
    wait_for_service_ready nginx 180
    wait_for_service_ready scheduler 300
    docker compose ps
    success "升级完成。所有配置文件和 Docker volume 均已保留。"
}

run_resume() {
    guard_resume_installation
    update_repository
    normalize_repository_permissions
    cd "$INSTALL_DIR"
    [ -f install.sh ] || die "仓库中缺少 install.sh。"
    unset GITHUB_TOKEN GIT_ASKPASS GIT_TERMINAL_PROMPT || true
    SSPANEL_INSTALL_MODE=resume exec bash ./install.sh
}

run_install() {
    if [ -e "$INSTALL_DIR" ]; then
        verify_repository_origin
        guard_default_install
        update_repository
    else
        clone_repository
    fi

    normalize_repository_permissions
    [ -f "${INSTALL_DIR}/install.sh" ] || die "仓库中缺少 install.sh。"
    cd "$INSTALL_DIR"
    unset GITHUB_TOKEN GIT_ASKPASS GIT_TERMINAL_PROMPT || true
    exec bash ./install.sh
}

run_reinstall() {
    [ -d "$INSTALL_DIR" ] || die "未找到 ${INSTALL_DIR}。首次安装不需要 --reinstall。"
    verify_repository_origin
    confirm_reinstall
    update_repository
    normalize_repository_permissions
    cd "$INSTALL_DIR"
    unset GITHUB_TOKEN GIT_ASKPASS GIT_TERMINAL_PROMPT || true
    SSPANEL_INSTALL_MODE=reinstall SSPANEL_REINSTALL_CONFIRMED=1 exec bash ./install.sh
}

main() {
    parse_args "$@"
    ensure_root "$@"

    step 1 "检查服务器环境"
    check_supported_system
    install_base_dependencies

    step 2 "安装 Docker 环境"
    ensure_docker

    step 3 "准备面板仓库"
    case "$MODE" in
        install) run_install ;;
        resume) run_resume ;;
        upgrade) run_upgrade ;;
        reinstall) run_reinstall ;;
    esac
}

if [ -z "${BASH_SOURCE[0]:-}" ] || [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
