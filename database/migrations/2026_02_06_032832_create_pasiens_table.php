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
        Schema::create('pasien', function (Blueprint $table) {
            $table->id();
            $table->string('reg_id');
            $table->string('nomor_tiket');
            $table->string('nik');
            $table->string('nama');
            $table->date('tgl_lahir');
            $table->string('jenis_kelamin');
            $table->string('rt_rw');
            $table->string('kel');
            $table->string('kec');
            $table->string('kab');
            $table->string('faskes');
            $table->string('no_wa');
            $table->string('register_date');
            $table->string('tahun');
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pasien');
    }
};
