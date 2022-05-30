# Laravel France Connect

France connect OAuth2 Provider for Laravel Socialite (proxy version).

# INSTALLATION

## 1. COMPOSER

```
// This assumes that you have composer installed globally
composer require numesia/laravel-franceconnect-proxy
```

## 2. SERVICE PROVIDER

* Remove `Laravel\Socialite\SocialiteServiceProvider` from your `providers[]` array in `config\app.php` if you have added it already.
* Add `SocialiteProviders\Manager\ServiceProvider::class` to your `providers[]` array in `config\app.php`.

For example:
```
'providers' => [
    // a whole bunch of providers
    // remove 'Laravel\Socialite\SocialiteServiceProvider',
    SocialiteProviders\Manager\ServiceProvider::class, // add
];
```

> Note: If you would like to use the Socialite Facade, you need to [install](https://github.com/laravel/socialite) it.

## 3. ADD THE EVENT AND LISTENERS

* Add SocialiteProviders\Manager\SocialiteWasCalled event to your `listen[]` array in  `<app_name>/Providers/EventServiceProvider`.

* Add your listeners (i.e. the ones from the providers) to the `SocialiteProviders\Manager\SocialiteWasCalled[]` that you just created.

* The listener that you add for this provider is  `'SocialiteProviders\LaravelFranceConnect\FranceConnectExtendSocialite@handle',`.

> Note: You do not need to add anything for the built-in socialite providers unless you override them with your own providers.

For example:

```
/**
 * The event handler mappings for the application.
 *
 * @var array
 */
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        'Numesia\LaravelFranceConnect\FranceConnectExtendSocialite@handle',
    ],
];
```
## 4. ENVIRONMENT VARIABLES

Append provider values to your .env file

```
// other values above
FRANCECONNECT_SANDBOX=true
FRANCECONNECT_KEY=yourkeyfortheservice
FRANCECONNECT_SECRET=yoursecretfortheservice
FRANCECONNECT_REDIRECT_URI=https://example.com/login/franceconnect/callback
FRANCECONNECT_TOKEN_MODEL=App\Models\Fctoken
```

# USAGE

you are ready to authenticate users! You will need two routes: one for redirecting the user to the OAuth provider, and another for receiving the callback from the provider after authentication.

```
Route::get('login/franceconnect', 'Auth\LoginController@redirectToProvider');
Route::get('login/franceconnect/callback', 'Auth\LoginController@handleProviderCallback');
```

We will access Socialite using the Socialite facade (must [install socialite](https://github.com/laravel/socialite)):

```
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Socialite;

class LoginController extends Controller
{
    /**
     * Redirect the user to the FranceConnect authentication page.
     *
     * @return Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('franceconnect')
            ->scopes(["identite_pivot", "address", "email", "phone", "adresse_postale"])
            ->redirect();
    }

    /**
     * Obtain the user information from FranceConnect.
     *
     * @return Response
     */
    public function handleProviderCallback()
    {
        $user = Socialite::driver('franceconnect')->user();
        // Do something with $user
    }
}
```
