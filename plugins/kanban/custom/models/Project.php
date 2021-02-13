<?php namespace Kanban\Custom\Models;

use Model;
use Illuminate\Support\Str;
use RainLab\User\Models\User;
use RainLab\Pages\Classes\Page;
use October\Rain\Database\Traits\Validation;
use System\Models\File;

class Project extends Model
{
    use Validation;

    public $table = 'kanban_custom_projects';

    public $rules = [];

    public $hasOne = [
        'flow' => Flow::class,
    ];

    public $belongsTo = [
        'team' => Team::class,
    ];

    public $hasMany = [
        'tickets' => Ticket::class,
        'tags'    => Tag::class,
    ];

    public $belongsToMany = [
        'users' => [User::class, 'table' => 'kanban_custom_project_user'],
    ];

    public $attachOne = [
        'picture' => File::class,
    ];

    public $dates = ['start_date', 'due_date'];

    public $fillable = ['is_archived'];

    public function url()
    {
        return Page::url('project') . '/' . $this->id;
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
            'flow.sections.subsections.tickets' => $closure
        ]);
    }

    public function scopeSearch($query, $searchQuery)
    {
        if ($searchQuery) {
            $query->where('title', 'like', '%' . $searchQuery . '%');
        }
    }
}
