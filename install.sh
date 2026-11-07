#!/usr/bin/env bash
set -euo pipefail

if [[ ! -r /etc/os-release ]]; then
    printf '%s\n' 'This installer supports Ubuntu only.' >&2
    exit 1
fi

source /etc/os-release

if [[ "${ID:-}" != ubuntu ]]; then
    printf '%s\n' 'This installer supports Ubuntu only.' >&2
    exit 1
fi

PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

run_root() {
    if [[ "$EUID" -eq 0 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
    run_root apt-get update
    run_root apt-get install -y docker.io docker-compose-v2 openssl
    run_root systemctl enable --now docker
fi

cd "$PROJECT_DIR"
mkdir -p storage/app/google

if [[ ! -f storage/app/google/credentials.json ]]; then
    printf '%s\n' 'Add the Google service-account key at storage/app/google/credentials.json, then rerun this installer.' >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

set_secret() {
    local name="$1"

    if grep -q "^${name}=$" .env; then
        local secret
        secret="$(openssl rand -hex 32)"
        sed -i "s/^${name}=$/${name}=${secret}/" .env
    fi
}

set_secret DB_PASSWORD
set_secret MYSQL_ROOT_PASSWORD
set_secret ADMIN_PASSWORD

if [[ -n "${SUDO_USER:-}" ]]; then
    chown "$SUDO_USER:$(id -gn "$SUDO_USER")" .env
fi
chmod 600 .env

if docker info >/dev/null 2>&1; then
    docker compose config --quiet
    docker compose up -d --build
else
    run_root docker compose config --quiet
    run_root docker compose up -d --build
fi

printf '%s\n' 'BullsMedia is starting. Admin credentials are stored in .env.'