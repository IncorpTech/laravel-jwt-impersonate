# Incorp Laravel JWT Impersonate

**DISCLAIMER:** This is a project meant to fit the needs of a private company. This means this repository will not focus on flexibe open-source driven development, rather it will be changed and altered as the company sees fit.  


**Laravel JWT Impersonate** makes it easy to **authenticate as your users**. Add a simple **trait** to your **user model** and impersonate as one of your users in one click.
 
- [Requirements](#requirements)
- [Installation](#installation)
- [Simple usage](#simple-usage)
    - [Using the built-in controller](#using-the-built-in-controller)
- [Advanced Usage](#advanced-usage)
    - [Defining impersonation authorization](#defining-impersonation-authorization)
    - [Using your own strategy](#using-your-own-strategy)
    - [Middleware](#middleware)
    - [Exceptions](#exceptions)
    - [Events](#events)
- [Configuration](#configuration)


## Requirements

- Laravel >= 5.8
- PHP >= 7.1
- JWT-Auth >= dev-develop

## Installation

- Require it with Composer:
```bash
composer require incorp/laravel-jwt-impersonate
```

- Add the service provider at the end of your `config/app.php`:
```php
'providers' => [
    // ...
    Incorp\Impersonate\ImpersonateServiceProvider::class,
],
```

- Add the trait `Incorp\Impersonate\Models\Impersonate` to your **User** model.

## Simple usage

Impersonate a user:
```php
$token = Auth::user()->impersonate($other_user);
// You're now logged as the $other_user and the authentication token is stored in $token.
```

Leave impersonation:
```php
$token = Auth::user()->leaveImpersonation();
// You're now logged as your original user and the authentication token is stored in $token.
```

### Using the built-in controller

In your routes file you can call the `impersonate` route macro if you want to use the built-in controller. 
```php
Route::impersonate();
```

Alternatively, you can execute this macro with your `RouteServiceProvider`.

```php
namespace App\Providers;

class RouteServiceProvider extends ServiceProvider
{
    public function map() {
        Route::middleware('web')->group(function (Router $router) {
            $router->impersonate();
        });
    }
}
```

```php
// Where $id is the ID of the user you want impersonate
route('impersonate', $id) //the url path is "impersonate/take/{id}".
```

```php
// Generate an URL to leave current impersonation
route('impersonate.leave') //the url path is "impersonate/leave".
```

```php
// Check the current user impersonation status
route('impersonate.info') //the url path is "impersonate/info".
```

## Advanced Usage

### Defining impersonation authorization

By default all users can **impersonate** an user.  
You need to add the method `canImpersonate()` to your user model:

```php
    /**
     * @return bool
     */
    public function canImpersonate()
    {
        // For example
        return $this->is_admin == 1;
    }
```

By default all users can **be impersonated**.  
You need to add the method `canBeImpersonated()` to your user model to extend this behavior:

```php
    /**
     * @return bool
     */
    public function canBeImpersonated()
    {
        // For example
        return $this->can_be_impersonated == 1;
    }
```

### Using your own strategy

It is possible to implement your own controller to deal with impersonation:
```php
use Incorp\Impersonate\Services\ImpersonateManager;

class ImpersonateController extends Controller
{
    protected $manager;
    
    // Dependency Injection
    public function __construct(ImpersonateManager $manager)
    {
        $this->manager = $manager;
    }

    public function impersonate(){ /*....*/ }
    public function leave(){ /*....*/ }
}
```
```php
class ImpersonateController extends Controller
{
    protected $manager;
        
    //Direct app call
    public function __construct()
    {
        $this->manager = app('impersonate');
    }

    public function impersonate(){ /*....*/ }
    public function leave(){ /*....*/ }
}
```

- Working with the manager:
```php
$manager = app('impersonate');

// Find a user by its ID
$manager->findUserById($id);

// TRUE if you are impersonating an user.
$manager->isImpersonating();

// Impersonate a user. Pass the original user and the user you want to impersonate. Returns authentication token
$token = $manager->take($from, $to);

// Leave current impersonation. Returns authentication token
$token = $manager->leave();

// Get the impersonator ID
$manager->getImpersonatorId();
```

### Middleware

**Protect From Impersonation**

You can use the middleware `impersonate.protect` to protect your routes against user impersonation.  
This middleware can be useful when you want to protect specific pages like users subscriptions, users credit cards, ... 

```php
Router::get('/my-credit-card', function() {
    echo "Can't be accessed by an impersonator";
})->middleware('impersonate.protect');
```

### Exceptions

There are six possible exceptions thrown by the service:
- `AlreadyImpersonatingException` is thrown when an impersonator tries to take another persona without leaving the first one.
- `CantBeImpersonatedException` is thrown when the method `canBeImpersonated()` fails.
- `CantImpersonateException` is thrown when the method `canImpersonate()` fails.
- `CantImpersonateSelfException` is thrown when an user tries to impersonate self.
- `NotImpersonatingException` is thrown when an user tries to leave an impersonation without being impersonating.
- `ProtectedFromImpersonationException` is thrown when an impersonator tries to get access to a route protected by the middleware.

Each exception have a message and a status code available through the respective methods `getErrorMessage()` and `getErrorCode()`.

### Events

There are two events available that can be used to improve your workflow:
- `TakeImpersonation` is fired when an impersonation is taken.
- `LeaveImpersonation` is fired when an impersonation is left.

Each events returns two properties `$event->impersonator` and `$event->impersonated` containing a User model isntance.

## Configuration

The package comes with a configuration file.  

Publish it with the following command:
```bash
php artisan vendor:publish --tag=impersonate
```

Available options:
```php
    // The custom claim key used to store the original user id in the JWT token.
    'session_key' => 'impersonated_by',
```
```php
    // The alias for the authentication middleware to be used in the routes.
    'auth_alias' => 'auth',
```

## Licence

MIT
