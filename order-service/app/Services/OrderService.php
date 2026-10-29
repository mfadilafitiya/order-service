<?php

namespace App\Services;

use App\Clients\CartClient;
use App\Clients\ProductClient;
use App\Exceptions\CartEmptyException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderService
{
    public function __construct(
        protected CartClient $cartClient,
        protected ProductClient $productClient
    ) {
    }

    public function checkout(int $userId): Order
    {
        $cart = $this->cartClient->getCart($userId);

        if (!isset($cart['items']) || empty($cart['items'])) {
            throw new CartEmptyException('Keranjang belanja kosong');
        }

        $itemsToOrder = [];
        $totalAmount = 0.0;

        foreach ($cart['items'] as $cartItem) {
            $productId = (int) $cartItem['product_id'];
            $quantity = (int) $cartItem['quantity'];

            $product = $this->productClient->getProduct($productId);

            if (!$product) {
                throw new ProductNotFoundException("Produk dengan id {$productId} tidak ditemukan");
            }

            $stock = (int) ($product['stock'] ?? 0);
            if ($stock < $quantity) {
                throw new InsufficientStockException($product['name'] ?? 'Produk', $quantity, $stock);
            }

            $unitPrice = (float) $product['price'];
            $subtotal = $unitPrice * $quantity;
            $totalAmount += $subtotal;

            $itemsToOrder[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        $order = DB::transaction(function () use ($userId, $totalAmount, $itemsToOrder) {
            $newOrder = Order::create([
                'user_id' => $userId,
                'status' => 'PENDING',
                'total_amount' => $totalAmount,
            ]);

            $newOrder->items()->createMany($itemsToOrder);

            return $newOrder;
        });

        try {
            $cleared = $this->cartClient->clearCart($userId);
            if (!$cleared) {
                Log::warning("Pesanan {$order->id} berhasil dibuat, tetapi keranjang user {$userId} gagal dikosongkan.");
            }
        } catch (Throwable $e) {
            Log::warning("Pesanan {$order->id} berhasil dibuat, tetapi terjadi error saat mengosongkan keranjang user {$userId}: " . $e->getMessage());
        }

        return $order->load('items');
    }

    public function getOrderById(int $orderId): Order
    {
        $order = Order::with('items')->find($orderId);

        if (!$order) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    public function getOrdersByUser(int $userId, ?string $status = null): Collection
    {
        $query = Order::with('items')->where('user_id', $userId);

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function payOrder(int $orderId): Order
    {
        $order = $this->getOrderById($orderId);

        if ($order->status !== 'PENDING') {
            throw new InvalidOrderStatusException($order->status);
        }

        $order->update([
            'status' => 'PAID',
        ]);

        return $order->load('items');
    }
}
