# Bedrock Stale cache Bundle

## Introduction

This bundle aims to provide a stale cache feature to the `symfony/cache` component.
Basically, it does the following steps:
1. extending the lifetime of cache items with a "stale period"
2. during the stale period, if a cache item is fetched, try to regenerate it
3. if the regeneration fails with a specifically marked error, give back the initially cached value

## Usage

### Configuration

First, you should configure each stale cache service:

```yaml
bedrock_stale_cache:
    decorated_cache_pools:
        stale_cache_service_id:                    # Desired id for this new stale cache instance
            cache_pool: cache_pool_id              # Cache pool on top of which stale cache will be used 
            max_stale: 3600                        # Stale period duration, in seconds
            enable_debug_logs: true                # Optional (defaults to false), produce a bunch of debug logs
```

It will declare a `@stale_cache_service_id`, that you can use as an injected dependency.
The stale service will implement `Symfony\Contracts\Cache\CacheInterface`, so you'll need to use the `get` method to fetch cache items.
You can use `Symfony\Contracts\Cache\TagAwareCacheInterface` if you need tagging capabilities.

It's not compatible with the old `Symfony\Component\Cache\Adapter\AdapterInterface`.

### Allow stale cache for some errors

To use stale cache, you'll have to implement `Bedrock\StaleCacheBundle\Exception\UnavailableResourceException` on a custom, thrown error.
The method `allowStaleCacheUsage` can be used for some custom logic, or you can hard code a `return true`. 

### Events

A `Bedrock\StaleCacheBundle\Event\StaleCacheUsage` event is sent on stale cache usage. It is strongly advised to log it, with the associated error.

### Logs

Debug logs can be enabled to ensure correct stale cache usage.
It should not be enabled in a production environment since it can cause performance issue.

## Contribute

You can execute `make quality test` to execute quality checks and tests.
There is also a few make targets that can help you check this bundle is correctly supported on every Symfony versions
* `make composer-install-sf4`
* `make composer-install-sf5`
* `make composer-install-sf6`
