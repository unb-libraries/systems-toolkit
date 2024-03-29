FROM ghcr.io/unb-libraries/php-cli:8.x

COPY ./composer.json /app/composer.json
COPY ./syskit_config.yml.sample /app/syskit_config.yml
COPY ./lib /app/lib

RUN apk --no-cache \
  add \
  php81-dom

RUN composer install --no-interaction --no-progress --no-suggest
ENTRYPOINT ["/app/vendor/bin/syskit"]

LABEL org.label-schema.build-date=$BUILD_DATE \
  org.label-schema.description="systems-toolkit is the systems-toolkit image at UNB Libraries." \
  org.label-schema.name="systems-toolkit" \
  org.label-schema.url="https://github.com/unb-libraries/systems-toolkit" \
  org.label-schema.vcs-ref=$VCS_REF \
  org.label-schema.vcs-url="https://github.com/unb-libraries/systems-toolkit" \
  org.label-schema.version=$VERSION \
  org.opencontainers.image.source="https://github.com/unb-libraries/systems-toolkit"
