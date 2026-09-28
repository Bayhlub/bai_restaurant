<?php

// Details printed on the receipt and the address customers reach the app on. Override in .env.
return [
    // Lao name shown beside the English APP_NAME in the logo, on prints and receipts.
    'name_lo' => env('RESTAURANT_NAME_LO', 'ຮ້ານອາຫານ ໄບ'),

    'address' => env('RESTAURANT_ADDRESS', 'Vientiane, Laos'),
    'phone' => env('RESTAURANT_PHONE', ''),
    'receipt_footer_lo' => env('RESTAURANT_FOOTER_LO', 'ຂອບໃຈທີ່ມາອຸດໜູນ'),
    'receipt_footer_en' => env('RESTAURANT_FOOTER_EN', 'Thank you, see you again!'),

    /*
     * Base URL encoded in the table QR codes. Customer phones must be able to
     * reach it, so on a local network this is the PC's LAN IP, not the .test
     * domain that only Herd on this machine can resolve.
     */
    'customer_url' => env('CUSTOMER_URL', env('APP_URL', 'http://localhost')),
];
