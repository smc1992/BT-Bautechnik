<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Daily Logs (Bautagebuch)
        Schema::create('daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->date('date');
            $table->string('weather')->default('Sonnig'); // Sonnig, Bewölkt, Regen, Frost, Schnee
            $table->string('temperature')->nullable(); // z.B. 22°C
            $table->integer('workers_count')->default(1);
            $table->text('work_performed'); // Geleistete Arbeiten / Gewerk
            $table->text('special_occurrences')->nullable(); // Störungen / Vorkommnisse
            $table->timestamps();
        });

        // 2. Subcontractor & Expense Invoices (Subunternehmer-Eingangsrechnungen & Baukosten)
        Schema::create('subcontractor_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->uuid('contact_id')->nullable(); // Subunternehmer
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->decimal('amount_net', 12, 2);
            $table->string('tax_mode')->default('13b'); // 13b (Reverse Charge), 19 (Standard), 0 (Kleinunternehmer)
            $table->string('status')->default('in_review'); // in_review, approved, paid, rejected
            $table->text('description');
            $table->timestamps();
        });

        // 3. Defects & Acceptance (Mängelmanagement & Abnahmeprotokolle)
        Schema::create('defects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->uuid('assigned_contact_id')->nullable(); // Verantwortlicher Subunternehmer / Partner
            $table->foreign('assigned_contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->string('title');
            $table->string('location')->nullable(); // Bauteil / Stockwerk / Wand
            $table->text('description');
            $table->date('deadline')->nullable(); // Frist zur Beseitigung
            $table->string('priority')->default('mittel'); // niedrig, mittel, hoch, kritisch
            $table->string('status')->default('offen'); // offen, in_bearbeitung, behoben, abgenommen
            $table->timestamps();
        });

        // 4. Company Settings (Firmen-Stammdaten BT Bautechnik UG)
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name')->default('BT Bautechnik UG (haftungsbeschränkt)');
            $table->string('managing_director')->default('Julia Haberzettel');
            $table->string('street')->default('Sollngriesbacher Str. 4');
            $table->string('zip')->default('92334');
            $table->string('city')->default('Berching');
            $table->string('phone')->default('08462 123456');
            $table->string('email')->default('info@bt-bautechnik.de');
            $table->string('website')->default('www.bt-bautechnik.de');
            $table->string('tax_number')->default('110/123/45678');
            $table->string('vat_id')->default('DE345678901');
            $table->string('commercial_register')->default('HRB 12345 AG Nürnberg');
            $table->string('bank_name')->default('Sparkasse Neumarkt-Parsberg');
            $table->string('iban')->default('DE89 7605 0101 0001 2345 67');
            $table->string('bic')->default('BYLADEM1NM');
            $table->text('default_payment_terms')->nullable();
            $table->text('default_offer_text')->nullable();
            $table->text('default_invoice_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('defects');
        Schema::dropIfExists('subcontractor_invoices');
        Schema::dropIfExists('daily_logs');
    }
};
