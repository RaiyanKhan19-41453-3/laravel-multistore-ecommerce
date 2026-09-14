<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_devices', function (Blueprint $table) {
            $table->string('serial')->primary();
            $table->text('private_key')->nullable()->comment('EC key generated at onboarding, encrypted');
            $table->text('csr')->nullable();
            $table->text('compliance_request_id')->nullable();
            $table->text('csid')->nullable()->comment('BinarySecurityToken, encrypted');
            $table->text('csid_secret')->nullable()->comment('API secret, encrypted');
            $table->text('certificate')->nullable()->comment('X.509 production certificate');
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_devices');
    }
};
