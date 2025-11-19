<?php

namespace Codeages\PhalconBiz;

use Phalcon\Annotations\Adapter\AdapterInterface;
use Symfony\Component\Finder\Finder;
use Phalcon\Mvc\Router\Annotations as AnnotationRouter;

class AnnotationRouteDiscovery
{
    /**
     * @var AnnotationRouter
     */
    protected $router;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    protected $debug;

    protected $cacheDir;

    public function __construct(AnnotationRouter $router, AdapterInterface $adapter, $cacheDirectory, $debug = true)
    {
        $this->router = $router;
        $this->adapter = $adapter;

        if (!is_dir($cacheDirectory) || !is_writable($cacheDirectory)) {
            throw new \RuntimeException("Cache directory {$cacheDirectory} is not exist or not writeable.");
        }
        $this->cacheDirectory = rtrim($cacheDirectory, "\/\\");

        $this->debug = $debug;
    }

    /**
     * 在指定路径下搜寻Controller，查找路由，并添加到路由表中去
     *
     * @param string $namespace Controller 的命名空间
     * @param string $directory Controller 的目录
     */
    public function discover($namespace, $directory)
    {
        if ($this->debug) {
            $routes = $this->scanRoutes($namespace, $directory);
        } else {
            $routes = $this->getRoutesFromCache($namespace, $directory);
        }

        foreach ($routes as $route) {
            $this->router->addResource($route['class'], $route['routePrefix']);
        }
    }

    /**
     * 从缓存中读取路由表，如不存在则生成路由表缓存
     *
     * @param string $namespace Controller 的命名空间
     * @param string $directory Controller 的目录
     *
     * @return array 路由表
     */
    protected function getRoutesFromCache($namespace, $directory)
    {
        $cachePath = sprintf('%s/%s__routes.php', $this->cacheDirectory, str_replace('\\', '_', strtolower($namespace)));

        if (file_exists($cachePath)) {
            return require $cachePath;
        }

        $routes = $this->scanRoutes($namespace, $directory);

        if (false === file_put_contents($cachePath, '<?php return '.var_export($routes, true).'; ')) {
            throw new \RuntimeException('Cache directory can not be writen.');
        }

        return $routes;
    }

    /**
     * 在目录下扫描所有 Controller，以获取路由表
     *
     * @param string $namespace Controller 的命名空间
     * @param string $directory Controller 的目录
     *
     * @return array 路由表
     */
    protected function scanRoutes($namespace, $directory)
    {
        $routes = [];
        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php')->sortByName();

        foreach ($finder as $file) {
            $class = $namespace.'\\'.$file->getBasename('.php');

            if (!class_exists($class)) {
                continue;
            }

            $reflector = $this->adapter->get($class);
            $annotations = $reflector->getClassAnnotations();

            if (!$annotations) {
                continue;
            }

            if (!$annotations->has('RoutePrefix')) {
                continue;
            }

            $anno = $annotations->get('RoutePrefix');

            $routes[] = [
                'class' => $class,
                'routePrefix' => $anno->getArgument(0),
            ];
        }

        return $routes;
    }
}
