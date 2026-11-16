<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentPrint;
use App\Models\DocumentTemplate;
use App\Models\Letterhead;
use App\Models\Project;
use App\Support\Activity;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Documents on the letterhead: pick a template, write the content between the fixed header and footer, print.
 * Every print is recorded with a copy of what was printed, so the history shows (and can reprint) each one.
 */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::with('template', 'project', 'creator')
            ->when($request->filled('template'), fn ($q) => $q->where('template_id', $request->integer('template')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('ref_no', 'like', $term)->orWhere('title', 'like', $term)->orWhere('recipient', 'like', $term));
            })
            ->when($request->input('printed') === 'yes', fn ($q) => $q->where('print_count', '>', 0))
            ->when($request->input('printed') === 'no', fn ($q) => $q->where('print_count', 0))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'templates' => DocumentTemplate::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'allTemplates' => DocumentTemplate::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'recentPrints' => DocumentPrint::with('document', 'user')->latest('printed_at')->limit(8)->get(),
            'hasLetterhead' => Letterhead::exists(),
        ]);
    }

    public function create(Request $request)
    {
        $template = $request->filled('template') ? DocumentTemplate::where('is_active', true)->findOrFail($request->integer('template')) : null;
        $document = new Document([
            'template_id' => $template?->id,
            'letterhead_id' => $template?->letterhead_id,
            'title' => $template?->name ?? '',
            'doc_date' => today(),
            'body' => $template?->body ?? '',
            'project_id' => $request->integer('project') ?: null,
        ]);

        return $this->form($document, $template);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = isset($data['template_id']) ? DocumentTemplate::find($data['template_id']) : null;
        $document = DB::transaction(fn () => Document::create($data + [
            'ref_no' => $this->nextRef($template?->ref_prefix ?? 'DOC'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));
        Activity::log('created', "Document {$document->ref_no} created", $document);

        return $request->input('action') === 'print'
            ? redirect()->route('documents.print', [$document, 'go' => 1])
            : redirect()->route('documents.show', $document)->with('success', "Document {$document->ref_no} saved.");
    }

    public function show(Document $document)
    {
        $document->load('template', 'project.client', 'creator', 'prints.user');

        return view('documents.show', ['document' => $document, 'letterhead' => $document->resolvedLetterhead()]);
    }

    public function edit(Document $document)
    {
        return $this->form($document, $document->template);
    }

    public function update(Request $request, Document $document)
    {
        $data = $this->validated($request);
        unset($data['template_id']);
        $document->update($data + ['updated_by' => $request->user()->id]);

        return $request->input('action') === 'print'
            ? redirect()->route('documents.print', [$document, 'go' => 1])
            : redirect()->route('documents.show', $document)->with('success', 'Document saved.');
    }

    /** A new document with the same content and a new reference number. */
    public function duplicate(Request $request, Document $document)
    {
        $copy = $document->replicate(['ref_no', 'print_count', 'last_printed_at']);
        $copy->fill([
            'ref_no' => $this->nextRef($document->template?->ref_prefix ?? 'DOC'),
            'doc_date' => today(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()->route('documents.edit', $copy)->with('success', "Copied to {$copy->ref_no}. Make your changes and print.");
    }

    public function destroy(Document $document)
    {
        $document->delete();

        return redirect()->route('documents.index')->with('success', "Document {$document->ref_no} deleted.");
    }

    /** The print page: A4 sheets with the header and footer on every page. */
    public function print(Document $document)
    {
        $document->load('project.client', 'template');

        return view('documents.print', [
            'document' => $document,
            'letterhead' => $document->resolvedLetterhead(),
            'body' => $document->renderedBody(),
            'title' => $document->title,
            'logUrl' => route('documents.prints.store', $document),
            'copyOf' => null,
        ]);
    }

    /** Called by the Print button just before the browser's print dialog opens. */
    public function logPrint(Request $request, Document $document)
    {
        $request->validate(['with_letterhead' => ['nullable', 'boolean'], 'from' => ['nullable', 'integer']]);
        $from = $request->filled('from') ? $document->prints()->find($request->integer('from')) : null;
        $letterhead = $from ? $from->letterhead : $document->resolvedLetterhead();

        $print = DB::transaction(function () use ($request, $document, $from, $letterhead) {
            $print = $document->prints()->create([
                'letterhead_id' => $letterhead?->id,
                'user_id' => $request->user()->id,
                'title' => $from?->title ?? $document->title,
                'recipient' => $from?->recipient ?? $document->recipient,
                'doc_date' => $from?->doc_date ?? $document->doc_date,
                'body' => $from?->body ?? $document->renderedBody(),
                'with_letterhead' => $request->boolean('with_letterhead', true),
                'printed_at' => now(),
            ]);
            $document->increment('print_count', 1, ['last_printed_at' => now()]);

            return $print;
        });

        return response()->json(['ok' => true, 'id' => $print->id, 'count' => $document->fresh()->print_count]);
    }

    /** Exactly what was printed at that time, ready to print again. */
    public function showPrint(DocumentPrint $print)
    {
        $print->load('document', 'user', 'letterhead');
        abort_unless($print->document, 404);

        return view('documents.print', [
            'document' => $print->document,
            'letterhead' => $print->letterhead ?? $print->document->resolvedLetterhead(),
            'body' => RichText::clean($print->body, true),
            'title' => $print->title,
            'logUrl' => route('documents.prints.store', $print->document),
            'copyOf' => $print,
        ]);
    }

    private function form(Document $document, ?DocumentTemplate $template)
    {
        $letterheads = Letterhead::orderByDesc('is_default')->orderBy('name')->get();

        return view('documents.form', [
            'document' => $document,
            'template' => $template,
            'letterheads' => $letterheads,
            'letterheadData' => $letterheads->mapWithKeys(fn ($l) => [$l->id => [
                'header' => $l->imageUrl('header'), 'footer' => $l->imageUrl('footer'),
                'header_mm' => $l->header_mm, 'footer_mm' => $l->footer_mm, 'side_mm' => $l->side_mm,
            ]]),
            'defaultLetterheadId' => $template?->letterhead_id ?? $letterheads->firstWhere('is_default', true)?->id ?? $letterheads->first()?->id,
            'projects' => Project::with('client')->orderBy('name')->get()->filter(fn ($p) => request()->user()->canSeeProject($p) && (! in_array($p->status, ['completed', 'cancelled'], true) || $p->id === $document->project_id))->values(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'exists:document_templates,id'],
            'letterhead_id' => ['nullable', 'exists:letterheads,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'doc_date' => ['required', 'date'],
            'body' => ['nullable', 'string', 'max:500000'],
        ]);
        $data['body'] = RichText::clean($data['body'] ?? '', true);

        return $data;
    }

    /** QT-2026-0001 style numbers, counted per prefix and year. */
    private function nextRef(string $prefix): string
    {
        $base = strtoupper($prefix).'-'.now()->year.'-';
        $last = Document::withTrashed()->where('ref_no', 'like', $base.'%')->lockForUpdate()->pluck('ref_no')
            ->map(fn ($v) => (int) substr($v, strlen($base)))->max() ?? 0;

        return $base.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
