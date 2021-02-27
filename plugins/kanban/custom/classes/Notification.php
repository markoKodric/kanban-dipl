<?php namespace Kanban\Custom\Classes;

class Notification
{
    protected $message;

    protected $type;

    const TYPE_INFO = 1;
    const TYPE_SUCCESS = 2;
    const TYPE_ALERT = 3;
    const TYPE_ERROR = 4;

    public function __construct($message, $type = null)
    {
        $this->message = $message;

        $this->type = $type ?? static::TYPE_INFO;
    }

    public function toArray()
    {
        return [
            'message' => $this->message,
            'type'    => $this->type,
        ];
    }

    public static function info($message)
    {
        return (new static($message, static::TYPE_INFO))->toArray();
    }

    public static function success($message)
    {
        return (new static($message, static::TYPE_SUCCESS))->toArray();
    }

    public static function alert($message)
    {
        return (new static($message, static::TYPE_ALERT))->toArray();
    }

    public static function error($message)
    {
        return (new static($message, static::TYPE_ERROR))->toArray();
    }
}