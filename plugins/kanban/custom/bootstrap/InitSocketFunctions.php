<?php namespace Kanban\Custom\Bootstrap;

use Event;
use Illuminate\Support\Str;
use Kanban\Custom\Classes\Notification;
use Kanban\Custom\Models\Checklist;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\FlowSection;
use RainLab\User\Models\User;

class InitSocketFunctions
{
    protected $controller;

    public function init()
    {
        Event::listen('cms.ajax.beforeRunHandler', function ($controller, $handler) {
            $this->controller = $controller;

            if ($handler == 'onSocketEvent' && ($ticket = post('ticket')) && ($element = post('element')) && ($partial = post('partial'))) {
                return $this->handleTicketUpdate($ticket, $element, $partial);
            }

            if ($handler == 'onSocketEvent' && ($section = post('section')) && ($element = post('element')) && ($partial = post('partial'))) {
                return $this->handleSectionUpdate($section, $element, $partial);
            }

            if ($handler == 'onSocketEvent' && ($checklist = post('checklist')) && ($element = post('element')) && ($partial = post('partial'))) {
                return $this->handleChecklistUpdate($checklist, $element, $partial);
            }

            if ($handler == 'onSocketEvent' && ($project = post('project')) && ($flow = post('flow'))) {
                return $this->handleFlowUpdate($project);
            }

            if ($handler == 'onSocketEvent' && ($project = post('project')) && ($element = post('element')) && ($partial = post('partial'))) {
                return $this->handleBoardUpdate($project, $element, $partial);
            }
        });
    }

    protected function handleTicketUpdate($ticket, $element, $partial)
    {
        $user = User::find(post('uid'));

        $ticket = Ticket::find($ticket);

        $notification = post('notification');

        if ($notification == 'stop') {
            return [
                $element => $this->controller->renderPartial($partial, [
                    'ticket' => $ticket
                ]),
            ];
        }

        $notificationMessage = !empty($notification) && $notification != 'false' ? $notification : $user->name . ' updated ticket: ' . $ticket->name;

        return [
            $element => $this->controller->renderPartial($partial, [
                'ticket' => $ticket
            ]),
            '@#js-notifications' => $this->controller->renderPartial('snippets/notification', ['item' => Notification::info($notificationMessage)]),
            'notification' => $notificationMessage,
        ];
    }

    protected function handleSectionUpdate($section, $element, $partial)
    {
        $user = User::find(post('uid'));

        $section = FlowSection::find($section);

        $notification = post('notification');

        if ($notification == 'stop') {
            return [
                $element => $this->controller->renderPartial($partial, [
                    'section' => $section
                ]),
            ];
        }

        $notificationMessage = !empty($notification) && $notification != 'false' ? $notification : $user->name . ' added ticket to ' . $section->flow->project->title . ' (' . $section->name . ')';

        return [
            $element => $this->controller->renderPartial($partial, [
                'section' => $section
            ]),
            '@#js-notifications' => $this->controller->renderPartial('snippets/notification', ['item' => Notification::info($notificationMessage)]),
            'notification' => $notificationMessage,
        ];
    }

    protected function handleChecklistUpdate($checklist, $element, $partial)
    {
        $user = User::find(post('uid'));

        if (Str::startsWith($element, '-')) {
            return [
                $element => $checklist,
                'checklist' => $checklist,
            ];
        }

        $checklist = Checklist::find($checklist);

        $notificationMessage = $user->name . ' updated ticket: ' . $checklist->ticket->name;

        return [
            $element => $this->controller->renderPartial($partial, [
                'checklist' => $checklist,
                'ticket'    => $checklist->ticket
            ]),
            '@#js-notifications' => $this->controller->renderPartial('snippets/notification', ['item' => Notification::info($notificationMessage)]),
            'notification' => $notificationMessage,
            'checklist' => $checklist->id,
        ];
    }

    protected function handleFlowUpdate($project)
    {
        $user = User::find(post('uid'));

        $project = Project::find($project);

        $notificationMessage = $user->name . ' updated flow of: ' . $project->title;

        return [
            '.workflow-' . $project->id => $this->controller->renderPartial('projectsingle/_board', [
                'project' => $project,
            ]),
            '@#js-notifications' => $this->controller->renderPartial('snippets/notification', ['item' => Notification::info($notificationMessage)]),
            'notification' => $notificationMessage,
        ];
    }

    protected function handleBoardUpdate($project, $element, $partial)
    {
        $project = Project::find($project);

        return [
            $element => $this->controller->renderPartial($partial, [
                'project' => $project
            ])
        ];
    }
}