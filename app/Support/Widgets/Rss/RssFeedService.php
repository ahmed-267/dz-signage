<?php

namespace App\Support\Widgets\Rss;

use App\Support\Widgets\SafeRemoteUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use Throwable;

final class RssFeedService
{
    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    public function fetch(string $feedUrl, int $maxItems = 5): ?array
    {
        try {
            $feedUrl = SafeRemoteUrl::assertSafe($feedUrl);
        } catch (ValidationException) {
            return null;
        }

        $maxItems = max(1, min(20, $maxItems));
        $cacheKey = 'widget:rss:'.md5($feedUrl.'|'.$maxItems);
        $ttl = max(60, (int) config('widgets.rss.cache_ttl_seconds', 600));

        try {
            return Cache::remember($cacheKey, $ttl, function () use ($feedUrl, $maxItems, $cacheKey) {
                $payload = $this->downloadAndParse($feedUrl, $maxItems);
                if ($payload === null) {
                    throw new \RuntimeException('rss_fetch_failed');
                }

                Cache::forever($cacheKey.':last', $payload);

                return $payload;
            });
        } catch (Throwable) {
            $last = Cache::get($cacheKey.':last');

            return $this->normalizeCached($last);
        }
    }

    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    private function normalizeCached(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['fetchedAt']) || ! is_array($value['items'] ?? null)) {
            return null;
        }

        $items = [];
        foreach ($value['items'] as $item) {
            if (! is_array($item) || ! isset($item['title']) || ! is_string($item['title'])) {
                continue;
            }
            $items[] = [
                'title' => $item['title'],
                'link' => isset($item['link']) && is_string($item['link']) ? $item['link'] : null,
                'publishedAt' => isset($item['publishedAt']) && is_string($item['publishedAt']) ? $item['publishedAt'] : null,
                'source' => isset($item['source']) && is_string($item['source']) ? $item['source'] : null,
            ];
        }

        return [
            'title' => isset($value['title']) && is_string($value['title']) ? $value['title'] : null,
            'items' => $items,
            'fetchedAt' => (string) $value['fetchedAt'],
        ];
    }

    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    private function downloadAndParse(string $feedUrl, int $maxItems): ?array
    {
        $timeout = (int) config('widgets.rss.http_timeout', 8);
        $maxBytes = (int) config('widgets.rss.max_bytes', 524288);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*'])
                ->get($feedUrl);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentLength = $response->header('Content-Length');
        if (is_numeric($contentLength) && (int) $contentLength > $maxBytes) {
            return null;
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        return $this->parse($body, $maxItems);
    }

    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }|null
     */
    private function parse(string $body, int $maxItems): ?array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                return null;
            }

            $root = strtolower($xml->getName());
            if ($root === 'rss' || isset($xml->channel)) {
                return $this->parseRss($xml, $maxItems);
            }

            if ($root === 'feed' || isset($xml->entry)) {
                return $this->parseAtom($xml, $maxItems);
            }

            return null;
        } catch (Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }
     */
    private function parseRss(SimpleXMLElement $xml, int $maxItems): array
    {
        $channel = $xml->channel ?? $xml;
        $feedTitle = $this->cleanText((string) ($channel->title ?? ''));
        $items = [];

        foreach ($channel->item ?? [] as $item) {
            $title = $this->cleanText((string) ($item->title ?? ''));
            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'link' => $this->nullableUrl((string) ($item->link ?? '')),
                'publishedAt' => $this->normalizeDate((string) ($item->pubDate ?? $item->children('dc', true)->date ?? '')),
                'source' => $feedTitle !== '' ? $feedTitle : null,
            ];

            if (count($items) >= $maxItems) {
                break;
            }
        }

        return [
            'title' => $feedTitle !== '' ? $feedTitle : null,
            'items' => $items,
            'fetchedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     title: string|null,
     *     items: list<array{title: string, link: string|null, publishedAt: string|null, source: string|null}>,
     *     fetchedAt: string
     * }
     */
    private function parseAtom(SimpleXMLElement $xml, int $maxItems): array
    {
        $feedTitle = $this->cleanText((string) ($xml->title ?? ''));
        $items = [];

        foreach ($xml->entry ?? [] as $entry) {
            $title = $this->cleanText((string) ($entry->title ?? ''));
            if ($title === '') {
                continue;
            }

            $link = null;
            foreach ($entry->link ?? [] as $linkNode) {
                $attrs = $linkNode->attributes();
                $rel = (string) ($attrs['rel'] ?? 'alternate');
                $href = (string) ($attrs['href'] ?? '');
                if ($href !== '' && ($rel === 'alternate' || $rel === '')) {
                    $link = $href;
                    break;
                }
            }

            $items[] = [
                'title' => $title,
                'link' => $this->nullableUrl((string) $link),
                'publishedAt' => $this->normalizeDate((string) ($entry->updated ?? $entry->published ?? '')),
                'source' => $feedTitle !== '' ? $feedTitle : null,
            ];

            if (count($items) >= $maxItems) {
                break;
            }
        }

        return [
            'title' => $feedTitle !== '' ? $feedTitle : null,
            'items' => $items,
            'fetchedAt' => now()->toIso8601String(),
        ];
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function nullableUrl(string $url): ?string
    {
        $url = trim($url);

        return $url !== '' ? $url : null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return now()->parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
