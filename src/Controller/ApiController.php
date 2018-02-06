<?php

namespace Arrow\ApiBase\Controller;

use Arrow\ApiBase\Api\ErrorCodes;

use Illuminate\Routing\Controller as BaseController;
use Arrow\ApiBase\Traits\ApiResponseTrait;

abstract class ApiController extends BaseController
{
    use ApiResponseTrait;
}