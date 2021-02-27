<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\TicketHandler;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class TicketSingle extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters, TicketHandler;

    protected $parameters = ['ticket?'];

    public $ticket;

    public $user;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Ticket',
            'description' => 'Display ticket on page.'
        ];
    }

    public function defineProperties()
    {
        return $this->withLayoutOptions([
            'projectPage' => [
                'title' => 'Select project page',
                'type'  => 'dropdown',
            ]
        ]);
    }

    public function onRun()
    {
        $this->user = Auth::getUser();

        if (!($this->ticket = Ticket::find($this->dynamicParam('ticket')))) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->ticket->project->team->id) {
            App::abort(403);
        }
    }

    public function getProjectPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }
}
