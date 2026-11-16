<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Letterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Settings › Letterhead & templates. The browser renders the uploaded PDF / Word / image (pdf.js, docx-preview),
 * the user marks where the header ends and the footer starts, and the browser posts both bands as PNGs.
 * Nothing here needs a system binary, so it runs on shared hosting.
 */
class LetterheadController extends Controller
{
    private const SOURCE_TYPES = 'pdf,docx,png,jpg,jpeg,webp';

    public function index()
    {
        return view('settings.letterheads.index', [
            'section' => 'letterheads',
            'letterheads' => Letterhead::orderByDesc('is_default')->orderBy('name')->get(),
            'templates' => DocumentTemplate::with('letterhead')->withCount('documents')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('settings.letterheads.form', ['section' => 'letterheads', 'letterhead' => new Letterhead(['header_mm' => 40, 'footer_mm' => 25, 'side_mm' => 20])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $letterhead = new Letterhead($this->fields($data));
        $letterhead->created_by = $request->user()->id;
        $this->storeFiles($request, $letterhead);
        $letterhead->is_default = ! Letterhead::exists();
        $letterhead->save();

        return redirect()->route('settings.letterheads.index')->with('success', 'Letterhead saved.');
    }

    public function edit(Letterhead $letterhead)
    {
        return view('settings.letterheads.form', ['section' => 'letterheads', 'letterhead' => $letterhead]);
    }

    public function update(Request $request, Letterhead $letterhead)
    {
        $data = $this->validated($request, false);
        $letterhead->fill($this->fields($data));
        $this->storeFiles($request, $letterhead);
        $letterhead->touch();
        $letterhead->save();

        return redirect()->route('settings.letterheads.index')->with('success', 'Letterhead updated.');
    }

    public function makeDefault(Letterhead $letterhead)
    {
        DB::transaction(function () use ($letterhead) {
            Letterhead::where('is_default', true)->update(['is_default' => false]);
            $letterhead->update(['is_default' => true]);
        });

        return back()->with('success', "{$letterhead->name} is now the default letterhead.");
    }

    /** Files stay on disk: documents printed on this letterhead can still be reprinted from the history. */
    public function destroy(Letterhead $letterhead)
    {
        $letterhead->delete();
        if ($letterhead->is_default && ($next = Letterhead::orderBy('id')->first())) {
            $next->update(['is_default' => true]);
        }

        return redirect()->route('settings.letterheads.index')->with('success', 'Letterhead removed.');
    }

    /** Header / footer image, for anyone who can make documents or change settings. */
    public function image(Request $request, int $letterhead, string $part)
    {
        abort_unless($request->user()->hasPermission('documents.manage') || $request->user()->hasPermission('settings.manage'), 403);
        $letterhead = Letterhead::withTrashed()->findOrFail($letterhead);
        $path = match ($part) { 'header' => $letterhead->header_path, 'footer' => $letterhead->footer_path, default => abort(404) };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=31536000']);
    }

    /** The original upload, so the header and footer can be cut again. */
    public function source(Letterhead $letterhead)
    {
        abort_unless($letterhead->source_path && Storage::disk('local')->exists($letterhead->source_path), 404);

        return request()->boolean('download')
            ? Storage::disk('local')->download($letterhead->source_path, $letterhead->source_name)
            : Storage::disk('local')->response($letterhead->source_path, $letterhead->source_name);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'source' => ['nullable', 'file', 'mimes:'.self::SOURCE_TYPES, 'max:20480'],
            'header' => [$creating ? 'required_without:footer' : 'nullable', 'nullable', 'file', 'mimes:png,jpg,jpeg', 'max:10240'],
            'footer' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:10240'],
            'header_mm' => ['required', 'numeric', 'min:0', 'max:150'],
            'footer_mm' => ['required', 'numeric', 'min:0', 'max:150'],
            'side_mm' => ['required', 'numeric', 'min:5', 'max:50'],
        ], ['header.required_without' => 'Upload your letterhead file and mark the header or footer before saving.']);
    }

    private function fields(array $data): array
    {
        return collect($data)->only('name', 'header_mm', 'footer_mm', 'side_mm')->all();
    }

    /** New uploads get new file names, so documents printed earlier keep their old header and footer. */
    private function storeFiles(Request $request, Letterhead $letterhead): void
    {
        if ($request->hasFile('source')) {
            $letterhead->source_path = $request->file('source')->store('letterheads', 'local');
            $letterhead->source_name = $request->file('source')->getClientOriginalName();
        }
        foreach (['header', 'footer'] as $part) {
            if ($request->hasFile($part)) {
                $letterhead->{"{$part}_path"} = $request->file($part)->store('letterheads', 'local');
            } elseif ($request->boolean("clear_{$part}")) {
                $letterhead->{"{$part}_path"} = null;
            }
        }
    }
}
