<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    protected $table = 'pegawai';
    protected $guarded = ['id'];

    public function pasien()
    {
        return $this->hasMany(Pasien::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
