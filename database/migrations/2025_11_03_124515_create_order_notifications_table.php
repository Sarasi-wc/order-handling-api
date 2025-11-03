<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('customer_email');
            $table->string('type'); // 'completed' or 'failed'
            $table->string('status'); // order status at notification time
            $table->decimal('total', 10, 2);
            $table->text('message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['order_id', 'type']);
            $table->index('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};
