<?php

namespace App\Support;

use App\Models\Project;

class ProjectContext
{
    public static function current(): ?Project
    {
        if (request()->boolean('clear_project')) {
            session()->forget('ui.project_id');
            return null;
        }

        $routeProject = request()->route('project');
        $id = $routeProject instanceof Project ? $routeProject->id : request()->query('project_id', session('ui.project_id'));
        $project = is_string($id) ? Project::find($id) : null;

        if ($project) {
            session(['ui.project_id' => $project->id]);
        } else {
            session()->forget('ui.project_id');
        }

        return $project;
    }

    public static function id(): ?string
    {
        return self::current()?->id;
    }

    public static function url(string $route, ?string $id = null): string
    {
        if ($route === 'dashboard') {
            return route($route);
        }

        $id ??= self::id();
        return route($route, $id ? ['project_id' => $id] : []);
    }
}
