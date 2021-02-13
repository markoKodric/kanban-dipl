<?php namespace Kanban\Custom\Bootstrap;

use RainLab\User\Models\User;
use Kanban\Custom\Models\Team;
use Kanban\Custom\Models\Timer;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\Comment;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\FlowSection;

class ExtendUser
{
    protected static $restrictions;

    public function init()
    {
        $this->extendUserModel();
    }

    protected function extendUserModel()
    {
        User::extend(function ($model) {
            $model->belongsTo['team'] = [Team::class];

            $model->belongsToMany['projects'] = [Project::class, 'table' => 'kanban_custom_project_user'];

            $model->belongsToMany['tickets'] = [Ticket::class, 'table' => 'kanban_custom_ticket_user'];

            $model->belongsToMany['restrictions'] = [FlowSection::class, 'table' => 'kanban_custom_flow_section_restrictions'];

            $model->hasMany['timers'] = [Timer::class];
            $model->hasMany['comments'] = [Comment::class];

            $model->addFillable(['team_id']);
            $model->addJsonable(['permissions']);

            $model->addDynamicMethod('getCredentialsAttribute', function () use ($model) {
                preg_match_all('/(?<=\s|^)[a-z]/i', $model->name . ' ' . $model->surname, $matches);

                return strtoupper(implode('', $matches[0]));
            });

            $model->addDynamicMethod('can', function ($permission) use ($model) {
                if (!$model->permissions || (!is_null($model->permissions) && empty($model->permissions))) {
                    return true;
                }

                return in_array($permission, $model->permissions);
            });

            $model->addDynamicMethod('canManageSection', function ($section) use ($model) {
                if (!static::$restrictions) {
                    static::$restrictions = $model->restrictions;
                }

                return is_null(static::$restrictions->where('id', $section->id)->first());
            });
        });
    }
}