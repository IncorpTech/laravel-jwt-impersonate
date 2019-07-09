<?php

namespace Incorp\Impersonate\Services;

use Incorp\Impersonate\Exceptions\AlreadyImpersonatingException;
use Incorp\Impersonate\Exceptions\CantBeImpersonatedException;
use Incorp\Impersonate\Exceptions\CantImpersonateException;
use Incorp\Impersonate\Exceptions\CantImpersonateSelfException;
use Incorp\Impersonate\Exceptions\NotImpersonatingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Incorp\Impersonate\Events\LeaveImpersonation;
use Incorp\Impersonate\Events\TakeImpersonation;

class ImpersonateManager
{
    /**
     * @var Application
     */
    private $app;
    /**
     * Authentication manager
     * @var
     */
    private $auth;

    /**
     * UserFinder constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->auth = $app['auth'];
    }

    /**
     * @param   int $id
     * @return  Model
     */
    public function findUserById($id)
    {
        $model = $this->app['config']->get('auth.providers.users.model');

        $user = call_user_func([
            $model,
            'findOrFail'
        ], $id);

        return $user;
    }

    /**
     * @return bool
     */
    public function isImpersonating()
    {
        return !empty($this->getImpersonatorId());
    }

    /**
     * @param   void
     * @return  int|null
     */
    public function getImpersonatorId()
    {
        return $this->auth->parseToken()->getPayLoad()->get($this->getSessionKey());
    }

    /**
     * @param   $impersonator
     * @return  void
     */
    public function setImpersonatorId($impersonator)
    {
        $this->auth->customClaims([$this->getSessionKey() => $impersonator->getKey()]);
    }

    /**
     * @param Model $from
     * @param Model $to
     * @return bool
     * @throws AlreadyImpersonatingException
     * @throws CantBeImpersonatedException
     * @throws CantImpersonateException
     * @throws CantImpersonateSelfException
     */
    public function take($from, $to)
    {
        if (!$this->isImpersonating()) {
            if (!($to->getKey() == $from->getKey())) {
                if ($to->canBeImpersonated()) {
                    if ($from->canImpersonate()) {
                        $this->deferLogout();
                        $this->setImpersonatorId($from);
                        $token = $this->deferLogin($to);

                        $this->app['events']->dispatch(new TakeImpersonation($from, $to));

                        return $token;
                    } else {
                        throw new CantImpersonateException();
                    }
                } else {
                    throw new CantBeImpersonatedException();
                }
            } else {
                throw new CantImpersonateSelfException();
            }
        } else {
            throw new AlreadyImpersonatingException();
        }
    }

    /**
     * @return string
     * @throws NotImpersonatingException
     */
    public function leave()
    {
        if ($this->isImpersonating()) {
            $impersonated = $this->auth->user();
            $impersonator = $this->findUserById($this->getImpersonatorId());
            $this->deferLogout();
            $this->clear();
            $token = $this->deferLogin($impersonator);

            $this->app['events']->dispatch(new LeaveImpersonation($impersonator, $impersonated));

            return $token;
        } else {
            throw new NotImpersonatingException();
        }
    }

    /**
     * @return string
     */
    public function retrieveToken()
    {
        return $this->auth->getToken();
    }

    public function deferLogout()
    {
        $this->auth->logout();
    }

    /**
     * @param $impersonator
     * @return string
     */
    public function deferLogin($impersonator)
    {
        $token = $this->auth->login($impersonator);
        $this->auth->setToken($token);
        return $token;
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->auth->customClaims([$this->getSessionKey() => null]);
    }

    /**
     * @return string
     */
    public function getSessionKey()
    {
        return config('laravel-jwt-impersonate.session_key');
    }

    /**
     * @return  string
     */
    public function getTakeRedirectTo()
    {
        try {
            $uri = route(config('laravel-jwt-impersonate.take_redirect_to'));
        } catch (\InvalidArgumentException $e) {
            $uri = config('laravel-jwt-impersonate.take_redirect_to');
        }

        return $uri;
    }

    /**
     * @return  string
     */
    public function getLeaveRedirectTo()
    {
        try {
            $uri = route(config('laravel-jwt-impersonate.leave_redirect_to'));
        } catch (\InvalidArgumentException $e) {
            $uri = config('laravel-jwt-impersonate.leave_redirect_to');
        }

        return $uri;
    }
}
