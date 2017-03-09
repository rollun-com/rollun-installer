<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 09.03.17
 * Time: 16:54
 */

namespace rollun\installer;

use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use FilesystemIterator;
use Interop\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use rollun\installer\Install\InstallerAbstract;
use rollun\installer\Install\InstallerInterface;

class LibInstallerManager
{
    /** @var  PackageInterface */
    protected $package;

    /** @var  string */
    protected $rootNamespace;

    /** @var  ConsoleIO */
    protected $cliIO;

    /** @var  ContainerInterface */
    protected $container;

    /** @var array */
    protected $installers;

    /** @var  string */
    protected $src;


    /**
     * LibInstallerManager constructor.
     * @param PackageInterface $package
     * @param ContainerInterface $container
     * @param ConsoleIO $cliIO
     * @param string $src
     */
    public function __construct(PackageInterface $package, ContainerInterface $container, ConsoleIO $cliIO, $src = null)
    {

        $this->package = $package;
        $this->container = $container;
        $this->cliIO = $cliIO;

        $autoload = $package->getAutoload();
        if (isset($autoload['psr-4'])) {
            $this->rootNamespace = array_keys($autoload['psr-4'])[0];

            if (isset($src)) {
                $this->src = $src;
            } else if (isset($autoload['psr-4'][$this->rootNamespace])) {
                $this->src = realpath('vendor') . DIRECTORY_SEPARATOR .
                    $package->getPrettyName() . DIRECTORY_SEPARATOR .
                    $autoload['psr-4'][$this->rootNamespace];
            }
            if (!isset($this->src) || !is_string($this->src) || !is_dir($this->src)) {
                throw new \RuntimeException("Can't find src for package: " . $this->package->getPrettyName());
            }
        } else {
            throw new \RuntimeException("Lib don't implements psr-4");
        }

        $installers = $this->findInstaller($this->src);
        foreach ($installers as $installerClass) {
            try {
                /** @var  InstallerAbstract $installer */
                $installer = new $installerClass($this->container, $this->cliIO);
                $this->installers[$installerClass] = $installer;
            } catch (\Exception $exception) {
                $this->cliIO->writeError(
                    "Installer: $installerClass crash by exception with message: " .
                    $exception->getMessage()
                );
            }
        }
    }
    /**
     * @return PackageInterface
     */
    public function getPackage()
    {
        return $this->package;
    }


    /**
     * lib dir
     * @param $dir string
     * root namespace for lib.
     * @return string[]
     */
    protected function findInstaller($dir)
    {
        $installer = [];

        if (is_dir($dir)) {
            $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS |
                FilesystemIterator::KEY_AS_PATHNAME);
        } else {
            return $installer;
        }

        foreach ($iterator as $item) {
            //Save only class who implement InstallerInterface and has Installer in name
            /** @var $item RecursiveDirectoryIterator */
            if (!preg_match('/^(\.)|(vendor)/', $item->getFilename())) {
                if ($item->isDir()) {
                    $installer = array_merge($installer, $this->findInstaller($item->getPathname()));
                } elseif (preg_match('/Installer.php^/', $item->getFilename())) {
                    //get path to lib
                    $match = [];
                    $path = preg_match('/\/vendor\/([\w-\/]+)/', $item->getPath(), $match)
                    && isset($match[1]) ? $match[1] : $item->getPath();

                    //get path to src
                    $match = [];
                    $path = preg_match('/\/src\/([\w-\/]+)/', $path, $match)
                    && isset($match[1]) ? $match[1] : null;

                    $classNameSpace = $this->rootNamespace . str_replace(DIRECTORY_SEPARATOR, '\\', $path);
                    $class = rtrim($classNameSpace, '\\') . '\\' . $item->getBasename('.php');
                    if (class_exists($class)) {
                        $reflector = new \ReflectionClass($class);
                        if ($reflector->implementsInterface(InstallerInterface::class) &&
                            $reflector->isInstantiable()
                        ) {
                            $installer[] = $reflector->getName();
                        }
                    }
                }
            }
        }
        return $installer;
    }

    /**
     * @return array
     */
    public function getInstallers()
    {
        return $this->installers;
    }

    public function getInstaller($name)
    {
        return isset($this->installers[$name]) ? $this->installers[$name] : null;
    }

    /**
     * @return string
     */
    public function getRootNamespace()
    {
        return $this->rootNamespace;
    }

}
