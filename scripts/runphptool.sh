#!/usr/bin/env bash

set -euo pipefail

containercommand=""
if [[ -x $(which "docker") ]]; then
    containercommand="docker compose"
fi
if [[ -x $(which "podman") ]]; then
    containercommand="podman compose"
fi
if [[ -x $(which "podman-compose") ]]; then
    containercommand="podman-compose"
fi

echo "### Using this container command: $containercommand"

composercommand="bash -c"
phpcommand="php -d memory_limit=512M"
if [[ -n $containercommand ]]; then
    composercommand="$containercommand run --rm tools bash -c"
    phpcommand="$containercommand run --rm tools $phpcommand"
fi

export PHP_VERSION

mkdir -p $TOOL_DIR

echo "### Installing tool"
read -r -d '' composer_setup <<- EOM || true
    if [[ ! -f $TOOL_DIR/composer.json ]]; then
      composer init --working-dir=$TOOL_DIR --no-interaction --stability=stable --name="rx/tool"
      composer config --working-dir=$TOOL_DIR --no-interaction allow-plugins true
      composer config --working-dir=$TOOL_DIR --no-interaction lock false
    fi
    composer req --working-dir=$TOOL_DIR --dev -a -W $TOOL_PACKAGE
EOM
$composercommand "$composer_setup"

echo "### Running tool"
$phpcommand $TOOL_DIR/vendor/bin/$TOOL_COMMAND "$@"
result=$?

echo "### Tearing down containers"
[[ -z $containercommand ]] || $containercommand down

exit $result
