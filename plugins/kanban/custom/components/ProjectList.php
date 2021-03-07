<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;

class ProjectList extends ComponentBase
{
    use RenderingHelpers, MenuHelpers;

    public $projects;

    public $currentProject;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Project list',
            'description' => 'Display project list on page.'
        ];
    }

    public function defineProperties()
    {
        return [
            'projectPage' => [
                'title' => 'Select project page',
                'type' => 'dropdown',
            ]
        ];
    }

    public function onRun()
    {
        if (!Auth::check()) return;

        $this->projects = Auth::getUser()->team()->with('projects')->first()->projects()->unarchived()->with('picture')->get();

        $this->currentProject = $this->projects->where('id', session()->get('currentProject'))->first() ?? $this->projects->where('is_default', 1)->first() ?? $this->projects->first();

        if ($this->currentProject && url()->current() == url('/')) {
            return redirect($this->currentProject->url());
        }
    }

    public function onCreateProject()
    {
        $requestValid = validate_request([
            'title'       => 'required|min:3',
            'description' => 'required|max:1024',
        ]);

        if (!$requestValid) {
            $this->onRun();

            return [
                '#js-project-form' => $this->renderPartial('@_form', ['formErrors' => session()->get('errors')])
            ];
        }

        if (!Auth::getUser()->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        if (filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN)) {
            Auth::getUser()->team->projects()->update([
                'is_default' => false
            ]);
        }

        $project = Auth::getUser()->team->projects()->forceCreate([
            'team_id'      => Auth::getUser()->team->id,
            'title'        => request()->post('title'),
            'slug'         => Str::slug(request()->post('title')),
            'description'  => request()->post('description'),
            'picture'      => request()->post('avatar'),
            'start_date'   => empty(request()->post('start_date')) ? now()->toDateString() : request()->post('start_date', now()->toDateString()),
            'due_date'     => empty(request()->post('due_date')) ? null : request()->post('due_date', now()->toDateString()),
            'sort_order'   => Auth::getUser()->team->projects->count() + 1,
            'is_started'   => false,
            'is_active'    => true,
            'is_default'   => filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN) || Auth::getUser()->team->projects->isEmpty(),
            'is_completed' => false,
        ]);

        $project->users()->attach(Auth::getUser()->id);

        session()->put('currentProject', $project->id);

        return redirect($project->url());
    }

    public function onSearch()
    {
        $this->projects = Auth::getUser()->team()->with('projects')->first()->projects()->unarchived()->search(post('query'))->get();

        return [
            '#js-project-list' => $this->renderPartial('@default', [
                'projects' => $this->projects
            ])
        ];
    }

    public function getProjectPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }
}
