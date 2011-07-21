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
 * @category  Zend
 * @package   Zend_Uri
 * @copyright Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 * @version   $Id$
 */

/**
 * @namespace
 */
namespace Zend\Uri;

use Zend\Validator;

/**
 * Generic URI handler
 *
 * @uses      \Zend\Uri\Exception
 * @uses      \Zend\Validator\Hostname
 * @uses      \Zend\Validator\Ip
 * @category  Zend
 * @package   Zend_Uri
 * @copyright Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */
class Uri
{
    /**
     * Character classes defined in RFC-3986
     */
    const CHAR_UNRESERVED = '\w\-\.~';
    const CHAR_GEN_DELIMS = ':\/\?#\[\]@';
    const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    const CHAR_RESERVED   = ':\/\?#\[\]@!\$&\'\(\)\*\+,;=';
    
    /**
     * Host part types
     */
    const HOST_IPV4      = 1;
    const HOST_IPV6      = 2;
    const HOST_IPVF      = 4;
    const HOST_IPVANY    = 7;       
    const HOST_DNSNAME   = 8;
    const HOST_DNSORIPV4 = 9; 
    const HOST_REGNAME   = 16;
    const HOST_ALL       = 31;
     
    /**
     * URI scheme 
     * 
     * @var string
     */
    protected $_scheme;
    
    /**
     * URI userInfo part (usually user:password in HTTP URLs)
     * 
     * @var string
     */
    protected $_userInfo;
    
    /**
     * URI hostname
     *  
     * @var string
     */
    protected $_host;
    
    /**
     * URI port
     * 
     * @var integer
     */
    protected $_port;
    
    /**
     * URI path
     * 
     * @var string
     */
    protected $_path;
    
    /**
     * URI query string
     * 
     * @var string
     */
    protected $_query;
    
    /**
     * URI fragment
     * 
     * @var string
     */
    protected $_fragment;

    /**
     * Which host part types are valid for this URI?
     *
     * @var integer
     */
    protected $_validHostTypes = self::HOST_ALL;
    
    /**
     * Array of valid schemes.
     * 
     * Subclasses of this class that only accept specific schemes may set the
     * list of accepted schemes here. If not empty, when setScheme() is called
     * it will only accept the schemes listed here.  
     *
     * @var array
     */
    static protected $_validSchemes = array();
    
