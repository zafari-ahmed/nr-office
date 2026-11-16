<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Activity;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function show(string $asset)
    {
        $file = Branding::file($asset);
        abort_unless($file, 404);

        // An uploaded SVG must never run scripts.
        return response()->file($file, ['Cache-Control' => 'public, max-age=31536000', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'", 'X-Content-Type-Options' => 'nosniff']);
    }

    public function edit()
    {
        return view('settings.branding', ['sections' => SettingsController::SECTIONS, 'section' => 'branding']);
    }

    public function update(Request $request)
    {
        $rules = collect(Branding::ASSETS)->mapWithKeys(fn ($a, $key) => [$key => ['nullable', 'file', 'mimes:'.$a[3], 'max:2048']])->all();
        $request->validate($rules, ['*.max' => 'Each file must be 2 MB or smaller.', '*.mimes' => 'That file type is not supported here.']);

        $saved = [];
        foreach (array_keys(Branding::ASSETS) as $asset) {
            if (! $request->hasFile($asset)) {
                continue;
            }
            $this->deleteUpload($asset);
            Setting::put("brand.{$asset}", $request->file($asset)->store('branding', 'public'));
            $saved[] = strtolower(Branding::ASSETS[$asset][0]);
        }
        if (! $saved) {
            return back()->withErrors(['logo' => 'Choose a file to upload.']);
        }
        Activity::log('branding_updated', 'Branding updated: '.implode(', ', $saved));

        return back()->with('success', ucfirst(implode(', ', $saved)).' updated.');
    }

    public function destroy(string $asset)
    {
        $this->deleteUpload($asset);
        Setting::put("brand.{$asset}", '');

        return back()->with('success', Branding::ASSETS[$asset][0].' reset to the default.');
    }

    private function deleteUpload(string $asset): void
    {
        if ($path = Branding::uploaded($asset)) {
            Storage::disk('public')->delete($path);
        }
    }
}
