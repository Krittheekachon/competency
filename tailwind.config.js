import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

theme: {
        extend: {
            colors: {
                "on-background": "#1b1c1c",
                "primary-fixed-dim": "#ffb4ab",
                "secondary-fixed-dim": "#c8c6c5",
                "on-primary": "#ffffff",
                "primary": "#680006",
                // ... เพิ่มให้ครบตามก้อนสีที่คุณส่งมา
            },
            // ... เพิ่ม fontFamily และ fontSize ด้วยถ้าต้องการให้เป๊ะ 100%
        }
    },

    plugins: [forms],
};
