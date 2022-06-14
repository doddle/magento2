<?php
declare(strict_types=1);

namespace Doddle\Returns\Helper;

use Doddle\Returns\Helper\Data as DataHelper;
use Doddle\Returns\Model\Config\Source\ApiMode;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\RemoteServiceUnavailableException;
use Doddle\Returns\Model\HTTP\Client\CurlFactory;
use Doddle\Returns\Model\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class Api extends AbstractHelper
{
    private const PATH_OAUTH_TOKEN = '/v1/oauth/token';

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
     * Make a POST request
     *
     * @param $path
     * @param null $accessScope
     * @param null $data
     * @return array
     * @throws AuthorizationException
     * @throws RemoteServiceUnavailableException
     */
    public function postRequest(
        $path,
        $accessScope = null,
        $data = null
    ): array {
        if ($data !== null) {
            $data = $this->jsonEncoder->serialize($data);
        }

        $accessToken = $this->getAccessToken(
            $accessScope
        );

        /** @var Curl $curl */
        $curl = $this->curlFactory->create();

        $curl->addHeader('Authorization', 'Bearer ' . $accessToken);
        $curl->addHeader('Content-type', 'application/json');
        $curl->addHeader('Expect:', ''); // Avoid HTTP 100 response from API

        $url = $this->getApiUrl($path);

        try {
            $curl->post(
                $url,
                $data
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

        if ($curl->getStatus() !== 200) {
            throw new RemoteServiceUnavailableException(
                __(
                    'Got HTTP %1 response for POST request: %2 - %3',
                    $curl->getStatus(),
                    $url,
                    $curl->getBody()
                )
            );
        }

        return (array) $this->jsonEncoder->unserialize($curl->getBody());
    }

    /**
     * Make a PATCH request
     *
     * @param $path
     * @param null $accessScope
     * @param null $data
     * @return array
     * @throws AuthorizationException
     * @throws RemoteServiceUnavailableException
     */
    public function patchRequest(
        $path,
        $accessScope = null,
        $data = null
    ): array {
        if ($data !== null) {
            $data = $this->jsonEncoder->serialize($data);
        }

        $accessToken = $this->getAccessToken(
            $accessScope
        );

        /** @var Curl $curl */
        $curl = $this->curlFactory->create();

        $curl->addHeader('Authorization', 'Bearer ' . $accessToken);
        $curl->addHeader('Content-type', 'application/json');
        $curl->addHeader('Expect:', ''); // Avoid HTTP 100 response from API

        $url = $this->getApiUrl($path);

        try {
            $curl->patch(
                $url,
                $data
            );
        } catch (\Exception $e) {
            throw new RemoteServiceUnavailableException(
                __(
                    'Failed to send HTTP PATCH request: %1 - %2',
                    $url,
                    $e->getMessage()
                )
            );
        }

        if ($curl->getStatus() !== 200) {
            throw new RemoteServiceUnavailableException(
                __(
                    'Got HTTP %1 response for PATCH request: %2 - %3',
                    $curl->getStatus(),
                    $url,
                    $curl->getBody()
                )
            );
        }

        return (array) $this->jsonEncoder->unserialize($curl->getBody());
    }

    /**
     * Retrieve API access token for a given scope
     *
     * @param $scope
     * @return string
     * @throws AuthorizationException
     */
    private function getAccessToken($scope): string
    {
        if (!isset($this->accessTokens[$scope])) {
            $apiKey = $this->dataHelper->getApiKey();
            $apiSecret = $this->dataHelper->getApiSecret();
            $path = sprintf('%s?api_key=%s', self::PATH_OAUTH_TOKEN, $apiKey);

            $post = [
                'grant_type' => 'client_credentials',
                'scope' => $scope
            ];

            /** @var Curl $curl */
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
            if ($this->verifyAccessToken($response, $scope) === false) {
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
     *
     * Note, this assumes multiple scopes are retuned in the same order they were requested
     *
     * @param $response
     * @param bool $scope
     * @return bool
     */
    private function verifyAccessToken($response, $scope = false): bool
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
     * Product full API URL (test or live) from resource path
     *
     * @param $path
     * @return string
     */
    private function getApiUrl($path): string
    {
        $basePath = ($this->dataHelper->getApiMode() == ApiMode::API_MODE_TEST) ?
            $this->dataHelper->getTestApiUrl() :
            $this->dataHelper->getLiveApiUrl();

        return $basePath . $path;
    }
}
