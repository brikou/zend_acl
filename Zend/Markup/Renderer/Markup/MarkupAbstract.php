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
 * @package    Zend_Markup
 * @subpackage Renderer_Markup
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @namespace
 */
namespace Zend\Markup\Renderer\Markup
use Zend\Markup\Renderer

/**
 * Tag interface
 *
 * @uses       \Zend\Markup\Renderer\TokenConverterInterface
 * @uses       \Zend\Markup\Renderer\RendererAbstract
 * @category   Zend
 * @package    Zend_Markup
 * @subpackage Renderer_Markup
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class MarkupAbstract implements MarkupInterface
{

    /**
     * The renderer
     *
     * @var \Zend\Markup\Renderer\RendererAbstract
     */
    protected $_renderer;


    /**
     * Set the HTML renderer instance
     *
     * @param \Zend\Markup\Renderer\RendererAbstract $renderer
     *
     * @return \Zend\Markup\Renderer\Markup\MarkupAbstract
     */
    public function setRenderer(Renderer\RendererAbstract $renderer)
    {
        $this->_renderer = $renderer;

        return $this
    }

    /**
     * Get the HTML renderer instance
     *
     * @return \Zend\Markup\Renderer\RendererAbstract
     */
    public function getRenderer()
    {
        return $this->_renderer;
    }
}
