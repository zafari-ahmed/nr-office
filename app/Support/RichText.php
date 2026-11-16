<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Formatted text from the rich editor (project notes). Only basic formatting is kept: headings, bold/italic/underline,
 * lists, quotes, links and tables. Anything else pasted in (styles, scripts, images, unknown tags) is stripped,
 * keeping its text.
 */
class RichText
{
    /** $aligned also keeps text alignment (left/center/right/justify), used by letterhead documents. */
    public static function clean(?string $html, bool $aligned = false): string
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block) // unknown tags are unwrapped, their text kept
            ->withMaxInputLength(500_000)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            ->dropElement('script')->dropElement('style')->dropElement('img')->dropElement('iframe');
        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'hr', 'table', 'thead', 'tbody', 'tr'] as $tag) {
            $config = $config->allowElement($tag);
        }
        $config = $config->allowElement('a', ['href'])->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan']);
        if ($aligned) {
            foreach (['p', 'h1', 'h2', 'h3', 'h4'] as $tag) {
                $config = $config->allowElement($tag, ['style']);
            }
            $config = $config->allowElement('th', ['colspan', 'rowspan', 'style'])->allowElement('td', ['colspan', 'rowspan', 'style']);
        }
        $clean = trim((new HtmlSanitizer($config))->sanitize((string) $html));

        // Of any inline style, keep only the text alignment.
        return $aligned ? preg_replace_callback('/ style="([^"]*)"/', function ($m) {
            return preg_match('/text-align:\s*(left|center|right|justify)/i', html_entity_decode($m[1]), $a) ? ' style="text-align: '.strtolower($a[1]).'"' : '';
        }, $clean) : $clean;
    }

    /** True when the stored value has no visible text (e.g. "<p></p>"). */
    public static function isEmpty(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '';
    }

    /** Notes written before the editor existed are plain text; show those with their line breaks. */
    public static function render(?string $body): HtmlString
    {
        $body = (string) $body;

        return new HtmlString(preg_match('/^\s*</', $body) ? self::clean($body) : nl2br(e($body)));
    }
}
