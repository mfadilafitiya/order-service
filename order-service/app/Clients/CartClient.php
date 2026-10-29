<?php

namespace App\Clients;

use App\Exceptions\ServiceUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CartClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.cart.url', env('CART_SERVICE_URL', 'http://cart-service:3002'));
    }

    public function getCart(int $userId): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/carts/{$userId}");

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::error("Cart service mengembalikan status {$response->status()}: " . $response->body());
            throw new ServiceUnavailableException("Cart service");
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Gagal menghubungi cart-service: " . $e->getMessage(), [
                'user_id' => $userId,
                'url' => "{$this->baseUrl}/carts/{$userId}",
            ]);
            throw new ServiceUnavailableException("Cart service");
        }
    }

    public function clearCart(int $userId): bool
    {
        try {
            $response = Http::timeout(5)->delete("{$this->baseUrl}/carts/{$userId}");
            return $response->successful();
        } catch (Throwable $e) {
            Log::warning("Gagal memanggil endpoint clear cart untuk user {$userId}: " . $e->getMessage());
            return false;
        }
    }
}
