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
 * @package    Zend_Router
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Mvc\Router\Http;

use Zend\Mvc\Router\Route,
    Zend\Mvc\Router\RouteMatch as BaseRouteMatch;

/**
 * Part route match.
 *
 * @package    Zend_Router
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class RouteMatch extends BaseRouteMatch
{
    /**
     * Length of the matched path.
     * 
     * @var integer
     */
    protected $length;
    
    /**
     * Create a part RouteMatch with given parameters and length.
     * 
     * @param  array      $params
     * @param  null|Route $route
     * @param  integer    $length
     * @return void
     */
    public function __construct(array $params, Route $route = null, $length = 0)
    {
        parent::__construct($params, $route);
        
        $this->length = $length;
    }
    
    /**
     * Merge parameters from another match.
     * 
     * @param  self $match
     * @return void
     */
    public function merge(self $match)
    {
        $this->params  = array_merge($this->params, $match->getParams());
        $this->length += $match->getLength();
    }

    /**
     * Get the matched path length.
     * 
     * @return integer
     */
    public function getLength()
    {
        return $this->length;
    }
}
