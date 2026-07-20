<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Most common list/filter query: household + date descending
            $table->index(['household_id', 'date'], 'transactions_household_date');

            // Household + type (expense/income/transfer) for report and forecast queries
            $table->index(['household_id', 'type'], 'transactions_household_type');

            // Household + category for spending breakdowns
            $table->index(['household_id', 'category_id'], 'transactions_household_category');

            // Account-scoped listing (account detail page, balance calc)
            $table->index(['account_id', 'date'], 'transactions_account_date');

            // Import-batch filtering (post-import categorize flow)
            $table->index(['household_id', 'import_batch'], 'transactions_household_import_batch');
        });

        Schema::table('budgets', function (Blueprint $table) {
            // Budget lookup for a given household + month (dashboard, report)
            $table->index(['household_id', 'month'], 'budgets_household_month');

            // Category spending roll-up per household + category
            $table->index(['household_id', 'category_id'], 'budgets_household_category');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_household_date');
            $table->dropIndex('transactions_household_type');
            $table->dropIndex('transactions_household_category');
            $table->dropIndex('transactions_account_date');
            $table->dropIndex('transactions_household_import_batch');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_household_month');
            $table->dropIndex('budgets_household_category');
        });
    }
};
