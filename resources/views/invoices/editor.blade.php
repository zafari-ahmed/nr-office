<x-layouts.app full :title="$invoice->exists ? 'Edit invoice' : 'New invoice'">
    @include('partials.doc-editor', [
        'doc' => $invoice, 'kind' => 'Invoice', 'number' => $invoice->invoice_number,
        'items' => $items, 'projects' => $projects,
        'action' => $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store'),
        'method' => $invoice->exists ? 'PUT' : 'POST',
    ])
</x-layouts.app>
