<?php
/**
 * @link http://www.ipaya.cn/
 * @copyright Copyright (c) 2018 ipaya.cn
 */

namespace iPaya\Yii\Swoole;


class ErrorHandler extends \yii\web\ErrorHandler
{
    protected function renderException($exception)
    {
        throw $exception;
    }
}
