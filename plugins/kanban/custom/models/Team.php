<?php namespace Kanban\Custom\Models;

use Model;
use October\Rain\Database\Traits\Validation;
use RainLab\User\Models\User;

class Team extends Model
{
    use Validation;

    public $table = 'kanban_custom_teams';

    public $rules = [];

    public $fillable = ['settings'];

    public $jsonable = ['settings'];

    public $hasMany = [
        'projects' => Project::class,
        'users'    => User::class,
    ];

    public function defaultProject()
    {
        return $this->projects()->where('is_default', true)->first();
    }
}
