#!/usr/bin/env bash

TOOL_DIR=.Build/tools/phpstan
TOOL_PACKAGE="phpstan/extension-installer phpstan/phpstan-deprecation-rules phpstan/phpstan-strict-rules bnf/phpstan-psr-container phpstan/phpdoc-parser phpstan/phpstan-phpunit"
TOOL_COMMAND="phpstan analyse --no-progress"

# --generate-baseline=phpstan-baseline.neon

source scripts/runphptool.sh
