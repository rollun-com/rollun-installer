<?php
/**
 * Created by PhpStorm.
 * User: victorsecuring
 * Date: 10.12.17
 * Time: 1:51 PM
 */

namespace rollun\installer\Example;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use rollun\installer\Install\InstallerAbstract;
use rollun\installer\InstallerException;

class ExampleSecondInstaller extends InstallerAbstract
{

    /**
     * install
     * @return array
     */
    public function install()
    {
        $returnConfig = $this->callInstaller(ExampleOneInstaller::class);
        try {
            $config = $this->container->get("config");
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            throw new InstallerException("Exception by run isInstall.", $e->getCode(), $e);
        }
        if(isset($config[ExampleOneInstaller::class]) && $config[ExampleOneInstaller::class] === true) {
            $this->consoleIO->write("container reload");
            $this->consoleIO->write(print_r($returnConfig, true));
        } else {
            $this->consoleIO->write("container not reload");
        }

        return [
            ExampleSecondInstaller::class => true
        ];
    }

    /**
     * Clean all installation
     * @return void
     */
    public function uninstall()
    {
        // TODO: Implement uninstall() method.
    }

    /**
     * Return string with description of installable functional.
     * @param string $lang ; set select language for description getted.
     * @return string
     */
    public function getDescription($lang = "en")
    {
        return "Test installer";
    }

    /**
     * Return true if install, or false else
     * @return bool
     */
    public function isInstall()
    {
        try {
            $config = $this->container->get("config");
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            throw new InstallerException("Exception by run isInstall.", $e->getCode(), $e);
        }
        return (
            isset($config[ExampleSecondInstaller::class]) &&
            $config[ExampleSecondInstaller::class] === true
        );
    }
}