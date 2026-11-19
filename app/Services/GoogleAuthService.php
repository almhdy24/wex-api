<?php

namespace App\Services;

use App\Exceptions\ValidationFailedException;
use Config\Services;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * GoogleAuthService
 *
 * Verifies Google OAuth ID tokens against Google JWKS public keys.
 */
class GoogleAuthService
{
    protected const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    protected const CACHE_KEY = 'google_oauth_jwks_certs';
    protected const CACHE_TTL = 3600; // 1 hour fallback

    protected array $allowedAudiences;
    protected ?array $mockKeys = null;

    public function __construct(?array $mockKeys = null)
    {
        $rawAuds = env('GOOGLE_CLIENT_IDS', '');
        $this->allowedAudiences = array_values(array_filter(array_map('trim', explode(',', $rawAuds))));
        $this->mockKeys = $mockKeys;
    }

    /**
     * Allows test suites to inject mock public keys for offline verification.
     */
    public function setMockKeys(?array $keys): void
    {
        $this->mockKeys = $keys;
    }

    /**
     * Verifies a Google ID token and returns structured profile information.
     *
     * @return array{google_id: string, email: string, name: ?string, avatar_url: ?string}
     * @throws ValidationFailedException
     */
    public function verifyIdToken(string $idToken): array
    {
        if (trim($idToken) === '') {
            throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
        }

        try {
            $jwks = $this->getJwks();
            $keys = JWK::parseKeySet($jwks);

            // Decode token using RS256 keys
            $payload = JWT::decode($idToken, $keys);

            // 1. Verify Issuer
            $validIssuers = ['accounts.google.com', 'https://accounts.google.com'];
            if (!in_array($payload->iss ?? '', $validIssuers, true)) {
                throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
            }

            // 2. Verify Audience (if GOOGLE_CLIENT_IDS configured)
            if (!empty($this->allowedAudiences)) {
                $aud = $payload->aud ?? '';
                if (!in_array($aud, $this->allowedAudiences, true)) {
                    throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
                }
            }

            // 3. Verify Expiration
            if (!isset($payload->exp) || $payload->exp < time()) {
                throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
            }

            // 4. Verify Email and email_verified
            if (empty($payload->email)) {
                throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
            }

            $emailVerified = $payload->email_verified ?? false;
            // Handle both boolean true and string "true"
            if ($emailVerified !== true && $emailVerified !== 'true') {
                throw new ValidationFailedException(['id_token' => 'البريد الإلكتروني لحساب جوجل غير مؤكد']);
            }

            return [
                'google_id'  => (string) $payload->sub,
                'email'      => strtolower(trim((string) $payload->email)),
                'name'       => isset($payload->name) ? trim((string) $payload->name) : null,
                'avatar_url' => isset($payload->picture) ? trim((string) $payload->picture) : null,
            ];
        } catch (ValidationFailedException $vfe) {
            throw $vfe;
        } catch (\Throwable $e) {
            throw new ValidationFailedException(['id_token' => lang('Api.google_auth_failed')]);
        }
    }

    /**
     * Retrieves Google's public JWKS certificates with caching.
     */
    protected function getJwks(): array
    {
        if ($this->mockKeys !== null) {
            return $this->mockKeys;
        }

        $cache = Services::cache();
        $cached = $cache->get(self::CACHE_KEY);

        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $client = Services::curlrequest([
            'timeout' => 5,
        ]);

        $response = $client->get(self::CERTS_URL);
        $body = json_decode($response->getBody(), true);

        if (!is_array($body) || empty($body['keys'])) {
            throw new \RuntimeException('Failed to fetch Google JWKS certificates.');
        }

        // Cache for 1 hour
        $cache->save(self::CACHE_KEY, $body, self::CACHE_TTL);

        return $body;
    }
}
