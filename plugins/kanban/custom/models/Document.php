<?php namespace Kanban\Custom\Models;

use Model;
use RainLab\User\Models\User;
use October\Rain\Database\Traits\Validation;

class Document extends Model
{
    use Validation;

    public $table = 'kb_documentation';

    public $fillable = ['creator_id', 'last_user_id', 'title', 'content', 'editing_user_id'];

    public $rules = [];

    public $belongsTo = [
        'user'     => [User::class, 'key' => 'creator_id'],
        'lastUser' => [User::class, 'key' => 'last_user_id'],
        'project'  => Project::class,
    ];


}