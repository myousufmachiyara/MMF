<?php

namespace App\Services;

use App\Models\ChartOfAccounts;
use App\Models\Voucher;
use Carbon\Carbon;

/**
 * Checks whether a customer has breached their credit-limit or credit-days
 * threshold before a new invoice is raised.
 *
 * Usage (from any controller):
 *
 *   $result = CreditLimitService::check($customerId, $invoiceDate);
 *   if ($result['blocked']) {
 *       return back()->with('credit_blocked', $result['message']);
 *   }
 */
class CreditLimitService
{
    /**
     * Run both credit checks for the given customer.
     *
     * @param  int         $customerId   ChartOfAccounts.id
     * @param  string|null $invoiceDate  Date of the new invoice (Y-m-d). Defaults to today.
     */
    public static function check(int $customerId, ?string $invoiceDate = null): array
    {
        $account   = ChartOfAccounts::findOrFail($customerId);
        $checkDate = $invoiceDate ? Carbon::parse($invoiceDate) : Carbon::today();

        $warnings         = [];
        $limitBreached    = false;
        $daysBreached     = false;
        $daysSincePayment = null;
        $lastPaymentDate  = null;

        // ── 1. Outstanding balance check ─────────────────────────────────────
        // Outstanding = total invoiced (receivables) minus total paid (payables).
        // These fields on ChartOfAccounts are updated whenever a sale invoice
        // or receipt voucher is posted against this customer account.
        $outstanding = (float) $account->receivables - (float) $account->payables;

        if ($account->credit_limit > 0 && $outstanding > $account->credit_limit) {
            $limitBreached = true;
            $overBy        = number_format($outstanding - $account->credit_limit, 2);
            $warnings[]    = "Outstanding balance " . number_format($outstanding, 2)
                           . " exceeds credit limit " . number_format($account->credit_limit, 2)
                           . " (over by {$overBy}).";
        }

        // ── 2. Credit-days check ─────────────────────────────────────────────
        // A "receipt" voucher records a customer payment:
        //   ac_dr_sid = cash / bank account   (debit — money comes in)
        //   ac_cr_sid = customer COA account  (credit — clears the receivable)
        //
        // So the last payment date = latest receipt voucher where ac_cr_sid
        // matches this customer's COA id.
        if ($account->credit_days > 0) {
            $lastPayment = self::getLastPaymentDate($customerId);

            if ($lastPayment) {
                $lastPaymentDate  = $lastPayment->toDateString();
                $daysSincePayment = (int) $lastPayment->diffInDays($checkDate);

                if ($daysSincePayment > $account->credit_days) {
                    $daysBreached = true;
                    $warnings[]   = "Last payment was {$daysSincePayment} day(s) ago"
                                  . " (limit: {$account->credit_days} day(s),"
                                  . " last payment: {$lastPaymentDate}).";
                }
            }
            // No prior payment on record — we don't block, just skip the days check.
        }

        // ── 3. Build response ────────────────────────────────────────────────
        // Hard-block only when BOTH limits are breached simultaneously.
        // If you want to block on either condition alone, change && to ||.
        $blocked = $limitBreached && $daysBreached;

        $message = '';
        if ($blocked) {
            $message = "⛔ Invoice blocked for \"{$account->name}\": "
                     . implode(' | ', $warnings);
        } elseif ($warnings) {
            $message = "⚠️ Credit warning for \"{$account->name}\": "
                     . implode(' | ', $warnings);
        }

        return [
            'blocked'            => $blocked,
            'limit_breached'     => $limitBreached,
            'days_breached'      => $daysBreached,
            'outstanding'        => $outstanding,
            'credit_limit'       => (float) $account->credit_limit,
            'credit_days'        => (int)   $account->credit_days,
            'days_since_payment' => $daysSincePayment,
            'last_payment_date'  => $lastPaymentDate,
            'message'            => $message,
            'warnings'           => $warnings,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Returns the Carbon date of the most recent receipt voucher posted against
    // this customer, or null if no payment has ever been recorded.
    //
    // Receipt voucher double-entry:
    //   DR  cash / bank   (ac_dr_sid)
    //   CR  customer COA  (ac_cr_sid)  ← this is the customer payment side
    // ─────────────────────────────────────────────────────────────────────────
    private static function getLastPaymentDate(int $customerId): ?Carbon
    {
        $date = Voucher::where('voucher_type', 'receipt')
            ->where('ac_cr_sid', $customerId)
            ->latest('date')        // order by the voucher date, not created_at
            ->value('date');

        return $date ? Carbon::parse($date) : null;
    }
}