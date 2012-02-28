<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Config
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Config\Writer;

use Zend\Config\Writer,
    Zend\Config\Exception,
    Zend\Stdlib\IteratorToArray;

/**
 * @category   Zend
 * @package    Zend_Config
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class AbstractWriter implements Writer
{
    /**
     * writeFile(): defined by Writer interface.
     *
     * @see    Writer::writeFile()
     * @param  string  $filename
     * @param  mixed   $config
     * @param  boolean $exclusiveLock
     * @return void
     */
    public function writeFile($filename, $config, $exclusiveLock = true)
    {
        if (!is_writable($filename)) {
            throw new Exception\RuntimeException(sprintf('File "%s" is not writable', $filename));
        }

        $flags = 0;

        if ($exclusiveLock) {
            $flags |= LOCK_EX;
        }

        file_put_contents($filename, $this->writeString($config), $exclusiveLock);
    }

    /**
     * writeString(): defined by Writer interface.
     *
     * @see    Writer::writeFile()
     * @param  mixed   $config
     * @return void
     */
    public function writeString($config)
    {
        if ($config instanceof Traversable) {
            $config = IteratorToArray::convert($config);
        } elseif (!is_array($config)) {
            throw new Exception\InvalidArgumentException(__METHOD__ . ' expects an array or Traversable config');
        }

        return $this->processConfig($config);
    }

    /**
     * Process an array configuration.
     *
     * @return string
     */
    abstract protected function processConfig(array $config);
}
