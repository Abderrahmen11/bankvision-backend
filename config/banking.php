<?php

return [

    /*
    |--------------------------------------------------------------------------
    | High-Value Transaction Threshold
    |--------------------------------------------------------------------------
    |
    | Transactions at or above this amount are treated as high-value for AML
    | flagging and high-value reporting purposes.
    |
    */
    'high_value_transaction_threshold' => env('BANKING_HIGH_VALUE_THRESHOLD', 10000),

    /*
    |--------------------------------------------------------------------------
    | Financial Statement Estimates
    |--------------------------------------------------------------------------
    |
    | The Reports module derives projected income statement, balance sheet and
    | cash flow figures from ledger totals using these ratios. They are
    | estimates — the API marks such payloads with `estimated: true` so the
    | frontend can label them accordingly.
    |
    */
    'estimates' => [
        // Defaults are documented in backend/.env.example and are conservative estimates.
        'fee_income_rate'        => env('BANKING_EST_FEE_INCOME_RATE', 0.005),   // fees on wire volume
        'operating_cost_ratio'   => env('BANKING_EST_OPERATING_COST_RATIO', 0.35),
        'credit_provision_rate'  => env('BANKING_EST_CREDIT_PROVISION_RATE', 0.10),
        'corporate_tax_rate'     => env('BANKING_EST_CORPORATE_TAX_RATE', 0.21),

        // Balance sheet composition ratios
        'cash_reserve_ratio'     => env('BANKING_EST_CASH_RESERVE_RATIO', 0.10),
        'other_assets_ratio'     => env('BANKING_EST_OTHER_ASSETS_RATIO', 0.05),
        'customer_deposits_ratio'=> env('BANKING_EST_CUSTOMER_DEPOSITS_RATIO', 0.85),
        'other_liabilities_ratio'=> env('BANKING_EST_OTHER_LIABILITIES_RATIO', 0.05),
        'equity_ratio'           => env('BANKING_EST_EQUITY_RATIO', 0.08),
        'deposit_interest_share' => env('BANKING_EST_DEPOSIT_INTEREST_SHARE', 0.60),
        'operating_cash_share'   => env('BANKING_EST_OPERATING_CASH_SHARE', 0.02),
        'loan_repayment_share'   => env('BANKING_EST_LOAN_REPAYMENT_SHARE', 0.05),
    ],

];
