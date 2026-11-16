<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Letterhead;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Settings › Letterhead & templates › templates. A template is made from an old document: the browser turns the
 * uploaded Word file (mammoth) or PDF (pdf.js text) into the starting body, which can then be tidied up and given
 * {{placeholders}}. The old file is kept for reference.
 */
class DocumentTemplateController extends Controller
{
    public function create()
    {
        return $this->form(new DocumentTemplate(['ref_prefix' => 'DOC', 'is_active' => true]));
    }

    public function store(Request $request)
    {
        $template = new DocumentTemplate(['created_by' => $request->user()->id, 'sort_order' => (int) DocumentTemplate::max('sort_order') + 1]);
        $this->save($request, $template);

        return redirect()->route('settings.letterheads.index')->with('success', "Template {$template->name} saved.");
    }

    public function edit(DocumentTemplate $template)
    {
        return $this->form($template);
    }

    public function update(Request $request, DocumentTemplate $template)
    {
        $this->save($request, $template);

        return redirect()->route('settings.letterheads.index')->with('success', "Template {$template->name} saved.");
    }

    public function destroy(DocumentTemplate $template)
    {
        $template->delete();

        return redirect()->route('settings.letterheads.index')->with('success', 'Template removed. Documents made from it are kept.');
    }

    public function sample(DocumentTemplate $template)
    {
        abort_unless($template->sample_path && Storage::disk('local')->exists($template->sample_path), 404);

        return Storage::disk('local')->download($template->sample_path, $template->sample_name);
    }

    private function form(DocumentTemplate $template)
    {
        return view('settings.letterheads.template-form', [
            'section' => 'letterheads',
            'template' => $template,
            'letterheads' => Letterhead::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    private function save(Request $request, DocumentTemplate $template): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ref_prefix' => ['required', 'alpha_dash', 'max:10'],
            'letterhead_id' => ['nullable', 'exists:letterheads,id'],
            'body' => ['nullable', 'string', 'max:500000'],
            'sample' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $template->fill([
            'name' => $data['name'],
            'ref_prefix' => strtoupper($data['ref_prefix']),
            'letterhead_id' => $data['letterhead_id'] ?? null,
            'body' => RichText::clean($data['body'] ?? '', true),
            'is_active' => $request->boolean('is_active'),
        ]);
        if ($request->hasFile('sample')) {
            $template->sample_path = $request->file('sample')->store('document-templates', 'local');
            $template->sample_name = $request->file('sample')->getClientOriginalName();
        }
        $template->save();
    }
}
