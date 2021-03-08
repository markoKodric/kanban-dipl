<?php namespace Kanban\Custom\Bootstrap;

use Backend\Behaviors\RelationController;
use RainLab\User\Controllers\Users;
use RainLab\User\Models\User;
use Kanban\Custom\Models\Team;
use Kanban\Custom\Models\Timer;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Models\Comment;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Models\Activity;
use Kanban\Custom\Models\Permission;
use Kanban\Custom\Models\FlowSection;

class ExtendUser
{
    protected static $restrictions;

    public function init()
    {
        $this->extendUserModel();
        $this->extendUserFields();
    }

    protected function extendUserModel()
    {
        User::extend(function ($model) {
            $model->belongsTo['team'] = [Team::class];

            $model->belongsToMany['projects'] = [Project::class, 'table' => 'kb_project_user'];

            $model->belongsToMany['tickets'] = [Ticket::class, 'table' => 'kb_ticket_user'];

            $model->belongsToMany['restrictions'] = [FlowSection::class, 'table' => 'kb_flow_section_restrictions'];

            $model->belongsToMany['permissions'] = [Permission::class, 'table' => 'kb_user_permission'];

            $model->hasMany['timers'] = [Timer::class];

            $model->hasMany['comments'] = [Comment::class];

            $model->hasMany['activities'] = [Activity::class];

            $model->addFillable(['team_id']);
            $model->addJsonable(['permissions']);

            $model->addDynamicMethod('getCredentialsAttribute', function () use ($model) {
                preg_match_all('/(?<=\s|^)[a-z]/i', $model->name . ' ' . $model->surname, $matches);

                return strtoupper(implode('', $matches[0]));
            });

            $model->addDynamicMethod('can', function ($permission) use ($model) {
                if ($model->permissions()->get()->isEmpty()) {
                    return true;
                }

                return $model->permissions()->where('code', $permission)->exists();
            });

            $model->addDynamicMethod('canManageSection', function ($section) use ($model) {
                if (!static::$restrictions) {
                    static::$restrictions = $model->restrictions;
                }

                return is_null(static::$restrictions->where('id', $section->id)->first());
            });

            $model->addDynamicMethod('getPictureAttribute', function ($value) use ($model) {
                if ($model->avatar) {
                    return $model->avatar->getPath();
                }

                if ($value) {
                    return url($value);
                }

                return $value;
            });

            $model->addDynamicMethod('getFirstNameAttribute', function () use ($model) {
                $splitName = explode(' ', $model->name);

                return $splitName[0] ?? $model->name;
            });
        });
    }

    protected function extendUserFields()
    {
        Users::extend(function ($controller) {
            $controller->relationConfig = '$/kanban/custom/models/extensions/users_relation_config.yaml';

            $controller->implement[] = RelationController::class;

            return $controller;
        });

        Users::extendFormFields(function ($form, $model, $context) {
            $form->addTabFields([
                'permissions' => [
                    'label' => 'Permissions',
                    'tab' => 'Permissions',
                    'type' => 'partial',
                    'path' => '$/kanban/custom/models/_partials/permissions_field.htm'
                ]
            ]);

            return $form;
        });
    }
}