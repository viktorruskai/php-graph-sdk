<?php
declare(strict_types=1);

/**
 * Copyright 2017 Facebook, Inc.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

namespace Facebook\Exceptions;

use Facebook\FacebookResponse;

/**
 * Class FacebookResponseException
 *
 * @package Facebook
 */
class FacebookResponseException extends FacebookSDKException
{
    /**
     * The response that threw the exception.
     */
    protected FacebookResponse $response;

    /**
     * Decoded response.
     */
    protected array $responseData;

    /**
     * Creates a FacebookResponseException.
     *
     * @param FacebookResponse $response The response that threw the exception.
     * @param FacebookSDKException|null $previousException The more detailed exception.
     */
    public function __construct(FacebookResponse $response, ?FacebookSDKException $previousException = null)
    {
        $this->response = $response;
        $this->responseData = $response->getDecodedBody();

        $errorMessage = $this->get('message', 'Unknown error from Graph.');
        $errorCode = $this->get('code', -1);

        parent::__construct($errorMessage, $errorCode, $previousException);
    }

    /**
     * A factory for creating the appropriate exception based on the response from Graph.
     */
    public static function create(FacebookResponse $response): FacebookResponseException
    {
        $data = $response->getDecodedBody();

        if (!isset($data['error']['code']) && isset($data['code'])) {
            $data = ['error' => $data];
        }

        $code = $data['error']['code'] ?? null;
        $message = $data['error']['message'] ?? 'Unknown error from Graph.';

        if (isset($data['error']['error_subcode'])) {
            switch ($data['error']['error_subcode']) {
                // Other authentication issues
                case 458:
                case 459:
                case 460:
                case 463:
                case 464:
                case 467:
                    return new self($response, new FacebookAuthenticationException($message, $code));
                // Video upload resumable error
                case 1363030:
                case 1363019:
                case 1363033:
                case 1363021:
                case 1363041:
                    return new self($response, new FacebookResumableUploadException($message, $code));
                case 1363037:
                    $previousException = new FacebookResumableUploadException($message, $code);

                    $startOffset = isset($data['error']['error_data']['start_offset']) ? (int)$data['error']['error_data']['start_offset'] : null;
                    $previousException->setStartOffset($startOffset);

                    $endOffset = isset($data['error']['error_data']['end_offset']) ? (int)$data['error']['error_data']['end_offset'] : null;
                    $previousException->setEndOffset($endOffset);

                    return new self($response, $previousException);
            }
        }

        switch ($code) {
            // Login status or token expired, revoked, or invalid
            case 100:
            case 102:
            case 190:
                return new self($response, new FacebookAuthenticationException($message, $code));

            // Server issue, possible downtime
            case 1:
            case 2:
                return new self($response, new FacebookServerException($message, $code));

            // API Throttling
            case 4:
            case 17:
            case 32:
            case 341:
            case 613:
                return new self($response, new FacebookThrottleException($message, $code));

            // Duplicate Post
            case 506:
                return new self($response, new FacebookClientException($message, $code));
        }

        // Missing Permissions
        if ($code === 10 || ($code >= 200 && $code <= 299)) {
            return new self($response, new FacebookAuthorizationException($message, $code));
        }

        // OAuth authentication error
        if (isset($data['error']['type']) && $data['error']['type'] === 'OAuthException') {
            return new self($response, new FacebookAuthenticationException($message, $code));
        }

        // All others
        return new self($response, new FacebookOtherException($message, $code));
    }

    /**
     * Checks isset and returns that or a default value.
     */
    private function get(string $key, mixed $default = null): mixed
    {
        return $this->responseData['error'][$key] ?? $default;
    }

    /**
     * Returns the HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->response->getHttpStatusCode();
    }

    /**
     * Returns the sub-error code
     */
    public function getSubErrorCode(): int
    {
        return $this->get('error_subcode', -1);
    }

    /**
     * Returns the error type
     */
    public function getErrorType(): string
    {
        return $this->get('type', '');
    }

    /**
     * Returns the raw response used to create the exception.
     */
    public function getRawResponse(): string
    {
        return $this->response->getBody();
    }

    /**
     * Returns the decoded response used to create the exception.
     *
     * @noinspection PhpUnused
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * Returns the response entity used to create the exception.
     *
     * @noinspection PhpUnused
     */
    public function getResponse(): FacebookResponse
    {
        return $this->response;
    }
}
