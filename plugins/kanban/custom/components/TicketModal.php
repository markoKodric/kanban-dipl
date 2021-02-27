<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\TicketHandler;
use Kanban\Custom\Traits\RenderingHelpers;

class TicketModal extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, TicketHandler;

    public $ticket;

    public $user;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Ticket modal',
            'description' => 'Display ticket modal on page.'
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

        if (session()->get('ticket_modal')) {
            $this->ticket = Ticket::find(session()->get('ticket_modal'));
        }
    }

    public function onOpenTicket()
    {
        $this->onRun();

        $this->ticket = Ticket::find(post('ticket'));

        session()->put('ticket_modal', $this->ticket->id);

        if ($this->user->team->id != $this->ticket->project->team->id) {
            App::abort(403);
        }

        return [
            '#ticket-modal' => $this->renderPartial('ticketmodal/_ticket', ['ticket' => $this->ticket])
        ];
    }

    public function getProjectPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }
}
