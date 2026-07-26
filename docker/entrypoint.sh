#!/bin/sh
set -e

cd /app

# 若 .env 不存在则从模板创建
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 若 APP_KEY 为空则生成并写入 .env
if ! grep -q '^APP_KEY=.\+' .env; then
    echo "生成 APP_KEY ..."
    php artisan key:generate --force
fi

# 等待数据库就绪（仅在 DB_CONNECTION 为 mysql 时）
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "等待数据库 $DB_HOST 就绪 ..."
    i=0
    while [ $i -lt 60 ]; do
        if php -r "exit(@new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}', '${DB_USERNAME}', '${DB_PASSWORD}') ? 0 : 1);" 2>/dev/null; then
            echo "数据库已就绪。"
            break
        fi
        i=$((i + 1))
        sleep 2
    done
    if [ $i -ge 60 ]; then
        echo "数据库连接超时，退出。" >&2
        exit 1
    fi
fi

# 建表
php artisan migrate --force --no-interaction

# 如果传入参数则运行对应命令，否则启动 nginx + php-fpm
if [ $# -gt 0 ]; then
    exec "$@"
fi

# 启动 nginx + php-fpm（supervisor）
cat > /tmp/supervisord.conf <<EOF
[supervisord]
nodaemon=true
logfile=/dev/stderr
user=root

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

exec supervisord -n -c /tmp/supervisord.conf
