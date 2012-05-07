<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Package
 */

namespace Zend\Crypt\Rsa;

use Zend\Crypt\Rsa\Exception\RuntimeException,
    Countable;

/**
 * @category   Zend
 * @package    Zend_Crypt
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Key implements Countable
{
    /**
     * @var string
     */
    protected $_pemString = null;

    /**
     * Bits, key string and type of key
     *
     * @var array
     */
    protected $_details = array();

    /**
     * Key Resource
     *
     * @var resource
     */
    protected $_opensslKeyResource = null;

    /**
     * Retrieves key resource
     *
     * @return resource
     */
    public function getOpensslKeyResource()
    {
        return $this->_opensslKeyResource;
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    public function toString()
    {
        if (!empty($this->_pemString)) {
            return $this->_pemString;
        } elseif (!empty($this->_certificateString)) {
            return $this->_certificateString;
        }
        throw new RuntimeException('No public key string representation is available');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    public function count()
    {
        return $this->_details['bits'];
    }

    public function getType()
    {
        return $this->_details['type'];
    }
}
