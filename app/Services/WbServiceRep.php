<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Support\Facades\Log;

class WbServiceRep
{
    public function __construct(
        private Client $http,
        private string $token,
        private array $rate // ['sleep_ms'=>700,'retries'=>3,'backoff_ms'=>500]
    ) {}

    public function fetchAllGoodsDetailed(string $wbToken, int $limit = 1000): array
    {
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter';

        $limit  = max(1, min(1000, $limit));
        $offset = 0;
        $all    = [];

        while (true) {
            $resp = $this->http->request('GET', $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                ],
                'query' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify' => false,
            ]);

            $code = $resp->getStatusCode();
            $body = (string) $resp->getBody();

            if ($code >= 400) {
                Log::error('[WB] fetchAllGoodsDetailed error', [
                    'code' => $code, 'offset' => $offset, 'limit' => $limit,
                    'body' => mb_substr($body, 0, 2000),
                ]);
                throw new \RuntimeException("WB API error {$code} on list/goods/filter");
            }

            $json = json_decode($body, true);
            $list = $json['data']['listGoods'] ?? [];

            if (empty($list)) break;

            foreach ($list as $g) {
                $sizes = [];
                foreach (($g['sizes'] ?? []) as $s) {
                    $sizes[] = [
                        'sizeID'              => isset($s['sizeID']) ? (int)$s['sizeID'] : null,
                        'price'               => isset($s['price']) ? (float)$s['price'] : null,
                        'discountedPrice'     => isset($s['discountedPrice']) ? (float)$s['discountedPrice'] : null,
                        'clubDiscountedPrice' => isset($s['clubDiscountedPrice']) ? (float)$s['clubDiscountedPrice'] : null,
                        'techSizeName'        => $s['techSizeName'] ?? null,
                    ];
                }

                $all[] = [
                    'nmId'               => isset($g['nmID']) ? (int)$g['nmID'] : 0,
                    'vendorCode'         => (string)($g['vendorCode'] ?? ''),
                    'currencyIsoCode4217'=> (string)($g['currencyIsoCode4217'] ?? ''),
                    'discount'           => isset($g['discount']) ? (int)$g['discount'] : 0,
                    'clubDiscount'       => isset($g['clubDiscount']) ? (int)$g['clubDiscount'] : null,
                    'editableSizePrice'  => (bool)($g['editableSizePrice'] ?? false),
                    'sizes'              => $sizes,
                ];
            }

            if (count($list) < $limit) break;
            $offset += $limit;
        }

        return $all;
    }

    public function setDiscounts(string $wbToken, array $items): array
    {
        $endpoint = 'https://discounts-prices-api.wildberries.ru/api/v2/upload/task';

        try {
            $resp = $this->http->post($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$wbToken}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'data' => $items,
                ],
                'http_errors' => false,
                'timeout' => 60,
                'verify' => false,
            ]);

            $code = $resp->getStatusCode();
            $body = (string)$resp->getBody();
            $json = json_decode($body, true);

            if ($code >= 400) {
                Log::error('[WB] setDiscounts error', [
                    'code' => $code,
                    'body' => mb_substr($body, 0, 2000),
                ]);
                return [
                    'id' => null,
                    'alreadyExists' => false,
                    'error' => true,
                    'errorText' => "HTTP {$code}",
                ];
            }

            if ($json['error']) {
                $errorText = $json['errorText'];
               Log::error('[WB] setDiscounts error', [
                    'body' => $errorText,
                ]);
                return [
                    'id' => null,
                    'alreadyExists' => false,
                    'error' => true,
                    'errorText' => $errorText,
                ]; 
            }

            return [
                'id' => $json['data']['id'] ?? null,
                'alreadyExists' => (bool)($json['data']['alreadyExists'] ?? false),
                'error' => (bool)($json['error'] ?? false),
                'errorText' => $json['errorText'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[WB] setDiscounts exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'id' => null,
                'alreadyExists' => false,
                'error' => true,
                'errorText' => $e->getMessage(),
            ];
        }
    }
}
