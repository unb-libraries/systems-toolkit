# libvips image used to generate DZI tiles (vips dzsave).
# Built locally by `syskit dzi:pull-image` when syskit.imaging.dziTiler is vips.
FROM debian:bookworm-slim
RUN apt-get update \
  && apt-get install -y --no-install-recommends libvips-tools \
  && rm -rf /var/lib/apt/lists/*
