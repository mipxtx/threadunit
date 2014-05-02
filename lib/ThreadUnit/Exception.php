<?php
/**
 * @author: mix
 * @date: 09.03.14
 */
namespace ThreadUnit;

/**
 * Class Exception
 *
 * @package ThreadUnit
 */
class Exception extends \Exception{

    const GENERAL_ERROR = 1;

    public function __construct($message = "", $code = null){
        if($code === null){
            $code = self::GENERAL_ERROR;
        }
        parent::__construct($message, $code);
    }

}