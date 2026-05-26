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
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                'logo-zoom': {
                    '0%':   { transform: 'scale(0.15) rotate(0deg)',    opacity: '0',  animationTimingFunction: 'ease-out' },
                    '3%':   { transform: 'scale(0.15) rotate(40deg)',   opacity: '1',  animationTimingFunction: 'ease-in-out' },
                    '8%':   { transform: 'scale(0.7)  rotate(115deg)',               animationTimingFunction: 'ease-in-out' },
                    '17%':  { transform: 'scale(1.9)  rotate(244deg)',               animationTimingFunction: 'ease-in-out' },
                    '25%':  { transform: 'scale(0.7)  rotate(360deg)',               animationTimingFunction: 'ease-in-out' },
                    '33%':  { transform: 'scale(0.15) rotate(480deg)',               animationTimingFunction: 'ease-in-out' },
                    '41%':  { transform: 'scale(0.7)  rotate(590deg)',               animationTimingFunction: 'ease-in-out' },
                    '50%':  { transform: 'scale(1.9)  rotate(720deg)',               animationTimingFunction: 'ease-in-out' },
                    '58%':  { transform: 'scale(0.7)  rotate(840deg)',               animationTimingFunction: 'ease-in-out' },
                    '66%':  { transform: 'scale(0.15) rotate(960deg)',               animationTimingFunction: 'ease-in-out' },
                    '74%':  { transform: 'scale(0.7)  rotate(1070deg)',              animationTimingFunction: 'ease-in-out' },
                    '83%':  { transform: 'scale(1.9)  rotate(1200deg)',              animationTimingFunction: 'ease-in-out' },
                    '91%':  { transform: 'scale(0.7)  rotate(1315deg)',              animationTimingFunction: 'ease-in-out' },
                    '100%': { transform: 'scale(1)    rotate(1440deg)' },
                },
                'logo-spin': {
                    '0%':   { transform: 'translate(0, 0)        rotate(0deg)' },
                    '13%':  { transform: 'translate(28px, -8px)  rotate(18deg)' },
                    '25%':  { transform: 'translate(5px, 20px)   rotate(-4deg)' },
                    '38%':  { transform: 'translate(-28px, -8px) rotate(-18deg)' },
                    '50%':  { transform: 'translate(0, 0)        rotate(0deg)' },
                    '63%':  { transform: 'translate(28px, -8px)  rotate(18deg)' },
                    '75%':  { transform: 'translate(5px, 20px)   rotate(-4deg)' },
                    '88%':  { transform: 'translate(-28px, -8px) rotate(-18deg)' },
                    '100%': { transform: 'translate(0, 0)        rotate(0deg)' },
                },
                'logo-explode': {
                    '0%':   { transform: 'scale(1)',  opacity: '1' },
                    '40%':  { transform: 'scale(2.5)', opacity: '1' },
                    '100%': { transform: 'scale(14)', opacity: '0' },
                },
                'logo-flash': {
                    '0%':   { opacity: '0' },
                    '20%':  { opacity: '0.95' },
                    '100%': { opacity: '0' },
                },
                'logo-ring': {
                    '0%':   { transform: 'scale(0.3)', opacity: '0.7' },
                    '100%': { transform: 'scale(3.5)', opacity: '0' },
                },
                'popup-enter': {
                    '0%':   { transform: 'scale(0.8)', opacity: '0' },
                    '100%': { transform: 'scale(1)',   opacity: '1' },
                },
                'text-grow': {
                    '0%':   { transform: 'scale(0.05)', opacity: '0' },
                    '25%':  { opacity: '1' },
                    '100%': { transform: 'scale(1)',    opacity: '1' },
                },
                'text-fall': {
                    '0%':  { opacity: '0', transform: 'translate(0, 0) rotate(0deg)',                                                                                          animationTimingFunction: 'ease-out' },
                    '6%':  { opacity: '1', transform: 'translate(calc(var(--tx,0px)*.05), calc(var(--peak,-200px)*.05)) rotate(calc(var(--rot,10deg)*.05))',                    animationTimingFunction: 'ease-out' },
                    '40%': { opacity: '1', transform: 'translate(calc(var(--tx,0px)*.45), var(--peak,-200px)) rotate(calc(var(--rot,10deg)*.35))',                              animationTimingFunction: 'ease-in' },
                    '80%': { opacity: '0.5', transform: 'translate(calc(var(--tx,0px)*.85), calc(var(--peak,-200px) * -0.6)) rotate(calc(var(--rot,10deg)*.75))' },
                    '100%':{ opacity: '0', transform: 'translate(var(--tx,0px), calc(var(--peak,-200px) * -1.4)) rotate(var(--rot,10deg))' },
                },
            },
            animation: {
                'logo-zoom':    'logo-zoom    3s linear forwards',
                'logo-spin':    'logo-spin    1.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
                'logo-explode': 'logo-explode 0.6s ease-in forwards',
                'logo-flash':   'logo-flash   0.6s ease-out forwards',
                'logo-ring':    'logo-ring    0.6s ease-out forwards',
                'popup-enter':  'popup-enter  0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
                'text-grow':    'text-grow    2.3s cubic-bezier(0.22, 1, 0.36, 1) forwards',
                'text-fall':    'text-fall    1.8s linear forwards',
            },
        },
    },

    plugins: [forms],
};
