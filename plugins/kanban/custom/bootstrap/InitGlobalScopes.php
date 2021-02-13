<?php namespace Kanban\Custom\Bootstrap;

use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Scopes\Unarchived;

class InitGlobalScopes
{
    public function init()
    {
        $this->initProject();
        $this->initTicket();
    }

    protected function initProject()
    {
        Project::addGlobalScope(new Unarchived());
    }

    protected function initTicket()
    {
        Ticket::addGlobalScope(new Unarchived());
    }
}