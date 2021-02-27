<?php namespace Kanban\Custom\Components;

use Auth;
use Kanban\Custom\Models\Project;
use System\Models\File;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Page;
use Illuminate\Support\Facades\Hash;
use Kanban\Custom\Traits\RenderingHelpers;

class ActivityLog extends ComponentBase
{
    use RenderingHelpers;

    public $user;

    public $activities;

    public $users;

    public $projects;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Activity log',
            'description' => 'Display activity log on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = Auth::getUser();

        $this->users = $this->user->team->users;

        $this->projects = $this->user->team->projects;

        $this->activities = $this->initActivities();
    }

    public function onFilter()
    {
        session()->put('activity_log_filters', post('filters'));

        $this->onRun();

        return [
            '#js-activities' => $this->renderPartial('@_activities'),
        ];
    }

    public function onRefresh()
    {
        session()->put('activity_log_filters', []);

        $this->onRun();

        return [
            '#activity-log' => $this->renderPartial('@_log'),
        ];
    }

    protected function initActivities()
    {
        $filters = array_merge([
            "search"     => "",
            "user"       => "",
            "project"    => "",
            "start_date" => "",
            "end_date"   => "",
        ], session()->get('activity_log_filters', []));

        $this->activities = Project::whereIn('id', $this->user->team->projects()->select('id')->get()->pluck('id')->all())
            ->with([
                'activities' => function ($query) use ($filters) {
                    if ($searchQuery = $filters['search']) {
                        $query->where('description', 'like', '%' . $searchQuery . '%');
                    }

                    if ($user = $filters['user']) {
                        $query->where('user_id', $user);
                    }

                    if ($project = $filters['project']) {
                        $query->where('project_id', $project);
                    }

                    if ($startDate = $filters['start_date']) {
                        $query->where('created_at', '>=', $startDate);
                    }

                    if ($endDate = $filters['end_date']) {
                        $query->where('created_at', '<=', $endDate);
                    }
                }
            ])
            ->get()
            ->pluck('activities')
            ->flatten()
            ->sortByDesc('created_at');

        return $this->activities;
    }
}
