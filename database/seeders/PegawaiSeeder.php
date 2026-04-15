<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PegawaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@baruharjo',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
        
        $dataPegawai = [
            ['nama' => 'dr. Riana Widyastuti', 'email' => 'riana@baruharjo'],
            ['nama' => 'Sulihwita Ghufriana', 'email' => 'sulihwita@baruharjo'],
            ['nama' => 'Karyasri', 'email' => 'karyasri@baruharjo'],
            ['nama' => 'Rike Dwi Anggraini', 'email' => 'rike@baruharjo'],
            ['nama' => 'Vinda Dian Saputra', 'email' => 'vinda@baruharjo'],
            ['nama' => 'Jovi Pradana', 'email' => 'jovi@baruharjo'],
            ['nama' => 'Desta Ria Vanika', 'email' => 'desta@baruharjo'],
            ['nama' => 'Muhamad Syarifuddin', 'email' => 'syarifuddin@baruharjo'],
            ['nama' => 'Zullaiha', 'email' => 'zullaiha@baruharjo'],
            ['nama' => 'Dina Maretnawati', 'email' => 'dina@baruharjo'],
            ['nama' => 'Yayuk Indah Kusumawati', 'email' => 'yayuk@baruharjo'],
            ['nama' => 'Susi Rahayu', 'email' => 'susi@baruharjo'],
            ['nama' => 'Noni Nurwidayanti', 'email' => 'noni@baruharjo'],
            ['nama' => 'Lia Roehanatul Mardliyah', 'email' => 'lia@baruharjo'],
            ['nama' => 'Wartini', 'email' => 'wartini@baruharjo'],
            ['nama' => 'Adi Mulyono', 'email' => 'adi@baruharjo'],
            ['nama' => 'Hasan Supriyono', 'email' => 'hasan@baruharjo'],
            ['nama' => 'Siti Nur Aisiyah', 'email' => 'siti@baruharjo'],
            ['nama' => 'Dewi Mustika Sari', 'email' => 'dewi@baruharjo'],
            ['nama' => 'Koirun Nadifah', 'email' => 'koirun@baruharjo'],
            ['nama' => 'Suprapto', 'email' => 'suprapto@baruharjo'],
            ['nama' => 'Sri Budi Artini', 'email' => 'sri@baruharjo'],
            ['nama' => 'Darmani', 'email' => 'darmani@baruharjo'],
            ['nama' => 'Dyah Suntari', 'email' => 'dyah@baruharjo'],
            ['nama' => 'Sri Utari', 'email' => 'utari@baruharjo'],
            ['nama' => 'Muhammad Riski Dwi Adi Nugraha', 'email' => 'riski@baruharjo'],
            ['nama' => 'Erin Chairudina Sa\'adah', 'email' => 'erin@baruharjo'],
            ['nama' => 'Destya Wieke Enantiomery', 'email' => 'wieke@baruharjo'],
            ['nama' => 'Dian Wahyu Susanti', 'email' => 'dian@baruharjo'],
            ['nama' => 'dr. Mufita Sulistyorini', 'email' => 'mufita@baruharjo'],
            ['nama' => 'Indah Purwanti', 'email' => 'indah@baruharjo'],
            ['nama' => 'Ahmad Budi', 'email' => 'budi@baruharjo'],
            ['nama' => 'Mohamad Romadon', 'email' => 'romadon@baruharjo'],
            ['nama' => 'Martha Sulistiyo Rini', 'email' => 'martha@baruharjo'],
            ['nama' => 'Sisfandi', 'email' => 'sisfandi@baruharjo'],
            ['nama' => 'Rita Permatasari', 'email' => 'rita@baruharjo'],
            ['nama' => 'Aries Siswo Eko Utomo', 'email' => 'aries@baruharjo'],
            ['nama' => 'Kisma Choirunnisa Mindanik', 'email' => 'kisma@baruharjo'],
            ['nama' => 'Azizah Putri Andini', 'email' => 'azizah@baruharjo'],
            ['nama' => 'Sulistriyani', 'email' => 'sulistriyani@baruharjo'],
            ['nama' => 'Kusnul Kotimah', 'email' => 'kusnul@baruharjo'],
            ['nama' => 'Nurlatipah', 'email' => 'nurlatipah@baruharjo'],
            ['nama' => 'Friza Anindya Wahyu Weningtyas', 'email' => 'friza@baruharjo'],
            ['nama' => 'Ninik Tugiyatun', 'email' => 'ninik@baruharjo'],
            ['nama' => 'Muhammad Syaifulloh Mahdzur', 'email' => 'syaifulloh@baruharjo'],
            ['nama' => 'Umi Ratnaningsih', 'email' => 'umi@baruharjo'],
            ['nama' => 'Suseno', 'email' => 'suseno@baruharjo'],
            ['nama' => 'Windarwati', 'email' => 'windarwati@baruharjo'],
            ['nama' => 'Arintha Bharata Wijaya', 'email' => 'arintha@baruharjo'],
            ['nama' => 'Yanu Herabiyantari', 'email' => 'yanu@baruharjo'],
            ['nama' => 'Asrori Zain', 'email' => 'asrori@baruharjo'],
            ['nama' => 'Lailatul Hazizah', 'email' => 'hazizah@baruharjo'],
            ['nama' => 'Dony Kamseno', 'email' => 'dony@baruharjo'],
            ['nama' => 'Yeni Rahmawati', 'email' => 'yeni@baruharjo'],
            ['nama' => 'Wahyu Aji Widhi Atmoko', 'email' => 'wahyu@baruharjo'],
            ['nama' => 'Dily Puji Lestari Kurniawati', 'email' => 'dily@baruharjo'],
            ['nama' => 'Nur Asiyah', 'email' => 'asiyah@baruharjo'],
            ['nama' => 'Christin Anggarita', 'email' => 'christin@baruharjo'],
            ['nama' => 'Bibit Heru Prabowo', 'email' => 'bibit@baruharjo'],
            ['nama' => 'Hendri Yanuar Firmansyah', 'email' => 'hendri@baruharjo'],
            ['nama' => 'Herlina Utami Dran', 'email' => 'herlina@baruharjo'],
            ['nama' => 'Prima Guscahyawan Al Hakim', 'email' => 'prima@baruharjo'],
            ['nama' => 'Septian Hadi Santosa', 'email' => 'septian@baruharjo'],
            ['nama' => 'Nur Abidin', 'email' => 'abidin@baruharjo'],
            ['nama' => 'Nurul Alfiah', 'email' => 'nurul@baruharjo'],
            ['nama' => 'Mohammad Nashrulloh Alhabib', 'email' => 'nashrulloh@baruharjo'],
            ['nama' => 'Ilham Bahrul Huda', 'email' => 'huda@baruharjo'],
            ['nama' => 'Eko Purwanto', 'email' => 'eko@baruharjo'],
            ['nama' => 'Aang Ridwan', 'email' => 'aang@baruharjo'],
            ['nama' => 'Dessy Nurfitasari', 'email' => 'dessy@baruharjo'],
        ];

        foreach ($dataPegawai as $data) {
            // Create pegawai record
            $pegawai = Pegawai::create([
                'nama' => $data['nama'],
            ]);

            // Create user account linked to pegawai
            User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'user',
                'pegawai_id' => $pegawai->id,
            ]);
        }
    }
}
