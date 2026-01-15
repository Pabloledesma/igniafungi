<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Checking schema...\n";

Schema::table('batches', function (Blueprint $table) {
    if (!Schema::hasColumn('batches', 'expected_yield')) {
        $table->decimal('expected_yield', 10, 2)->default(0)->after('quantity');
        echo "Added expected_yield.\n";
    } else {
        echo "expected_yield exists.\n";
    }

    if (!Schema::hasColumn('batches', 'estimated_harvest_date')) {
        $table->date('estimated_harvest_date')->nullable()->after('status');
        echo "Added estimated_harvest_date.\n";
    } else {
        echo "estimated_harvest_date exists.\n";
    }
});

echo "Done.\n";
