<?php

namespace Opcodes\LogViewer\Concerns\LogIndex;

use Carbon\CarbonInterface;
use Opcodes\LogViewer\Facades\Cache;
use Opcodes\LogViewer\LogIndexChunk;
use Opcodes\LogViewer\Utils\GenerateCacheKey;
use Opcodes\LogViewer\Utils\Utils;

trait CanCacheIndex
{
    public function clearCache(): void
    {
        foreach ($this->getAllCacheKeys() as $cacheKey) {
            Cache::forget($cacheKey);
        }

        // this will reset all properties to default, because it won't find any cached settings for this index
        $this->loadMetadata();
    }

    public function cacheSize(): int
    {
        return collect($this->getAllCacheKeys())
            ->sum(fn ($cacheKey) => strlen(serialize(Cache::get($cacheKey))));
    }

    protected function getAllCacheKeys(): array
    {
        $keys = [];

        foreach ($this->getChunkDefinitions() as $chunkDefinition) {
            $keys[] = $this->chunkCacheKey($chunkDefinition['index']);
        }

        $keys[] = $this->metaCacheKey();
        $keys[] = $this->cacheKey();

        return $keys;
    }

    protected function saveMetadataToCache(): void
    {
        Cache::put($this->metaCacheKey(), $this->getMetadata(), $this->cacheTtl());
    }

    protected function getMetadataFromCache(): array
    {
        return Cache::get($this->metaCacheKey(), []);
    }

    protected function saveChunkToCache(LogIndexChunk $chunk): void
    {
        Cache::put(
            $this->chunkCacheKey($chunk->index),
            $chunk->data,
            $this->cacheTtl()
        );
    }

    protected function getChunkDataFromCache(int $index, $default = null): ?array
    {
        return Cache::get($this->chunkCacheKey($index), $default);
    }

    protected function clearChunksFromCache(): void
    {
        foreach ($this->getChunkDefinitions() as $chunkDefinition) {
            Cache::forget($this->chunkCacheKey($chunkDefinition['index']));
        }
    }

    protected function cacheKey(): string
    {
        return GenerateCacheKey::for($this);
    }

    protected function metaCacheKey(): string
    {
        return GenerateCacheKey::for($this, 'metadata');
    }

    protected function chunkCacheKey(int $index): string
    {
        return GenerateCacheKey::for($this, "chunk:$index");
    }

    protected function cacheTtl(): CarbonInterface
    {
        if (! empty($this->query)) {
            // There will be a lot more search queries, and they're usually just one-off searches.
            // We don't want these to take up too much of Redis/File-cache space for too long.
            return now()->addDay();
        }

        return now()->addWeek();
    }
}
