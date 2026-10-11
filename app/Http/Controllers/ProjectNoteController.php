<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $project->notes()->create([...$data, 'user_id' => $request->user()->id]);

        return redirect()->route('projects.show', [$project, 'tab' => 'notes'])->with('success', 'Note added.');
    }

    public function pin(ProjectNote $note)
    {
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back();
    }

    public function destroy(ProjectNote $note)
    {
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}
