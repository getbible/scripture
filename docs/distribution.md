# Shipping and deployment

## Product delivery

An application distributor should deliver one tested runtime containing:

- a supported PHP build;
- the released `getbible/sword` extension;
- the application's locked Composer dependencies;
- application-owned configuration;
- a writable snapshot cache; and
- only the SWORD modules the distributor is licensed to provide.

PIE and native build tools belong in the product build pipeline, not in the
end-user onboarding flow. The deployed runtime must not compile or download code
when the application starts.

## Container build pattern

The following Dockerfile is a complete build pattern for a PHP CLI application
whose committed `composer.lock` includes `getbible/scripture`. It installs PIE
and the native extension while the image is built. It does not download Bible
modules; mount or copy a separately reviewed module root according to each
module's distribution terms.

```dockerfile
# syntax=docker/dockerfile:1.7
FROM php:8.4-cli-bookworm

ARG PIE_VERSION=1.4.9
ARG PIE_SHA256=19a31ddd4bfd08b9eb5eaad2e5f63e76e7919cae7683852da41c80da704ad6c0

ENV GETBIBLE_SCRIPTURE_CONFIG_PATH=/etc/getbible/scripture.json \
    GETBIBLE_SCRIPTURE_MODULE_PATH=/var/lib/getbible/sword \
    GETBIBLE_SCRIPTURE_CACHE_PATH=/var/cache/getbible/scripture

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        autoconf \
        automake \
        binutils \
        build-essential \
        ca-certificates \
        cmake \
        curl \
        git \
        libbz2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        liblzma-dev \
        libtool \
        pkg-config \
        unzip \
        zlib1g-dev; \
    curl \
        --fail \
        --location \
        --proto '=https' \
        --retry 3 \
        --show-error \
        --silent \
        --tlsv1.2 \
        --output /tmp/pie.phar \
        "https://github.com/php/pie/releases/download/${PIE_VERSION}/pie.phar"; \
    echo "${PIE_SHA256}  /tmp/pie.phar" | sha256sum --check --strict; \
    php /tmp/pie.phar install --no-cache 'getbible/sword:^0.1.1'; \
    php --ri getbiblesword; \
    rm -f /tmp/pie.phar; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /srv/application

COPY . .

RUN set -eux; \
    composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --classmap-authoritative; \
    groupadd --system scripture; \
    useradd --system --gid scripture --home-dir /nonexistent scripture; \
    mkdir -p \
        /etc/getbible \
        /var/cache/getbible/scripture \
        /var/lib/getbible/sword; \
    chown -R scripture:scripture \
        /etc/getbible \
        /var/cache/getbible/scripture

USER scripture:scripture

ENTRYPOINT ["php", "vendor/bin/getbible-scripture"]
CMD ["scripture:doctor", "--json"]
```

Build the product image in CI:

```bash
docker build --pull --tag registry.example/getbible-application:2026.07 .
```

Create persistent application-owned configuration and cache volumes:

```bash
docker volume create getbible-scripture-config
docker volume create getbible-scripture-cache
```

Run setup once with the reviewed module root read-only:

```bash
docker run --rm \
  --mount type=volume,src=getbible-scripture-config,dst=/etc/getbible \
  --mount type=bind,src=/srv/getbible/sword,dst=/var/lib/getbible/sword,readonly \
  --mount type=volume,src=getbible-scripture-cache,dst=/var/cache/getbible/scripture \
  registry.example/getbible-application:2026.07 \
  scripture:setup \
  --config=/etc/getbible/scripture.json \
  --module-path=/var/lib/getbible/sword \
  --cache-path=/var/cache/getbible/scripture \
  --refresh-interval=P1M \
  --lock-timeout=30 \
  --module=KJV \
  --auto-refresh \
  --json
```

The product can then run readiness checks without PIE or build tools:

```bash
docker run --rm \
  --mount type=volume,src=getbible-scripture-config,dst=/etc/getbible \
  --mount type=bind,src=/srv/getbible/sword,dst=/var/lib/getbible/sword,readonly \
  --mount type=volume,src=getbible-scripture-cache,dst=/var/cache/getbible/scripture \
  registry.example/getbible-application:2026.07 \
  scripture:doctor --json
```

The image intentionally retains the native build packages. A distributor can
reduce image size with a carefully verified multi-stage build, but must copy
every runtime library reported by `ldd`, keep PHP ABI/ZTS compatibility exact,
and preserve the extension's INI activation. Omitting one of those checks can
produce an image that builds successfully but fails when PHP starts.

## VM and operating-system installer contract

An operating-system package or application installer should perform these
steps with administrator authorization:

1. install a supported PHP runtime;
2. install the released `getbible/sword` extension for that exact PHP ABI;
3. verify `php --ri getbiblesword`;
4. install the application's locked Composer vendor tree;
5. install or mount reviewed SWORD modules under an application-owned root;
6. create the cache and configuration directories with least privilege;
7. run `scripture:setup` with explicit non-interactive values;
8. run `scripture:doctor --json` and stop on a non-zero exit;
9. install the scheduler described in [production operations](operations.md);
10. start the product only after every readiness check succeeds.

The installer should record the application version, PHP version, extension
version, native product and ABI versions, contract identifier, module-source
provenance, and configuration checksum.

## Module distribution

CrossWire modules have independent licenses and disclaimers. A product
distributor must decide which modules may be redistributed and must preserve
their metadata and terms. The released native ABI does not provide repository,
license-acceptance, download, update, or atomic module-install operations.

Do not make the product entry point download a raw module archive. Install
module content through a reviewed deployment step, mount it read-only for
application workers, and use Scripture snapshots for validated access.
