#!/usr/bin/env bash

scripts/composer.sh run extension-create-libs

V=$(git tag --sort version:refname | grep '^v5' | tail -1)
ZIP_NAME="oidc_${V/v/}.zip"
git archive -o $ZIP_NAME $V
zip $ZIP_NAME Libraries/*.phar

scripts/composer.sh run extension-clean
