<?php

namespace Arrow\ApiBase\Middleware;

use Closure;

class ApiVersion
{
    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $version)
    {
        // Get api version from header, if blank then default to 1
        $headerVersion = $request->header('api-version') ? $request->header('api-version') : '1';

        // If header version doesn't match the version passed in the return json response
        if ($headerVersion !== $version) {
            return response()->json(['result' => 'error', 'message' => 'Unsupported API Version.'], 400);
        }

        return $next($request);
    }

}