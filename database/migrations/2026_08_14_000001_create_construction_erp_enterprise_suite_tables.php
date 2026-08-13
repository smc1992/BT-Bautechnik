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
        // 1. Role in users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('admin'); // admin, manager (Bauleiter), worker (Monteur)
            }
            if (!Schema::hasColumn('users', 'hourly_rate')) {
                $table->decimal('hourly_rate', 8, 2)->default(0.00);
            }
        });

        // 2. Cumulative invoice fields
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'previous_invoiced_amount')) {
                $table->decimal('previous_invoiced_amount', 12, 2)->default(0.00);
            }
            if (!Schema::hasColumn('invoices', 'previous_paid_amount')) {
                $table->decimal('previous_paid_amount', 12, 2)->default(0.00);
            }
            if (!Schema::hasColumn('invoices', 'withholding_tax_rate')) {
                $table->decimal('withholding_tax_rate', 5, 2)->default(0.00); // 15% § 48b EStG Bauabzugsteuer
            }
            if (!Schema::hasColumn('invoices', 'dunning_interest')) {
                $table->decimal('dunning_interest', 10, 2)->default(0.00);
            }
        });

        // 3. Supplements table (Nachtragsmanagement VOB/B § 2)
        if (!Schema::hasTable('supplements')) {
            Schema::create('supplements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('supplement_number'); // e.g. NT-01, NT-02
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('reason')->default('scope_change'); // scope_change, unforeseen, client_request, obstruction
                $table->decimal('amount_net', 12, 2)->default(0.00);
                $table->decimal('vat_rate', 5, 2)->default(19.00);
                $table->decimal('amount_gross', 12, 2)->default(0.00);
                $table->string('status')->default('draft'); // draft, submitted, approved, rejected, billed
                $table->date('submission_date')->nullable();
                $table->date('approval_date')->nullable();
                $table->string('created_by')->nullable();
                $table->json('attachments')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Measurements table (Aufmaßblätter VOB/C DIN 18299)
        if (!Schema::hasTable('measurements')) {
            Schema::create('measurements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('measurement_number'); // e.g. AM-2026-001
                $table->string('title');
                $table->date('measurement_date');
                $table->string('location_area')->nullable(); // e.g. 1. OG / Tiefgarage / Bauteil B
                $table->string('status')->default('draft'); // draft, checked, approved, invoiced
                $table->decimal('total_amount_net', 12, 2)->default(0.00);
                $table->string('inspector_name')->nullable();
                $table->string('client_representative')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Measurement Items (Aufmaßzeilen mit Formeln)
        if (!Schema::hasTable('measurement_items')) {
            Schema::create('measurement_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('measurement_id')->constrained('measurements')->onDelete('cascade');
                $table->integer('position_index')->default(1);
                $table->string('item_code')->nullable(); // Pos. 01.01
                $table->string('description');
                $table->string('unit')->default('m²'); // m², m, m³, Stk., Std.
                $table->decimal('length', 10, 3)->nullable();
                $table->decimal('width', 10, 3)->nullable();
                $table->decimal('height', 10, 3)->nullable();
                $table->decimal('factor', 8, 3)->default(1.000);
                $table->decimal('deduction', 10, 3)->default(0.000); // Abzug nach VOB (z.B. Fenster > 2.5m²)
                $table->decimal('quantity', 12, 3)->default(0.000);
                $table->decimal('unit_price', 10, 2)->default(0.00);
                $table->decimal('total_price', 12, 2)->default(0.00);
                $table->string('room_or_axis')->nullable();
                $table->timestamps();
            });
        }

        // 6. Time Entries (Mobile MiLoG Zeiterfassung)
        if (!Schema::hasTable('time_entries')) {
            Schema::create('time_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignUuid('project_id')->nullable()->constrained('projects')->onDelete('set null');
                $table->date('entry_date');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->integer('break_minutes')->default(0);
                $table->decimal('hours', 6, 2)->default(0.00);
                $table->string('activity_type')->default('construction'); // construction, travel, preparation, regie, warranty
                $table->string('trade')->nullable(); // Gewerk: Abdichtung, Estrich, Rohbau...
                $table->text('description')->nullable();
                $table->string('status')->default('submitted'); // submitted, approved, payroll_processed
                $table->timestamps();
            });
        }

        // 7. Project Plans & Documents with Revision Index (Bauplanverwaltung)
        if (!Schema::hasTable('project_plans')) {
            Schema::create('project_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('plan_number')->nullable(); // e.g. AR-101
                $table->string('title');
                $table->string('category')->default('architecture'); // architecture, structural, tga, fire_safety, permit
                $table->string('revision_index')->default('Index 0'); // Index 0, Index A, Index B
                $table->string('file_path');
                $table->string('file_name');
                $table->bigInteger('file_size')->default(0);
                $table->string('file_mime')->nullable();
                $table->date('plan_date')->nullable();
                $table->string('uploaded_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 8. Equipment & Fleet Management (Geräte-, Maschinen- & Fuhrpark mit UVV)
        if (!Schema::hasTable('equipment')) {
            Schema::create('equipment', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('inventory_number'); // e.g. GER-001
                $table->string('name');
                $table->string('category')->default('machine'); // machine, tool, vehicle, drying, safety
                $table->string('manufacturer')->nullable();
                $table->string('model')->nullable();
                $table->string('serial_number')->nullable();
                $table->foreignUuid('current_project_id')->nullable()->constrained('projects')->onDelete('set null');
                $table->string('status')->default('available'); // available, on_site, in_repair, retired
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_price', 10, 2)->default(0.00);
                $table->date('next_uvv_inspection')->nullable(); // DGUV V3 / UVV Prüfung
                $table->date('next_tuev_inspection')->nullable(); // TÜV für Fahrzeuge
                $table->string('photo_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('project_plans');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('measurement_items');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('supplements');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['previous_invoiced_amount', 'previous_paid_amount', 'withholding_tax_rate', 'dunning_interest']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'hourly_rate']);
        });
    }
};
