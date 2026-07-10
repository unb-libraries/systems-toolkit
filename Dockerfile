FROM ghcr.io/unb-libraries/php-cli:8.x

COPY ./composer.json /app/composer.json
COPY ./syskit_config.yml.sample /app/syskit_config.yml
COPY ./lib /app/lib

RUN apk --no-cache \
  add \
  php81-dom

RUN composer install --no-interaction --no-progress --no-suggest
ENTRYPOINT ["/app/vendor/bin/syskit"]

LABEL org.opencontainers.image.title="systems-toolkit" \
  org.opencontainers.image.description="systems-toolkit is the systems-toolkit image at UNB Libraries." \
  org.opencontainers.image.url="https://github.com/unb-libraries/systems-toolkit" \
  org.opencontainers.image.source="https://github.com/unb-libraries/systems-toolkit" \
  org.opencontainers.image.version="$VERSION" \
  org.opencontainers.image.revision="$VCS_REF" \
  org.opencontainers.image.created="$BUILD_DATE"
