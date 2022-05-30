<?php

namespace Numesia\LaravelFranceConnect;

use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Nonce Session Name.
     */
    const OPENID_SESSION_NONCE = "open_id_session_nonce";

    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'FRANCECONNECT';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ["openid", "profile"];

    /**
     * {@inheritdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            $this->getServiceUrl() . '/api/v1/authorize', $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getServiceUrl() . '/api/v1/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            $this->getServiceUrl() . '/api/v1/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * {@inheritdoc}
     */
    public function redirect()
    {
        $fctokenClass = $this->getFctokenModel();

        $token = new $fctokenClass;

        $token->name = self::OPENID_SESSION_NONCE;
        $token->nonce = $this->getRandomToken();

        $token->save();

        $this->request->session()->put(self::OPENID_SESSION_NONCE, $token->nonce);

        return parent::redirect();
    }

    /**
     * {@inheritdoc}
     */
    protected function getCodeFields($state = null)
    {
        $nonce = $this->request->session()->get(self::OPENID_SESSION_NONCE);

        $token = $this->getFctokenModel()::whereNonce($nonce)->firstOrFail();

        return array_merge(parent::getCodeFields($state), [
            'nonce' => $nonce.'-'.$token->id,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        if ($this->hasInvalidNonce($this->parseIdToken($response))) {
            throw new InvalidNonceException;
        }

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->parseAccessToken($response)
        ));

        $this->credentialsResponseBody = $response;

        if ($user instanceof User) {
            $user->setAccessTokenResponseBody($this->credentialsResponseBody);
        }

        return $user->setToken($token)
            ->setRefreshToken($this->parseRefreshToken($response))
            ->setExpiresIn($this->parseExpiresIn($response));
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'    => $user['sub'], 'nickname' => null, 'name' => $user['given_name'] . ' ' . $user['family_name'],
            'email' => $user['email'], 'avatar' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Get Fctoken model
     *
     * @return Model
     */
    private function getFctokenModel()
    {
        return env('FRANCECONNECT_TOKEN_MODEL', 'App\Models\Fctoken');
    }

    /**
     * Determine if the current jwt / session has a mismatching "nonce".
     *
     * @return bool
     */
    private function hasInvalidNonce($jwt)
    {
        JWT::$leeway = 130;
        $payload     = JWT::decode($jwt, $this->clientSecret, ['HS256']);

        $data = explode('-', $payload->nonce);

        if (count($data) != 2) {
            return true;
        }

        $token = $this->getFctokenModel()::find(last($data));

        if ($token == null) {
            return true;
        }

        $nonce = $token->nonce;
        $token->delete();

        return head($data) != $nonce;
    }

    /**
     * Get the token id from the token response body.
     *
     * @param string $body
     *
     * @return string
     */
    private function parseIdToken($body)
    {
        return Arr::get($body, 'id_token');
    }

    /**
     * Get a random token.
     *
     * @return     string  The random token.
     */
    private function getRandomToken()
    {
        return sha1(random_int(0, mt_getrandmax()));
    }

    /**
     * Gets the service url.
     *
     * @return     string  The service url.
     */
    private function getServiceUrl()
    {
        if (env('FRANCECONNECT_SANDBOX') == true) {
            return 'https://fcp.integ01.dev-franceconnect.fr';
        }

        return 'https://app.franceconnect.gouv.fr';
    }

    public function logoutUrl($redirectUri, $token)
    {
        return $this->getServiceUrl() . '/api/v1/logout?' . http_build_query([
            'id_token_hint'            => $token,
            'state'                    => $this->getRandomToken(),
            'post_logout_redirect_uri' => url($redirectUri),
        ], '', '&', $this->encodingType);
    }
}
