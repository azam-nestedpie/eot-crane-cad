<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel silently treats an unparsable JSON body as empty, which then
 * surfaces as misleading "field is required" errors. Answer 400 instead.
 */
class RejectMalformedJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if ($request->isJson() && trim($body) !== '' && ! json_validate($body)) {
            return response()->json(['message' => 'Malformed JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
