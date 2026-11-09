<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Support\RichText;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $this->ensureCanSeeProject($project);
        $request->validate(['body' => ['required', 'string', 'max:500000']]);
        $body = RichText::clean($request->input('body'));
        if (RichText::isEmpty($body)) {
            return back()->withErrors(['body' => 'Write something before adding the note.']);
        }
        $project->notes()->create(['body' => $body, 'user_id' => $request->user()->id]);

        return redirect()->route('projects.show', [$project, 'tab' => 'notes'])->with('success', 'Note added.');
    }

    public function pin(ProjectNote $note)
    {
        $this->ensureCanSeeProject($note->project);
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back();
    }

    public function destroy(ProjectNote $note)
    {
        $this->ensureCanSeeProject($note->project);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}
