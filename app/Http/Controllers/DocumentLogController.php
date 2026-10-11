<?php

namespace App\Http\Controllers;

use App\Models\DocumentLog;
use App\Models\Project;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = DocumentLog::with('project', 'attachments', 'creator')
            ->when($request->type, fn ($q, $t) => $q->where('document_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project, fn ($q, $p) => $q->where('project_id', $p))
            ->latest('sent_date')->latest('id')->paginate(20)->withQueryString();

        return view('document-logs.index', [
            'logs' => $logs,
            'projects' => Project::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $log = DB::transaction(function () use ($data, $request) {
            $log = DocumentLog::create([...collect($data)->except('files')->all(), 'created_by' => $request->user()->id]);
            $log->attachFiles($request->file('files') ?? [], 'document');
            $this->syncFollowUp($log);

            return $log;
        });

        $type = DocumentLog::TYPES[$log->document_type];
        Activity::log('logged', "{$type} {$log->reference_no} logged as sent to ".($log->sent_to_name ?: $log->sent_to_email ?: 'client'), $log);

        return back()->with('success', "{$type} logged on the project timeline.");
    }

    public function show(DocumentLog $documentLog)
    {
        $documentLog->load('project', 'attachments', 'creator', 'followUp');

        return view('document-logs.show', ['log' => $documentLog]);
    }

    public function update(Request $request, DocumentLog $documentLog)
    {
        $data = $this->validated($request);
        $documentLog->update(collect($data)->except('files')->all());
        $documentLog->attachFiles($request->file('files') ?? [], 'document');
        $this->syncFollowUp($documentLog);

        return back()->with('success', 'Log updated.');
    }

    public function destroy(DocumentLog $documentLog)
    {
        $documentLog->followUp()?->where('status', 'pending')->delete();
        $documentLog->delete();

        return redirect()->route('document-logs.index')->with('success', 'Log removed.');
    }

    /** A next follow-up date on the log becomes (or updates) a project follow-up. */
    private function syncFollowUp(DocumentLog $log): void
    {
        if (! $log->next_follow_up_date) {
            return;
        }
        $log->followUp()->updateOrCreate([], [
            'project_id' => $log->project_id,
            'title' => 'Follow up on '.strtolower(DocumentLog::TYPES[$log->document_type]).' '.($log->reference_no ?? ''),
            'notes' => $log->last_discussion,
            'due_date' => $log->next_follow_up_date,
            'assigned_to' => $log->created_by,
            'created_by' => $log->created_by,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'document_type' => ['required', Rule::in(array_keys(DocumentLog::TYPES))],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'sent_date' => ['required', 'date'],
            'sent_to_name' => ['nullable', 'string', 'max:255'],
            'sent_to_email' => ['nullable', 'email', 'max:255'],
            'last_discussion' => ['nullable', 'string', 'max:10000'],
            'email_subject' => ['nullable', 'string', 'max:255'],
            'email_body' => ['nullable', 'string', 'max:100000'],
            'next_follow_up_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(DocumentLog::STATUSES))],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);
    }
}
