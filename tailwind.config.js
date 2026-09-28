import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Enums return class strings (area accents, status badges), so scan them too.
        './app/Enums/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', 'Noto Sans Lao', ...defaultTheme.fontFamily.sans],
                // Headings on the customer-facing pages; the Lao face covers U+0E81–0EDF.
                display: ['Playfair Display', 'Noto Serif Lao', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                cream: {
                    50: '#FDFBF7',
                    100: '#F9F3E9',
                    200: '#F0E6D6',
                    300: '#E3D4BB',
                },
                forest: {
                    50: '#EFF5F1',
                    100: '#D7E6DC',
                    500: '#357F5F',
                    600: '#2A6B4F',
                    700: '#1E5039',
                    800: '#163A2A',
                    900: '#0F2A1E',
                },
                terracotta: {
                    50: '#FDF4EF',
                    100: '#F8E3D6',
                    400: '#D98A66',
                    500: '#C86B43',
                    600: '#AE5530',
                    700: '#8C4326',
                },
            },
        },
    },

    plugins: [forms],
};
