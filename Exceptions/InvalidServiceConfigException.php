<?php

namespace DefenderService\DamMicroService\Exceptions;

class InvalidServiceConfigException extends Exception
{
    /**
     * Raw error info.
     *
     * @var array|string
     */
    public $raw;

    /**
     * Bootstrap.
     *
     * @author changsheng.gu@vcg.com
     *
     * @param string       $message
     * @param array|string $raw
     * @param int|string   $code
     */
    public function __construct($message, $raw = '', $code = 1)
    {
        parent::__construct($message, intval($code));

        $this->raw = $raw;
    }
}
