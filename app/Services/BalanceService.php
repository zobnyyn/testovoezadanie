<?php

namespace App\Services;

use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BalanceService
{
    /**
     * Пополнение баланса пользователя
     */
    public function deposit(int $userId, float $amount, ?string $comment = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля');
        }

        return DB::transaction(function () use ($userId, $amount, $comment) {
            $user = User::findOrFail($userId);

            // Получаем или создаем баланс
            $balance = Balance::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0.00]
            );

            // Блокируем запись для обновления
            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            $balanceBefore = $balance->balance;
            $balance->balance += $amount;
            $balance->save();

            // Создаем транзакцию
            Transaction::create([
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balance->balance,
                'comment' => $comment,
            ]);

            return [
                'user_id' => $userId,
                'amount' => $amount,
                'balance' => $balance->balance,
                'comment' => $comment,
            ];
        });
    }

    /**
     * Списание средств с баланса пользователя
     */
    public function withdraw(int $userId, float $amount, ?string $comment = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля');
        }

        return DB::transaction(function () use ($userId, $amount, $comment) {
            $user = User::findOrFail($userId);

            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            if (!$balance) {
                throw new InvalidArgumentException('У пользователя нет баланса');
            }

            if ($balance->balance < $amount) {
                throw new InvalidArgumentException('Недостаточно средств на балансе');
            }

            $balanceBefore = $balance->balance;
            $balance->balance -= $amount;
            $balance->save();

            // Создаем транзакцию
            Transaction::create([
                'user_id' => $userId,
                'type' => 'withdraw',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balance->balance,
                'comment' => $comment,
            ]);

            return [
                'user_id' => $userId,
                'amount' => $amount,
                'balance' => $balance->balance,
                'comment' => $comment,
            ];
        });
    }

    /**
     * Перевод средств между пользователями
     */
    public function transfer(int $fromUserId, int $toUserId, float $amount, ?string $comment = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля');
        }

        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('Нельзя переводить средства самому себе');
        }

        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $comment) {
            $fromUser = User::findOrFail($fromUserId);
            $toUser = User::findOrFail($toUserId);

            // Блокируем балансы для обновления (в порядке ID для избежания deadlock)
            $userIds = [$fromUserId, $toUserId];
            sort($userIds);

            $balances = Balance::whereIn('user_id', $userIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $fromBalance = $balances->get($fromUserId);

            if (!$fromBalance) {
                throw new InvalidArgumentException('У отправителя нет баланса');
            }

            if ($fromBalance->balance < $amount) {
                throw new InvalidArgumentException('Недостаточно средств на балансе');
            }

            // Получаем или создаем баланс получателя
            $toBalance = $balances->get($toUserId);
            if (!$toBalance) {
                $toBalance = Balance::create([
                    'user_id' => $toUserId,
                    'balance' => 0.00,
                ]);
            }

            // Обновляем балансы
            $fromBalanceBefore = $fromBalance->balance;
            $toBalanceBefore = $toBalance->balance;

            $fromBalance->balance -= $amount;
            $fromBalance->save();

            $toBalance->balance += $amount;
            $toBalance->save();

            // Создаем транзакции для обоих пользователей
            Transaction::create([
                'user_id' => $fromUserId,
                'type' => 'transfer_out',
                'amount' => $amount,
                'balance_before' => $fromBalanceBefore,
                'balance_after' => $fromBalance->balance,
                'related_user_id' => $toUserId,
                'comment' => $comment,
            ]);

            Transaction::create([
                'user_id' => $toUserId,
                'type' => 'transfer_in',
                'amount' => $amount,
                'balance_before' => $toBalanceBefore,
                'balance_after' => $toBalance->balance,
                'related_user_id' => $fromUserId,
                'comment' => $comment,
            ]);

            return [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'from_balance' => $fromBalance->balance,
                'to_balance' => $toBalance->balance,
                'comment' => $comment,
            ];
        });
    }

    /**
     * Получение баланса пользователя
     */
    public function getBalance(int $userId): array
    {
        $user = User::findOrFail($userId);
        $balance = Balance::where('user_id', $userId)->first();

        return [
            'user_id' => $userId,
            'balance' => $balance ? $balance->balance : '0.00',
        ];
    }
}

