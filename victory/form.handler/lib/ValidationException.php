<?php
namespace Victory\FormHandler\Lib;

class ValidationException extends \Exception
{
    protected $errors;

    public function __construct($message = "", $errors = [], $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
