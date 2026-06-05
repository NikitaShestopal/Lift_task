<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class IpLocateClient
{
    const BASE_URL = 'https://www.iplocate.io/api/lookup/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function getCountryByIp(string $ip): ?string
    {
        if (in_array($ip, ['127.0.0.1', 'localhost', '::1'], true)) {
            return 'Localhost';
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $ip, [
                'timeout' => 5.0
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            return $data['country'] ?? 'Unknown Country';

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Помилка iplocate.io для IP %s: %s', $ip, $e->getMessage()));
            throw $e;
        }
    }
}
