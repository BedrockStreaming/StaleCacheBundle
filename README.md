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

### Remarks

This bundle disables [the stampede prevention from Symfony Cache](https://symfony.com/doc/current/components/cache.html#stampede-prevention).

## Contribute

You can execute `make test` to execute all tests.

## TODO before release

* [ ] Create Github Actions to execute tests
* [ ] Cleanup configuration
* [ ] Create version 1.0 for Symfony 5.4 + PHP 7.4 and version 2.0 for Symfony 6.0 and PHP 8.0, using branch `feat/php8`
