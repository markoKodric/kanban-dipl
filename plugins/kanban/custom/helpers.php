<?php

if (!function_exists('validate_request'))
{
    /**
     * Validates current request and flashes errors to the session.
     * Returns true if the request is valid or false if it's not.
     *
     * @param array $rules
     * @return bool
     */
    function validate_request(array $rules, $messages = [])
    {
        if(post('_ajax_validate')) {
            $rules['_ajax_validate_ensure_failure'] = 'required';
        }

        $validator = Validator::make(request()->all(), $rules, $messages);

        if($validator->fails()) {
            session()->flash('errors', $validator->messages());

            return false;
        }

        session()->forget('errors');

        return true;
    }
}

if (!function_exists('recursive_unset'))
{
    function recursive_unset(&$array, $key)
    {
        unset($array[$key]);

        foreach ($array as &$value) {
            if (is_array($value)) {
                recursive_unset($value, $key);
            }
        }
    }
}