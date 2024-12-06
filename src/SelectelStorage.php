<?php

namespace querty888\selectel\storage;

/**
 * Selectel Storage PHP class
 *
 * PHP version 5 up to php 8.4 by querty888
 *
 * @author   Eugene Smith <easmith@mail.ru>
 */
class SelectelStorage
{

    /**
     * Throw exception on Error
     *
     * @var boolean
     */
    protected static $throwExceptions = true;
    
    /**
     * Header string in array for authtorization.
     *
     * @var array()
     */
    protected array $token;
    
    /**
     * Storage url
     *
     * @var string
     */
    protected $url = '';
    
    /**
     * The response format
     *
     * @var string
     */
    protected $format = '';
    
    /**
     * Allowed response formats
     *
     * @var array
     */
    protected $formats = ['', 'json', 'xml'];

    /**
     * Creating Selectel Storage PHP class
     *
     * @param string $user Account id
     * @param string $key Storage key
     * @param string $format Allowed response formats
     *
     * @return SelectelStorage
     */
    public function __construct(string $user, string $key, $format = null)
    {
        $header = SCurl::init("https://auth.selcdn.ru/")
            ->setHeaders(["Host: auth.selcdn.ru", 'X-Auth-User: ' . $user, 'X-Auth-Key: ' . $key])
            ->request("GET")
            ->getHeaders();

        if ($header["HTTP-Code"] != 204) {
            if ($header["HTTP-Code"] == 403) {
                $this->error($header["HTTP-Code"], sprintf("Forbidden for user '%s'", $user));
                return;
            }

            $this->error($header["HTTP-Code"], __METHOD__);
            return;
        }

        $this->format = (in_array($format, $this->formats, true) ? $format : $this->format);
        $this->url = $header['x-storage-url'];
        $this->token = ['X-Auth-Token: ' . $header['x-storage-token']];
    }

    /**
     * Handle errors
     *
     * @param integer $code
     * @param string $message
     *
     * @return mixed
     * @throws SelectelStorageException
     */
    protected function error($code, $message)
    {
        if (self::$throwExceptions) {
            throw new SelectelStorageException($message, $code);
        }

        return $code;
    }

    /**
     * Getting storage info
     */
    public function getInfo(): array
    {
        $head = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();
        return static::getX($head);
    }

    /**
     * Select only 'x-' from headers
     *
     * @param array $headers Array of headers
     * @param string $prefix Frefix for filtering
     */
    protected static function getX($headers, $prefix = 'x-'): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (stripos($key, $prefix) === 0) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Getting containers list
     *
     * @param int $limit Limit (Default 10000)
     * @param string $marker Marker (Default '')
     * @param string $format Format ('', 'json', 'xml') (Default self::$format)
     *
     * @return string
     */
    public function listContainers($limit = 10000, $marker = '', $format = null): array|string
    {
        $params = ['limit' => $limit, 'marker' => $marker, 'format' => (in_array($format, $this->formats, true) ? $format : $this->format)];

        $cont = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->request("GET")
            ->getContent();

        if ($params['format'] == '') {
            return explode("\n", trim($cont));
        }

        return trim($cont);
    }

    /**
     * Create container by name.
     * Headers for
     *
     * @param array $headers
     * @return SelectelContainer
     */
    public function createContainer(string $name, $headers = [])
    {
        $headers = array_merge($this->token, $headers);
        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("PUT")
            ->getInfo();

        if (!in_array($info["http_code"], [201, 202])) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $this->getContainer($name);
    }

