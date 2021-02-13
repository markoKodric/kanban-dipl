<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Flow;
use Kanban\Custom\Models\FlowSection;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Models\FlowSectionUpdate;
use Kanban\Custom\Traits\DynamicParameters;
use RainLab\User\Models\User;

class ProjectSingle extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project'];

    public $project;

    public $user;

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

        session()->put('currentProject', $this->project->id);
    }

    public function onSaveWorkflow()
    {
        if (!($sections = json_decode(request()->post('sections'), true))) {
            $this->onRun();

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
                'name'       => $section['title'],
                'sort_order' => $key + 1,
                'wip_limit'  => isset($section['wipLimit']) && is_numeric($section['wipLimit']) ? $section['wipLimit'] : null
            ]);

            foreach ($section['subsections'] as $subkey => $subsection) {
                $sectionModel->subsections()->create([
                    'flow_id'    => $flow->id,
                    'name'       => $subsection['title'],
                    'sort_order' => $subkey + 1,
                    'wip_limit'  => is_numeric($subsection['wipLimit']) ? $subsection['wipLimit'] : null
                ]);
            }
        }

        $this->onRun();

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

        $this->project->flow->sections()->whereNotIn('id', $sectionIds)->get()->each(function ($section) {
            $section->tickets->each(function ($ticket) {
                $ticket->comments()->delete();
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

            $section->delete();
        });

        foreach ($sections as $key => $section) {
            if (isset($section['id'])) {
                $sectionModel = $this->project->flow->sections()->where('id', $section['id'])->first();

                $sectionModel->update([
                    'name'       => $section['title'],
                    'sort_order' => $key + 1,
                    'wip_limit'  => isset($section['wipLimit']) && is_numeric($section['wipLimit']) ? $section['wipLimit'] : null
                ]);
            } else {
                $sectionModel = $this->project->flow->sections()->create([
                    'name'       => $section['title'],
                    'sort_order' => $key + 1,
                    'wip_limit'  => isset($section['wipLimit']) && is_numeric($section['wipLimit']) ? $section['wipLimit'] : null
                ]);
            }

            foreach ($section['subsections'] as $subkey => $subsection) {
                if (isset($subsection['id'])) {
                    $sectionModel->subsections()->where('id', $subsection['id'])->update([
                        'flow_id'    => $this->project->flow->id,
                        'name'       => $subsection['title'],
                        'sort_order' => $subkey + 1,
                        'wip_limit'  => is_numeric($subsection['wipLimit']) ? $subsection['wipLimit'] : null
                    ]);
                } else {
                    $sectionModel->subsections()->create([
                        'flow_id'    => $this->project->flow->id,
                        'name'       => $subsection['title'],
                        'sort_order' => $subkey + 1,
                        'wip_limit'  => is_numeric($subsection['wipLimit']) ? $subsection['wipLimit'] : null
                    ]);
                }
            }
        }

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

        return [
            '#js-flow-definer-sections' => $this->renderPartial('@_flow_definer_sections', ['defaultTemplate' => $this->user->team->settings['default_flow_template'] ?? null]),
        ];
    }

    public function onResetFlow()
    {
        $this->onRun();

        return [
            '#js-flow-definer-sections' => $this->renderPartial('@_flow_definer_sections'),
        ];
    }

    public function onCreateTicket()
    {
        $this->onRun();

        $section = $this->project->flow->sections->where('id', post('_section_id'))->first();

        if (!validate_request(['title' => 'required', '_section_id' => 'required'])) {
            return [
                '#addTicketForm' => $this->renderPartial('@__add_ticket_form', ['section' => $section, 'errors' => session()->get('errors')])
            ];
        }

        $ticket = $this->project->tickets()->create([
            'flow_section_id' => post('_section_id'),
            'name'            => post('title'),
            'description'     => post('description'),
            'priority'        => post('priority'),
            'sort_order'      => $section->tickets()->count() + 1,
            'time_estimation' => $this->calculateEstimation(post('estimation')),
        ]);

        $ticket->users()->attach(Auth::getUser()->id);

        session()->forget('errors');

        return [
            '#section-tickets-' . post('_section_id') => $this->renderPartial('@_tickets', ['section' => $section]),
            '#section-add-ticket' => $this->renderPartial('@_add_ticket', ['section' => $section])
        ];
    }

    public function onReorderTickets()
    {
        $this->onRun();

        $tickets = Ticket::find(post('tickets'));
        $newSection = post('sectionId');

        $updates = [];

        foreach ($tickets as $ticket) {
            if ($ticket->flow_section_id != $newSection) {
                $updates[] = $ticket->flow_section_id;
            }
        }

        $tickets->each(function ($ticket, $i) use ($newSection) {
            $currentSection = $ticket->flow_section_id;

            if ($currentSection != $newSection) {
                FlowSectionUpdate::create([
                    'ticket_id' => $ticket->id,
                    'flow_section_id' => $newSection
                ]);
            }

            $ticket->update([
                'sort_order' => array_search($ticket->id, post('tickets')),
                'flow_section_id' => $newSection
            ]);
        });

        $updates = array_merge(array_unique($updates), [$newSection]);
        $partialUpdates = [
            '#section-add-ticket' => $this->renderPartial('@_add_ticket', ['section' => $this->project->flow->sections()->orderBy('sort_order')->first()])
        ];

        foreach ($updates as $update) {
            $partialUpdates['#js-section-header-' . $update] = $this->renderPartial('@_section_header', ['section' => FlowSection::find($update)]);
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
                        })->orderBy('priority');
                    },
                    'flow.sections.subsections.tickets' => function ($query) use ($searchQuery) {
                        $query->where(function ($subquery) use ($searchQuery) {
                            $subquery->where('name', 'like', '%' . $searchQuery . '%')
                                ->orWhere('description', 'like', '%' . $searchQuery . '%');
                        })->orderBy('priority');
                    },
                ]
            )->first();

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $this->project])
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
                    })->orderBy('priority');
                });
                break;
            case 'estimated-only':
                $query->ticketsFilter(function ($query) {
                    $query->where('time_estimation', '>', 0)->orderBy('priority');
                });
                break;
            case 'non-estimated-only':
                $query->ticketsFilter(function ($query) {
                    $query->where('time_estimation', 0)->orderBy('priority');
                });
                break;
            case 'checklist-only':
                $query->ticketsFilter(function ($query) {
                    $query->whereHas('checklists')->orderBy('priority');
                });
                break;
            case 'files-only':
                $query->ticketsFilter(function ($query) {
                    $query->whereHas('files')->orderBy('priority');
                });
                break;
            case 'comments-only':
                $query->ticketsFilter(function ($query) {
                    $query->whereHas('comments')->orderBy('priority');
                });
                break;
            case 'assigned-only':
                $query->ticketsFilter(function ($query) {
                    $query->whereHas('users')->orderBy('priority');
                });
                break;
            case 'high-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 1);
                });
                break;
            case 'medium-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 2);
                });
                break;
            case 'low-priority':
                $query->ticketsFilter(function ($query) {
                    $query->where('priority', 3);
                });
                break;
            default:
                $query->ticketsFilter(function ($query) {
                    $query->orderBy('priority');
                });
        }

        return [
            '#workflow' => $this->renderPartial('@_board', ['project' => $query->first()])
        ];
    }

    public function onAddUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

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
