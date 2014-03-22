<?php

namespace SrUnit;

use Composer\Autoload\ClassLoader;
use RuntimeException;

/**
 * Class Bootstrap
 *
 * @link http://www.superReal.de
 * @copyright (C) superReal GmbH | Agentur für Neue Kommunikation
 * @package superreal/srunit
 * @author Jens Wiese <j.wiese AT superreal.de>
 */
class Bootstrap
{
    /** @var string */
    protected $shopBaseDir;

    /** @var  ClassLoader */
    protected $composerClassLoader;

    /** @var bool */
    protected $isOxidLoaded = false;

    /** @var bool */
    protected $isOxidMandatory = false;

    /** @var bool  */
    protected $isOxidBypassed = false;


    /**
     * Creates new Bootstrap object
     *
     * @return Bootstrap
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $testDir = $this->retrieveTestDirectory();
        $this->shopBaseDir = realpath($testDir . '/../../../');
        $this->moduleDir = realpath($testDir . '/../');

        register_shutdown_function(array($this, 'deactivateSrUnit'));
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->deactivateSrUnit();
    }

    /**
     * Enable loading of Oxid bootstrapping
     *
     * @return $this
     */
    public function makeOxidMandatory()
    {
        $this->isOxidMandatory = true;

        return $this;
    }

    public function bypassOxid()
    {
        if ($this->isOxidLoaded()) {
            $this->activateSrUnit();
        }

        $this->isOxidBypassed = true;
    }

    /**
     * Bootstraps Unit Test Environment
     *
     * @throws \RuntimeException
     */
    public function bootstrap()
    {
        $this->loadComposerAutoloader();
        $this->loadOxidFramework();
        $this->registerModuleAutoloader();

        if ($this->isOxidMandatory() && !$this->isOxidLoaded()) {
            throw new RuntimeException('Could not bootstrap test environment, due to a not loaded Oxid framework.');
        }
    }

    /**
     * Returns whether or not Oxid framework is set mandatory
     *
     * @return bool
     */
    public function isOxidMandatory()
    {
        return $this->isOxidMandatory;
    }

    /**
     * Returns whether or not OXID framework was bootrapped
     *
     * @return bool
     */
    public function isOxidLoaded()
    {
        return $this->isOxidLoaded;
    }

    /**
     * Returns Composer ClassLoader
     *
     * @return ClassLoader
     */
    public function getComposerClassLoader()
    {
        return $this->composerClassLoader;
    }

    /**
     * @return $this
     */
    public function activateSrUnit()
    {
        define('SRUNIT_TESTS', true);

        $module = new \oxModule();
        $module->load('srunit');
        if (false === $module->isActive()) {
            $module->activate();
        }

        return $this;
    }

    /**
     *
     */
    public function deactivateSrUnit()
    {
        $module = new \oxModule();
        $module->load('srunit');
        if (true === $module->isActive()) {
            $module->deactivate();
        }
    }

    /**
     * Bootstraps composer-autoloader
     */
    private function loadComposerAutoloader()
    {
        $path = $this->shopBaseDir . 'vendor/autoload.php';

        if (file_exists($path)) {
            $this->composerClassLoader = require $path;
        }
    }

    /**
     * Boostraps Oxid
     */
    private function loadOxidFramework()
    {
        if ($this->isOxidMandatory()) {
            $path = $this->shopBaseDir . 'bootstrap.php';

            if (file_exists($path)) {
                require_once $path;
                $this->isOxidLoaded = true;

                if ($this->isOxidBypassed()) {
                    $this->activateSrUnit();
                }
            }
        }
    }

    /**
     * Bootstraps all files defined in metadata.php
     * of current module
     */
    private function registerModuleAutoloader()
    {
        $metadataFilePath = $this->moduleDir . '/metadata.php';

        if (false === file_exists($metadataFilePath)) {
            return;
        }

        require_once $metadataFilePath;

        if (false === isset($aModule['files'])) {
            return;
        }
        $customLoader = function ($className) use ($aModule) {
            $className = strtolower($className);

            if (isset($aModule['files'][$className])) {
                $path = substr(
                    $aModule['files'][$className],
                    strpos($aModule['files'][$className], '/') + 1
                );

                require_once $path;
            }
        };

        spl_autoload_register($customLoader);
    }

    /**
     * Returns test directory where Bootstrap class is called from.
     *
     * @return string
     */
    private function retrieveTestDirectory()
    {
        $trace = debug_backtrace();
        $firstTraceEntry = array_pop($trace);
        $testDirectory = dirname($firstTraceEntry['file']);

        return $testDirectory;
    }
} 