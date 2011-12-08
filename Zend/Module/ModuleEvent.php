<?php

namespace Zend\Module;

use Zend\EventManager\Event;

/**
 * Custom event for use with module manager
 *
 * Composes Module objects
 * 
 * @copyright Copyright (C) 2006-Present, Zend Technologies, Inc.
 * @license New BSD {@link http://framework.zend.com/license}
 */
class ModuleEvent extends Event
{
    /**
     * Get the name of a given module 
     * 
     * @return string
     */
    public function getModuleName()
    {
        return $this->getParam('moduleName');
    }

    /**
     * Set the name of a given module 
     * 
     * @param string $moduleName 
     * @return ModuleEvent
     */
    public function setModuleName($moduleName)
    {
        if (!is_string($moduleName)) {
            throw new Exception\InvalidArgumentException(__METHOD__ . ' expects a string as an argument; ' . gettype($moduleName) . ' provided');
        }
        $this->setParam('moduleName', $moduleName);
        return $this;
    }

    /**
     * Get module object
     * 
     * @return null|object
     */
    public function getModule()
    {
        return $this->getParam('module');
    }

    /**
     * Set module object to compose in this event
     * 
     * @param  object $module 
     * @return ModuleEvent
     */
    public function setModule($module)
    {
        if (!is_object($module)) {
            throw new Exception\InvalidArgumentException(__METHOD__ . ' expects a module object as an argument; ' . gettype($module) . ' provided');
        }
        $this->setParam('module', $module);
        return $this;
    }
}
