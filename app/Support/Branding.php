<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Office logo, sidebar icon and favicon. Super admins upload them in Settings › Branding (kept on the public disk
 * and served by BrandingController); without an upload the files in public/images are used.
 */
class Branding
{
    /** asset => [label, where it shows, default file in public/, accepted types] */
    public const ASSETS = [
        'logo' => ['Logo', 'Sign-in page. Once you upload one, it also heads quotations and invoices.', 'images/nr-logo.png', 'jpg,jpeg,png,webp,svg'],
        'icon' => ['Sidebar icon', 'Small square mark at the top of the sidebar and on documents, in place of the "NR" badge.', null, 'jpg,jpeg,png,webp,svg'],
        'favicon' => ['Favicon', 'The little icon on the browser tab. A square PNG of 64×64 or more works best.', 'images/favicon.png', 'png,ico,svg'],
    ];

    /** The uploaded file's path on the public disk, if there is one. */
    public static function uploaded(string $asset): ?string
    {
        $path = Setting::get("brand.{$asset}");

        return filled($path) && Storage::disk('public')->exists($path) ? $path : null;
    }

    public static function isCustom(string $asset): bool
    {
        return self::uploaded($asset) !== null;
    }

    /** Absolute path of the file to serve: the upload, else the default. */
    public static function file(string $asset): ?string
    {
        if ($path = self::uploaded($asset)) {
            return Storage::disk('public')->path($path);
        }
        $default = self::ASSETS[$asset][2] ?? null;

        return $default && file_exists(public_path($default)) ? public_path($default) : null;
    }

    /** URL to use in pages, or null when there is nothing to show. Versioned so a new upload shows straight away. */
    public static function url(string $asset): ?string
    {
        if ($path = self::uploaded($asset)) {
            return route('branding.show', ['asset' => $asset, 'v' => substr(md5($path), 0, 8)]);
        }
        $default = self::ASSETS[$asset][2] ?? null;

        return $default && file_exists(public_path($default)) ? asset($default).'?v='.filemtime(public_path($default)) : null;
    }
}
