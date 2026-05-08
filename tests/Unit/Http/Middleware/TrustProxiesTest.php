<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('trusts Render forwarded scheme headers without trusting forwarded host', function () {
    $request = Request::create('http://davidharting.com/test', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->headers->set('X-Forwarded-Port', '443');
    $request->headers->set('X-Forwarded-Host', 'attacker.example');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $seenSecure = null;
    $seenPort = null;
    $seenHost = null;

    (new TrustProxies)->handle($request, function (Request $req) use (&$seenSecure, &$seenPort, &$seenHost) {
        $seenSecure = $req->isSecure();
        $seenPort = $req->getPort();
        $seenHost = $req->getHost();

        return new Response('ok');
    });

    expect($seenSecure)->toBeTrue()
        ->and($seenPort)->toBe(443)
        ->and($seenHost)->toBe('davidharting.com');
});
