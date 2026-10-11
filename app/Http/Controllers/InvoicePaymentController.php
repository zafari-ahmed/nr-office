<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        abort_if(in_array($invoice->status, ['draft', 'cancelled']), 422, 'Send the invoice before recording payments.');

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$invoice->balance_due],
            'method' => ['required', Rule::in(array_keys(InvoicePayment::METHODS))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice->payments()->create([...$data, 'received_by' => $request->user()->id]);
        $invoice->refreshPaymentStatus();
        Activity::log('payment_recorded', 'Payment of '.money($data['amount'])." received on {$invoice->invoice_number}", $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Payment recorded.');
    }

    public function destroy(InvoicePayment $payment)
    {
        $invoice = $payment->invoice;
        $payment->delete();
        $invoice->refreshPaymentStatus();

        return back()->with('success', 'Payment removed.');
    }
}
