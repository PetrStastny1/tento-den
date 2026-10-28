#!/bin/sh
# Creates the test database (<DB_NAME>_test) for the application user. Idempotent, works on an
# existing volume too. Runs inside the mariadb container; the root password stays in its environment.
set -eu
MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}_test\`.* TO \`${MARIADB_USER}\`@\`%\`;
SQL
