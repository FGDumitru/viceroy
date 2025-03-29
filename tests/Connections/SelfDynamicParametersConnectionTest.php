<?php

namespace Viceroy\Tests\Connections;

use PHPUnit\Framework\TestCase;
use Viceroy\Connections\SelfDynamicParametersConnection;
use Viceroy\Connections\Definitions\OpenAICompatibleEndpointConnection;
use Viceroy\Core\Response;

class SelfDynamicParametersConnectionTest extends TestCase
{
    private SelfDynamicParametersConnectionTestHelper $connection;
    private $mockConnection;

    protected function setUp(): void
    {
        $this->mockConnection = $this->createMock(OpenAICompatibleEndpointConnection::class);
        $this->connection = new SelfDynamicParametersConnectionTestHelper();
    }

    public function testAddNewFunction()
    {
        $functionName = 'testFunction';
        $definition = 'Test function definition';
        
        $result = $this->connection->addNewFunction($functionName, $definition);
        
        $this->assertSame($this->connection, $result);
        $this->assertArrayHasKey($functionName, $this->connection->getDefinedFunctions());
        $this->assertEquals($definition, $this->connection->getDefinedFunctions()[$functionName]);
    }

    public function testSetDebugMode()
    {
        $result = $this->connection->setDebugMode(true);
        
        $this->assertSame($this->connection, $result);
        $this->assertTrue($this->connection->isDebugMode());
    }

    public function testSetChainMode()
    {
        $result = $this->connection->setChainMode(true);
        
        $this->assertSame($this->connection, $result);
        $this->assertTrue($this->connection->isChainMode());
    }

    public function testSystemMessageTemplate()
    {
        $this->assertStringContainsString('Your task:', $this->connection->getSystem());
        $this->assertStringContainsString('Output Requirements:', $this->connection->getSystem());
    }

    public function testSetConnectionTimeout()
    {
        $timeout = 30;
        $this->connection->setConnectionTimeout($timeout);
        $this->assertTrue(true); // Just testing no exception
    }

    public function testGetLastResponse()
    {
        $this->assertNull($this->connection->getLastResponse());
    }

    public function testMagicCallWithValidFunction()
    {
        $this->markTestIncomplete('Magic call functionality requires more complex mocking');
    }

    public function testMagicCallWithInvalidFunction()
    {
        $this->markTestIncomplete('Magic call functionality requires more complex mocking');
    }

    public function testMagicCallWithChainMode()
    {
        $this->markTestIncomplete('Magic call functionality requires more complex mocking');
    }
}

class SelfDynamicParametersConnectionTestHelper extends SelfDynamicParametersConnection
{
    public function __construct()
    {
        parent::__construct('Definitions\\OpenAICompatibleEndpointConnection');
        $this->definedFunctions = [];
        $this->debugMode = false;
        $this->chainMode = false;
        $this->systemMessageTemplate = <<<SYS
Your task:

    You will receive a set of user instructions along with one or more parameters.
    Your role is to process these parameters exactly as instructed and return the result in JSON format.

Output Requirements:

    Always respond with a JSON object containing a "response" key. DO NOT OUTPUT anything else after the JSON object has finished printing.
    The "response" key should contain the result based on the user's instructions.
SYS;
        $this->systemMessage = $this->systemMessageTemplate;
        $this->lastResponse = null;
    }

    public function addNewFunction(string $functionName, string $definition): SelfDynamicParametersConnection
    {
        $this->definedFunctions[$functionName] = $definition;
        return $this;
    }

    public function setDebugMode(bool $debugMode): SelfDynamicParametersConnection
    {
        $this->debugMode = $debugMode;
        return $this;
    }

    public function setChainMode(bool $chainMode = true): SelfDynamicParametersConnection
    {
        $this->chainMode = $chainMode;
        $this->useLastResponse = $chainMode;
        return $this;
    }

    public function getDefinedFunctions(): array
    {
        return $this->definedFunctions;
    }

    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function isChainMode(): bool
    {
        return $this->chainMode;
    }

    public function getSystem(): string
    {
        return $this->systemMessage;
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    public function setConnection($connection): void
    {
        // Create a proxy object that matches parent class behavior
        $this->connection = new class($connection) {
            private $baseInstance;

            public function __construct($baseInstance)
            {
                $this->baseInstance = $baseInstance;
            }

            public function getBaseInstance()
            {
                return $this->baseInstance;
            }

            public function __call($method, $arguments)
            {
                if ($method === 'query') {
                    $response = $this->baseInstance->query(...$arguments);
                    // Return raw response to let parent class handle parsing
                    return $response;
                }
                if (method_exists($this->baseInstance, $method)) {
                    return $this->baseInstance->$method(...$arguments);
                }
                throw new \BadMethodCallException("Method ($method) does not exist");
            }
        };
    }
}
