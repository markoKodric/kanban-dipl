<?php namespace Kanban\Custom\Models;

use Model;
use System\Models\File;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use RainLab\Pages\Classes\Page;
use October\Rain\Database\Traits\Validation;

class Ticket extends Model
{
    use Validation;

    public $table = 'kanban_custom_tickets';

    public $fillable = ['flow_section_id', 'project_id', 'name', 'description', 'priority', 'sort_order', 'time_estimation', 'due_date', 'is_archived'];

    public $rules = [];

    public $dates = ['due_date'];

    public $belongsTo = [
        'section' => FlowSection::class,
        'project' => Project::class,
    ];

    public $hasMany = [
        'checklists' => Checklist::class,
        'comments'   => Comment::class,
    ];

    public $morphMany = [
        'timers' => [Timer::class, 'name' => 'timeable'],
    ];

    public $belongsToMany = [
        'users' => [User::class, 'table' => 'kanban_custom_ticket_user'],
        'tags'  => [Tag::class, 'table' => 'kanban_custom_tickets_tags'],
    ];

    public $attachMany = [
        'files' => File::class,
    ];

    public function url()
    {
        return Page::url('ticket') . '/' . $this->id;
    }

    public function runningTimer()
    {
        return $this->timers()->running()->first();
    }

    public function getPriorityImageAttribute()
    {
        switch ($this->priority) {
            case 1:  return 'assets/images/high-priority.png';
            case 2:  return 'assets/images/medium-priority.png';
            default: return 'assets/images/low-priority.png';
        }
    }

    public function estimationHours()
    {
        return floor($this->time_estimation / 3600);
    }

    public function estimationMinutes()
    {
        return floor($this->time_estimation / 60) % 60;
    }

    public function estimatedTimeForHumans()
    {
        if ($this->time_estimation == 0) {
            return '-';
        }

        $estimatedTime = '';

        if ($this->estimationHours() != 0) {
            $estimatedTime .= $this->estimationHours() . 'h' . ($this->estimationMinutes() != 0 ? ' ' : '');
        }

        if ($this->estimationMinutes() != 0) {
            $estimatedTime .= $this->estimationMinutes() . 'm';
        }

        return $estimatedTime;
    }

    public function excludedUsers($searchQuery = null)
    {
        if ($searchQuery) {
            return $this->project
                ->users()
                ->whereNotIn('id', $this->users->pluck('id')->all())
                ->orderBy('name')
                ->get()
                ->filter(function ($user) use ($searchQuery) {
                    return Str::contains(strtolower($user->name . ' ' . $user->surname), $searchQuery);
                });
        }

        return $this->project
            ->users()
            ->whereNotIn('id', $this->users->pluck('id')->all())
            ->orderBy('name')
            ->get();
    }

    public function elapsedTime()
    {
        $totalTime = $this->timers->sum('time_in_seconds');

        return date('H:i:s', mktime(0, 0, $totalTime));
    }

    public function totalChecklistItems()
    {
        return $this->checklists->sum(function ($checklist) {
            return $checklist->items->count();
        });
    }

    public function doneChecklistItems()
    {
        return $this->checklists
            ->sum(function ($checklist) {
                return $checklist->items()
                    ->where('is_done', true)
                    ->count();
            });
    }

    public function availableTags()
    {
        return $this->project
            ->tags()
            ->whereNotIn('id', $this->tags->pluck('id')->all())
            ->get();
    }
}
