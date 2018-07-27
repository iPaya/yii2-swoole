<?php
/**
 * @link http://www.ipaya.cn/
 * @copyright Copyright (c) 2018 ipaya.cn
 */

namespace iPaya\Yii\Swoole;

use yii\web\Application;

define('YII_ENABLE_ERROR_HANDLER', false);

/**
 * @package iPaya\Yii\Swoole
 */
class HttpServer extends \iPaya\Swoole\HttpServer
{
    /**
     * @var string Vendor 目录
     */
    private $vendorPath;
    /**
     * @var string Yii 应用类
     */
    private $applicationClass = Application::class;
    /**
     * @var array Yii 应用配置
     */
    private $applicationConfig = [];

    /**
     * NOTE: 暂时只支持 yii\web\Application
     *
     * @var \yii\base\Application|\yii\web\Application|\yii\console\Application
     */
    private $application;
    /**
     * @var array
     */
    private $applicationComponents = [];

    /**
     * @return array
     */
    public function getApplicationComponents(): array
    {
        return $this->applicationComponents;
    }

    /**
     * @param array $applicationComponents
     */
    protected function setApplicationComponents(array $applicationComponents): void
    {
        $this->applicationComponents = $applicationComponents;
    }

    /**
     * @return \yii\base\Application|\yii\console\Application|Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param \yii\base\Application|\yii\console\Application|Application $application
     */
    public function setApplication($application)
    {
        $this->application = $application;
    }

    /**
     * @inheritDoc
     */
    public function onSwooleWorkerStart($server, int $workerId): void
    {
        parent::onSwooleWorkerStart($server, $workerId);

        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        require $this->getVendorPath() . '/yiisoft/yii2/Yii.php';

        \Yii::setAlias('@webroot', $this->getDocumentRoot());
        \Yii::setAlias('@web', '/');

        $config = $this->getApplicationConfig();
        $applicationClass = $this->getApplicationClass();

        if ($applicationClass == \yii\web\Application::class) {
            $config['components']['request']['class'] = Request::class;
            $config['components']['request']['scriptFile'] = $config['components']['request']['scriptFile'] ?? $this->getDocumentRoot() . $this->getScriptFile();
            $config['components']['request']['scriptFile'] = $config['components']['request']['scriptUrl'] ?? $this->getScriptFile();
            $config['components']['response']['class'] = Response::class;
            $config['components']['errorHandler']['class'] = ErrorHandler::class;
        }

        /** @var \yii\base\Application|\yii\web\Application|\yii\console\Application $application */
        $application = new $applicationClass($config);
        $this->setApplication($application);
        $this->setApplicationComponents($application->getComponents());
    }

    /**
     * @return array
     */
    public function getApplicationConfig(): array
    {
        return $this->applicationConfig;
    }

    /**
     * @param array $applicationConfig
     */
    public function setApplicationConfig(array $applicationConfig): void
    {
        $this->applicationConfig = $applicationConfig;

        if ($this->getVendorPath() == null && isset($this->applicationConfig['vendorPath'])) {
            $this->setVendorPath($this->applicationConfig['vendorPath']);
        }
    }

    /**
     * @return mixed
     */
    public function getVendorPath()
    {
        return $this->vendorPath;
    }

    /**
     * @return string
     */
    public function getApplicationClass(): string
    {
        return $this->applicationClass;
    }

    /**
     * @param string $applicationClass
     */
    public function setApplicationClass(string $applicationClass): void
    {
        $this->applicationClass = $applicationClass;
    }

    /**
     * @param mixed $vendorPath
     */
    public function setVendorPath($vendorPath): void
    {
        $this->vendorPath = $vendorPath;
    }

    protected function initialize(): void
    {
        parent::initialize();
        $this->setRequestHandler(new RequestHandler($this));
    }

    /**
     * @inheritDoc
     */
    protected function beforeStart(): bool
    {
        if (!parent::beforeStart()) {
            return false;
        }

        if ($this->getVendorPath() == null) {
            $this->stderr('<error>必须配置 "vendorPath", 通过 \iPaya\Yii\Swoole\HttpServer::setVendorPath() 设置.</error>');
            return false;
        }

        return true;
    }


}
