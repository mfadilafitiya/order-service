<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use illuminate\support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('PRODUCT_SERVICE_URL', 'http://localhost:3001'); // Default URL if not set in .env
    }

    public function getProduct($productId)
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/products/{$productId}");

            if ($response->status() === 404) {
                return null;
            }
            if ($response->failed()) {
            throw new ServiceUnavailableException("Product service tidak dapat dihubungi. coba lagi nanti.");
            } 

            return $response->json();
        } catch (\Exception $e) {
            if ($e instanceof ServiceUnavailableException) {
                throw $e;
            }
            Log::error("Gagal Menghubungi Product Service: " . $e->getMessage());
            throw new ServiceUnavailableException("Product service tidak dapat dihubungi. coba lagi nanti.");
        }
    }
}