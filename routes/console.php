<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('rekon:about', function () {
    $this->info('Rekon Akuntansi - aplikasi rekonsiliasi akuntansi pemerintah daerah.');
});
