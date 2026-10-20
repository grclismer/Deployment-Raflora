//
// ================================================================================
// DEVELOPER DOCUMENTATION - tailwind.config.js
// ================================================================================
// Purpose: Tailwind CSS v4 configuration with content paths for Blade files.
// Stack: JavaScript - Tailwind CSS configuration.
// Dependencies:
//   - Tailwind CSS v4
// Process Logic:
//   - Content paths: Scans all .blade.php, .js, and .vue files for class usage
//   - Enables JIT compilation for optimized CSS output
// AI Context: PROJECT_CONTEXT: Frontend-focused Laravel/Tailwind build. AI Analysis integration in progress.
// ================================================================================

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                display: ['"Playfair Display"', 'serif'],
                sans: ['"Inter"', 'sans-serif'],
            },
            colors: {
                raflora: {
                    primary: {
                        50: '#f0fdf4',
                        100: '#dcfce7',
                        200: '#bbf7d0',
                        500: '#78B069', // Raflora Green
                        600: '#16A34A', // Success
                        700: '#15803d',
                        800: '#166534',
                        900: '#14532d',
                    },
                    secondary: {
                        50: '#fffbeb',
                        100: '#fef3c7',
                        500: '#D4AF37', // Gold
                        600: '#d97706',
                        700: '#b45309',
                        800: '#92400e',
                    },
                    navy: {
                        50: '#eff6ff',
                        100: '#dbeafe',
                        500: '#3b82f6',
                        800: '#1e40af',
                        900: '#0F2E5B', // Navy Blue
                    },
                    surface: '#FFFFFF', // White
                    cream: '#FDF8F3', // Cream
                    soft: '#f8fafc',
                    border: '#D1D5DB', // Border
                    text: '#374151', // Body Text
                    muted: '#6B7280', // Muted Text
                    heading: '#0F2E5B', // Heading
                    success: '#16A34A', // Success
                    warning: '#F59E0B', // Warning
                    danger: '#DC2626', // Raflora Red
                },
            },
            boxShadow: {
                rf: '0 10px 24px rgba(15, 23, 42, 0.08)',
                'rf-lg': '0 22px 50px -22px rgba(76, 29, 149, 0.3)',
            },
            borderRadius: {
                rf: '0.75rem',
                'rf-lg': '1rem',
                'rf-xl': '1.5rem',
            },
        },
    },
    plugins: [],
}