<?php

// Only the rules this app uses; everything else falls back to English.
return [
    'required' => 'ກະລຸນາໃສ່ :attribute.',
    'email' => ':attribute ຕ້ອງເປັນອີເມວທີ່ຖືກຕ້ອງ.',
    'numeric' => ':attribute ຕ້ອງເປັນຕົວເລກ.',
    'integer' => ':attribute ຕ້ອງເປັນຈຳນວນເຕັມ.',
    'min' => [
        'numeric' => ':attribute ຕ້ອງບໍ່ນ້ອຍກວ່າ :min.',
        'string' => ':attribute ຕ້ອງມີຢ່າງໜ້ອຍ :min ຕົວອັກສອນ.',
    ],
    'max' => [
        'numeric' => ':attribute ຕ້ອງບໍ່ເກີນ :max.',
        'string' => ':attribute ຕ້ອງບໍ່ເກີນ :max ຕົວອັກສອນ.',
        'file' => ':attribute ຕ້ອງບໍ່ເກີນ :max ກິໂລໄບ.',
    ],
    'unique' => ':attribute ນີ້ມີຢູ່ແລ້ວ.',
    'image' => ':attribute ຕ້ອງເປັນຮູບພາບ.',
    'confirmed' => 'ການຢືນຢັນ :attribute ບໍ່ກົງກັນ.',
    'current_password' => 'ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ.',
    'attributes' => [
        'email' => 'ອີເມວ',
        'password' => 'ລະຫັດຜ່ານ',
        'name' => 'ຊື່',
        'number' => 'ເລກໂຕະ',
        'seats' => 'ບ່ອນນັ່ງ',
        'itemNameLo' => 'ຊື່ (ລາວ)',
        'itemNameEn' => 'ຊື່ (ອັງກິດ)',
        'itemPrice' => 'ລາຄາ',
        'itemImage' => 'ຮູບ',
        'categoryNameLo' => 'ຊື່ (ລາວ)',
        'categoryNameEn' => 'ຊື່ (ອັງກິດ)',
    ],
];
