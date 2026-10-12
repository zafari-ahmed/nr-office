<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DocumentLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment)
    {
        abort_unless($this->allowed(auth()->user(), $attachment, false), 403);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return request()->boolean('inline')
            ? $disk->response($attachment->path, $attachment->original_name)
            : $disk->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        abort_unless($this->allowed(auth()->user(), $attachment, true), 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'File removed.');
    }

    /** A file follows the rights of the record it is attached to. */
    private function allowed(User $user, Attachment $attachment, bool $change): bool
    {
        $owner = $attachment->attachable;

        return match (true) {
            $owner instanceof Project => $user->canSeeProject($owner),
            $owner instanceof Employee => $change ? $user->hasPermission('employees.manage') : ($user->hasPermission('employees.view') || $user->employee_id === $owner->id),
            $owner instanceof Expense => $user->hasPermission('expenses.approve') || ($user->ownsExpense($owner) && (! $change || $owner->status === 'pending')) || (! $change && $user->hasPermission('expenses.view_all')),
            $owner instanceof Quotation, $owner instanceof DocumentLog => $user->hasPermission($change ? 'quotations.manage' : 'quotations.view'),
            $owner instanceof Invoice => $user->hasPermission($change ? 'invoices.manage' : 'invoices.view'),
            default => $user->isAdmin(),
        };
    }
}
