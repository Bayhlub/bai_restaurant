<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Starter menu. Prices are in LAK. Edit freely from the admin screen later.
     */
    public function run(): void
    {
        $menu = [
            ['ອາຫານຫຼັກ', 'Main Dishes', [
                ['ລາບໄກ່', 'Chicken Laap', 45000],
                ['ລາບໝູ', 'Pork Laap', 45000],
                ['ຕຳໝາກຫຸ່ງ', 'Papaya Salad', 25000],
                ['ປີ້ງໄກ່', 'Grilled Chicken', 50000],
                ['ປີ້ງປາ', 'Grilled Fish', 70000],
                ['ຂົ້ວຜັກບົ້ງ', 'Stir-fried Morning Glory', 20000],
            ]],
            ['ເຝີ ແລະ ເສັ້ນ', 'Noodles', [
                ['ເຝີໄກ່', 'Chicken Pho', 30000],
                ['ເຝີຊີ້ນ', 'Beef Pho', 35000],
                ['ຂ້າວປຽກເສັ້ນ', 'Khao Piak Sen', 30000],
                ['ຜັດໄທ', 'Pad Thai', 35000],
            ]],
            ['ເຂົ້າ', 'Rice', [
                ['ເຂົ້າໜຽວ', 'Sticky Rice', 8000],
                ['ເຂົ້າຈ້າວ', 'Steamed Rice', 8000],
                ['ເຂົ້າຜັດ', 'Fried Rice', 30000],
            ]],
            ['ເຄື່ອງດື່ມ', 'Drinks', [
                ['ເບຍລາວ (ໃຫຍ່)', 'Beer Lao (Large)', 20000],
                ['ນ້ຳດື່ມ', 'Drinking Water', 5000],
                ['ໂຄກ', 'Coke', 10000],
                ['ກາເຟເຢັນ', 'Iced Coffee', 15000],
                ['ນ້ຳໝາກນາວ', 'Lime Juice', 15000],
            ]],
        ];

        foreach ($menu as $sort => [$nameLo, $nameEn, $items]) {
            $category = Category::firstOrCreate(
                ['name_en' => $nameEn],
                ['name_lo' => $nameLo, 'sort_order' => $sort],
            );

            foreach ($items as $itemSort => [$itemLo, $itemEn, $price]) {
                $category->menuItems()->firstOrCreate(
                    ['name_en' => $itemEn],
                    ['name_lo' => $itemLo, 'price' => $price, 'sort_order' => $itemSort],
                );
            }
        }
    }
}
