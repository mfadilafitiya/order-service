<?php

namespace App\Services;

use App\Clients\CartClient;
use App\Clients\ProductClient;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderService
{
    protected CartClient $cartClient;
    protected ProductClient $productClient;

    public function __construct(CartClient $cartClient, ProductClient $productClient)
    {
        $this->cartClient = $cartClient;
        $this->productClient = $productClient;
    }

    public function checkout(int $userId)
    {
        // 1. Ambil data keranjang dari cart-service
        $cart = $this->cartClient->getCart($userId);

        if (!isset($cart['items']) || empty($cart['items'])) {
            throw new Exception("Keranjang belanja kosong", 409);
        }

        $itemsToOrder = [];
        $totalAmount = 0;

        // 2. Loop setiap item di keranjang untuk validasi produk dan stok
        foreach ($cart['items'] as $cartItem) {
            $productId = $cartItem['product_id'];
            $quantity = $cartItem['quantity'];

            // Ambil data produk dari product-service
            $product = $this->productClient->getProduct($productId);

            if (!$product) {
                throw new Exception("Produk dengan id {$productId} tidak ditemukan di product-service", 409);
            }

            $stock = $product['stock'];
            if ($stock < $quantity) {
                throw new Exception("Stok {$product['name']} tidak mencukupi: diminta {$quantity}, tersedia {$stock}", 409);
            }

            // Ubah harga dari teks (misal "450000.00") menjadi angka
            $unitPrice = (float) $product['price'];
            $subtotal = $unitPrice * $quantity; // <-- Diperbaiki (ditambah operator *)
            $totalAmount += $subtotal;

            $itemsToOrder[] = [
                'product_id' => $productId,
                'product_name' => $product['name'], // Snapshot nama produk
                'unit_price' => $unitPrice,          // Snapshot harga produk
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        // 3. Simpan pesanan ke database menggunakan transaksi database (DB::transaction)
        $order = DB::transaction(function () use ($userId, $totalAmount, $itemsToOrder) {
            $newOrder = Order::create([
                'user_id' => $userId,
                'status' => 'PENDING',
                'total_amount' => $totalAmount,
            ]);

            foreach ($itemsToOrder as $item) {
                $newOrder->items()->create($item);
            }

            return $newOrder;
        });

        // 4. Kosongkan keranjang via cart-service
        $clearSuccess = $this->cartClient->clearCart($userId);
        if (!$clearSuccess) {
            Log::warning("Pesanan {$order->id} berhasil dibuat, tetapi keranjang user {$userId} gagal dikosongkan.");
        }

        // Load relasi items untuk ditampilkan pada response
        return $order->load('items');
    }

    public function getOrderById(int $orderId)
    {
        $order = Order::with('items')->find($orderId);

        if (!$order) {
            throw new Exception("Pesanan dengan ID {$orderId} tidak ditemukan", 404);
        }

        return $order;
    }

    public function updateOrderStatus(int $orderId, string $status)
    {
        $order = $this->getOrderById($orderId);

        $validStatuses = ['PENDING', 'PAID', 'CANCELLED', 'COMPLETED'];
        $upperStatus = strtoupper($status);

        if (!in_array($upperStatus, $validStatuses)) {
            throw new Exception("Status pesanan tidak valid", 422);
        }

        $order->update([
            'status' => $upperStatus
        ]);

        return $order->load('items');
    }
}