    /**
     * Create a new URI object
     * 
     * @param  \Zend\Uri\Uri|string|null $uri
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($uri = null) 
    {
        if (is_string($uri)) {
            $this->parse($uri);
            
        } elseif ($uri instanceof URI) {
            // Copy constructor
            $this->setScheme($uri->getScheme());
            $this->setUserInfo($uri->getUserInfo());
            $this->setHost($uri->getHost());
            $this->setPort($uri->getPort());
            $this->setPath($uri->getPath());
            $this->setQuery($uri->getQuery());
            $this->setFragment($uri->getFragment());
            
        } elseif ($uri !== null) {
            /**
             * @todo use a proper Exception class for Zend\Uri
             */
            throw new Exception\InvalidArgumentException('expecting a string or a URI object, got ' . gettype($uri));
        }
    }

    /**
     * Check if the URI is valid
     * 
     * Note that a relative URI may still be valid
     * 
     * @return boolean
     */
    public function isValid()
    {
        if ($this->_host) {
            if (strlen($this->_path) > 0 && substr($this->_path, 0, 1) != '/') return false; 
        } else {
            if ($this->_userInfo || $this->_port) return false;
            
            if ($this->_path) { 
                // Check path-only (no host) URI
                if (substr($this->_path, 0, 2) == '//') return false; 
                
            } elseif (! ($this->_query || $this->_fragment)) {
                // No host, path, query or fragment - this is not a valid URI 
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if the URI is an absolute or relative URI
     * 
     * @return boolean
     */
    public function isAbsolute()
    {
        return ($this->_scheme != null);
    }
    
    /**
     * Parse a URI string
     *
     * @param  string $uri
     * @return \Zend\Uri\Uri
     */
    public function parse($uri)
    {
        // Capture scheme
        if (($scheme = self::parseScheme($uri)) != null) {  
            $this->setScheme($scheme);
            $uri = substr($uri, strlen($scheme) + 1);
        }
        
        // Capture authority part
        if (preg_match('|^//([^/\?#]*)|', $uri, $match)) {
            $authority = $match[1];
            $uri = substr($uri, strlen($match[0]));
            
            // Split authority into userInfo and host
            if (strpos($authority, '@') !== false) {
                list($userInfo, $authority) = explode('@', $authority, 2);
                $this->setUserInfo($userInfo);
            }
            
            $colonPos = strrpos($authority, ':');
            if ($colonPos !== false) {
                $port = substr($authority, $colonPos + 1);
                if ($port) $this->setPort((int) $port);
                $authority = substr($authority, 0, $colonPos);
            }
            
            if ($authority) {
                $this->setHost($authority);
            }
        }
        if (! $uri) return $this;
        
        // Capture the path
        if (preg_match('|^[^\?#]*|', $uri, $match)) {
            $this->setPath($match[0]);
            $uri = substr($uri, strlen($match[0]));
        }
        if (! $uri) return $this;
        
        // Capture the query
        if (preg_match('|^\?([^#]*)|', $uri, $match)) {
            $this->setQuery($match[1]);
            $uri = substr($uri, strlen($match[0]));
        }
        if (! $uri) return $this;
        
        // All that's left is the fragment
        if ($uri && substr($uri, 0, 1) == '#') {
            $this->setFragment(substr($uri, 1));
        }
        
        return $this;
    }

    /**
     * Compose the URI into a string
     * 
     * @return string
     */
    public function toString()
    {
        if (! $this->isValid()) {
            throw new Exception\InvalidUriException("URI is not valid and cannot be converted into a string");
        }
        
        $uri = '';

        if ($this->_scheme) $uri = "$this->_scheme:"; 
        
        if ($this->_host !== null) {
            $uri .= '//';
            if ($this->_userInfo) $uri .= $this->_userInfo . '@';
            $uri .= $this->_host;
            if ($this->_port) $uri .= ":$this->_port";
        }

        if ($this->_path) {
            $uri .= $this->_path;
        } elseif ($this->_host && ($this->_query || $this->_fragment)) {
            $uri .= '/';
        }
        
        if ($this->_query) $uri .= "?" . self::encodeQueryFragment($this->_query);
        if ($this->_fragment) $uri .= "#" . self::encodeQueryFragment($this->_fragment);

        return $uri;
    }
    
    /**
     * Normalize the URI
     * 
     * Normalizing a URI includes removing any redundant parent directory or 
     * current directory references from the path (e.g. foo/bar/../baz becomes
     * foo/baz), normalizing the scheme case, decoding any over-encoded 
     * characters etc. 
     *  
     * Eventually, two normalized URLs pointing to the same resource should be 
     * equal even if they were originally represented by two different strings 
     * 
     * @return \Zend\Uri\Uri
     */
    public function normalize()
    {
        return $this;
    }
    
    /**
     * Merge a relative URI into the current (usually absolute) URI, using the
     * current URI as a base reference point. 
     *  
     * Merging algorithm is adapted from RFC-3986 section 5.2 
     * (@link http://tools.ietf.org/html/rfc3986#section-5.2)
     * 
     * @todo Implement by moving the logic from ::resolve to a common static
     *       method shared by both methods
     *       
     * @param  \Zend\Uri\Uri | string $baseUri
     * @return \Zend\Uri\Uri
     */
    public function merge($relativeUri)
    {
        throw new \ErrorException("Implement me!");
    }
    
    /**
     * Convert a relative URI into an absolute URI using a base absolute URI as 
     * a reference.
     * 
     * This is similar to ::merge() - only it uses the supplied URI as the 
     * base reference instead of using the current URI as the base reference.
     * 
     * Merging algorithm is adapted from RFC-3986 section 5.2 
     * (@link http://tools.ietf.org/html/rfc3986#section-5.2)
     * 
     * @param  \Zend\Uri\Uri | string $baseUri
     * @return \Zend\Uri\Uri
     */
    public function resolve($baseUri)
    {
        // Ignore if URI is absolute
        if ($this->isAbsolute()) return $this;
            
        if (is_string($baseUri)) {
            $baseUri = new static($baseUri);
        }
        
        /* @var $baseUrl \Zend\Uri\Uri */
        if (! $baseUri instanceof static) {
            throw new Exception\InvalidUriTypeException("Provided base URL is not an instance of " . get_class($this));
        }
        
        // Merging starts here...
        if ($this->getHost()) {
            $this->setPath(static::removePathDotSegments($this->getPath()));
            
        } else { 
            $basePath = $baseUri->getPath();
            $relPath  = $this->getPath();
            if (! $relPath) {
                $this->setPath($basePath);
                if (! $this->getQuery()) {
                    $this->setQuery($baseUri->getQuery());
                }
                
            } else {
                if (substr($relPath, 0, 1) == '/') {
                    $this->setPath(static::removePathDotSegments($relPath));
                } else {
                    if ($baseUri->getHost() && ! $basePath) {
                        $mergedPath = '/';
                    } else {
                        $mergedPath = substr($basePath, 0, strrpos($basePath, '/') + 1);
                    }
                    $this->setPath(static::removePathDotSegments($mergedPath . $relPath));
                }
            }
            
            // Set the authority part
            $this->setUserInfo($baseUri->getUserInfo());
            $this->setHost($baseUri->getHost());
            $this->setPort($baseUri->getPort());
        }
        
        $this->setScheme($baseUri->getScheme());
        
        return $this;
    } 
    

    /**
     * Convert the link to a relative link by substracting a base URI
     * 
     *  This is the opposite of resolving a relative link - i.e. creating a 
     *  relative reference link from an original URI and a base URI. 
     *  
     *  If the two URIs do not intersect (e.g. the original URI is not in any
     *  way related to the base URI) the URI will not be modified. 
     * 
     * @param  \Zend\Uri\Uri | string $baseUri 
     * @return \Zend\Uri\Uri
     */
    public function makeRelative($baseUri)
    {
        return $this;
    }
    
    /**
     * Get the scheme part of the URI 
     * 
     * @return string | null
     */
    public function getScheme()
    {
        return $this->_scheme;
    }

    /**
     * Get the User-info (usually user:password) part
     * 
     * @return string | null
     */
    public function getUserInfo()
    {
        return $this->_userInfo;
    }

    /**
     * Get the URI host
     * 
     * @return string | null
     */
    public function getHost()
    {
        return $this->_host;
    }

    /**
     * Get the URI port
     * 
     * @return integer | null
     */
    public function getPort()
    {
        return $this->_port;
    }

    /**
     * Get the URI path
     * 
     * @return string | null
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Get the URI query
     * 
     * @return string | null
     */
    public function getQuery()
    {
        return $this->_query;
    }
    
    /**
     * Return the query string as an associative array of key => value pairs
     *
     * This is an extension to RFC-3986 but is quite useful when working with
     * most common URI types
     * 
     * @return array
     */
    public function getQueryAsArray()
    {
        $query = array();
        if ($this->_query) {
            parse_str($this->_query, $query);
        }
        
        return $query;
    }

    /**
     * Get the URI fragment
     *  
     * @return string | null
     */
    public function getFragment()
    {
        return $this->_fragment;
    }

	/**
	 * Set the URI scheme
	 * 
	 * If the scheme is not valid according to the generic scheme syntax or 
	 * is not acceptable by the specific URI class (e.g. 'http' or 'https' are 
	 * the only acceptable schemes for the Zend\Uri\HTTTP class) an exception
	 * will be thrown. 
	 * 
	 * You can check if a scheme is valid before setting it using the 
	 * validateScheme() method. 
	 * 
     * @param  string $scheme
     * @throws \Zend\Uri\Exception\InvalidUriPartException
     * @return \Zend\Uri\Uri
     */
    public function setScheme($scheme)
    {
        if ($scheme !== null && (! self::validateScheme($scheme))) {
            throw new Exception\InvalidUriPartException(
            	"Scheme '$scheme' is not valid or is not accepted by " . get_class($this), 
                Exception\InvalidUriPartException::INVALID_SCHEME
            );
        }
        
        $this->_scheme = $scheme;
        return $this;
    }

    /**
     * Set the URI User-info part (usually user:password)
     * 
     * @param  string $userInfo
     * @return \Zend\Uri\Uri
     */
    public function setUserInfo($userInfo)
    {
        $this->_userInfo = $userInfo;
        return $this;
    }

    /**
     * Set the URI host
     * 
     * Note that the generic syntax for URIs allows using host names which
     * are not neceserily IPv4 addresses or valid DNS host names. For example, 
     * IPv6 addresses are allowed as well, and also an abstract "registered name"
     * which may be any name composed of a valid set of characters, including, 
     * for example, tilda (~) and underscore (_) which are not allowed in DNS
     * names. 
     * 
     * Subclasses of \Zend\Uri\Uri may impose more strict validation of host
     * names - for example the HTTP RFC clearly states that only IPv4 and 
     * valid DNS names are allowed in HTTP URIs.  
     *  
     * @param  string $host
     * @return \Zend\Uri\Uri
     */
    public function setHost($host)
    {
        $this->_host = $host;
        return $this;
    }

    /**
     * Set the port part of the URI
     * 
     * @param  integer $port
     * @return \Zend\Uri\Uri
     */
    public function setPort($port)
    {
        $this->_port = $port;
        return $this;
    }

    /**
     * Set the path
     * 
     * @param  string $path
     * @return \Zend\Uri\Uri
     */
    public function setPath($path)
    {
        $this->_path = $path;
        return $this;
    }

    /**
     * Set the query string
     * 
     * If an array is provided, will encode this array of parameters into a 
     * query string. Array values will be represented in the query string using
     * PHP's common square bracket notation. 
     * 
     * @param  string | array $query
     * @return \Zend\Uri\Uri
     */
    public function setQuery($query)
    {
        if (is_array($query)) {
            // We replace the + used for spaces by http_build_query with the 
            // more standard %20. 
            $query = str_replace('+', '%20', http_build_query($query));
        }
        
        $this->_query = $query;
        return $this;
    }

    /**
     * Set the URI fragment part
     * 
     * @param  string $fragment
     * @return \Zend\Uri\Uri
     */
    public function setFragment($fragment)
    {
        $this->_fragment = $fragment;
        return $this;
    }

    /**
     * Magic method to convert the URI to a string
     * 
     * @return string
     */
	public function __toString()
    {
        try {
            return $this->toString();
        } catch (\Exception $ex) { 
            return '';
        }
    }
    
    /**
     * Encoding and Validation Methods
     */

    /**
     * Check if a scheme is valid or not
     * 
     * Will check $scheme to be valid against the generic scheme syntax defined
     * in RFC-3986. If the class also defines specific acceptable schemes, will
     * also check that $scheme is one of them.
     * 
     * @param  string $scheme
     * @return boolean
     */
    static public function validateScheme($scheme)
    {
        if (! empty(static::$_validSchemes) &&
            ! in_array(strtolower($scheme), static::$_validSchemes)) {
            
            return false;
        }
        
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9\-\.+]*$/', $scheme);
    }
    
    /**
     * Check that the userInfo part of a URI is valid
     * 
     * @param  string $userInfo
     * @return boolean
     */
    static public function validateUserInfo($userInfo)
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':]+|%[A-Fa-f0-9]{2})*$/';
        return (boolean) preg_match($regex, $userInfo);
    }
    
    /**
     * Validate the host part
     * 
     * Users may control which host types to allow by passing a second parameter
     * with a bitmask of HOST_* constants which are allowed. If not specified,
     * all address types will be allowed. 
     * 
     * Note that the generic URI syntax allows different host representations, 
     * including IPv4 addresses, IPv6 addresses and future IP address formats 
     * enclosed in square brackets, and registered names which may be DNS names 
     * or even more complex names. This is different (and is much more loose) 
     * from what is commonly accepted as valid HTTP URLs for example.
     * 
     * @param  string  $host
     * @param  integer $allowed bitmask of allowed host types 
     * @return boolean
     */
    static public function validateHost($host, $allowed = self::HOST_ALL)
    {
        if ($allowed & self::HOST_REGNAME) { 
            if (static::_isValidRegName($host)) return true;
        }
        
        if ($allowed & self::HOST_DNSNAME) { 
            if (static::_isValidDnsHostname($host)) return true;
        }
        
        if ($allowed & self::HOST_IPVANY) {
            if (static::_isValidIpAddress($host, $allowed)) return true; 
        }
        
        return false;
    }
    
    /**
     * Validate the port 
     * 
     * Valid values include numbers between 1 and 65535, and empty values
     * 
     * @param  integer $port
     * @return boolean
     */
    static public function validatePort($port)
    {
        if ($port === 0) {
            return false; 
        }
        
        if ($port) {
            $port = (int) $port;
            if ($port < 1 || $port > 0xffff) return false;
        }
        
        return true;
    }
    
    /**
     * Validate the path 
     * 
     * @param  string $path
     * @return boolean
     */
    static public function validatePath($path)
    {
        throw new \Exception("Implelemt me!");
    }

    /**
     * Check if a URI query or fragment part is valid or not
     * 
     * Query and Fragment parts are both restricted by the same syntax rules, 
     * so the same validation method can be used for both. 
     * 
     * You can encode a query or fragment part to ensure it is valid by passing
     * it through the encodeQueryFragment() method.
     *  
     * @param  string $input
     * @return boolean 
     */
    static public function validateQueryFragment($input)
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':@\/\?]+|%[A-Fa-f0-9]{2})*$/';
        return (boolean) preg_match($regex, $input);
    }
    
    /**
     * URL-encode the user info part of a URI 
     * 
     * @param  string $userInfo
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    static public function encodeUserInfo($userInfo)
    {
        if (! is_string($userInfo)) {
            throw new Exception\InvalidArgumentException("Expecting a string, got " . gettype($userInfo));
        }
        
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:]|%(?![A-Fa-f0-9]{2}))/'; 
        $replace = function($match) {
            return rawurlencode($match[0]);
        };
        
        return preg_replace_callback($regex, $replace, $userInfo);
    }
    
    /**
     * Encode the path
     *  
     * @param  string $path
     * @return string
     */
    static public function encodePath($path)
    {
        throw new \Exception("Implelemt me!");
    }
    
    /**
     * URL-encode a query string or fragment based on RFC-3986 guidelines. 
     * 
     * Note that query and fragment encoding allows more unencoded characters 
     * than the usual rawurlencode() function would usually return - for example 
     * '/' and ':' are allowed as literals.
     *  
     * @param  string $input
     * @return string
     */
    static public function encodeQueryFragment($input)
    {
        if (! is_string($input)) {
            throw new Exception\InvalidArgumentException("Expecting a string, got " . gettype($input));
        }   
        
        $regex = '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/'; 
        $replace = function($match) {
            return rawurlencode($match[0]);
        };
        
        return preg_replace_callback($regex, $replace, $input);
    }
    
    /**
     * Extract only the scheme part out of a URI string. 
     * 
     * This is used by the parse() method, but is useful as a standalone public
     * method if one wants to test a URI string for it's scheme before doing
     * anything with it.
     * 
     * Will return the scmeme if found, or NULL if no scheme found (URI may 
     * still be valid, but not full)
     * 
     * @param  string $uriString
     * @return string | null
     * @throws InvalidArgumentException
     */
    static public function parseScheme($uriString)
    {
        if (! is_string($uriString)) {
            throw new Exception\InvalidArgumentException("Expecting a string, got " . gettype($uriString));
        }
        
        if (preg_match('/^([A-Za-z][A-Za-z0-9\.\+\-]*):/', $uriString, $match)) {
            return $match[1];
        } else {
            return null;
        }
    }
    
    /**
     * Remove any extra dot segments (/../, /./) from a path
     *
     * Algorithm is adapted from RFC-3986 section 5.2.4
     * (@link http://tools.ietf.org/html/rfc3986#section-5.2.4)
     * 
     * @todo   consider optimizing
     * 
     * @param  string $path
     * @return string
     */
    static public function removePathDotSegments($path)
    {
        $output = '';
        
        while ($path) {
            if ($path == '..' || $path == '.') break;
            
            if ($path == '/.') {
                $path = '/';
                
            } elseif ($path == '/..') {
                $path = '/';
                $output = substr($output, 0, strrpos($output, '/', -1));
                
            } elseif (substr($path, 0, 4) == '/../') {
                $path = '/' . substr($path, 4);
                $output = substr($output, 0, strrpos($output, '/', -1));
                
            } elseif (substr($path, 0, 3) == '/./') {
                $path = substr($path, 2);
                
            } elseif (substr($path, 0, 2) == './') { 
                $path = substr($path, 2);
                
            } elseif (substr($path, 0, 3) == '../') {
                $path = substr($path, 3);
                  
            } else {
                $slash = strpos($path, '/', 1);
                if ($slash === false) { 
                    $seg = $path;
                } else {
                    $seg = substr($path, 0, $slash); 
                }
                
                $output .= $seg;
                $path = substr($path, strlen($seg));
            }
        }
        
        return $output; 
    }
    
    /**
     * Check if a host name is a valid IP address, depending on allowed IP address types
     * 
     * @param  string  $host
     * @param  integer $allowed allowed address types
     * @return boolean
     */
    static protected function _isValidIpAddress($host, $allowed)
    {
        $validatorParams = array(
            'allowipv4' => (bool) ($allowed & self::HOST_IPV4),
            'allowipv6' => (bool) ($allowed & self::HOST_IPV6)
        );
        
        if ($allowed & (self::HOST_IPV6 | self::HOST_IPVF)) {
            if (preg_match('/^\[(.+)\]$/', $host, $match)) { 
                $host = $match[1];
                $validatorParams['allowipv4'] = false;
            }
        }
        
        if ($allowed & (self::HOST_IPV4 | self::HOST_IPV6)) {
            $validator = new Validator\Ip($validatorParams);
            if ($validator->isValid($host)) return true;
        }
        
        if ($allowed & self::HOST_IPVF) { 
            $regex = '/^v\.[[:xdigit:]]+[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':]+$/';
            return (bool) preg_match($regex, $host);
        }
        
        return false;
    }
    
    /**
     * Check if an address is a valid DNS hostname
     *
     * @param  string $host
     * @return boolean
     */
    static protected function _isValidDnsHostname($host)
    {
        $validator = new Validator\Hostname(array(
            'allow' => Validator\Hostname::ALLOW_DNS | Validator\Hostname::ALLOW_LOCAL
        ));
        
        return $validator->isValid($host);
    }
    
    /**
     * Check if an address is a valid registerd name (as defined by RFC-3986) address
     *
     * @param  string $host
     * @return boolean
     */
    static protected function _isValidRegName($host)
    {
        $regex = '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':@\/\?]+|%[A-Fa-f0-9]{2})+$/';
        return (bool) preg_match($regex, $host);
    }
}
