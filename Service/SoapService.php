<?php

namespace Avtonom\WebGateBundle\Service;

use Symfony\Bridge\Monolog\Logger;
use Avtonom\WebGateBundle\Exception\WebGateException;

class SoapService
{
    protected $wsdlPath;
    protected $methodName;

    protected $login;
    protected $password;

    protected $connectionTimeout;

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @param Logger $logger
     * @param string $wsdlPath
     * @param string $methodName
     * @param string $login
     * @param string $password
     * @param int $connectionTimeout
     */
    function __construct(Logger $logger, $wsdlPath, $methodName, $login, $password, $connectionTimeout = 10)
    {
        $this->logger = $logger;
        $this->wsdlPath = $wsdlPath;
        $this->methodName = $methodName;
        $this->login = $login;
        $this->password = $password;
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * @param array $data
     * @param bool|false $returnXml
     *
     * @return \stdClass|array|string
     *
     * @throws WebGateException
     *
     * @deprecated
     */
    public function client($data, $returnXml = false)
    {
        return $this->send($data, $returnXml);
    }

    /**
     * @param array $data
     * @param bool|false $returnXml
     *
     * @return \stdClass|array|string
     *
     * @throws WebGateException
     */
    public function send($data, $returnXml = false)
    {
        try {
            $options = [
                'trace' => true,
                'exceptions' => true,
            ];
            if(!empty($this->login)){
                $options['login'] = $this->login;
                $options['password'] = $this->password;
            }
            if(0){
                $options['location'] = '';
                $options['uri'] = '';
                $wsdl = null;
            } else{
                $wsdl = $this->wsdlPath;
            }
            if($this->connectionTimeout) {
                ini_set("default_socket_timeout", $this->connectionTimeout);
                ini_set("max_execution_time", $this->connectionTimeout);
                set_time_limit($this->connectionTimeout);
                $options['connection_timeout'] = $this->connectionTimeout;

                $s_options = array(
                    'http' => array(
                        'timeout' => $this->connectionTimeout,
                    )
                );
                if($stream = stream_context_create($s_options)) {
                    try {
                        stream_set_timeout($stream, $this->connectionTimeout);
                        $options['stream_context'] = $stream;
                    } catch (\Exception $e) {
                        $this->logger->addWarning(PHP_EOL.__METHOD__.':'.sprintf('%s [%s/%s] %s', 'stream_context_create', get_class($e), $e->getCode(), $e->getMessage()));
                    }
                }
            }
            $client = new \SoapClient($wsdl, $options);

            $response = $client->__soapCall($this->methodName, array('params' => $data));
            $this->logger->addDebug('Request: '.$client->__getLastRequest());
            $this->logger->addDebug('Response XML: '.PHP_EOL.$client->__getLastResponse());
            $this->logger->addDebug('Response JSON: '.PHP_EOL.json_encode($response));
//            $this->logger->addDebug('Response Headers: '.$client->__getLastResponseHeaders());

            return ($returnXml) ? $client->__getLastResponse() : $response;

        } catch (\SoapFault $e) {
            if(isset($client)){
                $this->logger->addDebug(PHP_EOL.__METHOD__.':');
                $this->logger->addDebug('Request Headers: '.$client->__getLastRequestHeaders());
                $this->logger->addDebug('Request: '.$client->__getLastRequest());
                $this->logger->addDebug('Response Headers: '.$client->__getLastResponseHeaders());
                $this->logger->addDebug('Response: '.PHP_EOL.$client->__getLastResponse());
            }
            if($e->getCode()){
                $code = $e->getCode();
            } else {
                $code = (isset($e->faultcode) && is_numeric($e->faultcode)) ? $e->faultcode : 502;
            }
            if($code == 500){
                $code = 502;
            }
            $this->logger->addCritical(PHP_EOL.__METHOD__.sprintf('[%s/%s] %s', $e->getCode(), $code, $e->getMessage()));
            throw new WebGateException($e->getMessage(), $code, $e);
        }
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }
}