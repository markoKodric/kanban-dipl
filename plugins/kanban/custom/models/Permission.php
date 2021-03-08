<?php namespace Kanban\Custom\Models;

use Model;
use RainLab\User\Models\User;
use October\Rain\Database\Traits\Validation;

class Permission extends Model
{
    use Validation;

    public $table = 'kb_permissions';

    public $fillable = ['title'];

    public $rules = [];

    public $timestamps = false;

    public $belongsToMany = [
        'users' => User::class,
    ];
}
