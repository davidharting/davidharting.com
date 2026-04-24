<?php

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

test('trusts X-Forwarded-Proto from any proxy', function () {
    $request = Request::create('http://app.onrender.com/test', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $seenSecure = null;
    (new TrustProxies)->handle($request, function (Request $req) use (&$seenSecure) {
        $seenSecure = $req->isSecure();

        return new Symfony\Component\HttpFoundation\Response('ok');
    });

    expect($seenSecure)->toBeTrue();
});

test('trusts X-Forwarded-Host from any proxy', function () {
    $request = Request::create('http://internal/test', 'GET');
    $request->headers->set('X-Forwarded-Host', 'davidharting.com');
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $seenHost = null;
    (new TrustProxies)->handle($request, function (Request $req) use (&$seenHost) {
        $seenHost = $req->getHost();

        return new Symfony\Component\HttpFoundation\Response('ok');
    });

    expect($seenHost)->toBe('davidharting.com');
});
