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

class SessionStorage
{
    /**
     * @var Application
     */
    private $app;

    /**
     * UserFinder constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
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
        return session()->has($this->getSessionKey());
    }

    /**
     * @param   void
     * @return  int|null
     */
    public function getImpersonatorId()
    {
        return session($this->getSessionKey(), null);
    }

    /**
     * @param Model $from
     * @param Model $to
     * @return bool
     */
    public function take($from, $to)
    {
        if (!$this->isImpersonating()) {
            if (!($to->getKey() == $from->getKey())) {
                if ($to->canBeImpersonated()) {
                    if ($from->canImpersonate()) {
                        session()->put(config('laravel-jwt-impersonate.session_key'), $from->getKey());

                        $this->app['auth']->logout();
                        $token = $this->app['auth']->login($to);

                        $this->app['events']->fire(new TakeImpersonation($from, $to));

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
     * @return  bool
     */
    public function leave()
    {
        if ($this->isImpersonating()) {
            $impersonated = $this->app['auth']->user();
            $impersonator = $this->findUserById($this->getImpersonatorId());

            $this->app['auth']->logout();
            $token = $this->app['auth']->login($impersonator);

            $this->clear();

            $this->app['events']->fire(new LeaveImpersonation($impersonator, $impersonated));

            return $token;
        } else {
            throw new NotImpersonatingException();
        }
    }

    /**
     * @return void
     */
    public function clear()
    {
        session()->forget($this->getSessionKey());
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
