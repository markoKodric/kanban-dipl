<?php namespace Kanban\Custom\Models;

use Model;
use System\Models\File;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use RainLab\Pages\Classes\Page;
use October\Rain\Database\Traits\Validation;

class Project extends Model
{
    use Validation;

    public $table = 'kb_projects';

    public $rules = [];

    public $hasOne = [
        'flow' => Flow::class,
    ];

    public $belongsTo = [
        'team' => Team::class,
    ];

    public $hasMany = [
        'tickets'    => Ticket::class,
        'tags'       => Tag::class,
        'activities' => Activity::class,
        'documents'  => Document::class,
        'swimlanes'  => Swimlane::class,
    ];

    public $belongsToMany = [
        'users' => [User::class, 'table' => 'kb_project_user'],
    ];

    public $attachOne = [
        'picture' => File::class,
    ];

    public $attachMany = [
        'files' => File::class,
    ];

    public $dates = ['start_date', 'due_date'];

    public $fillable = ['is_archived', 'docs'];

    public function url()
    {
        return Page::url('project') . '/' . $this->id;
    }

    public function updateUrl()
    {
        return Page::url('project-information') . '/' . $this->id;
    }

    public function mediaUrl()
    {
        return Page::url('shared-files') . '/' . $this->id;
    }

    public function settingsUrl()
    {
        return Page::url('settings') . '/' . $this->id;
    }

    public function getPicture()
    {
        if (!$this->picture) {
            return url('storage/app/media/logos/logo.png');
        }

        return $this->picture->getPath();
    }

    public function excludedUsers($searchQuery = null)
    {
        if ($searchQuery) {
            return $this->team->users()
                ->whereNotIn('id', $this->users->pluck('id')->all())
                ->orderBy('name')
                ->get()
                ->filter(function ($user) use ($searchQuery) {
                    return Str::contains(strtolower($user->name . ' ' . $user->surname), $searchQuery);
                });
        }

        return $this->team->users()
            ->whereNotIn('id', $this->users->pluck('id')->all())
            ->orderBy('name')
            ->get();
    }

    public function scopeTicketsFilter($query, $closure)
    {
        $query->with([
            'flow.sections.tickets' => $closure,
            'flow.sections.subsections.tickets' => $closure,
            'swimlanes.sections.tickets' => $closure,
            'swimlanes.sections.subsections.tickets' => $closure,
        ]);
    }

    public function scopeSearch($query, $searchQuery)
    {
        if ($searchQuery) {
            $query->where('title', 'like', '%' . $searchQuery . '%');
        }
    }

    public function scopeArchived($query)
    {
        $query->where($this->table . '.is_archived', true);
    }

    public function scopeUnarchived($query)
    {
        $query->where($this->table . '.is_archived', false)->orWhereNull($this->table . '.is_archived');
    }

    public function updateSwimlanes()
    {
        $this->swimlanes->each(function ($swimlane) {
            $swimlane->sections()->each(function ($section) {
                $originalSection = $this->flow->sections()->where('id', $section->original_section_id)->first();

                if (!$originalSection) {
                    $section->cleanDelete();
                } else {
                    $section->update([
                        'name'                  => $originalSection->name,
                        'sort_order'            => $originalSection->sort_order,
                        'wip_limit'             => $originalSection->wip_limit,
                        'mark_tickets_complete' => $originalSection->mark_tickets_complete,
                    ]);
                }
            });

            $newSections = $this->flow->sections()
                ->whereNotIn('id', $swimlane->sections->pluck('original_section_id')->all())
                ->whereNull('swimlane_id')
                ->get();

            foreach ($newSections as $section) {
                $newSection = $swimlane->sections()->create(array_merge(
                    array_except($section->attributes, ['id', 'created_at', 'updated_at']),
                    [
                        'swimlane_id'         => $swimlane->id,
                        'original_section_id' => $section->id,
                    ]
                ));

                if ($newSection->parent_section_id) {
                    $newSection->update([
                        'parent_section_id' => $swimlane->sections()->where('original_section_id', $newSection->parent_section_id)->first()->id,
                    ]);
                }
            }
        });
    }
}
