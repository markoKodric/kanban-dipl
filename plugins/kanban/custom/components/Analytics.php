<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Carbon\CarbonPeriod;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Carbon;
use Kanban\Custom\Models\FlowSectionUpdate;
use RainLab\Pages\Classes\Page;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class Analytics extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project', 'segment?'];

    public $user;

    public $data;

    public $project;

    public $segment;

    public $segments = [
        'cfd' => ['cfd', 'Cumulative Flow Diagram'],
        'cc'  => ['cc', 'Control Chart'],
        'uo'  => ['uo', 'User overview'],
    ];

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Analytics',
            'description' => 'Display analytics on page.'
        ];
    }

    public function onRun()
    {
        $this->user = Auth::getUser();

        $this->segment = $this->dynamicParam('segment');

        if ($projectId = $this->dynamicParam('project')) {
            $this->project = Project::find($projectId);
        } else {
            $this->project = Project::find(session()->get('currentProject')) ?? $this->user->team->defaultProject();

            if ($this->project) {
                return redirect($this->analyticsUrl($this->dynamicParam('segment', 'cfd')));
            }
        }

        if (!$this->project) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->project->team->id) {
            App::abort(403);
        }

        session()->put('currentProject', $this->project->id);

        $this->initAnalytics();
    }

    public function onChangeTimePeriod()
    {
        $this->onRun();

        return [
            '#js-analytics' => $this->renderPartial('@_' . $this->segment)
        ];
    }

    public function analyticsUrl($segment)
    {
        return Page::url('analytics') . '/' . $this->project->id . '/' . $this->segments[$segment][0];
    }

    protected function initAnalytics()
    {
        $this->segment = $this->dynamicParam('segment', 'cfd');

        $timePeriod = post('time_period');

        switch ($this->segment) {
            case null:
            case 'cfd':
                return $this->initCFD($timePeriod);
            case 'cc':
                return $this->initCC($timePeriod);
            case 'uo':
                return $this->initUO($timePeriod);
        }
    }

    protected function initCFD($timePeriod = null)
    {
        if (!$this->project->flow) return;

        $flowUpdates = FlowSectionUpdate::get();

        $startDate = $this->project->created_at->startOfDay();

        if ($firstUpdate = FlowSectionUpdate::where('project_id', $this->project->id)->orderBy('created_at')->first()) {
            $startDate = $firstUpdate->created_at->subDay()->startOfDay();
        }

        $endDate = now();

        if ($lastUpdate = FlowSectionUpdate::where('project_id', $this->project->id)->orderBy('created_at', 'desc')->first()) {
            $endDate = $lastUpdate->created_at->startOfDay();
        }

        if ($timePeriod && $startDate->copy()->addDays($timePeriod)->lt($endDate)) {
            $startDate = $startDate->addDays($timePeriod);
        }

        $timePeriod = CarbonPeriod::create($startDate, $endDate)->toArray();

        $flowSections = $this->project->flow->sections()->doesntHave('subsections')->whereNull('swimlane_id')->with(['parent'])->get();

        $cfdData = [];

        $colors = [
            'color1'  => '#F72585',
            'color2'  => '#7209B7',
            'color3'  => '#3A0CA3',
            'color4'  => '#4361EE',
            'color5'  => '#4CC9F0',
            'color6'  => '#16F4D0',
            'color7'  => '#153B50',
            'color8'  => '#429EA6',
            'color9'  => '#1B512D',
            'color10' => '#2C497F',
            'color11' => '#DE8F6E',
        ];

        $colorIndex = 1;

        foreach ($flowSections as $section) {
            $data = [];

            foreach ($timePeriod as $day) {
                $data[] = [
                    round($day->format('Uu') / pow(10, 6 - 3)),
                    $flowUpdates->filter(function ($item) use ($section, $day) {
                        return $item->flow_section_id == $section->id && $item->created_at->startOfDay()->lte($day->startOfDay());
                    })
                    ->sortByDesc('created_at')
                    ->groupBy('ticket_id')
                    ->count()
                ];
            }

            $cfdData[] = [
                'name'         => $section->name . ($section->parent ? ' (' . $section->parent->name . ')' : ''),
                'color'        => $colors['color' . $colorIndex++],
                'data'         => $data,
                'showInLegend' => true,
                'marker'       => ['symbol' => 'circle']
            ];
        }

        $this->data = json_encode($cfdData);
    }

    protected function initCC($timePeriod = null)
    {
        if (!$this->project->flow) return;

        $flowUpdates = FlowSectionUpdate::get();

        $startDate = $this->project->created_at->startOfDay();

        if ($firstUpdate = FlowSectionUpdate::where('project_id', $this->project->id)->orderBy('created_at')->first()) {
            $startDate = $firstUpdate->created_at->subDay()->startOfDay();
        }

        $endDate = now();

        if ($lastUpdate = FlowSectionUpdate::where('project_id', $this->project->id)->orderBy('created_at', 'desc')->first()) {
            $endDate = $lastUpdate->created_at->startOfDay();
        }

        if ($timePeriod && $startDate->copy()->addDays($timePeriod)->lt($endDate)) {
            $startDate = $startDate->addDays($timePeriod);
        }

        $timePeriod = CarbonPeriod::create($startDate, $endDate)->toArray();

        $flowSections = $this->project->flow->sections()->with(['parent', 'subsections'])->whereNull('swimlane_id')->get();

        $ticketsDone = $this->project->tickets()->whereHas('section', function ($query) {
            $query->whereNull('swimlane_id');
        })->whereNotNull('completed_at')->get();

        $ccData = $ticketsDone->map(function ($item) {
            $ticketCompletedDate = round($item->completed_at->startOfDay()->format('Uu') / pow(10, 6 - 3));
            $ticketCompletionDays = $item->created_at->diffInDays($item->completed_at);

            return [
                'x' => $ticketCompletedDate,
                'y' => $ticketCompletionDays,
                'name' => $item->name
            ];
        });

        $filteredData = [];

        foreach ($ccData as $point) {
            $filteredData[] = [
                'x'    => $point['x'],
                'y'    => $point['y'],
                'name' => $ccData->where('x', $point['x'])->where('y', $point['y'])->implode('name', ', ')
            ];
        }

        $ccData = collect($filteredData)->unique('name')->toArray();

        $ccAverage = $ticketsDone->map(function ($item) {
                return $item->created_at->diffInDays($item->completed_at);
            })
            ->average();

        $averageData = $ticketsDone->map(function ($item) use ($ccAverage) {
            return [
                'x' => round($item->completed_at->startOfDay()->format('Uu') / pow(10, 6 - 3)),
                'y' => $ccAverage
            ];
        })->values()->toArray();

        $counter = 4;
        $rollingData = $ticketsDone->map(function ($item) use ($ticketsDone, &$counter) {
                $intermediateAverage = $ticketsDone->slice($counter - 4, 8)
                    ->map(function ($ticket) {
                        return $ticket->created_at->diffInDays($ticket->completed_at);
                    })
                    ->average();

                $counter++;

                return [
                    'x' => round($item->completed_at->startOfDay()->format('Uu') / pow(10, 6 - 3)),
                    'y' => intval($intermediateAverage)
                ];
            })
            ->groupBy([function ($item) {
                return Carbon::createFromTimestampMs($item['x'])->toDateString();
            }, 'y'])
            ->sort()
            ->map(function ($newItem, $key) {
                return $newItem->map(function ($ticket) use ($newItem, $key) {
                    return [
                        'x' => round(Carbon::createFromTimestampMs($ticket->first()['x'])->startOfDay()->format('Uu') / pow(10, 6 - 3)),
                        'y' => $newItem->first()->average('y'),
                    ];
                });
            })
            ->values()
            ->map(function ($item) {
                return $item->first();
            })
            ->values()
            ->toArray();

        foreach ($rollingData as $item) {
            $dataMean = $item['y'];

            $squaredData = pow($item['y'] - $ccAverage, 2);

            $variance = sqrt(1 / ($squaredData ?: 1));

            $ucl = $dataMean + (3 * $variance);

            $lcl = $dataMean - (3 * $variance);

            $standardDeviationArea[] = [
                $item['x'],
                max($lcl, 0),
                $ucl,
            ];
        }

        $standardDeviationArea = collect($standardDeviationArea)
            ->unique(function ($item) {
                return $item[0];
            })
            ->values()
            ->toArray();

        $this->data = [
            'ccData'                => json_encode($ccData),
            'averageData'           => json_encode($averageData),
            'rollingData'           => json_encode($rollingData),
            'standardDeviationArea' => json_encode($standardDeviationArea),
        ];
    }

    protected function initUO($timerPeriod = null)
    {

    }
}
