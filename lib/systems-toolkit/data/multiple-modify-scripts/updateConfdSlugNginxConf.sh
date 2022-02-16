#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Tokenize NGINX_CONFD_SLUG' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateConfdSlugNginxConf.sh --yes
sed -i "s|conf\.d|NGINX_CONFD_SLUG|g" build/nginx/app.conf
sed -i "s|conf\.d|NGINX_CONFD_SLUG|g" build/nginx/server.conf
sed -i "s|http\.d|NGINX_CONFD_SLUG|g" build/nginx/app.conf
sed -i "s|http\.d|NGINX_CONFD_SLUG|g" build/nginx/server.conf
