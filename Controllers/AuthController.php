<?php

namespace Sphere\Api\Controllers;

use Sphere\Api\Error;

class AuthController extends RestController
{
    public function login()
    {
        $request = $this->request;

        $this->validate($request, [
            'email'     => 'required|email',
            'password'  => 'required'
        ]);

        $credentials = $request->only(['email', 'password']);
        $token = app('auth')->attempt($credentials);

        if (!$token) {
            return $this->response->error(Error::AUTH_WRONG_LOGIN_OR_PASSWORD);
        }

        return $this->respondWithToken($token);
    }

    public function user()
    {
        return $this->response->item($this->user);
    }

    public function refresh()
    {
        return $this->respondWithToken(app('auth')->refresh());
    }

    public function logout()
    {
        app('auth')->logout();

        return $this->response->success('Successfully logged out');
    }

    protected function respondWithToken($token)
    {
        return $this->response->success([
            'token' => $token,
            'token_type' => 'bearer',
            'ttl' => app('auth')->factory()->getTTL() * 60,
            'expires_at' => app('auth')->factory()->getTTL() * 60 + time(),
        ]);
    }
}
