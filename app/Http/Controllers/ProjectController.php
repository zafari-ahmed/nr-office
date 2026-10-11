<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Support\Activity;
use App\Support\Numbering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->query('view', 'table');
        $query = Project::with(['client', 'nextFollowUp', 'manager'])
            ->withSum(['invoices as outstanding' => fn ($q) => $q->whereNotIn('status', ['draft', 'cancelled'])], 'balance_due')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('company_name', 'like', "%$s%"))))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest();

        $projects = $view === 'board' ? $query->get() : $query->paginate(20)->withQueryString();
        $counts = Project::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalValue = (float) Project::whereNot('status', 'cancelled')->sum('project_value');

        return view('projects.index', compact('projects', 'view', 'counts', 'totalValue'));
    }

    public function create()
    {
        return view('projects.form', [
            'project' => new Project(['status' => 'lead', 'code' => Numbering::project()]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['client_id'] = $this->resolveClient($request, $data['client_id'] ?? null);
        unset($data['new_client_name'], $data['new_client_phone']);
        $data['project_value'] ??= 0;
        $data['code'] = Numbering::project();
        $project = Project::create($data);
        Activity::log('created', "Project {$project->name} created", $project, $project->id);

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project)
    {
        $project->load([
            'client', 'manager',
            'followUps' => fn ($q) => $q->with('assignee')->orderByRaw("status = 'done'")->orderBy('due_date'),
            'quotations' => fn ($q) => $q->latest('issue_date')->latest('revision'),
            'invoices' => fn ($q) => $q->latest('issue_date'),
            'expenses' => fn ($q) => $q->with('head')->latest('expense_date'),
            'siteVisits' => fn ($q) => $q->with('employee')->latest('departure_date'),
            'documentLogs' => fn ($q) => $q->with('attachments', 'creator')->latest('sent_date'),
            'notes.user', 'attachments.uploader',
        ]);

        // Timeline merges activity log entries with "Logged" document cards.
        $timeline = $project->activities()->with('user')->where('action', '!=', 'logged')->limit(40)->get()->toBase()
            ->map(fn ($a) => ['kind' => 'activity', 'at' => $a->created_at, 'item' => $a])
            ->merge($project->documentLogs->map(fn ($d) => ['kind' => 'log', 'at' => $d->sent_date->copy()->setTimeFrom($d->created_at), 'item' => $d]))
            ->sortByDesc('at')->values();

        return view('projects.show', [
            'project' => $project,
            'kpis' => $project->kpis(),
            'timeline' => $timeline,
            'tab' => $request->query('tab', 'overview'),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['project' => $project, ...$this->formData()]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project);
        $data['client_id'] = $this->resolveClient($request, $data['client_id'] ?? null);
        unset($data['new_client_name'], $data['new_client_phone']);
        $data['project_value'] ??= 0;
        $oldStatus = $project->status;
        $project->update($data);
        if ($oldStatus !== $project->status) {
            Activity::log('status_changed', 'Status changed to '.Project::STATUSES[$project->status], $project, $project->id);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project updated.');
    }

    public function updateStatus(Request $request, Project $project)
    {
        $request->validate(['status' => ['required', Rule::in(array_keys(Project::STATUSES))]]);
        $project->update(['status' => $request->status]);
        Activity::log('status_changed', 'Status changed to '.Project::STATUSES[$project->status], $project, $project->id);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Status updated.');
    }

    public function uploadFiles(Request $request, Project $project)
    {
        $request->validate(['files' => ['required', 'array'], 'files.*' => ['file', 'max:20480']]);
        $project->attachFiles($request->file('files'));
        Activity::log('files_uploaded', count($request->file('files')).' file(s) uploaded', $project, $project->id);

        return redirect()->route('projects.show', [$project, 'tab' => 'files'])->with('success', 'Files uploaded.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project archived.');
    }

    private function formData(): array
    {
        return [
            'clients' => Client::orderBy('name')->get(),
            'managers' => User::whereIn('role', ['admin', 'manager'])->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client_name' => ['nullable', 'string', 'max:255'],
            'new_client_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'project_value' => ['nullable', 'numeric', 'min:0'],
            'site_address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** Lets the project form create a client inline. */
    private function resolveClient(Request $request, ?int $clientId): ?int
    {
        if (! $clientId && $request->filled('new_client_name')) {
            return Client::create(['name' => $request->new_client_name, 'phone' => $request->new_client_phone])->id;
        }

        return $clientId;
    }
}
