<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with('project', 'client')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->q, fn ($q, $s) => $q->where('invoice_number', 'like', "%$s%"))
            ->latest('issue_date')->latest('id')->paginate(20)->withQueryString();

        $summary = Invoice::selectRaw('status, count(*) as total, sum(total) as amount, sum(balance_due) as balance')
            ->groupBy('status')->get()->keyBy('status');

        return view('invoices.index', compact('invoices', 'summary'));
    }

    public function create(Request $request)
    {
        $invoice = new Invoice([
            'invoice_number' => Numbering::invoice(),
            'project_id' => $request->query('project'),
            'issue_date' => today(),
            'due_date' => today()->addDays(Setting::int('finance.payment_terms_days')),
            'discount_type' => 'amount',
            'discount_value' => 0,
            'tax_rate' => Setting::get('finance.tax_rate'),
            'status' => 'draft',
        ]);

        return view('invoices.editor', ['invoice' => $invoice, 'items' => [], 'projects' => $this->projects()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $invoice = DB::transaction(function () use ($data, $request) {
            $invoice = Invoice::create([
                ...collect($data)->except('items', 'action')->all(),
                'invoice_number' => Numbering::invoice(),
                'client_id' => Project::find($data['project_id'])->client_id,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $invoice->syncItems($data['items']);

            return $invoice;
        });
        Activity::log('created', "Invoice {$invoice->invoice_number} created · ".money($invoice->total), $invoice);
        $this->applyAction($request, $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice saved.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('project.client', 'items', 'payments.receiver', 'quotation');

        return view('invoices.show', ['invoice' => $invoice, 'methods' => InvoicePayment::METHODS]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load('project.client', 'items', 'payments');

        return view('quotations.print', ['doc' => $invoice, 'kind' => 'Invoice']);
    }

    public function edit(Invoice $invoice)
    {
        abort_unless($invoice->isEditable(), 403, 'Invoices with payments cannot be edited.');

        return view('invoices.editor', [
            'invoice' => $invoice,
            'items' => $invoice->items->map->only('description', 'quantity', 'unit', 'rate')->all(),
            'projects' => $this->projects(),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->isEditable(), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($invoice, $data) {
            $invoice->update(collect($data)->except('items', 'action')->all());
            $invoice->syncItems($data['items']);
        });
        Activity::log('updated', "Invoice {$invoice->invoice_number} updated · ".money($invoice->total), $invoice);
        $this->applyAction($request, $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice saved.');
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $request->validate(['status' => ['required', Rule::in(['sent', 'cancelled', 'draft'])]]);
        $invoice->update(['status' => $request->status, 'sent_at' => $request->status === 'sent' ? ($invoice->sent_at ?? now()) : $invoice->sent_at]);
        if ($request->status === 'sent') {
            $invoice->refreshPaymentStatus();
        }
        Activity::log('status_changed', "Invoice {$invoice->invoice_number} marked ".Invoice::STATUSES[$invoice->status], $invoice);

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice)
    {
        abort_if($invoice->payments()->exists(), 403, 'Remove payments before deleting this invoice.');
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    private function applyAction(Request $request, Invoice $invoice): void
    {
        if ($request->input('action') === 'send' && $invoice->status === 'draft') {
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);
            $invoice->refreshPaymentStatus();
            Activity::log('status_changed', "Invoice {$invoice->invoice_number} sent", $invoice);
        }
    }

    private function projects()
    {
        return Project::with('client')->where('status', '!=', 'cancelled')->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
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
