#!/usr/bin/env bash

PHP_VERSION=8.2
TOOL_DIR=.Build/tools/phpcs
TOOL_PACKAGE="friendsofphp/php-cs-fixer"
TOOL_COMMAND="php-cs-fixer fix -v --diff"

source scripts/runphptool.sh
