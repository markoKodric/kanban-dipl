<?php namespace Kanban\Custom\Components;

use Auth;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\DB;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Scopes\Unarchived;
use Kanban\Custom\Traits\RenderingHelpers;

class Archive extends ComponentBase
{
    use RenderingHelpers;

    public $user;

    public $projects;

    public $tickets;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Archive',
            'description' => 'Display archive on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->tickets = $this->initTickets();

        $this->projects = $this->initProjects();
    }

    public function onRestoreTicket()
    {
        DB::table(with(new Ticket)->getTable())->where('id', post('ticket'))->update([
            'is_archived' => false,
        ]);

        $this->tickets = $this->initTickets();

        return [
            '#js-tickets' => $this->renderPartial('@_tickets')
        ];
    }

    public function onRestoreProject()
    {
        DB::table(with(new Project)->getTable())->where('id', post('project'))->update([
            'is_archived' => false,
        ]);

        $this->projects = $this->initProjects();

        return [
            '#js-projects' => $this->renderPartial('@_projects')
        ];
    }

    public function onSearch()
    {
        $this->tickets = $this->initTickets(post('query'));

        $this->projects = $this->initProjects(post('query'));

        return [
            '#js-tickets' => $this->renderPartial('@_tickets'),
            '#js-projects' => $this->renderPartial('@_projects')
        ];
    }

    protected function initTickets($searchQuery = null)
    {
        $query = $this->user()->team->tickets()->archived();

        if ($searchQuery) {
            return $query->search($searchQuery)->get();
        }

        return $query->get();
    }

    protected function initProjects($searchQuery = null)
    {
        $query = $this->user()->team->projects()->archived();

        if ($searchQuery) {
            return $query->search($searchQuery)->get();
        }

        return $query->get();
    }

    protected function user()
    {
        return Auth::getUser();
    }
}
