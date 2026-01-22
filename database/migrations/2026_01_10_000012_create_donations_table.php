<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('donatable');

            $table->string('label')->nullable();
            $table->string('recipient_name');

            // bank_account | duitnow | ewallet
            $table->string('method');

            // Bank
            $table->string('bank_code')->nullable();        // normalized code
            $table->string('bank_name')->nullable();        // optional display
            $table->string('account_number')->nullable();

            // DuitNow
            $table->string('duitnow_type')->nullable();     // mobile|nric|business|passport
            $table->string('duitnow_value')->nullable();

            // E-wallet
            $table->string('ewallet_provider')->nullable(); // tng|grab|shopee|boost|etc
            $table->string('ewallet_handle')->nullable();   // phone/email/merchant-id/username
            $table->text('ewallet_qr_payload')->nullable(); // QR text payload

            $table->string('reference_note')->nullable();

            $table->string('status')->default('unverified'); // unverified|verified|rejected|inactive
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->index();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['donatable_type', 'donatable_id', 'method']);
            $table->index(['recipient_name']);
            $table->index(['status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            // Add CHECK constraint for method
            DB::statement("
                ALTER TABLE donations
                ADD CONSTRAINT donations_method_chk
                CHECK (method IN ('bank_account','duitnow','ewallet'))
            ");

            // Add CHECK constraint for status
            DB::statement("
                ALTER TABLE donations
                ADD CONSTRAINT donations_status_chk
                CHECK (status IN ('unverified','verified','rejected','inactive'))
            ");

            // Add CHECK constraint to enforce required fields by method
            DB::statement("
                ALTER TABLE donations
                ADD CONSTRAINT donations_require_fields_chk
                CHECK (
                    (method = 'bank_account' AND bank_code IS NOT NULL AND account_number IS NOT NULL
                        AND duitnow_type IS NULL AND duitnow_value IS NULL
                        AND ewallet_provider IS NULL AND ewallet_handle IS NULL AND ewallet_qr_payload IS NULL
                    )
                    OR
                    (method = 'duitnow' AND duitnow_type IS NOT NULL AND duitnow_value IS NOT NULL
                        AND bank_code IS NULL AND bank_name IS NULL AND account_number IS NULL
                        AND ewallet_provider IS NULL AND ewallet_handle IS NULL AND ewallet_qr_payload IS NULL
                    )
                    OR
                    (method = 'ewallet' AND ewallet_provider IS NOT NULL
                        AND (ewallet_handle IS NOT NULL OR ewallet_qr_payload IS NOT NULL)
                        AND bank_code IS NULL AND bank_name IS NULL AND account_number IS NULL
                        AND duitnow_type IS NULL AND duitnow_value IS NULL
                    )
                )
            ");

            // Partial unique index: prevent duplicate bank accounts
            DB::statement("
                CREATE UNIQUE INDEX donations_unique_bank
                ON donations (donatable_type, donatable_id, bank_code, account_number)
                WHERE method = 'bank_account'
            ");

            // Partial unique index: prevent duplicate DuitNow
            DB::statement("
                CREATE UNIQUE INDEX donations_unique_duitnow
                ON donations (donatable_type, donatable_id, duitnow_type, duitnow_value)
                WHERE method = 'duitnow'
            ");

            // Partial unique index: prevent duplicate e-wallet by handle
            DB::statement("
                CREATE UNIQUE INDEX donations_unique_ewallet
                ON donations (donatable_type, donatable_id, ewallet_provider, ewallet_handle)
                WHERE method = 'ewallet' AND ewallet_handle IS NOT NULL
            ");

            // Partial unique index: prevent duplicate e-wallet by QR payload
            DB::statement("
                CREATE UNIQUE INDEX donations_unique_ewallet_qr
                ON donations (donatable_type, donatable_id, ewallet_provider, md5(ewallet_qr_payload))
                WHERE method = 'ewallet' AND ewallet_qr_payload IS NOT NULL
            ");

            // Partial unique index: only one default per method per donatable
            DB::statement('
                CREATE UNIQUE INDEX donations_one_default_per_method
                ON donations (donatable_type, donatable_id, method)
                WHERE is_default = true
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
