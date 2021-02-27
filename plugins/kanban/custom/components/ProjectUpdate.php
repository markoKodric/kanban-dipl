<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Page;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class ProjectUpdate extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project'];

    public $project;

    public $user;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Project update',
            'description' => 'Display project update form on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = User::where('id', Auth::getUser()->id)->with('team')->first();

        if ($projectId = $this->dynamicParam('project')) {
            $this->project = Project::find($projectId);
        } else {
            $this->project = Project::find(session()->get('currentProject')) ?? $this->user->team->defaultProject();

            if ($this->project) {
                return redirect($this->project->updateUrl());
            }
        }

        if (!$this->project) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->project->team->id) {
            App::abort(403);
        }

        session()->put('currentProject', $this->project->id);
    }

    public function onUpdateProject()
    {
        $requestValid = validate_request([
            'title'       => 'required|min:3',
            'description' => 'required|max:1024',
        ]);

        if (!$requestValid) {
            $this->onRun();

            return [
                '#js-project' => $this->renderPartial('@_form', ['formErrors' => session()->get('errors')])
            ];
        }

        if (filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN)) {
            Auth::getUser()->team->projects()->update([
                'is_default' => false
            ]);
        }

        $this->onRun();

        $this->project->forceFill([
            'title'          => request()->post('title'),
            'slug'           => Str::slug(request()->post('title')),
            'description'    => request()->post('description'),
            'picture'        => request()->post('avatar'),
            'start_date'     => empty(request()->post('start_date')) ? $this->project->start_date->toDateString() : request()->post('start_date', now()->toDateString()),
            'due_date'       => empty(request()->post('due_date')) ? null : request()->post('due_date', now()->toDateString()),
            'is_default'     => filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN) || Auth::getUser()->team->projects->isEmpty(),
            'client_name'    => request()->post('client_name'),
            'client_email'   => request()->post('client_email'),
            'client_phone'   => request()->post('client_phone'),
            'client_company' => request()->post('client_company'),
        ]);

        if(request()->file('picture')) {
            $fileName = explode('.', request()->file('picture')->getClientOriginalName());
            $fileName = $fileName[0] . time() . '_' . $this->project->id . '.' . $fileName[1];

            $file = new File([
                'data'      => request()->file('picture'),
                'title'     => $fileName,
                'is_public' => true,
            ]);

            $file->save();

            $this->project->picture()->add($file);
        }

        $this->project->save();

        $this->project->refresh();

        return redirect()->refresh();
    }

    public function onArchiveProject()
    {
        $this->onRun();

        $this->project->update([
            'is_archived' => true,
        ]);

        return redirect()->to(Page::url('archive'));
    }

    public function onDeleteProject()
    {
        $this->onRun();

        $this->project->tickets->each(function ($ticket) {
            $ticket->comments()->delete();
            $ticket->files->each(function ($file) {
                unlink($file->getLocalPath());
            });
            $ticket->files()->delete();
            $ticket->checklists->each(function($checklist) {
                $checklist->items()->delete();
            });
            $ticket->checklists()->delete();
            $ticket->timers()->delete();
            $ticket->users()->detach();
            $ticket->tags()->detach();

            $ticket->delete();
        });

        $this->project->flow->sections->each(function ($section) {
            $section->subsections()->delete();
        });

        $this->project->flow->sections()->delete();

        $this->project->flow()->delete();
        $this->project->users()->detach();
        $this->project->tags()->delete();

        $this->project->delete();

        session()->forget('currentProject');

        return redirect()->to('/');
    }
}
