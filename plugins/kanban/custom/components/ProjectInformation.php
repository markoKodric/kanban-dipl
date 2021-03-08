<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Kanban\Custom\Classes\Notification;
use System\Models\File;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Page;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class ProjectInformation extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project', 'segment?'];

    public $project;

    public $user;

    public $segment;

    public $segments = [
        'documentation' => ['documentation', 'Documentation', 'la la-book'],
        'update'  => ['update', 'Update information', 'la la-pencil-alt'],
    ];

    public $editMode = false;

    public $document;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Project information',
            'description' => 'Display project information on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = User::where('id', Auth::getUser()->id)->with('team')->first();

        $this->segment = $this->dynamicParam('segment');

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

        $this->initData();
    }

    public function infoUrl($segment)
    {
        return Page::url('project-information') . '/' . $this->project->id . '/' . $this->segments[$segment][0];
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
                '#js-project' => $this->renderPartial('@_update', ['formErrors' => session()->get('errors')])
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

    public function onCreateDocument()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $this->document = $this->project->documents()->create([
            'title' => post('title'),
            'creator_id' => Auth::getUser()->id,
            'last_user_id' => Auth::getUser()->id,
        ]);

        $this->project->refresh();

        $this->editMode = true;

        return [
            '#js-information' => $this->renderPartial('@_documentation')
        ];
    }

    public function onSaveDocument()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $this->document->update([
            'content' => post('content'),
            'last_user_id' => Auth::getUser()->id,
        ]);
    }

    public function onChangeDocument()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        session()->put('documentation_document', post('document'));

        $this->onRun();

        return [
            '#js-document' => $this->renderPartial('@__document')
        ];
    }

    public function onDeleteDocument()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        session()->forget('documentation_document');

        $this->document->delete();

        $this->onRun();

        return [
            '#js-information' => $this->renderPartial('@_documentation')
        ];
    }

    public function onStartEditMode()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        if (!is_null($this->document->editing_user_id)) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('User ' . User::find($this->document->editing_user_id)->name . ' is already editing this document.')])
            ];
        }

        $this->document->update([
            'editing_user_id' => Auth::getUser()->id,
        ]);

        $this->editMode = true;

        return [
            '#js-document' => $this->renderPartial('@__document')
        ];
    }

    public function onStopEditMode()
    {
        $this->onRun();

        if (!$this->user->can('projects-manage-projects')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $this->document->update([
            'editing_user_id' => null,
        ]);

        $this->editMode = false;

        return [
            '#js-document' => $this->renderPartial('@__document')
        ];
    }

    protected function initData()
    {
        if (!$this->segment || $this->segment == 'documentation') {
            $this->document = $this->project->documents()->where('id', session()->get('documentation_document'))->first() ?? $this->project->documents->first();
        }
    }
}
