<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Couriers move from DB rows to config/couriers.php. Shipments keep
     * their history with a plain courier_code string; the courier_id
     * foreign key and the couriers table go away entirely.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        $hasCourierId = Schema::hasColumn('shipments', 'courier_id');
        $hasCourierCode = Schema::hasColumn('shipments', 'courier_code');

        if ($hasCourierId && ! $hasCourierCode) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->string('courier_code')->nullable()->after('courier_id');
                $table->index('courier_code', 'shipments_courier_code_index');
            });

            // Backfill from the rows being removed: lowercase code into the
            // new column, keep the display name snapshot already stored.
            if (Schema::hasTable('couriers')) {
                $codes = DB::table('couriers')->get(['id', 'code'])
                    ->keyBy('id')
                    ->map(fn ($row) => strtolower((string) $row->code));

                foreach (DB::table('shipments')->whereNotNull('courier_id')->orderBy('id')->get() as $shipment) {
                    $code = $codes->get($shipment->courier_id);

                    if ($code !== null) {
                        DB::table('shipments')->where('id', $shipment->id)
                            ->update(['courier_code' => $code]);
                    }
                }
            }
        }

        if ($hasCourierId) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropForeign(['courier_id']);
            });

            // MySQL keeps the FK's backing index after the constraint is
            // gone; SQLite never created one.
            if (Schema::hasIndex('shipments', 'shipments_courier_id_foreign')) {
                Schema::table('shipments', function (Blueprint $table) {
                    $table->dropIndex('shipments_courier_id_foreign');
                });
            }

            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('courier_id');
            });
        }

        Schema::dropIfExists('couriers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shipments') || Schema::hasColumn('shipments', 'courier_id')) {
            return;
        }

        // Recreate exactly the schema history left behind (original create
        // + store_id from 140352 + composite unique from 141516): the older
        // down()s that run later in a full rollback look for
        // couriers_store_code_unique and the store_id FK, and would fail
        // on a plain code unique that never existed at that point.
        if (! Schema::hasTable('couriers')) {
            Schema::create('couriers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code');
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->integer('sort_order')->default(0);
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
                $table->unique(['store_id', 'code'], 'couriers_store_code_unique');
                $table->timestamps();
            });
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('id')->constrained('couriers')->nullOnDelete();
        });

        if (Schema::hasColumn('shipments', 'courier_code')) {
            $codes = DB::table('couriers')->get(['id', 'code'])
                ->keyBy('code')
                ->map(fn ($row) => $row->id);

            foreach (DB::table('shipments')->whereNotNull('courier_code')->orderBy('id')->get() as $shipment) {
                $courierId = $codes->get($shipment->courier_code);

                if ($courierId !== null) {
                    DB::table('shipments')->where('id', $shipment->id)
                        ->update(['courier_id' => $courierId]);
                }
            }

            Schema::table('shipments', function (Blueprint $table) {
                $table->dropIndex('shipments_courier_code_index');
                $table->dropColumn('courier_code');
            });
        }
    }
};
