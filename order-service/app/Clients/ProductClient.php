<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.product.url', env('PRODUCT_SERVICE_URL', 'http://product-services:3000'));
    }

    public function getProduct(int $productId): ?array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/products/{$productId}");

            if ($response->status() === 404) {
                return null;
            }

            if ($response->successful()) {
                $json = $response->json();
                return $json['data'] ?? $json;
            }

            Log::error("Product service mengembalikan status {$response->status()}: " . $response->body());
            throw new ServiceUnavailableException("Product service");
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Gagal menghubungi product-service: " . $e->getMessage(), [
                'product_id' => $productId,
                'url' => "{$this->baseUrl}/products/{$productId}",
            ]);
            throw new ServiceUnavailableException("Product service");
        }
    }
}
