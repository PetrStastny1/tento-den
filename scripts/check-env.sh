#!/bin/sh
# Fails with a readable message when .env is missing or a required secret is empty.
set -eu

ENV_FILE="${1:-.env}"
REQUIRED="DB_PASSWORD DB_ROOT_PASSWORD"

if [ ! -f "$ENV_FILE" ]; then
	echo "Chyba: soubor $ENV_FILE neexistuje. Vytvořte ho: cp .env.example .env a vyplňte hesla." >&2
	exit 1
fi

missing=""
for name in $REQUIRED; do
	value=$(grep -E "^${name}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2-)
	# strip optional surrounding quotes, so DB_PASSWORD="" counts as empty too
	value=$(printf '%s' "$value" | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/")
	if [ -z "$value" ]; then
		missing="$missing $name"
	fi
done

if [ -n "$missing" ]; then
	echo "Chyba: v $ENV_FILE nejsou vyplněná hesla:$missing" >&2
	echo "Vyplňte je náhodnou hodnotou, např.: openssl rand -hex 24" >&2
	exit 1
fi
