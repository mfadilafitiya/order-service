<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CartClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('CART_SERVICE_URL', 'http://localhost:3002');
    }

    public function getCart($userId)
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/carts/{$userId}");

            if ($response->failed()) {
                throw new ServiceUnavailableException("Cart service tidak dapat dihubungi, coba lagi nanti");
            }

            return $response->json('data');
        } catch (\Exception $e) {
            if ($e instanceof ServiceUnavailableException) {
                throw $e;
            }
            Log::error("Gagal menghubungi cart-service: " . $e->getMessage());
            throw new ServiceUnavailableException("Cart service tidak dapat dihubungi, coba lagi nanti");
        }
    }

    public function clearCart($userId)
    {
        try {
            $response = Http::timeout(5)->delete("{$this->baseUrl}/carts/{$userId}");

            if ($response->failed()) {
                Log::warning("Gagal mengosongkan keranjang untuk user {$userId}");
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Exception saat mengosongkan keranjang: " . $e->getMessage());
            return false;
        }
    }
}