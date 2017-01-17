<?php

namespace Arrow\ApiBase\Controller;

use Arrow\ApiBase\Api\ErrorCodes;

use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Manager;
use League\Fractal\Serializer\JsonApiSerializer;
use League\Fractal\Pagination\Cursor;
use \Response;
use \ErrorProvider;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

abstract class ApiController extends BaseController
{
    protected $statusCode = 200;
    protected $current, $eagerLoad, $requestedScopes, $request, $previous, $fractal;

    protected $possibleRelationships = array();
    protected $permanentRelationships = array();

    public function __construct(Manager $fractal, Request $request)
    {
        $this->fractal = $fractal;
        // $this->fractal->setSerializer(new JsonApiSerializer());

        $this->request = $request;

        $this->current = $this->request->has('current') ? (int) base64_decode($this->request->get('current')) : null;
        $this->previous = $this->request->has('previous') ? (int) base64_decode($this->request->get('previous')) : null;

        $requestedEmbeds = explode(',', $this->request->get('embed', ''));
        $this->eagerLoad = array_keys(array_merge(array_intersect($this->possibleRelationships, $requestedEmbeds), $this->permanentRelationships));
        $this->setRequestedScopes(array_values(array_merge(array_intersect($this->possibleRelationships, $requestedEmbeds), $this->permanentRelationships)));

        $this->fractal->parseIncludes($this->getRequestedScopes());
    }

    protected abstract function getPerPage();

    protected function getCurrent($raw = false)
    {
        $current = $this->current ? : 0;
        return $raw ? $current : base64_encode($current);
    }

    protected function getPrevious($raw = false)
    {
        return $raw ? $this->previous : ($this->previous ? base64_encode($this->previous) : null);
    }

    protected function getEagerLoad()
    {
        return $this->eagerLoad;
    }

    protected function setRequestedScopes($scopes)
    {
        $safe = array();
        foreach ($scopes as $scope) $safe[] = str_replace('.', '_', $scope);
        $this->requestedScopes = $safe;
    }

    protected function getRequestedScopes()
    {
        return $this->requestedScopes;
    }

    /**
     * Getter for statusCode
     *
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Setter for statusCode
     *
     * @param int $statusCode Value to set
     *
     * @return self
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    protected function getCursor($newCursor, $collection)
    {
        return new Cursor($this->getCurrent(), $this->getPrevious(), $newCursor, $this->getPerPage(), $collection->count());
    }

    protected function respondWithSuccess($message, $httpSuccessCode = null)
    {
        if ($httpSuccessCode) $this->statusCode = $httpSuccessCode;

        return $this->respondWithArray([
            'result'    =>  'sucess',
            'message'   =>  $message
        ]);
    }

    protected function respondWithItem($item, $callback)
    {
        $resource = new Item($item, $callback);

        $resource->setMetaValue('available_embeds', $this->possibleRelationships);

        $rootScope = $this->fractal->createData($resource);

        return $this->respondWithArray($rootScope->toArray());
    }

    protected function respondWithCollection($collection, $callback)
    {
        $resource = new Collection($collection, $callback);
        $last = $collection->last();
        $resource->setCursor($this->getCursor($last ? base64_encode($last->id) : null, $collection));

        $resource->setMetaValue('available_embeds', $this->possibleRelationships);

        $rootScope = $this->fractal->createData($resource);

        return $this->respondWithArray($rootScope->toArray());
    }

    protected function respondWithArray(array $array, array $headers = array())
    {
        $response = Response::json($array, $this->statusCode, $headers);

        $response->header('Content-Type', 'application/json');
        $response->header('Access-Control-Allow-Origin', '*');

        return $response;
    }

    protected function respondWithCustomError($code, $httpErrorCode = null)
    {
        if ($httpErrorCode) $this->statusCode = $httpErrorCode;
        if ($this->statusCode === 200)
        {
            trigger_error(
                "You better have a really good reason for erroring on a 200...",
                E_USER_WARNING
            );
        }

        return $this->respondWithArray([
                'error' => [
                    'code' => $code,
                    'http_code' => $this->statusCode,
                    'message' => ErrorProvider::getError($code),
                ]
        ]);
    }

    protected function respondWithError($message, $errorCode)
    {
        if ($this->statusCode === 200) {
            trigger_error(
                "You better have a really good reason for erroring on a 200...",
                E_USER_WARNING
            );
        }

        return $this->respondWithArray([
            'error' => [
                'code' => $errorCode,
                'http_code' => $this->statusCode,
                'message' => $message,
            ]
        ]);
    }

    /**
     * Generates a Response with a 403 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorForbidden($message = 'Forbidden')
    {
        return $this->setStatusCode(403)->respondWithError($message, ErrorCodes::CODE_FORBIDDEN);
    }

    /**
     * Generates a Response with a 500 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorInternalError($message = 'Internal Error')
    {
        return $this->setStatusCode(500)->respondWithError($message, ErrorCodes::CODE_INTERNAL_ERROR);
    }

    /**
     * Generates a Response with a 404 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorNotFound($message = 'Resource Not Found')
    {
        return $this->setStatusCode(404)->respondWithError($message, ErrorCodes::CODE_NOT_FOUND);
    }

    /**
     * Generates a Response with a 401 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorUnauthorized($message = 'Unauthorized')
    {
        return $this->setStatusCode(401)->respondWithError($message, ErrorCodes::CODE_UNAUTHORIZED);
    }

    /**
     * Generates a Response with a 400 HTTP header and a given message.
     *
     * @return  Response
     */
    public function errorWrongArgs($message = 'Wrong Arguments')
    {
        return $this->setStatusCode(400)->respondWithError($message, ErrorCodes::CODE_WRONG_ARGUMENTS);
    }
}