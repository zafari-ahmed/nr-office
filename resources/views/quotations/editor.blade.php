<x-layouts.app :title="$quotation->exists ? 'Edit quotation' : 'New quotation'">
    @include('partials.doc-editor', [
        'doc' => $quotation, 'kind' => 'Quotation', 'number' => $quotation->quotation_number,
        'items' => $items, 'projects' => $projects,
        'action' => $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store'),
        'method' => $quotation->exists ? 'PUT' : 'POST',
    ])
</x-layouts.app>
