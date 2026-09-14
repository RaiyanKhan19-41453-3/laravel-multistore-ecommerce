<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_counters', function (Blueprint $table) {
            $table->string('device_serial')->primary();
            $table->unsignedBigInteger('last_icv')->default(0);
            $table->timestamps();
        });

        Schema::create('zatca_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->comment('standard, simplified, credit, debit');
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('icv')->comment('Tamper-proof invoice counter per device');
            $table->string('invoice_number');
            $table->text('previous_invoice_hash')->comment('Base64 PIH chain link');
            $table->text('invoice_hash')->nullable()->comment('Base64 SHA-256 of signed XML');
            $table->longText('xml')->comment('UBL 2.1 payload submitted to Fatoora');
            $table->text('qr_payload')->nullable()->comment('Base64 TLV for the printed QR');
            $table->string('status', 20)->default('draft')->comment('draft, signed, reported, cleared, failed');
            $table->json('gateway_response')->nullable();
            $table->unsignedInteger('submit_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_documents');
        Schema::dropIfExists('zatca_counters');
    }
};
