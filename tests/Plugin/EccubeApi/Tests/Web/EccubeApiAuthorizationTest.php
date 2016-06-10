<?php

namespace Plugin\EccubeApi\Tests\Web;

use Plugin\EccubeApi\Util\EntityUtil;

class EccubeApiAuthorizationTest extends AbstractEccubeApiWebTestCase
{
    protected $Customer;
    protected $CustomerClient;
    protected $CustomerUserInfo;
    protected $Member;
    protected $MemberClient;
    protected $MemberUserInfo;
    protected $scope_granted;

    public function setUp()
    {
        parent::setUp();
        /** Member, Customer 共に選択可能な scope */
        $this->scope_granted = 'openid email customer_read customer_write';
        $this->Customer = $this->createCustomer();
        $this->CustomerUserInfo = $this->createUserInfo($this->Customer);
        $this->CustomerClient = $this->createApiClient($this->Customer);

        $this->Member = $this->app['eccube.repository.member']->find(2);
        $this->MemberInfo = $this->createUserInfo($this->Member);
        $this->MemberClient = $this->createApiClient($this->Member);

        $Scopes = $this->app['eccube.repository.oauth2.scope']->findByString($this->scope_granted, $this->Member);
        foreach ($Scopes as $Scope) {
            $this->addClientScope($this->CustomerClient, $Scope->getScope());
            $this->addClientScope($this->MemberClient, $Scope->getScope());
        }
    }

