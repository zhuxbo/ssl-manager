<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('acme_authorizations');
        Schema::dropIfExists('acme_certs');
        Schema::dropIfExists('acme_orders');
        Schema::dropIfExists('acme_accounts');
        Schema::dropIfExists('acme_nonces');
        Schema::dropIfExists('free_cert_quotas');
        Schema::dropIfExists('invoice_limits');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // 系统不需要支持回滚
    }
};
