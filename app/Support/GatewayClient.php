<?php

namespace App\Support;

use Exception;

/**
 * Gateway Client
 *
 * A standalone PHP client for the October CMS Gateway V1 API.
 */
class GatewayClient
{
    /**
     * @var string API base URL
     */
    const API_BASE_URL = 'https://octobercms.com';

    /**
     * @var string API version prefix
     */
    const API_VERSION = 'v1';

    /**
     * @var string|null baseUrl for API requests
     */
    protected $baseUrl;

    /**
     * @var string|null apiKey for HMAC authentication
     */
    protected $apiKey;

    /**
     * @var string|null apiSecret for HMAC authentication
     */
    protected $apiSecret;

    /**
     * @var string|null projectHash for project-level authentication
     */
    protected $projectHash;

    /**
     * @var int timeout in seconds
     */
    protected $timeout = 30;

    public function __construct()
    {
        $this->apiKey = config('october.api_key');
        $this->apiSecret = config('october.api_secret');
        $this->baseUrl = config('october.url', self::API_BASE_URL);
    }

    /**
     * @var array lastResponseHeaders
     */
    protected $lastResponseHeaders = [];

    /**
     * @var int lastStatusCode
     */
    protected $lastStatusCode = 0;

    /**
     * @var string|null lastErrorCode from API response
     */
    protected $lastErrorCode;

    public function setCredentials(string $apiKey, string $apiSecret): self
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        return $this;
    }

    public function listProjects(int $limit = 50, ?string $cursor = null): array
    {
        $params = ['limit' => $limit];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }
        return $this->requestWithHmac('projects/list', $params);
    }

    public function getProject(string $projectId): array
    {
        return $this->requestWithHmac('projects/get', ['project_id' => $projectId]);
    }

    protected function requestWithHmac(string $endpoint, array $params): array
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new Exception('API credentials required for this operation');
        }

        $params['nonce'] = (string) round(microtime(true) * 1000);
        $queryString = http_build_query($params, '', '&');
        $signature = base64_encode(
            hash_hmac('sha512', $queryString, base64_decode($this->apiSecret), true)
        );

        $headers = [
            'Rest-Key: ' . $this->apiKey,
            'Rest-Sign: ' . $signature,
        ];

        return $this->request($endpoint, $params, 'POST', $headers);
    }

    protected function request(string $endpoint, array $params = [], string $method = 'POST', array $headers = []): array
    {
        $url = $this->baseUrl . '/' . self::API_VERSION . '/' . ltrim($endpoint, '/');

        $headers = array_merge([
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ], $headers);

        $ch = curl_init();

        if ($method === 'GET') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }
        else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        $this->lastStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $this->lastResponseHeaders = $this->parseHeaders($headerString);
        $this->lastErrorCode = null;

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . $body);
        }

        if ($this->lastStatusCode >= 400) {
            $this->lastErrorCode = $data['error'] ?? 'unknown_error';
            $message = $data['message'] ?? 'An error occurred';
            throw new Exception($message, $this->lastStatusCode);
        }

        return $data;
    }

    protected function parseHeaders(string $headerString): array
    {
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        return $headers;
    }
}
