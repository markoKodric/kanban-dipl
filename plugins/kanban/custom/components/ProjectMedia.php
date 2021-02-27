<?php namespace Kanban\Custom\Components;

use App;
use Auth;
use Illuminate\Support\Str;
use System\Models\File;
use RainLab\User\Models\User;
use Cms\Classes\ComponentBase;
use Kanban\Custom\Models\Project;
use Kanban\Custom\Traits\MenuHelpers;
use Kanban\Custom\Traits\RenderingHelpers;
use Kanban\Custom\Traits\DynamicParameters;

class ProjectMedia extends ComponentBase
{
    use RenderingHelpers, MenuHelpers, DynamicParameters;

    protected $parameters = ['project'];

    public $project;

    public $user;

    public $files;

    public $selectedFile;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - Project media',
            'description' => 'Display project media files on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = User::where('id', Auth::getUser()->id)->with('team')->first();

        if ($projectId = $this->dynamicParam('project')) {
            $this->project = Project::find($projectId);
        } else {
            $this->project = Project::find(session()->get('currentProject')) ?? $this->user->team->defaultProject();

            if ($this->project) {
                return redirect($this->project->mediaUrl());
            }
        }

        if (!$this->project) {
            return redirect('/404');
        }

        if ($this->user->team->id != $this->project->team->id) {
            App::abort(403);
        }

        session()->put('currentProject', $this->project->id);

        $this->files = $this->initFiles();
    }

    public function onFileUpload()
    {
        session()->forget('project_media_filters');

        $this->onRun();

        if (request()->file('file')) {
            $fileName = explode('.', request()->file('file')->getClientOriginalName());
            $fileName = $fileName[0] . time() . '_' . $this->project->id . '.' . $fileName[1];

            $file = new File([
                'data'      => request()->file('file'),
                'title'     => $fileName,
                'is_public' => true,
            ]);

            $file->save();

            $this->project->files()->add($file);
        }

        $this->onRun();

        return [
            '#js-upload'      => $this->renderPartial('@_upload'),
            '#js-media-files' => $this->renderPartial('@_files'),
        ];
    }

    public function onDeleteFile()
    {
        $this->onRun();

        $file = $this->project->files()->where('id', post('file'))->first();

        unlink($file->getLocalPath());

        $file->delete();

        $this->onRun();

        return [
            '#js-media-files'   => $this->renderPartial('@_files'),
            '#js-selected-file' => $this->renderPartial('@_selection'),
        ];
    }

    public function onSelectFile()
    {
        $this->onRun();

        session()->put('project_media_file', post('file'));

        $this->initSelectedFile();

        return [
            '#js-media-files'   => $this->renderPartial('@_files'),
            '#js-selected-file' => $this->renderPartial('@_selection'),
        ];
    }

    public function onFilter()
    {
        session()->put('project_media_filters', post('filters'));

        $this->onRun();

        return [
            '#js-media-files'   => $this->renderPartial('@_files'),
            '#js-selected-file' => $this->renderPartial('@_selection'),
        ];
    }

    protected function initSelectedFile()
    {
        if (session()->has('project_media_file')) {
            $this->selectedFile = $this->project->files->where('id', session()->get('project_media_file'))->first();
        }
    }

    protected function initFiles()
    {
        $filters = array_merge([
            "search"    => "",
            "type"      => "all",
            "order"     => "file_name",
            "direction" => "asc",
        ], session()->get('project_media_filters', []));

        $sortDirection = $filters['direction'] == 'asc' ? 'sortBy' : 'sortByDesc';

        $this->files = $this->project->files->filter(function ($item) use ($filters) {
            switch ($filters['type']) {
                case 'images':
                    return in_array($item->content_type, $this->imageTypes());
                case 'videos':
                    return in_array($item->content_type, $this->videoTypes());
                case 'audio':
                    return in_array($item->content_type, $this->audioTypes());
                case 'documents':
                    return in_array($item->content_type, $this->docTypes());
                default:
                    return $item;
            }
        })->$sortDirection($filters['order']);

        if ($searchQuery = $filters['search']) {
            $this->files = $this->files->filter(function ($item) use ($searchQuery) {
                return Str::contains($item->file_name, $searchQuery);
            });
        }

        return $this->files;
    }

    protected function imageTypes()
    {
        return [
            'image/png',
            'image/jpg',
            'image/jpeg',
            'image/apng',
            'image/avif',
            'image/webp',
            'image/gif',
            'image/svg+xml',
        ];
    }

    protected function videoTypes()
    {
        return [
            'video/mp4',
            'video/webm',
            'video/ogg',
            'application/ogg',
        ];
    }

    protected function audioTypes()
    {
        return [
            'audio/mpeg',
            'audio/wave',
            'audio/wav',
            'audio/x-wav',
            'audio/x-pn-wav',
            'audio/webm',
            'audio/ogg',
            'audio/vorbis',
            'application/ogg',
        ];
    }

    protected function docTypes()
    {
        return [
            'application/octet-stream',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
            'text/html',
        ];
    }
}
