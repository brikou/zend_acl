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
 * @package    Zend\Http
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Http;

use Zend\Config\Config,
    Zend\Uri\Http;

/**
 * Http client
 *
 * @category   Zend
 * @package    Zend\Http
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Client
{
    /**
     * HTTP protocol versions
     */
    const HTTP_1 = '1.1';
    const HTTP_0 = '1.0';
    
    /**
     * Content attributes
     */
    const CONTENT_TYPE   = 'Content-Type';
    const CONTENT_LENGTH = 'Content-Length';

    /**
     * POST data encoding methods
     */
    const ENC_URLENCODED = 'application/x-www-form-urlencoded';
    const ENC_FORMDATA   = 'multipart/form-data';
    
    protected static $client;
    protected $response;
    protected $request;
    protected $adapter;
    protected $auth;
    
    /**
     * Configuration array, set using the constructor or using ::setConfig()
     *
     * @var array
     */
    protected $config = array(
        'maxredirects' => 5,
        'strictredirects' => false,
        'useragent' => 'Zend\\Http\\Client',
        'timeout' => 10,
        'adapter' => 'Zend\\Http\\Client\\Adapter\\Socket',
        'httpversion' => self::HTTP_1,
        'keepalive' => false,
        'strict' => true,
        'output_stream' => false,
        'encodecookies' => true,
    );
   
    /**
     * Constructor
     *
     * @param string $uri
     * @param array  $config
     */
    public function __construct($uri = null, $config = null)
    {
        if ($uri !== null) {
            $this->setUri($uri);
        }
        if ($config !== null) {
            $this->setConfig($config);
        }
    }
    /**
     * Get the static HTTP client
     *
     * @return Zend\Http\Client
     */
    protected static function getStaticClient()
    {
        if (!isset(self::$client)) {
            self::$client = new Client();
        }
        return self::$client;
    }
    /**
     * Set configuration parameters for this HTTP client
     *
     * @param  \Zend\Config\Config | array $config
     * @return \Zend\Http\Client
     * @throws \Zend\Http\Client\Exception
     */
    public function setConfig($config = array())
    {
        if ($config instanceof Config) {
            $config = $config->toArray();

        } elseif (! is_array($config)) {
            throw new Client\Exception\InvalidArgumentException('Array or Zend\\Config object expected, got ' . gettype($config));
        }

        foreach ($config as $k => $v) {
            $this->config[strtolower($k)] = $v;
        }

        // Pass configuration options to the adapter if it exists
        if ($this->adapter instanceof Client\Adapter) {
            $this->adapter->setConfig($config);
        }

        return $this;
    }
    /**
     * Load the connection adapter
     *
     * While this method is not called more than one for a client, it is
     * seperated from ->request() to preserve logic and readability
     *
     * @param  \Zend\Http\Client\Adapter|string $adapter
     * @return null
     * @throws \Zend\Http\Client\Exception
     */
    public function setAdapter($adapter)
    {
        if (is_string($adapter)) {
            if (!class_exists($adapter)) {
                throw new Client\Exception\InvalidArgumentException('Unable to locate adapter class "' . $adapter . '"');
            }
            $adapter = new $adapter;
        }

        if (! $adapter instanceof Client\Adapter) {
            throw new Client\Exception\InvalidArgumentException('Passed adapter is not a HTTP connection adapter');
        }

        $this->adapter = $adapter;
        $config = $this->config;
        unset($config['adapter']);
        $this->adapter->setConfig($config);
    }
    /**
     * Load the connection adapter
     *
     * @return \Zend\Http\Client\Adapter $adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }
    /**
     * Get Request
     * 
     * @return Zend\Http\Request
     */
    public function getRequest()
    {
        if (empty($this->request)) {
            $this->request= new Request();
        }
        return $this->request;
    }
    /**
     * Get Response
     * 
     * @return Zend\Http\Response
     */
    public function getResponse()
    {
        if (empty($this->response)) {
            $this->response= new Response();
        }
        return $this->response;
    }
    /**
     * Set request
     * 
     * @param Zend\Http\Request $request 
     */
    public function setRequest(Request $request)
    {
        $this->request= $request;
    }
    /**
     * Set response
     * 
     * @param Zend\Http\Response $response 
     */
    public function setResponse(Response $response)
    {
        $this->response= $response;
    }
    /**
     * Set Uri (to the request)
     * 
     * @param string|Zend\Uri\Http $uri 
     */
    public function setUri($uri)
    {
        if (!empty($uri)) {
            $this->getRequest()->setUri($uri);
        }
    }
    /**
     * Get uri (from the request)
     * 
     * @return string 
     */
    public function getUri()
    {
        return $this->getRequest()->getUri();
    }
    /**
     * Set a cookie (to the request)
     * 
     * @param  \Zend\Stdlib\ParametersDescription $cookie
     * @return Client 
     */
    public function setCookie($cookie) 
    {
        if (!empty($cookie)) {
            $this->getRequest()->setCookie($cookie);
        }
        return $this;
    }
    /**
     * Get the cookie (from the request)
     * 
     * @return type 
     */
    public function getCookie()
    {
        return $this->getRequest()->cookie();
    }
    /**
     * Set the headers (for the request)
     * 
     * @param  \Zend\Http\RequestHeaders $headers
     * @return Client 
     */
    public function setHeaders($headers)
    {
        if (!empty($headers)) {
            $this->getRequest()->headers($headers);
        }
        return $this;
    }
    /**
     * Add an header to the request
     * 
     * @param  string|array $type
     * @param  string $value 
     * @return boolean
     */
    public function addHeader($type,$value=null)
    {
        if (!empty($type)) {
            if (is_array($type)) {
                foreach ($type as $key => $value) {
                    $this->getRequest()->headers()->addHeader($key, $value);
                }
            } else {
                $this->getRequest()->headers()->addHeader($type, $value);
            }
            return true;
        }
        return false;
    }
    /**
     * Check if exists the header type specified
     * 
     * @param  string $type
     * @return boolean 
     */
    public function hasHeader($type)
    {
        return $this->getRequest()->headers()->has($type);
    }
    /**
     * Send HTTP request
     *
     * @param  Request $request
     * @return Response
     */
    public function send(Request $request=null)
    {
        if ($request !== null) {
            $this->setRequest($request);
        }
        
        $this->redirectCounter = 0;
        $response = null;

        // Make sure the adapter is loaded
        if ($this->adapter == null) {
            $this->setAdapter($this->config['adapter']);
        }

        // Send the first request. If redirected, continue.
        do {
            // uri
            $uri= $this->getUri();
            // query
            $query= $this->getRequest()->query()->toArray();
            if (!empty($query)) {
                $uri.= '?'.http_build_query($query);
            }
            // method
            $method= $this->getRequest()->getMethod();
            // body
            $body = $this->prepareBody();
            // headers
            $headers = $this->prepareHeaders($body);
            
            // check that adapter supports streaming before using it
            if(is_resource($body) && !($this->adapter instanceof Client\Adapter\Stream)) {
                throw new Client\Exception\RuntimeException('Adapter does not support streaming');
            }

            
            // Open the connection, send the request and read the response
            $this->adapter->connect($this->getRequest()->uri()->getHost(), $this->getRequest()->uri()->getPort(),
                ($this->getRequest()->uri()->getScheme() == 'https' ? true : false));

            if($this->config['output_stream']) {
                if($this->adapter instanceof Client\Adapter\Stream) {
                    $stream = $this->_openTempStream();
                    $this->adapter->setOutputStream($stream);
                } else {
                    throw new Exception\RuntimeException('Adapter does not support streaming');
                }
            }

            // HTTP connection
            $this->lastRequest = $this->adapter->write($method,
                $this->getRequest()->uri(), $this->config['httpversion'], $headers, $body);
            
            $this->lastResponse = $this->adapter->read();
            if (! $this->lastResponse) {
                throw new Exception\RuntimeException('Unable to read response, or response is empty');
            }

            if($this->config['output_stream']) {
                rewind($stream);
                // cleanup the adapter
                $this->adapter->setOutputStream(null);
                $response = Response\Stream::fromStream($this->lastResponse, $stream);
                $response->setStreamName($this->_stream_name);
                if(!is_string($this->config['output_stream'])) {
                    // we used temp name, will need to clean up
                    $response->setCleanup(true);
                }
            } else {
                $response = Response::fromString($this->lastResponse);
            }

            // Load cookies into cookie jar
            if (isset($this->cookiejar)) {
                $this->cookiejar->addCookiesFromResponse($response, $uri);
            }

            // If we got redirected, look for the Location header
            if ($response->isRedirect() && ($location = $response->getHeader('location'))) {

                // Check whether we send the exact same request again, or drop the parameters
                // and send a GET request
                if ($response->getStatus() == 303 ||
                   ((! $this->config['strictredirects']) && ($response->getStatus() == 302 ||
                       $response->getStatus() == 301))) {

                    $this->resetParameters();
                    $this->setMethod(Request::METHOD_GET);
                }

                // If we got a well formed absolute URI
                $url = new Uri\Url($location);
                
                if ($url->isValid()) {
                    $this->setHeaders('host', null);
                    $this->setUri($location);

                } else {

                    // Split into path and query and set the query
                    if (strpos($location, '?') !== false) {
                        list($location, $query) = explode('?', $location, 2);
                    } else {
                        $query = '';
                    }
                    $this->uri->setQuery($query);

                    // Else, if we got just an absolute path, set it
                    if(strpos($location, '/') === 0) {
                        $this->uri->setPath($location);

                        // Else, assume we have a relative path
                    } else {
                        // Get the current path directory, removing any trailing slashes
                        $path = $this->uri->getPath();
                        $path = rtrim(substr($path, 0, strrpos($path, '/')), "/");
                        $this->uri->setPath($path . '/' . $location);
                    }
                }
                ++$this->redirectCounter;

            } else {
                // If we didn't get any location, stop redirecting
                break;
            }

        } while ($this->redirectCounter < $this->config['maxredirects']);

        $this->response= $response;
        return $response;
    }

    /**
     * Set a file to upload (using a POST request)
     *
     * Can be used in two ways:
     *
     * 1. $data is null (default): $filename is treated as the name if a local file which
     * will be read and sent. Will try to guess the content type using mime_content_type().
     * 2. $data is set - $filename is sent as the file name, but $data is sent as the file
     * contents and no file is read from the file system. In this case, you need to
     * manually set the Content-Type ($ctype) or it will default to
     * application/octet-stream.
     *
     * @param  string $filename Name of file to upload, or name to save as
     * @param  string $formname Name of form element to send as
     * @param  string $data Data to send (if null, $filename is read and sent)
     * @param  string $ctype Content type to use (if $data is set and $ctype is
     *                null, will be application/octet-stream)
     * @return \Zend\Http\Client
     * @throws \Zend\Http\Client\Exception
     */
    public function setFileUpload($filename, $formname, $data = null, $ctype = null)
    {
        if ($data === null) {
            if (($data = @file_get_contents($filename)) === false) {
                throw new Exception\RuntimeException("Unable to read file '{$filename}' for upload");
            }
            if (! $ctype) {
                $ctype = $this->_detectFileMimeType($filename);
            }
        }

        $this->getRequest()->file()->set($filename, array(
            'formname' => $formname,
            'filename' => basename($filename),
            'ctype' => $ctype,
            'data' => $data
        ));

        return $this;
    }
    /**
     * Remove a file to upload
     *
     * @param  string $filename
     * @return boolean
     */
    public function removeFileUpload($filename)
    {
        $file= $this->getRequest()->file()->get($filename);
        if (!empty($file)) {
            $this->getRequest()->file()->set($filename,null);
            return true;
        }
        return false;
    }
    
    /**
     * Prepare the request headers
     *
     * @return array
     */
    protected function prepareHeaders($body)
    {
        if (!empty($body)) {
            $this->addHeader(self::CONTENT_LENGTH,strlen($body));
        }

        // Set the host header
        if ($this->config['httpversion']==self::HTTP_1) {
            $uri = $this->getRequest()->uri();
            $host= $uri->getHost();
            // If the port is not default, add it
            if (! (($uri->getScheme() == 'http' && $uri->getPort() == 80) ||
                  ($uri->getScheme() == 'https' && $uri->getPort() == 443))) {
                $host .= ':' . $uri->getPort();
            }

            $this->addHeader('Host',$host);
        }

        // Set the connection header
        if (!$this->hasHeader('Connection')) {
            if (! $this->config['keepalive']) {
                $this->addHeader('Connection','close');
            }
        }

        // Set the Accept-encoding header if not set - depending on whether
        // zlib is available or not.
        if (! isset($this->headers['accept-encoding'])) {
            if (function_exists('gzinflate')) {
                $this->addHeader('Accept-encoding','gzip, deflate');
            } else {
                $this->addHeader('Accept-encoding','identity');
            }
        }


        // Set the user agent header
        if (!$this->hasHeader('User-Agent') && isset($this->config['useragent'])) {
            $this->addHeader('User-Agent',$this->config['useragent']);
        }

        // Set HTTP authentication if needed
        if (is_array($this->auth)) {
            $auth = self::encodeAuthHeader($this->auth['user'], $this->auth['password'], $this->auth['type']);
            $this->addHeader('Authorization',$auth);
        }

        // Load cookies from cookie jar
        // @todo cookie management
        /*
        if (!empty($this->getCookie())) {
            $cookstr = $this->getCookie()->match($this->getUri(),
                true, Header\Cookie::COOKIE_STRING_CONCAT);

            if ($cookstr) {
                $this->addHeader('Cookie',$cookstr);
            }
        }
        */
        return $this->getRequest()->headers()->toArray();
    }
    

    /**
     * Prepare the request body (for POST and PUT requests)
     *
     * @return string
     * @throws \Zend\Http\Client\Exception
     */
    protected function prepareBody()
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->getRequest()->isTrace()) {
            return '';
        }
        
        // If mbstring overloads substr and strlen functions, we have to
        // override it's internal encoding
        if (function_exists('mb_internal_encoding') &&
           ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }
        
        $rawBody= $this->getRequest()->getRawBody();
        if (!empty($rawBody)) {
            if (isset($mbIntEnc)) {
                mb_internal_encoding($mbIntEnc);
            }
            return $rawBody;
        }

        $body = '';

        $tot_files = count($this->getRequest()->file()->toArray());
        // If we have files to upload, force enctype to multipart/form-data
        if ($tot_files > 0) {
            $this->setEncType(self::ENC_FORMDATA);
        }

        // If we have POST parameters or files, encode and add them to the body
        if (count($this->getRequest()->post()->toArray()) > 0 || $tot_files > 0) {
            switch($this->enctype) {
                case self::ENC_FORMDATA:
                    // Encode body as multipart/form-data
                    $boundary = '---ZENDHTTPCLIENT-' . md5(microtime());
                    $this->addHeader(self::CONTENT_TYPE, self::ENC_FORMDATA . "; boundary={$boundary}");

                    // Get POST parameters and encode them
                    $params = self::_flattenParametersArray($this->getRequest()->post()->toArray());
                    foreach ($params as $pp) {
                        $body .= self::encodeFormData($boundary, $pp[0], $pp[1]);
                    }

                    // Encode files
                    foreach ($this->getRequest()->file()->toArray() as $key => $file) {
                        $fhead = array(self::CONTENT_TYPE => $file['ctype']);
                        $body .= self::encodeFormData($boundary, $file['formname'], $file['data'], $file['filename'], $fhead);
                    }

                    $body .= "--{$boundary}--\r\n";
                    break;

                case self::ENC_URLENCODED:
                    // Encode body as application/x-www-form-urlencoded
                    $this->addHeader(self::CONTENT_TYPE, self::ENC_URLENCODED);
                    $body = http_build_query($this->paramsPost);
                    break;

                default:
                    if (isset($mbIntEnc)) {
                        mb_internal_encoding($mbIntEnc);
                    }

                    throw new Exception\RuntimeException("Cannot handle content type '{$this->enctype}' automatically." .
                        " Please use Zend\Http\Client->setRawData() to send this kind of content.");
                    break;
            }
        }

        // Set the Content-Length if we have a body or if request is POST/PUT
        if ($body || $this->getRequest()->isPost() || $this->getRequest()->isPut()) {
            $this->addHeader(self::CONTENT_LENGTH, strlen($body));
        }

        if (isset($mbIntEnc)) {
            mb_internal_encoding($mbIntEnc);
        }

        return $body;
    }

    
    /**
     * Attempt to detect the MIME type of a file using available extensions
     *
     * This method will try to detect the MIME type of a file. If the fileinfo
     * extension is available, it will be used. If not, the mime_magic
     * extension which is deprected but is still available in many PHP setups
     * will be tried.
     *
     * If neither extension is available, the default application/octet-stream
     * MIME type will be returned
     *
     * @param string $file File path
     * @return string MIME type
     */
    protected function _detectFileMimeType($file)
    {
        $type = null;

        // First try with fileinfo functions
        if (function_exists('finfo_open')) {
            if (self::$_fileInfoDb === null) {
                self::$_fileInfoDb = @finfo_open(FILEINFO_MIME);
            }

            if (self::$_fileInfoDb) {
                $type = finfo_file(self::$_fileInfoDb, $file);
            }

        } elseif (function_exists('mime_content_type')) {
            $type = mime_content_type($file);
        }

        // Fallback to the default application/octet-stream
        if (! $type) {
            $type = 'application/octet-stream';
        }

        return $type;
    }

    /**
     * Encode data to a multipart/form-data part suitable for a POST request.
     *
     * @param string $boundary
     * @param string $name
     * @param mixed $value
     * @param string $filename
     * @param array $headers Associative array of optional headers @example ("Content-Transfer-Encoding" => "binary")
     * @return string
     */
    public static function encodeFormData($boundary, $name, $value, $filename = null, $headers = array()) {
        $ret = "--{$boundary}\r\n" .
            'Content-Disposition: form-data; name="' . $name .'"';

        if ($filename) {
            $ret .= '; filename="' . $filename . '"';
        }
        $ret .= "\r\n";

        foreach ($headers as $hname => $hvalue) {
            $ret .= "{$hname}: {$hvalue}\r\n";
        }
        $ret .= "\r\n";

        $ret .= "{$value}\r\n";

        return $ret;
    }

    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     * @param string $user
     * @param string $password
     * @param string $type
     * @return string
     * @throws \Zend\Http\Client\Exception
     */
    public static function encodeAuthHeader($user, $password, $type = self::AUTH_BASIC)
    {
        $authHeader = null;

        switch ($type) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos($user, ':') !== false) {
                    throw new Client\Exception\InvalidArgumentException("The user name cannot contain ':' in 'Basic' HTTP authentication");
                }

                $authHeader = 'Basic ' . base64_encode($user . ':' . $password);
                break;

            //case self::AUTH_DIGEST:
                /**
                 * @todo Implement digest authentication
                 */
            // break;

            default:
                throw new Client\Exception\InvalidArgumentException("Not a supported HTTP authentication type: '$type'");
        }

        return $authHeader;
    }

    /**
     * Convert an array of parameters into a flat array of (key, value) pairs
     *
     * Will flatten a potentially multi-dimentional array of parameters (such
     * as POST parameters) into a flat array of (key, value) paris. In case
     * of multi-dimentional arrays, square brackets ([]) will be added to the
     * key to indicate an array.
     *
     * @since 1.9
     *
     * @param array $parray
     * @param string $prefix
     * @return array
     */
    static protected function _flattenParametersArray($parray, $prefix = null)
    {
        if (! is_array($parray)) {
            return $parray;
        }

        $parameters = array();

        foreach($parray as $name => $value) {

            // Calculate array key
            if ($prefix) {
                if (is_int($name)) {
                    $key = $prefix . '[]';
                } else {
                    $key = $prefix . "[$name]";
                }
            } else {
                $key = $name;
            }

            if (is_array($value)) {
                $parameters = array_merge($parameters, self::_flattenParametersArray($value, $key));

            } else {
                $parameters[] = array($key, $value);
            }
        }

        return $parameters;
    }
    
    /**
     * -------------------- STATIC METHODS -------------------------------
     */
    
    /**
     * HTTP GET METHOD (static)
     *
     * @param string $url
     * @param array $query
     * @param array $headers
     * @return Response
     */
    public static function get($url, $query=null, $headers=null)
    {
        if (empty($url)) {
            return false;
        }
        
        $request= new Request();
        $request->setUri($url);
        $request->setMethod(Request::METHOD_GET);
        
        if (!empty($headers) && is_array($headers)) {
            $request->headers($headers);
        }
        
        if (!empty($query) && is_array($query)) {
            $request->query()->fromArray($query);
        }
        
        return self::getStaticClient()->send($request);
    }
    
    /**
     * --------------- CONVENIENT API (ZF1 BACKWARD COMPATIBLE) ----------------
     */
    
    /**
     * Set the HTTP method (to the request)
     * 
     * @param string $method
     * @return Client 
     */
    public function setMethod($method)
    {
        $this->getRequest()->setMethod($method);
        return $this;
    }
    /**
     * Execute an HTTP request
     * 
     * @param string $method
     * @return Response 
     */
    public function request($method='GET')
    {
        switch ($method) {
            case 'GET'  : 
                $method= Request::METHOD_GET;
                break;
            case 'POST' : 
                $method= Request::METHOD_POST;
                break;
            case 'OPTIONS' :
                $method= Request::METHOD_OPTIONS;
                break;
            case 'PUT' :
                $method= Request::METHOD_PUT;
                break;
            case 'DELETE' :
                $method= Request::METHOD_DELETE;
                break;
            default :
                throw new Exception\InvalidArgumentException ('The HTTP method is not valid');
        }
        
        $this->setMethod($method);
        if (!$this->getUri()) {
            throw new Exception\RunTimeException ('You must specify a valid URI for the HTTP request');
        }
        return $this->send();
    }
}