<?php
declare(strict_types=1);

namespace Doddle\Returns\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\RemoteServiceUnavailableException;
use Magento\Framework\Exception\AuthorizationException;
use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Model\Config\Source\ApiMode;

class Api extends AbstractHelper
{
    const PATH_OAUTH_TOKEN = '/v1/oauth/token';
    const PATH_ORDERS = '/v2/orders/';
    const SCOPE_ORDERS = 'orders:write';

    /** @var DataHelper */
    private $dataHelper;

    /** @var array */
    private $accessTokens = [];

    /** @var Json */
    private $jsonEncoder;

    /** @var CurlFactory */
    private $curlFactory;

    /**
     * @param Data $dataHelper
     * @param Json $jsonEncoder
     * @param CurlFactory $curlFactory
     * @param Context $context
     */
    public function __construct(
        DataHelper $dataHelper,
        Json $jsonEncoder,
        CurlFactory $curlFactory,
        Context $context
    ) {
        parent::__construct($context);
        $this->dataHelper = $dataHelper;
        $this->jsonEncoder = $jsonEncoder;
        $this->curlFactory = $curlFactory;
    }

    /**
     * Post an order to the Doddle Orders API, return the Doddle API order ID if successful
     *
     * @param $orderData
     * @return bool|mixed
     * @throws AuthorizationException
     * @throws RemoteServiceUnavailableException
     */
    public function sendOrder($orderData)
    {
        $jsonOrderData = $this->jsonEncoder->serialize($orderData);

        $accessToken = $this->getAccessToken(
            self::SCOPE_ORDERS
        );

        $response = $this->postRequest(
            self::PATH_ORDERS,
            $jsonOrderData,
            $accessToken
        );

        if (isset($response['resource']['orderId'])) {
            return $response['resource']['orderId'];
        }

        return false;
    }

    /**
     * @param $path
     * @param null $postData
     * @param null $accessToken
     * @return array|bool|float|int|mixed|string|null
     * @throws RemoteServiceUnavailableException
     */
    private function postRequest(
        $path,
        $postData = null,
        $accessToken = null
    ) {
        $curl = $this->curlFactory->create();

        $curl->addHeader('Authorization', 'Bearer ' . $accessToken);
        $curl->addHeader('Content-type', 'application/json');

        $url = $this->getApiUrl($path);

        try {
            $curl->post(
                $url,
                $postData
            );
        } catch (\Exception $e) {
            throw new RemoteServiceUnavailableException(
                __(
                    'Failed to send HTTP POST request: %1 - %2',
                    $url,
                    $e->getMessage()
                )
            );
        }

        if ($curl->getStatus() != 200) {
            throw new RemoteServiceUnavailableException(
                __(
                    'Got HTTP %1 response for request: %2 - %3',
                    $curl->getStatus(),
                    $url,
                    $curl->getBody()
                )
            );
        }

        return $this->jsonEncoder->unserialize($curl->getBody());
    }

    /**
     * @param $scope
     * @return mixed
     * @throws AuthorizationException
     */
    private function getAccessToken($scope)
    {
        if (!isset($this->accessTokens[$scope])) {
            $apiKey = $this->dataHelper->getApiKey();
            $apiSecret = $this->dataHelper->getApiSecret();
            $path = sprintf('%s?api_key=%s', self::PATH_OAUTH_TOKEN, $apiKey);

            $post = [
                'grant_type' => 'client_credentials',
                'scope' => $scope
            ];

            $curl = $this->curlFactory->create();

            $curl->setCredentials(
                $apiKey,
                $apiSecret
            );

            try {
                $curl->post(
                    $this->getApiUrl($path),
                    $post
                );
            } catch (Exception $e) {
                throw new AuthorizationException(
                    __(
                        'Failed to get access token request HTTP auth - %1',
                        $e->getMessage()
                    )
                );
            }

            $response = $this->jsonEncoder->unserialize($curl->getBody());

            // Check the token is valid for requested scope
            if ($this->verifyAccessToken($response, $scope) == false) {
                throw new AuthorizationException(
                    __(
                        'Failed to retrieve valid access token for scope - %1',
                        $scope
                    )
                );
            }

            $this->accessTokens[$scope] = $response['access_token'];
        }

        return $this->accessTokens[$scope];
    }

    /**
     * Confirm access token and valid scope is present.
     * Note this is only implemented for single scope currently.
     *
     * @param $response
     * @param bool $scope
     * @return bool
     */
    private function verifyAccessToken($response, $scope = false)
    {
        if (!isset($response['access_token'])) {
            return false;
        }

        if ($scope && strpos($response['scope'], $scope) === false) {
            return false;
        }

        return true;
    }

    /**
     * @param $path
     * @return string
     */
    private function getApiUrl($path)
    {
        $basePath = ($this->dataHelper->getApiMode() == ApiMode::API_MODE_TEST) ?
            $this->dataHelper->getTestApiUrl() :
            $this->dataHelper->getLiveApiUrl();

        return $basePath . $path;
    }
}
