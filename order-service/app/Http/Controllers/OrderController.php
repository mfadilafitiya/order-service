<?php

namespace App\Http\Controllers;

use App\Exceptions\CartEmptyException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\OrderNotFoundException;
use App\Exceptions\ProductNotFoundException;
use App\Exceptions\ServiceUnavailableException;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\GetOrdersByUserRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService)
    {
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->checkout((int) $request->validated()['user_id']);

            return response()->json([
                'message' => 'Pesanan berhasil dibuat',
                'data' => $order,
            ], 201);
        } catch (CartEmptyException | ProductNotFoundException | InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (ServiceUnavailableException $e) {
            return $this->serviceUnavailableResponse($e);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e, 'membuat pesanan');
        }
    }

    public function show(mixed $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json([
                'message' => "Pesanan dengan id {$id} tidak ditemukan",
            ], 404);
        }

        try {
            $order = $this->orderService->getOrderById((int) $id);

            return response()->json([
                'message' => 'Berhasil mengambil pesanan',
                'data' => $order,
            ], 200);
        } catch (OrderNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e, 'mengambil detail pesanan');
        }
    }

    public function getByUser(GetOrdersByUserRequest $request, mixed $userId): JsonResponse
    {
        if (!is_numeric($userId)) {
            return response()->json([
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        try {
            $status = $request->validated()['status'] ?? null;
            $orders = $this->orderService->getOrdersByUser((int) $userId, $status);

            return response()->json([
                'message' => 'Berhasil mengambil daftar pesanan',
                'data' => $orders,
            ], 200);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e, 'mengambil daftar pesanan');
        }
    }

    public function pay(mixed $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json([
                'message' => "Pesanan dengan id {$id} tidak ditemukan",
            ], 404);
        }

        try {
            $order = $this->orderService->payOrder((int) $id);

            return response()->json([
                'message' => 'Pesanan berhasil dibayar',
                'data' => $order,
            ], 200);
        } catch (OrderNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (InvalidOrderStatusException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e, 'membayar pesanan');
        }
    }

    private function serviceUnavailableResponse(ServiceUnavailableException $e): JsonResponse
    {
        Log::error("Layanan eksternal bermasalah: " . $e->getMessage());

        $body = [
            'message' => $e->getMessage(),
        ];

        if (config('app.debug')) {
            $body['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($body, 503);
    }

    private function serverErrorResponse(Throwable $e, string $action): JsonResponse
    {
        Log::error("Terjadi error tak terduga saat {$action}: " . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $body = [
            'message' => 'Terjadi kesalahan pada server',
        ];

        if (config('app.debug')) {
            $body['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($body, 500);
    }
}
