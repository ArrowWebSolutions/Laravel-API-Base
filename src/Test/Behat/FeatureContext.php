<?php

namespace Arrow\ApiBase\Test\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends MinkContext implements Context, SnippetAcceptingContext
{
    use PhpUnitFunctions;
    /**
     * The Guzzle HTTP Client.
     */
    protected $client;

    /**
     * The current resource
     */
    protected $resource;

    /**
     * The request payload
     */
    protected $requestPayload;

    /**
     * The Guzzle HTTP Response.
     */
    protected $response;

    /**
     * The decoded response object.
     */
    protected $responsePayload;

    /**
     * The current scope within the response payload
     * which conditions are asserted against.
     */
    protected $scope;

    protected $bearerToken;
    protected $parameters;

    /**
     * The additional payload merged with the response payload
     */
    protected $additionalPayload;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;

        // Just a standard guzzle client will be created
        $this->createClients();
    }

    /**
     * @Given /^I use the client token$/
     */
    public function iUseTheClientToken()
    {
        //create a new client, using client token
        $this->createClients('client');
    }

    /**
     * @Given /^I use the user token$/
     */
    public function iUseTheUserToken()
    {
        //create a new client, using user token
        $this->createClients('user');
    }

    /**
     * @Given /^I use an invalid client token$/
     */
    public function iUseAnInvalidClientToken()
    {
        $this->createClients('client-invalid');
    }

    /**
     * @Given /^I use an invalid user token$/
     */
    public function iUseAnInvalidUserToken()
    {
        $this->createClients('user-invalid');
    }

    /**
     * @Given /^I have a password reset token for "([^"]*)"$/
     */
    public function iHaveAPasswordResetToken($email)
    {
        // Since we don't know the emailed token 
        // then replace the token with a new one
        $token = hash_hmac('sha256', Str::random(40), $email);

        $tokenHash = password_hash($token, PASSWORD_BCRYPT, ['cost' => '10']);

        if (DB::table('password_resets')
                    ->where('email', $email)
                    ->exists()) {
            DB::table('password_resets')
                ->where('email', $email)
                ->update([
                    'token' => $tokenHash
                ]);
        } else {
            DB::table('password_resets')
                ->insert([
                    'email' => $email,
                    'token' => $tokenHash,
                    'created_at' => new Carbon
                ]);
        }

        // append to the variable to populate when we send the request
        $this->additionalPayload['token'] = $token;
    }

    /**
     * @Given /^I have the payload:$/
     */
    public function iHaveThePayload(PyStringNode $requestPayload)
    {
        $this->requestPayload = $requestPayload;
    }

    /**
     * @When /^I request "(GET|PUT|POST|DELETE) ([^"]*)"$/
     */
    public function iRequest($httpMethod, $resource)
    {
        $this->resource = $resource;

        $method = strtolower($httpMethod);

        // Merge payload with additional payload
        $payload = json_decode($this->requestPayload, TRUE);

        if ($this->additionalPayload) {
            $payload = array_merge($payload,$this->additionalPayload);
        }

        try {
            switch ($httpMethod) {
                case 'PUT':
                case 'POST':
                    $this->response = $this->client->request($httpMethod, $resource, [
                        'json' => $payload,
                    ]);
                    break;

                default:
                    $this->response = $this
                        ->client
                        ->$method($resource);
            }
        } catch (BadResponseException $e) {

            $response = $e->getResponse();

            // Sometimes the request will fail, at which point we have
            // no response at all. Let Guzzle give an error here, it's
            // pretty self-explanatory.
            if ($response === null) {
                throw $e;
            }

            $this->response = $e->getResponse();
        }
    }

    /**
     * @When /^I request "(GET|PUT|POST|DELETE) ([^"]*)" and let "(\{[^\}]+\})"$/
     */
    public function iRequestAndLet($httpMethod, $resource, $replacements)
    {
        $replacements = json_decode($replacements);
        if (!$replacements) throw new \Exception("Invalid JSON in feature. Change this in BEHAT.");

        $scopePayload = $this->getScopePayload();
        foreach (get_object_vars($replacements) as $property)
        {
            $resource = str_replace("[:{$property}]", $scopePayload->$property, $resource);
        }

        return $this->iRequest($httpMethod, $resource);
    }

    /**
     * @Then /^I get a "([^"]*)" response$/
     */
    public function iGetAResponse($statusCode)
    {
        $response = $this->getResponse();
        $contentType = $response->getHeader('Content-Type');

        if (is_array($contentType)) $contentType = array_pop($contentType);

        if ($contentType === 'application/json') {
            $bodyOutput = '';
            if ($obj = json_decode((string)$response->getBody()))
            {
                $bodyOutput = json_decode((string)$response->getBody());
                $bodyOutput = isset($bodyOutput->error->message) ? $bodyOutput->error->message : '';
            }

            if (empty($bodyOutput)) $bodyOutput = (string)$response->getBody();
        } else {
            $bodyOutput = 'Output is ' . $contentType . ', which is not JSON and is therefore scary. Run the request manually.';
        }
        $this->assertSame((int) $statusCode, (int) $this->getResponse()->getStatusCode(), $bodyOutput);
    }

    /**
     * @Given /^the "([^"]*)" property equals "([^"]*)"$/
     */
    public function thePropertyEquals($property, $expectedValue)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->assertEquals(
            $actualValue,
            $expectedValue,
            "Asserting the [$property] property in current scope equals [$expectedValue]: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is null$/
     */
    public function thePropertyIsNull($property)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->assertEquals($actualValue, null,
            "Asserting the [$property] property in current scope equals null: " . json_encode($payload)
        );
    }

    /**
     * @Given the payload is an object
     */
    public function thePayloadIsAnObject()
    {
        $payload = $this->getScopePayload();

        $this->assertTrue(
            is_object($payload),
            "Asserting the payload in current scope [{$this->scope}] is an object: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property exists$/
     */
    public function thePropertyExists($property)
    {
        $payload = $this->getScopePayload();

        $message = sprintf(
            'Asserting the [%s] property exists in the scope [%s]: %s',
            $property,
            $this->scope,
            json_encode($payload)
        );

        if (is_object($payload)) {
            $this->assertTrue(array_key_exists($property, get_object_vars($payload)), $message);

        } else {
            $this->assertTrue(array_key_exists($property, $payload), $message);
        }
    }

    /**
     * @Given /^the "([^"]*)" property is an array$/
     */
    public function thePropertyIsAnArray($property)
    {
        $payload = $this->getScopePayload();

        $actualValue = $this->arrayGet($payload, $property);

        $this->assertTrue(
            is_array($actualValue),
            "Asserting the [$property] property in current scope [{$this->scope}] is an array: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is an array with a length of (\d+)$/

     */
    public function thePropertyIsAnArrayWithALengthOf($property, $length)
    {
        $payload = $this->getScopePayload();

        $actualValue = $this->arrayGet($payload, $property);

        $this->assertTrue(
            is_array($actualValue),
            "Asserting the [$property] property in current scope [{$this->scope}] is an array: ".json_encode($payload)
        );

        $this->assertSame(
            count($actualValue),
            (int)$length,
            "Asserting the [$property] property in the current scope [{$this->scope}] is an array with a length of [$length]:" . json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is an object$/
     */
    public function thePropertyIsAnObject($property)
    {
        $payload = $this->getScopePayload();

        $actualValue = $this->arrayGet($payload, $property);

        $this->assertTrue(
            is_object($actualValue),
            "Asserting the [$property] property in current scope [{$this->scope}] is an object: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is an empty array$/
     */
    public function thePropertyIsAnEmptyArray($property)
    {
        $payload = $this->getScopePayload();
        $scopePayload = $this->arrayGet($payload, $property);

        $this->assertTrue(
            is_array($scopePayload) and $scopePayload === [],
            "Asserting the [$property] property in current scope [{$this->scope}] is an empty array: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property contains (\d+) items$/
     */
    public function thePropertyContainsItems($property, $count)
    {
        $payload = $this->getScopePayload();

        assertCount(
            $count,
            $this->arrayGet($payload, $property),
            "Asserting the [$property] property contains [$count] items: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is an integer$/
     */
    public function thePropertyIsAnInteger($property)
    {
        $payload = $this->getScopePayload();

        $this->isType(
            'int',
            $this->arrayGet($payload, $property),
            "Asserting the [$property] property in current scope [{$this->scope}] is an integer: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is a string$/
     */
    public function thePropertyIsAString($property)
    {
        $payload = $this->getScopePayload();

        $this->isType(
            'string',
            $this->arrayGet($payload, $property),
            "Asserting the [$property] property in current scope [{$this->scope}] is a string: ".json_encode($payload)
        );
    }

    /**
     * @Given /^the "([^"]*)" property is a string equalling "([^"]*)"$/
     */
    public function thePropertyIsAStringEqualling($property, $expectedValue)
    {
        $payload = $this->getScopePayload();

        $this->thePropertyIsAString($property);

        $actualValue = $this->arrayGet($payload, $property);

        $this->assertSame(
            $actualValue,
            $expectedValue,
            "Asserting the [$property] property in current scope [{$this->scope}] is a string equalling [$expectedValue]."
        );
    }

    /**
     * @Given /^the "([^"]*)" property is a boolean$/
     */
    public function thePropertyIsABoolean($property)
    {
        $payload = $this->getScopePayload();

        $this->assertTrue(
            gettype($this->arrayGet($payload, $property)) == 'boolean',
            "Asserting the [$property] property in current scope [{$this->scope}] is a boolean."
        );
    }

    /**
     * @Given /^the "([^"]*)" property is a boolean equalling "([^"]*)"$/
     */
    public function thePropertyIsABooleanEqualling($property, $expectedValue)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        if (! in_array($expectedValue, ['true', 'false'])) {
            throw new \InvalidArgumentException("Testing for booleans must be represented by [true] or [false].");
        }

        $this->thePropertyIsABoolean($property);

        $this->assertSame(
            $actualValue,
            $expectedValue == 'true',
            "Asserting the [$property] property in current scope [{$this->scope}] is a boolean equalling [$expectedValue]."
        );
    }

    /**
     * @Given /^the "([^"]*)" property is a integer equalling "([^"]*)"$/
     */
    public function thePropertyIsAIntegerEqualling($property, $expectedValue)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $this->thePropertyIsAnInteger($property);

        $this->assertSame(
            $actualValue,
            (int) $expectedValue,
            "Asserting the [$property] property in current scope [{$this->scope}] is an integer equalling [$expectedValue]."
        );
    }

    /**
     * @Given /^the "([^"]*)" property is either:$/
     */
    public function thePropertyIsEither($property, PyStringNode $options)
    {
        $payload = $this->getScopePayload();
        $actualValue = $this->arrayGet($payload, $property);

        $valid = explode("\n", (string) $options);

        $this->assertTrue(
            in_array($actualValue, $valid),
            sprintf(
                "Asserting the [%s] property in current scope [{$this->scope}] is in array of valid options [%s].",
                $property,
                implode(', ', $valid)
            )
        );
    }

    /**
     * @Given /^scope into the last "([^"]*)" property$/
     */
    public function scopeIntoTheLastProperty($scope)
    {
        $this->scope = "{$scope}";

        $last = count($this->getScopePayload());
        $last = max(0, $last - 1);
        $this->scope = "{$scope}.{$last}";
    }
    
    /**
     * @Given /^scope into the first "([^"]*)" property$/
     */
    public function scopeIntoTheFirstProperty($scope)
    {
        $this->scope = "{$scope}.0";
    }

    /**
     * @Given /^scope into the "([^"]*)" property$/
     */
    public function scopeIntoTheProperty($scope)
    {
        $this->scope = $scope;
    }

    /**
     * @Given /^the properties exist:$/
     */
    public function thePropertiesExist(PyStringNode $propertiesString)
    {
        foreach (explode("\n", (string) $propertiesString) as $property) {
            $this->thePropertyExists($property);
        }
    }

    /**
     * @Given /^reset scope$/
     */
    public function resetScope()
    {
        $this->scope = null;
    }

    /**
     * @Transform /^(\d+)$/
     */
    public function castStringToNumber($string)
    {
        return intval($string);
    }

    /**
     * Checks the response exists and returns it.
     *
     * @return  Guzzle\Http\Message\Response
     */
    protected function getResponse()
    {
        if (! $this->response) {
            throw new Exception("You must first make a request to check a response.");
        }

        return $this->response;
    }

    /**
     * Return the response payload from the current response.
     *
     * @return  mixed
     */
    protected function getResponsePayload()
    {
        if (! $this->responsePayload) {
            $json = json_decode($this->getResponse()->getBody(true));

            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = 'Failed to decode JSON body ';

                switch (json_last_error()) {
                    case JSON_ERROR_DEPTH:
                        $message .= '(Maximum stack depth exceeded).';
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        $message .= '(Underflow or the modes mismatch).';
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        $message .= '(Unexpected control character found).';
                        break;
                    case JSON_ERROR_SYNTAX:
                        $message .= '(Syntax error, malformed JSON).';
                        break;
                    case JSON_ERROR_UTF8:
                        $message .= '(Malformed UTF-8 characters, possibly incorrectly encoded).';
                        break;
                    default:
                        $message .= '(Unknown error).';
                        break;
                }

                throw new Exception($message);
            }

            $this->responsePayload = $json;
        }

        return $this->responsePayload;
    }

    /**
     * Returns the payload from the current scope within
     * the response.
     *
     * @return mixed
     */
    protected function getScopePayload()
    {
        $payload = $this->getResponsePayload();

        if (! $this->scope) {
            return $payload;
        }

        return $this->arrayGet($payload, $this->scope);
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @copyright   Taylor Otwell
     * @link        http://laravel.com/docs/helpers
     * @param       array   $array
     * @param       string  $key
     * @param       mixed   $default
     * @return      mixed
     */
    protected function arrayGet($array, $key)
    {
        if (is_null($key)) {
            return $array;
        }

        // if (isset($array[$key])) {
        //     return $array[$key];
        // }

        foreach (explode('.', $key) as $segment) {

            if (is_object($array)) {
                if (! isset($array->{$segment})) {
                    return;
                }
                $array = $array->{$segment};

            } elseif (is_array($array)) {
                if (! array_key_exists($segment, $array)) {
                    return;
                }
                $array = $array[$segment];
            }
        }

        return $array;
    }

    /**
     * Create a Guzzle client instance
     *
     * @param  string $tokenType
     */
    protected function createClients($tokenType = null) {

        $parameters = $this->parameters;

        $config = isset($parameters['guzzle']) && is_array($parameters['guzzle']) ? $parameters['guzzle'] : [];

        $config['base_uri'] = $parameters['base_uri'];
        $config['headers'] = [
            'Content-Type' => 'text/json',
            'Accept' => 'text/json',
        ];

        /**
         * If the tokenType parameter is set then we want to get
         * a token so setup a guzzle client and pass the token
         * back and use this in the config for the main guzzle client
         */
        $bearerToken = null;
        switch ($tokenType)
        {
            case 'user':
                $bearerToken = $this->getPasswordAccessToken(
                    $parameters['password_credentials']['client_id'],
                    $parameters['password_credentials']['client_secret'],
                    $parameters['password_credentials']['username'],
                    $parameters['password_credentials']['password'],
                    '',
                    $config
                );
                break;
            case 'client':
                $bearerToken = $this->getClientCredentialsAccessToken(
                    $parameters['client_credentials']['client_id'],
                    $parameters['client_credentials']['client_secret'],
                    '',
                    $config
                );
                break;
            case 'user-invalid':
                $bearerToken = $this->getInvalidPasswordAccessToken();
                break;
            case 'client-invalid':
                $bearerToken = $this->getInvalidClientAccessToken();
                break;
        }

        if (!empty($bearerToken)) {
            $config['headers']['Authorization'] = 'Bearer ' . $bearerToken;
            $this->bearerToken = $bearerToken;
        }

        $this->client = new Client($config);
    }

    protected function getInvalidPasswordAccessToken()
    {
        return sha1(str_random(60));
    }

    protected function getInvalidClientAccessToken()
    {
        return $this->getInvalidPasswordAccessToken();
    }

    /**
     * Gets an access token for a password grant
     * @param  int $clientId     id of the requesting client application
     * @param  string $clientSecret secret of the requesting client application
     * @param  string $username     username of the user to authenticate
     * @param  string $password     password of the user to authenticate
     * @param  string $scope        requested scopes
     * @param  array $config       Guzzle config
     * @return string              the access token
     */
    protected function getPasswordAccessToken($clientId, $clientSecret, $username, $password, $scope, $config)
    {
        //just cache the results so we don't hit the API for a new token for each request
        static $results = [];

        $hash = sha1($clientId . $clientSecret . $username . $password . $scope);
        if (!isset($results[$hash]))
        {
            $http = new Client($config);
            $response = $http->post('/oauth/token', [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'username' => $username,
                    'password' => $password,
                    'scope' => $scope,
                ],
            ]);

            $results[$hash] = json_decode((string) $response->getBody(), true)['access_token'];
        }

        return $results[$hash];
    }

    /**
     * Gets an access token for a client credentials grant
     * @param  int $clientId     id of the requesting client application
     * @param  string $clientSecret secret of the requesting client application
     * @param  string $scope        requested scopes
     * @param  array $config       guzzle config
     * @return string               the access token
     */
    protected function getClientCredentialsAccessToken($clientId, $clientSecret, $scope, $config)
    {
        //just cache the results so we don't hit the API for a new token for each request
        static $results = [];

        $hash = sha1($clientId . $clientSecret . $scope);
        if (!isset($results[$hash]))
        {
            $http = new Client($config);
            $response = $http->post('/oauth/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $scope,
                ],
            ]);

            $results[$hash] = json_decode((string) $response->getBody(), true)['access_token'];
        }

        return $results[$hash];
    }
}