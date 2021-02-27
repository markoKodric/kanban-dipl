<?php namespace Kanban\Custom\Traits;

use Auth;
use System\Models\File;
use Illuminate\Support\Str;
use Kanban\Custom\Models\Timer;
use Kanban\Custom\Classes\Notification;

trait TicketHandler
{
    public function onSingleTicketUpdatePriority()
    {
        if (!($priority = post('priority'))) return;

        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'priority' => $priority,
        ]);

        return [
            '#js-priority'                 => $this->renderPartial('ticketsingle/_ticket_priority', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketUpdateTicketTitle()
    {
        if (!($title = post('ticket_title'))) return;

        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'name' => $title,
        ]);

        return [
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketUpdateTicketDescription()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'description' => post('ticket_description'),
        ]);

        return [
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketUpdateTicketDueDate()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'due_date' => empty(post('ticket_due_date')) ? null : post('ticket_due_date'),
        ]);
    }

    public function onSingleTicketUpdateTicketEstimation()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'time_estimation' => (post('ticket_estimation_hours', 0) * 60 * 60) + (post('ticket_estimation_minutes', 0) * 60)
        ]);

        return [
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketToggleTimer()
    {
        $this->onRun();

        if (!$this->ticket->users()->where('id', $this->user->id)->exists()) return $this->notifyUser('Unauthorized action.');

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

    public function onSingleTicketCreateChecklist()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $checklist = $this->ticket->checklists()->create([
            'title'      => request()->post('checklist') ? request()->post('checklist') : 'Checklist',
            'sort_order' => $this->ticket->checklists()->count()
        ]);

        return [
            '@#js-checklists' => $this->renderPartial('ticketsingle/_ticket_checklist', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            'checklist'       => $checklist->id,
        ];
    }

    public function onSingleTicketCreateChecklistItem()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        $checklist->items()->create([
            'description' => request()->post('checklist-item') ? request()->post('checklist-item') : 'Checklist item',
            'is_done'     => false,
            'sort_order'  => $checklist->items()->count()
        ]);

        $checklist->refresh();

        return [
            '#js-checklist-' . post('checklist')          => $this->renderPartial('ticketsingle/__checklist', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('ticketsingle/__ticket_add_checklist_item', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id                => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketToggleChecklistItem()
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

        $checklist->refresh();

        return [
            '#js-checklist-' . post('checklist')          => $this->renderPartial('ticketsingle/__checklist', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('ticketsingle/__ticket_add_checklist_item', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id                => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
            'checklist'                                   => $checklist->id,
        ];
    }

    public function onSingleTicketDeleteChecklistItem()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        $checklistItem = $checklist->items()->where('id', post('item'))->first();

        if (!$checklist || !$checklistItem) {
            return;
        }

        $checklistItem->delete();

        $checklist->refresh();

        return [
            '#js-checklist-' . post('checklist')          => $this->renderPartial('ticketsingle/__checklist', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#js-add-checklist-item-' . post('checklist') => $this->renderPartial('ticketsingle/__ticket_add_checklist_item', ['checklist' => $checklist, 'ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id                => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
            'checklist'                                   => $checklist->id,
        ];
    }

    public function onSingleTicketDeleteChecklist()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $checklist = $this->ticket->checklists()->where('id', post('checklist'))->first();

        if (!$checklist) return;

        $checklist->items()->delete();
        $checklist->delete();

        return [
            '#js-checklists'               => $this->renderPartial('ticketsingle/_checklists', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
            'checklist'                    => $checklist->id,
        ];
    }

    public function onSingleTicketFileUpload()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        if (request()->file('file')) {
            $fileName = explode('.', request()->file('file')->getClientOriginalName());
            $fileName = $fileName[0] . time() . '_' . $this->ticket->id . '.' . $fileName[1];

            $file = new File([
                'data'      => request()->file('file'),
                'title'     => $fileName,
                'is_public' => true,
            ]);

            $file->save();

            $this->ticket->files()->add($file);
        }

        return [
            '#js-ticket-files'             => $this->renderPartial('ticketsingle/_ticket_files', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketDeleteFile()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $file = $this->ticket->files()->where('id', post('file_id'))->first();

        unlink($file->getLocalPath());

        $file->delete();

        return [
            '#js-ticket-files'             => $this->renderPartial('ticketsingle/_ticket_files', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketAddComment()
    {
        if (!($comment = post('comment'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        Auth::getUser()->comments()->create([
            'comment'   => $comment,
            'ticket_id' => $this->ticket->id,
        ]);

        return [
            '#js-ticket-comments'          => $this->renderPartial('ticketsingle/_ticket_comments', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketDeleteComment()
    {
        if (!($comment = post('comment_id'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->comments()
            ->where('parent_id', post('comment_id'))
            ->orWhere('id', post('comment_id'))
            ->delete();

        return [
            '#js-ticket-comments'          => $this->renderPartial('ticketsingle/_ticket_comments', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketUpdateComment()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->comments()->where('id', post('comment_id'))->update([
            'comment' => post('comment'),
        ]);

        return [
            '#js-ticket-comments' => $this->renderPartial('ticketsingle/_ticket_comments', ['ticket' => $this->ticket])
        ];
    }

    public function onSingleTicketReplyToComment()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->comments()->where('id', post('comment_id'))->first()->replies()->create([
            'user_id'   => Auth::getUser()->id,
            'ticket_id' => $this->ticket->id,
            'comment'   => post('comment'),
        ]);

        return [
            '#js-ticket-comments'          => $this->renderPartial('ticketsingle/_ticket_comments', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketDelete()
    {
        $this->onRun();

        if (!$this->user->can('ticket.delete')) return $this->notifyUser('Unauthorized action.');

        $project = $this->ticket->project;

        $this->ticket->comments()->delete();
        $this->ticket->files()->delete();
        $this->ticket->checklists->each(function ($checklist) {
            $checklist->items()->delete();
        });
        $this->ticket->checklists()->delete();
        $this->ticket->timers()->delete();
        $this->ticket->users()->detach();
        $this->ticket->tags()->detach();

        $this->ticket->delete();

        return redirect()->to($project->url());
    }

    public function onSingleTicketAddUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.users.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->users()->attach($user);

        return [
            '#js-ticket-users'             => $this->renderPartial('ticketsingle/_ticket_users', ['ticket' => $this->ticket]),
            '#js-ticket-add-users'         => $this->renderPartial('ticketsingle/_ticket_add_users', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketSearchUsers()
    {
        $this->onRun();

        return [
            '#js-ticket-users'     => $this->renderPartial('ticketsingle/_ticket_users', ['ticket' => $this->ticket]),
            '#js-ticket-add-users' => $this->renderPartial('ticketsingle/_ticket_add_users', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketRemoveUser()
    {
        if (!($user = post('user'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.users.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->users()->detach($user);

        return [
            '#js-ticket-users'             => $this->renderPartial('ticketsingle/_ticket_users', ['ticket' => $this->ticket]),
            '#js-ticket-add-users'         => $this->renderPartial('ticketsingle/_ticket_add_users', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketCreateTag()
    {
        $this->onRun();

        if (!$this->user->can('ticket.tags.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->project->tags()->create([
            'title' => post('title', 'New tag'),
            'color' => post('color', '#444444'),
        ]);

        return [
            '#js-ticket-available-tags' => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket])
        ];
    }

    public function onSingleTicketUpdateTag()
    {
        if (!($title = post('title'))) {
            return;
        }

        if (!($color = post('color'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.tags.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->project->tags()->where('id', post('tag'))->update([
            'title' => $title,
            'color' => $color,
        ]);

        return [
            '#js-ticket-available-tags'    => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketSearchTags()
    {
        $this->onRun();

        if (empty(trim(post('query')))) {
            return [
                '#js-ticket-available-tags' => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket])
            ];
        }

        $tags = $this->ticket->availableTags()->filter(function ($tag) {
            return Str::contains(strtolower($tag->title), trim(post('query')));
        });

        return [
            '#js-ticket-available-tags' => $this->renderPartial('ticketsingle/_ticket_tags_picker', [
                'tags'   => $tags,
                'ticket' => $this->ticket,
            ])
        ];
    }

    public function onSingleTicketAddTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.tags.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->tags()->attach($tag);

        return [
            '#js-ticket-tags'              => $this->renderPartial('ticketsingle/_ticket_tags', ['ticket' => $this->ticket]),
            '#js-ticket-available-tags'    => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketRemoveTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.tags.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->tags()->detach($tag);

        return [
            '#js-ticket-tags'              => $this->renderPartial('ticketsingle/_ticket_tags', ['ticket' => $this->ticket]),
            '#js-ticket-available-tags'    => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketDeleteTag()
    {
        if (!($tag = post('tag'))) {
            return;
        }

        $this->onRun();

        if (!$this->user->can('ticket.tags.manage')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->project->tickets->each(function ($ticket) use ($tag) {
            $ticket->tags()->detach($tag);
        });

        $this->ticket->project->tags()->where('id', $tag)->delete();

        return [
            '#js-ticket-tags'              => $this->renderPartial('ticketsingle/_ticket_tags', ['ticket' => $this->ticket]),
            '#js-ticket-available-tags'    => $this->renderPartial('ticketsingle/_ticket_tags_picker', ['ticket' => $this->ticket]),
            '#ticket-' . $this->ticket->id => $this->renderPartial('projectsingle/_ticket', ['ticket' => $this->ticket]),
        ];
    }

    public function onSingleTicketArchive()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'is_archived' => true,
        ]);

        return [
            '#js-ticket-actions' => $this->renderPartial('ticketsingle/_ticket_actions', ['ticket' => $this->ticket])
        ];
    }

    public function onSingleTicketRestore()
    {
        $this->onRun();

        if (!$this->user->can('ticket.edit')) return $this->notifyUser('Unauthorized action.');

        $this->ticket->update([
            'is_archived' => false,
        ]);

        return [
            '#js-ticket-actions' => $this->renderPartial('ticketsingle/_ticket_actions', ['ticket' => $this->ticket])
        ];
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

    protected function notifyUser($message)
    {
        return [
            '@#js-notifications' => $this->renderPartial('snippets/notification', ['item' => Notification::error($message)])
        ];
    }
}