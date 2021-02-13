<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use Input;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Timer;
use Kanban\Custom\Models\Ticket;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;
use System\Models\File;

class TicketSingle extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['ticket?'];

    public $ticket;

    public $user;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Ticket',
            'description' => 'Display ticket on page.'
        ];
    }

    public function defineProperties()
    {
        return $this->withLayoutOptions([
            'projectPage' => [
                'title' => 'Select project page',
                'type' => 'dropdown',
            ]
        ]);
    }

    public function onRun()
    {
        $this->user = Auth::getUser();

        if (!($this->ticket = Ticket::find($this->dynamicParam('ticket')))) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->ticket->project->team->id) {
            App::abort(403);
        }
    }

    public function onUpdatePriority()
    {
        if (!($priority = post('priority'))) {
            return;
        }

        $this->onRun();

        $this->ticket->update([
            'priority' => $priority,
        ]);

        return [
            '#js-priority' => $this->renderPartial('@_priority')
        ];
    }

    public function onUpdateTicketTitle()
    {
        if (!($title = post('ticket_title'))) {
            return;
        }

        $this->onRun();

        $this->ticket->update([
            'name' => $title,
        ]);
    }

    public function onUpdateTicketDescription()
    {
        $this->onRun();

        $this->ticket->update([
            'description' => post('ticket_description'),
        ]);
    }

    public function onUpdateTicketDueDate()
    {
        $this->onRun();

        $this->ticket->update([
            'due_date' => empty(post('ticket_due_date')) ? null : post('ticket_due_date'),
        ]);
    }

    public function onUpdateTicketEstimation()
    {
        $this->onRun();

        $this->ticket->update([
            'time_estimation' => (post('ticket_estimation_hours', 0) * 60 * 60) + (post('ticket_estimation_minutes', 0) * 60)
        ]);
    }

    public function onToggleTimer()
    {
        $this->onRun();

        if ($timerRunning = $this->ticket->runningTimer()) {
            $timerRunning->update([
                'updated_at' => now()
            ]);
        } else {
            $this->ticket->timers()->save(new Timer([
                'user_id'    => Auth::getUser()->id,
                'created_at' => now(),
                'updated_at' => null,
            ]));
        }
    }

    public function onCreateChecklist()
    {
        $this->onRun();

        $this->ticket->checklists()->create([
            'title'      => request()->post('checklist') ? request()->post('checklist') : 'Checklist',
            'sort_order' => $this->ticket->checklists()->count()
        ]);

        return [
            '#js-checklists' => $this->renderPartial('@_checklists')
        ];
    }

    public function onCreateChecklistItem()
    {
        $this->onRun();

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        $checklist->items()->create([
            'description' => request()->post('checklist-item') ? request()->post('checklist-item') : 'Checklist item',
            'is_done'     => false,
            'sort_order'  => $checklist->items()->count()
        ]);

        return [
            '#js-checklist-' . post('checklist') => $this->renderPartial('@__checklist', ['checklist' => $checklist]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('@__add_checklist_item', ['checklist' => $checklist]),
        ];
    }

    public function onToggleChecklistItem()
    {
        $this->onRun();

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        $checklistItem = $checklist->items()->where('id', post('item'))->first();

        if (!$checklist || !$checklistItem) {
            return;
        }

        $checklistItem->update([
            'is_done' => !$checklistItem->is_done,
        ]);

        return [
            '#js-checklist-' . post('checklist') => $this->renderPartial('@__checklist', ['checklist' => $checklist]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('@__add_checklist_item', ['checklist' => $checklist]),
        ];
    }

    public function onDeleteChecklistItem()
    {
        $this->onRun();

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        $checklistItem = $checklist->items()->where('id', post('item'))->first();

        if (!$checklist || !$checklistItem) {
            return;
        }

        $checklistItem->delete();

        return [
            '#js-checklist-' . post('checklist') => $this->renderPartial('@__checklist', ['checklist' => $checklist]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('@__add_checklist_item', ['checklist' => $checklist]),
        ];
    }

    public function onDeleteChecklist()
    {
        $this->onRun();

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        if (!$checklist) return;

        $checklist->items()->delete();
        $checklist->delete();

        return [
            '#js-checklists' => $this->renderPartial('@_checklists'),
        ];
    }

    public function onFileUpload()
    {
        $this->onRun();

        if(request()->file('file')) {
            $fileName = explode('.', request()->file('file')->getClientOriginalName());
            $fileName = $fileName[0] . time() . '_' . $this->ticket->id . '.' . $fileName[1];

            $file = new File([
                'data'      => request()->file('file'),
                'title'     => $fileName,
                'is_public' => false,
            ]);

            $file->save();

            $this->ticket->files()->add($file);
        }

        return [
            '#js-ticket-files' => $this->renderPartial('@_files')
        ];
    }

    public function onDeleteFile()
    {
        $this->onRun();

        $file = $this->ticket->files()->where('id', post('file_id'))->first();

        unlink($file->getLocalPath());

        $file->delete();

        return [
            '#js-ticket-files' => $this->renderPartial('@_files')
        ];
    }

    public function onAddComment()
    {
        if (!($comment = post('comment'))) {
            return;
        }

        $this->onRun();

        Auth::getUser()->comments()->create([
            'comment'   => $comment,
            'ticket_id' => $this->ticket->id,
        ]);

        return [
            '#js-ticket-comments' => $this->renderPartial('@_comments')
        ];
    }

    public function onDeleteComment()
    {
        if (!($comment = post('comment_id'))) {
            return;
        }

        $this->onRun();

        $this->ticket->comments()
            ->where('parent_id', post('comment_id'))
            ->orWhere('id', post('comment_id'))
            ->delete();

        return [
            '#js-ticket-comments' => $this->renderPartial('@_comments')
        ];
    }

    public function onUpdateComment()
    {
        $this->onRun();

        $this->ticket->comments()->where('id', post('comment_id'))->update([
            'comment' => post('comment'),
        ]);

        return [
            '#js-ticket-comments' => $this->renderPartial('@_comments')
        ];
    }

    public function onReplyToComment()
    {
        $this->onRun();

        $this->ticket->comments()->where('id', post('comment_id'))->first()->replies()->create([
            'user_id'   => Auth::getUser()->id,
            'ticket_id' => $this->ticket->id,
            'comment'   => post('comment'),
        ]);

        return [
            '#js-ticket-comments' => $this->renderPartial('@_comments')
        ];
    }

    public function onDelete()
    {
        $this->onRun();

        $project = $this->ticket->project;

        $this->ticket->comments()->delete();
        $this->ticket->files()->delete();
        $this->ticket->checklists->each(function($checklist) {
            $checklist->items()->delete();
        });
        $this->ticket->checklists()->delete();
        $this->ticket->timers()->delete();
        $this->ticket->users()->detach();
        $this->ticket->tags()->detach();

        $this->ticket->delete();

        return redirect()->to($project->url());
    }

    public function onAddUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        $this->ticket->users()->attach($user);

        return [
            '#js-ticket-users'     => $this->renderPartial('@_users'),
            '#js-ticket-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->ticket->excludedUsers(),
            ]),
        ];
    }

    public function onSearchUsers()
    {
        $this->onRun();

        return [
            '#js-ticket-users'     => $this->renderPartial('@_users'),
            '#js-ticket-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->ticket->excludedUsers(post('query')),
                'query' => post('query'),
            ]),
        ];
    }

    public function onRemoveUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        $this->ticket->users()->detach($user);

        return [
            '#js-ticket-users'     => $this->renderPartial('@_users'),
            '#js-ticket-add-users' => $this->renderPartial('@_add_users', [
                'users' => $this->ticket->excludedUsers(),
            ]),
        ];
    }

    public function onCreateTag()
    {
        $this->onRun();

        $this->ticket->project->tags()->create([
            'title' => post('title', 'New tag'),
            'color' => post('color', '#444444'),
        ]);

        return [
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker')
        ];
    }

    public function onUpdateTag()
    {
        if (!($title = post('title') || !($color = post('color')))) {
            return;
        }

        $this->onRun();

        $this->ticket->project->tags()->where('id', post('tag'))->update([
            'title' => $title,
            'color' => $color,
        ]);

        return [
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker')
        ];
    }

    public function onSearchTags()
    {
        $this->onRun();

        if (empty(trim(post('query')))) {
            return [
                '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker')
            ];
        }

        $tags = $this->ticket->availableTags()->filter(function ($tag) {
            return Str::contains(strtolower($tag->title), trim(post('query')));
        });

        return [
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker', [
                'tags' => $tags,
            ])
        ];
    }

    public function onAddTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        $this->ticket->tags()->attach($tag);

        return [
            '#js-ticket-tags' => $this->renderPartial('@_tags'),
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker'),
        ];
    }

    public function onRemoveTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        $this->ticket->tags()->detach($tag);

        return [
            '#js-ticket-tags' => $this->renderPartial('@_tags'),
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker'),
        ];
    }

    public function onDeleteTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        $this->ticket->project->tickets->each(function ($ticket) use ($tag) {
            $ticket->tags()->detach($tag);
        });

        $this->ticket->project->tags()->where('id', $tag)->delete();

        return [
            '#js-ticket-tags' => $this->renderPartial('@_tags'),
            '#js-ticket-available-tags' => $this->renderPartial('@_tags_picker'),
        ];
    }

    public function getProjectPageOptions()
    {
        return ['' => '---'] + $this->getAllPages();
    }

    protected function getEstimation($estimation)
    {
        $result = 0;

        $hours = $estimation[0] ?? false;
        $minutes = $estimation[1] ?? false;

        if ($hours) {
            $result += ($hours * 60 * 60);
        }

        if ($minutes) {
            $result += ($minutes * 60);
        }

        return $result;
    }
}
