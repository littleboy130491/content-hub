<?php

namespace App\Services\Safety;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Deterministic cleaning and isolation for untrusted web content.
 *
 * Filtering cannot stop prompt injection, so this only removes the hiding
 * places (comments, scripts, invisible characters, data URIs) and marks the
 * text as data. The rest of the defence is in how the text is used.
 */
class ContentSanitizer
{
    /**
     * Invisible or direction-changing characters that can hide instructions:
     * zero-width characters, bidi controls, the BOM, soft hyphen and the
     * Unicode tag block (U+E0000 to U+E007F).
     */
    protected const INVISIBLE = '/[\x{00AD}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{206F}\x{FEFF}\x{E0000}-\x{E007F}]/u';

    public function clean(string $markdown, ?int $maxChars = null): string
    {
        $maxChars ??= (int) config('contenthub.scraping.max_markdown_chars');

        $text = $markdown;

        // HTML comments, and script/style/noscript/template blocks with their contents.
        $text = preg_replace('/<!--.*?-->/s', '', $text);
        $text = preg_replace('#<(script|style|noscript|template|iframe|object)\b[^>]*>.*?</\1\s*>#is', '', $text);

        // Elements hidden from people but still read by models.
        $text = preg_replace('#<([a-z0-9]+)\b[^>]*(hidden|display\s*:\s*none|visibility\s*:\s*hidden|aria-hidden\s*=\s*["\']?true)[^>]*>.*?</\1\s*>#is', '', $text);

        // Any remaining raw HTML tags; markdown formatting stays.
        $text = strip_tags($text);

        $text = preg_replace(self::INVISIBLE, '', $text);

        // data: URIs in links, images or bare text.
        $text = preg_replace('/!\[[^\]]*\]\(\s*data:[^)]*\)/i', '', $text);
        $text = preg_replace('/\(\s*data:[^)]*\)/i', '()', $text);
        $text = preg_replace('/\bdata:[a-z0-9.+\/-]+;base64,[a-z0-9+\/=]+/i', '', $text);

        // Collapse the blank space left behind.
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars)."\n\n[Content cut at {$maxChars} characters.]";
        }

        return $text;
    }

    /**
     * Wrap untrusted text in a delimiter the page cannot forge: the token is
     * random per request, and any copy of it inside the text is removed.
     */
    public function isolate(string $text, string $token, string $source): string
    {
        $text = str_replace($token, '', $text);
        $text = preg_replace('#</?untrusted_web_content[^>]*>#i', '', $text);

        return sprintf(
            "<untrusted_web_content id=\"%s\" source=\"%s\">\n%s\n</untrusted_web_content id=\"%s\">",
            $token,
            e($source),
            $text,
            $token,
        );
    }

    public function newToken(): string
    {
        return Str::lower(Str::random(12));
    }

    /**
     * Only http and https URLs may be scraped.
     */
    public function assertScrapableUrl(string $url): string
    {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            throw new InvalidArgumentException('Only http and https web addresses can be scraped.');
        }

        return $url;
    }
}
