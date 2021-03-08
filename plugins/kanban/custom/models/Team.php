<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use RainLab\User\Models\User;

class Team extends Model
{
    use Validation;

    public $table = 'kb_teams';

    public $rules = [];

    public $fillable = ['settings'];

    public $jsonable = ['settings'];

    public $hasMany = [
        'projects' => Project::class,
        'users'    => User::class,
    ];

    public $hasManyThrough = [
        'tickets' => [
            Ticket::class,
            'through' => Project::class,
        ]
    ];

    public function defaultProject()
    {
        return $this->projects()->where('is_default', true)->first();
    }
}
