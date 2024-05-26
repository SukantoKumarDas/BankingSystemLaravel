<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class BankingController extends Controller
{
    public function createUser(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'account_type' => 'required|in:Individual,Business',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'account_type' => $request->account_type,
            'balance' => 0,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json($user, 201);
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function showTransactions() {
        $user = Auth::user();
        $transactions = Transaction::where('user_id', $user->id)->get();

        return response()->json([
            'balance' => $user->balance,
            'transactions' => $transactions,
        ]);
    }

    public function showDeposits() {
        $user = Auth::user();
        $deposits = Transaction::where([ 'user_id' => $user->id, 'transaction_type' => 'deposit' ])->get();
        return response()->json($deposits);
    }

    public function deposit(Request $request) {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->balance += $request->amount;
        $user->save();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'deposit',
            'amount' => $request->amount,
            'fee' => 0,
            'date' => now(),
        ]);

        return response()->json($transaction, 201);
    }

    public function showWithdrawals() {
        $user = Auth::user();
        $withdrawals = Transaction::where([ 'user_id' => $user->id, 'transaction_type' => 'withdrawal' ])->get();
        return response()->json($withdrawals);
    }

    public function withdrawal(Request $request) {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $amount = $request->amount;
        $fee = 0;

        // Apply free withdrawal conditions and calculate fee
        if ($user->account_type == 'Individual') {
            // Free withdrawal conditions for Individual accounts
            if (now()->isFriday()) {
                // Free on Fridays
                $fee = 0;
            } else {
                // First 1K is free
                $free_amount = min($amount, 1000);
                $remaining_amount = $amount - $free_amount;

                // Apply fee for the remaining amount
                $fee = $remaining_amount * 0.015 / 100;
            }
        } elseif ($user->account_type == 'Business') {
            // Business accounts have a different fee structure
            $total_withdrawal = $user->transactions()
                ->where('transaction_type', 'withdrawal')
                ->whereMonth('date', now()->month)
                ->sum('amount');

            if ($total_withdrawal > 5000) {
                // First 5K withdrawal each month is free
                $fee = 0;
            } else {
                // Remaining amount will be charged
                $fee = $amount * 0.025 / 100;
            }

            // Decrease the withdrawal fee to 0.015% after a total withdrawal of 50K
            if ($total_withdrawal + $amount > 50000) {
                $fee = $amount * 0.015 / 100;
            }
        }

        // Check if user has enough balance to cover the withdrawal and fee
        if ($user->balance < ($amount + $fee)) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        // Update user's balance by deducting the withdrawn amount and fee
        $user->balance -= ($amount + $fee);
        $user->save();

        // Create a transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'withdrawal',
            'amount' => $amount,
            'fee' => $fee,
            'date' => now(),
        ]);

        return response()->json($transaction, 201);
    }

}
