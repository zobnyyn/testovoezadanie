<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BalanceController extends Controller
{
    public function __construct(
        private BalanceService $balanceService
    ) {}

    /**
     * Пополнение баланса
     */
    public function deposit(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'comment' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Ошибка валидации',
                    'messages' => $validator->errors()
                ], 422);
            }

            $result = $this->balanceService->deposit(
                $request->input('user_id'),
                $request->input('amount'),
                $request->input('comment')
            );

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
        }
    }

    /**
     * Списание средств
     */
    public function withdraw(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'comment' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Ошибка валидации',
                    'messages' => $validator->errors()
                ], 422);
            }

            $result = $this->balanceService->withdraw(
                $request->input('user_id'),
                $request->input('amount'),
                $request->input('comment')
            );

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
        }
    }

    /**
     * Перевод между пользователями
     */
    public function transfer(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_user_id' => 'required|integer|exists:users,id',
                'to_user_id' => 'required|integer|exists:users,id|different:from_user_id',
                'amount' => 'required|numeric|min:0.01',
                'comment' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Ошибка валидации',
                    'messages' => $validator->errors()
                ], 422);
            }

            $result = $this->balanceService->transfer(
                $request->input('from_user_id'),
                $request->input('to_user_id'),
                $request->input('amount'),
                $request->input('comment')
            );

            return response()->json($result, 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
        }
    }

    /**
     * Получение баланса пользователя
     */
    public function getBalance(int $userId): JsonResponse
    {
        try {
            $result = $this->balanceService->getBalance($userId);
            return response()->json($result, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
        }
    }
}

