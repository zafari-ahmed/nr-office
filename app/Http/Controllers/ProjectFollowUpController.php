<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFollowUp;
use App\Support\Activity;
use Illuminate\Http\Request;

class ProjectFollowUpController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $this->ensureCanSeeProject($project);
        $data = $this->validated($request);
        $followUp = $project->followUps()->create([...$data, 'created_by' => $request->user()->id]);
        Activity::log('follow_up_added', "Follow-up added: {$followUp->title}", $followUp, $project->id);

        return back()->with('success', 'Follow-up added.');
    }

    /** Quick-add from the top bar "+ New" menu, project chosen in the drawer. */
    public function storeAny(Request $request)
    {
        $request->validate(['project_id' => ['required', 'exists:projects,id']]);

        return $this->store($request, Project::findOrFail($request->project_id));
    }

    public function done(ProjectFollowUp $followUp)
    {
        $this->ensureCanSeeProject($followUp->project);
        $followUp->update(['status' => 'done', 'completed_at' => now()]);
        Activity::log('follow_up_done', "Follow-up done: {$followUp->title}", $followUp, $followUp->project_id);

        return back()->with('success', 'Follow-up marked done.');
    }

    public function snooze(Request $request, ProjectFollowUp $followUp)
    {
        $this->ensureCanSeeProject($followUp->project);
        $days = (int) $request->input('days', 2);
        $newDate = $followUp->due_date->isPast() ? today()->addDays($days) : $followUp->due_date->copy()->addDays($days);
        $followUp->update(['due_date' => $newDate, 'snoozed_until' => $newDate]);

        return back()->with('success', 'Snoozed to '.$newDate->format('d M').'.');
    }

    public function destroy(ProjectFollowUp $followUp)
    {
        $this->ensureCanSeeProject($followUp->project);
        $followUp->delete();

        return back()->with('success', 'Follow-up removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['required', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
    }
}
