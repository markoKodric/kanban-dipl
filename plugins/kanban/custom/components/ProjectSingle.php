<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use Kanban\Custom\Models\Activity;
use Kanban\Custom\Models\Swimlane;
use RainLab\User\Models\User;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Flow;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\FlowSection;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Classes\Notification;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Models\FlowSectionUpdate;
use Kanban\Custom\Traits\DynamicParameters;

class ProjectSingle extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project'];

    public $project;

    public $ticket;

    public $user;

    public $activeFilter;

    public $filters = [
        ''                   => '-- Filters --',
        'my-tickets'         => 'My tickets',
        'high-priority'      => 'High priority',
        'medium-priority'    => 'Medium priority',
        'low-priority'       => 'Low priority',
        'estimated-only'     => 'Estimated only',
        'non-estimated-only' => 'Non-estimated only',
    ];

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Project single',
            'description' => 'Display single project on page.'
        ];
    }

    public function defineProperties()
    {
        return [
            'ticketPage' => [
                'title' => 'Select ticket page',
                'type' => 'dropdown',
            ]
        ];
    }

    public function onRun()
    {
        $this->user = User::where('id', Auth::getUser()->id)->with('team')->first();

        if ($projectId = $this->dynamicParam('project')) {
            $this->project = Project::find($projectId);
        } else {
            $this->project = Project::find(session()->get('currentProject')) ?? $this->user->team->defaultProject();

            if ($this->project) {
                return redirect($this->project->url());
            }
        }

        if (!$this->project) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->project->team->id) {
            App::abort(403);
        }

        /*if (get('ticket')) {
            $this->ticket = $this->project->tickets()->where('id', get('ticket'))->first();
        }*/

        session()->put('currentProject', $this->project->id);
    }

    public function onSaveWorkflow()
    {
        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        if (!($sections = json_decode(request()->post('sections'), true))) {
            return [
                '#flow-definer' => $this->renderPartial('@_flow_definer', ['error' => 'Please add at least one section.'])
            ];
        }

        $flow = Flow::forceCreate([
            'project_id' => $this->dynamicParam('project'),
            'name'       => Str::random(48) . uniqid(),
        ]);

        foreach ($sections as $key => $section) {
            $sectionModel = $flow->sections()->create([
                'name'                  => $section['title'],
                'sort_order'            => $key + 1,
                'wip_limit'             => isset($section['wipLimit']) && is_numeric($section['wipLimit']) ? $section['wipLimit'] : null,
                'mark_tickets_complete' => $section['markComplete'] ?? false,
            ]);

            foreach ($section['subsections'] as $subkey => $subsection) {
                $sectionModel->subsections()->create([
                    'flow_id'               => $flow->id,
                    'name'                  => $subsection['title'],
                    'sort_order'            => $subkey + 1,
                    'wip_limit'             => is_numeric($subsection['wipLimit']) ? $subsection['wipLimit'] : null,
                    'mark_tickets_complete' => $subsection['markComplete'] ?? false,
                ]);
            }
        }

        $flow->refresh();

        if (!$flow->sections()->where('mark_tickets_complete', true)->exists()) {
            $flow->sections->last()->update([
                'mark_tickets_complete' => true,
            ]);
        }

        if (post('default_template')) {
            $this->user->team()->update([
                'settings' => $sections
            ]);
        }

        return [
            '#workflow'       => $this->renderPartial('@_board', ['project' => $this->project]),
            '#js-flow-update' => $this->renderPartial('@_flow_update', ['project' => $this->project]),
        ];
    }

    public function onUpdateWorkflow()
    {
        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        if (!($sections = json_decode(request()->post('sections'), true))) {
            return [
                '#flow-definer' => $this->renderPartial('@_flow_definer', ['error' => 'Please add at least one section.'])
            ];
        }

        $sectionIds = collect($sections)
            ->map(function ($item) {
                return array_merge([$item['id'] ?? null], collect($item['subsections'])->pluck('id')->all());
            })
            ->flatten()
            ->filter(function ($item) {
                return !is_null($item);
            })->all();

        $this->project->flow->sections()
            ->whereNotIn('id', $sectionIds)
            ->whereNull('swimlane_id')
            ->each(function ($section) {
                $section->cleanDelete();
            });

        foreach ($sections as $key => $section) {
            $attributes = [
                'name'                  => $section['title'],
                'sort_order'            => $key + 1,
                'wip_limit'             => isset($section['wipLimit']) && is_numeric($section['wipLimit']) ? $section['wipLimit'] : null,
                'mark_tickets_complete' => !! ($section['markComplete'] ?? false),
            ];

            if (isset($section['id'])) {
                $sectionModel = $this->project->flow->sections()->where('id', $section['id'])->first();

                $sectionModel->update($attributes);
            } else {
                $sectionModel = $this->project->flow->sections()->create($attributes);
            }

            foreach ($section['subsections'] as $subkey => $subsection) {
                $attributes = [
                    'flow_id'               => $this->project->flow->id,
                    'name'                  => $subsection['title'],
                    'sort_order'            => $subkey + 1,
                    'wip_limit'             => is_numeric($subsection['wipLimit']) ? $subsection['wipLimit'] : null,
                    'mark_tickets_complete' => !! ($subsection['markComplete'] ?? false),
                ];

                if (isset($subsection['id'])) {
                    $subsectionModel = $sectionModel->subsections()->where('id', $subsection['id'])->first();

                    $subsectionModel->update($attributes);
                } else {
                    $sectionModel->subsections()->create($attributes);
                }
            }
        }

        $this->project->updateSwimlanes();

        $this->onRun();

        if (post('default_template')) {
            recursive_unset($sections, 'id');

            $settings = $this->user->team->settings;

            $settings['default_flow_template'] = $sections;

            $this->user->team()->update([
                'settings' => $settings,
            ]);
        }

        return [
            '#workflow'       => $this->renderPartial('@_board', ['project' => $this->project]),
            '#js-flow-update' => $this->renderPartial('@_flow_update', ['project' => $this->project]),
        ];
    }

    public function onLoadDefaultFlow()
    {
        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        return [
            '#js-flow-definer-sections' => $this->renderPartial('@_flow_definer_sections', ['defaultTemplate' => $this->user->team->settings['default_flow_template'] ?? null]),
        ];
    }

    public function onResetFlow()
    {
        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        return [
            '#js-flow-definer-sections' => $this->renderPartial('@_flow_definer_sections', ['flow' => $this->project->flow]),
        ];
    }

    public function onCreateTicket()
    {
        $this->onRun();

        if (!$this->user->can('board.tickets.add')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $section = $this->project->flow->sections->where('id', post('_section_id'))->first();

        if (!validate_request(['title' => 'required', '_section_id' => 'required'])) {
            return [
                '#addTicketForm' => $this->renderPartial('@__add_ticket_form', [
                    'section' => $section,
                    'errors' => session()->get('errors')
                ])
            ];
        }

        $ticket = $this->project->tickets()->create([
            'flow_section_id' => post('_section_id'),
            'name'            => post('title'),
            'description'     => post('description'),
            'priority'        => post('priority'),
            'sort_order'      => $section->tickets()->count() + 1,
            'time_estimation' => $this->calculateEstimation(post('estimation')),
            'color'           => post('color', '#fff'),
        ]);

        $ticket->users()->attach(Auth::getUser()->id);

        session()->forget('errors');

        FlowSectionUpdate::create([
            'ticket_id'       => $ticket->id,
            'flow_section_id' => $section->id,
            'project_id'      => $this->project->id,
            'user_id'         => Auth::getUser()->id,
            'description'     => 'Created ticket',
        ]);

        Activity::create([
            'user_id'     => Auth::getUser()->id,
            'project_id'  => $this->project->id,
            'description' => 'Added ticket "' . $ticket->name . '"',
            'data'        => [
                'ticket'  => $ticket->id,
                'section' => $section->id,
            ]
        ]);

        return [
            'ticket' => $ticket->id,
            'section' => $section->id,
            '#section-tickets-' . post('_section_id') => $this->renderPartial('@_tickets', ['section' => $section]),
            '#section-add-ticket-' . post('_section_id') => $this->renderPartial('@_add_ticket', ['section' => $section])
        ];
    }

    public function onReorderTickets()
    {
        $this->onRun();

        if (!$this->user->can('board.tickets.reorder')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')]),
                '#workflow' => $this->renderPartial('@_board', ['project' => $this->project]),
            ];
        }

        $tickets = Ticket::find(post('tickets'));
        $newSection = post('sectionId');

        if ($this->user->restrictions->find($newSection)) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')]),
                '#workflow' => $this->renderPartial('@_board', ['project' => $this->project]),
            ];
        }

        $section = FlowSection::find($newSection);

        $updates = [];

        foreach ($tickets as $ticket) {
            if ($ticket->flow_section_id != $newSection) {
                $updates[] = $ticket->flow_section_id;
            }
        }

        $tickets->each(function ($ticket, $i) use ($section) {
            $currentSection = FlowSection::find($ticket->flow_section_id);

            if ($currentSection->id != $section->id) {
                FlowSectionUpdate::create([
                    'ticket_id'           => $ticket->id,
                    'flow_section_id'     => $section->id,
                    'old_flow_section_id' => $currentSection->id,
                    'project_id'          => $this->project->id,
                    'user_id'             => Auth::getUser()->id,
                    'description'         => $section->mark_tickets_complete ? 'Completed ticket' : 'Moved ticket',
                ]);
            }

            $ticket->update([
                'sort_order'      => array_search($ticket->id, post('tickets')),
                'flow_section_id' => $section->id,
            ]);

            if ($section->mark_tickets_complete && $ticket->id == post('ticket')) {
                Activity::create([
                    'user_id'     => Auth::getUser()->id,
                    'project_id'  => $this->project->id,
                    'description' => ($currentSection->mark_tickets_complete ? 'Uncompleted' : 'Completed') . ' ticket "' . $ticket->name . '"',
                    'data'        => [
                        'ticket'  => $ticket->id,
                        'section' => $section->id,
                    ]
                ]);

                $ticket->update([
                    'completed_at' => now(),
                ]);
            } else if (!$section->mark_tickets_complete && $ticket->id == post('ticket')) {
                $ticket->update([
                    'completed_at' => null,
                ]);
            }
        });

        $this->project->refresh();

        $updates = array_merge(array_unique($updates), [$newSection]);
        $partialUpdates = [
            '#section-add-ticket'       => $this->renderPartial('@_add_ticket', [
                'section' => $this->project->flow->sections()->orderBy('sort_order')->first()
            ]),
            '#ticket-' . post('ticket') => $this->renderPartial('@_ticket', [
                'ticket' => Ticket::find(post('ticket'))
            ]),
        ];

        foreach ($updates as $update) {
            $partialUpdates['#js-section-header-' . $update] = $this->renderPartial('@_section_header', [
                'section' => FlowSection::find($update)
            ]);
        }

        return $partialUpdates;
    }

    public function onSearchTickets()
    {
        $this->user = Auth::getUser();

        $searchQuery = request()->post('query');

        $this->project = Project::where('id', $this->dynamicParam('project'))
            ->with(
                [
                    'flow.sections.tickets' => function ($query) use ($searchQuery) {
                        $query->where(function ($subquery) use ($searchQuery) {
                            $subquery->where('name', 'like', '%' . $searchQuery . '%')
                                ->orWhere('description', 'like', '%' . $searchQuery . '%');
                        })->unarchived()->orderBy('priority');
                    },
                    'flow.sections.subsections.tickets' => function ($query) use ($searchQuery) {
                        $query->where(function ($subquery) use ($searchQuery) {
                            $subquery->where('name', 'like', '%' . $searchQuery . '%')
                                ->orWhere('description', 'like', '%' . $searchQuery . '%');
                        })->unarchived()->orderBy('priority');
                    },
                    'swimlanes.sections.tickets' => function ($query) use ($searchQuery) {
                        $query->where(function ($subquery) use ($searchQuery) {
                            $subquery->where('name', 'like', '%' . $searchQuery . '%')
                                ->orWhere('description', 'like', '%' . $searchQuery . '%');
                        })->unarchived()->orderBy('priority');
                    },
                    'swimlanes.sections.subsections.tickets' => function ($query) use ($searchQuery) {
                        $query->where(function ($subquery) use ($searchQuery) {
                            $subquery->where('name', 'like', '%' . $searchQuery . '%')
                                ->orWhere('description', 'like', '%' . $searchQuery . '%');
                        })->unarchived()->orderBy('priority');
                    },
                ]
            )->first();

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $this->project]),
        ];
    }

    public function onFilterTickets()
    {
        $this->user = Auth::getUser();

        $query = Project::where('id', $this->dynamicParam('project'));

        switch (post('filter')) {
            case 'my-tickets':
                $query = $query->ticketsFilter(function ($query) {
                    $query->whereHas('users', function ($subquery) {
                        $subquery->where('id', Auth::getUser()->id);
                    })->unarchived()->orderBy('priority');
                });
                break;
            case 'estimated-only':
                $query->ticketsFilter(function ($query) {
                    $query->where('time_estimation', '>', 0)->unarchived()->orderBy('priority');
                });
                break;
            case 'non-estimated-only':
                $query->ticketsFilter(function ($query) {
                    $query->where('time_estimation', 0)->unarchived()->orderBy('priority');
                });
                break;
            case 'high-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 1);
                })->unarchived();
                break;
            case 'medium-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 2);
                })->unarchived();
                break;
            case 'low-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 3);
                })->unarchived();
                break;
            default:
                $query->ticketsFilter(function ($query) {
                    $query->orderBy('priority');
                })->unarchived();
        }

        $this->activeFilter = post('filter');

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $query->first()]),
            '#js-project-filters' => $this->renderPartial('@_filters'),
        ];
    }

    public function onFilterTicketsByTag()
    {
        $this->user = Auth::getUser();

        $query = Project::where('id', $this->dynamicParam('project'))->ticketsFilter(function ($query) {
                $query->whereHas('tags', function ($subquery) {
                    $subquery->where('id', post('tag'));
                })->unarchived()->orderBy('priority');
        });

        $this->activeFilter = post('tag');

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $query->first()]),
            '#js-project-filters' => $this->renderPartial('@_filters'),
        ];
    }

    public function onResetFilters()
    {
        $this->user = Auth::getUser();

        $this->onRun();

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $this->project]),
            '#js-project-filters' => $this->renderPartial('@_filters'),
        ];
    }

    public function onAddUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('board.users.manage')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $this->project->users()->attach($user);

        return [
            '#js-project-users'     => $this->renderPartial('@_users'),
            '#js-project-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->project->excludedUsers(),
            ]),
        ];
    }

    public function onSearchUsers()
    {
        $this->onRun();

        return [
            '#js-project-users'     => $this->renderPartial('@_users'),
            '#js-project-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->project->excludedUsers(post('query')),
                'query' => post('query'),
            ]),
        ];
    }

    public function onRemoveUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('board.users.manage')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $this->project->tickets->each(function ($ticket) use ($user) {
            $ticket->users()->detach($user);
        });

        $this->project->users()->detach($user);

        return [
            '#workflow'             => $this->renderPartial('@_board', ['project' => $this->project]),
            '#js-project-users'     => $this->renderPartial('@_users'),
            '#js-project-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->project->excludedUsers(),
            ]),
        ];
    }

    public function onAddSection()
    {
        if (!($section = post('section_title'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        $markComplete = filter_var(post('markComplete'), FILTER_VALIDATE_BOOLEAN);

        if ($markComplete) {
            $this->project->flow->sections()->update([
                'mark_tickets_complete' => null,
            ]);
        }

        $this->project->flow->sections()->create([
            'name'                  => $section,
            'wip_limit'             => post('wip_limit') ?: null,
            'mark_tickets_complete' => $markComplete ?: null,
        ]);

        $this->project->updateSwimlanes();

        $this->project->refresh();

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $this->project]),
            '#js-flow-update' => $this->renderPartial('@_flow_update', ['project' => $this->project]),
        ];
    }

    public function onAddSwimlane()
    {
        if (!($swimlane = post('swimlane_title'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('board.workflow.edit')) {
            return [
                '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error('Unauthorized action.')])
            ];
        }

        Swimlane::create([
            'project_id' => $this->project->id,
            'name'       => $swimlane,
            'sort_order' => 1
        ]);

        $this->project->updateSwimlanes();

        $this->project->refresh();

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $this->project])
        ];
    }

    /*public function onToggleTicket()
    {
        $this->onRun();

        $this->ticket = $this->project->tickets()->where('id', post('ticket'))->first();

        return [
            '#js-ticket-popup' => $this->renderPartial('ticketsingle/_ticket', ['ticket' => $this->ticket])
        ];
    }*/

    public function getTicketPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }

    protected function calculateEstimation($estimation)
    {
        $result = 0;

        $hours = $estimation[0] ?? false;
        $minutes = $estimation[1] ?? false;

        if ($hours) {
            $result += ($hours * 60 * 60);
        }

        if ($minutes) {
            $result += ($minutes * 60);
        }

        return $result;
    }
}
