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
 * @package    Zend_Service
 * @subpackage Twitter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Twitter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Service\Twitter;

use Zend\Stdlib\Options,
	Zend\Service\Twitter\Exception\UnexpectedValueException;

class SearchOptions extends Options
{
	/**
	 * Search query. Should be URL encoded. Queries will be limited by complexity.
	 * @var q string
	 */
	protected $q;
	
	/**
	 * Returns tweets by users located within a given radius of the given 
	 * latitude/longitude. The location is preferentially taking from the Geotagging 
	 * API, but will fall back to their Twitter profile. The parameter value 
	 * is specified by "latitude,longitude,radius", where radius units must 
	 * be specified as either "mi" (miles) or "km" (kilometers). 
	 * Note that you cannot use the near operator via the API to geocode 
	 * arbitrary locations; however you can use this geocode parameter to 
	 * search near geocodes directly.
	 * 
	 * @var geocode string
	 */
	protected $geocode;
	
	/**
	 * Restricts tweets to the given language, given by an ISO 639-1 code.
	 * 
	 * @var lang string
	 */
	protected $lang;
	
	/**
	 * Specify the language of the query you are sending (only ja is currently effective).
	 * This is intended for language-specific clients and the default should work in
	 * the majority of cases..
	 * 
	 * @var locale string
	 */
	protected $locale;
	
	/**
	 * The page number (starting at 1) to return, up to a max of
	 * roughly 1500 results (based on rpp * page).
	 * 
	 * @var page string
	 */
	protected $page;
	
	/**
	 * Specifies what type of search results you would prefer to receive.
	 * The current default is "mixed." (mixed|recent|popular)
	 * 
	 * @var result_type string
	 */
	protected $result_type = 'mixed';
	
	/**
	 * The number of tweets to return per page, up to a max of 100.
	 * 
	 * @var rpp string
	 */
	protected $rpp;
	
	/**
	 * When true, prepends ":" to the beginning of the tweet. This is useful for 
	 * readers that do not display Atom's author field. The default is false.
	 * 
	 * @var show_user string
	 */
	protected $show_user = false;
	
	/**
	 * Returns results with an ID greater than (that is, more recent than) the specified ID.
	 * There are limits to the number of Tweets which can be accessed through the API. 
	 * If the limit of Tweets has occured since the since_id, the since_id will be forced 
	 * to the oldest ID available.
	 * 
	 * @var since_id string
	 */
	protected $since_id;
	
	/**
	 * Returns results with an ID less than (that is, older than) or equal to the specified ID.
	 * 
	 * @var max_id string
	 */
	protected $max_id;
	
	/**
	 * When set to either true, t or 1, each tweet will include a node called "entities,".
	 * 
	 * @var include_entities string
	 */
	protected $include_entities;
	
	/**
     * Cast to array
     *
     * @return array
     */
	public function toArray()
	{
	    $array = array();
        $transform = function($letters) {
   	        $letter = array_shift($letters);
            return '_' . strtolower($letter);
        };
        foreach ($this as $key => $value) {
            if(!is_null($value))
            {
                $normalizedKey = preg_replace_callback('/([A-Z])/', $transform, $key);
                $array[$normalizedKey] = $value;
            }
        }
        return $array;
	}
	
	/**
     * Get query
     *
     * @return string
     */
	public function getQ()
	{
	    return $this->q;
	}
	
	/**
     * Alias to get query
     *
     * @return string
     */
	public function getQuery()
	{
	    return $this->getQ();
	}
	
	/**
     * Set query
     *
     * @param  string $q
     * @return SearchOptions
     */
	public function setQuery($q)
	{
	    $this->q = $q;
	    return $this;
	}

	/**
     * Alias to set query
     *
     * @param  string $q
     * @return SearchOptions
     */
	public function setQ($q)
	{
	    return $this->setQuery($q);
	}
	
	/**
     * Get geocode
     *
     * @return string
     */
	public function getGeocode()
	{
	    return $this->geocode;
	}
	
	/**
     * Set the geocode parameter
     *
     * @param  string $geocode
     * @return SearchOptions
     */
	public function setGeocode($geocode)
	{
	    $this->geocode = $geocode;
	    return $this;
	}

	/**
     * Get lang
     *
     * @return string
     */
	public function getLang()
	{
	    return $this->lang;
	}
	
	/**
     * Set the lang parameter
     *
     * @param  string $lang
     * @return SearchOptions
     */
	public function setLang($lang)
	{
	    $this->lang = $lang;
	    return $this;
	}

	/**
     * Get locale
     *
     * @return string
     */
	public function getLocale()
	{
	    return $this->locale;
	}
	
	/**
     * Set the locale parameter
     *
     * @param  string $locale
     * @return SearchOptions
     */
	public function setLocale($locale)
	{
	    $this->locale = $locale;
	    return $this;
	}

	/**
     * Get page
     *
     * @return int
     */
	public function getPage()
	{
	    return $this->page;
	}
	
	/**
     * Set the current page
     *
     * @param  int $page
     * @return SearchOptions
     */
	public function setPage($page)
	{
	    $this->page = intval($page);
	    return $this;
	}
	
	/**
     * Get result_type
     *
     * @return string
     */
	public function getResultType()
	{
	    return $this->result_type;
	}
	
	/**
     * Set the result_type parameter
     *
     * @param  string $resultType
     * @return SearchOptions
     */
	public function setResultType($resultType)
	{
	    $resultType = strtolower($resultType);
	    if(!in_array($resultType,array('mixed','popular','recent')))
	    {
	        throw new UnexpectedValueException(
	            'Bad value "'.$resultType.'" on result_type parameter'
	        );
	    }
		
	    $this->result_type = $resultType;
	    return $this;
	}

	/**
     * Get rpp parameter
     *
     * @return int
     */
	public function getRpp()
	{
	    return $this->rpp;
	}
	
	/**
     * Set rpp parameter
     *
     * @param  int $rpp
     * @return SearchOptions
     */
	public function setRpp($rpp)
	{
	    $rpp = (intval($rpp) > 100) ? 100 : intval($rpp);
	    $this->rpp = $rpp;
	    return $this;
	}

	/**
     * Get show_user parameter
     *
     * @return bool
     */
	public function getShowUser()
	{
	    return $this->show_user;
	}
	
	/**
     * Set the show_user parameter
     *
     * @param  int $showUser
     * @return SearchOptions
     */
	public function setShowUser($showUser)
	{
	    $this->show_user = (bool)$showUser;
	    return $this;
	}

	/**
     * Get since_id parameter
     *
     * @return int
     */
	public function getSinceId()
	{
	    return $this->since_id;
	}
	
	/**
     * Set the since_id parameter
     *
     * @param  int $sinceId
     * @return SearchOptions
     */
	public function setSinceId($sinceId)
	{
	    $this->since_id = $sinceId;
	    return $this;
	}
	

	/**
     * Get max_id parameter
     *
     * @return int
     */
	public function getMaxId()
	{
	    return $this->max_id;
	}
	
	/**
     * Set the max_id parameter
     *
     * @param  int $maxId
     * @return SearchOptions
     */
	public function setMaxId($maxId)
	{
	    $this->max_id = (int)$maxId;
	    return $this;
	}

	/**
     * Get include_entities parameter
     *
     * @return bool
     */
	public function getIncludeEntities()
	{
	    return $this->include_entities;
	}
	
	/**
     * Set the include_entities parameter
     *
     * @param  int $includeEntities
     * @return SearchOptions
     */
	public function setIncludeEntities($includeEntities)
	{
	    $this->include_entities = (bool)$includeEntities;
	    return $this;
	}
}
