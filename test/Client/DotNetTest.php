<?php

/**
 * @see       https://github.com/laminas/laminas-soap for the canonical source repository
 * @copyright https://github.com/laminas/laminas-soap/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-soap/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Soap\Client;

use Laminas\Soap\Client\DotNet as DotNetClient;
use LaminasTest\Soap\TestAsset\MockCallUserFunc;
use PHPUnit_Framework_TestCase;

require_once __DIR__ . '/../TestAsset/call_user_func.php';

/**
 * .NET SOAP client tester.
 *
 * @group      Laminas_Soap
 */
class DotNetTest extends PHPUnit_Framework_TestCase
{
    /**
     * .NET SOAP client.
     *
     * @var \Laminas\Soap\Client\DotNet
     */
    private $client = null;

    /**
     * cURL client.
     *
     * @var \Laminas\Http\Client\Adapter\Curl
     */
    private $curlClient = null;

    /**
     * Sets up the fixture.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->client = new DotNetClient(null, array('location' => 'http://unithost/test',
                                                    'uri'      => 'http://unithost/test'));
    }

    /**
     * Tests that a default cURL client is used if none is injected.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::getCurlClient
     */
    public function testADefaultCurlClientIsUsedIfNoneIsInjected()
    {
        $this->assertInstanceOf('Laminas\Http\Client\Adapter\Curl', $this->client->getCurlClient());
    }

    /**
     * Tests that the cURL client can be injected.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::getCurlClient
     * @covers Laminas\Soap\Client\DotNet::setCurlClient
     */
    public function testCurlClientCanBeInjected()
    {
        $this->mockCurlClient();
        $this->assertSame($this->curlClient, $this->client->getCurlClient());
    }

    /**
     * Tests that a cURL client request is done when using NTLM
     * authentication.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::_doRequest
     */
    public function testCurlClientRequestIsDoneWhenUsingNtlmAuthentication()
    {
        $this->mockNtlmRequest();
        $this->assertInstanceOf('stdClass', $this->client->TestMethod());
    }

    /**
     * Tests that the default SOAP client request is done when not using NTLM authentication.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::_doRequest
     */
    public function testDefaultSoapClientRequestIsDoneWhenNotUsingNtlmAuthentication()
    {
        $soapClient = $this->getMock('Laminas\Soap\Client\Common',
                                     array('_doRequest'),
                                     array(array($this->client, '_doRequest'),
                                           null,
                                           array('location' => 'http://unit/test',
                                                 'uri'      => 'http://unit/test')));

        MockCallUserFunc::$mock = true;
        $this->client->setSoapClient($soapClient);
        $this->client->TestMethod();

        $this->assertSame('http://unit/test#TestMethod', MockCallUserFunc::$params[3]);

        MockCallUserFunc::$mock = false;
    }

    /**
     * Tests that the last request headers can be fetched correctly.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::getLastRequestHeaders
     */
    public function testLastRequestHeadersCanBeFetchedCorrectly()
    {
        $expectedHeaders = "Content-Type: text/xml; charset=utf-8\r\n"
                         . "Method: POST\r\n"
                         . "SOAPAction: \"http://unithost/test#TestMethod\"\r\n"
                         . "User-Agent: PHP-SOAP-CURL\r\n";

        $this->mockNtlmRequest();
        $this->client->TestMethod();

        $this->assertSame($expectedHeaders, $this->client->getLastRequestHeaders());
    }

    /**
     * Tests that the last response headers can be fetched correctly.
     *
     * @return void
     * @covers Laminas\Soap\Client\DotNet::getLastResponseHeaders
     */
    public function testLastResponseHeadersCanBeFetchedCorrectly()
    {
        $expectedHeaders = "Cache-Control: private\r\n"
                         . "Content-Type: text/xml; charset=utf-8\r\n";

        $this->mockNtlmRequest();
        $this->client->TestMethod();

        $this->assertSame($expectedHeaders, $this->client->getLastResponseHeaders());
    }

    /**
     * Mocks the cURL client.
     *
     * @return void
     */
    private function mockCurlClient()
    {
        $this->curlClient = $this->getMock('Laminas\Http\Client\Adapter\Curl',
                                           array('close', 'connect', 'read', 'write'));
        $this->client->setCurlClient($this->curlClient);
    }

    /**
     * Mocks an NTLM SOAP request.
     *
     * @return void
     */
    private function mockNtlmRequest()
    {
        $headers  = array('Content-Type' => 'text/xml; charset=utf-8',
                          'Method'       => 'POST',
                          'SOAPAction'   => '"http://unithost/test#TestMethod"',
                          'User-Agent'   => 'PHP-SOAP-CURL');
        $response = "HTTP/1.1 200 OK\n"
                  . "Cache-Control: private\n"
                  . "Content-Type: text/xml; charset=utf-8\n"
                  . "\n\n"
                  . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">'
                  . '<s:Body>'
                  . '<TestMethodResponse xmlns="http://unit/test">'
                  . '<TestMethodResult>'
                  . '<TestMethodResult><dummy></dummy></TestMethodResult>'
                  . '</TestMethodResult>'
                  . '</TestMethodResponse>'
                  . '</s:Body>'
                  . '</s:Envelope>';

        $this->mockCurlClient();

        $this->curlClient->expects($this->once())
                         ->method('connect')
                         ->with('unithost', 80);
        $this->curlClient->expects($this->once())
                         ->method('read')
                         ->will($this->returnValue($response));
        $this->curlClient->expects($this->any())
                         ->method('write')
                         ->with('POST', $this->isInstanceOf('Laminas\Uri\Http'), 1.1, $headers, $this->stringContains('<SOAP-ENV'));

        $this->client->setOptions(array('authentication' => 'ntlm',
                                        'login'          => 'username',
                                        'password'       => 'testpass'));
    }
}
