<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use System\Models\File;
use Illuminate\Support\Str;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Page;
use Kanban\Custom\Scopes\Unarchived;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class Settings extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['settings?'];

    public $user;

    /*
     * Dynamic parameters variables
     */
    public $settings;
    public $project;
    public $settingSegments = [
        'permissions' => ['permissions', 'Roles & Permissions', 'la la-users-cog'],
        'projects'    => ['projects', 'Projects', 'la la-list'],
        'general'     => ['general', 'General', 'la la-cogs'],
    ];

    /*
     * Permission variables
     */
    public $users;
    public $permissionsUser;
    public $permissionsProject;
    public $permissionList = [
        'projects.manage'       => 'Manage projects (create, edit, delete)',

        'board.workflow.edit'   => 'Edit workflow',
        'board.users.add'       => 'Add users',
        'board.tickets.add'     => 'Add tickets',
        'board.tickets.reorder' => 'Reorder tickets',
        //'board.sections.manage' => 'Manage sections',

        'ticket.users.add'      => 'Add users',
        'ticket.tags.add'       => 'Add tags',
        'ticket.edit'           => 'Edit ticket',
        'ticket.delete'         => 'Delete ticket',
    ];

    /*
     * Projects variables
     */
    public $projects;
    public $projectsProject;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Settings',
            'description' => 'Display settings on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = Auth::getUser();

        $this->initSettings();
    }

    public function onUpdateUserPermissions()
    {
        $this->onRun();

        $newPermissions = array_keys(request()->except(['permissions_user', 'permissions_project', 'sections']));

        $newPermissions = array_map(function ($item) {
            return str_replace('_', '.', $item);
        }, $newPermissions);

        $this->user->team->users()->where('id', post('permissions_user'))->update([
            'permissions' => !empty($newPermissions) ? json_encode($newPermissions) : null,
        ]);

        $projectSections = array_diff($this->permissionsProject->flow->sections()->doesntHave('subsections')->get()->pluck('id')->all(), post('sections'));

        $sections = [];
        foreach ($projectSections as $section) {
            $sections[$section] = ['project_id' => $this->permissionsProject->id];
        }

        $this->permissionsUser->restrictions()->wherePivot('project_id', $this->permissionsProject->id)->sync($sections);
    }

    public function onPermissionsChangeUser()
    {
        session()->put('settings_permission_user', post('permissions_user'));
        session()->forget('settings_permissions_project');

        $this->onRun();

        return [
            '#js-settings' => $this->renderPartial('@_permissions')
        ];
    }

    public function onPermissionsChangeProject()
    {
        session()->put('settings_permissions_project', post('permissions_project'));

        $this->onRun();

        return [
            '#js-permission-list' => $this->renderPartial('@__permission_list')
        ];
    }

    public function onResetUserPermissions()
    {
        $this->user = Auth::getUser();

        $this->user->team->users()->where('id', post('permissions_user'))->update([
            'permissions' => null,
        ]);

        $this->onRun();

        $this->permissionsUser = $this->users->where('id', post('permissions_user'))->first();

        $this->permissionsUser->restrictions()->sync([]);

        return [
            '#js-permission-list' => $this->renderPartial('@__permission_list')
        ];
    }

    public function onProjectsChangeProject()
    {
        $this->onRun();

        session()->put('settings_projects_project', post('projects_project'));

        $this->projectsProject = $this->projects->where('id', post('projects_project'))->first();

        return [
            '#js-settings-projects' => $this->renderPartial('@_projects')
        ];
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
                '#js-project' => $this->renderPartial('@__project_settings', ['formErrors' => session()->get('errors')])
            ];
        }

        if (filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN)) {
            Auth::getUser()->team->projects()->update([
                'is_default' => false
            ]);
        }

        $this->onRun();

        $this->projectsProject->forceFill([
            'title'          => request()->post('title'),
            'slug'           => Str::slug(request()->post('title')),
            'description'    => request()->post('description'),
            'picture'        => request()->post('avatar'),
            'start_date'     => empty(request()->post('start_date')) ? $this->projectsProject->start_date->toDateString() : request()->post('start_date', now()->toDateString()),
            'due_date'       => empty(request()->post('due_date')) ? null : request()->post('due_date', now()->toDateString()),
            'is_default'     => filter_var(request()->post('is_default'), FILTER_VALIDATE_BOOLEAN) || Auth::getUser()->team->projects->isEmpty(),
            'client_name'    => request()->post('client_name'),
            'client_email'   => request()->post('client_email'),
            'client_phone'   => request()->post('client_phone'),
            'client_company' => request()->post('client_company'),
        ]);

        if(request()->file('picture')) {
            $fileName = explode('.', request()->file('picture')->getClientOriginalName());
            $fileName = $fileName[0] . time() . '_' . $this->projectsProject->id . '.' . $fileName[1];

            $file = new File([
                'data'      => request()->file('picture'),
                'title'     => $fileName,
                'is_public' => false,
            ]);

            $file->save();

            $this->projectsProject->picture()->add($file);
        }

        $this->projectsProject->save();

        $this->projectsProject->refresh();

        return redirect()->refresh();
    }

    public function onArchiveProject()
    {
        $this->onRun();

        $this->projects->where('id', post('projects_project'))->first()->update([
            'is_archived' => true,
        ]);

        return redirect()->refresh();
    }

    public function onDeleteProject()
    {
        $this->onRun();

        $project = $this->projects->where('id', post('projects_project'))->first();

        $project->tickets->each(function ($ticket) {
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

        $project->flow->sections->each(function ($section) {
            $section->subsections()->delete();
        });

        $project->flow->sections()->delete();

        $project->flow()->delete();
        $project->users()->detach();
        $project->tags()->delete();

        $project->delete();

        return redirect()->refresh();
    }

    public function onUpdateGeneralSettings()
    {
        $this->onRun();

        $settings = $this->user->team->settings ?? [];

        $settings['general']['ticket_modal'] = filter_var(post('ticket_modal'), FILTER_VALIDATE_BOOLEAN);

        $this->user->team->update([
            'settings' => $settings,
        ]);
    }

    public function settingsUrl($segment)
    {
        return Page::url('settings') . '/' . $this->settingSegments[$segment][0];
    }

    protected function initSettings()
    {
        $this->settings = $this->dynamicParam('settings');

        switch ($this->settings) {
            case null:
            case 'permissions':
                return $this->initPermissions();
            case 'projects':
                return $this->initProjects();
        }
    }

    protected function initPermissions()
    {
        $this->users = $this->user->team->users->sortBy('name');

        $this->permissionsUser = $this->users->where('id', session()->get('settings_permission_user'))->first() ?? $this->users->first();

        $this->projects = $this->user->team->projects()->whereHas('users', function ($query) {
            $query->where('id', $this->permissionsUser->id);
        })->withoutGlobalScope(Unarchived::class)->orderBy('title')->get();

        $projectId = session()->get('settings_permissions_project', -1);

        $this->permissionsProject = $this->projects->where('id', $projectId)->first() ?? null;
    }

    protected function initProjects()
    {
        $this->projects = $this->user->team->projects()->withoutGlobalScope(Unarchived::class)->orderBy('title')->get();

        $projectId = session()->get('settings_projects_project', session()->get('currentProject', -1));

        $this->projectsProject = $this->projects->where('id', $projectId)->first() ?? $this->projects->first();
    }
}
