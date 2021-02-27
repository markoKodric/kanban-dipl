<?php namespace Kanban\Custom\Models;

use Model;
use RainLab\User\Models\User;
use October\Rain\Database\Traits\Validation;

class Activity extends Model
{
    use Validation;

    public $table = 'kanban_custom_activity_log';

    public $fillable = ['description', 'data', 'user_id', 'project_id'];

    public $rules = [];

    public $jsonable = ['data'];

    public $belongsTo = [
        'user'    => User::class,
        'project' => Project::class,
    ];
}
