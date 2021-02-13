<?php namespace Kanban\Custom\Models;

use Auth;
use Model;
use RainLab\User\Models\User;
use October\Rain\Database\Traits\Validation;

class Comment extends Model
{
    use Validation;

    public $table = 'kanban_custom_ticket_comments';

    public $fillable = ['comment', 'ticket_id', 'parent_id', 'user_id'];

    public $rules = [];

    public $belongsTo = [
        'ticket' => Ticket::class,
        'user'   => User::class,
    ];

    public $hasMany = [
        'replies' => [Comment::class, 'key' => 'parent_id'],
    ];

    public function isMine()
    {
        return $this->user_id == Auth::getUser()->id;
    }
}
