<?php

return [
    'host' => '202.150.136.53',
    'port' => 22,
    'username' => 'rfid-sum',
    'password' => 'sumtex2025@doit',
    'sudo_password' => 'sumtex2025@doit',
    'services' => [
        'ti_picking' => [
            'label' => 'Restart TI/Picking',
            'service' => 'sum_mqtt_backend.service',
        ],
        'transfer_bale_sby' => [
            'label' => 'Restart Transfer Bale SBY',
            'service' => 'sum_tb_sby',
        ],
        'transfer_bale_bandung' => [
            'label' => 'Restart Transfer Bale Bandung',
            'service' => 'sum_mqtt_2_backend.service',
        ],
    ],
];
