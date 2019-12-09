<?php

namespace Arrow\ApiBase\Middleware;

class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
	   //Intercepts OPTIONS requests
	   if($request->isMethod('OPTIONS')) {
		   $response = response('', 200);
	   } else {
		   // Pass the request to the next middleware
		   $response = $next($request);
	   }

	   // Adds headers to the response
	   $response->headers->set('Access-Control-Allow-Methods', 'HEAD, GET, POST, PUT, PATCH, DELETE');
	   $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Application');
	   $response->headers->set('Access-Control-Allow-Origin', '*');

	   // Sends it
	   return $response;  
	}
}	