    /**
     * Member で Authorization Code を取得する.
     */
    public function testAuthorizationEndPointWithMember()
    {
        $client = $this->logInTo($this->Member);
        $path = $this->app->path('oauth2_server_admin_authorize');
        $params = array(
                    'client_id' => $this->MemberClient->getClientIdentifier(),
                    'redirect_uri' => $this->MemberClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // GET でリクエストし, 認可画面を表示
        $crawler = $client->request('GET', $path, $params);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertRegExp('/OpenID Connect 認証/u', $crawler->filter('ol#permissions')->text());
        $this->assertRegExp('/メールアドレス/u', $crawler->filter('ol#permissions')->text());

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $location = $this->client->getResponse()->headers->get('location');
        $this->assertRegExp('/^'.preg_quote($this->MemberClient->getRedirectUri(), '/').'/', $location);
        preg_match('/^'.preg_quote($this->MemberClient->getRedirectUri(), '/').'\?code=(\w+)&state=random_state/', $location, $matched);

        $authorization_code = $matched[1];
        $AuthorizationCode = $this->app['eccube.repository.oauth2.authorization_code']->getAuthorizationCode($authorization_code);
        $this->assertTrue(is_array($AuthorizationCode));

        $this->expected = $this->scope_granted;
        $this->actual = $AuthorizationCode['scope'];
        $this->verify();

        $this->expected = $this->MemberClient->getClientIdentifier();
        $this->actual = $AuthorizationCode['client_id'];
        $this->verify();
    }

    /**
     * Customer で Authorization Code を取得する.
     */
    public function testAuthorizationEndPointWithCustomer()
    {
        $client = $this->logInTo($this->Customer);
        $path = $this->app->path('oauth2_server_mypage_authorize');
        $params = array(
                    'client_id' => $this->CustomerClient->getClientIdentifier(),
                    'redirect_uri' => $this->CustomerClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // GET でリクエストし, 認可画面を表示
        $crawler = $client->request('POST', $path, $params);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertRegExp('/OpenID Connect 認証/u', $crawler->filter('ol#permissions')->text());
        $this->assertRegExp('/メールアドレス/u', $crawler->filter('ol#permissions')->text());

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $location = $this->client->getResponse()->headers->get('location');
        $this->assertRegExp('/^'.preg_quote($this->CustomerClient->getRedirectUri(), '/').'/', $location);
        preg_match('/^'.preg_quote($this->CustomerClient->getRedirectUri(), '/').'\?code=(\w+)&state=random_state/', $location, $matched);

        $authorization_code = $matched[1];
        $AuthorizationCode = $this->app['eccube.repository.oauth2.authorization_code']->getAuthorizationCode($authorization_code);
        $this->assertTrue(is_array($AuthorizationCode));

        $this->expected = $this->scope_granted;
        $this->actual = $AuthorizationCode['scope'];
        $this->verify();

        $this->expected = $this->CustomerClient->getClientIdentifier();
        $this->actual = $AuthorizationCode['client_id'];
        $this->verify();
    }

    /**
     * Member で Authorization Code を取得する.
     */
    public function testAuthorizationEndPointWithMemberOob()
    {
        $redirect_uri = 'urn:ietf:wg:oauth:2.0:oob';
        $this->MemberClient->setRedirectUri($redirect_uri);
        $this->app['orm.em']->flush($this->MemberClient);

        $client = $this->logInTo($this->Member);
        $path = $this->app->path('oauth2_server_admin_authorize');
        $params = array(
                    'client_id' => $this->MemberClient->getClientIdentifier(),
                    'redirect_uri' => $this->MemberClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // GET でリクエストし, 認可画面を表示
        $crawler = $client->request('GET', $path, $params);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertRegExp('/OpenID Connect 認証/u', $crawler->filter('ol#permissions')->text());
        $this->assertRegExp('/メールアドレス/u', $crawler->filter('ol#permissions')->text());

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $location = $this->client->getResponse()->headers->get('location');
        $this->assertRegExp('/^'.preg_quote($this->app->url('oauth2_server_admin_authorize'), '/').'/', $location);
        preg_match('/^'.preg_quote($this->app->url('oauth2_server_admin_authorize'), '/').'\/(\w+)/', $location, $matched);

        $authorization_code = $matched[1];
        $AuthorizationCode = $this->app['eccube.repository.oauth2.authorization_code']->getAuthorizationCode($authorization_code);
        $this->assertTrue(is_array($AuthorizationCode));

        $this->expected = $this->scope_granted;
        $this->actual = $AuthorizationCode['scope'];
        $this->verify();

        $this->expected = $this->MemberClient->getClientIdentifier();
        $this->actual = $AuthorizationCode['client_id'];
        $this->verify();

        // AuthorizationCode 表示画面
        $crawler = $client->request('GET', $this->app->url('oauth2_server_admin_authorize_oob', array('code' => $matched[1])));
        $this->expected = $AuthorizationCode['code'];
        $this->actual = $crawler->filter('pre')->text();
        $this->verify();
    }

    /**
     * Customer で Authorization Code を取得する.
     */
    public function testAuthorizationEndPointWithCustomerOob()
    {
        $redirect_uri = 'urn:ietf:wg:oauth:2.0:oob';
        $this->CustomerClient->setRedirectUri($redirect_uri);
        $this->app['orm.em']->flush($this->CustomerClient);

        $client = $this->logInTo($this->Customer);
        $path = $this->app->path('oauth2_server_mypage_authorize');
        $params = array(
                    'client_id' => $this->CustomerClient->getClientIdentifier(),
                    'redirect_uri' => $this->CustomerClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // GET でリクエストし, 認可画面を表示
        $crawler = $client->request('GET', $path, $params);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertRegExp('/OpenID Connect 認証/u', $crawler->filter('ol#permissions')->text());
        $this->assertRegExp('/メールアドレス/u', $crawler->filter('ol#permissions')->text());

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $location = $this->client->getResponse()->headers->get('location');
        $this->assertRegExp('/^'.preg_quote($this->app->url('oauth2_server_mypage_authorize'), '/').'/', $location);
        preg_match('/^'.preg_quote($this->app->url('oauth2_server_mypage_authorize'), '/').'\/(\w+)/', $location, $matched);

        $authorization_code = $matched[1];
        $AuthorizationCode = $this->app['eccube.repository.oauth2.authorization_code']->getAuthorizationCode($authorization_code);
        $this->assertTrue(is_array($AuthorizationCode));

        $this->expected = $this->scope_granted;
        $this->actual = $AuthorizationCode['scope'];
        $this->verify();

        $this->expected = $this->CustomerClient->getClientIdentifier();
        $this->actual = $AuthorizationCode['client_id'];
        $this->verify();

        // AuthorizationCode 表示画面
        $crawler = $client->request('GET', $this->app->url('oauth2_server_mypage_authorize_oob', array('code' => $matched[1])));
        $this->expected = $AuthorizationCode['code'];
        $this->actual = $crawler->filter('pre')->text();
        $this->verify();
    }

    /**
     * Member で OAuth2.0 Authorization code Flow を使用してアクセストークンを取得する.
     */
    public function testOAuth2AuthorizationCodeFlowWithMember()
    {
        $this->scope_granted = 'customer_read customer_write';

        $client = $this->logInTo($this->Member);
        $path = $this->app->path('oauth2_server_admin_authorize');
        $params = array(
                    'client_id' => $this->MemberClient->getClientIdentifier(),
                    'redirect_uri' => $this->MemberClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // POST でリクエストし, 認可画面を表示
        $crawler = $client->request('POST', $path, $params);

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $location = $client->getResponse()->headers->get('location');
        preg_match('/\?code=(\w+)&state=random_state/', $location, $matched);

        $authorization_code = $matched[1];

        // Token リクエスト
        $crawler = $client->request(
            'POST',
            $this->app->path('oauth2_server_token'),
            array(
                'grant_type' => 'authorization_code',
                'code' => $authorization_code,
                'client_id' => $this->MemberClient->getClientIdentifier(),
                'client_secret' => $this->MemberClient->getClientSecret(),
                'state' => 'random_state',
                'nonce' => 'random_nonce',
                'redirect_uri' => $this->MemberClient->getRedirectUri()
            )
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $TokenResponse = json_decode($client->getResponse()->getContent(), true);

        $this->expected = 3600;
        $this->actual = $TokenResponse['expires_in'];
        $this->verify();

        $this->expected = 'bearer'; // XXX 小文字で返ってくる
        $this->actual = $TokenResponse['token_type'];
        $this->verify();

        $this->expected = $this->scope_granted;
        $this->actual = $TokenResponse['scope'];
        $this->verify();

        $this->assertTrue(array_key_exists('refresh_token', $TokenResponse));

        $access_token = $TokenResponse['access_token'];

        // API Request
        $crawler = $client->request(
                'GET',
                $this->app->path('api_operation_find', array('table' => 'customer', 'id' => $this->Customer->getId())),
                array(),
                array(),
                array(
                    'HTTP_AUTHORIZATION' => 'Bearer '.$access_token,
                    'CONTENT_TYPE' => 'application/json',
                )

            );
        $content = json_decode($client->getResponse()->getContent(), true);

        $this->expected = $this->Customer->getName01();
        $this->actual = $content['customer']['name01'];
        $this->verify();

        $this->expected = $this->Customer->getName02();
        $this->actual = $content['customer']['name02'];
        $this->verify();
    }

    /**
     * Customer で OpenID Connect Authorization code Flow を使用してアクセストークンを取得する.
     */
    public function testOpenIdConnectAuthorizationCodeFlowWithCustomer()
    {
        $this->scope_granted = 'openid customer_read customer_write';

        $client = $this->logInTo($this->Customer);
        $path = $this->app->path('oauth2_server_mypage_authorize');
        $params = array(
                    'client_id' => $this->CustomerClient->getClientIdentifier(),
                    'redirect_uri' => $this->CustomerClient->getRedirectUri(),
                    'response_type' => 'code',
                    'state' => 'random_state',
                    'nonce' => 'random_nonce',
                    'scope' => $this->scope_granted,
        );

        // GET でリクエストし, 認可画面を表示
        $crawler = $client->request('GET', $path, $params);

        // 認可要求
        $params['authorized'] = 1;
        $params['_token'] = 'dummy';
        $crawler = $client->request('POST', $path, $params);

        $location = $client->getResponse()->headers->get('location');
        preg_match('/\?code=(\w+)&state=random_state/', $location, $matched);

        $authorization_code = $matched[1];

        // Token リクエスト
        $crawler = $client->request(
            'POST',
            $this->app->path('oauth2_server_token'),
            array(
                'grant_type' => 'authorization_code',
                'code' => $authorization_code,
                'client_id' => $this->CustomerClient->getClientIdentifier(),
                'client_secret' => $this->CustomerClient->getClientSecret(),
                'state' => 'random_state',
                'nonce' => 'random_nonce',
                'redirect_uri' => $this->CustomerClient->getRedirectUri()
            )
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $TokenResponse = json_decode($client->getResponse()->getContent(), true);

        $this->expected = 3600;
        $this->actual = $TokenResponse['expires_in'];
        $this->verify();

        $this->expected = 'bearer'; // XXX 小文字で返ってくる
        $this->actual = $TokenResponse['token_type'];
        $this->verify();

        $this->expected = $this->scope_granted;
        $this->actual = $TokenResponse['scope'];
        $this->verify();

        // scope=offline_access が無い場合は refresh_token が取得できない
        $this->assertFalse(array_key_exists('refresh_token', $TokenResponse));

        // verify id_token
        $crawler = $client->request(
            'GET',
            $this->app->url('oauth2_server_tokeninfo'),
            array('id_token' => $TokenResponse['id_token']),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json',
            )
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $TokenInfo = json_decode($client->getResponse()->getContent(), true);

        $this->expected = rtrim($this->app->url('homepage'), '/');
        $this->actual = $TokenInfo['iss'];
        $this->verify();

        $this->expected = $this->CustomerClient->getClientIdentifier();
        $this->actual = $TokenInfo['aud'];
        $this->verify();

        $this->expected = $this->CustomerUserInfo->getSub();
        $this->actual = $TokenInfo['sub'];
        $this->verify();

        $PublicKey = $this->app['eccube.repository.oauth2.openid.public_key']->findOneBy(array('UserInfo' => $this->CustomerUserInfo));
        // verify id_token with JWS
        $jwt = \JOSE_JWT::decode($TokenResponse['id_token']);
        $jws = new \JOSE_JWS($jwt);
        try {
            $jws->verify($PublicKey->getPublicKey(), $this->CustomerClient->getEncryptionAlgorithm());
        } catch (\JOSE_Exception_VerificationFailed $e) {
            $this->fail($e->getMessage());
        }

        $access_token = $TokenResponse['access_token'];
        $arrayEntity = EntityUtil::entityToArray($this->app, $this->Customer);
        $faker = $this->getFaker();
        $arrayEntity['kana01'] = $faker->firstKanaName;
        $arrayEntity['kana02'] = $faker->lastKanaName;

        // API Request
        $crawler = $client->request(
            'PUT',
            $this->app->path('api_operation_put', array('table' => 'customer', 'id' => $this->Customer->getId())),
            array(),
            array(),
            array(
                'HTTP_AUTHORIZATION' => 'Bearer '.$access_token,
                'CONTENT_TYPE' => 'application/json',
            ),
            json_encode($arrayEntity)
        );

        $this->expected = 204;
        $this->actual = $this->client->getResponse()->getStatusCode();
        $this->verify();

        $Result = $this->app['eccube.repository.customer']->find($this->Customer->getId());

        $this->expected = $arrayEntity['kana01'];
        $this->actual = $Result->getKana01();
        $this->verify();

        $this->expected = $arrayEntity['kana02'];
        $this->actual = $Result->getKana02();
        $this->verify();
    }
}
