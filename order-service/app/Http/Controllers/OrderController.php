<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Exceptions\ServiceUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function store(Request $request)
    {
        // Validasi input sederhana untuk user_id
        if (!$request->has('user_id') || !is_numeric($request->user_id)) {
            return response()->json([
                'message' => 'Data yang dikirim tidak valid',
                'errors' => [
                    'user_id' => ['Field user_id wajib diisi dan harus berupa angka']
                ]
            ], 422);
        }

        try {
            $order = $this->orderService->checkout((int) $request->user_id);

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            $statusCode = $e->getCode();
            // Pastikan kode status error valid (4xx atau 5xx), jika tidak default ke 500
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            // Jika error dari service lain (503) atau error tak terduga
            if ($e instanceof ServiceUnavailableException) {
                $statusCode = 503;
            }

            Log::error("Gagal melakukan checkout: " . $e->getMessage());

            $response = [
                'message' => $e->getMessage() ?: 'Terjadi kesalahan pada server'
            ];

            // Tampilkan detail teknis jika mode debug aktif
            if (config('app.debug')) {
                $response['debug'] = [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json($response, $statusCode);
        }
    }

    public function show($id)
    {
        try {
            $order = $this->orderService->getOrderById((int) $id);

            return response()->json([
                'message' => 'Detail pesanan berhasil diambil',
                'data' => $order
            ], 200);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 404;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        if (!$request->has('status')) {
            return response()->json([
                'message' => 'Field status wajib diisi'
            ], 422);
        }

        try {
            $order = $this->orderService->updateOrderStatus((int) $id, $request->status);

            return response()->json([
                'message' => 'Status pesanan berhasil diperbarui',
                'data' => $order
            ], 200);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 400;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}