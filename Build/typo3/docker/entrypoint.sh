#!/bin/bash
set -eu

runuser -u www-data vendor/bin/typo3 install:extensionsetupifpossible
vendor/bin/typo3 backend:createadmin admin password || true

exec apache2-foreground
