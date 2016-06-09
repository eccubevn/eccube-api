<?php

/*
 * This file is part of the EccubeApi
 *
 * Copyright (C) 2016 LOCKON CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\EccubeApi\Controller;

use Eccube\Application;
use Eccube\Common\Constant;
use Eccube\Entry\AbstractEntity;
use Plugin\EccubeApi\Util\EntityUtil;
use OAuth2\HttpFoundationBridge\Request as BridgeRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * エンティティの CRUD API.
 *
 * @author Kentaro Ohkouchi
 */
class EccubeApiCRUDController extends AbstractApiController
{

    /**
     * すべてデータを返す.
     */
    public function findAll(Application $app, Request $request, $table = null)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }

        $response = $this->doAuthorizedByFind($app, $request, $table);
        if ($response !== true) {
            return $response;
        }
        $className = $metadata->getName();

        $Repository = $app['orm.em']->getRepository($className);
        $Results = array();
        // TODO LIMIT, OFFSET が必要
        foreach ($Repository->findAll() as $Entity) {
            $Results[] = EntityUtil::entityToArray($app, $Entity);
        }

        return $this->getWrapperedResponseBy($app, array($table => $Results));
    }

    /**
     * IDを指定してエンティティを検索する
     */
    public function find(Application $app, Request $request, $table = null, $id = 0)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }
        $response = $this->doAuthorizedByFind($app, $request, $table);
        if ($response !== true) {
            return $response;
        }

        return $this->findEntity($app, $request,
                                 function ($id, $className) use ($app) {
                                     return $app['orm.em']->getRepository($className)->find($id);
                                 },
                                 array($id), $table);
    }

    /**
     * \Eccube\Entity\ProductCategory を検索する.
     */
    public function findProductCategory(Application $app, Request $request, $product_id = 0, $category_id = 0)
    {
        $scope_reuqired = 'product_category_read';
        $is_authorized = $this->verifyRequest($app, $request, $scope_reuqired);
        if ($this->hasBearerTokenHeader($request)) {
            // Bearer トークンが存在する場合は認証チェック
            if (!$is_authorized) {
                return $app['oauth2.server.resource']->getResponse();
            }
        }

        // TODO 検索結果が0件の場合は 404 を返す.
        return $this->findEntity($app, $request,
                                 function ($product_id, $category_id, $className) use ($app) {
                                     return $app['orm.em']->getRepository($className)->findOneBy(
                                         array(
                                             'product_id' => $product_id,
                                             'category_id' => $category_id
                                     ));
                                 },
                                 array($product_id, $category_id), 'product_category');
    }

    /**
     * \Eccube\Entity\PaymentOption を検索する.
     */
    public function findPaymentOption(Application $app, Request $request, $delivery_id = 0, $payment_id = 0)
    {
        $scope_reuqired = 'payment_option_read';
        if (!$this->verifyRequest($app, $request, $scope_reuqired)) {
            return $app['oauth2.server.resource']->getResponse();
        }

        // TODO 検索結果が0件の場合は 404 を返す.
        return $this->findEntity($app, $request,
                                 function ($delivery_id, $payment_id, $className) use ($app) {
                                     return $app['orm.em']->getRepository($className)->findOneBy(
                                         array(
                                             'delivery_id' => $delivery_id,
                                             'payment_id' => $payment_id
                                     ));
                                 },
                                 array($delivery_id, $payment_id), 'payment_option');
    }

    /**
     * \Eccube\Entity\BlockPosition を検索する.
     */
    public function findBlockPosition(Application $app, Request $request, $page_id = 0, $target_id = 0, $block_id = 0)
    {
        $scope_reuqired = 'block_position_read';
        if (!$this->verifyRequest($app, $request, $scope_reuqired)) {
            return $app['oauth2.server.resource']->getResponse();
        }

        // TODO 検索結果が0件の場合は 404 を返す.
        return $this->findEntity($app, $request,
                                 function ($page_id, $target_id, $block_id, $className) use ($app) {
                                     return $app['orm.em']->getRepository($className)->findOneBy(
                                         array(
                                             'page_id' => $page_id,
                                             'target_id' => $target_id,
                                             'block_id' => $block_id
                                     ));
                                 },
                                 array($page_id, $target_id, $block_id), 'block_position');
    }

    /**
     * コールバック関数を指定してエンティティを検索する.
     *
     * @param Application $app
     * @param Request $request
     * @param callable $callback コールバック関数
     * @param array $params_arr コールバック関数の引数
     * @param string $table 検索対象のテーブル名
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    protected function findEntity(Application $app, Request $request, callable $callback = null, array $params_arr = array(), $table = null)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }
        $className = $metadata->getName();
        $params_arr[] = $className;

        $Results = call_user_func_array($callback, $params_arr);

        return $this->getWrapperedResponseBy($app, array($table => EntityUtil::entityToArray($app, $Results)));
    }

    /**
     * エンティティを生成する.
     */
    public function create(Application $app, Request $request, $table = null)
    {
        return $this->createEntity($app, $request, function ($Response, $table, $Entity) use ($app) {
            $Response->headers->set("Location", $app->url('api_operation_find',
                                                          array(
                                                              'table' => $table,
                                                              'id' => $Entity->getId()
                                                          )));

            return;
        }, $table);
    }

    /**
     * \Eccube\Entity\ProductCategory を生成する.
     */
    public function createProductCategory(Application $app, Request $request)
    {
        return $this->createEntity($app, $request, function ($Response, $table, $Entity) use ($app) {
            $Response->headers->set("Location", $app->url('api_operation_find_product_category',
                                                          array(
                                                              'product_id' => $Entity->getProductId(),
                                                              'category_id' => $Entity->getCategoryId()
                                                          )));

            return;
        }, 'product_category');
    }

    /**
     * \Eccube\Entity\PaymentOption を生成する.
     */
    public function createPaymentOption(Application $app, Request $request)
    {
        return $this->createEntity($app, $request, function ($Response, $table, $Entity) use ($app) {
            $Response->headers->set("Location", $app->url('api_operation_find_payment_option',
                                                          array(
                                                              'delivery_id' => $Entity->getDeliveryId(),
                                                              'payment_id' => $Entity->getPaymentId()
                                                          )));

            return;
        }, 'payment_option');
    }

    /**
     * \Eccube\Entity\BlockPosition を生成する.
     */
    public function createBlockPosition(Application $app, Request $request)
    {
        return $this->createEntity($app, $request, function ($Response, $table, $Entity) use ($app) {
            $Response->headers->set("Location", $app->url('api_operation_find_block_position',
                                                          array(
                                                              'page_id' => $Entity->getPageId(),
                                                              'target_id' => $Entity->getTargetId(),
                                                              'block_id' => $Entity->getBlockId()
                                                          )));

            return;
        }, 'block_position');
    }

    /**
     * コールバック関数を指定してエンティティを生成する.
     */
    protected function createEntity(Application $app, Request $request, callable $callback, $table = null)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }

        $scope_reuqired = $table.'_read '.$table.'_write';
        if (!$this->verifyRequest($app, $request, $scope_reuqired)) {
            return $app['oauth2.server.resource']->getResponse();
        }

        $className = $metadata->getName();
        $Entity = new $className;
        EntityUtil::copyRelatePropertiesFromArray($app, $Entity, $request->request->all());

        try {
            $app['orm.em']->persist($Entity);
            $app['orm.em']->flush($Entity);
            $Response = $app['oauth2.server.resource']->getResponse();
            call_user_func($callback, $Response, $table, $Entity);

            return $this->getWrapperedResponseBy($app, array(), 201);
        } catch (\Exception $e) {
            $this->addErrors($app, 400, $e->getMessage());
            return $this->getWrapperedResponseBy($app, $this->getErrors(), 400);
        }
    }

    /**
     * ID を指定してエンティティを更新する.
     */
    public function update(Application $app, Request $request, $table = null, $id = 0)
    {
        return $this->updateEntity($app, $request,
                                 function ($id, $className) use ($app) {
                                     return $app['orm.em']->getRepository($className)->find($id);
                                 },
                                 array($id), $table);

    }

    /**
     * \Eccube\Entity\ProductCategory を更新する.
     */
    public function updateProductCategory(Application $app, Request $request, $product_id = 0, $category_id = 0)
    {
        // TODO エンティティが存在しない場合は 404 を返す.
        return $this->updateEntity($app, $request,
                                   function ($product_id, $category_id, $className) use ($app) {
                                       return $app['orm.em']->getRepository($className)->findOneBy(
                                           array(
                                               'product_id' => $product_id,
                                               'category_id' => $category_id
                                           ));
                                   },
                                   array($product_id, $category_id), 'product_category');
    }

    /**
     * \Eccube\Entity\BlockPosition を更新する.
     */
    public function updateBlockPosition(Application $app, Request $request, $page_id = 0, $target_id = 0, $block_id = 0)
    {
        // TODO エンティティが存在しない場合は 404 を返す.
        return $this->updateEntity($app, $request,
                                   function ($page_id, $target_id, $block_id, $className) use ($app) {
                                       return $app['orm.em']->getRepository($className)->findOneBy(
                                           array(
                                               'page_id' => $page_id,
                                               'target_id' => $target_id,
                                               'block_id' => $block_id
                                           ));
                                   },
                                   array($page_id, $target_id, $block_id), 'block_position');
    }

    /**
     * コールバック関数を指定してエンティティを更新する.
     */
    public function updateEntity(Application $app, Request $request, callable $callback, array $params_arr = array(), $table = null)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }

        $scope_reuqired = $table.'_read '.$table.'_write';
        if (!$this->verifyRequest($app, $request, $scope_reuqired)) {
            return $app['oauth2.server.resource']->getResponse();
        }

        $className = $metadata->getName();
        $params_arr[] = $className;

        $Entity = call_user_func_array($callback, $params_arr);
        EntityUtil::copyRelatePropertiesFromArray($app, $Entity, $request->request->all());
        try {
            $app['orm.em']->flush($Entity);
            return $this->getWrapperedResponseBy($app, null, 204);
        } catch (\Exception $e) {
            $this->addErrors($app, 400, $e->getMessage());
            return $this->getWrapperedResponseBy($app, $this->getErrors(), 400);
        }
    }

    /**
     * エンティティを削除する.
     */
    public function delete(Application $app, Request $request, $table = null, $id = 0)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }
        $scope_reuqired = $table.'_read '.$table.'_write';
        if (!$this->verifyRequest($app, $request, $scope_reuqired)) {
            return $app['oauth2.server.resource']->getResponse();
        }

        if (!array_key_exists('del_flg', $metadata->fieldMappings)) {
            // TODO エラーメッセージ
            return $this->getWrapperedResponseBy($app, $this->getErrors(), 405);
        }
        $className = $metadata->getName();

        $Repository = $app['orm.em']->getRepository($className);
        $Entity = $Repository->find($id);
        try {
            $Entity->setDelFlg(Constant::ENABLED);
            $app['orm.em']->flush($Entity);
        } catch (\Exception $e) {
            return $this->getWrapperedResponseBy($app, $this->getErrors(), 400);
        }

        return $this->getWrapperedResponseBy($app, null, 204);
    }

    /**
     * 認証の必要なテーブルかどうか.
     */
    protected function requireAuthorization($table)
    {
        switch ($table) {
            // 認証不要なテーブル
            case 'news':
            case 'product':
            case 'product_class':
            case 'product_image':
            case 'product_tag':
            case 'product_category':
            case 'job':
            case 'pref':
            case 'sex':
                return false;
            default:
                return true;
        }
    }

    /**
     * 検索メソッドの認証をします.
     *
     * @param Application $app
     * @param Request $request
     * @param string $table テーブル名
     * @return \OAuth2\HttpFoundationBridge\Response|boolean 認証が失敗した場合エラーレスポンス, 成功した場合 true
     */
    protected function doAuthorizedByFind(Application $app, Request $request, $table)
    {
        $metadata = EntityUtil::findMetadata($app, $table);
        if (!is_object($metadata)) {
            return $this->getWrapperedErrorResponseBy($app);
        }

        $scope_reuqired = $table.'_read';
        $is_authorized = $this->verifyRequest($app, $request, $scope_reuqired);
        $AccessToken = $app['oauth2.server.resource']->getAccessTokenData(
            BridgeRequest::createFromRequest($request),
            $app['oauth2.server.resource']->getResponse()
        );
        if ($this->hasBearerTokenHeader($request)
            || $this->requireAuthorization($table)) {
            // Bearer トークンが存在する場合は認証チェック
            if (!$is_authorized) {
                return $app['oauth2.server.resource']->getResponse();
            }

            // Customer で認証すれば参照可能なテーブル
            if ($AccessToken['client']->hasCustomer()) {
                switch ($table) {
                    case 'customer_read':
                    case 'customer_address_read':
                    case 'order_read':
                    case 'order_detail_read':
                        break;
                    default:
                        return $this->getWrapperedErrorResponseBy($app, 'Access Forbidden', 403);
                }
            }
        }
        return true;
    }

    /**
     * Authorization ヘッダの Bearer トークンが存在するかどうか.
     */
    protected function hasBearerTokenHeader(Request $request)
    {
        return preg_match('/Bearer (\w+)/', $request->headers->get('authorization')) > 0;
    }
}
