/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter"', 'ui-sans-serif', 'system-ui'],
                display: ['"Fraunces"', 'ui-serif', 'Georgia'],
            },
            colors: {
                ink: '#1B2521',
                paper: '#F6F4EE',
                moss: {
                    50: '#EEF3ED',
                    100: '#D8E4D5',
                    300: '#9CBB93',
                    500: '#4E7A48',
                    600: '#3E6339',
                    700: '#2F4C2B',
                    900: '#1B2E19',
                },
                rust: {
                    400: '#C97B4A',
                    500: '#B4602F',
                    600: '#96502A',
                },
                clay: '#A8432E',
            },
            borderRadius: {
                xl: '0.85rem',
            },
        },
    },
    plugins: [],
};
