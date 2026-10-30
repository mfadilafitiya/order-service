<?php

namespace App\Services;

use App\Exceptions\CartItemNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Models\CartItem;

class CartItemService
{

    public function __construct(private ProductService $productService)
    {
    }

    public function getCart(int $userId)
    {
        $items = CartItem::forUser($userId)->get();

        return [
            'user_id' => $userId,
            'items' => $items,
            'total_quantity'=> $items->sum('quantity')
        ];
    }

    public function addItem(int $userId, int $productId, int $quantity)
    {
        if (!$this->productService->getProduct($productId)) {
            throw new ProductNotFoundException($productId);
        }
        $Item = CartItem::firstOrNew([
            'user_id' => $userId,
            'product_id' => $productId
        ]);

        $Item->quantity = ($Item->exists ? $Item->quantity : 0) + $quantity;

        $Item->save();
        return $Item;
    }

    //update keranjang
    public function updateQuantity(int $id, int $quantity): CartItem
    {
        $item = CartItem::find($id);
        if (!$item) {
            throw new CartItemNotFoundException($id);
        }

        $item->update([
            'quantity' => $quantity
        ]);
        
        return $item;
    }

    public function removeItem(int $id)
    {
        if (CartItem::destroy($id) === 0) {
            throw new CartItemNotFoundException($id);
        }
    }

    public function clearCart(int $userId)
    {
        return CartItem::where('user_id', $userId)->delete();
    }

}
?>