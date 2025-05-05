#!/bin/bash
openssl req -x509 -nodes -days 1460 -sha256 -newkey rsa:2048 \
    -subj '/CN=t3ext-oidc.test' \
    -reqexts SAN -extensions SAN \
    -config <(cat /etc/ssl/openssl.cnf \
        <(printf "\n[SAN]\nsubjectAltName=DNS:*.t3ext-oidc.test")) \
    -keyout ./certs/developer.key \
    -out ./certs/developer.pem
