<?php namespace Kanban\Custom\Components;

use Auth;
use System\Models\File;
use Cms\Classes\ComponentBase;
use RainLab\Pages\Classes\Page;
use Illuminate\Support\Facades\Hash;
use Kanban\Custom\Traits\RenderingHelpers;

class UserProfile extends ComponentBase
{
    use RenderingHelpers;

    public $user;

    public function componentDetails()
    {
        return [
            'name'        => 'Kanban - User profile',
            'description' => 'Display user profile on page.'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->user = Auth::getUser();
    }

    public function onUpdateProfile()
    {
        $this->onRun();

        $rules = [
            'first_name'  => 'required',
            'email'       => 'required|email',
        ];

        if (post('password')) {
            $rules['password'] = 'required|min:8|confirmed';
        }

        $requestValid = validate_request($rules);

        if (!$requestValid) {
            return [
                '#js-profile' => $this->renderPartial('@_form', ['formErrors' => session()->get('errors')])
            ];
        }

        $data = [
            'name'     => post('first_name', $this->user->first_name),
            'surname'  => post('last_name', $this->user->surname),
            'username' => post('username', $this->user->username),
            'email'    => post('email', $this->user->email),
        ];

        if (post('password')) {
            $data['password'] = Hash::make(post('password'));
        }

        if(request()->file('picture')) {
            $fileName = explode('.', request()->file('picture')->getClientOriginalName());
            $fileName = md5($fileName[0] . time() . '_' . $this->user->id . '.' . $fileName[1]);

            $file = new File([
                'data'      => request()->file('picture'),
                'title'     => $fileName,
                'is_public' => true,
            ]);

            $file->save();

            $this->user->avatar()->add($file);

            $data['picture'] = str_replace(url('/'), '', $file->getPath());
        }

        $this->user->forceFill($data)->save();

        return [
            '#js-profile'      => $this->renderPartial('@_form'),
            '#js-profile-link' => $this->renderPartial('layout/_sidebar_profile'),
        ];
    }

    public function onRemovePicture()
    {
        $this->onRun();

        if ($this->user->avatar) {
            unlink($this->user->avatar->getLocalPath());

            $this->user->avatar->delete();
        }

        $this->user->picture = null;
        $this->user->save();

        return [
            '#js-profile'      => $this->renderPartial('@_form'),
            '#js-profile-link' => $this->renderPartial('layout/_sidebar_profile'),
        ];
    }

    public function onDeactivateAccount()
    {
        $this->onRun();

        $this->user->delete();

        Auth::logout();

        return redirect(Page::url('login'));
    }
}
