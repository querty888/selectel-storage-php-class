<?php

namespace querty888\selectel\storage;

/**
 * Created 06.09.14 23:47 by PhpStorm.
 *
 * PHP version 5 up to php 8.4 by querty888
 *
 * @category selectel-storage-php-class
 * @package class_package
 * @author Eugene Kuznetcov <easmith@mail.ru>
 */
class SCurl
{

    private static ?\querty888\selectel\storage\SCurl $instance = null;

    /**
     * Curl resource
     *
     * @var null|resource
     */
    private \CurlHandle|bool $ch;

    /**
     * Current URL
     *
     * @var string
     */
    private $url;

    /**
     * Last request result
     */
    private array $result = [];

    /**
     * Request params
     */
    private array $params = [];

    /**
     * Curl wrapper
     *
     * @param string $url
     */
    private function __construct($url)
    {
        $this->setUrl($url);
        $this->curlInit();
    }

    private function curlInit(): void
    {
        $this->ch = curl_init($this->url);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        
// TODO: big files
// curl_setopt($this->ch, CURLOPT_RANGE, "0-100");
    }

    /**
     *
     * @param string $url
     *
     * @return SCurl
     */
    public static function init($url): ?\querty888\selectel\storage\SCurl
    {
        if (self::$instance == null) {
            self::$instance = new SCurl($url);
        }
        
        return self::$instance->setUrl($url);
    }

    /**
     * Set url for request
     *
     * @param string $url URL
     */
    public function setUrl($url): ?\querty888\selectel\storage\SCurl
    {
        $this->url = $url;
        return self::$instance;
    }

    /**
     * @param $file
     * @throws SelectelStorageException
     */
    public function putFile($file): ?\querty888\selectel\storage\SCurl
    {
        if (!file_exists($file)) {
            throw new SelectelStorageException(sprintf("File '%s' does not exist", $file));
        }
        
        $fp = fopen($file, "r");
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, filesize($file));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set method and request
     *
     * @param string $method
     *
     * @return SCurl
     */
    public function request($method): ?\querty888\selectel\storage\SCurl
    {
        $this->method($method);
        $this->params = [];
        curl_setopt($this->ch, CURLOPT_URL, $this->url);

        $response = explode("\r\n\r\n", curl_exec($this->ch));

        $this->result['info'] = curl_getinfo($this->ch);
        $this->result['header'] = $this->parseHead($response[0]);
        unset($response[0]);
        $this->result['content'] = implode("\r\n\r\n", $response);

        // reinit
        $this->curlInit();

        return self::$instance;
    }

    /**
     * Set request method
     *
     * @param string $method
     *
     * @return SCurl
     */
    private function method($method): ?\querty888\selectel\storage\SCurl
    {
        switch ($method) {
            case "GET" : {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_HTTPGET, true);
                break;
            }
            case "HEAD" : {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                break;
            }
            case "POST" : {
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
                break;
            }
            case "PUT" : {
                curl_setopt($this->ch, CURLOPT_PUT, true);
                break;
            }
            default : {
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            }
        }
        
        return self::$instance;
    }

    /**
     * Header Parser
     *
     * @param array $head
     */
    private function parseHead(string $head): array
    {
        $result = [];
        $code = explode("\r\n", $head);
        preg_match('/HTTP\/(.+) (\d+)/', $code[0], $codeMatches);

        $result['HTTP-Version'] = $codeMatches[1];
        $result['HTTP-Code'] = (int)$codeMatches[2];
        preg_match_all("/([A-z\-]+)\: (.*)\r\n/", $head, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $result[strtolower($match[1])] = $match[2];
        }

        return $result;
    }

    public function putFileContents($contents): ?\querty888\selectel\storage\SCurl
    {
        $fp = fopen("php://temp", "rb+");
        fwrite($fp, (string) $contents);
        rewind($fp);
        curl_setopt($this->ch, CURLOPT_INFILE, $fp);
        curl_setopt($this->ch, CURLOPT_INFILESIZE, strlen((string) $contents));
        $this->request('PUT');
        fclose($fp);
        return self::$instance;
    }

    /**
     * Set headers
     *
     * @param array $headers
     *
     * @return SCurl
     */
    public function setHeaders($headers): ?\querty888\selectel\storage\SCurl
    {
        $headers = array_merge(["Expect:"], $headers);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        return self::$instance;
    }

    /**
     * Set request parameters
     *
     *
     * @return SCurl
     */
    public function setParams(array $params): ?\querty888\selectel\storage\SCurl
    {
        $this->params = $params;
        return self::$instance;
    }

    /**
     * Getting info, headers and content of last response
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Getting headers of last response
     *
     * @param string $header Header
     *
     * @return array
     */
    public function getHeaders($header = null)
    {
        return $this->result['header'];
    }

    /**
     * Getting content of last response
     *
     * @return array
     */
    public function getContent()
    {
        return $this->result['content'];
    }

    /**
     * Getting info of last response
     *
     * @param string $info Info's field
     *
     * @return array
     */
    public function getInfo($info = null)
    {
        return $this->result['info'];
    }

}
