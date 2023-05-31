<?php

namespace Sphere\Api\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Sphere\Api\Error;

//@todo убрать реализацию авторизации из пакета. Оставить только интерфейс (?)
class AuthController extends RestController
{
    public function login()
    {
        $request = $this->request;

        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only(['email', 'password']);
        $token = app('auth')->attempt($credentials);

        if (!$token) {
            return $this->response->error(Error::AUTH_WRONG_LOGIN_OR_PASSWORD);
        }

        if (!User::where('email', Str::lower($request->email))->active()->exists()) {
            return $this->response->error('Извините, ваш аккаунт переведен в архив', ['code' => 5103]);
        }

        return $this->respondWithToken($token);
    }

    public function user()
    {
        if (!$this->user->is_active) {
            return $this->response->error('Извините, ваш аккаунт переведен в архив', ['code' => 5103]);
        }

        if ($relations = $this->processor->getRelations()) {
            $this->user->load($relations);
        }
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
        $ttlSeconds = app('auth')->factory()->getTTL() * 60;

        return $this->response->success([
            'token' => $token,
            'token_type' => 'bearer',
            'ttl' => $ttlSeconds,
            'expires_at' => time() + $ttlSeconds,
        ]);
    }
}
