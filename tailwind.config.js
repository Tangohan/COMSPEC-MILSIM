/** Design system Athena — minimaliste haute précision
 *  Palette: fond slate-50, texte slate-900, accents emerald-600
 *  Titres: Sans-Serif (Inter), gras, italique, tracking élevé
 *  Corps: Serif (Source Serif 4), text-slate-600, leading-relaxed
 *  Build : npm install && npm run build:tailwind → public/assets/css/tailwind.css
 *  (voir views/partials/tailwind_cdn_or_build.php — CDN uniquement si le fichier compilé est absent).
 */
module.exports = {
  content: ['views/**/*.php', 'public/**/*.js'],
  /** Classes utilisées depuis des chaînes PHP (ex. vues/account/index.php) — évite un fond blanc si le purge les omet. */
  safelist: [
    /* Tiroir navigation dashboard (évite double colonne si purge) */
    'w-[200%]',
    'w-[min(100%,340px)]',
    'ease-[cubic-bezier(0.33,1,0.68,1)]',
    'shadow-[8px_0_40px_-12px_rgba(15,23,42,0.35)]',
    /* Méga-menu : utilitaires arbitraires du header (filet de sécurité après build Tailwind) */
    'w-[min(60rem,calc(100vw-1.25rem))]',
    'max-w-[calc(100vw-1rem)]',
    'from-violet-500',
    'to-purple-600',
    'from-sky-500',
    'to-blue-600',
    'from-amber-500',
    'to-orange-600',
    'from-emerald-500',
    'to-teal-600',
    'from-rose-500',
    'to-red-600',
    'from-indigo-500',
    'to-indigo-700',
    'group-hover:ring-violet-500/25',
    'group-hover:ring-sky-500/25',
    'group-hover:ring-amber-500/25',
    'group-hover:ring-emerald-500/25',
    'group-hover:ring-rose-500/25',
    'group-hover:ring-indigo-500/25',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        serif: ['"Source Serif 4"', 'Georgia', 'serif'],
      },
      letterSpacing: {
        architect: '0.3em',
        blueprint: '0.5em',
      },
    },
  },
  plugins: [require('@tailwindcss/typography')],
};