    /**
     * Select container by name
     *
     *
     * @return SelectelContainer
     */
    public function getContainer(string $name)
    {
        $url = $this->url . $name;
        $headers = SCurl::init($url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();

        if ($headers["HTTP-Code"] != 204) {
            return $this->error($headers["HTTP-Code"], __METHOD__);
        }

        return new SelectelContainer($url, $this->token, $this->format, static::getX($headers));
    }

    /**
     * Delete container or object by name
     *
     *
     * @return integera
     */
    public function delete(string $name)
    {
        $info = SCurl::init($this->url . $name)
            ->setHeaders($this->token)
            ->request("DELETE")
            ->getInfo();

        if ($info["http_code"] != 204) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $info;
    }

    /**
     * Copy
     *
     * @param string $origin Origin object
     * @param string $destin Destination
     *
     * @return array
     */
    public function copy(string $origin, $destin)
    {
        $url = parse_url($this->url);
        $destin = $url['path'] . $destin;
        $headers = array_merge($this->token, ['Destination: ' . $destin]);

        return SCurl::init($this->url . $origin)
            ->setHeaders($headers)
            ->request("COPY")
            ->getResult();
    }

    public function setContainerHeaders(string $name, $headers)
    {
        $headers = static::getX($headers, "X-Container-Meta-");
        if (static::class !== 'SelectelStorage') {
            return 0;
        }

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Setting meta info
     *
     * @param string $name Name of object
     * @param array $headers Headers
     *
     * @return integer
     */
    protected function setMetaInfo(string $name, $headers)
    {
        if (static::class === 'SelectelStorage') {
            $headers = static::getX($headers, "X-Container-Meta-");
        } elseif (static::class === 'SelectelContainer') {
            $headers = static::getX($headers, "X-Container-Meta-");
        } else {
            return 0;
        }

        $info = SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("POST")
            ->getInfo();

        if ($info["http_code"] != 204) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $info["http_code"];
    }

    /**
     * Upload  and extract archive
     *
     * @param string $archiveFileName The name of a local file
     * @param string $remotePath The path to extract archive
     * @return array
     */
    public function putArchive($archive, $path = null)
    {
        $url = $this->url . $path . '?extract-archive=' . pathinfo((string) $archive, PATHINFO_EXTENSION);


        $headers = match ($this->format) {
            'json' => array_merge($this->token, ['Accept: application/json']),
            'xml' => array_merge($this->token, ['Accept: application/xml']),
            default => array_merge($this->token, ['Accept: text/plain']),
        };

        $info = SCurl::init($url)
            ->setHeaders($headers)
            ->putFile($archive)
            ->getContent();

        if ($this->format == '') {
            return explode("\n", trim((string) $info));
        }


        return $this->format == 'json' ? json_decode((string) $info, TRUE) : trim((string) $info);
    }

    /**
     * Set X-Account-Meta-Temp-URL-Key for temp file download link generation. Run it once and use key forever.
     *
     *
     * @return integer
     */
    public function setAccountMetaTempURLKey(string $key)
    {
        $url = $this->url;
        $headers = array_merge($this->token, ["X-Account-Meta-Temp-URL-Key: " . $key]);
        $res = SCurl::init($url)
            ->setHeaders($headers)
            ->request("POST")
            ->getHeaders();

        if ($res["HTTP-Code"] != 202) {
            return $this->error($res ["HTTP-Code"], __METHOD__);
        }

        return $res["HTTP-Code"];
    }

    /**
     * Get temp file download link
     *
     * @param string $key X-Account-Meta-Temp-URL-Key specified by setAccountMetaTempURLKey method
     * @param string $path to file, including container name
     * @param integer $expires time in UNIX-format, after this time link will be voided
     * @param string $otherFileName custom filename if needed
     */
    public function getTempURL($key, string $path, $expires, $otherFileName = null): string
    {
        $url = substr($this->url, 0, strlen($this->url) - 1);

        $sig_body = "GET\n{$expires}\n{$path}";

        $sig = hash_hmac('sha1', $sig_body, $key);

        $res = $url . $path . '?temp_url_sig=' . $sig . '&temp_url_expires=' . $expires;

        if ($otherFileName != null) {
            $res .= '&filename=' . urlencode($otherFileName);
        }

        return $res;
    }

}
