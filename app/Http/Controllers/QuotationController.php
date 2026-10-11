<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $quotations = Quotation::with('project', 'client')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'revised'))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('quotation_number', 'like', "%$s%")->orWhere('title', 'like', "%$s%")))
            ->latest('issue_date')->latest('id')->paginate(20)->withQueryString();

        $counts = Quotation::selectRaw('status, count(*) as total, sum(total) as amount')->groupBy('status')->get()->keyBy('status');

        return view('quotations.index', compact('quotations', 'counts'));
    }

    public function create(Request $request)
    {
        $quotation = new Quotation([
            'quotation_number' => Numbering::quotation(),
            'revision' => 1,
            'project_id' => $request->query('project'),
            'issue_date' => today(),
            'valid_until' => today()->addDays(30),
            'discount_type' => 'amount',
            'discount_value' => 0,
            'tax_rate' => Setting::get('finance.tax_rate'),
            'terms' => Setting::get('finance.quotation_terms'),
            'status' => 'draft',
        ]);

        return view('quotations.editor', ['quotation' => $quotation, 'items' => [], 'projects' => $this->projects()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $quotation = DB::transaction(function () use ($data, $request) {
            $project = Project::findOrFail($data['project_id']);
            $quotation = Quotation::create([
                ...collect($data)->except('items', 'action')->all(),
                'quotation_number' => Numbering::quotation(),
                'revision' => 1,
                'client_id' => $project->client_id,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $quotation->syncItems($data['items']);

            return $quotation;
        });

        Activity::log('created', "Quotation {$quotation->label()} created · ".money($quotation->total), $quotation);
        $this->applyAction($request, $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation saved.');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('project.client', 'items', 'revisions', 'invoice', 'creator');

        return view('quotations.show', compact('quotation'));
    }

    public function print(Quotation $quotation)
    {
        $quotation->load('project.client', 'items');

        return view('quotations.print', ['doc' => $quotation, 'kind' => 'Quotation']);
    }

    public function edit(Quotation $quotation)
    {
        abort_unless($quotation->isEditable(), 403, 'Only draft or sent quotations can be edited. Create a revision instead.');

        return view('quotations.editor', [
            'quotation' => $quotation,
            'items' => $quotation->items->map->only('description', 'quantity', 'unit', 'rate')->all(),
            'projects' => $this->projects(),
        ]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->isEditable(), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($quotation, $data) {
            $quotation->update(collect($data)->except('items', 'action')->all());
            $quotation->syncItems($data['items']);
        });
        Activity::log('updated', "Quotation {$quotation->label()} updated · ".money($quotation->total), $quotation);
        $this->applyAction($request, $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation saved.');
    }

    public function updateStatus(Request $request, Quotation $quotation)
    {
        $request->validate(['status' => ['required', Rule::in(['sent', 'accepted', 'rejected', 'expired', 'draft'])]]);
        $status = $request->status;
        $quotation->update([
            'status' => $status,
            'sent_at' => $status === 'sent' ? ($quotation->sent_at ?? now()) : $quotation->sent_at,
            'accepted_at' => $status === 'accepted' ? now() : $quotation->accepted_at,
        ]);
        if ($status === 'accepted' && in_array($quotation->project->status, ['lead', 'quoted'])) {
            $quotation->project->update(['status' => 'active']);
        }
        if ($status === 'sent' && $quotation->project->status === 'lead') {
            $quotation->project->update(['status' => 'quoted']);
        }
        Activity::log('status_changed', "Quotation {$quotation->label()} marked ".Quotation::STATUSES[$status], $quotation);

        return back()->with('success', 'Quotation marked '.strtolower(Quotation::STATUSES[$status]).'.');
    }

    /** Create Rev N+1 as an editable draft copy; the old revision is kept as history. */
    public function revise(Quotation $quotation)
    {
        $new = DB::transaction(function () use ($quotation) {
            $latest = Quotation::where('quotation_number', $quotation->quotation_number)->max('revision');
            $new = $quotation->replicate(['sent_at', 'accepted_at', 'invoice_id']);
            $new->fill(['revision' => $latest + 1, 'parent_id' => $quotation->id, 'status' => 'draft', 'issue_date' => today(), 'created_by' => auth()->id()]);
            $new->save();
            foreach ($quotation->items as $item) {
                $new->items()->create($item->only('sort_order', 'description', 'quantity', 'unit', 'rate', 'amount'));
            }
            $quotation->update(['status' => 'revised']);

            return $new;
        });
        Activity::log('revised', "Quotation revised to {$new->label()}", $new);

        return redirect()->route('quotations.edit', $new)->with('success', "Revision {$new->revision} created.");
    }

    public function convert(Quotation $quotation)
    {
        abort_unless($quotation->status === 'accepted' && ! $quotation->invoice_id, 403, 'Only accepted quotations can be converted once.');

        $invoice = DB::transaction(function () use ($quotation) {
            $invoice = Invoice::create([
                'invoice_number' => Numbering::invoice(),
                'project_id' => $quotation->project_id,
                'client_id' => $quotation->client_id,
                'quotation_id' => $quotation->id,
                'issue_date' => today(),
                'due_date' => today()->addDays(Setting::int('finance.payment_terms_days')),
                'status' => 'draft',
                'discount_type' => $quotation->discount_type,
                'discount_value' => $quotation->discount_value,
                'tax_rate' => $quotation->tax_rate,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'created_by' => auth()->id(),
            ]);
            $invoice->syncItems($quotation->items->map->only('description', 'quantity', 'unit', 'rate')->all());
            $quotation->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
        Activity::log('converted', "Quotation {$quotation->label()} converted to invoice {$invoice->invoice_number}", $invoice);

        return redirect()->route('invoices.edit', $invoice)->with('success', 'Invoice created from quotation. Review and send.');
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    private function applyAction(Request $request, Quotation $quotation): void
    {
        if ($request->input('action') === 'send' && $quotation->status === 'draft') {
            $quotation->update(['status' => 'sent', 'sent_at' => now()]);
            if ($quotation->project->status === 'lead') {
                $quotation->project->update(['status' => 'quoted']);
            }
            Activity::log('status_changed', "Quotation {$quotation->label()} sent", $quotation);
        }
    }

    private function projects()
    {
        return Project::with('client')->whereNotIn('status', ['completed', 'cancelled'])->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_type' => ['required', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'action' => ['nullable', 'string'],
        ]);
        $data['discount_value'] ??= 0;
        $data['tax_rate'] ??= 0;

        return $data;
    }
}
