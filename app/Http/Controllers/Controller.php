<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Stops people opening projects they can't see (not on the team, no "See all projects"). */
    protected function ensureCanSeeProject(?\App\Models\Project $project): void
    {
        abort_unless($project && auth()->user()?->canSeeProject($project), 403, 'You are not on this project\'s team.');
    }
    //
}
